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
			'helpful'         => __( 'Helpful links (404 & search)', 'heartland-k9s' ),
		]
	);

	// Image sizes (width, height, crop).
	add_image_size( 'hk9-hero', 1920, 9999, false );
	add_image_size( 'hk9-card', 800, 600, true );
	add_image_size( 'hk9-square', 800, 800, true );
	add_image_size( 'hk9-portrait', 600, 800, true );
	add_image_size( 'hk9-logo', 400, 300, false );
	// Header/footer crest at 64–80px tall on 1×–2× screens (created on demand for
	// logos uploaded before 1.0.2, see hk9_ensure_image_size()).
	add_image_size( 'hk9-logo-sm', 140, 160, false );
}
add_action( 'after_setup_theme', 'hk9_theme_setup' );

/**
 * Make sure an attachment has a given registered intermediate size, creating it
 * from the original when it is missing (media uploaded before the size existed).
 *
 * Runs at most once per attachment/size: success stores the size in the
 * attachment metadata, failure is remembered for a day in a transient so a
 * read-only uploads directory never costs more than one attempt.
 *
 * @param int    $attachment_id Attachment id.
 * @param string $size          Registered size name.
 * @return bool Whether the size is available.
 */
function hk9_ensure_image_size( int $attachment_id, string $size ): bool {
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $meta ) ) {
		return false;
	}
	if ( isset( $meta['sizes'][ $size ] ) ) {
		return true;
	}

	$registered = wp_get_registered_image_subsizes();
	if ( ! isset( $registered[ $size ] ) ) {
		return false;
	}

	// WordPress does not create sizes the original cannot fill; neither do we.
	$dims = image_resize_dimensions( (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ), (int) $registered[ $size ]['width'], (int) $registered[ $size ]['height'], (bool) $registered[ $size ]['crop'] );
	if ( ! $dims ) {
		return false;
	}

	$lock = 'hk9_subsize_' . $attachment_id . '_' . sanitize_key( $size );
	if ( get_transient( $lock ) ) {
		return false;
	}

	$file   = get_attached_file( $attachment_id );
	$editor = is_string( $file ) && '' !== $file ? wp_get_image_editor( $file ) : null;
	$made   = $editor instanceof WP_Image_Editor ? $editor->make_subsize( $registered[ $size ] ) : null;

	if ( ! is_array( $made ) || empty( $made['file'] ) ) {
		set_transient( $lock, 1, DAY_IN_SECONDS );
		return false;
	}

	$meta['sizes'][ $size ] = $made;
	wp_update_attachment_metadata( $attachment_id, $meta );

	return true;
}

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
			'hk9-logo-sm'  => __( 'Logo small (140×160 max)', 'heartland-k9s' ),
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
