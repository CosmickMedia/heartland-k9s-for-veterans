<?php
/**
 * Post types, rewrite extras and URL-ownership guards.
 *
 * URL ownership: Pages own listing URLs (/stories/, /events/ ...); CPT singles live
 * under the same slug (/stories/<slug>/) with has_archive=false and with_front=false.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\PostTypes;

use HK9\Core\Taxonomies\PartnerType;

defined( 'ABSPATH' ) || exit;

final class Registrar {

	/** Bump when rewrite rules change to trigger a one-time flush after an update. */
	public const REWRITE_VERSION = '1';

	/** Page slugs that own listing URLs; each gets a /page/N/ rule and cannot be a parent. */
	public const LISTING_SLUGS = [ 'stories', 'events', 'campaigns', 'barkode', 'meet-the-team', 'hk9-current-teams-in-training', 'back-the-pack', 'photos', 'news' ];

	/** Post types in admin-menu order. */
	public const TYPES = [ 'hk9_story', 'hk9_team', 'hk9_person', 'hk9_partner', 'hk9_campaign', 'hk9_event', 'hk9_barkode', 'hk9_submission' ];

	/** Types with a public single URL. */
	public const VIEWABLE_TYPES = [ 'hk9_story', 'hk9_team', 'hk9_campaign', 'hk9_event', 'hk9_barkode' ];

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_post_types' ], 5 );
		add_action( 'init', [ self::class, 'register_rewrites' ], 7 );
		add_action( 'init', [ self::class, 'maybe_flush' ], 99 );
		add_filter( 'hk9_barkode_rewrite_rules', [ self::class, 'strip_barkode_rules' ] );
		add_action( 'pre_get_posts', [ self::class, 'guard_barkode_query' ], 1 );
		add_filter( 'page_attributes_dropdown_pages_args', [ self::class, 'exclude_listing_parents' ] );
		add_filter( 'quick_edit_dropdown_pages_args', [ self::class, 'exclude_listing_parents' ] );
		add_filter( 'wp_insert_post_data', [ self::class, 'reject_listing_parent' ], 10, 2 );
		add_action( 'save_post_page', [ self::class, 'flush_on_listing_page_change' ], 10, 2 );

		if ( class_exists( PartnerType::class ) ) {
			PartnerType::register();
		}
	}

	/**
	 * Activation: register everything directly (init has already run), so the
	 * flush in Plugin::activate() sees our rules.
	 */
	public static function activate(): void {
		// The plugin was not loaded at plugins_loaded during activation, so the
		// rule filter must be attached here or the flush would keep embed/attachment rules.
		if ( ! has_filter( 'hk9_barkode_rewrite_rules', [ self::class, 'strip_barkode_rules' ] ) ) {
			add_filter( 'hk9_barkode_rewrite_rules', [ self::class, 'strip_barkode_rules' ] );
		}
		self::register_post_types();
		if ( class_exists( PartnerType::class ) ) {
			PartnerType::activate();
		}
		self::register_rewrites();
		update_option( 'hk9_rewrite_version', self::REWRITE_VERSION, true );
	}

	/**
	 * One-time flush after an in-place update that changed rewrite rules.
	 */
	public static function maybe_flush(): void {
		if ( (string) get_option( 'hk9_rewrite_version', '0' ) === self::REWRITE_VERSION ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'hk9_rewrite_version', self::REWRITE_VERSION, true );
	}

	/**
	 * Labels for a type.
	 *
	 * @return array<string, string>
	 */
	private static function labels( string $singular, string $plural, string $singular_lc, string $plural_lc ): array {
		return [
			'name'                     => $plural,
			'singular_name'            => $singular,
			'menu_name'                => $plural,
			'name_admin_bar'           => $singular,
			'all_items'                => $plural,
			/* translators: %s: singular label */
			'add_new'                  => __( 'Add New', 'heartland-k9s-core' ),
			/* translators: %s: singular label */
			'add_new_item'             => sprintf( __( 'Add New %s', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label */
			'edit_item'                => sprintf( __( 'Edit %s', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label */
			'new_item'                 => sprintf( __( 'New %s', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label */
			'view_item'                => sprintf( __( 'View %s', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: plural label */
			'view_items'               => sprintf( __( 'View %s', 'heartland-k9s-core' ), $plural ),
			/* translators: %s: plural label */
			'search_items'             => sprintf( __( 'Search %s', 'heartland-k9s-core' ), $plural ),
			/* translators: %s: plural label (lower case) */
			'not_found'                => sprintf( __( 'No %s found.', 'heartland-k9s-core' ), $plural_lc ),
			/* translators: %s: plural label (lower case) */
			'not_found_in_trash'       => sprintf( __( 'No %s found in Trash.', 'heartland-k9s-core' ), $plural_lc ),
			/* translators: %s: singular label (lower case) */
			'archives'                 => sprintf( __( '%s archives', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label (lower case) */
			'insert_into_item'         => sprintf( __( 'Insert into %s', 'heartland-k9s-core' ), $singular_lc ),
			/* translators: %s: singular label (lower case) */
			'uploaded_to_this_item'    => sprintf( __( 'Uploaded to this %s', 'heartland-k9s-core' ), $singular_lc ),
			'featured_image'           => __( 'Featured image', 'heartland-k9s-core' ),
			'set_featured_image'       => __( 'Set featured image', 'heartland-k9s-core' ),
			'remove_featured_image'    => __( 'Remove featured image', 'heartland-k9s-core' ),
			'use_featured_image'       => __( 'Use as featured image', 'heartland-k9s-core' ),
			/* translators: %s: plural label (lower case) */
			'filter_items_list'        => sprintf( __( 'Filter %s list', 'heartland-k9s-core' ), $plural_lc ),
			/* translators: %s: plural label */
			'items_list_navigation'    => sprintf( __( '%s list navigation', 'heartland-k9s-core' ), $plural ),
			/* translators: %s: plural label */
			'items_list'               => sprintf( __( '%s list', 'heartland-k9s-core' ), $plural ),
			/* translators: %s: singular label */
			'item_published'           => sprintf( __( '%s published.', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label */
			'item_updated'             => sprintf( __( '%s updated.', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label */
			'item_scheduled'           => sprintf( __( '%s scheduled.', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label */
			'item_reverted_to_draft'   => sprintf( __( '%s reverted to draft.', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label */
			'item_link'                => sprintf( __( '%s link', 'heartland-k9s-core' ), $singular ),
			/* translators: %s: singular label (lower case) */
			'item_link_description'    => sprintf( __( 'A link to a %s.', 'heartland-k9s-core' ), $singular_lc ),
		];
	}

	/**
	 * Argument sets for every type.
	 *
	 * @return array<string, array>
	 */
	public static function definitions(): array {
		$common = [
			'show_ui'      => true,
			'show_in_menu' => 'hk9',
			'map_meta_cap' => true,
			'has_archive'  => false,
			'query_var'    => true,
			'can_export'   => true,
		];
		$rewrite = static fn( string $slug ): array => [
			'slug'       => $slug,
			'with_front' => false,
			'feeds'      => false,
			'pages'      => true,
			'ep_mask'    => EP_PERMALINK,
		];
		$caps = static fn( string $type ): array => Capabilities::CAP_TYPES[ $type ];

		$types = [
			'hk9_story'      => [
				'labels'              => self::labels( __( 'Story', 'heartland-k9s-core' ), __( 'Stories', 'heartland-k9s-core' ), __( 'story', 'heartland-k9s-core' ), __( 'stories', 'heartland-k9s-core' ) ),
				'description'         => __( 'Veteran and canine success stories.', 'heartland-k9s-core' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'rewrite'             => $rewrite( 'stories' ),
				'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
				'capability_type'     => $caps( 'hk9_story' ),
				'menu_position'       => 1,
			],
			'hk9_team'       => [
				'labels'              => self::labels( __( 'Team', 'heartland-k9s-core' ), __( 'Teams', 'heartland-k9s-core' ), __( 'team', 'heartland-k9s-core' ), __( 'teams', 'heartland-k9s-core' ) ),
				'description'         => __( 'Veteran + canine teams in training or graduated.', 'heartland-k9s-core' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'rewrite'             => $rewrite( 'teams' ),
				'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
				'capability_type'     => $caps( 'hk9_team' ),
				'menu_position'       => 2,
			],
			'hk9_person'     => [
				'labels'              => self::labels( __( 'Person', 'heartland-k9s-core' ), __( 'People', 'heartland-k9s-core' ), __( 'person', 'heartland-k9s-core' ), __( 'people', 'heartland-k9s-core' ) ),
				'description'         => __( 'Board members, staff and trainers shown on Meet the Team.', 'heartland-k9s-core' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
				'capability_type'     => $caps( 'hk9_person' ),
				'menu_position'       => 3,
			],
			'hk9_partner'    => [
				'labels'              => self::labels( __( 'Partner', 'heartland-k9s-core' ), __( 'Partners', 'heartland-k9s-core' ), __( 'partner', 'heartland-k9s-core' ), __( 'partners', 'heartland-k9s-core' ) ),
				'description'         => __( 'Sponsors and partner organisations (logo + link).', 'heartland-k9s-core' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ],
				'capability_type'     => $caps( 'hk9_partner' ),
				'taxonomies'          => [ PartnerType::TAXONOMY ],
				'menu_position'       => 4,
			],
			'hk9_campaign'   => [
				'labels'              => self::labels( __( 'Campaign', 'heartland-k9s-core' ), __( 'Campaigns', 'heartland-k9s-core' ), __( 'campaign', 'heartland-k9s-core' ), __( 'campaigns', 'heartland-k9s-core' ) ),
				'description'         => __( 'Fundraising and awareness campaigns.', 'heartland-k9s-core' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'rewrite'             => $rewrite( 'campaigns' ),
				'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields' ],
				'capability_type'     => $caps( 'hk9_campaign' ),
				'menu_position'       => 5,
			],
			'hk9_event'      => [
				'labels'              => self::labels( __( 'Event', 'heartland-k9s-core' ), __( 'Events', 'heartland-k9s-core' ), __( 'event', 'heartland-k9s-core' ), __( 'events', 'heartland-k9s-core' ) ),
				'description'         => __( 'Dated events with venue and registration details.', 'heartland-k9s-core' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_in_rest'        => true,
				'rewrite'             => $rewrite( 'events' ),
				'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
				'capability_type'     => $caps( 'hk9_event' ),
				'menu_position'       => 6,
			],
			'hk9_barkode'    => [
				'labels'              => self::labels( __( 'BarKode Record', 'heartland-k9s-core' ), __( 'BarKode Records', 'heartland-k9s-core' ), __( 'BarKode record', 'heartland-k9s-core' ), __( 'BarKode records', 'heartland-k9s-core' ) ),
				'description'         => __( 'Service/therapy K9 registry records reached from printed QR codes. Not indexed, not searchable, not in the REST API.', 'heartland-k9s-core' ),
				'public'              => false,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'embeddable'          => false,
				'rewrite'             => [
					'slug'       => 'barkode',
					'with_front' => false,
					'feeds'      => false,
					'pages'      => false,
					'ep_mask'    => EP_NONE,
				],
				/**
				 * Filters the supports list for BarKode records (editor is off by default; fields are used).
				 *
				 * @param string[] $supports Supports.
				 */
				'supports'            => apply_filters( 'hk9/barkode/supports', [ 'title', 'thumbnail', 'revisions' ] ),
				'capability_type'     => $caps( 'hk9_barkode' ),
				'menu_position'       => 7,
			],
			'hk9_submission' => [
				'labels'              => self::labels( __( 'Form Submission', 'heartland-k9s-core' ), __( 'Form Submissions', 'heartland-k9s-core' ), __( 'submission', 'heartland-k9s-core' ), __( 'submissions', 'heartland-k9s-core' ) ),
				'description'         => __( 'Copies of contact and application-inquiry form submissions.', 'heartland-k9s-core' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => [ 'title' ],
				'capability_type'     => $caps( 'hk9_submission' ),
				'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
				'menu_position'       => 8,
			],
		];

		foreach ( $types as $type => $args ) {
			$types[ $type ] = array_merge( $common, $args ); // Type-specific keys override the common set.
		}

		/**
		 * Filters the post type definitions before registration.
		 *
		 * @param array $types type => register_post_type() args.
		 */
		return (array) apply_filters( 'hk9/post_types/definitions', $types );
	}

	public static function register_post_types(): void {
		foreach ( self::definitions() as $type => $args ) {
			register_post_type( $type, $args );
		}
	}

	/**
	 * Listing pagination rules (/stories/page/2/) beat CPT single rules because
	 * 'top' extra rules precede permastruct rules.
	 */
	public static function register_rewrites(): void {
		foreach ( self::listing_slugs() as $slug ) {
			$safe = preg_quote( $slug, '#' );
			add_rewrite_rule( $safe . '/page/([0-9]+)/?$', 'index.php?pagename=' . $slug . '&paged=$matches[1]', 'top' );
		}
	}

	/**
	 * @return string[]
	 */
	public static function listing_slugs(): array {
		/**
		 * Filters the page slugs that own listing URLs.
		 *
		 * @param string[] $slugs Slugs.
		 */
		return array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) apply_filters( 'hk9/listing_slugs', self::LISTING_SLUGS ) ) ) ) );
	}

	/**
	 * Keep only the plain single rule for BarKode records (no embed/attachment/feed/trackback/comment pages).
	 *
	 * @param array<string, string> $rules regex => query.
	 * @return array<string, string>
	 */
	public static function strip_barkode_rules( array $rules ): array {
		foreach ( $rules as $regex => $query ) {
			if ( ! str_contains( $query, 'hk9_barkode=' )
				|| str_contains( $query, 'embed=true' )
				|| str_contains( $query, 'attachment=' )
				|| str_contains( $query, 'tb=1' )
				|| str_contains( $query, 'feed=' )
				|| str_contains( $query, 'cpage=' )
				|| str_contains( $regex, 'embed' )
				|| str_contains( $regex, 'attachment' )
			) {
				unset( $rules[ $regex ] );
			}
		}
		return $rules;
	}

	/**
	 * Any non-singular frontend main query for BarKode records (?post_type=, feeds, search) is a 404.
	 */
	public static function guard_barkode_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || $query->is_singular() ) {
			return;
		}
		$post_type = $query->get( 'post_type' );
		$types     = is_array( $post_type ) ? $post_type : ( '' === $post_type || null === $post_type ? [] : [ (string) $post_type ] );
		if ( ! in_array( 'hk9_barkode', $types, true ) ) {
			return;
		}
		$query->set( 'post_type', 'post' );
		$query->set( 'post__in', [ 0 ] );
		$query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * IDs of the pages that own listing URLs (cached per request).
	 *
	 * @return int[]
	 */
	public static function listing_page_ids(): array {
		static $ids = null;
		if ( null !== $ids ) {
			return $ids;
		}
		$ids = [];
		foreach ( self::listing_slugs() as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof \WP_Post ) {
				$ids[] = (int) $page->ID;
			}
		}
		return $ids;
	}

	/**
	 * Listing pages cannot be chosen as a parent (children would be unreachable).
	 *
	 * @param array $args wp_dropdown_pages() args.
	 * @return array
	 */
	public static function exclude_listing_parents( array $args ): array {
		$ids = self::listing_page_ids();
		if ( $ids ) {
			$exclude         = isset( $args['exclude'] ) ? wp_parse_id_list( $args['exclude'] ) : [];
			$args['exclude'] = array_values( array_unique( array_merge( $exclude, $ids ) ) );
		}
		return $args;
	}

	/**
	 * Server-side guard for the same rule (block editor, REST, quick edit).
	 *
	 * @param array $data    Slashed post data.
	 * @param array $postarr Raw post array.
	 * @return array
	 */
	public static function reject_listing_parent( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== 'page' || empty( $data['post_parent'] ) ) {
			return $data;
		}
		if ( in_array( (int) $data['post_parent'], self::listing_page_ids(), true ) ) {
			$data['post_parent'] = 0;
		}
		return $data;
	}

	/**
	 * A listing page being created/renamed changes which rules are live; a soft flush keeps
	 * /slug/page/N/ working (rules are static, but the flush is cheap and only on those pages).
	 */
	public static function flush_on_listing_page_change( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( in_array( $post->post_name, self::listing_slugs(), true ) ) {
			flush_rewrite_rules( false );
		}
	}
}
