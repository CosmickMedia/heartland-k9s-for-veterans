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
			// Editor content shown on a sections template (.hk9-prose card before/after the sections).
			if ( in_array( hk9_editor_content_position( $post_id, $template ), [ 'before', 'after' ], true ) ) {
				$add( 'content' );
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
 * Whether the theme stylesheets are printed inline in the document (default)
 * instead of as render-blocking <link>s.
 *
 * The compiled CSS is small (core 9.6 kB gzip, bundles 2.5–5.5 kB) but every
 * <link> costs a round trip before the first paint, and pages with two or three
 * bundles queue behind the fonts and the hero image on HTTP/1.1. Printing the
 * files inline removes those requests from the critical path with no flash of
 * unstyled content (every rule is present when the parser reaches the body):
 * mobile Lighthouse (docs/performance.md) FCP −0.3 to −0.5 s and Performance
 * 94 → 99 on the three-bundle pages, at the cost of 10–25 kB (gzip) of CSS per
 * page view that is no longer cached across pages. Define `HK9_INLINE_CSS`
 * (wp-config.php) or filter `hk9/theme/inline_css` to go back to <link>s.
 *
 * @return bool
 */
function hk9_inline_css(): bool {
	$default = defined( 'HK9_INLINE_CSS' ) ? (bool) HK9_INLINE_CSS : true;
	/**
	 * Filter whether theme CSS is inlined.
	 *
	 * @param bool $inline Default true.
	 */
	return (bool) apply_filters( 'hk9/theme/inline_css', $default );
}

/**
 * Enqueue one compiled stylesheet — as a <link>, or as an inline <style> with
 * the same handle (so dependencies, order and wp_add_inline_style() keep working).
 *
 * @param string   $handle   Style handle.
 * @param string   $relative Path relative to the theme root.
 * @param string[] $deps     Dependencies.
 * @param bool     $inline   Print the file contents instead of linking it.
 */
function hk9_enqueue_theme_style( string $handle, string $relative, array $deps, bool $inline ): void {
	if ( ! $inline ) {
		wp_enqueue_style( $handle, HK9_THEME_URI . '/' . $relative, $deps, hk9_asset_version( $relative ) );
		return;
	}
	$css = (string) file_get_contents( HK9_THEME_DIR . '/' . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	// Compiled CSS lives in assets/dist/: make its relative font URLs absolute so the
	// font preloads (hk9_preload_fonts()) still match the @font-face sources.
	$css = str_replace( 'url("../', 'url("' . HK9_THEME_URI . '/assets/', $css );
	$css = str_replace( "url('../", "url('" . HK9_THEME_URI . '/assets/', $css );
	$css = str_replace( 'url(../', 'url(' . HK9_THEME_URI . '/assets/', $css );
	wp_register_style( $handle, false, $deps, hk9_asset_version( $relative ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( $handle );
	if ( '' !== $css ) {
		wp_add_inline_style( $handle, $css );
	}
}

/**
 * Enqueue the compiled theme stylesheets and script.
 */
function hk9_enqueue_assets(): void {
	$css = 'assets/dist/theme.css';
	$js  = 'assets/dist/theme.js';

	if ( file_exists( HK9_THEME_DIR . '/' . $css ) ) {
		$inline = hk9_inline_css();
		hk9_enqueue_theme_style( 'hk9-theme', $css, [], $inline );

		$root_css = hk9_root_css();
		if ( '' !== $root_css ) {
			wp_add_inline_style( 'hk9-theme', $root_css );
		}

		foreach ( hk9_style_bundles() as $bundle ) {
			$file = 'assets/dist/' . $bundle . '.css';
			if ( file_exists( HK9_THEME_DIR . '/' . $file ) ) {
				hk9_enqueue_theme_style( 'hk9-' . $bundle, $file, [ 'hk9-theme' ], $inline );
			}
		}
	}

	if ( file_exists( HK9_THEME_DIR . '/' . $js ) ) {
		wp_enqueue_script( 'hk9-theme', HK9_THEME_URI . '/' . $js, [], hk9_asset_version( $js ), [ 'strategy' => 'defer', 'in_footer' => true ] );

		$config = [
			'navBreakpoint' => 1024,
			'adminBar'      => is_admin_bar_showing(),
			'fonts'         => hk9_deferred_fonts(),
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
 * The web fonts the theme ships, from the generated assets/fonts/fonts.json
 * (tools/fonts/build-fonts.py): family, style, weight, file, unicode_range,
 * display, deferred. Empty when the manifest is missing.
 *
 * @return array<int,array{family:string,style:string,weight:string,file:string,unicode_range:string,display:string,deferred:bool}>
 */
function hk9_font_faces(): array {
	static $faces = null;
	if ( null !== $faces ) {
		return $faces;
	}
	$faces = [];
	$file  = HK9_THEME_DIR . '/assets/fonts/fonts.json';
	if ( ! file_exists( $file ) ) {
		return $faces;
	}
	$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	foreach ( (array) ( $json['faces'] ?? [] ) as $face ) {
		if ( ! is_array( $face ) || empty( $face['family'] ) || empty( $face['file'] ) ) {
			continue;
		}
		$faces[] = [
			'family'        => (string) $face['family'],
			'style'         => (string) ( $face['style'] ?? 'normal' ),
			'weight'        => (string) ( $face['weight'] ?? '400' ),
			'file'          => (string) $face['file'],
			'unicode_range' => (string) ( $face['unicode_range'] ?? '' ),
			'display'       => (string) ( $face['display'] ?? 'swap' ),
			'deferred'      => ! empty( $face['deferred'] ),
		];
	}
	return $faces;
}

/**
 * Whether a font family is in use (not replaced by a system stack in Settings).
 *
 * @param string $family Family name from the manifest.
 * @return bool
 */
function hk9_font_family_active( string $family ): bool {
	if ( 'Fraunces' === $family ) {
		return 'system-serif' !== hk9_theme_option( 'fonts.serif' );
	}
	if ( 'Inter' === $family ) {
		return 'system-sans' !== hk9_theme_option( 'fonts.sans' );
	}
	return true;
}

/**
 * Faces the compiled CSS does not declare — the Fraunces italic, used only
 * below the fold (footer tagline, testimonial quotes) — as data for theme.js,
 * which adds them through the Font Loading API once the roman faces and the
 * page have loaded. Keeping the 80 kB italic out of the critical path lets
 * the LCP image and the two preloaded roman faces share the bandwidth on a
 * slow connection (mobile Lighthouse: home 94 → 97 in isolation). The final
 * rendering is unchanged: the italic swaps in exactly as a `font-display:
 * swap` face would, just later; hk9_deferred_fonts_noscript() covers browsers
 * without JavaScript.
 *
 * @return array<int,array{family:string,style:string,weight:string,url:string,unicodeRange:string,display:string}>
 */
function hk9_deferred_fonts(): array {
	$fonts = [];
	foreach ( hk9_font_faces() as $face ) {
		if ( ! $face['deferred'] || ! hk9_font_family_active( $face['family'] ) || ! file_exists( HK9_THEME_DIR . '/assets/fonts/' . $face['file'] ) ) {
			continue;
		}
		$fonts[] = [
			'family'       => $face['family'],
			'style'        => $face['style'],
			'weight'       => $face['weight'],
			'url'          => HK9_THEME_URI . '/assets/fonts/' . $face['file'],
			'unicodeRange' => $face['unicode_range'],
			'display'      => $face['display'],
		];
	}
	/**
	 * Filter the faces theme.js loads after the page has loaded.
	 *
	 * @param array $fonts {family, style, weight, url, unicodeRange, display}[].
	 */
	return (array) apply_filters( 'hk9/theme/deferred_fonts', $fonts );
}

/**
 * The deferred faces as plain @font-face rules ('' when there are none).
 *
 * @return string
 */
function hk9_deferred_fonts_css(): string {
	$css = '';
	foreach ( hk9_deferred_fonts() as $font ) {
		if ( ! is_array( $font ) || empty( $font['url'] ) || empty( $font['family'] ) ) {
			continue;
		}
		$css .= sprintf(
			'@font-face{font-family:%s;font-style:%s;font-weight:%s;font-display:%s;src:url(%s) format("woff2");%s}',
			wp_json_encode( (string) $font['family'] ),
			esc_attr( (string) ( $font['style'] ?? 'normal' ) ),
			esc_attr( (string) ( $font['weight'] ?? '400' ) ),
			esc_attr( (string) ( $font['display'] ?? 'swap' ) ),
			esc_url( (string) $font['url'] ),
			! empty( $font['unicodeRange'] ) ? 'unicode-range:' . esc_attr( (string) $font['unicodeRange'] ) . ';' : ''
		);
	}
	return $css;
}

/**
 * No-JavaScript fallback for the deferred faces: the same @font-face rules,
 * declared in the head so the browser fetches them as usual.
 */
function hk9_deferred_fonts_noscript(): void {
	$css = hk9_deferred_fonts_css();
	if ( '' !== $css ) {
		echo '<noscript><style id="hk9-fonts-noscript">' . $css . '</style></noscript>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in hk9_deferred_fonts_css().
	}
}
add_action( 'wp_head', 'hk9_deferred_fonts_noscript', 3 );

/**
 * The block editor canvas declares the deferred faces directly (editor.css is
 * compiled from the same partial and therefore lacks them; nothing is deferred
 * in the editor).
 */
function hk9_editor_deferred_fonts(): void {
	if ( ! is_admin() ) {
		return;
	}
	$css = hk9_deferred_fonts_css();
	if ( '' !== $css ) {
		wp_add_inline_style( 'wp-block-library', $css );
	}
}
add_action( 'enqueue_block_assets', 'hk9_editor_deferred_fonts' );

/**
 * Preload the two roman variable fonts (the italic is deferred, see
 * hk9_deferred_fonts()).
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

	// A phone variant (hero_image.image_mobile, rendered through <picture>) is
	// preloaded per media query with the same candidates as its <source>, so the
	// preload matches the request the browser makes and nothing loads twice.
	$mobile = ( 'hk9-hero' === (string) $lcp['size'] && function_exists( 'hk9_hero_mobile_source' ) ) ? hk9_hero_mobile_source( $id, (int) ( $lcp['mobile'] ?? 0 ) ) : null;
	if ( null !== $mobile ) {
		printf(
			'<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" media="%s" fetchpriority="high">' . "\n",
			esc_url( $mobile['src'] ),
			esc_attr( $mobile['srcset'] ),
			esc_attr( $mobile['sizes'] ),
			esc_attr( hk9_hero_mobile_media() )
		);
		$media = ' media="' . esc_attr( hk9_hero_desktop_media() ) . '"';
	}

	printf(
		'<link rel="preload" as="image" href="%s"%s%s%s fetchpriority="high">' . "\n",
		esc_url( $src[0] ),
		'' !== $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
		'' !== $srcset ? ' imagesizes="' . esc_attr( (string) $lcp['sizes'] ) . '"' : '',
		$media // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	);
}
add_action( 'wp_head', 'hk9_preload_lcp_image', 1 );

/**
 * Gravity Forms 3.0 registers `gravity_forms_orbital_theme` (the Orbital
 * theme's own stylesheet) but ships the file empty — its rules moved into the
 * theme framework file. On a form page that is one render-blocking request
 * (a full round trip on mobile) for zero bytes of CSS, so it is dropped —
 * only while the file on disk really is empty, so a later release that
 * fills it again is served untouched. Gravity Forms itself only enqueues on
 * pages that render a form; nothing else is touched.
 */
function hk9_drop_empty_gravity_styles( $tag, $handle, $href ) {
	if ( 'gravity_forms_orbital_theme' !== $handle || is_admin() ) {
		return $tag;
	}
	static $empty = null;
	if ( null === $empty ) {
		$empty = false;
		$path  = (string) wp_parse_url( (string) $href, PHP_URL_PATH );
		$base  = (string) wp_parse_url( plugins_url(), PHP_URL_PATH );
		if ( '' !== $path && '' !== $base && str_starts_with( $path, $base ) ) {
			$file  = WP_PLUGIN_DIR . substr( $path, strlen( $base ) );
			$empty = is_file( $file ) && 0 === (int) filesize( $file );
		}
	}
	return $empty ? '' : $tag;
}
add_filter( 'style_loader_tag', 'hk9_drop_empty_gravity_styles', 10, 3 );

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
