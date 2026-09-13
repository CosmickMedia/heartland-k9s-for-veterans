<?php
/**
 * Import state machine: one step runner shared by the REST endpoints (admin
 * polling) and WP-CLI. Every tick takes the atomic lock, loads the state,
 * executes the current step from its cursor until the time/batch budget is
 * spent, persists the state and releases the lock.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Runner {

	public const LOCK_TTL = 60;

	/** @var callable|null function(string $step, array $state): void — fired when a step completes (CLI progress). */
	public static $on_step = null;

	private const STEP_CLASSES = [
		'validate'        => Steps\Validate::class,
		'terms'           => Steps\Terms::class,
		'media_files'     => Steps\MediaFiles::class,
		'media_sizes'     => Steps\MediaSizes::class,
		'posts_stub'      => Steps\PostsStub::class,
		'posts_hierarchy' => Steps\PostsHierarchy::class,
		'reading'         => Steps\Reading::class,
		'posts_content'   => Steps\PostsContent::class,
		'menus'           => Steps\Menus::class,
		'options'         => Steps\Options::class,
		'redirects'       => Steps\Redirects::class,
		'finalize'        => Steps\Finalize::class,
	];

	/**
	 * Begin a new run.
	 *
	 * @param array $mode {dry_run, overwrite, adopt, batch, budget, until_step}
	 */
	public static function start( string $dir, array $mode, int $user_id, string $source = 'path' ): array|WP_Error {
		Map::ensure();

		$state = State::load();
		if ( State::STATUS_RUNNING === $state['status'] && Map::lock_info() ) {
			return new WP_Error( 'hk9_import_busy', __( 'An import is already running. Pause it or wait for it to finish.', 'heartland-k9s-core' ), [ 'status' => 409 ] );
		}
		$manifest = Manifest::load( $dir );
		if ( is_wp_error( $manifest ) ) {
			$manifest->add_data( [ 'status' => 400 ] );
			return $manifest;
		}

		$state                   = State::blank();
		$state['run_id']         = State::new_run_id();
		$state['payload_dir']    = untrailingslashit( $dir );
		$state['payload_source'] = $source;
		$state['mode']           = self::mode( $mode );
		$state['status']         = State::STATUS_RUNNING;
		$state['step']           = State::STEPS[0];
		$state['step_index']     = 0;
		$state['cursor']         = 0;
		$state['started_at']     = gmdate( 'c' );
		$state['user_id']        = $user_id;
		$state['passes']         = 1;

		$log = new Log( $state['run_id'] );
		$log->open(
			[
				'dir'       => $state['payload_dir'],
				'dry_run'   => $state['mode']['dry_run'],
				'overwrite' => $state['mode']['overwrite'],
				'adopt'     => $state['mode']['adopt'],
				'user'      => $user_id,
				'generated' => $manifest->generated_at(),
			]
		);
		$state['log_file'] = $log->file();

		State::clear_pause_request();
		State::save( $state );
		State::record_run( $state );
		Payload::set_current( $state['payload_dir'], $source );

		return $state;
	}

	/**
	 * Continue a paused run, or start a fresh pass over a finished/failed run
	 * (already-imported records are skipped; failed ones get another chance).
	 */
	public static function resume( array $mode_overrides = [] ): array|WP_Error {
		Map::ensure();
		$state = State::load();
		if ( State::STATUS_IDLE === $state['status'] ) {
			return new WP_Error( 'hk9_import_nothing', __( 'There is no run to resume. Start a new import.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		if ( State::STATUS_RUNNING === $state['status'] && Map::lock_info() ) {
			return new WP_Error( 'hk9_import_busy', __( 'The run is currently executing.', 'heartland-k9s-core' ), [ 'status' => 409 ] );
		}
		if ( ! is_file( trailingslashit( $state['payload_dir'] ) . 'manifest.json' ) ) {
			return new WP_Error( 'hk9_import_payload_gone', __( 'The payload directory of this run no longer exists.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		foreach ( [ 'overwrite', 'adopt', 'batch', 'budget', 'until_step', 'dry_run' ] as $k ) {
			if ( array_key_exists( $k, $mode_overrides ) ) {
				$state['mode'][ $k ] = $mode_overrides[ $k ];
			}
		}
		$state['mode'] = self::mode( $state['mode'] );

		$log = new Log( $state['run_id'] );
		if ( in_array( $state['status'], [ State::STATUS_DONE, State::STATUS_FAILED ], true ) || ! empty( $mode_overrides['restart'] ) ) {
			// New pass from the top: re-validate, re-walk (idempotent), retry failures.
			$state['step']        = State::STEPS[0];
			$state['step_index']  = 0;
			$state['cursor']      = 0;
			$state['step_total']  = 0;
			$state['counts']      = [];
			$state['errors']      = [];
			$state['warnings']    = [];
			$state['failed_keys'] = [];
			$state['prehash']     = [];
			$state['dry_created'] = [];
			$state['dry_adopted'] = [];
			$state['finished_at'] = '';
			$state['passes']      = (int) $state['passes'] + 1;
			$log->info( 'run', '', sprintf( 'Resuming with a new pass (#%d).', $state['passes'] ) );
		} else {
			$log->info( 'run', '', sprintf( 'Resuming at %s cursor %d.', $state['step'], (int) $state['cursor'] ) );
		}
		$state['status'] = State::STATUS_RUNNING;
		State::clear_pause_request();
		State::save( $state );
		State::record_run( $state );
		return $state;
	}

	/**
	 * Execute one tick. Returns the state snapshot (or WP_Error when locked).
	 */
	public static function step( int $budget = 0, ?int $batch = null ): array|WP_Error {
		Map::ensure();
		$token = strtolower( wp_generate_password( 16, false, false ) );
		if ( ! Map::acquire_lock( $token, self::LOCK_TTL ) ) {
			$info = Map::lock_info();
			return new WP_Error(
				'hk9_import_locked',
				sprintf(
					/* translators: %d: seconds */
					__( 'Another process holds the import lock (expires in %d s).', 'heartland-k9s-core' ),
					$info ? max( 0, $info['expires'] - time() ) : 0
				),
				[ 'status' => 423 ]
			);
		}

		$state = State::load();
		try {
			if ( State::STATUS_RUNNING !== $state['status'] ) {
				return $state;
			}
			$budget = $budget > 0 ? $budget : (int) $state['mode']['budget'];
			$batch  = $batch ?? (int) $state['mode']['batch'];

			$manifest = Manifest::load( $state['payload_dir'] );
			if ( is_wp_error( $manifest ) ) {
				$state['status'] = State::STATUS_FAILED;
				$state['errors'][] = [
					'key'     => '',
					'step'    => $state['step'],
					'message' => $manifest->get_error_message(),
					'fatal'   => true,
					'time'    => gmdate( 'c' ),
				];
				return $state;
			}

			$log      = new Log( $state['run_id'] );
			$deadline = microtime( true ) + $budget;
			$ctx      = new Context( $manifest, $state, $log, $deadline, $batch );
			self::refresh_on_tick( $token );

			while ( State::STATUS_RUNNING === $state['status'] ) {
				$name  = (string) $state['step'];
				$class = self::STEP_CLASSES[ $name ] ?? null;
				if ( null === $class ) {
					throw new \RuntimeException( sprintf( 'Unknown step "%s".', $name ) );
				}
				$step = new $class( $ctx );
				$done = $step->run();
				if ( ! $done ) {
					break; // Budget spent or pause requested; cursor saved.
				}
				$log->info( $name, '', 'step complete ' . wp_json_encode( $state['counts'][ $name ] ?? [] ) );
				if ( null !== self::$on_step ) {
					( self::$on_step )( $name, $state );
				}

				$idx = State::step_index( $name );
				if ( $idx + 1 >= count( State::STEPS ) ) {
					$state['status']      = State::STATUS_DONE;
					$state['finished_at'] = gmdate( 'c' );
					$log->info( 'run', '', 'Run complete.' );
					break;
				}
				$state['step']       = State::STEPS[ $idx + 1 ];
				$state['step_index'] = $idx + 1;
				$state['cursor']     = 0;
				$state['step_total'] = 0;

				if ( '' !== (string) $state['mode']['until_step'] && $name === $state['mode']['until_step'] ) {
					$state['status'] = State::STATUS_PAUSED;
					$log->info( 'run', '', sprintf( 'Paused after step %s (--step).', $name ) );
					break;
				}
				if ( State::pause_requested() ) {
					break;
				}
			}

			if ( State::pause_requested() && State::STATUS_RUNNING === $state['status'] ) {
				$state['status'] = State::STATUS_PAUSED;
				$log->info( 'run', '', 'Paused by request.' );
			}
			State::clear_pause_request();
		} catch ( \Throwable $e ) {
			$state['status']      = State::STATUS_FAILED;
			$state['finished_at'] = gmdate( 'c' );
			$already              = array_filter( $state['errors'], static fn( $er ) => ! empty( $er['fatal'] ) );
			// Validate records its own fatals before throwing a RuntimeException; do not duplicate them.
			if ( ! ( $e instanceof \RuntimeException && $already ) ) {
				$state['errors'][] = [
					'key'     => '',
					'step'    => $state['step'],
					'message' => $e->getMessage(),
					'fatal'   => true,
					'time'    => gmdate( 'c' ),
				];
			}
			if ( isset( $log ) ) {
				$log->error( (string) $state['step'], '', 'FATAL ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
			}
		} finally {
			Context::$on_tick = null;
			State::save( $state );
			State::record_run( $state );
			Map::release_lock( $token );
		}
		return $state;
	}

	/**
	 * Keep the lock alive while a long tick runs (refreshed per processed item via Context).
	 */
	private static function refresh_on_tick( string $token ): void {
		Context::$on_tick = static function () use ( $token ): void {
			static $last = 0;
			if ( time() - $last >= 10 ) {
				$last = time();
				Map::refresh_lock( $token, self::LOCK_TTL );
			}
		};
	}

	/**
	 * Run ticks until the run leaves the running state (CLI).
	 *
	 * @param callable|null $on_tick function(array $state): void
	 */
	public static function run_all( ?callable $on_tick = null, int $budget = 30, ?int $batch = null ): array|WP_Error {
		$state = State::load();
		while ( State::STATUS_RUNNING === $state['status'] ) {
			$result = self::step( $budget, $batch );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$state = $result;
			if ( $on_tick ) {
				$on_tick( $state );
			}
		}
		return $state;
	}

	public static function pause(): array {
		$state = State::load();
		if ( State::STATUS_RUNNING !== $state['status'] ) {
			return $state;
		}
		if ( ! Map::lock_info() ) {
			$state['status'] = State::STATUS_PAUSED;
			State::save( $state );
			State::record_run( $state );
			( new Log( $state['run_id'] ) )->info( 'run', '', 'Paused.' );
			return $state;
		}
		State::request_pause();
		$state['pause_pending'] = true;
		return $state;
	}

	/**
	 * Retry: clear per-item failures and start a new pass (idempotent walk).
	 */
	public static function retry(): array|WP_Error {
		$state = State::load();
		if ( State::STATUS_IDLE === $state['status'] ) {
			return new WP_Error( 'hk9_import_nothing', __( 'Nothing to retry.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		return self::resume( [ 'restart' => true ] );
	}

	/**
	 * Reset state (clears the state option, lock and pause request only; the
	 * map table and run history stay so rollback remains possible).
	 */
	public static function reset(): void {
		State::clear();
		Map::clear_lock();
	}

	/**
	 * Snapshot for the admin screen / CLI status.
	 */
	public static function status( bool $full = false ): array {
		Map::ensure();
		$state = State::load();
		if ( ! $full ) {
			$state['errors']   = array_slice( $state['errors'], -100 );
			$state['warnings'] = array_slice( $state['warnings'], -100 );
			unset( $state['prehash'], $state['dry_created'], $state['dry_adopted'] );
		}
		$state['errors_total']   = count( State::load()['errors'] );
		$state['warnings_total'] = count( State::load()['warnings'] );
		$lock                    = Map::lock_info();
		$runs                    = array_reverse( State::runs(), true );
		$payload                 = Payload::current();
		return [
			'state'     => $state,
			'lock'      => $lock ? [ 'expires_in' => max( 0, $lock['expires'] - time() ) ] : null,
			'steps'     => State::STEPS,
			'runs'      => array_slice( $runs, 0, 10, true ),
			'map'       => Map::counts(),
			'payload'   => $payload ? Payload::describe( $payload['dir'] ) + [ 'source' => $payload['source'] ] : null,
			'dev_paths' => Payload::dev_paths(),
			'now'       => gmdate( 'c' ),
		];
	}

	private static function mode( array $mode ): array {
		$until = (string) ( $mode['until_step'] ?? '' );
		if ( '' !== $until && ! in_array( $until, State::STEPS, true ) ) {
			$until = '';
		}
		return [
			'dry_run'    => ! empty( $mode['dry_run'] ),
			'overwrite'  => ! empty( $mode['overwrite'] ),
			'adopt'      => ! empty( $mode['adopt'] ),
			'batch'      => max( 1, min( 1000, (int) ( $mode['batch'] ?? 25 ) ) ),
			'budget'     => max( 2, min( 600, (int) ( $mode['budget'] ?? 20 ) ) ),
			'until_step' => $until,
		];
	}
}
