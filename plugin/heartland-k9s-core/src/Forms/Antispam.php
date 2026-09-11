<?php
/**
 * Anti-spam protections shared by admin-post and REST submissions.
 *
 * - nonce (`hk9_nonce`, action `hk9_form_{id}`)
 * - honeypot (`hk9_website` must be empty)
 * - time-trap (`hk9_ts`: >= 3 s and <= 6 h old; signed into the token so it cannot be forged)
 * - single-use token (`hk9_token` = random + HMAC over form|random|ts; claimed atomically once
 *   validation passed and released again when the submission is neither stored nor mailed)
 * - per-IP-hash rate limit (successful sends per hour; keyed hash, never stored)
 *
 * No IP addresses or submitted data are persisted or logged by this class.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Antispam {

	public const MIN_AGE = 3;
	public const MAX_AGE = 6 * HOUR_IN_SECONDS;

	private const TOKEN_TTL  = 6 * HOUR_IN_SECONDS;
	private const RATE_WINDOW = HOUR_IN_SECONDS;

	/** Nonce action for a form id. */
	public static function nonce_action( string $form ): string {
		return 'hk9_form_' . $form;
	}

	/**
	 * Issue a fresh timestamp + token pair for a rendered form.
	 *
	 * @return array{ts:string,token:string}
	 */
	public static function issue( string $form ): array {
		$ts     = (string) time();
		$random = bin2hex( random_bytes( 16 ) );
		return [
			'ts'    => $ts,
			'token' => $random . '.' . self::sign( $form, $random, $ts ),
		];
	}

	private static function sign( string $form, string $random, string $ts ): string {
		return substr( hash_hmac( 'sha256', $form . '|' . $random . '|' . $ts, wp_salt( 'nonce' ) ), 0, 24 );
	}

	/**
	 * Run the pre-validation checks. Returns null when everything passes, otherwise
	 * `['code' => ..., 'status' => int, 'message' => ..., 'refresh' => bool]`.
	 *
	 * @param array<string,mixed> $input Unslashed request data.
	 */
	public static function check( string $form, array $input, string $ip ): ?array {
		$nonce = is_string( $input['hk9_nonce'] ?? null ) ? $input['hk9_nonce'] : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $form ) ) ) {
			return [
				'code'    => 'nonce',
				'status'  => 403,
				'message' => __( 'Your session has expired. Please reload the page and try again.', 'heartland-k9s-core' ),
				'refresh' => false,
			];
		}

		$honeypot = $input['hk9_website'] ?? '';
		if ( is_array( $honeypot ) || '' !== trim( (string) $honeypot ) ) {
			return [
				'code'    => 'honeypot',
				'status'  => 400,
				'message' => __( 'We could not verify your submission. Please try again.', 'heartland-k9s-core' ),
				'refresh' => false,
			];
		}

		$ts    = is_string( $input['hk9_ts'] ?? null ) ? $input['hk9_ts'] : '';
		$token = is_string( $input['hk9_token'] ?? null ) ? $input['hk9_token'] : '';
		if ( ! self::token_is_valid( $form, $token, $ts ) ) {
			return [
				'code'    => 'token',
				'status'  => 400,
				'message' => __( 'Your form session was not recognised. Please reload the page and try again.', 'heartland-k9s-core' ),
				'refresh' => true,
			];
		}

		$age = time() - (int) $ts;
		if ( $age < self::MIN_AGE ) {
			return [
				'code'    => 'too_fast',
				'status'  => 400,
				'message' => __( 'That was quick! Please review your message and send it again.', 'heartland-k9s-core' ),
				'refresh' => false,
			];
		}
		if ( $age > self::MAX_AGE ) {
			return [
				'code'    => 'expired',
				'status'  => 400,
				'message' => __( 'This form has been open for a while. Please try sending again.', 'heartland-k9s-core' ),
				'refresh' => true,
			];
		}

		if ( self::is_used( $token ) ) {
			return self::duplicate();
		}

		if ( self::is_rate_limited( $ip ) ) {
			return [
				'code'    => 'rate_limited',
				'status'  => 429,
				'message' => __( 'Too many messages have been sent from your connection recently. Please try again later.', 'heartland-k9s-core' ),
				'refresh' => false,
			];
		}

		return null;
	}

	/** Structural + signature check of a token for the given form and timestamp. */
	public static function token_is_valid( string $form, string $token, string $ts ): bool {
		if ( ! preg_match( '/^[0-9]{9,11}$/', $ts ) || ! preg_match( '/^([a-f0-9]{32})\.([a-f0-9]{24})$/', $token, $m ) ) {
			return false;
		}
		return hash_equals( self::sign( $form, $m[1], $ts ), $m[2] );
	}

	/** Random part of a token (safe for query strings / transient keys), or '' when malformed. */
	public static function token_key( string $token ): string {
		return preg_match( '/^([a-f0-9]{32})\./', $token, $m ) ? $m[1] : '';
	}

	/** The neutral duplicate response (the token was already claimed by another request from the same page). */
	public static function duplicate(): array {
		return [
			'code'    => 'duplicate',
			'status'  => 409,
			'message' => __( 'This form was already submitted from this page. Please reload the page and try again.', 'heartland-k9s-core' ),
			'refresh' => false,
		];
	}

	public static function is_used( string $token ): bool {
		$key = self::token_key( $token );
		return '' !== $key && false !== get_transient( 'hk9_form_used_' . $key );
	}

	/**
	 * Claim a token atomically (single use): true when this request is the
	 * first to claim it, false when it was already claimed. Two simultaneous
	 * submits with the same token therefore cannot both pass.
	 *
	 * With an external object cache the claim is wp_cache_add() (an atomic
	 * "add" on memcached/redis); otherwise an INSERT of the transient row
	 * (add_option() fails when the row exists). Release with release() when the
	 * submission is not accepted after all.
	 */
	public static function claim( string $token ): bool {
		$key = self::token_key( $token );
		if ( '' === $key ) {
			return false;
		}
		$name = 'hk9_form_used_' . $key;
		if ( wp_using_ext_object_cache() ) {
			return (bool) wp_cache_add( $name, 1, 'transient', self::TOKEN_TTL );
		}
		$timeout_option = '_transient_timeout_' . $name;
		$value_option   = '_transient_' . $name;
		$timeout        = (int) get_option( $timeout_option );
		if ( $timeout > 0 && $timeout < time() ) {
			// Expired leftovers not yet garbage-collected: clear so the row can be inserted again.
			delete_option( $value_option );
			delete_option( $timeout_option );
		}
		if ( ! add_option( $value_option, '1', '', 'no' ) ) {
			return false;
		}
		update_option( $timeout_option, (string) ( time() + self::TOKEN_TTL ), 'no' );
		return true;
	}

	/** Give a claimed token back (the submission was neither stored nor mailed). */
	public static function release( string $token ): void {
		$key = self::token_key( $token );
		if ( '' !== $key ) {
			delete_transient( 'hk9_form_used_' . $key );
		}
	}

	/** Mark a token as consumed (single use). Prefer claim() on write paths. */
	public static function mark_used( string $token ): void {
		$key = self::token_key( $token );
		if ( '' !== $key ) {
			set_transient( 'hk9_form_used_' . $key, 1, self::TOKEN_TTL );
		}
	}

	/**
	 * Client IP for rate limiting.
	 *
	 * REMOTE_ADDR is used unless the request comes from a trusted proxy
	 * (settings `forms.trusted_proxies`, IPs/CIDRs, off by default) AND the
	 * configured header (`forms.proxy_header`, e.g. X-Forwarded-For or
	 * CF-Connecting-IP) carries a valid address. For X-Forwarded-For style
	 * lists the right-most address that is not itself a trusted proxy wins, so
	 * a client cannot spoof its position by prepending values.
	 */
	public static function client_ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ip     = $remote;

		$proxies = Config::trusted_proxies();
		$header  = Config::proxy_header();
		if ( '' !== $remote && [] !== $proxies && '' !== $header && self::ip_in_list( $remote, $proxies ) ) {
			$server_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );
			$raw        = isset( $_SERVER[ $server_key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) ) : '';
			if ( '' !== $raw ) {
				$candidates = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
				for ( $i = count( $candidates ) - 1; $i >= 0; $i-- ) {
					$candidate = (string) filter_var( $candidates[ $i ], FILTER_VALIDATE_IP );
					if ( '' === $candidate ) {
						break; // Malformed entry: stop trusting the chain, keep REMOTE_ADDR.
					}
					if ( self::ip_in_list( $candidate, $proxies ) ) {
						continue; // Another hop of ours; look further left.
					}
					$ip = $candidate;
					break;
				}
			}
		}

		/**
		 * Filters the client IP used for rate limiting (e.g. behind a trusted reverse proxy).
		 *
		 * @param string $ip     Resolved IP.
		 * @param string $remote REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'hk9/forms/client_ip', $ip, $remote );
		return (string) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Whether an IP matches a list of IPs / CIDR ranges (IPv4 and IPv6).
	 *
	 * @param string[] $list
	 */
	public static function ip_in_list( string $ip, array $list ): bool {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.
		if ( false === $packed ) {
			return false;
		}
		$bits_total = strlen( $packed ) * 8;
		foreach ( $list as $entry ) {
			$entry = trim( (string) $entry );
			if ( '' === $entry ) {
				continue;
			}
			[ $net, $prefix ] = array_pad( explode( '/', $entry, 2 ), 2, null );
			$net_packed       = @inet_pton( (string) $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $net_packed || strlen( $net_packed ) !== strlen( $packed ) ) {
				continue;
			}
			$bits = null === $prefix ? $bits_total : (int) $prefix;
			if ( $bits < 0 || $bits > $bits_total ) {
				continue;
			}
			$bytes = intdiv( $bits, 8 );
			$rest  = $bits % 8;
			if ( $bytes > 0 && substr( $packed, 0, $bytes ) !== substr( $net_packed, 0, $bytes ) ) {
				continue;
			}
			if ( $rest > 0 ) {
				$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
				if ( ( ord( $packed[ $bytes ] ) & $mask ) !== ( ord( $net_packed[ $bytes ] ) & $mask ) ) {
					continue;
				}
			}
			return true;
		}
		return false;
	}

	/** Salted (daily) hash of an IP; the only IP-derived value ever stored. */
	public static function ip_hash( string $ip ): string {
		if ( '' === $ip ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) . '|' . gmdate( 'Y-m-d' ) );
	}

	/**
	 * Rate-limit transient key: a keyed hash of the IP that does not roll over
	 * with the daily storage salt (so the hourly window is not reset at UTC
	 * midnight). Never stored on a submission.
	 */
	private static function rate_key( string $ip ): string {
		return 'hk9_form_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) . '|rate' ), 0, 32 );
	}

	public static function is_rate_limited( string $ip ): bool {
		$limit = Config::rate_limit();
		if ( 0 === $limit || '' === $ip ) {
			return false;
		}
		$count = (int) get_transient( self::rate_key( $ip ) );
		return $count >= $limit;
	}

	/** Count a successful send against the IP hash. */
	public static function record_send( string $ip ): void {
		if ( '' === $ip || 0 === Config::rate_limit() ) {
			return;
		}
		$key   = self::rate_key( $ip );
		$count = (int) get_transient( $key );
		if ( 0 === $count ) {
			set_transient( $key, 1, self::RATE_WINDOW );
			return;
		}
		// Keep the original window: read the stored timeout when available (object cache aware fallback = full window).
		$timeout   = (int) get_option( '_transient_timeout_' . $key );
		$remaining = $timeout > 0 ? max( 1, $timeout - time() ) : self::RATE_WINDOW;
		set_transient( $key, $count + 1, $remaining );
	}
}
