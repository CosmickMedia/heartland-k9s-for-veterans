<?php
/**
 * Step 8: posts_content — bake tokens into block content (and into meta that
 * carries {{post_url}}) now that hierarchy + reading settings make permalinks
 * final. Block counts are compared before/after baking; unresolved tokens fail
 * the record.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Manifest;
use HK9\Core\Import\Map;
use HK9\Core\Import\PostFields;
use HK9\Core\Import\Reconcile;

defined( 'ABSPATH' ) || exit;

final class PostsContent extends Step {

	public const NAME = 'posts_content';

	protected function items(): array {
		return $this->manifest()->keys( Manifest::POST_TYPES_KEY );
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}
		$sens = Context::is_sensitive( $record );

		$desired = PostFields::desired_content( $record, $this->manifest(), $ctx->tokens );
		if ( is_wp_error( $desired ) ) {
			$ctx->fail( $key, $desired->get_error_message(), $sens );
			return;
		}
		$fields = $desired['fields'];
		if ( ! $fields ) {
			$ctx->result( $key, 'skip', 'no content', $sens );
			return;
		}

		$row = Map::get( $key );
		$id  = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		if ( 0 === $id || ! get_post( $id ) ) {
			if ( $ctx->dry() ) {
				$ctx->result( $key, 'create', sprintf( '%d blocks', $desired['blocks'][1] ), $sens );
				return;
			}
			$ctx->fail( $key, 'Post is missing (earlier step failed).', $sens );
			return;
		}

		$current = PostFields::current( $id, array_keys( $fields ) );
		$plan    = Reconcile::plan( $row, $fields, $current, $ctx->overwrite() );
		$first   = ! isset( $row['field_hashes']['content'] ) && isset( $fields['content'] );
		$action  = $first && 'update' === $plan['action'] ? 'create' : $plan['action'];
		$detail  = $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : sprintf( '%d blocks', $desired['blocks'][1] );
		$ctx->result( $key, $action, $detail, $sens );
		if ( $ctx->dry() ) {
			return;
		}
		if ( $plan['apply'] ) {
			$r = PostFields::apply( $id, $plan['apply'] );
			if ( is_wp_error( $r ) ) {
				$ctx->fail( $key, $r->get_error_message(), $sens );
				return;
			}
		}
		$after = PostFields::current( $id, array_keys( $fields ) );
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, $after ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}
}
