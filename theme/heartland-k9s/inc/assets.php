<?php
/**
 * Frontend assets: compiled CSS/JS, font preloads, root token overrides, and
 * removal of core output that would trigger third-party requests.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version string for a theme asset (file mtime, falls back to the theme version).
 *
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function hk9_asset_version( string $relative ): string {
	$file = HK9_THEME_DIR . '/' . ltrim( $relative, '/' );
	$time = file_exists( $file ) ? (int) filemtime( $file ) : 0;
	return $time > 0 ? (string) $time : HK9_THEME_VERSION;
}

/**
 * Enqueue the compiled theme stylesheet and script.
 */
function hk9_enqueue_assets(): void {
	$css = 'assets/dist/theme.css';
	$js  = 'assets/dist/theme.js';

	if ( file_exists( HK9_THEME_DIR . '/' . $css ) ) {
		wp_enqueue_style( 'hk9-theme', HK9_THEME_URI . '/' . $css, [], hk9_asset_version( $css ) );

		$root_css = hk9_root_css();
		if ( '' !== $root_css ) {
			wp_add_inline_style( 'hk9-theme', $root_css );
		}
	}

	if ( file_exists( HK9_THEME_DIR . '/' . $js ) ) {
		wp_enqueue_script( 'hk9-theme', HK9_THEME_URI . '/' . $js, [], hk9_asset_version( $js ), [ 'strategy' => 'defer', 'in_footer' => true ] );

		$config = [
			'navBreakpoint' => 1024,
			'adminBar'      => is_admin_bar_showing(),
			'i18n'          => [
				'openMenu'  => __( 'Open menu', 'heartland-k9s' ),
				'closeMenu' => __( 'Close menu', 'heartland-k9s' ),
			],
		];
		wp_add_inline_script( 'hk9-theme', 'window.HK9 = window.HK9 || {}; window.HK9.config = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	// Core's classic-theme-styles adds button/pullquote rules the theme already covers.
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'hk9_enqueue_assets', 20 );

/**
 * Preload the two roman variable fonts (the italic loads on demand).
 */
function hk9_preload_fonts(): void {
	if ( 'system-serif' === hk9_theme_option( 'fonts.serif' ) && 'system-sans' === hk9_theme_option( 'fonts.sans' ) ) {
		return;
	}

	$fonts = [];
	if ( 'system-serif' !== hk9_theme_option( 'fonts.serif' ) ) {
		$fonts[] = 'assets/fonts/fraunces-var.woff2';
	}
	if ( 'system-sans' !== hk9_theme_option( 'fonts.sans' ) ) {
		$fonts[] = 'assets/fonts/inter-var.woff2';
	}

	foreach ( $fonts as $font ) {
		if ( ! file_exists( HK9_THEME_DIR . '/' . $font ) ) {
			continue;
		}
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( HK9_THEME_URI . '/' . $font . '?v=' . hk9_asset_version( $font ) )
		);
	}
}
add_action( 'wp_head', 'hk9_preload_fonts', 2 );

/**
 * Trim core head output that is useless here or points at third-party hosts
 * (emoji CDN hints, generator tag). No functional loss on the front end.
 */
function hk9_trim_head(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'hk9_trim_head' );

/**
 * Drop the s.w.org DNS prefetch hint that the emoji loader adds.
 *
 * @param array  $urls          Hint URLs.
 * @param string $relation_type Hint type.
 * @return array
 */
function hk9_resource_hints( array $urls, string $relation_type ): array {
	if ( 'dns-prefetch' === $relation_type ) {
		$urls = array_filter( $urls, static fn( $url ) => false === strpos( is_array( $url ) ? ( $url['href'] ?? '' ) : (string) $url, 's.w.org' ) );
	}
	return array_values( $urls );
}
add_filter( 'wp_resource_hints', 'hk9_resource_hints', 10, 2 );

/**
 * Keep the emoji plugin's TinyMCE/dashicons untouched but stop the front-end emoji CDN.
 *
 * @param array $plugins TinyMCE plugins.
 * @return array
 */
function hk9_disable_emoji_tinymce( $plugins ): array {
	return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
}
add_filter( 'tiny_mce_plugins', 'hk9_disable_emoji_tinymce' );
