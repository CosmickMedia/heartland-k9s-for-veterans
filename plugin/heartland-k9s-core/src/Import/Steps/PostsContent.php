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
use HK9\Core\Import\Hash;
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
			$this->keep_unpublished( $key, $sens );
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
			$ctx->fail( $key, __( 'Post is missing (earlier step failed).', 'heartland-k9s-core' ), $sens );
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
				$this->keep_unpublished( $key, $sens );
				return;
			}
		}
		$after = PostFields::current( $id, array_keys( $fields ) );
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, $after ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}

	/**
	 * Safety net behind the hierarchy pre-flight: a post published by posts_hierarchy
	 * whose first content import just failed must not stay public with empty
	 * content. It goes back to draft and its status hash is re-recorded, so the next
	 * pass sees an untouched value and re-applies the payload status once the
	 * content bakes.
	 */
	private function keep_unpublished( string $key, bool $sens ): void {
		$ctx = $this->ctx;
		if ( $ctx->dry() ) {
			return;
		}
		$row  = Map::get( $key );
		$id   = $row && Map::STATUS_ACTIVE === $row['status'] ? (int) $row['object_id'] : 0;
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || 'publish' !== $post->post_status || '' !== trim( (string) $post->post_content ) ) {
			return;
		}
		if ( isset( $row['field_hashes']['content'] ) ) {
			return; // Content was imported before; an editor may have emptied it on purpose.
		}
		$r = PostFields::apply( $id, [ 'status' => 'draft' ] );
		if ( is_wp_error( $r ) ) {
			$ctx->warn( $key, __( 'Could not revert the empty post to draft: ', 'heartland-k9s-core' ) . $r->get_error_message(), $sens );
			return;
		}
		$hashes = $row['field_hashes'];
		if ( isset( $hashes['status'] ) ) {
			$hashes['status']['db'] = Hash::of( 'draft' );
			Map::overwrite( $key, $hashes, $row['before_data'] );
		}
		$ctx->warn( $key, sprintf( /* translators: %d: post ID */ __( 'Post #%d reverted to draft: its content could not be imported. It is published again once the content bakes.', 'heartland-k9s-core' ), $id ), $sens );
	}
}
