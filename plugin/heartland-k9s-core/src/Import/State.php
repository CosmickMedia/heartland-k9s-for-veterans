<?php
/**
 * Import run state (option-backed, autoload = no) and run history.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class State {

	public const OPTION         = 'hk9_import_state';
	public const RUNS_OPTION    = 'hk9_import_runs';
	public const PAUSE_OPTION   = 'hk9_import_pause_requested';
	public const PAYLOAD_OPTION = 'hk9_import_payload';

	public const STATUS_IDLE    = 'idle';
	public const STATUS_RUNNING = 'running';
	public const STATUS_PAUSED  = 'paused';
	public const STATUS_FAILED  = 'failed';
	public const STATUS_DONE    = 'done';

	public const MAX_ERRORS = 500;
	public const MAX_RUNS   = 20;

	/**
	 * Ordered step names (the contract; see docs/importer.md).
	 */
	public const STEPS = [
		'validate',
		'terms',
		'media_files',
		'media_sizes',
		'posts_stub',
		'posts_hierarchy',
		'reading',
		'posts_content',
		'menus',
		'options',
		'redirects',
		'finalize',
	];

	public static function blank(): array {
		return [
			'run_id'         => '',
			'payload_dir'    => '',
			'payload_source' => '',
			'mode'           => [
				'dry_run'    => false,
				'overwrite'  => false,
				'adopt'      => false,
				'batch'      => 25,
				'budget'     => 20,
				'until_step' => '',
			],
			'status'         => self::STATUS_IDLE,
			'step'           => '',
			'step_index'     => 0,
			'cursor'         => 0,
			'step_total'     => 0,
			'totals'         => [
				'records'     => 0,
				'attachments' => 0,
				'bytes'       => 0,
			],
			'bytes_copied'   => 0,
			'counts'         => [],
			'errors'         => [],
			'warnings'       => [],
			'failed_keys'    => [],
			'prehash'        => [],
			'dry_created'    => [],
			'dry_adopted'    => [],
			'started_at'     => '',
			'updated_at'     => '',
			'finished_at'    => '',
			'user_id'        => 0,
			'log_file'       => '',
			'passes'         => 0,
		];
	}

	public static function load(): array {
		wp_cache_delete( self::OPTION, 'options' );
		$state = get_option( self::OPTION, [] );
		if ( ! is_array( $state ) || empty( $state['run_id'] ) ) {
			return self::blank();
		}
		return array_replace_recursive( self::blank(), $state );
	}

	public static function save( array $state ): void {
		$state['updated_at'] = gmdate( 'c' );
		if ( count( $state['errors'] ) > self::MAX_ERRORS ) {
			$state['errors'] = array_slice( $state['errors'], -self::MAX_ERRORS );
		}
		if ( count( $state['warnings'] ) > self::MAX_ERRORS ) {
			$state['warnings'] = array_slice( $state['warnings'], -self::MAX_ERRORS );
		}
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, $state, '', false );
		} else {
			update_option( self::OPTION, $state, false );
		}
	}

	public static function clear(): void {
		delete_option( self::OPTION );
		delete_option( self::PAUSE_OPTION );
	}

	public static function request_pause(): void {
		update_option( self::PAUSE_OPTION, time(), false );
	}

	public static function pause_requested(): bool {
		wp_cache_delete( self::PAUSE_OPTION, 'options' );
		return (bool) get_option( self::PAUSE_OPTION, false );
	}

	public static function clear_pause_request(): void {
		delete_option( self::PAUSE_OPTION );
	}

	/* --------------------------------------------------------------- runs */

	public static function runs(): array {
		wp_cache_delete( self::RUNS_OPTION, 'options' );
		$runs = get_option( self::RUNS_OPTION, [] );
		return is_array( $runs ) ? $runs : [];
	}

	public static function record_run( array $state ): void {
		$runs = self::runs();
		$id   = (string) $state['run_id'];
		if ( '' === $id ) {
			return;
		}
		$runs[ $id ] = [
			'run_id'      => $id,
			'started_at'  => $state['started_at'],
			'finished_at' => $state['finished_at'],
			'status'      => $state['status'],
			'mode'        => $state['mode'],
			'payload_dir' => $state['payload_dir'],
			'counts'      => $state['counts'],
			'errors'      => count( $state['errors'] ),
			'user_id'     => $state['user_id'],
			'log_file'    => $state['log_file'],
			'rolled_back' => $runs[ $id ]['rolled_back'] ?? false,
		];
		if ( count( $runs ) > self::MAX_RUNS ) {
			$runs = array_slice( $runs, -self::MAX_RUNS, null, true );
		}
		update_option( self::RUNS_OPTION, $runs, false );
	}

	public static function mark_rolled_back( string $run_id, array $report ): void {
		$runs = self::runs();
		if ( isset( $runs[ $run_id ] ) ) {
			$runs[ $run_id ]['rolled_back']     = true;
			$runs[ $run_id ]['rollback_report'] = [
				'deleted'   => count( $report['deleted'] ?? [] ),
				'restored'  => count( $report['restored'] ?? [] ),
				'unadopted' => count( $report['unadopted'] ?? [] ),
				'skipped'   => count( $report['skipped'] ?? [] ),
				'at'        => gmdate( 'c' ),
			];
			update_option( self::RUNS_OPTION, $runs, false );
		}
	}

	public static function clear_runs(): void {
		delete_option( self::RUNS_OPTION );
	}

	/**
	 * Run ids are unguessable: they double as the log file name.
	 */
	public static function new_run_id(): string {
		return gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 8, false, false ) );
	}

	public static function step_index( string $step ): int {
		$i = array_search( $step, self::STEPS, true );
		return false === $i ? 0 : (int) $i;
	}
}
