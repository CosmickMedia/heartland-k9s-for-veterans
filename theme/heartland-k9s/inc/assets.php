<?php
/**
 * Frontend assets: compiled CSS (core + per-template bundles), JS, font and LCP
 * image preloads, root token overrides, and removal of core output that would
 * trigger third-party requests.
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
 * Stylesheet bundles for the current request, in cascade order.
 *
 * The compiled CSS is a small core (theme.css, every page) plus bundles that only
 * some templates need (tools/build-css.mjs):
 *   forms    plugin forms (contact, application), search form, comment form
 *   content  block content — .hk9-prose (the_content(), richtext fields, FAQ answers)
 *   blog     posts index / single post / archives / search / 404 / attachment /
 *            comments / password form
 *   pages    reference page templates: about, program, veterans, get-involved,
 *            barkode, stories, contact
 *   records  record grids + singles (stories, teams, events, campaigns, people,
 *            partners, BarKode records) and the migrated templates (donate,
 *            landing tiers, gallery, application, thank-you…)
 * The order returned here mirrors the partial order of the former single
 * stylesheet (forms before prose/blog: the search field's white background in
 * blog-states must win over the form-input default), so the cascade is
 * unchanged. Filter `hk9/theme/style_bundles` to add a bundle for a custom template.
 *
 * @return string[] Bundle names.
 */
function hk9_style_bundles(): array {
	$bundles = [];
	$add     = static function ( string ...$names ) use ( &$bundles ): void {
		foreach ( $names as $name ) {
			$bundles[ $name ] = true;
		}
	};

	$template = '';

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$type    = (string) get_post_type( $post_id );

		if ( post_password_required( $post_id ) ) {
			$add( 'blog', 'forms' );
		}

		if ( 'page' === $type ) {
			$template = hk9_template_for_post( $post_id );
			switch ( $template ) {
				case 'home':
					break;
				case 'about':
				case 'program':
				case 'veterans':
				case 'barkode':
				case 'stories':
					$add( 'pages' );
					break;
				case 'get-involved':
					$add( 'pages', 'records' );
					break;
				case 'contact':
					$add( 'pages', 'forms' );
					break;
				case 'application':
					$add( 'content', 'forms', 'records' );
					break;
				case 'donate':
				case 'events':
				case 'campaigns':
				case 'partners':
				case 'teams':
					$add( 'records' );
					break;
				case 'landing':
				case 'people':
				case 'highlighted-team':
				case 'gallery':
				case 'thank-you':
					$add( 'content', 'records' );
					break;
				default:
					$add( 'content' );
					if ( comments_open( $post_id ) || get_comments_number( $post_id ) ) {
						$add( 'blog', 'forms' );
					}
			}
		} elseif ( 'post' === $type ) {
			$add( 'content', 'blog' );
			if ( comments_open( $post_id ) || get_comments_number( $post_id ) ) {
				$add( 'forms' );
			}
		} elseif ( 'attachment' === $type ) {
			$add( 'content', 'blog' );
		} else {
			// hk9_story / hk9_team / hk9_event / hk9_campaign / hk9_barkode singles.
			$add( 'content', 'records' );
		}
	} elseif ( is_404() || is_search() ) {
		// Listing hero / 404 card with the search form.
		$add( 'blog', 'forms' );
	} else {
		// Posts index and archives: listing hero + cards (the empty panel there has no search form).
		$add( 'blog' );
	}

	$order   = [ 'forms', 'content', 'blog', 'pages', 'records' ];
	$bundles = array_values( array_intersect( $order, array_keys( $bundles ) ) );

	/**
	 * Filter the stylesheet bundles enqueued for the current request.
	 *
	 * @param string[] $bundles  Bundle names (subset of content, blog, forms, pages, records).
	 * @param string   $template Page template slug ('' when not a page).
	 */
	$bundles = (array) apply_filters( 'hk9/theme/style_bundles', $bundles, $template );

	return array_values( array_intersect( $order, array_unique( array_map( 'strval', $bundles ) ) ) );
}

/**
 * Enqueue the compiled theme stylesheets and script.
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

		foreach ( hk9_style_bundles() as $bundle ) {
			$file = 'assets/dist/' . $bundle . '.css';
			if ( file_exists( HK9_THEME_DIR . '/' . $file ) ) {
				wp_enqueue_style( 'hk9-' . $bundle, HK9_THEME_URI . '/' . $file, [ 'hk9-theme' ], hk9_asset_version( $file ) );
			}
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
 *
 * The href must be byte-identical to the @font-face `src` the compiled CSS
 * resolves to (assets/fonts/<file>.woff2, no query string) — otherwise the
 * browser cannot match the preload and downloads every font twice.
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
			esc_url( HK9_THEME_URI . '/' . $font )
		);
	}
}
add_action( 'wp_head', 'hk9_preload_fonts', 2 );

/**
 * The image most likely to be the Largest Contentful Paint element for the
 * current request, or null: the image hero (home / program / barkode), the
 * About "legacy" overlap-card photo, or the blog listing hero background.
 *
 * @return array{id:int,size:string,sizes:string,mobile:int}|null
 */
function hk9_lcp_image(): ?array {
	$candidate = null;

	if ( is_singular( 'page' ) ) {
		$post_id  = get_queried_object_id();
		$template = hk9_template_for_post( $post_id );

		if ( in_array( $template, [ 'home', 'program', 'barkode' ], true ) && in_array( 'hero_image', hk9_sections_layout( $post_id, $template ), true ) ) {
			$hero     = hk9_section( $post_id, 'hero_image' );
			$image_id = (int) ( $hero['image'] ?? 0 );
			if ( $image_id <= 0 && has_post_thumbnail( $post_id ) ) {
				$image_id = (int) get_post_thumbnail_id( $post_id );
			}
			$mobile_id = (int) ( $hero['image_mobile'] ?? 0 );
			$candidate = [ 'id' => $image_id, 'size' => 'hk9-hero', 'sizes' => '100vw', 'mobile' => $mobile_id !== $image_id ? $mobile_id : 0 ];
		} elseif ( 'about' === $template && in_array( 'legacy', hk9_sections_layout( $post_id, $template ), true ) ) {
			$legacy    = hk9_section( $post_id, 'legacy' );
			$candidate = [ 'id' => (int) ( $legacy['image'] ?? 0 ), 'size' => 'large', 'sizes' => hk9_legacy_image_sizes(), 'mobile' => 0 ];
		}
	} elseif ( is_home() && function_exists( 'hk9_archive_context' ) ) {
		$context   = hk9_archive_context();
		$candidate = [ 'id' => (int) ( $context['image'] ?? 0 ), 'size' => 'hk9-hero', 'sizes' => '100vw', 'mobile' => 0 ];
	}

	/**
	 * Filter the LCP image preload candidate.
	 *
	 * @param array|null $candidate {id, size, sizes, mobile} or null to skip the preload.
	 */
	$candidate = apply_filters( 'hk9/theme/lcp_image', $candidate );

	if ( ! is_array( $candidate ) || (int) ( $candidate['id'] ?? 0 ) <= 0 || ! wp_attachment_is_image( (int) $candidate['id'] ) ) {
		return null;
	}

	return $candidate;
}

/**
 * `sizes` attribute of the About "legacy" split-card image (shared by the
 * template part and the LCP preload so both pick the same srcset candidate).
 *
 * @return string
 */
function hk9_legacy_image_sizes(): string {
	return '(max-width: 767px) calc(100vw - 32px), (max-width: 1087px) calc(50vw - 32px), 512px';
}

/**
 * Preload the LCP image with the same srcset/sizes the <img> carries, so the
 * request starts with the stylesheet instead of after it. A mobile hero variant
 * (rendered through <picture>) preloads per media query.
 */
function hk9_preload_lcp_image(): void {
	$lcp = hk9_lcp_image();
	if ( null === $lcp ) {
		return;
	}

	$id   = (int) $lcp['id'];
	$src  = wp_get_attachment_image_src( $id, (string) $lcp['size'] );
	if ( ! is_array( $src ) || empty( $src[0] ) ) {
		return;
	}
	$srcset = (string) wp_get_attachment_image_srcset( $id, (string) $lcp['size'] );
	$media  = '';

	$mobile_id = (int) ( $lcp['mobile'] ?? 0 );
	if ( $mobile_id > 0 && wp_attachment_is_image( $mobile_id ) ) {
		$mobile = wp_get_attachment_image_src( $mobile_id, (string) $lcp['size'] );
		if ( is_array( $mobile ) && ! empty( $mobile[0] ) ) {
			printf( '<link rel="preload" as="image" href="%s" media="(max-width: 767px)" fetchpriority="high">' . "\n", esc_url( $mobile[0] ) );
			$media = ' media="(min-width: 768px)"';
		}
	}

	printf(
		'<link rel="preload" as="image" href="%s"%s%s%s fetchpriority="high">' . "\n",
		esc_url( $src[0] ),
		'' !== $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
		'' !== $srcset ? ' imagesizes="' . esc_attr( (string) $lcp['sizes'] ) . '"' : '',
		$media // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string.
	);
}
add_action( 'wp_head', 'hk9_preload_lcp_image', 1 );

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
