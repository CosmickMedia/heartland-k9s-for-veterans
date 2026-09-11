<?php
/**
 * Forms settings access.
 *
 * Reads the `forms.*` (and a few `links.*`) keys through Settings\Store::get()
 * (or hk9_option()) when the Settings module is present and falls back to the
 * raw `hk9_settings` option or built-in defaults otherwise, so the forms keep
 * working in any boot state.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Config {

	/** Reference contact-form subjects (value => label), used when settings are empty. */
	public static function default_subjects(): array {
		return [
			'veteran-application' => __( 'Veteran Application Inquiry', 'heartland-k9s-core' ),
			'donation'            => __( 'Donation Inquiry', 'heartland-k9s-core' ),
			'volunteer'           => __( 'Volunteer / Campaign', 'heartland-k9s-core' ),
			'provider'            => __( 'K9 Provider Partnership', 'heartland-k9s-core' ),
			'other'               => __( 'Other', 'heartland-k9s-core' ),
		];
	}

	/**
	 * Read a dot-notation settings key.
	 *
	 * @param string $key     e.g. `forms.rate_limit`.
	 * @param mixed  $default Returned when the key is missing/empty.
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$value = null;
		if ( class_exists( 'HK9\\Core\\Settings\\Store' ) && method_exists( 'HK9\\Core\\Settings\\Store', 'get' ) ) {
			$value = \HK9\Core\Settings\Store::get( $key, $default );
		} elseif ( function_exists( 'hk9_option' ) ) {
			$value = hk9_option( $key, $default );
		} else {
			$settings = get_option( 'hk9_settings', [] );
			$value    = is_array( $settings ) ? self::walk( $settings, $key ) : null;
		}
		if ( null === $value || '' === $value || [] === $value ) {
			return $default;
		}
		return $value;
	}

	private static function walk( array $data, string $path ): mixed {
		$node = $data;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return null;
			}
			$node = $node[ $segment ];
		}
		return $node;
	}

	/**
	 * Recipient list for a form (`forms.contact_recipients` / `forms.application_recipients`),
	 * one address per line or comma-separated; invalid addresses dropped; admin_email fallback.
	 *
	 * @return string[]
	 */
	public static function recipients( string $form ): array {
		$raw  = self::get( 'forms.' . $form . '_recipients', '' );
		$list = [];
		if ( is_array( $raw ) ) {
			$raw = implode( "\n", array_map( 'strval', $raw ) );
		}
		foreach ( preg_split( '/[\r\n,;]+/', (string) $raw ) ?: [] as $candidate ) {
			$email = sanitize_email( trim( $candidate ) );
			if ( '' !== $email && is_email( $email ) ) {
				$list[ strtolower( $email ) ] = $email;
			}
		}
		if ( [] === $list && 'contact' !== $form ) {
			// Documented fallback: other forms use the contact recipients before the admin email.
			return self::recipients( 'contact' );
		}
		if ( [] === $list ) {
			$admin = sanitize_email( (string) get_option( 'admin_email' ) );
			if ( is_email( $admin ) ) {
				$list[ strtolower( $admin ) ] = $admin;
			}
		}
		/**
		 * Filters the recipient list of a form.
		 *
		 * @param string[] $recipients Validated addresses.
		 * @param string   $form       Form id.
		 */
		return array_values( (array) apply_filters( 'hk9/forms/recipients', array_values( $list ), $form ) );
	}

	/** Subject options for the contact form (value => label). */
	public static function subjects(): array {
		$rows = self::get( 'forms.subjects', [] );
		$out  = [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				$value = sanitize_title( (string) ( $row['value'] ?? $label ) );
				if ( '' === $label || '' === $value ) {
					continue;
				}
				$out[ $value ] = $label;
			}
		}
		if ( [] === $out ) {
			$out = self::default_subjects();
		}
		return $out;
	}

	/** Successful sends allowed per IP hash per hour. 0 = unlimited. */
	public static function rate_limit(): int {
		$limit = self::get( 'forms.rate_limit', 5 );
		return max( 0, (int) $limit );
	}

	/** Days to keep stored submissions. 0 = keep forever. */
	public static function retention_days(): int {
		return max( 0, (int) self::get( 'forms.retention_days', 90 ) );
	}

	/**
	 * Trusted reverse-proxy addresses (IPs / CIDRs) from `forms.trusted_proxies`
	 * (one per line). Empty = off: REMOTE_ADDR is always the client.
	 *
	 * @return string[]
	 */
	public static function trusted_proxies(): array {
		$raw = self::get( 'forms.trusted_proxies', '' );
		if ( is_array( $raw ) ) {
			$raw = implode( "\n", array_map( 'strval', $raw ) );
		}
		$out = [];
		foreach ( preg_split( '/[\r\n,;\s]+/', (string) $raw ) ?: [] as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			[ $net, $prefix ] = array_pad( explode( '/', $entry, 2 ), 2, null );
			if ( false === filter_var( $net, FILTER_VALIDATE_IP ) ) {
				continue;
			}
			if ( null !== $prefix && ( ! ctype_digit( $prefix ) || (int) $prefix > ( str_contains( $net, ':' ) ? 128 : 32 ) ) ) {
				continue;
			}
			$out[] = $entry;
		}
		return array_values( array_unique( $out ) );
	}

	/** Header carrying the client IP behind a trusted proxy (`forms.proxy_header`, e.g. X-Forwarded-For); '' = none. */
	public static function proxy_header(): string {
		$header = (string) self::get( 'forms.proxy_header', 'X-Forwarded-For' );
		return (string) preg_replace( '/[^A-Za-z0-9\-]/', '', $header );
	}

	public static function store_submissions(): bool {
		$value = self::get( 'forms.store_submissions', true );
		return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * From name/email for outgoing mail: settings, else site name + WordPress' own default sender.
	 *
	 * @return array{name:string,email:string}
	 */
	public static function from(): array {
		$name  = sanitize_text_field( (string) self::get( 'forms.from_name', '' ) );
		$email = sanitize_email( (string) self::get( 'forms.from_email', '' ) );
		if ( '' === $name ) {
			$name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			$host = (string) wp_parse_url( network_home_url(), PHP_URL_HOST );
			if ( str_starts_with( $host, 'www.' ) ) {
				$host = substr( $host, 4 );
			}
			$email = 'wordpress@' . ( '' !== $host ? $host : 'localhost' );
			if ( ! is_email( $email ) ) {
				// e.g. "localhost" without a TLD: fall back to the administrator address.
				$email = sanitize_email( (string) get_option( 'admin_email' ) );
			}
		}
		return [
			'name'  => $name,
			'email' => $email,
		];
	}

	public static function contact_success_text(): string {
		return (string) self::get( 'forms.contact_success_text', __( 'Thank you for reaching out. We will get back to you shortly.', 'heartland-k9s-core' ) );
	}

	/** Configured application success page URL, or '' when none resolves. */
	public static function application_success_url(): string {
		$url = self::link_url( self::get( 'forms.application_success_page', null ) );
		if ( '' === $url ) {
			$page = get_page_by_path( 'thank-you' );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				$url = (string) get_permalink( $page );
			}
		}
		return $url;
	}

	/** URL of the "5 Questions" page (settings `links.five_questions`), or '' when unknown. */
	public static function five_questions_url(): string {
		$url = self::link_url( self::get( 'links.five_questions', null ) );
		if ( '' === $url ) {
			$page = get_page_by_path( '5-questions' );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				$url = (string) get_permalink( $page );
			}
		}
		return $url;
	}

	/**
	 * Resolve a `link` field value ({label,url,post_id,...}) or a plain URL string to a URL.
	 */
	public static function link_url( mixed $link ): string {
		if ( is_string( $link ) ) {
			return self::absolutize( esc_url_raw( trim( $link ) ) );
		}
		if ( ! is_array( $link ) ) {
			return '';
		}
		$post_id = (int) ( $link['post_id'] ?? 0 );
		if ( $post_id > 0 && 'publish' === get_post_status( $post_id ) ) {
			return (string) get_permalink( $post_id );
		}
		return self::absolutize( esc_url_raw( trim( (string) ( $link['url'] ?? '' ) ) ) );
	}

	/** Site-relative paths ("/thank-you/") become absolute URLs on this site. */
	private static function absolutize( string $url ): string {
		if ( '' !== $url && str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			return home_url( $url );
		}
		return $url;
	}
}
