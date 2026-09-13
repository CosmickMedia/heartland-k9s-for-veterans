<?php
/**
 * Step 2: terms (adopt by slug via term_exists, else wp_insert_term).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Map;
use HK9\Core\Import\Reconcile;

defined( 'ABSPATH' ) || exit;

final class Terms extends Step {

	public const NAME = 'terms';

	protected function items(): array {
		return $this->manifest()->keys( 'term' );
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}
		$tax  = (string) $record['taxonomy'];
		$slug = sanitize_title( (string) $record['slug'] );
		$sens = Context::is_sensitive( $record );

		if ( ! taxonomy_exists( $tax ) ) {
			$ctx->fail( $key, sprintf( 'Taxonomy "%s" is not registered.', $tax ), $sens );
			return;
		}

		$desired = [
			'name'        => (string) $record['name'],
			'description' => (string) ( $record['description'] ?? '' ),
		];
		$parent  = 0;
		if ( ! empty( $record['parent'] ) && is_taxonomy_hierarchical( $tax ) ) {
			$p = $ctx->tokens->bake( (string) $record['parent'] );
			if ( is_wp_error( $p ) ) {
				$ctx->fail( $key, $p->get_error_message(), $sens );
				return;
			}
			$parent            = (int) $p;
			$desired['parent'] = $parent;
		}

		$row     = Map::get( $key );
		$term_id = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		$adopted = false;
		if ( $term_id > 0 && ! term_exists( $term_id, $tax ) ) {
			$term_id = 0; // Deleted since; recreate.
			$row     = null;
		}
		if ( 0 === $term_id ) {
			$existing = term_exists( $slug, $tax );
			if ( is_array( $existing ) && ! empty( $existing['term_id'] ) ) {
				$term_id = (int) $existing['term_id'];
				$adopted = true;
			}
		}

		if ( 0 === $term_id ) {
			$ctx->result( $key, 'create', $tax . ':' . $slug, $sens );
			if ( $ctx->dry() ) {
				return;
			}
			Map::reserve( $key, 'term', $ctx->run_id );
			$args = [ 'slug' => $slug, 'description' => $desired['description'] ];
			if ( $parent ) {
				$args['parent'] = $parent;
			}
			$result = wp_insert_term( wp_slash( $desired['name'] ), $tax, wp_slash( $args ) ); // Core unslashes name/description.
			if ( is_wp_error( $result ) ) {
				$existing = term_exists( $slug, $tax );
				if ( is_array( $existing ) ) {
					$term_id = (int) $existing['term_id'];
					$adopted = true;
				} else {
					Map::delete( $key );
					$ctx->fail( $key, $result->get_error_message(), $sens );
					return;
				}
			} else {
				$term_id = (int) $result['term_id'];
				update_term_meta( $term_id, '_hk9_source_key', $key );
				update_term_meta( $term_id, '_hk9_import_run', $ctx->run_id );
			}
			$after = $this->current( $term_id, $tax );
			Map::bind(
				$key,
				'term',
				$term_id,
				$ctx->run_id,
				[
					'created_by_run' => $adopted ? null : $ctx->run_id,
					'adopted_by_run' => $adopted ? $ctx->run_id : null,
					'payload_hash'   => $this->manifest()->payload_hash( $record ),
					'field_hashes'   => Reconcile::fresh( $desired, $after ),
				]
			);
			return;
		}

		// Existing (mapped or adopted): reconcile fields.
		$current = $this->current( $term_id, $tax );
		$plan    = Reconcile::plan( $adopted && ! $row ? [ 'object_id' => $term_id, 'field_hashes' => [] ] : $row, $desired, $current, $ctx->overwrite() );
		if ( $adopted && ! $row ) {
			$ctx->adopted( $key, $term_id, sprintf( '%s:%s by slug%s%s', $tax, $slug, $plan['apply'] ? ' (fields applied: ' . implode( ',', array_keys( $plan['apply'] ) ) . ')' : '', $ctx->dry() ? ' (dry run)' : '' ), $sens );
		} else {
			$ctx->result( $key, $plan['action'], $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : '', $sens );
		}
		if ( $ctx->dry() ) {
			return;
		}
		if ( ! $row ) {
			Map::bind( $key, 'term', $term_id, $ctx->run_id, [ 'created_by_run' => null, 'adopted_by_run' => $ctx->run_id ] );
			update_term_meta( $term_id, '_hk9_source_key', $key );
		}
		if ( $plan['apply'] ) {
			$args = [];
			foreach ( $plan['apply'] as $f => $v ) {
				$args[ $f ] = $v;
			}
			$r = wp_update_term( $term_id, $tax, wp_slash( $args ) );
			if ( is_wp_error( $r ) ) {
				$ctx->fail( $key, $r->get_error_message(), $sens );
				return;
			}
		}
		$after = $this->current( $term_id, $tax );
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, $after ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}

	public static function current( int $term_id, string $tax ): array {
		$term = get_term( $term_id, $tax );
		if ( ! $term || is_wp_error( $term ) ) {
			return [];
		}
		return [
			'name'        => (string) $term->name,
			'description' => (string) $term->description,
			'parent'      => (int) $term->parent,
		];
	}
}
