<?php
/**
 * Anti-spam protections shared by admin-post and REST submissions.
 *
 * - nonce (`hk9_nonce`, action `hk9_form_{id}`)
 * - honeypot (`hk9_website` must be empty)
 * - time-trap (`hk9_ts`: >= 3 s and <= 6 h old; signed into the token so it cannot be forged)
 * - single-use token (`hk9_token` = random + HMAC over form|random|ts; marked used on success)
 * - per-IP-hash rate limit (successful sends per hour, hashed with a daily salt)
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
			return [
				'code'    => 'duplicate',
				'status'  => 409,
				'message' => __( 'We have already received this submission. Thank you!', 'heartland-k9s-core' ),
				'refresh' => false,
			];
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

	public static function is_used( string $token ): bool {
		$key = self::token_key( $token );
		return '' !== $key && false !== get_transient( 'hk9_form_used_' . $key );
	}

	/** Mark a token as consumed (single use). */
	public static function mark_used( string $token ): void {
		$key = self::token_key( $token );
		if ( '' !== $key ) {
			set_transient( 'hk9_form_used_' . $key, 1, self::TOKEN_TTL );
		}
	}

	/** Client IP (REMOTE_ADDR only; use the filter behind a trusted proxy). */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		/**
		 * Filters the client IP used for rate limiting (e.g. behind a trusted reverse proxy).
		 *
		 * @param string $ip
		 */
		$ip = (string) apply_filters( 'hk9/forms/client_ip', $ip );
		return (string) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/** Salted (daily) hash of an IP; the only IP-derived value ever stored. */
	public static function ip_hash( string $ip ): string {
		if ( '' === $ip ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) . '|' . gmdate( 'Y-m-d' ) );
	}

	private static function rate_key( string $ip ): string {
		return 'hk9_form_rl_' . substr( self::ip_hash( $ip ), 0, 32 );
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
