<?php
/**
 * Step 10: options — reconciled per LEAF (dot path) for the default deep merge,
 * per top-level key for merge:replace, with pre-images kept for rollback.
 *
 * Per-leaf reconciliation means an editor changing a sibling key the payload
 * never sets (e.g. contact.hours) is not reported as a conflict on every later
 * run, and a single unresolvable link token (a page whose import failed) only
 * drops that leaf with a warning instead of failing the whole settings record.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Map;
use HK9\Core\Import\Reconcile;

defined( 'ABSPATH' ) || exit;

final class Options extends Step {

	public const NAME = 'options';

	/** Separator inside leaf field names (`branding.wordmark_line1`). */
	public const SEP = '.';

	protected function items(): array {
		return $this->manifest()->keys( 'option' );
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}
		$name  = (string) $record['name'];
		$merge = 'replace' === ( $record['merge'] ?? 'deep' ) ? 'replace' : 'deep';

		if ( ! Validate::option_allowed( $name ) ) {
			$ctx->fail( $key, sprintf( /* translators: %s: option name */ __( 'Option "%s" is not importable.', 'heartland-k9s-core' ), $name ) );
			return;
		}

		$value       = $record['value'];
		$current_raw = get_option( $name, null );
		$grouped     = is_array( $value ) && ! array_is_list( $value );
		$current     = [];
		$desired     = [];

		if ( ! $grouped ) {
			// Scalar / list option: one field.
			$baked = $ctx->tokens->bake( $value );
			if ( is_wp_error( $baked ) ) {
				$ctx->fail( $key, $baked->get_error_message() );
				return;
			}
			$desired['value'] = $baked;
			$current['value'] = $current_raw;
		} else {
			$base   = is_array( $current_raw ) ? $current_raw : [];
			$leaves = self::leaves( $value );
			$baked  = [];
			foreach ( $leaves as $path => $leaf ) {
				$b = $ctx->tokens->bake( $leaf );
				if ( is_wp_error( $b ) ) {
					// (M) Warn + drop the leaf; the rest of the settings still import.
					$ctx->warn( $key, sprintf( /* translators: 1: setting path, 2: reason */ __( 'Dropped %1$s: %2$s', 'heartland-k9s-core' ), $path, $b->get_error_message() ) );
					continue;
				}
				$baked[ $path ] = $b;
			}
			if ( 'deep' === $merge ) {
				foreach ( $baked as $path => $b ) {
					$desired[ $path ] = $b;
					$current[ $path ] = self::path_get( $base, $path );
				}
			} else {
				// Replace whole top-level groups, rebuilt from the surviving leaves.
				$groups = [];
				foreach ( $baked as $path => $b ) {
					$top = explode( self::SEP, (string) $path, 2 )[0];
					if ( (string) $path === $top ) {
						$groups[ $top ] = $b; // The group itself is a leaf (scalar, list or empty array).
						continue;
					}
					if ( ! isset( $groups[ $top ] ) || ! is_array( $groups[ $top ] ) ) {
						$groups[ $top ] = [];
					}
					self::path_set( $groups[ $top ], (string) substr( (string) $path, strlen( $top ) + 1 ), $b );
				}
				foreach ( $groups as $top => $v ) {
					$desired[ $top ] = $v;
					$current[ $top ] = $base[ $top ] ?? null;
				}
			}
		}

		$row  = Map::get( $key );
		$plan = Reconcile::plan( $row ?: [ 'object_id' => 0, 'field_hashes' => [] ], $desired, $current, $ctx->overwrite() );
		$ctx->result( $key, $plan['action'], $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : implode( ',', array_keys( $plan['apply'] ) ) );
		if ( $ctx->dry() ) {
			return;
		}
		if ( ! $row ) {
			Map::bind( $key, 'option', 0, $ctx->run_id, [ 'created_by_run' => null ] );
		}
		if ( $plan['apply'] ) {
			if ( $grouped ) {
				$new = is_array( $current_raw ) ? $current_raw : [];
				foreach ( $plan['apply'] as $path => $v ) {
					self::path_set( $new, (string) $path, $v );
				}
			} else {
				$new = $plan['apply']['value'];
			}
			update_option( $name, $new );
		}
		$after_raw = get_option( $name, null );
		$after     = [];
		if ( $grouped ) {
			$after_arr = is_array( $after_raw ) ? $after_raw : [];
			foreach ( array_keys( $desired ) as $path ) {
				$after[ $path ] = self::path_get( $after_arr, (string) $path );
			}
		} else {
			$after['value'] = $after_raw;
		}
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, $after ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}

	/* -------------------------------------------------------------- leaves */

	/**
	 * Flatten an associative structure into dot-path => leaf. Scalars, lists and
	 * empty arrays are leaves (deep-merge replaces them whole); associative arrays
	 * recurse.
	 *
	 * @return array<string, mixed>
	 */
	public static function leaves( array $value, string $prefix = '' ): array {
		$out = [];
		foreach ( $value as $k => $v ) {
			$path = '' === $prefix ? (string) $k : $prefix . self::SEP . $k;
			if ( is_array( $v ) && $v && ! array_is_list( $v ) ) {
				$out += self::leaves( $v, $path );
			} else {
				$out[ $path ] = $v;
			}
		}
		return $out;
	}

	/** Value at a dot path (null when absent). */
	public static function path_get( array $data, string $path ): mixed {
		$cur = $data;
		foreach ( explode( self::SEP, $path ) as $seg ) {
			if ( ! is_array( $cur ) || ! array_key_exists( $seg, $cur ) ) {
				return null;
			}
			$cur = $cur[ $seg ];
		}
		return $cur;
	}

	/** Set a dot path, creating intermediate arrays (a scalar in the way is replaced). */
	public static function path_set( array &$data, string $path, mixed $value ): void {
		$segs = explode( self::SEP, $path );
		$last = array_pop( $segs );
		$cur  = &$data;
		foreach ( $segs as $seg ) {
			if ( ! isset( $cur[ $seg ] ) || ! is_array( $cur[ $seg ] ) ) {
				$cur[ $seg ] = [];
			}
			$cur = &$cur[ $seg ];
		}
		$cur[ $last ] = $value;
	}

	/** Remove a dot path (no-op when absent); ancestors left empty are pruned too. */
	public static function path_unset( array &$data, string $path ): void {
		$segs = explode( self::SEP, $path );
		$last = array_pop( $segs );
		if ( ! $segs ) {
			unset( $data[ $last ] );
			return;
		}
		$head = array_shift( $segs );
		if ( ! isset( $data[ $head ] ) || ! is_array( $data[ $head ] ) ) {
			return;
		}
		self::path_unset( $data[ $head ], implode( self::SEP, array_merge( $segs, [ $last ] ) ) );
		if ( [] === $data[ $head ] ) {
			unset( $data[ $head ] );
		}
	}

	/**
	 * Recursive merge for associative arrays; lists and scalars are replaced.
	 */
	public static function deep_merge( array $base, array $over ): array {
		if ( array_is_list( $over ) ) {
			return $over;
		}
		foreach ( $over as $k => $v ) {
			if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) && ! array_is_list( $v ) ) {
				$base[ $k ] = self::deep_merge( $base[ $k ], $v );
			} else {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}
}
