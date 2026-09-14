<?php
/**
 * Per-tick import context shared by the steps.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Context {

	public Manifest $manifest;
	public Tokens $tokens;
	public Log $log;
	/** @var array The run state (mutated in place; Runner persists it). */
	public array $state;
	public string $run_id;
	public string $step = '';

	/** @var callable|null Invoked after every processed item (lock heartbeat). */
	public static $on_tick = null;

	private float $deadline;
	private int $batch;
	private int $processed = 0;
	private bool $yield_requested = false;

	public function __construct( Manifest $manifest, array &$state, Log $log, float $deadline, int $batch ) {
		$this->manifest = $manifest;
		$this->state    = &$state;
		$this->log      = $log;
		$this->run_id   = (string) $state['run_id'];
		$this->deadline = $deadline;
		$this->batch    = max( 1, $batch );
		$this->tokens   = new Tokens( $manifest, $this->dry(), $state['failed_keys'] );
	}

	public function dry(): bool {
		return ! empty( $this->state['mode']['dry_run'] );
	}

	public function overwrite(): bool {
		return ! empty( $this->state['mode']['overwrite'] );
	}

	/**
	 * "Existing site" mode: bind payload records to matching pre-existing
	 * pages/attachments (by live id or slug) instead of creating duplicates.
	 */
	public function adopt(): bool {
		return ! empty( $this->state['mode']['adopt'] );
	}

	public function user_login(): string {
		$user = get_userdata( (int) $this->state['user_id'] );
		return $user ? $user->user_login : 'cli';
	}

	/* --------------------------------------------------------------- budget */

	public function tick(): void {
		++$this->processed;
		if ( null !== self::$on_tick ) {
			( self::$on_tick )();
		}
	}

	public function request_yield(): void {
		$this->yield_requested = true;
	}

	public function should_yield(): bool {
		if ( $this->yield_requested ) {
			return true;
		}
		if ( microtime( true ) >= $this->deadline ) {
			return true;
		}
		if ( $this->processed >= $this->batch ) {
			return true;
		}
		if ( 0 === $this->processed % 5 && State::pause_requested() ) {
			$this->yield_requested = true;
			return true;
		}
		return false;
	}

	public function reset_batch(): void {
		$this->processed = 0;
	}

	/* --------------------------------------------------------------- counts */

	public function count( string $kind, int $n = 1 ): void {
		$step = $this->step;
		if ( ! isset( $this->state['counts'][ $step ] ) ) {
			$this->state['counts'][ $step ] = [
				'create'   => 0,
				'adopt'    => 0,
				'update'   => 0,
				'skip'     => 0,
				'conflict' => 0,
				'fail'     => 0,
			];
		}
		$this->state['counts'][ $step ][ $kind ] = ( $this->state['counts'][ $step ][ $kind ] ?? 0 ) + $n;
	}

	public function add_bytes( int $bytes ): void {
		$this->state['bytes_copied'] = (int) $this->state['bytes_copied'] + $bytes;
	}

	/* --------------------------------------------------------------- errors */

	/**
	 * Per-record failure: counted, logged, and remembered so dependants fail cleanly.
	 */
	public function fail( string $key, string $message, bool $sensitive = false ): void {
		$this->count( 'fail' );
		$this->state['failed_keys'][ $key ] = $message;
		$this->tokens->set_failed( $this->state['failed_keys'] );
		$this->state['errors'][] = [
			'key'     => $key,
			'step'    => $this->step,
			'message' => $sensitive ? __( 'Failed (details withheld for a sensitive record; see the log hash).', 'heartland-k9s-core' ) : $message,
			'fatal'   => false,
			'time'    => gmdate( 'c' ),
		];
		$this->log->error( $this->step, $key, $message, $sensitive );
	}

	public function fatal( string $message ): void {
		$this->state['errors'][] = [
			'key'     => '',
			'step'    => $this->step,
			'message' => $message,
			'fatal'   => true,
			'time'    => gmdate( 'c' ),
		];
		$this->log->error( $this->step, '', 'FATAL ' . $message );
	}

	public function warn( string $key, string $message, bool $sensitive = false ): void {
		$this->state['warnings'][] = [
			'key'     => $key,
			'step'    => $this->step,
			'message' => $sensitive ? __( 'Warning (details withheld for a sensitive record).', 'heartland-k9s-core' ) : $message,
			'time'    => gmdate( 'c' ),
		];
		$this->log->warn( $this->step, $key, $message, $sensitive );
	}

	public function info( string $key, string $message, bool $sensitive = false ): void {
		$this->log->info( $this->step, $key, $message, $sensitive );
	}

	public function is_failed( string $key ): bool {
		return isset( $this->state['failed_keys'][ $key ] );
	}

	/**
	 * Leave a record alone for the rest of this pass (counted as a skip, not a
	 * failure): e.g. its mapped post was trashed by an editor. Tokens pointing
	 * at it keep resolving through its map row.
	 */
	public function skip_record( string $key, string $reason, bool $sensitive = false ): void {
		$this->state['skipped_keys'][ $key ] = $reason;
		$this->result( $key, 'skip', $reason, $sensitive );
	}

	public function is_skipped( string $key ): bool {
		return isset( $this->state['skipped_keys'][ $key ] );
	}

	public static function is_sensitive( array $record ): bool {
		return ! empty( $record['sensitive'] );
	}

	/**
	 * Record a result for a key ('create' | 'adopt' | 'update' | 'skip' | 'conflict').
	 */
	public function result( string $key, string $kind, string $detail = '', bool $sensitive = false ): void {
		$this->count( $kind );
		$this->log->info( $this->step, $key, strtoupper( $kind ) . ( '' !== $detail ? ' ' . $detail : '' ), $sensitive );
	}

	/**
	 * Record an adoption (a pre-existing object bound to a payload key). The
	 * object id is always logged — "key -> #id" is the audit trail of the
	 * existing-site migration — while every other detail of a sensitive record
	 * is withheld as usual.
	 */
	public function adopted( string $key, int $id, string $detail = '', bool $sensitive = false ): void {
		$this->count( 'adopt' );
		$who = $sensitive ? 'sensitive#' . Hash::short( $key ) : $key;
		$this->log->write( 'info', $this->step, $who, 'ADOPT #' . $id . ( ! $sensitive && '' !== $detail ? ' ' . $detail : '' ) );
	}
}
