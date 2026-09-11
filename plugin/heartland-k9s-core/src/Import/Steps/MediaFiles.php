<?php
/**
 * Step 3: media_files.
 *
 * Copies each payload file to wp_tempnam() and sideloads it (core copies+unlinks
 * tmp_name, so the payload file itself is never touched). Only the scaled /
 * rotated original and base metadata are produced here; sub-sizes come in
 * media_sizes. Dedupes by sha256 (map table, then _hk9_sha256 meta written by
 * validate's prehash pass) so manual uploads with identical bytes are adopted.
 *
 * Shared attachments: when two payload records carry byte-identical files, the
 * first record (manifest order) creates the attachment and OWNS its fields; every
 * later record is bound to the same attachment as a secondary row that never
 * writes title/alt/caption/description/date (its map row mirrors the owner's
 * field hashes so both rows hash-match the object, re-runs skip, and rollback
 * can delete the attachment once).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Hash;
use HK9\Core\Import\Manifest;
use HK9\Core\Import\Map;
use HK9\Core\Import\Reconcile;

defined( 'ABSPATH' ) || exit;

final class MediaFiles extends Step {

	public const NAME = 'media_files';

	public const FIELDS = [ 'title', 'caption', 'description', 'alt', 'date' ];

	protected function items(): array {
		return $this->manifest()->keys( 'attachment' );
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}
		$sens = Context::is_sensitive( $record );
		$sha  = strtolower( (string) $record['sha256'] );

		$desired = self::desired( $record );
		if ( is_wp_error( $desired ) ) {
			$ctx->fail( $key, $desired->get_error_message(), $sens );
			return;
		}

		$row = Map::get( $key );
		$id  = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		if ( $id > 0 && 'attachment' !== get_post_type( $id ) ) {
			$id  = 0; // Deleted since; recreate.
			$row = null;
		}

		$adopted = false;
		if ( 0 === $id ) {
			// Dedupe by content hash: another payload record's attachment first (oldest row wins) ...
			foreach ( Map::rows_by_sha( $sha ) as $other ) {
				if ( $other['source_key'] !== $key && 'attachment' === get_post_type( $other['object_id'] ) ) {
					$id      = $other['object_id'];
					$adopted = true;
					break;
				}
			}
			// ... then a pre-existing upload with identical bytes.
			if ( 0 === $id ) {
				$id = Map::find_attachment_by_sha( $sha );
				if ( 0 === $id && isset( $ctx->state['prehash'][ $sha ] ) ) {
					$id = (int) $ctx->state['prehash'][ $sha ];
				}
				$adopted = $id > 0;
			}
		}

		if ( 0 === $id ) {
			// Dry runs write nothing, so a later record with the same bytes cannot find the
			// attachment this one would create: remember it to report the share accurately.
			if ( $ctx->dry() && isset( $ctx->state['dry_created'][ $sha ] ) && $ctx->state['dry_created'][ $sha ] !== $key ) {
				$ctx->result( $key, 'skip', sprintf( 'would share the attachment created for %s (identical file)', $this->owner_label( (string) $ctx->state['dry_created'][ $sha ] ) ), $sens );
				return;
			}
			if ( $ctx->dry() ) {
				$ctx->state['dry_created'][ $sha ] = $key;
			}
			$this->create( $key, $record, $desired, $sha, $sens );
			return;
		}

		// Shared attachment? The oldest row bound to it owns its fields.
		$owner = Map::owner( 'attachment', $id );
		if ( $owner && $owner['source_key'] !== $key ) {
			$this->secondary( $key, $record, $desired, $id, $owner, $sha, $sens, $row );
			return;
		}

		$current = self::current( $id );
		$plan    = Reconcile::plan( $row && $row['object_id'] === $id ? $row : [ 'object_id' => $id, 'field_hashes' => [] ], $desired, $current, $ctx->overwrite() );
		$ctx->result( $key, $plan['action'], ( $adopted ? 'adopted #' . $id . ' ' : '' ) . ( $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : '' ), $sens );
		if ( $ctx->dry() ) {
			return;
		}
		if ( ! $row || $row['object_id'] !== $id ) {
			Map::bind(
				$key,
				'attachment',
				$id,
				$ctx->run_id,
				[
					'created_by_run' => null,
					'sha256'         => $sha,
				]
			);
			if ( '' === (string) get_post_meta( $id, '_hk9_source_key', true ) ) {
				update_post_meta( $id, '_hk9_source_key', $key );
			}
			update_post_meta( $id, '_hk9_sha256', $sha );
		}
		if ( $plan['apply'] ) {
			$r = self::apply( $id, $plan['apply'] );
			if ( is_wp_error( $r ) ) {
				$ctx->fail( $key, $r->get_error_message(), $sens );
				return;
			}
		}
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, self::current( $id ) ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}

	/**
	 * A record whose bytes already live in an attachment owned by another payload
	 * record. The object's fields belong to the owner (first record wins), so
	 * nothing is written to the post: the row is bound to the same attachment and
	 * its field hashes mirror the owner's `db` hashes (falling back to the live
	 * values for fields the owner never recorded) with its own `src` hashes, so
	 * the row hash-matches the object exactly when the owner's does. An editor's
	 * edit therefore shows up as a conflict on the owner only, and rollback sees
	 * the same "modified" verdict through every row.
	 */
	private function secondary( string $key, array $record, array $desired, int $id, array $owner, string $sha, bool $sens, ?array $row ): void {
		$ctx     = $this->ctx;
		$current = self::current( $id );
		$hashes  = [];
		foreach ( $desired as $f => $v ) {
			$hashes[ $f ] = [
				'db'  => $owner['field_hashes'][ $f ]['db'] ?? Hash::of( $current[ $f ] ?? null ),
				'src' => Hash::of( $v ),
			];
		}
		$first = ! $row || $row['object_id'] !== $id;
		$ctx->result( $key, 'skip', sprintf( 'shares attachment #%d with %s%s', $id, $this->owner_label( (string) $owner['source_key'] ), $first ? ' (identical file; the owner keeps its title/alt/caption/date)' : '' ), $sens );
		if ( $ctx->dry() ) {
			return;
		}
		if ( $first ) {
			// Fresh binding: any hashes / pre-images the row carried belong to an object that no longer exists.
			Map::bind(
				$key,
				'attachment',
				$id,
				$ctx->run_id,
				[
					'created_by_run' => null,
					'sha256'         => $sha,
					'payload_hash'   => $this->manifest()->payload_hash( $record ),
					'field_hashes'   => $hashes,
					'before_data'    => [],
				]
			);
			if ( '' === (string) get_post_meta( $id, '_hk9_sha256', true ) ) {
				update_post_meta( $id, '_hk9_sha256', $sha );
			}
			return;
		}
		Map::record( $key, $ctx->run_id, $hashes, [], $this->manifest()->payload_hash( $record ) );
	}

	/**
	 * Key of another record for log lines: withheld when that record is sensitive.
	 */
	private function owner_label( string $owner_key ): string {
		$owner_record = $this->record( $owner_key );
		if ( $owner_record && Context::is_sensitive( $owner_record ) ) {
			return 'sensitive#' . Hash::short( $owner_key );
		}
		return $owner_key;
	}

	private function create( string $key, array $record, array $desired, string $sha, bool $sens ): void {
		$ctx  = $this->ctx;
		$path = $this->manifest()->path( (string) $record['file'] );
		$ctx->result( $key, 'create', basename( (string) $record['file'] ), $sens );
		if ( $ctx->dry() ) {
			return;
		}
		if ( null === $path || ! is_file( $path ) ) {
			$ctx->fail( $key, 'File missing at import time.', $sens );
			return;
		}

		Map::reserve( $key, 'attachment', $ctx->run_id );

		// Crash window recovery: created in an earlier tick but never bound.
		$id = Map::find_orphan_post( $key );
		if ( 0 === $id ) {
			$id = $this->sideload( $key, $record, $desired, $path, $sha, $sens );
			if ( 0 === $id ) {
				Map::delete( $key );
				return;
			}
			$ctx->add_bytes( (int) filesize( $path ) );
		}

		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $desired['alt'] ) );

		Map::bind(
			$key,
			'attachment',
			$id,
			$ctx->run_id,
			[
				'created_by_run' => $ctx->run_id,
				'sha256'         => $sha,
				'payload_hash'   => $this->manifest()->payload_hash( $record ),
				'field_hashes'   => Reconcile::fresh( $desired, self::current( $id ) ),
			]
		);
	}

	private function sideload( string $key, array $record, array $desired, string $path, string $sha, bool $sens ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$ctx      = $this->ctx;
		$basename = sanitize_file_name( basename( $path ) );
		$tmp      = wp_tempnam( $basename );
		if ( ! $tmp || ! copy( $path, $tmp ) ) {
			$ctx->fail( $key, 'Could not copy the file to a temporary location.', $sens );
			return 0;
		}

		$file_array = [
			'name'     => $basename,
			'tmp_name' => $tmp,
			'type'     => (string) $record['mime'],
			'size'     => (int) filesize( $path ),
			'error'    => 0,
		];

		// Attachment slugs share the root namespace with Pages (wp_unique_post_slug checks post_type IN (page, attachment)
		// with the same parent), so an attachment named "about.jpg" would force the About page to become "about-2".
		// Imported attachments therefore get a "media-" prefixed slug; attachment pages are disabled by default anyway.
		$slug_base = sanitize_title( (string) ( $desired['title'] ?: pathinfo( $basename, PATHINFO_FILENAME ) ) );
		$post_data = [
			'post_title'   => $desired['title'],
			'post_name'    => 'media-' . ( '' !== $slug_base ? $slug_base : substr( $sha, 0, 12 ) ),
			'post_excerpt' => $desired['caption'],
			'post_content' => $desired['description'],
			'meta_input'   => [
				'_hk9_source_key' => $key,
				'_hk9_sha256'     => $sha,
				'_hk9_import_run' => $ctx->run_id,
			],
		];
		if ( '' !== $desired['date'] ) {
			$post_data['post_date_gmt'] = $desired['date'];
			$post_data['post_date']     = get_date_from_gmt( $desired['date'] );
		}

		// wp_insert_attachment() does not slash: pre-slash here.
		$post_data = wp_slash( $post_data );

		$no_sizes = static fn(): array => [];
		add_filter( 'intermediate_image_sizes_advanced', $no_sizes, 999 );
		$id = media_handle_sideload( $file_array, 0, null, $post_data );
		remove_filter( 'intermediate_image_sizes_advanced', $no_sizes, 999 );

		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			$ctx->fail( $key, 'Sideload failed: ' . $id->get_error_message(), $sens );
			return 0;
		}
		return (int) $id;
	}

	/**
	 * Resolved payload values for the reconcilable fields.
	 */
	public static function desired( array $record ): array|\WP_Error {
		$date = Manifest::date_pair( $record['date'] ?? null );
		if ( is_wp_error( $date ) ) {
			return $date;
		}
		$sensitive = Context::is_sensitive( $record );
		$title     = (string) ( $record['title'] ?? '' );
		if ( '' === $title ) {
			$title = pathinfo( (string) ( $record['file'] ?? 'file' ), PATHINFO_FILENAME );
		}
		if ( $sensitive ) {
			// Registry images: never carry names in title/alt/caption (F7).
			return [
				'title'       => 'Registry image',
				'caption'     => '',
				'description' => '',
				'alt'         => '',
				'date'        => $date ? $date['gmt'] : '',
			];
		}
		return [
			'title'       => $title,
			'caption'     => (string) ( $record['caption'] ?? '' ),
			'description' => (string) ( $record['description'] ?? '' ),
			'alt'         => (string) ( $record['alt'] ?? '' ),
			'date'        => $date ? $date['gmt'] : '',
		];
	}

	public static function current( int $id ): array {
		$post = get_post( $id );
		if ( ! $post ) {
			return [];
		}
		return [
			'title'       => (string) $post->post_title,
			'caption'     => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'date'        => (string) $post->post_date_gmt,
		];
	}

	public static function apply( int $id, array $apply ): true|\WP_Error {
		$args = [ 'ID' => $id ];
		if ( array_key_exists( 'title', $apply ) ) {
			$args['post_title'] = $apply['title'];
		}
		if ( array_key_exists( 'caption', $apply ) ) {
			$args['post_excerpt'] = $apply['caption'];
		}
		if ( array_key_exists( 'description', $apply ) ) {
			$args['post_content'] = $apply['description'];
		}
		if ( array_key_exists( 'date', $apply ) && '' !== $apply['date'] ) {
			$args['post_date_gmt'] = $apply['date'];
			$args['post_date']     = get_date_from_gmt( $apply['date'] );
			$args['edit_date']     = true;
		}
		if ( count( $args ) > 1 ) {
			$r = wp_update_post( wp_slash( $args ), true );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( array_key_exists( 'alt', $apply ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( (string) $apply['alt'] ) );
		}
		return true;
	}
}
