<?php
/**
 * Core XML sitemap (only while this plugin owns the meta output, Detector::owns_meta() — an SEO plugin brings its own):
 *  - the public content types are listed (pages, posts, stories, teams,
 *    campaigns, events; the registry, people and partners are removed by
 *    Privacy\Registry);
 *  - posts marked noindex (Search & social box) and local fixtures are left out;
 *  - every entry carries `lastmod`.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

defined( 'ABSPATH' ) || exit;

final class Sitemap {

	public const CONTENT_TYPES = [ 'page', 'post', 'hk9_story', 'hk9_team', 'hk9_campaign', 'hk9_event' ];

	public static function register(): void {
		add_filter( 'wp_sitemaps_enabled', [ self::class, 'enabled' ], 20 );
		add_filter( 'wp_sitemaps_post_types', [ self::class, 'post_types' ], 30 );
		add_filter( 'wp_sitemaps_posts_query_args', [ self::class, 'query_args' ], 10, 2 );
		add_filter( 'wp_sitemaps_posts_entry', [ self::class, 'entry' ], 10, 3 );
		add_filter( 'wp_sitemaps_posts_show_on_front_entry', [ self::class, 'front_entry' ] );
		add_filter( 'wp_sitemaps_taxonomies_entry', [ self::class, 'term_entry' ], 10, 3 );
	}

	/** Core sitemaps stay on in full mode; SEO plugins decide for themselves otherwise. */
	public static function enabled( $enabled ): bool {
		if ( Detector::owns_meta() ) {
			return true;
		}
		return (bool) $enabled;
	}

	/**
	 * Keep the editorial types (core lists every public type; this guards
	 * against another filter dropping them) — never adds private types.
	 *
	 * @param array<string, \WP_Post_Type> $post_types Types.
	 * @return array<string, \WP_Post_Type>
	 */
	public static function post_types( array $post_types ): array {
		if ( ! Detector::owns_meta() ) {
			return $post_types;
		}
		foreach ( self::CONTENT_TYPES as $type ) {
			if ( isset( $post_types[ $type ] ) ) {
				continue;
			}
			$object = get_post_type_object( $type );
			if ( $object instanceof \WP_Post_Type && $object->public && is_post_type_viewable( $object ) ) {
				$post_types[ $type ] = $object;
			}
		}
		unset( $post_types['hk9_barkode'], $post_types['hk9_person'], $post_types['hk9_partner'], $post_types['hk9_submission'] );
		return $post_types;
	}

	/**
	 * Exclude noindexed posts and local fixtures.
	 *
	 * @param array  $args      WP_Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function query_args( array $args, string $post_type ): array {
		if ( ! Detector::owns_meta() ) {
			return $args;
		}
		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key'     => Fields::meta_key( 'noindex' ),
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => Fields::meta_key( 'noindex' ),
				'value'   => [ '1', 'true', 'yes', 'on' ],
				'compare' => 'NOT IN',
			],
		];
		$meta_query[] = [
			'key'     => '_hk9_local_fixture',
			'compare' => 'NOT EXISTS',
		];
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return $args;
	}

	/**
	 * lastmod for post entries (ISO 8601, UTC).
	 *
	 * @param array    $entry     Sitemap entry.
	 * @param \WP_Post $post      Post.
	 * @param string   $post_type Post type.
	 * @return array
	 */
	public static function entry( array $entry, $post, string $post_type ): array {
		if ( ! Detector::owns_meta() || ! $post instanceof \WP_Post ) {
			return $entry;
		}
		$modified = get_post_modified_time( 'c', true, $post );
		if ( is_string( $modified ) && '' !== $modified ) {
			$entry['lastmod'] = $modified;
		}
		return $entry;
	}

	/**
	 * lastmod for the front-page entry (static front page).
	 *
	 * @param array $entry Entry.
	 * @return array
	 */
	public static function front_entry( array $entry ): array {
		if ( ! Detector::owns_meta() ) {
			return $entry;
		}
		$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$post  = $front > 0 ? get_post( $front ) : null;
		if ( $post instanceof \WP_Post ) {
			$modified = get_post_modified_time( 'c', true, $post );
			if ( is_string( $modified ) && '' !== $modified ) {
				$entry['lastmod'] = $modified;
			}
		}
		return $entry;
	}

	/**
	 * lastmod for term entries = the newest post in the term.
	 *
	 * @param array        $entry    Entry.
	 * @param int|\WP_Term $term     Term (id in older cores).
	 * @param string       $taxonomy Taxonomy.
	 * @return array
	 */
	public static function term_entry( array $entry, $term, string $taxonomy ): array {
		if ( ! Detector::owns_meta() ) {
			return $entry;
		}
		$term_id = $term instanceof \WP_Term ? (int) $term->term_id : (int) $term;
		if ( $term_id <= 0 ) {
			return $entry;
		}
		$latest = get_posts(
			[
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'numberposts'            => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'tax_query'              => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => $taxonomy,
						'terms'    => [ $term_id ],
					],
				],
			]
		);
		if ( $latest ) {
			$modified = get_post_modified_time( 'c', true, (int) $latest[0] );
			if ( is_string( $modified ) && '' !== $modified ) {
				$entry['lastmod'] = $modified;
			}
		}
		return $entry;
	}
}
