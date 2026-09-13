<?php
/**
 * Full-mode head output: document title parts, meta description, canonical,
 * robots directives, rel prev/next on paginated listings, Open Graph and
 * Twitter cards. Silent in plugin-managed mode (Detector::prints_meta()).
 * Views marked noindex (per-post toggle, registry records, local fixtures)
 * get the robots directive and the social tags but no `<link rel="canonical">`
 * / prev / next unless the per-post canonical override names another URL
 * (Context::rel_canonical()).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

defined( 'ABSPATH' ) || exit;

final class Head {

	public static function register(): void {
		add_filter( 'document_title_parts', [ self::class, 'title_parts' ], 20 );
		add_filter( 'wp_robots', [ self::class, 'robots' ], 25 );
		add_action( 'wp', [ self::class, 'take_over_core_tags' ] );
		add_action( 'wp_head', [ self::class, 'print' ], 1 );
	}

	/**
	 * Title parts: the SEO title field replaces the whole title; the posts
	 * page uses the configured listing title.
	 *
	 * @param array<string, string> $parts Parts.
	 * @return array<string, string>
	 */
	public static function title_parts( array $parts ): array {
		if ( ! Detector::prints_meta() ) {
			return $parts;
		}
		if ( is_home() && ! is_front_page() && function_exists( 'hk9_option' ) ) {
			$title = (string) hk9_option( 'blog.hero_title', '' );
			if ( '' !== trim( $title ) ) {
				$parts['title'] = $title;
			}
		}
		$post = Context::post();
		if ( $post && ( is_singular() || is_front_page() || is_home() ) ) {
			$seo_title = (string) Fields::get( (int) $post->ID, 'title', '' );
			if ( '' !== trim( $seo_title ) ) {
				$page = $parts['page'] ?? '';
				$parts = [ 'title' => $seo_title ];
				if ( '' !== $page ) {
					$parts['page'] = $page;
				}
			}
		}
		return $parts;
	}

	/**
	 * Robots: noindex for the field / registry / fixtures (follow kept so links
	 * are still crawled); search results are handled by core.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( ! Detector::owns_meta() ) {
			return $robots;
		}
		if ( is_singular() && Context::noindex() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			if ( ! isset( $robots['nofollow'] ) ) {
				$robots['follow'] = true;
			}
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/** Core prints rel=canonical on singular views only; ours covers every indexable view. */
	public static function take_over_core_tags(): void {
		if ( ! Detector::prints_meta() ) {
			return;
		}
		remove_action( 'wp_head', 'rel_canonical' );
		// Privacy\Registry prints a description on registry singles when nothing else does.
		if ( class_exists( 'HK9\\Core\\Privacy\\Registry' ) ) {
			remove_action( 'wp_head', [ 'HK9\\Core\\Privacy\\Registry', 'meta_description' ], 1 );
		}
	}

	/** wp_head (priority 1): description, canonical + prev/next (indexable views only), Open Graph, Twitter. */
	public static function print(): void {
		if ( ! Detector::prints_meta() || is_admin() || is_feed() || is_embed() ) {
			return;
		}
		if ( is_preview() || is_customize_preview() ) {
			return;
		}
		$kind = Context::kind();
		$out  = [];

		$description = Context::description();
		$canonical   = Context::canonical();     // The view's URL (og:url).
		$rel         = Context::rel_canonical(); // '' on noindexed views without an explicit override.

		if ( '' !== $description && '404' !== $kind && 'search' !== $kind ) {
			$out[] = sprintf( '<meta name="description" content="%s">', esc_attr( $description ) );
		}
		if ( '' !== $rel ) {
			$out[] = sprintf( '<link rel="canonical" href="%s">', esc_url( $rel ) );
			foreach ( self::prev_next() as $rel_name => $url ) {
				$out[] = sprintf( '<link rel="%s" href="%s">', esc_attr( $rel_name ), esc_url( $url ) );
			}
		}

		if ( '404' !== $kind && 'search' !== $kind ) {
			$title = Context::title();
			$image = Context::image();
			$out[] = sprintf( '<meta property="og:locale" content="%s">', esc_attr( str_replace( '-', '_', Context::language() ) ) );
			$out[] = sprintf( '<meta property="og:type" content="%s">', esc_attr( Context::og_type() ) );
			$out[] = sprintf( '<meta property="og:title" content="%s">', esc_attr( $title ) );
			if ( '' !== $description ) {
				$out[] = sprintf( '<meta property="og:description" content="%s">', esc_attr( $description ) );
			}
			if ( '' !== $canonical ) {
				$out[] = sprintf( '<meta property="og:url" content="%s">', esc_url( $canonical ) );
			}
			$out[] = sprintf( '<meta property="og:site_name" content="%s">', esc_attr( (string) get_bloginfo( 'name' ) ) );
			if ( $image ) {
				$out[] = sprintf( '<meta property="og:image" content="%s">', esc_url( $image['url'] ) );
				if ( str_starts_with( $image['url'], 'https://' ) ) {
					$out[] = sprintf( '<meta property="og:image:secure_url" content="%s">', esc_url( $image['url'] ) );
				}
				if ( $image['width'] > 0 && $image['height'] > 0 ) {
					$out[] = sprintf( '<meta property="og:image:width" content="%d">', (int) $image['width'] );
					$out[] = sprintf( '<meta property="og:image:height" content="%d">', (int) $image['height'] );
				}
				if ( '' !== $image['mime'] ) {
					$out[] = sprintf( '<meta property="og:image:type" content="%s">', esc_attr( $image['mime'] ) );
				}
				if ( '' !== $image['alt'] ) {
					$out[] = sprintf( '<meta property="og:image:alt" content="%s">', esc_attr( $image['alt'] ) );
				}
			}
			$post = Context::post();
			if ( $post && is_singular( 'post' ) ) {
				$out[] = sprintf( '<meta property="article:published_time" content="%s">', esc_attr( (string) get_the_date( 'c', $post ) ) );
				$out[] = sprintf( '<meta property="article:modified_time" content="%s">', esc_attr( (string) get_the_modified_date( 'c', $post ) ) );
				foreach ( (array) get_the_category( (int) $post->ID ) as $category ) {
					if ( $category instanceof \WP_Term ) {
						$out[] = sprintf( '<meta property="article:section" content="%s">', esc_attr( $category->name ) );
						break;
					}
				}
			}

			$out[] = sprintf( '<meta name="twitter:card" content="%s">', $image ? 'summary_large_image' : 'summary' );
			$out[] = sprintf( '<meta name="twitter:title" content="%s">', esc_attr( $title ) );
			if ( '' !== $description ) {
				$out[] = sprintf( '<meta name="twitter:description" content="%s">', esc_attr( $description ) );
			}
			if ( $image ) {
				$out[] = sprintf( '<meta name="twitter:image" content="%s">', esc_url( $image['url'] ) );
				if ( '' !== $image['alt'] ) {
					$out[] = sprintf( '<meta name="twitter:image:alt" content="%s">', esc_attr( $image['alt'] ) );
				}
			}
			$handle = self::twitter_handle();
			if ( '' !== $handle ) {
				$out[] = sprintf( '<meta name="twitter:site" content="%s">', esc_attr( $handle ) );
			}
		}

		/**
		 * Filters the head tags printed in full mode (one tag per entry, already escaped).
		 *
		 * @param string[] $out Tags.
		 */
		$out = (array) apply_filters( 'hk9/seo/head_tags', $out );
		if ( $out ) {
			echo "\n<!-- Heartland K9s SEO -->\n" . implode( "\n", array_map( 'strval', $out ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each tag escaped above.
		}
	}

	/**
	 * rel prev/next for paginated listings (blog home, archives, search).
	 *
	 * @return array<string, string> rel => url
	 */
	public static function prev_next(): array {
		global $wp_query;
		if ( ! ( is_home() || is_archive() ) || ! $wp_query instanceof \WP_Query ) {
			return [];
		}
		$max = (int) $wp_query->max_num_pages;
		if ( $max < 2 ) {
			return [];
		}
		$paged = Context::paged();
		$links = [];
		if ( $paged > 1 ) {
			$links['prev'] = self::page_url( $paged - 1 );
		}
		if ( $paged < $max ) {
			$links['next'] = self::page_url( $paged + 1 );
		}
		return array_filter( $links );
	}

	/** URL of page N of the current listing (from the canonical of page 1). */
	private static function page_url( int $n ): string {
		$canonical = Context::canonical();
		if ( '' === $canonical ) {
			return '';
		}
		$base = preg_replace( '#/page/\d+/?$#', '/', (string) strtok( $canonical, '?' ) ) ?? $canonical;
		$base = remove_query_arg( 'paged', $base );
		if ( $n < 2 ) {
			return $base;
		}
		if ( '' !== (string) get_option( 'permalink_structure' ) ) {
			return trailingslashit( $base ) . user_trailingslashit( 'page/' . $n, 'paged' );
		}
		return add_query_arg( 'paged', $n, $base );
	}

	/** @-handle from the X / Twitter profile URL or the Settings → SEO handle. */
	public static function twitter_handle(): string {
		$handle = function_exists( 'hk9_option' ) ? (string) hk9_option( 'seo.twitter_site', '' ) : '';
		$handle = trim( $handle );
		if ( '' === $handle && function_exists( 'hk9_option' ) ) {
			$url = (string) hk9_option( 'contact.x', '' );
			if ( preg_match( '#^https?://(?:www\.)?(?:twitter|x)\.com/@?([A-Za-z0-9_]{1,15})/?#i', $url, $m ) ) {
				$handle = $m[1];
			}
		}
		$handle = ltrim( $handle, '@' );
		return '' !== $handle && preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ? '@' . $handle : '';
	}
}
