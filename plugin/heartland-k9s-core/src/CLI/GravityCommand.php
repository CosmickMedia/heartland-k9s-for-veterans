<?php
/**
 * WP-CLI: wp hk9 gravity provision [--force] | wp hk9 gravity status [--format=table|json]
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

use HK9\Core\Forms\GravityProvisioner;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class GravityCommand {

	/**
	 * Create the Heartland forms ("Contact", "Initial Application Inquiry") in
	 * Gravity Forms, store their ids under Settings → Forms and switch the
	 * default provider to Gravity Forms when no form was selected yet.
	 * Idempotent: existing forms are kept. Never deletes a form.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Update existing Heartland forms in place (fields, the Heartland notification and confirmation;
	 *   entries and other notifications are kept) and re-create forms that were trashed or deleted.
	 *   A selected form that was not created by Heartland (picked by hand) is left as is (action "skipped").
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 gravity provision
	 *     wp hk9 gravity provision --force
	 *
	 * @when after_wp_load
	 */
	public function provision( array $args, array $assoc ): void {
		if ( ! class_exists( GravityProvisioner::class ) ) {
			WP_CLI::error( 'Provisioner unavailable.' );
		}
		$force  = ! empty( $assoc['force'] );
		$result = GravityProvisioner::provision( $force, 'cli' );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		foreach ( $result['forms'] as $role => $r ) {
			WP_CLI::log( sprintf( '  %-11s %-9s #%-4d %s', $role, $r['action'], $r['id'], $r['message'] ) );
		}
		if ( $result['provider_set'] ) {
			WP_CLI::log( '  forms.provider set to "gravity".' );
		}
		$drift   = array_filter( $result['forms'], static fn( array $r ): bool => 'drift' === $r['action'] );
		$skipped = array_filter( $result['forms'], static fn( array $r ): bool => 'skipped' === $r['action'] );
		if ( [] !== $skipped ) {
			WP_CLI::warning( 'Some selected forms were not created by Heartland and were left as is (see above): clear the selection under Settings → Forms and run again to get the Heartland definition.' );
		}
		if ( [] !== $drift ) {
			WP_CLI::warning( 'Some forms were not re-created; run with --force to create them again.' );
			return;
		}
		if ( [] !== $skipped ) {
			return;
		}
		WP_CLI::success( 'Heartland forms provisioned in Gravity Forms.' );
	}

	/**
	 * Report whether Gravity Forms is active, which forms are selected and any drift
	 * (trashed / deleted / inactive / outdated forms).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 gravity status
	 *
	 * @when after_wp_load
	 */
	public function status( array $args, array $assoc ): void {
		$status = GravityProvisioner::status();
		if ( 'json' === (string) ( $assoc['format'] ?? 'table' ) ) {
			WP_CLI::line( (string) wp_json_encode( $status, JSON_PRETTY_PRINT ) );
			WP_CLI::halt( $status['ok'] ? 0 : 1 );
		}
		WP_CLI::log( sprintf( '  Gravity Forms: %s', $status['gravity_active'] ? 'active (' . $status['gravity_version'] . ')' : 'not active' ) );
		WP_CLI::log( sprintf( '  Default provider: %s', $status['provider'] ) );
		if ( $status['last_run'] ) {
			WP_CLI::log( sprintf( '  Last provisioning: %s by %s (definition v%d)', (string) ( $status['last_run']['time'] ?? '' ), (string) ( $status['last_run']['by'] ?? '' ), (int) ( $status['last_run']['version'] ?? 0 ) ) );
		}
		foreach ( $status['forms'] as $role => $f ) {
			$line = sprintf( '  %-11s id=%-4d %-8s', $role, $f['id'], $f['state'] );
			if ( '' !== $f['title'] ) {
				$line .= ' "' . $f['title'] . '"';
			}
			if ( $f['provisioned'] ) {
				$line .= ' (Heartland v' . $f['version'] . ')';
			}
			if ( [] !== $f['candidates'] && 'unset' === $f['state'] ) {
				$line .= ' candidates: #' . implode( ', #', $f['candidates'] );
			}
			WP_CLI::log( $line );
			foreach ( $f['drift'] as $d ) {
				WP_CLI::log( '              drift: ' . $d );
			}
			if ( '' !== $f['note'] ) {
				WP_CLI::log( '              note:  ' . $f['note'] );
			}
		}
		if ( $status['needs_provisioning'] ) {
			WP_CLI::log( '  Run: wp hk9 gravity provision' );
		}
		if ( $status['ok'] ) {
			WP_CLI::success( 'Both Heartland forms are present and active.' );
			return;
		}
		WP_CLI::warning( 'Attention needed (see the drift lines above).' );
		WP_CLI::halt( 1 );
	}
}
