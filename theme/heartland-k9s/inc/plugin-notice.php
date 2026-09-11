<?php
/**
 * Admin notice when the companion plugin (Heartland K9s Core) is not active.
 * The theme keeps working (block content, featured-image heroes, reference
 * defaults), but stories/teams/people/partners/campaigns/events/BarKode records,
 * page sections, settings, forms and the importer need the plugin.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the companion plugin is loaded.
 *
 * @return bool
 */
function hk9_plugin_active(): bool {
	return class_exists( 'HK9\\Core\\Plugin' );
}

/**
 * Print the notice.
 */
function hk9_plugin_missing_notice(): void {
	if ( hk9_plugin_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && in_array( $screen->id, [ 'update', 'update-core', 'plugin-install' ], true ) ) {
		return;
	}

	$installed = file_exists( WP_PLUGIN_DIR . '/heartland-k9s-core/heartland-k9s-core.php' );
	$plugins   = admin_url( 'plugins.php' );
	$install   = admin_url( 'plugin-install.php?tab=upload' );

	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Heartland Canines for Veterans theme:', 'heartland-k9s' ) . '</strong> ';

	if ( $installed ) {
		printf(
			/* translators: %s: plugins page URL */
			wp_kses_post( __( 'the companion plugin <em>Heartland K9s Core</em> is installed but not active. <a href="%s">Activate it on the Plugins screen</a> to enable page sections, stories, teams, people, partners, campaigns, events, the BarKode registry, forms and the content importer.', 'heartland-k9s' ) ),
			esc_url( $plugins )
		);
	} else {
		printf(
			/* translators: %s: plugin upload URL */
			wp_kses_post( __( 'the companion plugin <em>Heartland K9s Core</em> is missing. Upload <code>heartland-k9s-core.zip</code> via <a href="%s">Plugins → Add New → Upload</a> (or copy the <code>heartland-k9s-core</code> folder into <code>wp-content/plugins/</code>) and activate it. Until then pages render with block content and reference defaults only.', 'heartland-k9s' ) ),
			esc_url( $install )
		);
	}

	echo '</p></div>';
}
add_action( 'admin_notices', 'hk9_plugin_missing_notice' );
