<?php
/**
 * Breadcrumb trail (Home › section › page) shared by the theme's visible
 * breadcrumbs and the BreadcrumbList structured data.
 *
 * Sections: page ancestors for pages, the posts page (News) for posts and
 * blog archives, the listing page picked under Settings → Destinations
 * (links.stories, links.events, …) for the custom types.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

defined( 'ABSPATH' ) || exit;

final class Breadcrumbs {

	/** @var array<int, array{name:string,url:string}>|null */
	private static ?array $cache = null;

	/** Listing setting per custom post type. */
	private const LISTINGS = [
		'hk9_story'    => [ 'links.stories', 'stories' ],
		'hk9_team'     => [ 'links.teams', 'hk9-current-teams-in-training' ],
		'hk9_campaign' => [ 'links.campaigns', 'campaigns' ],
		'hk9_event'    => [ 'links.events', 'events' ],
		'hk9_barkode'  => [ 'links.barkode', 'barkode' ],
	];

	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Trail for the current view. The last item is the current page (its
	 * `url` is kept so the schema can link it; the theme prints it unlinked).
	 * Empty on the front page and on 404s.
	 *
	 * @return array<int, array{name:string,url:string}>
	 */
	public static function trail(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$trail = self::compute();
		/**
		 * Filters the breadcrumb trail ([ {name, url}, … ], first = Home, last = current).
		 *
		 * @param array $trail Trail.
		 */
		$trail = (array) apply_filters( 'hk9/seo/breadcrumbs', $trail );
		// Drop empty names and consecutive duplicates (a listing page reached through its own link).
		$clean = [];
		foreach ( $trail as $item ) {
			if ( ! is_array( $item ) || '' === trim( (string) ( $item['name'] ?? '' ) ) ) {
				continue;
			}
			$item = [
				'name' => trim( (string) $item['name'] ),
				'url'  => (string) ( $item['url'] ?? '' ),
			];
			$last = end( $clean );
			if ( $last && '' !== $item['url'] && $last['url'] === $item['url'] ) {
				continue;
			}
			$clean[] = $item;
		}
		self::$cache = $clean;
		return self::$cache;
	}

	/** Trail for a given post (used by listings that describe other posts). */
	public static function for_post( \WP_Post $post ): array {
		$trail = [ self::home() ];
		if ( 'page' === $post->post_type ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				$trail[] = self::item( (string) get_the_title( $ancestor_id ), (string) get_permalink( $ancestor_id ) );
			}
		} elseif ( 'post' === $post->post_type ) {
			$news = self::posts_page();
			if ( $news ) {
				$trail[] = $news;
			}
		} elseif ( isset( self::LISTINGS[ $post->post_type ] ) ) {
			$listing = self::listing( $post->post_type );
			if ( $listing ) {
				$trail[] = $listing;
			}
		}
		$trail[] = self::item( (string) get_the_title( $post ), (string) get_permalink( $post ) );
		return $trail;
	}

	private static function compute(): array {
		$kind = Context::kind();
		if ( '404' === $kind || 'front' === $kind ) {
			return [];
		}
		if ( 'search' === $kind ) {
			return [
				self::home(),
				/* translators: %s: search query */
				self::item( sprintf( __( 'Search results for “%s”', 'heartland-k9s-core' ), get_search_query() ), '' ),
			];
		}
		if ( 'home' === $kind ) {
			$news = self::posts_page();
			return $news ? [ self::home(), $news ] : [ self::home(), self::item( __( 'News', 'heartland-k9s-core' ), Context::canonical() ) ];
		}
		if ( 'singular' === $kind ) {
			$post = Context::post();
			return $post ? self::for_post( $post ) : [ self::home() ];
		}
		if ( 'archive' === $kind ) {
			$trail = [ self::home() ];
			$obj   = get_queried_object();
			if ( is_post_type_archive() ) {
				$trail[] = self::item( (string) post_type_archive_title( '', false ), Context::canonical() );
				return $trail;
			}
			$news = self::posts_page();
			if ( $news ) {
				$trail[] = $news;
			}
			if ( $obj instanceof \WP_Term ) {
				if ( is_taxonomy_hierarchical( $obj->taxonomy ) ) {
					foreach ( array_reverse( get_ancestors( $obj->term_id, $obj->taxonomy, 'taxonomy' ) ) as $parent_id ) {
						$parent = get_term( $parent_id, $obj->taxonomy );
						if ( $parent instanceof \WP_Term ) {
							$link    = get_term_link( $parent );
							$trail[] = self::item( $parent->name, is_string( $link ) ? $link : '' );
						}
					}
				}
				$link    = get_term_link( $obj );
				$trail[] = self::item( $obj->name, is_string( $link ) ? $link : '' );
			} elseif ( $obj instanceof \WP_User ) {
				$trail[] = self::item( (string) $obj->display_name, (string) get_author_posts_url( (int) $obj->ID ) );
			} elseif ( is_date() ) {
				$year  = (int) get_query_var( 'year' );
				$month = (int) get_query_var( 'monthnum' );
				$day   = (int) get_query_var( 'day' );
				if ( $year > 0 ) {
					$trail[] = self::item( (string) $year, (string) get_year_link( $year ) );
				}
				if ( $month > 0 ) {
					$trail[] = self::item( (string) get_the_date( 'F', null ), (string) get_month_link( $year, $month ) );
				}
				if ( $day > 0 ) {
					$trail[] = self::item( (string) $day, (string) get_day_link( $year, $month, $day ) );
				}
			} else {
				$trail[] = self::item( (string) get_the_archive_title(), Context::canonical() );
			}
			return $trail;
		}
		return [ self::home() ];
	}

	private static function home(): array {
		/**
		 * Filters the breadcrumb label of the front page.
		 *
		 * @param string $label Label.
		 */
		return self::item( (string) apply_filters( 'hk9/seo/breadcrumb_home_label', __( 'Home', 'heartland-k9s-core' ) ), home_url( '/' ) );
	}

	private static function item( string $name, string $url ): array {
		return [
			'name' => Description::clean( $name ),
			'url'  => $url,
		];
	}

	/** The posts page (News) item, or null when the blog has no page. */
	private static function posts_page(): ?array {
		$page_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_for_posts' ) : 0;
		if ( $page_id <= 0 ) {
			return null;
		}
		$label = function_exists( 'hk9_option' ) ? (string) hk9_option( 'blog.hero_title', '' ) : '';
		if ( '' === trim( $label ) ) {
			$label = (string) get_the_title( $page_id );
		}
		return self::item( $label, (string) get_permalink( $page_id ) );
	}

	/** The listing page of a custom post type (Settings → Destinations, then the reference path). */
	public static function listing( string $post_type ): ?array {
		if ( ! isset( self::LISTINGS[ $post_type ] ) ) {
			return null;
		}
		[ $setting, $path ] = self::LISTINGS[ $post_type ];
		$link = function_exists( 'hk9_option' ) ? hk9_option( $setting ) : null;
		if ( is_array( $link ) ) {
			$post_id = (int) ( $link['post_id'] ?? 0 );
			if ( $post_id > 0 && 'publish' === get_post_status( $post_id ) ) {
				return self::item( (string) get_the_title( $post_id ), (string) get_permalink( $post_id ) );
			}
			$url = trim( (string) ( $link['url'] ?? '' ) );
			if ( '' !== $url ) {
				$rel  = str_starts_with( $url, '/' ) ? $url : (string) wp_parse_url( $url, PHP_URL_PATH );
				$page = '' !== (string) $rel ? get_page_by_path( trim( (string) $rel, '/' ) ) : null;
				if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
					return self::item( (string) get_the_title( $page ), (string) get_permalink( $page ) );
				}
				$label = trim( (string) ( $link['label'] ?? '' ) );
				if ( '' !== $label ) {
					return self::item( $label, str_starts_with( $url, '/' ) ? home_url( $url ) : $url );
				}
			}
		}
		$page = get_page_by_path( $path );
		if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
			return self::item( (string) get_the_title( $page ), (string) get_permalink( $page ) );
		}
		return null;
	}
}
