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
 * WCAG relative luminance of a hex colour.
 *
 * @param string $hex `#rrggbb`.
 * @return float
 */
function hk9_relative_luminance( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	if ( 6 !== strlen( $hex ) ) {
		return 0.0;
	}
	$channels = array_map(
		static function ( $pair ) {
			$c = hexdec( $pair ) / 255;
			return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		},
		str_split( $hex, 2 )
	);
	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * WCAG contrast ratio between two hex colours.
 *
 * @param string $a `#rrggbb`.
 * @param string $b `#rrggbb`.
 * @return float
 */
function hk9_contrast_ratio( string $a, string $b ): float {
	$la = hk9_relative_luminance( $a );
	$lb = hk9_relative_luminance( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * Lighten (or darken) a colour along its HSL lightness axis — hue and saturation are
 * kept — until it reaches the requested contrast ratio against a background.
 *
 * Used for the footer tagline: the reference's crimson-on-navy is 2.26:1, so the
 * secondary colour is tinted to the nearest value that passes WCAG AA (4.5:1).
 *
 * @param string $hex        Colour to adjust (`#rrggbb`).
 * @param string $background Background colour (`#rrggbb`).
 * @param float  $target     Minimum contrast ratio.
 * @return string `#rrggbb` (the input when it already passes).
 */
function hk9_accessible_tint( string $hex, string $background, float $target = 4.5 ): string {
	if ( hk9_contrast_ratio( $hex, $background ) >= $target ) {
		return $hex;
	}

	[ $r, $g, $b ] = array_map( static fn( $p ) => hexdec( $p ) / 255, str_split( ltrim( $hex, '#' ), 2 ) );
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$l   = ( $max + $min ) / 2;
	$h   = 0.0;
	$s   = 0.0;
	if ( $max !== $min ) {
		$d = $max - $min;
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
		if ( $max === $r ) {
			$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}
		$h /= 6;
	}

	$to_hex = static function ( float $h, float $s, float $l ): string {
		$f = static function ( float $p, float $q, float $t ): float {
			if ( $t < 0 ) {
				$t += 1;
			}
			if ( $t > 1 ) {
				$t -= 1;
			}
			if ( $t < 1 / 6 ) {
				return $p + ( $q - $p ) * 6 * $t;
			}
			if ( $t < 1 / 2 ) {
				return $q;
			}
			if ( $t < 2 / 3 ) {
				return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
			}
			return $p;
		};
		if ( 0.0 === $s ) {
			$rgb = [ $l, $l, $l ];
		} else {
			$q   = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
			$p   = 2 * $l - $q;
			$rgb = [ $f( $p, $q, $h + 1 / 3 ), $f( $p, $q, $h ), $f( $p, $q, $h - 1 / 3 ) ];
		}
		return '#' . implode( '', array_map( static fn( $v ) => str_pad( dechex( (int) round( $v * 255 ) ), 2, '0', STR_PAD_LEFT ), $rgb ) );
	};

	// Lighten on dark backgrounds, darken on light ones, in 0.5% steps.
	$direction = hk9_relative_luminance( $background ) < 0.18 ? 1 : -1;
	for ( $i = 1; $i <= 200; $i++ ) {
		$candidate = $to_hex( $h, $s, min( 1, max( 0, $l + $direction * $i * 0.005 ) ) );
		if ( hk9_contrast_ratio( $candidate, $background ) >= $target ) {
			return $candidate;
		}
	}

	return $direction > 0 ? '#ffffff' : '#000000';
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

	$vars    = [];
	$changed = [];
	foreach ( $map as $setting => [ $var, $with_rgb ] ) {
		$hex      = hk9_sanitize_hex( hk9_theme_option( 'colors.' . $setting ) );
		$default  = hk9_sanitize_hex( hk9_theme_default( 'colors.' . $setting ) );
		if ( '' === $hex || $hex === $default ) {
			continue;
		}
		$changed[ $setting ] = $hex;
		$vars[]              = sprintf( '--hk9-%s:%s', $var, $hex );
		if ( $with_rgb ) {
			$vars[] = sprintf( '--hk9-%s-rgb:%s', $var, hk9_hex_to_rgb_triplet( $hex ) );
		}
	}

	// Footer tagline colour: the secondary colour tinted until it passes WCAG AA on the
	// primary colour (compiled default #dd7788 for the reference palette).
	if ( isset( $changed['primary'] ) || isset( $changed['secondary'] ) ) {
		$primary   = $changed['primary'] ?? hk9_sanitize_hex( hk9_theme_default( 'colors.primary' ) );
		$secondary = $changed['secondary'] ?? hk9_sanitize_hex( hk9_theme_default( 'colors.secondary' ) );
		if ( '' !== $primary && '' !== $secondary ) {
			$vars[] = sprintf( '--hk9-secondary-on-primary:%s', hk9_accessible_tint( $secondary, $primary ) );
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
