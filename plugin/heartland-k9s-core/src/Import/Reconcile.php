<?php
/**
 * Per-field idempotency + conflict algorithm.
 *
 * For every field the map row stores {db: hash(value read back from the DB
 * after our last write), src: hash(resolved payload value at that time)}.
 *
 *   - no row                         -> CREATE (apply everything)
 *   - db hash unchanged              -> field untouched by editors: apply only if the payload changed
 *   - db hash changed                -> an editor changed it: CONFLICT (skip) unless overwrite
 *   - field never recorded           -> apply if it differs (payload wins the first time; pre-image kept)
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Reconcile {

	/**
	 * @param array|null $row       Map row (decoded) or null when the object does not exist yet.
	 * @param array      $desired   field => resolved payload value.
	 * @param array      $current   field => value currently in the database (only used when $row).
	 * @param bool       $overwrite Resolve conflicts in favour of the payload.
	 *
	 * @return array{action:string,apply:array,conflicts:string[],overwritten:string[],hashes:array,before:array}
	 */
	public static function plan( ?array $row, array $desired, array $current, bool $overwrite ): array {
		$apply       = [];
		$conflicts   = [];
		$overwritten = [];
		$hashes      = [];
		$before      = [];

		if ( null === $row ) {
			foreach ( $desired as $f => $v ) {
				$apply[ $f ]  = $v;
				$hashes[ $f ] = [ 'src' => Hash::of( $v ) ];
			}
			return [
				'action'      => 'create',
				'apply'       => $apply,
				'conflicts'   => [],
				'overwritten' => [],
				'hashes'      => $hashes,
				'before'      => [],
			];
		}

		$stored = $row['field_hashes'] ?? [];
		foreach ( $desired as $f => $v ) {
			$src_now = Hash::of( $v );
			$db_now  = Hash::of( $current[ $f ] ?? null );
			$s       = $stored[ $f ] ?? null;

			if ( null === $s || ! isset( $s['db'] ) ) {
				// Never recorded (adopted object or new payload field).
				if ( $src_now !== $db_now ) {
					$apply[ $f ]  = $v;
					$before[ $f ] = $current[ $f ] ?? null;
				}
				$hashes[ $f ] = [
					'db'  => $db_now,
					'src' => $src_now,
				];
				continue;
			}

			if ( $db_now === $s['db'] ) {
				// Untouched since our last write.
				if ( $src_now !== ( $s['src'] ?? null ) && $src_now !== $db_now ) {
					$apply[ $f ]  = $v;
					$before[ $f ] = $current[ $f ] ?? null;
				}
				$hashes[ $f ] = [
					'db'  => $db_now,
					'src' => $src_now,
				];
				continue;
			}

			// Editor changed it.
			if ( $overwrite ) {
				if ( $src_now !== $db_now ) {
					$apply[ $f ]   = $v;
					$before[ $f ]  = $current[ $f ] ?? null;
					$overwritten[] = $f;
				}
				$hashes[ $f ] = [
					'db'  => $db_now,
					'src' => $src_now,
				];
			} else {
				$conflicts[] = $f;
				// Keep the stored hashes so the conflict persists until resolved.
				$hashes[ $f ] = $s;
			}
		}

		$action = $apply ? 'update' : ( $conflicts ? 'conflict' : 'skip' );

		return [
			'action'      => $action,
			'apply'       => $apply,
			'conflicts'   => $conflicts,
			'overwritten' => $overwritten,
			'hashes'      => $hashes,
			'before'      => $before,
		];
	}

	/**
	 * Hashes for a freshly created object: src from the payload, db from the read-back.
	 */
	public static function fresh( array $desired, array $after ): array {
		$out = [];
		foreach ( $desired as $f => $v ) {
			$out[ $f ] = [
				'db'  => Hash::of( $after[ $f ] ?? null ),
				'src' => Hash::of( $v ),
			];
		}
		return $out;
	}

	/**
	 * After writing, replace the 'db' hashes of the fields that were APPLIED with
	 * hashes of the values re-read from the database. Fields that were skipped as
	 * conflicts keep their stored hashes, so the conflict persists until resolved.
	 */
	public static function readback( array $plan, array $current_after ): array {
		$hashes = $plan['hashes'];
		foreach ( array_keys( $plan['apply'] ) as $f ) {
			if ( array_key_exists( $f, $current_after ) ) {
				$hashes[ $f ]['db'] = Hash::of( $current_after[ $f ] );
			}
		}
		return $hashes;
	}

	/**
	 * Are all recorded db hashes still matching the live values? (Rollback safety check.)
	 *
	 * @return string[] Fields that differ.
	 */
	public static function modified_fields( array $row, array $current ): array {
		$diff = [];
		foreach ( $row['field_hashes'] ?? [] as $f => $h ) {
			if ( ! isset( $h['db'] ) || ! array_key_exists( $f, $current ) ) {
				continue;
			}
			if ( Hash::of( $current[ $f ] ) !== $h['db'] ) {
				$diff[] = (string) $f;
			}
		}
		return $diff;
	}
}
