<?php
/**
 * WP-CLI: wp hk9 status [--run=<id>] [--format=table|json]
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

use HK9\Core\Import\Runner;
use HK9\Core\Import\State;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class StatusCommand {

	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered || ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		self::$registered = true;

		WP_CLI::add_command( 'hk9 status', self::class );
	}

	/**
	 * Show the importer state, lock, recent runs and map counts.
	 *
	 * ## OPTIONS
	 *
	 * [--run=<id>]
	 * : Show one run from the history.
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
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc ): void {
		self::status( $args, $assoc );
	}

	public static function status( array $args, array $assoc ): void {
		$snapshot = Runner::status( true );
		$format   = (string) ( $assoc['format'] ?? 'table' );

		if ( isset( $assoc['run'] ) ) {
			$run = $snapshot['runs'][ (string) $assoc['run'] ] ?? ( State::runs()[ (string) $assoc['run'] ] ?? null );
			if ( ! $run ) {
				WP_CLI::error( sprintf( 'Run %s is not in the history.', (string) $assoc['run'] ) );
			}
			if ( 'json' === $format ) {
				WP_CLI::line( (string) wp_json_encode( $run, JSON_PRETTY_PRINT ) );
				return;
			}
			foreach ( $run as $k => $v ) {
				WP_CLI::log( sprintf( '%-13s %s', $k, is_scalar( $v ) ? (string) $v : (string) wp_json_encode( $v ) ) );
			}
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::line( (string) wp_json_encode( $snapshot, JSON_PRETTY_PRINT ) );
			return;
		}

		$s = $snapshot['state'];
		WP_CLI::log( sprintf( 'Status:   %s', $s['status'] ) );
		if ( State::STATUS_IDLE !== $s['status'] ) {
			WP_CLI::log( sprintf( 'Run:      %s (%s%s%s, pass %d)', $s['run_id'], ! empty( $s['mode']['dry_run'] ) ? 'dry run' : 'import', ! empty( $s['mode']['adopt'] ) ? ', adopt existing' : '', ! empty( $s['mode']['overwrite'] ) ? ', overwrite' : '', (int) $s['passes'] ) );
			WP_CLI::log( sprintf( 'Payload:  %s', $s['payload_dir'] ) );
			WP_CLI::log( sprintf( 'Step:     %s (%d/%d) cursor %d/%d', $s['step'], (int) $s['step_index'] + 1, count( State::STEPS ), (int) $s['cursor'], (int) $s['step_total'] ) );
			WP_CLI::log( sprintf( 'Errors:   %d   Warnings: %d', (int) $s['errors_total'], (int) $s['warnings_total'] ) );
			WP_CLI::log( sprintf( 'Log:      %s', $s['log_file'] ) );
			$rows = [];
			foreach ( State::STEPS as $step ) {
				$c = $s['counts'][ $step ] ?? null;
				if ( null === $c ) {
					continue;
				}
				$rows[] = [ 'step' => $step ] + $c + [ 'adopt' => 0 ];
			}
			if ( $rows ) {
				\WP_CLI\Utils\format_items( 'table', $rows, [ 'step', 'create', 'adopt', 'update', 'skip', 'conflict', 'fail' ] );
			}
		}
		WP_CLI::log( sprintf( 'Lock:     %s', $snapshot['lock'] ? 'held (expires in ' . (int) $snapshot['lock']['expires_in'] . ' s)' : 'free' ) );
		WP_CLI::log( 'Map:      ' . ( $snapshot['map'] ? implode( ', ', array_map( static fn( $k, $v ) => $k . '=' . $v, array_keys( $snapshot['map'] ), $snapshot['map'] ) ) : 'empty' ) );
		if ( $snapshot['payload'] ) {
			$p = $snapshot['payload'];
			WP_CLI::log( sprintf( 'Payload:  %s (%s)', $p['dir'], ! empty( $p['ok'] ) ? sprintf( '%d records, %d media', (int) $p['records'], (int) $p['attachments'] ) : (string) ( $p['error'] ?? 'invalid' ) ) );
		}
		if ( $snapshot['runs'] ) {
			$rows = [];
			foreach ( $snapshot['runs'] as $r ) {
				$rows[] = [
					'run'      => $r['run_id'],
					'started'  => $r['started_at'],
					'status'   => $r['status'] . ( ! empty( $r['rolled_back'] ) ? ' (rolled back)' : '' ),
					'mode'     => ( ! empty( $r['mode']['dry_run'] ) ? 'dry-run' : 'import' ) . ( ! empty( $r['mode']['adopt'] ) ? '+adopt' : '' ) . ( ! empty( $r['mode']['overwrite'] ) ? '+overwrite' : '' ),
					'errors'   => (int) $r['errors'],
				];
			}
			\WP_CLI\Utils\format_items( 'table', $rows, [ 'run', 'started', 'status', 'mode', 'errors' ] );
		}
	}
}
