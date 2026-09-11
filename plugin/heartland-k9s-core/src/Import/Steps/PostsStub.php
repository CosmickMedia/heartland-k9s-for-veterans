<?php
/**
 * Step 5: posts_stub — create every post-like record as a DRAFT with no parent
 * (slug uniqueness is skipped for drafts, so siblings under different parents
 * never get spurious -2 suffixes, and nothing half-imported is public).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Manifest;
use HK9\Core\Import\Map;

defined( 'ABSPATH' ) || exit;

final class PostsStub extends Step {

	public const NAME = 'posts_stub';

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
		$type = (string) $record['type'];

		$row = Map::get( $key );
		$id  = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_type !== $type || 'trash' === $post->post_status ) {
				if ( $post && 'trash' === $post->post_status ) {
					$ctx->warn( $key, sprintf( 'Mapped post #%d is in the trash; a new one will be created.', $id ), $sens );
				}
				$id = 0;
			}
		}
		if ( $id > 0 ) {
			$ctx->result( $key, 'skip', 'exists #' . $id, $sens );
			return;
		}

		$ctx->result( $key, 'create', $type . ' ' . (string) ( $record['slug'] ?? '' ), $sens );
		if ( $ctx->dry() ) {
			return;
		}

		Map::reserve( $key, $type, $ctx->run_id );

		$id = Map::find_orphan_post( $key );
		if ( 0 === $id ) {
			$args = [
				'post_type'    => $type,
				'post_title'   => (string) ( $record['title'] ?? '' ),
				'post_name'    => sanitize_title( (string) ( $record['slug'] ?? '' ) ),
				'post_status'  => 'draft',
				'post_content' => '',
				'meta_input'   => [
					'_hk9_source_key'     => $key,
					'_hk9_import_run'     => $ctx->run_id,
					'_hk9_import_pending' => 1,
				],
			];
			$date = Manifest::date_pair( $record['date'] ?? null );
			if ( is_array( $date ) ) {
				$args['post_date']     = $date['local'];
				$args['post_date_gmt'] = $date['gmt'];
			}
			$result = wp_insert_post( wp_slash( $args ), true );
			if ( is_wp_error( $result ) || 0 === (int) $result ) {
				Map::delete( $key );
				$ctx->fail( $key, 'Could not create the draft stub: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'unknown error' ), $sens );
				return;
			}
			$id = (int) $result;
		}

		Map::bind(
			$key,
			$type,
			$id,
			$ctx->run_id,
			[
				'created_by_run' => $ctx->run_id,
				'payload_hash'   => $this->manifest()->payload_hash( $record ),
			]
		);
	}
}
