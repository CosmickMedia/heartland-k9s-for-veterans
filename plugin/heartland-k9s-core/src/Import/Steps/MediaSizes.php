<?php
/**
 * Step 4: media_sizes — generate missing intermediate sizes (resumable:
 * wp_update_image_subsizes() skips sizes that already exist).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Map;

defined( 'ABSPATH' ) || exit;

final class MediaSizes extends Step {

	public const NAME = 'media_sizes';

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
		$row  = Map::get( $key );
		$id   = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		if ( 0 === $id ) {
			if ( $ctx->dry() ) {
				// A record whose bytes another record would create first shares that attachment.
				$sha   = strtolower( (string) ( $record['sha256'] ?? '' ) );
				$owner = (string) ( $ctx->state['dry_created'][ $sha ] ?? '' );
				if ( '' !== $owner && $owner !== $key ) {
					$ctx->result( $key, 'skip', 'shares its attachment (dry run)', $sens );
					return;
				}
				$ctx->result( $key, 'create', 'sizes (dry run)', $sens );
				return;
			}
			$ctx->fail( $key, 'Attachment was not created in media_files.', $sens );
			return;
		}
		if ( ! wp_attachment_is_image( $id ) ) {
			$ctx->result( $key, 'skip', 'not an image', $sens );
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$before = wp_get_attachment_metadata( $id );
		$have   = is_array( $before ) ? count( $before['sizes'] ?? [] ) : 0;
		if ( $ctx->dry() ) {
			// Only sizes the image is large enough for count as missing (small images never get every registered size).
			$missing = wp_get_missing_image_subsizes( $id );
			$ctx->result( $key, $missing ? 'update' : 'skip', sprintf( '%d sizes, %d missing', $have, count( $missing ) ), $sens );
			return;
		}

		$meta = wp_update_image_subsizes( $id );
		if ( is_wp_error( $meta ) ) {
			$ctx->fail( $key, 'Sub-size generation failed: ' . $meta->get_error_message(), $sens );
			return;
		}
		$after = is_array( $meta ) ? count( $meta['sizes'] ?? [] ) : $have;
		$ctx->result( $key, $after > $have ? 'update' : 'skip', sprintf( '%d sizes', $after ), $sens );
	}
}
