<?php
/**
 * Uninstall handler for Heartland K9s Core.
 *
 * Runs with none of the plugin loaded (no autoloader, no constants). By default
 * nothing is deleted: stories, teams, people, partners, campaigns, events,
 * BarKode records, submissions, settings and redirects stay in the database so a
 * reinstall picks them up. Content is only purged when
 * hk9_settings[advanced][purge_on_uninstall] is true.
 *
 * @package HK9\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Plugin-defined capabilities are meaningless without the plugin: always remove them.
$hk9_cap_types = [
	[ 'hk9_story', 'hk9_stories' ],
	[ 'hk9_team', 'hk9_teams' ],
	[ 'hk9_person', 'hk9_people' ],
	[ 'hk9_partner', 'hk9_partners' ],
	[ 'hk9_campaign', 'hk9_campaigns' ],
	[ 'hk9_event', 'hk9_events' ],
	[ 'hk9_barkode', 'hk9_barkodes' ],
	[ 'hk9_submission', 'hk9_submissions' ],
];
$hk9_caps      = [ 'hk9_manage_settings', 'hk9_run_import', 'hk9_manage_redirects', 'hk9_view_submissions' ];
foreach ( $hk9_cap_types as [ $hk9_s, $hk9_p ] ) {
	$hk9_caps = array_merge(
		$hk9_caps,
		[
			"edit_{$hk9_s}",
			"read_{$hk9_s}",
			"delete_{$hk9_s}",
			"edit_{$hk9_p}",
			"edit_others_{$hk9_p}",
			"delete_{$hk9_p}",
			"publish_{$hk9_p}",
			"read_private_{$hk9_p}",
			"delete_private_{$hk9_p}",
			"delete_published_{$hk9_p}",
			"delete_others_{$hk9_p}",
			"edit_private_{$hk9_p}",
			"edit_published_{$hk9_p}",
			"create_{$hk9_p}",
		]
	);
}
foreach ( array_keys( wp_roles()->roles ) as $hk9_role_name ) {
	$hk9_role = get_role( $hk9_role_name );
	if ( ! $hk9_role ) {
		continue;
	}
	foreach ( $hk9_caps as $hk9_cap ) {
		$hk9_role->remove_cap( $hk9_cap );
	}
}

// Version/bookkeeping options are always removed (they are recreated on activation).
foreach ( [ 'hk9_core_version', 'hk9_caps_version', 'hk9_terms_version', 'hk9_rewrite_version' ] as $hk9_option ) {
	delete_option( $hk9_option );
}

$hk9_settings = get_option( 'hk9_settings', [] );
$hk9_purge    = is_array( $hk9_settings ) && ! empty( $hk9_settings['advanced']['purge_on_uninstall'] );

if ( ! $hk9_purge ) {
	return;
}

global $wpdb;

// 1. Posts of every Heartland type (and their meta / term relationships / attached revisions).
$hk9_types = [ 'hk9_story', 'hk9_team', 'hk9_person', 'hk9_partner', 'hk9_campaign', 'hk9_event', 'hk9_barkode', 'hk9_submission' ];
foreach ( $hk9_types as $hk9_type ) {
	$hk9_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $hk9_type ) );
	foreach ( $hk9_ids as $hk9_id ) {
		wp_delete_post( (int) $hk9_id, true );
	}
}

// 2. Section/plugin meta left on pages and posts.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'hk9\\_%' OR meta_key LIKE '\\_hk9\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// 3. Taxonomy terms.
$hk9_term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'hk9_partner_type' ) );
foreach ( $hk9_term_ids as $hk9_term_id ) {
	wp_delete_term( (int) $hk9_term_id, 'hk9_partner_type' );
}

// 4. Options and transients.
$hk9_option_names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'hk9\\_%' OR option_name LIKE '\\_transient\\_hk9\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hk9\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $hk9_option_names as $hk9_option_name ) {
	if ( str_starts_with( $hk9_option_name, '_transient_timeout_' ) ) {
		delete_transient( substr( $hk9_option_name, strlen( '_transient_timeout_' ) ) );
	} elseif ( str_starts_with( $hk9_option_name, '_transient_' ) ) {
		delete_transient( substr( $hk9_option_name, strlen( '_transient_' ) ) );
	} else {
		delete_option( $hk9_option_name );
	}
}

// 5. Importer map table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hk9_import_map" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// 6. Import logs (uploads/hk9-import) and any leftover uploaded payload copy (uploads/hk9-payload-*),
//    which can hold the whole site payload including registry records. Only directories directly
//    under the uploads base dir with those exact names are touched.
require_once ABSPATH . 'wp-admin/includes/file.php';
$hk9_uploads = wp_upload_dir( null, false );
if ( empty( $hk9_uploads['error'] ) && WP_Filesystem() ) {
	global $wp_filesystem;
	$hk9_base = realpath( (string) $hk9_uploads['basedir'] );
	if ( false !== $hk9_base && $wp_filesystem instanceof WP_Filesystem_Base ) {
		$hk9_dirs = array_merge(
			[ $hk9_base . DIRECTORY_SEPARATOR . 'hk9-import' ],
			glob( $hk9_base . DIRECTORY_SEPARATOR . 'hk9-payload-*', GLOB_ONLYDIR ) ?: []
		);
		foreach ( $hk9_dirs as $hk9_dir ) {
			$hk9_real = realpath( $hk9_dir );
			if ( false === $hk9_real || dirname( $hk9_real ) !== $hk9_base ) {
				continue; // Not a direct child of uploads (symlink elsewhere, or missing).
			}
			$hk9_name = basename( $hk9_real );
			if ( 'hk9-import' !== $hk9_name && ! str_starts_with( $hk9_name, 'hk9-payload-' ) ) {
				continue;
			}
			if ( $wp_filesystem->is_dir( $hk9_real ) ) {
				$wp_filesystem->delete( $hk9_real, true );
			}
		}
	}
}

// 7. Per-user dismissal marker of the "notification could not be sent" admin notice.
delete_metadata( 'user', 0, 'hk9_forms_mail_failed_dismissed', '', true );

// 8. Rewrite rules referencing the removed types.
flush_rewrite_rules();
