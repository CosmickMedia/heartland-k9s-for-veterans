<?php
/**
 * Step 7: reading — page_on_front / page_for_posts first, then show_on_front
 * (so the front page URL is final before content URLs are baked).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Map;
use HK9\Core\Import\Reconcile;

defined( 'ABSPATH' ) || exit;

final class Reading extends Step {

	public const NAME = 'reading';

	public const FIELDS = [ 'page_on_front', 'page_for_posts', 'show_on_front', 'posts_per_page' ];

	protected function items(): array {
		return $this->manifest()->keys( 'reading' );
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}

		$desired = [];
		foreach ( [ 'page_on_front', 'page_for_posts' ] as $f ) {
			if ( ! array_key_exists( $f, $record ) ) {
				continue;
			}
			$v = (string) $record[ $f ];
			if ( '' === $v ) {
				$desired[ $f ] = 0;
				continue;
			}
			$id = $ctx->tokens->bake( $v );
			if ( is_wp_error( $id ) ) {
				$ctx->fail( $key, $id->get_error_message() );
				return;
			}
			$desired[ $f ] = (int) $id;
			if ( ! $ctx->dry() ) {
				$post = get_post( (int) $id );
				if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
					$ctx->fail( $key, sprintf( '%s must point at a published page (got #%d %s).', $f, (int) $id, $post ? $post->post_status : 'missing' ) );
					return;
				}
			}
		}
		if ( ! empty( $desired['page_on_front'] ) && ! empty( $desired['page_for_posts'] ) && $desired['page_on_front'] === $desired['page_for_posts'] ) {
			$ctx->fail( $key, 'page_on_front and page_for_posts must be different pages.' );
			return;
		}
		if ( array_key_exists( 'show_on_front', $record ) ) {
			$desired['show_on_front'] = 'posts' === $record['show_on_front'] ? 'posts' : 'page';
		}
		if ( array_key_exists( 'posts_per_page', $record ) ) {
			$desired['posts_per_page'] = max( 1, (int) $record['posts_per_page'] );
		}
		if ( ! $desired ) {
			$ctx->result( $key, 'skip', 'nothing to set' );
			return;
		}

		$row     = Map::get( $key );
		$current = self::current();
		$plan    = Reconcile::plan( $row ?: [ 'object_id' => 0, 'field_hashes' => [] ], $desired, $current, $ctx->overwrite() );
		$ctx->result( $key, $plan['action'], $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : implode( ',', array_keys( $plan['apply'] ) ) );
		if ( $ctx->dry() ) {
			return;
		}
		if ( ! $row ) {
			Map::bind( $key, 'reading', 0, $ctx->run_id, [ 'created_by_run' => null ] );
		}
		foreach ( self::FIELDS as $f ) {
			if ( ! array_key_exists( $f, $plan['apply'] ) ) {
				continue;
			}
			update_option( $f, $plan['apply'][ $f ] );
		}
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, self::current() ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}

	public static function current(): array {
		return [
			'page_on_front'  => (int) get_option( 'page_on_front' ),
			'page_for_posts' => (int) get_option( 'page_for_posts' ),
			'show_on_front'  => (string) get_option( 'show_on_front' ),
			'posts_per_page' => (int) get_option( 'posts_per_page' ),
		];
	}
}
