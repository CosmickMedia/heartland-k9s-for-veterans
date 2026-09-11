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
			$id = $this->adopt_core_placeholder( $key, $record, $type );
		}
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

	/**
	 * A fresh WordPress install ships an untouched draft "Privacy Policy" page (slug privacy-policy,
	 * referenced by the wp_page_for_privacy_policy option). Creating a second page would push the
	 * imported one to privacy-policy-2, so adopt the core placeholder instead when it is still pristine
	 * (draft, never edited, not owned by another import record).
	 *
	 * @return int Adopted post id or 0.
	 */
	private function adopt_core_placeholder( string $key, array $record, string $type ): int {
		if ( 'page' !== $type ) {
			return 0;
		}
		$slug = sanitize_title( (string) ( $record['slug'] ?? '' ) );
		if ( 'privacy-policy' !== $slug ) {
			return 0;
		}
		$core_id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $core_id <= 0 ) {
			return 0;
		}
		$post = get_post( $core_id );
		if ( ! $post || 'page' !== $post->post_type || 'draft' !== $post->post_status || $slug !== $post->post_name ) {
			return 0;
		}
		if ( '' !== (string) get_post_meta( $core_id, '_hk9_source_key', true ) ) {
			return 0;
		}
		// Pristine = never edited (created and modified at the same time) — an edited draft is someone's work.
		if ( $post->post_modified_gmt !== $post->post_date_gmt ) {
			return 0;
		}
		update_post_meta( $core_id, '_hk9_source_key', $key );
		update_post_meta( $core_id, '_hk9_import_run', $this->ctx->run_id );
		update_post_meta( $core_id, '_hk9_import_pending', 1 );
		$this->ctx->info( $key, sprintf( 'Adopted the core placeholder Privacy Policy page #%d instead of creating privacy-policy-2.', $core_id ) );
		return $core_id;
	}
}
