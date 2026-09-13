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
	// Gallery tiles on phones render 130–310 CSS px wide (260–930 device px). Core
	// offers `medium` (≤ 300 px box → a portrait is 200–225 wide) and then jumps to
	// `large` / `medium_large` (683–768 wide, 100–250 kB): `hk9-tile` is the
	// uncropped candidate in between for every aspect ratio (2:3 → 455×683,
	// 3:4 → 512×683, 4:3 → 512×384), `hk9-portrait-sm` the smaller 3:4 crop that
	// 1.75–2× phones pick for portrait photos.
	add_image_size( 'hk9-tile', 512, 683, false );
	add_image_size( 'hk9-portrait-sm', 300, 400, true );
}
add_action( 'after_setup_theme', 'hk9_theme_setup' );

/**
 * Generate the intermediate sizes as WebP.
 *
 * `image_editor_output_format` maps a source mime type to the type the image
 * editor saves: JPEG sources get WebP sub-sizes (the uploaded original is never
 * touched, so existing URLs keep working and a JPEG stays available), PNG
 * sources get WebP sub-sizes only for opaque truecolor/greyscale PNGs (a photo
 * or flyer saved as PNG) — a logo with an alpha channel or a palette PNG stays
 * a lossless PNG (GD cannot encode a palette image as WebP, see
 * hk9_png_webp_safe()). WordPress falls back to the source type by itself when
 * the active editor cannot write WebP.
 *
 * Note for `wp media regenerate`: with an output-format map active, WordPress'
 * wp_create_image_subsizes() also writes a WebP copy of the full-size file and
 * makes it the attachment file (the original is kept as `original_image`) — the
 * same thing it does for a new upload. A full `wp media regenerate` is
 * consistent; `--image_size=<one size>` is not (WP-CLI keeps the old metadata
 * for the other sizes while core has already re-pointed `_wp_attached_file`),
 * so regenerate whole attachments, never a single size, while this filter is on.
 *
 * The filter runs in every context that creates sizes (upload, the importer's
 * media_sizes step, `wp media regenerate`, hk9_ensure_image_size()), so on a
 * migrated site the adopted originals stay JPEG while every theme size that is
 * generated for them (hk9-hero, hk9-card, hk9-square, hk9-portrait, hk9-logo-sm…)
 * is WebP. Filter `hk9/theme/webp_subsizes` (bool) to switch it off.
 *
 * @param array<string,string> $formats   Mime type map (source => output).
 * @param string|null          $filename  Path to the image (null while an editor saves a sub-size).
 * @param string|null          $mime_type Source mime type.
 * @return array<string,string>
 */
function hk9_image_output_format( $formats, $filename, $mime_type ): array {
	$formats = is_array( $formats ) ? $formats : [];

	/**
	 * Filter whether the theme requests WebP sub-sizes.
	 *
	 * @param bool $enabled Default true.
	 */
	if ( ! apply_filters( 'hk9/theme/webp_subsizes', true ) ) {
		return $formats;
	}

	$formats['image/jpeg'] = 'image/webp';

	if ( 'image/png' !== $mime_type ) {
		return $formats;
	}

	// The editor calls this without a path while saving each sub-size; the source
	// path was seen when the editor was created (wp_get_image_editor()), so keep the
	// verdict for the PNG that is being processed.
	static $last = [ 'file' => '', 'safe' => false ];
	if ( is_string( $filename ) && '' !== $filename && $filename !== $last['file'] && is_readable( $filename ) ) {
		$last = [ 'file' => $filename, 'safe' => hk9_png_webp_safe( $filename ) ];
	}
	if ( '' !== $last['file'] && $last['safe'] ) {
		$formats['image/png'] = 'image/webp';
	}

	return $formats;
}
add_filter( 'image_editor_output_format', 'hk9_image_output_format', 10, 3 );

/**
 * WebP encoding quality for the generated sizes.
 *
 * Core encodes WebP at 86 (tuned to match the look of its JPEG 82), which makes
 * the WebP files only ~10 % smaller than the JPEG ones (hero 768²: 98 vs 108 kB).
 * At 80 — the level image CDNs and cwebp ship as their default range — the same
 * size is 75 kB (−30 %) with no visible difference at the theme's display sizes
 * (heroes under a 60 % overlay, cards and tiles shown at 1–2 CSS px per source
 * pixel). JPEG/PNG output keeps core's defaults. Filter `hk9/theme/webp_quality`.
 *
 * @param int    $quality   Quality (1–100).
 * @param string $mime_type Output mime type.
 * @return int
 */
function hk9_webp_quality( $quality, $mime_type ): int {
	if ( 'image/webp' !== $mime_type ) {
		return (int) $quality;
	}
	/**
	 * Filter the WebP encoding quality (1–100).
	 *
	 * @param int $quality Default 80.
	 */
	return max( 1, min( 100, (int) apply_filters( 'hk9/theme/webp_quality', 80 ) ) );
}
add_filter( 'wp_editor_set_quality', 'hk9_webp_quality', 10, 2 );

/**
 * Whether a PNG can be re-encoded as lossy WebP without losing anything a PNG
 * is chosen for: an opaque truecolor (colour type 2) or greyscale (0) image
 * with no tRNS chunk. Alpha types (4, 6), tRNS transparency and palette images
 * (3 — flat graphics that GD cannot hand to imagewebp() unresized) keep PNG.
 * Reads only the chunk headers before the pixel data; unreadable or malformed
 * files count as unsafe.
 *
 * @param string $file Path to the PNG.
 * @return bool
 */
function hk9_png_webp_safe( string $file ): bool {
	$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $fh ) {
		return false;
	}
	$safe = false;
	if ( "\x89PNG\r\n\x1a\n" === fread( $fh, 8 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		for ( $i = 0; $i < 64; $i++ ) {
			$header = fread( $fh, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( ! is_string( $header ) || strlen( $header ) < 8 ) {
				$safe = false; // Truncated before the pixel data: do not guess.
				break;
			}
			$length = (int) unpack( 'N', substr( $header, 0, 4 ) )[1];
			$type   = substr( $header, 4, 4 );
			if ( 'IHDR' === $type ) {
				$data = fread( $fh, max( 4, $length + 4 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				if ( ! is_string( $data ) || strlen( $data ) < 10 ) {
					break;
				}
				$safe = in_array( ord( $data[9] ), [ 0, 2 ], true );
				if ( ! $safe ) {
					break;
				}
				continue;
			}
			if ( 'tRNS' === $type ) {
				$safe = false;
				break;
			}
			if ( 'IDAT' === $type || 'IEND' === $type ) {
				break;
			}
			fseek( $fh, $length + 4, SEEK_CUR );
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	return $safe;
}

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
			'hk9-tile'     => __( 'Tile (512×683 max)', 'heartland-k9s' ),
			'hk9-portrait-sm' => __( 'Portrait tile 3:4 (300×400)', 'heartland-k9s' ),
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
	$gallery = hk9_gallery_sizes_context();
	if ( '' !== $gallery ) {
		return $gallery;
	}
	$width = is_array( $size ) ? (int) $size[0] : 0;
	if ( $width >= 1216 ) {
		return '(max-width: 767px) calc(100vw - 32px), (max-width: 1279px) calc(100vw - 64px), 1216px';
	}
	return '(max-width: 767px) calc(100vw - 32px), ' . ( $width > 0 ? $width . 'px' : '100vw' );
}
add_filter( 'wp_calculate_image_sizes', 'hk9_content_image_sizes', 10, 2 );

/**
 * The `sizes` attribute for the tiles of a core gallery block with N columns.
 *
 * Core lays the tiles out two per row below 600px (one for `columns-1`) and N
 * per row from 600px, with a 16px gap. The tile width is therefore
 * (container − gap × (columns − 1)) / columns, where the container is:
 *  - the Photo Gallery template's overlap card (measured): 100vw − 82px below
 *    768px, 100vw − 130px up to 1088px, then 958px;
 *  - otherwise the content column of hk9_content_image_sizes().
 * Without this, every tile inherits the full content-column width and phones
 * download 600–800px candidates for tiles that render 130–250px wide.
 *
 * @param int  $columns      Gallery columns (1–8).
 * @param bool $gallery_page Whether the gallery renders inside the Photo Gallery template card.
 * @return string
 */
function hk9_gallery_sizes( int $columns, bool $gallery_page ): string {
	$columns = max( 1, min( 8, $columns ) );
	$gap     = 16;
	$tile    = static function ( string $container, int $cols ) use ( $gap ): string {
		if ( 1 === $cols ) {
			return $container;
		}
		$gaps = $gap * ( $cols - 1 );
		if ( preg_match( '/^(\d+)px$/', $container, $m ) ) {
			return (string) (int) round( ( (int) $m[1] - $gaps ) / $cols ) . 'px';
		}
		// "100vw - Npx" → "(100/cols)vw - ((N + gaps)/cols)px".
		if ( preg_match( '/^100vw - (\d+)px$/', $container, $m ) ) {
			$vw = rtrim( rtrim( number_format( 100 / $cols, 2, '.', '' ), '0' ), '.' );
			$px = (int) ceil( ( (int) $m[1] + $gaps ) / $cols );
			return sprintf( 'calc(%svw - %dpx)', $vw, $px );
		}
		return sprintf( 'calc((%s - %dpx) / %d)', $container, $gaps, $cols );
	};
	$narrow  = min( 2, $columns ); // Core's < 600px layout.

	if ( $gallery_page ) {
		return implode(
			', ',
			[
				'(max-width: 599px) ' . $tile( '100vw - 82px', $narrow ),
				'(max-width: 767px) ' . $tile( '100vw - 82px', $columns ),
				'(max-width: 1088px) ' . $tile( '100vw - 130px', $columns ),
				$tile( '958px', $columns ),
			]
		);
	}

	return implode(
		', ',
		[
			'(max-width: 599px) ' . $tile( '100vw - 32px', $narrow ),
			'(max-width: 767px) ' . $tile( '100vw - 32px', $columns ),
			'(max-width: 1279px) ' . $tile( '100vw - 64px', $columns ),
			$tile( '1216px', $columns ),
		]
	);
}

/**
 * The gallery `sizes` value that applies to images being rendered right now
 * ('' outside a gallery block). Set by hk9_gallery_block_sizes() while it
 * processes a core/gallery block; read by hk9_content_image_sizes().
 *
 * @param string|null $set New value when given.
 * @return string
 */
function hk9_gallery_sizes_context( ?string $set = null ): string {
	static $sizes = '';
	if ( null !== $set ) {
		$sizes = $set;
	}
	return $sizes;
}

/**
 * Give the tiles of a rendered core gallery block a `sizes` attribute that
 * matches their real width (see hk9_gallery_sizes()).
 *
 * Images that already carry srcset/sizes (hk9_rec_gallery() output) get the
 * value replaced; block-content images (which core would otherwise complete at
 * `the_content` priority 12 with the content-column default) get their srcset
 * and sizes added here through core's own wp_img_tag_add_srcset_and_sizes_attr(),
 * with the gallery value active in the wp_calculate_image_sizes filter. Core's
 * later pass leaves images that have both attributes alone and still adds
 * loading/decoding/fetchpriority and the `auto` sizes prefix for lazy images.
 *
 * @param string $content Rendered block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function hk9_gallery_block_sizes( $content, $block ): string {
	$content = (string) $content;
	if ( '' === $content || ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'core/gallery' || false === stripos( $content, '<img' ) ) {
		return $content;
	}

	$columns = (int) ( $block['attrs']['columns'] ?? 0 );
	if ( $columns <= 0 && preg_match( '/\bcolumns-(\d+)\b/', $content, $m ) ) {
		$columns = (int) $m[1];
	}
	if ( $columns <= 0 ) {
		$columns = 3; // Core's `columns-default`: three per row (up to the image count).
	}
	$count = preg_match_all( '/<img\b/i', $content );
	if ( $count > 0 ) {
		$columns = min( $columns, max( 1, $count ) );
	}

	$gallery_page = function_exists( 'hk9_rec_gallery_options' ) && null !== hk9_rec_gallery_options();
	$sizes        = hk9_gallery_sizes( $columns, $gallery_page );

	hk9_gallery_sizes_context( $sizes );
	$content = (string) preg_replace_callback(
		'/<img\b[^>]*>/i',
		static function ( array $m ) use ( $sizes ): string {
			$img = $m[0];
			if ( preg_match( '/\ssizes="[^"]*"/', $img ) ) {
				return (string) preg_replace( '/\ssizes="[^"]*"/', ' sizes="' . esc_attr( $sizes ) . '"', $img, 1 );
			}
			if ( false !== strpos( $img, ' srcset=' ) || ! preg_match( '/\bwp-image-(\d+)\b/', $img, $id ) ) {
				return $img;
			}
			return (string) wp_img_tag_add_srcset_and_sizes_attr( $img, 'hk9/gallery', (int) $id[1] );
		},
		$content
	);
	hk9_gallery_sizes_context( '' );

	return $content;
}
add_filter( 'render_block_core/gallery', 'hk9_gallery_block_sizes', 10, 2 );

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
