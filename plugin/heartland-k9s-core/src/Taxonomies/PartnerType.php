<?php
/**
 * hk9_partner_type taxonomy (Back the Pack partner / Campaign sponsor / K9 provider / Community partner).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class PartnerType {

	public const TAXONOMY = 'hk9_partner_type';
	public const VERSION  = '1';

	/**
	 * Seeded terms: slug => name (translated at seed time).
	 *
	 * @return array<string, string>
	 */
	public static function seed_terms(): array {
		return [
			'back-the-pack'    => __( 'Back the Pack Partner', 'heartland-k9s-core' ),
			'campaign-sponsor' => __( 'Campaign Sponsor', 'heartland-k9s-core' ),
			'provider'         => __( 'K9 Provider', 'heartland-k9s-core' ),
			'community'        => __( 'Community Partner', 'heartland-k9s-core' ),
		];
	}

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_taxonomy' ], 6 );
		add_action( 'admin_init', [ self::class, 'maybe_seed' ] );
	}

	public static function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			[ 'hk9_partner' ],
			[
				'labels'            => [
					'name'          => _x( 'Partner Types', 'taxonomy general name', 'heartland-k9s-core' ),
					'singular_name' => _x( 'Partner Type', 'taxonomy singular name', 'heartland-k9s-core' ),
					'menu_name'     => __( 'Partner Types', 'heartland-k9s-core' ),
					'all_items'     => __( 'All Partner Types', 'heartland-k9s-core' ),
					'edit_item'     => __( 'Edit Partner Type', 'heartland-k9s-core' ),
					'update_item'   => __( 'Update Partner Type', 'heartland-k9s-core' ),
					'add_new_item'  => __( 'Add New Partner Type', 'heartland-k9s-core' ),
					'new_item_name' => __( 'New Partner Type Name', 'heartland-k9s-core' ),
					'search_items'  => __( 'Search Partner Types', 'heartland-k9s-core' ),
					'not_found'     => __( 'No partner types found.', 'heartland-k9s-core' ),
					'back_to_items' => __( '← Go to Partner Types', 'heartland-k9s-core' ),
				],
				'description'       => __( 'How a partner relates to Heartland (used on the Back the Pack page and campaign sponsor lists).', 'heartland-k9s-core' ),
				'hierarchical'      => false,
				'public'            => false,
				'publicly_queryable' => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_in_nav_menus' => false,
				'show_in_rest'      => true,
				'show_admin_column' => false, // Custom "Type" column is rendered by ListTables.
				'show_tagcloud'     => false,
				'show_in_quick_edit' => true,
				'query_var'         => false,
				'rewrite'           => false,
				'meta_box_cb'       => 'post_categories_meta_box', // Checkbox UI keeps editors on the seeded set.
				'capabilities'      => [
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_hk9_partners',
				],
			]
		);
	}

	/** Seed on activation (and once after an update via the version gate). */
	public static function activate(): void {
		self::register_taxonomy();
		self::seed();
		update_option( 'hk9_terms_version', self::VERSION, true );
	}

	public static function maybe_seed(): void {
		if ( (string) get_option( 'hk9_terms_version', '0' ) === self::VERSION ) {
			return;
		}
		self::seed();
		update_option( 'hk9_terms_version', self::VERSION, true );
	}

	public static function seed(): void {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}
		foreach ( self::seed_terms() as $slug => $name ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term( $name, self::TAXONOMY, [ 'slug' => $slug ] );
			}
		}
	}
}
