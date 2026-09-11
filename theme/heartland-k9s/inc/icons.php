<?php
/**
 * Inline SVG icons from the lucide sprite (assets/dist/icons.svg).
 *
 * `hk9_icon( 'arrow-right' )` outputs
 *   <svg class="hk9-icon hk9-icon--arrow-right" aria-hidden="true" focusable="false"><use href="#hk9-icon-arrow-right"/></svg>
 *
 * The sprite is read from the filesystem once per request (never over HTTP). The
 * first time an icon is used its <symbol> is inlined inside that <svg>, so every
 * later <use> on the page resolves even when no footer sprite is printed.
 * A missing or unreadable sprite is guarded: a small built-in set covers the icons
 * the theme chrome needs (header, footer, cards); unknown names render nothing.
 *
 * Icon shapes: lucide (ISC) — https://lucide.dev — notice in docs/licenses/LICENSE-lucide.txt
 * (shipped with the sprite) and in the theme readme.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Built-in fallbacks (lucide-static v1.44.0 inner markup) used when the sprite is absent.
 *
 * @return array<string, string>
 */
function hk9_icon_fallbacks(): array {
	return [
		'menu'          => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
		'x'             => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'arrow-right'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
		'mail'          => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
		'phone'         => '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/>',
		'map-pin'       => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
		'heart'         => '<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/>',
		'shield-check'  => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
		'external-link' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'calendar'      => '<path d="M8 2v3"/><path d="M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/>',
	];
}

/**
 * Brand glyphs for the footer social links. lucide-static ≥ 1.0 no longer ships brand
 * icons, so these carry the shapes from the last lucide release that did (ISC, same
 * notice as the sprite: docs/licenses/LICENSE-lucide.txt). They are merged after the
 * sprite so a future sprite build with the same names wins.
 *
 * @return array<string, string>
 */
function hk9_icon_brand_extras(): array {
	return [
		'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
		'instagram' => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
		'youtube'   => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
		'linkedin'  => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/>',
	];
}

/**
 * Parse the sprite once per request into name => inner markup + viewBox.
 *
 * @return array<string, array{inner:string,attrs:string}>
 */
function hk9_icon_symbols(): array {
	static $symbols = null;

	if ( null !== $symbols ) {
		return $symbols;
	}

	$symbols = [];
	$file    = HK9_THEME_DIR . '/assets/dist/icons.svg';

	if ( is_readable( $file ) ) {
		$sprite = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		if ( is_string( $sprite ) && '' !== $sprite ) {
			if ( preg_match_all( '#<symbol\b([^>]*)>(.*?)</symbol>#s', $sprite, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $m ) {
					if ( ! preg_match( '/\bid="hk9-icon-([a-z0-9-]+)"/i', $m[1], $id ) ) {
						continue;
					}
					$symbols[ strtolower( $id[1] ) ] = [
						'inner' => trim( $m[2] ),
						'attrs' => trim( preg_replace( '/\s+/', ' ', $m[1] ) ),
					];
				}
			}
		}
	}

	$extras = empty( $symbols ) ? array_merge( hk9_icon_fallbacks(), hk9_icon_brand_extras() ) : hk9_icon_brand_extras();
	foreach ( $extras as $name => $inner ) {
		if ( isset( $symbols[ $name ] ) ) {
			continue;
		}
		$symbols[ $name ] = [
			'inner' => $inner,
			'attrs' => sprintf( 'id="hk9-icon-%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"', $name ),
		];
	}

	return $symbols;
}

/**
 * Whether an icon exists in the sprite (or the fallback set).
 *
 * @param string $name Icon name.
 * @return bool
 */
function hk9_icon_exists( string $name ): bool {
	$symbols = hk9_icon_symbols();
	return isset( $symbols[ hk9_icon_slug( $name ) ] );
}

/**
 * Normalise an icon name.
 *
 * @param string $name Raw name.
 * @return string
 */
function hk9_icon_slug( string $name ): string {
	return strtolower( trim( preg_replace( '/[^a-z0-9-]/i', '-', $name ), '-' ) );
}

/**
 * Names available for pickers (plugin uses hk9_icon_name_list() when present).
 *
 * @return string[]
 */
function hk9_theme_icon_names(): array {
	return array_keys( hk9_icon_symbols() );
}

/**
 * Render an inline SVG icon.
 *
 * @param string $name  lucide icon name (e.g. `arrow-right`).
 * @param array  $attrs Optional: `class` (extra classes), `size` (px → width/height),
 *                      `title` (accessible name; switches role to img), `fill` (bool: filled),
 *                      plus any other attribute (escaped).
 * @return string Empty string when the icon is unknown.
 */
function hk9_icon( string $name, array $attrs = [] ): string {
	static $printed = [];

	$slug    = hk9_icon_slug( $name );
	$symbols = hk9_icon_symbols();

	if ( '' === $slug || ! isset( $symbols[ $slug ] ) ) {
		return '';
	}

	$classes = [ 'hk9-icon', 'hk9-icon--' . $slug ];
	if ( ! empty( $attrs['class'] ) ) {
		$classes = array_merge( $classes, preg_split( '/\s+/', (string) $attrs['class'], -1, PREG_SPLIT_NO_EMPTY ) );
	}
	// Filled variant: lucide symbols carry fill="none" as a presentation attribute, which
	// beats an inherited CSS fill inside <use>, so a separate filled symbol is emitted.
	$filled = ! empty( $attrs['fill'] );
	if ( $filled ) {
		$classes[] = 'hk9-icon--fill';
	}
	unset( $attrs['class'], $attrs['fill'] );

	$svg_attrs = [
		'class' => implode( ' ', array_map( 'sanitize_html_class', array_unique( $classes ) ) ),
	];

	if ( ! empty( $attrs['size'] ) ) {
		$size                = (int) $attrs['size'];
		$svg_attrs['width']  = $size;
		$svg_attrs['height'] = $size;
	}
	unset( $attrs['size'] );

	$title = '';
	if ( ! empty( $attrs['title'] ) ) {
		$title             = (string) $attrs['title'];
		$svg_attrs['role'] = 'img';
	} else {
		$svg_attrs['aria-hidden'] = 'true';
	}
	unset( $attrs['title'] );
	$svg_attrs['focusable'] = 'false';

	foreach ( $attrs as $key => $value ) {
		$key = strtolower( preg_replace( '/[^a-zA-Z0-9:-]/', '', (string) $key ) );
		if ( '' === $key || is_array( $value ) || is_object( $value ) ) {
			continue;
		}
		$svg_attrs[ $key ] = (string) $value;
	}

	$attr_html = '';
	foreach ( $svg_attrs as $key => $value ) {
		$attr_html .= sprintf( ' %s="%s"', $key, esc_attr( (string) $value ) );
	}

	$symbol_id = 'hk9-icon-' . $slug . ( $filled ? '--fill' : '' );
	$inner     = '';

	if ( '' !== $title ) {
		$title_id = $symbol_id . '-title-' . wp_unique_id();
		$inner   .= sprintf( '<title id="%s">%s</title>', esc_attr( $title_id ), esc_html( $title ) );
		$attr_html .= sprintf( ' aria-labelledby="%s"', esc_attr( $title_id ) );
	}

	if ( empty( $printed[ $symbol_id ] ) ) {
		$printed[ $symbol_id ] = true;
		$symbol_attrs          = $symbols[ $slug ]['attrs'];
		if ( $filled ) {
			$symbol_attrs = str_replace( 'id="hk9-icon-' . $slug . '"', 'id="' . $symbol_id . '"', $symbol_attrs );
			$symbol_attrs = preg_replace( '/\bfill="[^"]*"/', 'fill="currentColor"', $symbol_attrs );
			if ( false === strpos( $symbol_attrs, 'fill=' ) ) {
				$symbol_attrs .= ' fill="currentColor"';
			}
		}
		// Sprite markup is a build artefact of the theme (tools/build-icons.mjs), not user input.
		$inner .= '<symbol ' . $symbol_attrs . '>' . $symbols[ $slug ]['inner'] . '</symbol>';
	}

	$inner .= sprintf( '<use href="#%1$s" xlink:href="#%1$s"></use>', esc_attr( $symbol_id ) );

	return sprintf( '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"%s>%s</svg>', $attr_html, $inner );
}

/**
 * Echo helper.
 *
 * @param string $name  Icon name.
 * @param array  $attrs Attributes.
 */
function hk9_the_icon( string $name, array $attrs = [] ): void {
	echo hk9_icon( $name, $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in hk9_icon().
}
