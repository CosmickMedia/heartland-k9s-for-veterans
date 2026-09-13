<?php
/**
 * WP-CLI: wp hk9 import | rollback | reset-state | preflight.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

use HK9\Core\Import\Manifest;
use HK9\Core\Import\Map;
use HK9\Core\Import\Rollback;
use HK9\Core\Import\Runner;
use HK9\Core\Import\State;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class ImportCommand {

	private static bool $registered = false;

	/**
	 * Idempotent registration (safe to call from a central CLI bootstrap as well).
	 */
	public static function register(): void {
		if ( self::$registered || ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		self::$registered = true;

		WP_CLI::add_command( 'hk9 import', self::class );
		WP_CLI::add_command( 'hk9 rollback', RollbackCommand::class );
		WP_CLI::add_command( 'hk9 reset-state', ResetStateCommand::class );
		WP_CLI::add_command( 'hk9 preflight', PreflightCommand::class );
	}

	/**
	 * Import a payload directory (idempotent, resumable).
	 *
	 * ## OPTIONS
	 *
	 * [<dir>]
	 * : Payload directory containing manifest.json (omit with --resume).
	 *
	 * [--dry-run]
	 * : Walk every step and report counts without writing.
	 *
	 * [--overwrite]
	 * : Resolve conflicts (records edited on this site) in favour of the payload.
	 *
	 * [--adopt-existing]
	 * : Existing site: bind payload records to the pages (by live id or slug), legacy BarKode pages (converted in place) and attachments (by live id + file name, or by upload path) already on this site instead of creating "-2" duplicates; reported in the "adopt" column, logged as "ADOPT #id". Required for the content-only payload (heartland-k9s-payload-lite.zip), whose live media records ship no file.
	 *
	 * [--resume]
	 * : Continue the paused/interrupted run, or start a new pass over a finished one.
	 *
	 * [--step=<name>]
	 * : Run up to and including this step, then pause.
	 * ---
	 * options:
	 *   - validate
	 *   - terms
	 *   - media_files
	 *   - media_sizes
	 *   - posts_stub
	 *   - posts_hierarchy
	 *   - reading
	 *   - posts_content
	 *   - menus
	 *   - options
	 *   - redirects
	 *   - finalize
	 * ---
	 *
	 * [--batch=<n>]
	 * : Items per tick before state is persisted (default 50).
	 *
	 * [--budget=<seconds>]
	 * : Seconds per tick (default 30).
	 *
	 * [--quiet-progress]
	 * : Do not print per-tick progress.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 import /var/www/html/wp-content/hk9-payload --user=admin --dry-run
	 *     wp hk9 import /var/www/html/wp-content/hk9-payload --user=admin
	 *     wp hk9 import /home/site/hk9-payload --adopt-existing --user=admin
	 *     wp hk9 import /home/site/hk9-payload-lite --adopt-existing --user=admin   # content-only payload
	 *     wp hk9 import --resume --user=admin
	 *     wp hk9 import ./payload --step=media_files --user=admin
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc ): void {
		self::import( $args, $assoc );
	}

	/**
	 * wp hk9 import <dir> [--dry-run] [--overwrite] [--adopt-existing] [--resume] [--step=<name>] [--batch=<n>] [--budget=<s>] --user=<admin>
	 */
	public static function import( array $args, array $assoc ): void {
		self::require_admin_user();
		Map::ensure();

		$resume  = ! empty( $assoc['resume'] );
		$dir     = isset( $args[0] ) ? (string) $args[0] : '';
		$mode    = [
			'dry_run'    => ! empty( $assoc['dry-run'] ),
			'overwrite'  => ! empty( $assoc['overwrite'] ),
			'adopt'      => ! empty( $assoc['adopt-existing'] ),
			'batch'      => isset( $assoc['batch'] ) ? max( 1, (int) $assoc['batch'] ) : 50,
			'budget'     => isset( $assoc['budget'] ) ? max( 2, (int) $assoc['budget'] ) : 30,
			'until_step' => isset( $assoc['step'] ) ? (string) $assoc['step'] : '',
		];
		if ( '' !== $mode['until_step'] && ! in_array( $mode['until_step'], State::STEPS, true ) ) {
			WP_CLI::error( sprintf( 'Unknown step "%s". Steps: %s', $mode['until_step'], implode( ', ', State::STEPS ) ) );
		}

		self::wait_for_lock();

		if ( $resume ) {
			$state = State::load();
			if ( '' !== $dir ) {
				$real = realpath( $dir );
				if ( false !== $real && untrailingslashit( $real ) !== $state['payload_dir'] ) {
					WP_CLI::warning( sprintf( 'Resuming run %s uses its recorded payload directory %s (ignoring %s).', $state['run_id'], $state['payload_dir'], $real ) );
				}
			}
			$overrides = [
				'batch'      => $mode['batch'],
				'budget'     => $mode['budget'],
				'until_step' => $mode['until_step'],
			];
			if ( ! empty( $assoc['overwrite'] ) ) {
				$overrides['overwrite'] = true;
			}
			if ( ! empty( $assoc['adopt-existing'] ) ) {
				$overrides['adopt'] = true;
			}
			if ( ! empty( $assoc['dry-run'] ) ) {
				$overrides['dry_run'] = true;
			}
			$result = Runner::resume( $overrides );
		} else {
			if ( '' === $dir ) {
				WP_CLI::error( 'Provide the payload directory (or use --resume).' );
			}
			$real = realpath( $dir );
			if ( false === $real || ! is_dir( $real ) || ! is_file( $real . '/manifest.json' ) ) {
				WP_CLI::error( sprintf( 'No manifest.json found in "%s".', $dir ) );
			}
			$state = State::load();
			if ( State::STATUS_RUNNING === $state['status'] && Map::lock_info() ) {
				WP_CLI::error( 'An import is already running. Use --resume, or wp hk9 reset-state --yes.' );
			}
			if ( in_array( $state['status'], [ State::STATUS_PAUSED, State::STATUS_RUNNING ], true ) ) {
				WP_CLI::warning( sprintf( 'Discarding the unfinished run %s (it stays in the run history for rollback).', $state['run_id'] ) );
			}
			$docroot = realpath( ABSPATH );
			if ( $docroot && str_starts_with( $real, $docroot . DIRECTORY_SEPARATOR ) && ! is_file( $real . '/.htaccess' ) ) {
				WP_CLI::warning( 'The payload directory is inside the web root without an .htaccess deny rule; do not leave sensitive payloads there.' );
			}
			// A content-only payload can only be satisfied in existing-site mode: say so before a run is recorded.
			$manifest = Manifest::load( $real );
			if ( $manifest instanceof Manifest && $manifest->is_lite() ) {
				if ( ! $mode['adopt'] ) {
					WP_CLI::error( 'This is a content-only payload: add --adopt-existing — it reuses the media already in this Media Library — or use the full payload.' );
				}
				WP_CLI::log( 'Content-only payload: the live media records ship no file and are reused from this site\'s Media Library.' );
			}
			$result = Runner::start( $real, $mode, get_current_user_id(), 'path' );
		}
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		$state = $result;
		WP_CLI::log( sprintf( '%s run %s (%s%s%s) — payload %s', $resume ? 'Resuming' : 'Starting', $state['run_id'], $state['mode']['dry_run'] ? 'DRY RUN' : 'import', ! empty( $state['mode']['adopt'] ) ? ', adopt existing' : '', $state['mode']['overwrite'] ? ', overwrite' : '', $state['payload_dir'] ) );

		$quiet = ! empty( $assoc['quiet-progress'] );
		$last  = '';
		if ( ! $quiet ) {
			Runner::$on_step = static function ( string $step, array $s ): void {
				$c = $s['counts'][ $step ] ?? [];
				WP_CLI::log( sprintf( '  %-16s done  create=%d adopt=%d update=%d skip=%d conflict=%d fail=%d', $step, (int) ( $c['create'] ?? 0 ), (int) ( $c['adopt'] ?? 0 ), (int) ( $c['update'] ?? 0 ), (int) ( $c['skip'] ?? 0 ), (int) ( $c['conflict'] ?? 0 ), (int) ( $c['fail'] ?? 0 ) ) );
			};
		}
		$final = Runner::run_all(
			static function ( array $s ) use ( $quiet, &$last ): void {
				if ( $quiet ) {
					return;
				}
				if ( ! in_array( $s['status'], [ State::STATUS_RUNNING, State::STATUS_PAUSED ], true ) ) {
					return;
				}
				$line = sprintf( '  %-16s %s/%s …', $s['step'], (string) $s['cursor'], (string) $s['step_total'] );
				if ( $line !== $last ) {
					WP_CLI::log( $line );
					$last = $line;
				}
			},
			$mode['budget'],
			$mode['batch']
		);
		if ( is_wp_error( $final ) ) {
			WP_CLI::error( $final->get_error_message() );
		}
		self::summary( $final );
	}

	public static function summary( array $state ): void {
		WP_CLI::log( '' );
		$rows = [];
		foreach ( State::STEPS as $step ) {
			$c      = $state['counts'][ $step ] ?? [];
			$rows[] = [
				'step'     => $step,
				'create'   => (int) ( $c['create'] ?? 0 ),
				'adopt'    => (int) ( $c['adopt'] ?? 0 ),
				'update'   => (int) ( $c['update'] ?? 0 ),
				'skip'     => (int) ( $c['skip'] ?? 0 ),
				'conflict' => (int) ( $c['conflict'] ?? 0 ),
				'fail'     => (int) ( $c['fail'] ?? 0 ),
			];
		}
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'step', 'create', 'adopt', 'update', 'skip', 'conflict', 'fail' ] );

		$fatal = array_filter( $state['errors'], static fn( $e ) => ! empty( $e['fatal'] ) );
		$items = array_filter( $state['errors'], static fn( $e ) => empty( $e['fatal'] ) );
		foreach ( $fatal as $e ) {
			WP_CLI::warning( 'FATAL [' . $e['step'] . '] ' . $e['message'] );
		}
		foreach ( array_slice( $items, 0, 50 ) as $e ) {
			WP_CLI::warning( '[' . $e['step'] . '] ' . $e['key'] . ': ' . $e['message'] );
		}
		if ( count( $items ) > 50 ) {
			WP_CLI::warning( sprintf( '… %d more record errors in the log.', count( $items ) - 50 ) );
		}
		if ( $state['warnings'] ) {
			WP_CLI::log( sprintf( '%d warning(s) — see the log.', count( $state['warnings'] ) ) );
		}
		WP_CLI::log( 'Log: ' . $state['log_file'] );
		WP_CLI::log( sprintf( 'Run %s status: %s (step %s, cursor %d).', $state['run_id'], $state['status'], $state['step'], (int) $state['cursor'] ) );

		if ( State::STATUS_FAILED === $state['status'] ) {
			WP_CLI::error( 'Import failed.' );
		}
		if ( State::STATUS_PAUSED === $state['status'] ) {
			WP_CLI::success( 'Paused. Continue with: wp hk9 import --resume --user=<admin>' );
			return;
		}
		if ( $items ) {
			WP_CLI::warning( sprintf( 'Completed with %d record error(s). Fix the payload and run: wp hk9 import --resume --user=<admin>', count( $items ) ) );
			WP_CLI::halt( 2 );
		}
		WP_CLI::success( $state['mode']['dry_run'] ? 'Dry run complete.' : 'Import complete.' );
	}

	/**
	 * wp hk9 rollback --run=<id> [--force] [--yes] [--dry-run]
	 */
	public static function rollback( array $args, array $assoc ): void {
		self::require_admin_user();
		$run   = (string) ( $assoc['run'] ?? '' );
		$force = ! empty( $assoc['force'] );
		$dry   = ! empty( $assoc['dry-run'] );
		if ( ! $dry ) {
			WP_CLI::confirm( sprintf( 'Roll back run %s%s? Objects created by it will be deleted and settings restored.', $run, $force ? ' (FORCE: including edited records)' : '' ), $assoc );
		}
		self::wait_for_lock();
		$report = Rollback::run( $run, $force, $dry );
		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report->get_error_message() );
		}
		foreach ( $report['deleted'] as $d ) {
			WP_CLI::log( sprintf( '  deleted   %s%s', $d['key'], isset( $d['id'] ) ? ' (#' . $d['id'] . ')' : ( isset( $d['note'] ) ? ' (' . $d['note'] . ')' : '' ) ) );
		}
		foreach ( $report['restored'] as $r ) {
			WP_CLI::log( sprintf( '  restored  %s [%s]', $r['key'], implode( ', ', (array) ( $r['fields'] ?? [] ) ) ) );
		}
		foreach ( (array) ( $report['unadopted'] ?? [] ) as $u ) {
			WP_CLI::log( sprintf( '  unadopted %s (#%d, kept)', $u['key'], (int) ( $u['id'] ?? 0 ) ) );
		}
		foreach ( $report['skipped'] as $s ) {
			WP_CLI::warning( sprintf( 'skipped   %s — %s', $s['key'], $s['reason'] ) );
		}
		foreach ( $report['errors'] as $e ) {
			WP_CLI::warning( 'error     ' . $e );
		}
		WP_CLI::success( sprintf( '%s: %d deleted, %d restored, %d un-adopted, %d skipped, %d errors.', $dry ? 'Rollback dry run' : 'Rollback', count( $report['deleted'] ), count( $report['restored'] ), count( (array) ( $report['unadopted'] ?? [] ) ), count( $report['skipped'] ), count( $report['errors'] ) ) );
	}

	/**
	 * wp hk9 reset-state --yes
	 */
	public static function reset_state( array $args, array $assoc ): void {
		self::require_admin_user();
		WP_CLI::confirm( 'Clear the import state and lock? (Map table and run history are kept.)', $assoc );
		Runner::reset();
		WP_CLI::success( 'Import state cleared.' );
	}

	private static function require_admin_user(): void {
		if ( ! function_exists( 'current_user_can' ) || ! get_current_user_id() || ! current_user_can( 'manage_options' ) || ! current_user_can( 'unfiltered_html' ) ) {
			WP_CLI::error( 'Run this command as an administrator with unfiltered_html: add --user=<admin login or id>.' );
		}
	}

	/**
	 * A killed process leaves the lock until it expires (60 s); wait instead of failing.
	 */
	private static function wait_for_lock(): void {
		$info = Map::lock_info();
		if ( ! $info ) {
			return;
		}
		$wait = max( 0, $info['expires'] - time() ) + 2;
		WP_CLI::log( sprintf( 'Import lock is held by another process; waiting up to %d s for it to expire…', $wait ) );
		$deadline = time() + $wait;
		while ( time() < $deadline && Map::lock_info() ) {
			sleep( 2 );
		}
		if ( Map::lock_info() ) {
			WP_CLI::error( 'Lock still held. If no import is running, use: wp hk9 reset-state --yes' );
		}
	}
}
