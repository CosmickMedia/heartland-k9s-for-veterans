<?php
/**
 * Step 10: options — deep-merge (default) or replace, per top-level key, with
 * pre-images kept for rollback.
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
			$ctx->fail( $key, sprintf( 'Option "%s" is not importable.', $name ) );
			return;
		}

		$value = $ctx->tokens->bake( $record['value'] );
		if ( is_wp_error( $value ) ) {
			$ctx->fail( $key, $value->get_error_message() );
			return;
		}

		$current_raw = get_option( $name, null );
		$grouped     = is_array( $value ) && ! array_is_list( $value );
		$current     = $grouped ? ( is_array( $current_raw ) ? $current_raw : [] ) : [ 'value' => $current_raw ];

		$desired = [];
		if ( $grouped ) {
			foreach ( $value as $group => $v ) {
				$cur               = $current[ $group ] ?? null;
				$desired[ $group ] = ( 'deep' === $merge && is_array( $cur ) && is_array( $v ) ) ? self::deep_merge( $cur, $v ) : $v;
			}
		} else {
			$desired['value'] = $value;
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
				foreach ( $plan['apply'] as $group => $v ) {
					$new[ $group ] = $v;
				}
			} else {
				$new = $plan['apply']['value'];
			}
			update_option( $name, $new );
		}
		$after_raw = get_option( $name, null );
		$after     = $grouped ? ( is_array( $after_raw ) ? $after_raw : [] ) : [ 'value' => $after_raw ];
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, $after ), $plan['before'], $this->manifest()->payload_hash( $record ) );
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
