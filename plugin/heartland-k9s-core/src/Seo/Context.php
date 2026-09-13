<?php
/**
 * Per-request SEO view context: what is being viewed, its canonical URL,
 * title, description and social image — computed once from the main query
 * (after `wp`) and shared by the head tags, the JSON-LD graph and the
 * breadcrumbs.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

defined( 'ABSPATH' ) || exit;

final class Context {

	/** @var array<string, mixed> */
	private static array $cache = [];

	/** Clears the per-request cache (tests / after query changes). */
	public static function flush(): void {
		self::$cache = [];
	}

	/**
	 * View kind: front | home | singular | archive | search | 404 | other.
	 */
	public static function kind(): string {
		if ( is_404() ) {
			return '404';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_front_page() ) {
			return 'front';
		}
		if ( is_home() ) {
			return 'home';
		}
		if ( is_singular() ) {
			return 'singular';
		}
		if ( is_archive() ) {
			return 'archive';
		}
		return 'other';
	}

	/** The queried post for singular views (front page included), else null. */
	public static function post(): ?\WP_Post {
		if ( is_singular() || ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				return $post;
			}
		}
		if ( is_home() && ! is_front_page() ) {
			$page = get_post( (int) get_option( 'page_for_posts' ) );
			return $page instanceof \WP_Post ? $page : null;
		}
		return null;
	}

	/** Template slug of the queried page ('' when not a page). */
	public static function template(): string {
		$post = self::post();
		if ( ! $post || 'page' !== $post->post_type || ! function_exists( 'hk9_template_for_post' ) ) {
			return '';
		}
		return (string) hk9_template_for_post( (int) $post->ID );
	}

	/** Current page number (paged for listings, page for split content). */
	public static function paged(): int {
		$paged = (int) get_query_var( 'paged' );
		if ( $paged < 1 ) {
			$paged = (int) get_query_var( 'page' );
		}
		return max( 1, $paged );
	}

	/**
	 * Self-referencing canonical URL for the current view (no query string,
	 * paged listings keep their page number). '' for search/404.
	 */
	public static function canonical(): string {
		if ( array_key_exists( 'canonical', self::$cache ) ) {
			return self::$cache['canonical'];
		}
		$url = self::compute_canonical();
		/**
		 * Filters the canonical URL for the current view ('' = none).
		 *
		 * @param string $url URL.
		 */
		self::$cache['canonical'] = (string) apply_filters( 'hk9/seo/canonical', $url );
		return self::$cache['canonical'];
	}

	private static function compute_canonical(): string {
		$kind = self::kind();
		if ( '404' === $kind || 'search' === $kind ) {
			return '';
		}
		$paged = self::paged();

		if ( 'singular' === $kind || 'front' === $kind ) {
			$post = self::post();
			if ( ! $post ) {
				return self::with_page( home_url( '/' ), $paged );
			}
			$override = (string) Fields::get( (int) $post->ID, 'canonical', '' );
			if ( '' !== $override ) {
				return $override;
			}
			$permalink = 'front' === $kind ? home_url( '/' ) : (string) get_permalink( $post );
			return self::with_page( $permalink, $paged );
		}

		if ( 'home' === $kind ) {
			$page = (int) get_option( 'page_for_posts' );
			$base = $page > 0 ? (string) get_permalink( $page ) : home_url( '/' );
			return self::with_page( $base, $paged );
		}

		if ( 'archive' === $kind ) {
			$base = '';
			$obj  = get_queried_object();
			if ( is_category() || is_tag() || is_tax() ) {
				$link = $obj instanceof \WP_Term ? get_term_link( $obj ) : '';
				$base = is_string( $link ) ? $link : '';
			} elseif ( is_author() ) {
				$base = $obj instanceof \WP_User ? (string) get_author_posts_url( (int) $obj->ID ) : '';
			} elseif ( is_day() ) {
				$base = (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
			} elseif ( is_month() ) {
				$base = (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
			} elseif ( is_year() ) {
				$base = (string) get_year_link( (int) get_query_var( 'year' ) );
			} elseif ( is_post_type_archive() ) {
				$link = get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
				$base = is_string( $link ) ? $link : '';
			}
			if ( '' === $base ) {
				$base = self::request_url();
			}
			return self::with_page( $base, $paged );
		}

		return self::request_url();
	}

	/**
	 * URL for `<link rel="canonical">` (and rel prev/next): the view's
	 * canonical, or '' on a singular view marked noindex — per-post toggle,
	 * registry record, local fixture — unless the per-post canonical override
	 * names another URL explicitly. A self-referencing canonical next to
	 * `noindex` sends conflicting signals ("index this URL" / "do not"), so
	 * noindexed views print none; Open Graph keeps `og:url` (sharing only).
	 */
	public static function rel_canonical(): string {
		if ( self::noindex() && ! self::has_canonical_override() ) {
			return '';
		}
		return self::canonical();
	}

	/** Whether the queried post carries an explicit canonical URL override (per-post field). */
	public static function has_canonical_override(): bool {
		$post = self::post();
		return $post instanceof \WP_Post && '' !== (string) Fields::get( (int) $post->ID, 'canonical', '' );
	}

	/** Adds /page/N/ (or ?paged=N) to a base URL. */
	private static function with_page( string $base, int $paged ): string {
		$base = (string) strtok( $base, '?' );
		if ( $paged < 2 ) {
			return $base;
		}
		if ( '' !== (string) get_option( 'permalink_structure' ) ) {
			return trailingslashit( $base ) . user_trailingslashit( 'page/' . $paged, 'paged' );
		}
		return add_query_arg( 'paged', $paged, $base );
	}

	/** The current pretty request URL without query string (fallback). */
	private static function request_url(): string {
		global $wp;
		$request = $wp instanceof \WP ? (string) $wp->request : '';
		$url     = home_url( '/' . ltrim( $request, '/' ) );
		if ( '' !== $request ) {
			$url = user_trailingslashit( $url );
		}
		return (string) strtok( $url, '?' );
	}

	/**
	 * Page title without the site name (SEO field → document title part).
	 */
	public static function title(): string {
		if ( array_key_exists( 'title', self::$cache ) ) {
			return self::$cache['title'];
		}
		$title = '';
		$post  = self::post();
		if ( $post && in_array( self::kind(), [ 'singular', 'front', 'home' ], true ) ) {
			$title = (string) Fields::get( (int) $post->ID, 'title', '' );
		}
		if ( '' === $title ) {
			$parts = self::title_parts();
			$title = (string) ( $parts['title'] ?? '' );
			if ( '' === $title ) {
				$title = 'front' === self::kind() ? (string) get_bloginfo( 'name' ) : wp_get_document_title();
			}
		}
		self::$cache['title'] = trim( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		return self::$cache['title'];
	}

	/** Meta description for the view ('' = none). */
	public static function description(): string {
		if ( array_key_exists( 'description', self::$cache ) ) {
			return self::$cache['description'];
		}
		self::$cache['description'] = Description::for_view();
		return self::$cache['description'];
	}

	/**
	 * Social image {id,url,width,height,alt,mime} or null.
	 *
	 * @return array{id:int,url:string,width:int,height:int,alt:string,mime:string}|null
	 */
	public static function image(): ?array {
		if ( array_key_exists( 'image', self::$cache ) ) {
			return self::$cache['image'];
		}
		self::$cache['image'] = Image::for_view();
		return self::$cache['image'];
	}

	/** Whether the current singular view is marked noindex (field or registry). */
	public static function noindex(): bool {
		if ( is_singular( 'hk9_barkode' ) ) {
			return true;
		}
		$post = self::post();
		if ( $post && is_singular() ) {
			if ( Fields::is_noindex( (int) $post->ID ) ) {
				return true;
			}
			if ( get_post_meta( (int) $post->ID, '_hk9_local_fixture', true ) ) {
				return true;
			}
		}
		return false;
	}

	/** Open Graph type for the view. */
	public static function og_type(): string {
		return is_singular( 'post' ) ? 'article' : 'website';
	}

	/** BCP-47 language tag for the view (en-US). */
	public static function language(): string {
		return str_replace( '_', '-', (string) get_locale() );
	}

	/**
	 * Document title parts as core computes them (`document_title_parts`
	 * filters included), without the final join.
	 *
	 * @return array<string, string>
	 */
	public static function title_parts(): array {
		$title = [ 'title' => '' ];

		if ( is_404() ) {
			$title['title'] = __( 'Page not found' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string.
		} elseif ( is_search() ) {
			/* translators: %s: search query */
			$title['title'] = sprintf( __( 'Search Results for &#8220;%s&#8221;' ), get_search_query() ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string.
		} elseif ( is_front_page() ) {
			$title['title'] = (string) get_bloginfo( 'name', 'display' );
		} elseif ( is_post_type_archive() ) {
			$title['title'] = (string) post_type_archive_title( '', false );
		} elseif ( is_tax() ) {
			$title['title'] = (string) single_term_title( '', false );
		} elseif ( is_home() || is_singular() ) {
			$title['title'] = (string) single_post_title( '', false );
		} elseif ( is_category() || is_tag() ) {
			$title['title'] = (string) single_term_title( '', false );
		} elseif ( is_author() && get_queried_object() ) {
			$author         = get_queried_object();
			$title['title'] = (string) $author->display_name;
		} elseif ( is_year() ) {
			$title['title'] = (string) get_the_date( _x( 'Y', 'yearly archives date format' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string.
		} elseif ( is_month() ) {
			$title['title'] = (string) get_the_date( _x( 'F Y', 'monthly archives date format' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string.
		} elseif ( is_day() ) {
			$title['title'] = (string) get_the_date();
		}

		$paged = self::paged();
		if ( $paged >= 2 && ! is_404() ) {
			/* translators: %s: page number */
			$title['page'] = sprintf( __( 'Page %s' ), $paged ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string.
		}

		if ( is_front_page() ) {
			$title['tagline'] = (string) get_bloginfo( 'description', 'display' );
		} else {
			$title['site'] = (string) get_bloginfo( 'name', 'display' );
		}

		/** This filter is documented in wp-includes/general-template.php */
		$title = (array) apply_filters( 'document_title_parts', $title );
		return array_map( static fn( $part ) => is_scalar( $part ) ? (string) $part : '', $title );
	}
}
