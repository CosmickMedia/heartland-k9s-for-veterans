<?php
/**
 * Privacy controls: BarKode registry exposure and user-enumeration hardening.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Privacy;

defined( 'ABSPATH' ) || exit;

final class Registry {

	public const TYPE = 'hk9_barkode';

	public static function register(): void {
		// BarKode records.
		add_filter( 'wp_robots', [ self::class, 'robots' ], 20 );
		add_filter( 'wp_headers', [ self::class, 'headers' ], 20 );
		add_filter( 'wp_sitemaps_post_types', [ self::class, 'sitemap_post_types' ] );
		add_action( 'template_redirect', [ self::class, 'block_embed' ], 0 );
		add_action( 'template_redirect', [ self::class, 'strip_discovery_links' ], 1 );
		add_filter( 'oembed_response_data', [ self::class, 'block_oembed_response' ], 100, 2 ); // After core's prio-10 enrichers (they would coerce false back into an array).
		add_action( 'wp_head', [ self::class, 'meta_description' ], 1 );
		add_filter( 'hk9/seo/description', [ self::class, 'generic_description' ], 10, 2 );
		add_filter( 'get_post_metadata', [ self::class, 'hide_review_notes_from_frontend' ], 10, 4 );

		// User enumeration.
		add_filter( 'rest_pre_dispatch', [ self::class, 'block_rest_users' ], 10, 3 );
		add_action( 'pre_get_posts', [ self::class, 'block_author_query' ] );
		add_filter( 'wp_sitemaps_add_provider', [ self::class, 'drop_users_sitemap' ], 10, 2 );
		add_filter( 'oembed_response_data', [ self::class, 'scrub_oembed_author' ], 10, 2 );
	}

	private static function enumeration_blocked(): bool {
		return function_exists( 'hk9_option' ) ? (bool) hk9_option( 'advanced.disable_user_enumeration', true ) : true;
	}

	/**
	 * noindex,nofollow on registry singles.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( is_singular( self::TYPE ) ) {
			unset( $robots['max-image-preview'], $robots['index'], $robots['follow'] );
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * X-Robots-Tag header (main query already parsed when wp_headers fires).
	 *
	 * @param array<string, string> $headers Headers.
	 * @return array<string, string>
	 */
	public static function headers( array $headers ): array {
		if ( is_singular( self::TYPE ) ) {
			$headers['X-Robots-Tag'] = 'noindex, nofollow';
		}
		return $headers;
	}

	/**
	 * @param array<string, \WP_Post_Type> $post_types Sitemap post types.
	 * @return array<string, \WP_Post_Type>
	 */
	public static function sitemap_post_types( array $post_types ): array {
		unset( $post_types[ self::TYPE ], $post_types['hk9_person'], $post_types['hk9_partner'], $post_types['hk9_submission'] );
		return $post_types;
	}

	/**
	 * The core embed template (?embed=true, or /embed/ on cores that ignore
	 * `embeddable`) must never render a registry record: answer 404 instead.
	 */
	public static function block_embed(): void {
		if ( ! is_embed() || ! is_singular( self::TYPE ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * No oEmbed payload for registry records (belt and braces for cores < 6.8
	 * where `embeddable => false` is not honoured by the oEmbed REST endpoint).
	 *
	 * @param array|false $data oEmbed data (false when another filter refused).
	 * @param \WP_Post    $post Post.
	 * @return array|false
	 */
	public static function block_oembed_response( mixed $data, \WP_Post $post ): mixed {
		// Runs late on purpose: core's get_oembed_response_data_rich() (priority 10) would
		// turn an early false back into an array.

		if ( self::TYPE === $post->post_type ) {
			return false;
		}
		return $data;
	}

	/**
	 * Remove oEmbed discovery, shortlink, REST link and feed link output for registry singles.
	 */
	public static function strip_discovery_links(): void {
		if ( ! is_singular( self::TYPE ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'get_shortlink', '__return_empty_string' );
	}

	/**
	 * Generic description for registry records (no dog/handler details).
	 */
	public static function generic_description( string $description, int $post_id ): string {
		if ( get_post_type( $post_id ) === self::TYPE ) {
			return __( 'Heartland Canines for Veterans BarKode registry record. Scan the QR code on the K9\'s patch to reach this page.', 'heartland-k9s-core' );
		}
		return $description;
	}

	/**
	 * Output the generic meta description on registry singles (when SEO meta output is on and no SEO plugin runs).
	 */
	public static function meta_description(): void {
		if ( ! is_singular( self::TYPE ) ) {
			return;
		}
		if ( function_exists( 'hk9_option' ) && ! hk9_option( 'advanced.output_seo_meta', true ) ) {
			return;
		}
		if ( self::seo_plugin_active() || function_exists( 'hk9_print_seo_meta' ) ) {
			return; // An SEO plugin or the companion theme emits the description.
		}
		$post_id = (int) get_queried_object_id();
		/**
		 * Filters the meta description for a post.
		 *
		 * @param string $description Description.
		 * @param int    $post_id     Post ID.
		 */
		$description = (string) apply_filters( 'hk9/seo/description', '', $post_id );
		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}
	}

	public static function seo_plugin_active(): bool {
		$active = defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SLIM_SEO_VER' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\\Load' );
		/**
		 * Filters whether an SEO plugin is handling meta output.
		 *
		 * @param bool $active Detected.
		 */
		return (bool) apply_filters( 'hk9/seo/plugin_active', $active );
	}

	/**
	 * hk9_review_notes is admin-only: never readable through get_post_meta() on the frontend.
	 *
	 * @param mixed  $value     Short-circuit value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Single.
	 * @return mixed
	 */
	public static function hide_review_notes_from_frontend( mixed $value, int $object_id, string $meta_key, bool $single ): mixed {
		if ( 'hk9_review_notes' !== $meta_key && '_hk9_review_notes' !== $meta_key ) {
			return $value;
		}
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $value;
		}
		if ( current_user_can( 'edit_post', $object_id ) ) {
			return $value;
		}
		return $single ? '' : [];
	}

	/**
	 * Anonymous REST users listing/lookup is refused when enumeration is blocked.
	 *
	 * @param mixed            $result  Pre-dispatch result.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function block_rest_users( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
		if ( null !== $result || ! self::enumeration_blocked() || is_user_logged_in() ) {
			return $result;
		}
		$route = $request->get_route();
		if ( preg_match( '#^/wp/v2/users(?:/|$)#', $route ) ) {
			return new \WP_Error(
				'rest_user_cannot_view',
				__( 'Sorry, you are not allowed to list users.', 'heartland-k9s-core' ),
				[ 'status' => 401 ]
			);
		}
		return $result;
	}

	/**
	 * ?author=N (and author archives requested by ID) are a 404 for visitors.
	 */
	public static function block_author_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! self::enumeration_blocked() || is_user_logged_in() ) {
			return;
		}
		$by_id = isset( $_GET['author'] ) && is_numeric( wp_unslash( $_GET['author'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only guard.
		if ( $by_id || ( $query->is_author() && '' === (string) $query->get( 'author_name' ) && '' !== (string) $query->get( 'author' ) ) ) {
			$query->set( 'author', '' );
			$query->set( 'author_name', '' );
			$query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Drop the users sitemap provider.
	 *
	 * @param mixed  $provider Provider (or false when another plugin removed it).
	 * @param string $name     Name.
	 * @return mixed
	 */
	public static function drop_users_sitemap( mixed $provider, string $name ): mixed {
		if ( 'users' === $name && self::enumeration_blocked() ) {
			return false;
		}
		return $provider;
	}

	/**
	 * oEmbed responses carry author_name/author_url; strip them when enumeration is blocked.
	 *
	 * @param array|false $data oEmbed data (false when an earlier filter refused the embed).
	 * @param \WP_Post    $post Post.
	 * @return array|false
	 */
	public static function scrub_oembed_author( mixed $data, \WP_Post $post ): mixed {
		if ( is_array( $data ) && self::enumeration_blocked() ) {
			unset( $data['author_name'], $data['author_url'] );
		}
		return $data;
	}
}
