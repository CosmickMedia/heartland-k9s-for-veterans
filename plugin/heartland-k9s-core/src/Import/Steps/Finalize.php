<?php
/**
 * Step 12: finalize — attach media to their parent posts, report orphans
 * (map rows no longer in the payload; never deleted), soft-flush rewrite
 * rules, and delete an uploaded (web-served) payload copy after a clean run.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Map;
use HK9\Core\Import\Payload;
use HK9\Core\Import\Reconcile;

defined( 'ABSPATH' ) || exit;

final class Finalize extends Step {

	public const NAME = 'finalize';

	protected function items(): array {
		$items = [];
		foreach ( $this->manifest()->keys( 'attachment' ) as $k ) {
			$record = $this->record( $k );
			if ( $record && ! empty( $record['parent'] ) ) {
				$items[] = 'attach:' . $k;
			}
		}
		$items[] = 'orphans';
		$items[] = 'cleanup';
		return $items;
	}

	protected function process( string $item ): void {
		if ( str_starts_with( $item, 'attach:' ) ) {
			$this->attach( substr( $item, 7 ) );
			return;
		}
		if ( 'orphans' === $item ) {
			$this->orphans();
			return;
		}
		$this->cleanup();
	}

	private function attach( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $ctx->is_failed( $key ) ) {
			return;
		}
		$sens = Context::is_sensitive( $record );
		$row  = Map::get( $key );
		$id   = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		$pid  = $ctx->tokens->bake( (string) $record['parent'] );
		if ( is_wp_error( $pid ) ) {
			$ctx->fail( $key, 'Parent: ' . $pid->get_error_message(), $sens );
			return;
		}
		if ( 0 === $id ) {
			if ( ! $ctx->dry() ) {
				$ctx->fail( $key, 'Attachment missing; cannot attach to its parent.', $sens );
			}
			return;
		}
		// A shared attachment belongs to the record that owns it: a secondary record never re-parents it.
		$owner = Map::owner( 'attachment', $id );
		if ( $owner && $owner['source_key'] !== $key ) {
			$ctx->info( $key, sprintf( 'parent not applied: attachment #%d is owned by %s', $id, (string) $owner['source_key'] ), $sens || $this->is_sensitive_key( (string) $owner['source_key'] ) );
			return;
		}
		$desired = [ 'parent' => (int) $pid ];
		$current = [ 'parent' => (int) ( get_post( $id )->post_parent ?? 0 ) ];
		$plan    = Reconcile::plan( $row, $desired, $current, $ctx->overwrite() );
		if ( 'skip' !== $plan['action'] ) {
			$ctx->result( $key, $plan['action'], 'parent #' . (int) $pid, $sens );
		}
		if ( $ctx->dry() || ! $plan['apply'] ) {
			return;
		}
		wp_update_post(
			[
				'ID'          => $id,
				'post_parent' => (int) $pid,
			]
		);
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, [ 'parent' => (int) ( get_post( $id )->post_parent ?? 0 ) ] ), $plan['before'] );
	}

	private function is_sensitive_key( string $key ): bool {
		$record = $this->record( $key );
		return $record ? Context::is_sensitive( $record ) : false;
	}

	private function orphans(): void {
		$ctx      = $this->ctx;
		$manifest = $this->manifest();
		$orphans  = [];
		foreach ( Map::all_keys() as $key ) {
			if ( ! $manifest->has( $key ) && ! $this->is_menu_item_of_manifest( $key ) ) {
				$orphans[] = $key;
			}
		}
		if ( $orphans ) {
			$ctx->warn( '', sprintf( '%d mapped record(s) are no longer in the payload and were left untouched: %s', count( $orphans ), implode( ', ', array_slice( $orphans, 0, 20 ) ) . ( count( $orphans ) > 20 ? ', …' : '' ) ) );
		}
	}

	private function is_menu_item_of_manifest( string $key ): bool {
		foreach ( $this->manifest()->keys( 'menu' ) as $mk ) {
			foreach ( (array) ( $this->record( $mk )['items'] ?? [] ) as $item ) {
				if ( is_array( $item ) && ( $item['key'] ?? '' ) === $key ) {
					return true;
				}
			}
		}
		return false;
	}

	private function cleanup(): void {
		$ctx = $this->ctx;
		if ( $ctx->dry() ) {
			$ctx->info( '', 'Dry run complete; nothing was written.' );
			return;
		}
		flush_rewrite_rules( false );

		$errors = count( array_filter( $ctx->state['errors'], static fn( $e ) => empty( $e['fatal'] ) ) );
		$dir    = (string) $ctx->state['payload_dir'];
		if ( 0 === $errors && Payload::is_uploaded_dir( $dir ) ) {
			if ( Payload::remove_uploaded( $dir ) ) {
				$ctx->info( '', 'Deleted the uploaded payload copy from uploads/.' );
				$ctx->state['payload_deleted'] = true;
				delete_option( \HK9\Core\Import\State::PAYLOAD_OPTION );
			} else {
				$ctx->warn( '', 'Could not delete the uploaded payload copy; remove it manually from uploads/.' );
			}
		} elseif ( $errors > 0 ) {
			$ctx->info( '', sprintf( 'Run finished with %d record error(s); payload kept so you can fix and resume.', $errors ) );
		} else {
			// A server path (CLI, or a bind-mounted dev directory that may be read-only) is never deleted.
			$ctx->info( '', sprintf( 'Payload directory %s was supplied as a server path and is left in place; remove it yourself if it sits inside the web root.', $dir ) );
		}
	}
}
