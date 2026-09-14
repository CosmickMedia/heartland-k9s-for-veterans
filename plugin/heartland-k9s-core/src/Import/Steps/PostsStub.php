<?php
/**
 * Step 5: posts_stub — create every post-like record as a DRAFT with no parent
 * (slug uniqueness is skipped for drafts, so siblings under different parents
 * never get spurious -2 suffixes, and nothing half-imported is public).
 *
 * In "existing site" mode (mode.adopt) an unmapped record is first matched
 * against the pages already on the site (Adopt::post_candidate: live id, then
 * slug/path). A match is bound as ADOPTED (created_by_run = NULL, so rollback
 * restores its pre-image instead of deleting it) and flagged pending, so the
 * hierarchy/content passes apply the payload unconditionally on the first bind
 * (the old builder content is the pre-migration state, not an edit). A page
 * matched by a BarKode record is converted in place (post_type) keeping its id
 * and slug; the pre-image (post_type, template) is recorded for rollback. A
 * record that already IS the record type (converted by an earlier run whose
 * map row is gone) is re-adopted as it is, so a lost binding never produces a
 * "<slug>-2" duplicate.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Adopt;
use HK9\Core\Import\Context;
use HK9\Core\Import\Manifest;
use HK9\Core\Import\Map;
use HK9\Core\Import\PostFields;
use HK9\Core\Import\Reconcile;

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
					// An editor trashed it: that is an edit, so the record is left alone (trash
					// included) unless overwrite is on — then a fresh copy is created.
					if ( ! $ctx->overwrite() ) {
						$ctx->warn( $key, sprintf( 'Mapped post #%d is in the trash on this site; left there. Restore it from the trash, or run with "Overwrite conflicts" to create a fresh copy.', $id ), $sens );
						$ctx->skip_record( $key, sprintf( 'in the trash (#%d)', $id ), $sens );
						return;
					}
					$ctx->warn( $key, sprintf( 'Mapped post #%d is in the trash; overwrite is on, so a new one will be created.', $id ), $sens );
				}
				$id = 0;
			}
		}
		if ( $id > 0 ) {
			$ctx->result( $key, 'skip', 'exists #' . $id, $sens );
			return;
		}

		if ( $ctx->adopt() && $this->adopt_existing( $key, $record, $type, $sens ) ) {
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
	 * Existing-site mode: bind the record to the page that already is this
	 * content. Returns true when the record was handled (adopted, or would be in
	 * a dry run); false means "no match — create as usual".
	 */
	private function adopt_existing( string $key, array $record, string $type, bool $sens ): bool {
		$ctx   = $this->ctx;
		$found = Adopt::post_candidate( $this->manifest(), $key, $record );
		if ( '' !== $found['warning'] ) {
			$ctx->warn( $key, $found['warning'], $sens );
		}
		$id = (int) $found['id'];
		if ( $id <= 0 ) {
			return false;
		}
		$detail = sprintf(
			'%s "%s" by %s%s',
			'' !== (string) $found['post_type'] ? (string) $found['post_type'] : 'page',
			(string) ( $record['slug'] ?? '' ),
			$found['how'],
			$found['convert'] ? ' -> converted to ' . $type : ( 'page' !== $type ? ' (already converted; binding restored)' : '' )
		);
		if ( $ctx->dry() ) {
			$ctx->adopted( $key, $id, $detail . ' (dry run)', $sens );
			$ctx->state['dry_adopted'][ $key ] = $id;
			return true;
		}

		Map::reserve( $key, $type, $ctx->run_id );

		$before = [];
		$hashes = [];
		if ( $found['convert'] ) {
			// Snapshot what the conversion changes, then convert in place (id + slug kept).
			$current            = PostFields::current( $id, [ 'post_type', 'template' ] );
			$before['post_type'] = $current['post_type'];
			$before['template']  = $current['template'];
			// A page template the new theme does not ship would make wp_update_post()
			// reject the write for the new post type (templates are validated per type);
			// records use fields, not templates, so the meta goes (restored on rollback).
			// The raw value is kept aside: a conversion that fails leaves the page a page,
			// and a page keeps its template.
			$raw_template = metadata_exists( 'post', $id, '_wp_page_template' ) ? (string) get_post_meta( $id, '_wp_page_template', true ) : null;
			delete_post_meta( $id, '_wp_page_template' );
			$r = wp_update_post(
				[
					'ID'        => $id,
					'post_type' => $type,
				],
				true
			);
			if ( is_wp_error( $r ) ) {
				clean_post_cache( $id );
				if ( null !== $raw_template && 'page' === get_post_type( $id ) ) {
					update_post_meta( $id, '_wp_page_template', $raw_template );
				}
				Map::delete( $key );
				$ctx->fail( $key, sprintf( 'Could not convert page #%d to %s: %s', $id, $type, $r->get_error_message() ), $sens );
				return true;
			}
			clean_post_cache( $id );
			$after = PostFields::current( $id, [ 'post_type', 'template' ] );
			foreach ( [ 'post_type', 'template' ] as $f ) {
				$hashes[ $f ] = [
					'db'  => Reconcile::hash( $f, $after[ $f ] ),
					'src' => Reconcile::hash( $f, $after[ $f ] ),
				];
			}
		}

		// Counted (and logged as ADOPT) only once the conversion, if any, went through:
		// a failed conversion is a fail, not an adoption.
		$ctx->adopted( $key, $id, $detail, $sens );

		update_post_meta( $id, '_hk9_source_key', $key );
		update_post_meta( $id, '_hk9_import_run', $ctx->run_id );
		update_post_meta( $id, '_hk9_import_pending', 1 );

		Map::bind(
			$key,
			$type,
			$id,
			$ctx->run_id,
			[
				'created_by_run' => null,
				'adopted_by_run' => $ctx->run_id,
				'payload_hash'   => $this->manifest()->payload_hash( $record ),
				'field_hashes'   => $hashes,
				'before_data'    => [],
			]
		);
		if ( $before ) {
			Map::record( $key, $ctx->run_id, $hashes, $before );
		}
		return true;
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
