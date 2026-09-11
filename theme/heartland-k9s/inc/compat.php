<?php
/**
 * Compatibility: SEO meta (Open Graph / Twitter / description) only when no SEO
 * plugin is active and `advanced.output_seo_meta` is on; robots tweaks.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a known SEO plugin is handling meta output.
 *
 * @return bool
 */
function hk9_seo_plugin_active(): bool {
	$active = defined( 'WPSEO_VERSION' )            // Yoast SEO
		|| class_exists( 'RankMath' )                 // Rank Math
		|| defined( 'SLIM_SEO_VER' )                  // Slim SEO
		|| defined( 'AIOSEO_VERSION' )                // All in One SEO
		|| defined( 'SEOPRESS_VERSION' )              // SEOPress
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' );    // The SEO Framework

	/**
	 * Filter whether the theme should stay silent on SEO meta.
	 *
	 * @param bool $active True when an SEO plugin is active.
	 */
	return (bool) apply_filters( 'hk9/theme/seo_plugin_active', $active );
}

/**
 * Meta description for the current view.
 *
 * @return string
 */
function hk9_meta_description(): string {
	$description = '';

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			if ( 'hk9_barkode' === $post->post_type ) {
				return __( 'Heartland Canines for Veterans BarKode registry record.', 'heartland-k9s' );
			}

			if ( has_excerpt( $post ) ) {
				$description = get_the_excerpt( $post );
			} else {
				$template = hk9_template_for_post( (int) $post->ID );
				if ( in_array( $template, [ 'home', 'program', 'barkode' ], true ) ) {
					$hero        = hk9_section( (int) $post->ID, 'hero_image' );
					$description = (string) ( $hero['text'] ?? '' );
				} elseif ( 'default' !== $template ) {
					$hero        = hk9_section( (int) $post->ID, 'hero_band' );
					$description = (string) ( $hero['text'] ?? '' );
				}
				if ( '' === trim( $description ) ) {
					$description = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
				}
			}
		}
	} elseif ( is_home() && ! is_front_page() ) {
		$description = (string) hk9_theme_option( 'blog.hero_text' );
	} elseif ( is_archive() ) {
		$description = wp_strip_all_tags( get_the_archive_description() );
	}

	if ( '' === trim( $description ) ) {
		$description = (string) get_bloginfo( 'description' );
	}

	$description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );

	return wp_html_excerpt( $description, 300, '…' );
}

/**
 * Open Graph image for the current view: featured image → hero image → footer logo.
 *
 * @return array{url:string,width:int,height:int}|null
 */
function hk9_meta_image(): ?array {
	$id = 0;

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		if ( has_post_thumbnail( $post_id ) ) {
			$id = (int) get_post_thumbnail_id( $post_id );
		} else {
			$template = hk9_template_for_post( $post_id );
			if ( in_array( $template, [ 'home', 'program', 'barkode' ], true ) ) {
				$hero = hk9_section( $post_id, 'hero_image' );
				$id   = (int) ( $hero['image'] ?? 0 );
			}
		}
	}

	if ( $id <= 0 ) {
		$id = (int) hk9_theme_option( 'blog.hero_image', 0 );
	}
	if ( $id <= 0 ) {
		$id = (int) hk9_theme_option( 'branding.header_logo', 0 );
	}
	if ( $id <= 0 ) {
		$id = (int) get_theme_mod( 'custom_logo', 0 );
	}
	if ( $id <= 0 ) {
		return null;
	}

	$src = wp_get_attachment_image_src( $id, 'large' );
	if ( ! $src ) {
		return null;
	}

	return [ 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ];
}

/**
 * Print description / Open Graph / Twitter meta.
 */
function hk9_print_seo_meta(): void {
	if ( hk9_seo_plugin_active() || ! hk9_theme_option( 'advanced.output_seo_meta' ) ) {
		return;
	}
	if ( is_404() || is_search() ) {
		return;
	}

	$description = hk9_meta_description();
	$title       = wp_get_document_title();
	$url         = '';

	if ( is_singular() ) {
		$url = get_permalink( get_queried_object_id() );
	} elseif ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() ) {
		$url = get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/' );
	}

	echo "\n<!-- Heartland K9s SEO meta -->\n";
	if ( '' !== $description ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
	}
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( str_replace( '-', '_', get_locale() ) ) );
	if ( '' !== $url ) {
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	}

	$image = ( is_singular( 'hk9_barkode' ) ) ? null : hk9_meta_image();
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image['url'] ) );
		if ( $image['width'] > 0 && $image['height'] > 0 ) {
			printf( '<meta property="og:image:width" content="%d">' . "\n", (int) $image['width'] );
			printf( '<meta property="og:image:height" content="%d">' . "\n", (int) $image['height'] );
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image['url'] ) );
	} else {
		echo '<meta name="twitter:card" content="summary">' . "\n";
	}
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
	if ( '' !== $description ) {
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $description ) );
	}
}
add_action( 'wp_head', 'hk9_print_seo_meta', 1 );

/**
 * Robots: never index the registry or local fixtures; search results are handled by core.
 *
 * @param array $robots Directives.
 * @return array
 */
function hk9_robots( array $robots ): array {
	if ( is_singular( 'hk9_barkode' ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['max-image-preview'] );
	}

	if ( is_singular() && get_post_meta( get_queried_object_id(), '_hk9_local_fixture', true ) ) {
		$robots['noindex'] = true;
	}

	return $robots;
}
add_filter( 'wp_robots', 'hk9_robots', 20 );

/**
 * Site-name + tagline title on the front page (core uses "Site – Tagline" already);
 * make the blog listing use the configured blog hero title.
 *
 * @param array $parts Title parts.
 * @return array
 */
function hk9_document_title_parts( array $parts ): array {
	if ( is_home() && ! is_front_page() ) {
		$title = (string) hk9_theme_option( 'blog.hero_title' );
		if ( '' !== $title ) {
			$parts['title'] = $title;
		}
	}
	return $parts;
}
add_filter( 'document_title_parts', 'hk9_document_title_parts' );
