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

		$desired = PostFields::desired_hierarchy( $record, $ctx->tokens );
		if ( is_wp_error( $desired ) ) {
			$ctx->fail( $key, $desired->get_error_message(), $sens );
			return;
		}
		foreach ( $desired['warnings'] as $w ) {
			$ctx->warn( $key, $w, $sens );
		}
		$fields = $desired['fields'];

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
			return;
		}

		$pending = (bool) get_post_meta( $id, '_hk9_import_pending', true );
		$current = PostFields::current( $id, array_keys( $fields ) );
		$plan    = Reconcile::plan( $row, $fields, $current, $ctx->overwrite() );
		$action  = $pending ? 'create' : $plan['action'];
		$detail  = $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : ( $plan['overwritten'] ? 'overwrote: ' . implode( ',', $plan['overwritten'] ) : '' );
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
		if ( $pending ) {
			delete_post_meta( $id, '_hk9_import_pending' );
		}
		$after = PostFields::current( $id, array_keys( $fields ) );
		if ( isset( $fields['slug'] ) && isset( $after['slug'] ) && $after['slug'] !== $fields['slug'] && ( isset( $plan['apply']['slug'] ) ) ) {
			$ctx->warn( $key, sprintf( 'Slug "%s" was already taken; WordPress stored "%s".', $fields['slug'], $after['slug'] ), $sens );
		}
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, $after ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}
}
