<?php
/**
 * Top-level "Heartland" admin menu and submenu ordering.
 *
 * Order: Overview, Stories, Teams, People, Partners, Campaigns, Events, BarKode Records,
 * Submissions, Partner Types, Settings, Redirects, Setup & Import.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Admin;

use HK9\Core\PostTypes\Registrar;
use HK9\Core\Taxonomies\PartnerType;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG        = 'hk9';
	public const IMPORT_SLUG = 'hk9-import';

	/** @var string Hook suffix of the overview screen. */
	private static string $hook = '';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ], 9 );
		add_action( 'admin_menu', [ self::class, 'add_late_items' ], 30 );
		add_action( 'admin_menu', [ self::class, 'sort_submenu' ], 999 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'parent_file', [ self::class, 'highlight_parent' ] );
	}

	public static function add_menu(): void {
		$hook = add_menu_page(
			__( 'Heartland', 'heartland-k9s-core' ),
			__( 'Heartland', 'heartland-k9s-core' ),
			'edit_posts',
			self::SLUG,
			[ self::class, 'render_overview' ],
			'dashicons-pets',
			25
		);
		self::$hook = is_string( $hook ) ? $hook : '';
		// Rename the auto-created first entry.
		add_submenu_page( self::SLUG, __( 'Heartland Overview', 'heartland-k9s-core' ), __( 'Overview', 'heartland-k9s-core' ), 'edit_posts', self::SLUG, [ self::class, 'render_overview' ] );
	}

	/**
	 * Taxonomy screen (core only auto-adds it for top-level CPT menus) and the
	 * Setup & Import placeholder when the importer module is absent.
	 */
	public static function add_late_items(): void {
		if ( taxonomy_exists( PartnerType::TAXONOMY ) ) {
			$tax = get_taxonomy( PartnerType::TAXONOMY );
			if ( $tax ) {
				add_submenu_page(
					self::SLUG,
					$tax->labels->name,
					$tax->labels->menu_name,
					$tax->cap->manage_terms,
					'edit-tags.php?taxonomy=' . PartnerType::TAXONOMY . '&post_type=hk9_partner'
				);
			}
		}
		if ( ! class_exists( 'HK9\\Core\\Import\\Admin' ) ) {
			add_submenu_page(
				self::SLUG,
				__( 'Setup & Import', 'heartland-k9s-core' ),
				__( 'Setup & Import', 'heartland-k9s-core' ),
				'hk9_run_import',
				self::IMPORT_SLUG,
				[ self::class, 'render_import_placeholder' ]
			);
		}
	}

	/**
	 * Force the documented order regardless of the priority other modules used.
	 */
	public static function sort_submenu(): void {
		global $submenu;
		if ( empty( $submenu[ self::SLUG ] ) || ! is_array( $submenu[ self::SLUG ] ) ) {
			return;
		}
		$order = [ self::SLUG ];
		foreach ( Registrar::TYPES as $type ) {
			$order[] = 'edit.php?post_type=' . $type;
		}
		$order[] = 'edit-tags.php?taxonomy=' . PartnerType::TAXONOMY . '&post_type=hk9_partner';
		$order[] = 'hk9-settings';
		$order[] = 'hk9-redirects';
		$order[] = self::IMPORT_SLUG;

		$rank = static function ( array $item ) use ( $order ): int {
			$slug = html_entity_decode( (string) $item[2] );
			$pos  = array_search( $slug, $order, true );
			return false === $pos ? 500 : $pos;
		};
		$items = array_values( $submenu[ self::SLUG ] );
		usort(
			$items,
			static function ( array $a, array $b ) use ( $rank ): int {
				return $rank( $a ) <=> $rank( $b );
			}
		);
		$submenu[ self::SLUG ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional submenu reorder.
	}

	/**
	 * Keep "Heartland" highlighted on CPT/taxonomy screens that live under it.
	 */
	public static function highlight_parent( string $parent_file ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return $parent_file;
		}
		if ( in_array( $screen->post_type, Registrar::TYPES, true ) || PartnerType::TAXONOMY === $screen->taxonomy ) {
			return self::SLUG;
		}
		return $parent_file;
	}

	public static function enqueue( string $hook_suffix ): void {
		if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
			return;
		}
		$css = HK9_CORE_DIR . 'assets/css/admin.css';
		wp_enqueue_style( 'hk9-admin', HK9_CORE_URL . 'assets/css/admin.css', [], (string) ( file_exists( $css ) ? filemtime( $css ) : HK9_CORE_VERSION ) );
	}

	/**
	 * Overview: counts and shortcuts.
	 */
	public static function render_overview(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'heartland-k9s-core' ), 403 );
		}
		$theme_ok = 'heartland-k9s' === get_stylesheet() || 'heartland-k9s' === get_template();
		?>
		<div class="wrap hk9-overview">
			<h1><?php esc_html_e( 'Heartland', 'heartland-k9s-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Content models, settings and tools for the Heartland Canines for Veterans website.', 'heartland-k9s-core' ); ?></p>
			<?php if ( ! $theme_ok ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'The "Heartland Canines for Veterans" theme is not active. Content is still stored, but the site design and page sections only render with that theme.', 'heartland-k9s-core' ); ?></p></div>
			<?php endif; ?>
			<div class="hk9-overview__grid">
				<?php
				foreach ( Registrar::TYPES as $type ) {
					$obj = get_post_type_object( $type );
					if ( ! $obj || ! current_user_can( $obj->cap->edit_posts ) ) {
						continue;
					}
					$counts    = wp_count_posts( $type );
					$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
					$drafts    = isset( $counts->draft ) ? (int) $counts->draft : 0;
					?>
					<div class="hk9-overview__card">
						<h2><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $type ) ); ?>"><?php echo esc_html( $obj->labels->name ); ?></a></h2>
						<div class="hk9-overview__count"><?php echo esc_html( number_format_i18n( $published ) ); ?></div>
						<p>
							<?php
							printf(
								/* translators: 1: published count label, 2: draft count */
								esc_html__( 'published · %s drafts', 'heartland-k9s-core' ),
								esc_html( number_format_i18n( $drafts ) )
							);
							?>
						</p>
						<?php if ( current_user_can( $obj->cap->create_posts ) && 'hk9_submission' !== $type ) : ?>
							<p><a class="button button-small" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $type ) ); ?>"><?php echo esc_html( $obj->labels->add_new_item ); ?></a></p>
						<?php endif; ?>
					</div>
					<?php
				}
				?>
				<?php if ( current_user_can( 'hk9_manage_settings' ) ) : ?>
					<div class="hk9-overview__card">
						<h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=hk9-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'heartland-k9s-core' ); ?></a></h2>
						<p><?php esc_html_e( 'Branding, colors, contact details, destinations, header, footer, blog, forms, analytics.', 'heartland-k9s-core' ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( current_user_can( 'hk9_manage_redirects' ) ) : ?>
					<div class="hk9-overview__card">
						<h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=hk9-redirects' ) ); ?>"><?php esc_html_e( 'Redirects', 'heartland-k9s-core' ); ?></a></h2>
						<p><?php esc_html_e( 'Legacy URL redirects, including printed BarKode QR paths.', 'heartland-k9s-core' ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( current_user_can( 'hk9_run_import' ) ) : ?>
					<div class="hk9-overview__card">
						<h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::IMPORT_SLUG ) ); ?>"><?php esc_html_e( 'Setup & Import', 'heartland-k9s-core' ); ?></a></h2>
						<p><?php esc_html_e( 'Import the content payload, check status, roll back.', 'heartland-k9s-core' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Placeholder shown until the importer module ships.
	 */
	public static function render_import_placeholder(): void {
		if ( ! current_user_can( 'hk9_run_import' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'heartland-k9s-core' ), 403 );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Setup & Import', 'heartland-k9s-core' ); ?></h1>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'The content importer is not installed in this build of the plugin. Content can still be created manually under the Heartland menu.', 'heartland-k9s-core' ); ?></p></div>
		</div>
		<?php
	}
}
