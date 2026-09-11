<?php
/**
 * Theme setup: supports, menus, image sizes, content width.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $content_width ) ) {
	$content_width = 1216; // 1280 container − 2 × 32 gutter.
}

/**
 * Register theme supports and menus.
 */
function hk9_theme_setup(): void {
	load_theme_textdomain( 'heartland-k9s', HK9_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ] );
	add_theme_support(
		'custom-logo',
		[
			'height'               => 64,
			'width'                => 56,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => false,
		]
	);
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/dist/editor.css' );

	register_nav_menus(
		[
			'primary'         => __( 'Primary navigation', 'heartland-k9s' ),
			'footer_quick'    => __( 'Footer — Quick Links', 'heartland-k9s' ),
			'footer_involved' => __( 'Footer — Get Involved', 'heartland-k9s' ),
			'legal'           => __( 'Footer — Legal', 'heartland-k9s' ),
		]
	);

	// Image sizes (width, height, crop).
	add_image_size( 'hk9-hero', 1920, 9999, false );
	add_image_size( 'hk9-card', 800, 600, true );
	add_image_size( 'hk9-square', 800, 800, true );
	add_image_size( 'hk9-portrait', 600, 800, true );
	add_image_size( 'hk9-logo', 400, 300, false );
}
add_action( 'after_setup_theme', 'hk9_theme_setup' );

/**
 * Expose the custom sizes in the media modal.
 *
 * @param array $sizes Size labels.
 * @return array
 */
function hk9_image_size_names( array $sizes ): array {
	return array_merge(
		$sizes,
		[
			'hk9-hero'     => __( 'Hero (1920 wide)', 'heartland-k9s' ),
			'hk9-card'     => __( 'Card 4:3 (800×600)', 'heartland-k9s' ),
			'hk9-square'   => __( 'Square (800×800)', 'heartland-k9s' ),
			'hk9-portrait' => __( 'Portrait 3:4 (600×800)', 'heartland-k9s' ),
			'hk9-logo'     => __( 'Logo (400×300 max)', 'heartland-k9s' ),
		]
	);
}
add_filter( 'image_size_names_choose', 'hk9_image_size_names' );

/**
 * Body classes: theme marker + page template slug.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function hk9_body_classes( array $classes ): array {
	$classes[] = 'hk9-theme';

	if ( is_singular() ) {
		$template = hk9_template_for_post( get_queried_object_id() );
		$classes[] = 'hk9-template-' . sanitize_html_class( $template );
	}

	if ( hk9_theme_option( 'header.sticky' ) ) {
		$classes[] = 'hk9-has-sticky-header';
	}

	return array_unique( $classes );
}
add_filter( 'body_class', 'hk9_body_classes' );

/**
 * Default `sizes` for responsive images inside the content column.
 *
 * @param string $sizes Sizes attribute.
 * @param array  $size  Width/height.
 * @return string
 */
function hk9_content_image_sizes( string $sizes, $size ): string {
	$width = is_array( $size ) ? (int) $size[0] : 0;
	if ( $width >= 1216 ) {
		return '(max-width: 767px) calc(100vw - 32px), (max-width: 1279px) calc(100vw - 64px), 1216px';
	}
	return '(max-width: 767px) calc(100vw - 32px), ' . ( $width > 0 ? $width . 'px' : '100vw' );
}
add_filter( 'wp_calculate_image_sizes', 'hk9_content_image_sizes', 10, 2 );

/**
 * Excerpt trimming for cards.
 */
add_filter( 'excerpt_length', static fn(): int => 28, 999 );
add_filter( 'excerpt_more', static fn(): string => '…' );

/**
 * Remove the "Uncategorized"-style noise from `<title>` on the front page: keep core defaults,
 * but make sure the separator is an en dash like the reference wordmark spacing.
 */
add_filter( 'document_title_separator', static fn(): string => '–' );
