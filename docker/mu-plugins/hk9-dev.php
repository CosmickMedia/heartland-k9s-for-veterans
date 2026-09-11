<?php
/**
 * Plugin Name: HK9 Dev Harness (local only)
 * Description: Routes wp_mail() to Mailpit and exposes local-only fixture commands. Never ship this file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Deliver all outbound mail to the Mailpit container.
add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host        = defined( 'HK9_DEV_MAIL_HOST' ) ? HK9_DEV_MAIL_HOST : ( getenv( 'HK9_DEV_MAIL_HOST' ) ?: 'mailpit' );
		$phpmailer->Port        = defined( 'HK9_DEV_MAIL_PORT' ) ? (int) HK9_DEV_MAIL_PORT : 1025;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
	}
);

// 2. Mark the environment so templates/importer can guard dev-only behaviour.
if ( ! defined( 'HK9_LOCAL_DEV' ) ) {
	define( 'HK9_LOCAL_DEV', true );
}

// 3. Local fixtures for blog/pagination/empty-state testing. Never part of the payload.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'hk9-dev fixtures',
		static function ( $args, $assoc ): void {
			$action = $args[0] ?? 'create';
			$marker = '_hk9_local_fixture';
			if ( 'delete' === $action ) {
				$ids = get_posts( [ 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => $marker ] );
				foreach ( $ids as $id ) {
					wp_delete_post( $id, true );
				}
				$terms = get_terms( [ 'taxonomy' => [ 'category', 'post_tag' ], 'hide_empty' => false, 'meta_key' => $marker ] );
				foreach ( $terms as $t ) {
					wp_delete_term( $t->term_id, $t->taxonomy );
				}
				$users = get_users( [ 'meta_key' => $marker, 'fields' => 'ID' ] );
				require_once ABSPATH . 'wp-admin/includes/user.php';
				foreach ( $users as $uid ) {
					wp_delete_user( (int) $uid, 1 );
				}
				WP_CLI::success( sprintf( 'Deleted %d fixture posts, %d fixture terms and %d fixture users.', count( $ids ), count( $terms ), count( $users ) ) );
				return;
			}
			$file = WP_CONTENT_DIR . '/hk9-fixtures/blog-fixtures.php';
			if ( ! file_exists( $file ) ) {
				WP_CLI::error( 'Fixture file missing: ' . $file );
			}
			require $file; // defines hk9_dev_create_blog_fixtures()
			$report = hk9_dev_create_blog_fixtures( $marker );
			WP_CLI::success( $report );
		}
	);
}
