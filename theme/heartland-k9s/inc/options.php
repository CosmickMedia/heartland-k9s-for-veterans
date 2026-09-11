<?php
/**
 * Settings access for templates.
 *
 * `hk9_theme_option()` wraps the plugin's `hk9_option()` (docs/ARCHITECTURE.md §7)
 * and falls back to the theme defaults in inc/defaults.php, so every template can
 * read settings without checking whether the plugin is active. When the plugin is
 * absent the theme also defines `hk9_option()` itself.
 *
 * Also emits the `:root` colour/font custom properties derived from settings
 * (attached to the theme stylesheet in inc/assets.php and to the editor styles).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hk9_option' ) ) {
	/**
	 * Read a setting by dot-notation key (plugin-compatible fallback).
	 *
	 * @param string $key     `group.key`.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	function hk9_option( string $key, $default = null ) {
		return hk9_theme_default( $key, $default );
	}
}

/**
 * Read a setting; plugin value first, theme default second, `$default` last.
 *
 * Empty strings and null from the plugin are treated as "unset" so an untouched
 * settings page still shows the reference defaults.
 *
 * @param string $key     `group.key`.
 * @param mixed  $default Explicit fallback (overrides the theme default when not null).
 * @return mixed
 */
function hk9_theme_option( string $key, $default = null ) {
	$theme_default = hk9_theme_default( $key, $default );
	$value         = hk9_option( $key, $theme_default );

	if ( null === $value || '' === $value ) {
		return $theme_default;
	}

	// Link values: fall back per-field so a saved link with an empty label still gets one.
	if ( is_array( $value ) && is_array( $theme_default ) && isset( $theme_default['url'] ) ) {
		return array_merge( $theme_default, array_filter( $value, static fn( $v ) => null !== $v && '' !== $v ) );
	}

	return $value;
}

/**
 * Normalise a hex colour to `#rrggbb` (returns '' when invalid).
 *
 * @param mixed $color Raw value.
 * @return string
 */
function hk9_sanitize_hex( $color ): string {
	if ( ! is_string( $color ) ) {
		return '';
	}
	$color = trim( $color );
	if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m ) ) {
		$hex = strtolower( $m[1] );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return '#' . $hex;
	}
	return '';
}

/**
 * `#rrggbb` → `r, g, b` triplet for rgba(var(--x-rgb), a) usage.
 *
 * @param string $hex Normalised hex colour.
 * @return string
 */
function hk9_hex_to_rgb_triplet( string $hex ): string {
	$hex = ltrim( $hex, '#' );
	if ( 6 !== strlen( $hex ) ) {
		return '';
	}
	return implode( ', ', array_map( 'hexdec', str_split( $hex, 2 ) ) );
}

/**
 * CSS for `:root` custom properties that differ from the compiled defaults.
 *
 * @return string CSS (may be empty when every setting equals the reference default).
 */
function hk9_root_css(): string {
	$map = [
		'primary'          => [ 'primary', true ],
		'secondary'        => [ 'secondary', true ],
		'background'       => [ 'bg', true ],
		'foreground'       => [ 'fg', true ],
		'muted'            => [ 'muted', true ],
		'muted_foreground' => [ 'muted-fg', true ],
		'border'           => [ 'border', true ],
		'accent'           => [ 'accent', false ],
	];

	$vars = [];
	foreach ( $map as $setting => [ $var, $with_rgb ] ) {
		$hex      = hk9_sanitize_hex( hk9_theme_option( 'colors.' . $setting ) );
		$default  = hk9_sanitize_hex( hk9_theme_default( 'colors.' . $setting ) );
		if ( '' === $hex || $hex === $default ) {
			continue;
		}
		$vars[] = sprintf( '--hk9-%s:%s', $var, $hex );
		if ( $with_rgb ) {
			$vars[] = sprintf( '--hk9-%s-rgb:%s', $var, hk9_hex_to_rgb_triplet( $hex ) );
		}
	}

	if ( 'system-serif' === hk9_theme_option( 'fonts.serif' ) ) {
		$vars[] = '--hk9-font-serif:ui-serif,Georgia,"Iowan Old Style","Times New Roman",serif';
	}
	if ( 'system-sans' === hk9_theme_option( 'fonts.sans' ) ) {
		$vars[] = '--hk9-font-sans:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
	}

	$logo_h = (int) hk9_theme_option( 'branding.header_logo_height' );
	if ( $logo_h > 0 && 64 !== $logo_h ) {
		$vars[] = sprintf( '--hk9-logo-h:%dpx', max( 24, min( 80, $logo_h ) ) );
	}
	$footer_logo_h = (int) hk9_theme_option( 'branding.footer_logo_height' );
	if ( $footer_logo_h > 0 && 80 !== $footer_logo_h ) {
		$vars[] = sprintf( '--hk9-footer-logo-h:%dpx', max( 24, min( 160, $footer_logo_h ) ) );
	}

	if ( empty( $vars ) ) {
		return '';
	}

	return ':root{' . implode( ';', $vars ) . '}';
}

/**
 * Site-wide contact helpers built from settings.
 */
function hk9_contact_address( string $separator = ', ' ): string {
	$parts = array_filter(
		[
			hk9_theme_option( 'contact.address_line1' ),
			hk9_theme_option( 'contact.address_line2' ),
			trim( hk9_theme_option( 'contact.city' ) . ', ' . hk9_theme_option( 'contact.state' ) . ' ' . hk9_theme_option( 'contact.zip' ), ', ' ),
		],
		static fn( $v ) => is_string( $v ) && '' !== trim( $v ) && ',' !== trim( $v )
	);
	return implode( $separator, $parts );
}

/**
 * `tel:` href for a display phone number.
 *
 * @param string $phone Display number.
 * @return string
 */
function hk9_tel_href( string $phone ): string {
	$digits = preg_replace( '/[^0-9+]/', '', $phone );
	if ( '' === $digits ) {
		return '';
	}
	if ( 10 === strlen( $digits ) ) {
		$digits = '+1' . $digits;
	}
	return 'tel:' . $digits;
}

/**
 * Replace `{year}` in footer strings.
 *
 * @param string $text Text with tokens.
 * @return string
 */
function hk9_replace_tokens( string $text ): string {
	return str_replace(
		[ '{year}', '{site}' ],
		[ gmdate( 'Y' ), get_bloginfo( 'name' ) ],
		$text
	);
}
