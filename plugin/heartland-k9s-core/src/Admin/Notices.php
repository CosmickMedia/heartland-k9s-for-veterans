<?php
/**
 * Admin notices (informational only).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Admin;

use HK9\Core\PostTypes\Registrar;
use HK9\Core\Taxonomies\PartnerType;

defined( 'ABSPATH' ) || exit;

final class Notices {

	public const THEME = 'heartland-k9s';

	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'theme_notice' ] );

		// Section-page editor guidance (block editor notice, always-visible section boxes, provider field toggles).
		if ( class_exists( EditorGuidance::class ) ) {
			EditorGuidance::register();
		}
	}

	/**
	 * Screens that belong to this plugin (CPT lists/editors, taxonomy, our pages).
	 */
	public static function is_plugin_screen( ?\WP_Screen $screen ): bool {
		if ( ! $screen ) {
			return false;
		}
		if ( in_array( $screen->post_type, Registrar::TYPES, true ) || PartnerType::TAXONOMY === $screen->taxonomy ) {
			return true;
		}
		return str_contains( $screen->id, '_page_hk9' ) || 'toplevel_page_hk9' === $screen->id;
	}

	/**
	 * Informational: the companion theme is not active.
	 */
	public static function theme_notice(): void {
		if ( ! current_user_can( 'switch_themes' ) && ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( self::THEME === get_stylesheet() || self::THEME === get_template() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$show_on = in_array( $screen->id, [ 'dashboard', 'plugins', 'themes' ], true ) || self::is_plugin_screen( $screen );
		if ( ! $show_on ) {
			return;
		}
		$message = __( 'Heartland K9s Core is active but the "Heartland Canines for Veterans" theme is not. Stories, teams, records and settings are stored normally; the site design and page sections render only with that theme.', 'heartland-k9s-core' );
		if ( current_user_can( 'switch_themes' ) ) {
			$message .= ' <a href="' . esc_url( admin_url( 'themes.php' ) ) . '">' . esc_html__( 'Open Appearance → Themes', 'heartland-k9s-core' ) . '</a>';
		}
		printf( '<div class="notice notice-info is-dismissible hk9-notice"><p>%s</p></div>', wp_kses( $message, [ 'a' => [ 'href' => [] ] ] ) );
	}
}
