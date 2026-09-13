<?php
/**
 * Step 6: posts_hierarchy — parent-first: parent, slug, final status, template,
 * excerpt, date, menu_order, featured image, terms and section meta, written in
 * ONE wp_update_post() (meta via meta_input) so the revision snapshot carries
 * the meta. `{{post_url}}`-bearing meta is deferred to posts_content.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Map;
use HK9\Core\Import\PostFields;
use HK9\Core\Import\Reconcile;

defined( 'ABSPATH' ) || exit;

final class PostsHierarchy extends Step {

	public const NAME = 'posts_hierarchy';

	protected function items(): array {
		return $this->manifest()->post_keys_parent_first();
	}

	/**
	 * wp_unique_post_slug() checks a hierarchical post's slug against pages AND
	 * attachments with the same parent, so an old upload whose slug equals a new
	 * page's slug (e.g. `about.jpg` -> `about`) would publish the page as `about-2`
	 * and hand `/about/` to the attachment. Attachment slugs are not user-facing
	 * (attachment pages are off), so the upload is renamed to `media-<slug>` first.
	 * Dry runs only warn.
	 */
	private function free_slug( string $key, int $id, array $record, array $fields, bool $sens ): void {
		global $wpdb;
		$ctx  = $this->ctx;
		$type = (string) ( $record['type'] ?? '' );
		$slug = (string) ( $fields['slug'] ?? '' );
		if ( '' === $slug || ! is_post_type_hierarchical( $type ) ) {
			return;
		}
		$parent = (int) ( $fields['parent'] ?? 0 );
		$taken  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'attachment' AND post_parent = %d AND ID != %d", $slug, $parent, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( array_map( 'intval', $taken ?: [] ) as $att ) {
			if ( $ctx->dry() ) {
				$ctx->warn( $key, sprintf( 'Attachment #%d holds the slug "%s" at this level; it will be renamed to "media-%s" so the page keeps its URL.', $att, $slug, $slug ), $sens );
				continue;
			}
			$r = wp_update_post(
				[
					'ID'        => $att,
					'post_name' => 'media-' . $slug,
				],
				true
			);
			if ( is_wp_error( $r ) ) {
				$ctx->warn( $key, sprintf( 'Attachment #%d holds the slug "%s" and could not be renamed: %s', $att, $slug, $r->get_error_message() ), $sens );
				continue;
			}
			$ctx->info( $key, sprintf( 'Renamed attachment #%d slug "%s" -> "%s" so the page keeps its URL.', $att, $slug, (string) get_post( $att )->post_name ), $sens );
		}
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}
		$sens = Context::is_sensitive( $record );

		$row = Map::get( $key );
		$id  = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		if ( 0 === $id || ! get_post( $id ) ) {
			if ( $ctx->dry() ) {
				$id = 0;
			} else {
				$ctx->fail( $key, __( 'Stub post is missing (posts_stub did not create it).', 'heartland-k9s-core' ), $sens );
				return;
			}
		}
		// Adopted row (existing-site mode): created_by_run is NULL; in a dry run the
		// would-be adoption is remembered by posts_stub so the plan is computed
		// against the real page instead of being reported as a create.
		$adopted = ( $row && Map::STATUS_ACTIVE === $row['status'] && null === $row['created_by_run'] );
		if ( 0 === $id && $ctx->dry() && ! empty( $ctx->state['dry_adopted'][ $key ] ) ) {
			$id      = (int) $ctx->state['dry_adopted'][ $key ];
			$row     = [ 'object_id' => $id, 'field_hashes' => [], 'created_by_run' => null ];
			$adopted = true;
		}

		$desired = PostFields::desired_hierarchy( $record, $ctx->tokens );
		if ( is_wp_error( $desired ) ) {
			$ctx->fail( $key, $desired->get_error_message(), $sens );
			return;
		}
		foreach ( $desired['warnings'] as $w ) {
			$ctx->warn( $key, $w, $sens );
		}
		$fields = $desired['fields'];
		if ( $adopted ) {
			$fields = PostFields::for_adopted( $fields );
		}

		// Never publish a stub whose content cannot be baked afterwards (a token that
		// depends on a failed attachment, a missing content file...): the record fails
		// here and the draft stub stays unpublished until the dependency is fixed.
		$preflight = PostFields::preflight_content( $record, $this->manifest(), $ctx->tokens );
		if ( is_wp_error( $preflight ) ) {
			$ctx->fail( $key, __( 'Content cannot be baked (post kept as draft): ', 'heartland-k9s-core' ) . $preflight->get_error_message(), $sens );
			return;
		}

		if ( 0 === $id ) {
			// Dry run for a record that would be created: everything applies.
			$ctx->result( $key, 'create', 'fields: ' . count( $fields ), $sens );
			$this->free_slug( $key, 0, $record, $fields, $sens );
			return;
		}

		$pending = ( $ctx->dry() && ! empty( $ctx->state['dry_adopted'][ $key ] ) ) || (bool) get_post_meta( $id, '_hk9_import_pending', true );
		$current = PostFields::current( $id, array_keys( $fields ) );
		$plan    = Reconcile::plan( $row, $fields, $current, $ctx->overwrite() );
		$action  = $pending ? ( $adopted ? 'adopt' : 'create' ) : $plan['action'];
		$detail  = $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : ( $plan['overwritten'] ? 'overwrote: ' . implode( ',', $plan['overwritten'] ) : '' );
		if ( 'adopt' === $action ) {
			$ctx->adopted( $key, $id, sprintf( 'fields applied: %d', count( $plan['apply'] ) ) . ( $ctx->dry() ? ' (dry run)' : '' ), $sens );
		} else {
			$ctx->result( $key, $action, $detail, $sens );
		}
		if ( $ctx->dry() ) {
			return;
		}

		if ( $pending || isset( $plan['apply']['slug'] ) || isset( $plan['apply']['status'] ) ) {
			// Publishing (or renaming) makes WordPress enforce slug uniqueness against attachments too.
			$this->free_slug( $key, $id, $record, $fields, $sens );
		}
		if ( $plan['apply'] ) {
			$r = PostFields::apply( $id, $plan['apply'] );
			if ( is_wp_error( $r ) ) {
				$ctx->fail( $key, $r->get_error_message(), $sens );
				return;
			}
		}
		if ( $pending ) {
			delete_post_meta( $id, '_hk9_import_pending' );
		}
		$after = PostFields::current( $id, array_keys( $fields ) );
		if ( isset( $fields['slug'], $after['slug'] ) && $after['slug'] !== $fields['slug'] && ! in_array( (string) ( $after['status'] ?? '' ), [ 'draft', 'auto-draft' ], true ) ) {
			$ctx->warn( $key, sprintf( 'Slug "%s" is taken by another %s at this level; WordPress stored "%s". Resolve the collision and re-run.', $fields['slug'], (string) $record['type'], $after['slug'] ), $sens );
		}
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, $after ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}
}
