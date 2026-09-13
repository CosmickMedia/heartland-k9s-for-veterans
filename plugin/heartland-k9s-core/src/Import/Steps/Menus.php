<?php
/**
 * Step 9: menus — create/adopt a nav_menu by name, items parent-first with
 * menu-item-status=publish and object ids for post_type items, then merge
 * locations into the theme mod. Editor-added items in an adopted menu are
 * never touched.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Map;
use HK9\Core\Import\Reconcile;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Menus extends Step {

	public const NAME = 'menus';

	public const ITEM_FIELDS = [ 'kind', 'object_id', 'object', 'url', 'title', 'target', 'classes', 'parent', 'order' ];

	protected function items(): array {
		return $this->manifest()->keys( 'menu' );
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}
		$name = (string) $record['name'];

		/* ---- menu object ---- */
		$row     = Map::get( $key );
		$menu_id = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		if ( $menu_id > 0 && ! wp_get_nav_menu_object( $menu_id ) ) {
			$menu_id = 0;
			$row     = null;
		}
		$adopted = false;
		if ( 0 === $menu_id ) {
			$existing = wp_get_nav_menu_object( $name );
			if ( $existing ) {
				$menu_id = (int) $existing->term_id;
				$adopted = true;
			}
		}
		if ( 0 === $menu_id ) {
			$ctx->result( $key, 'create', 'menu "' . $name . '"' );
			if ( $ctx->dry() ) {
				$this->dry_items( $record );
				return;
			}
			Map::reserve( $key, 'nav_menu', $ctx->run_id );
			$created = wp_create_nav_menu( $name );
			if ( is_wp_error( $created ) ) {
				$existing = wp_get_nav_menu_object( $name );
				if ( $existing ) {
					$menu_id = (int) $existing->term_id;
					$adopted = true;
				} else {
					Map::delete( $key );
					$ctx->fail( $key, $created->get_error_message() );
					return;
				}
			} else {
				$menu_id = (int) $created;
				update_term_meta( $menu_id, '_hk9_source_key', $key );
			}
			Map::bind( $key, 'nav_menu', $menu_id, $ctx->run_id, [ 'created_by_run' => $adopted ? null : $ctx->run_id, 'adopted_by_run' => $adopted ? $ctx->run_id : null, 'payload_hash' => $this->manifest()->payload_hash( $record ) ] );
			$row = Map::get( $key );
		} elseif ( ! $row ) {
			$ctx->adopted( $key, $menu_id, sprintf( 'menu "%s" by name%s', $name, $ctx->dry() ? ' (dry run)' : '' ) );
			if ( ! $ctx->dry() ) {
				Map::bind( $key, 'nav_menu', $menu_id, $ctx->run_id, [ 'created_by_run' => null, 'adopted_by_run' => $ctx->run_id, 'payload_hash' => $this->manifest()->payload_hash( $record ) ] );
				$row = Map::get( $key );
			}
		} else {
			$ctx->result( $key, 'skip', 'menu exists #' . $menu_id );
		}

		/* ---- items (parent-first) ---- */
		$items = $this->ordered_items( $record );
		foreach ( $items as $item ) {
			$this->item( $key, $menu_id, $item );
		}

		/* ---- locations ---- */
		$desired = [];
		foreach ( (array) ( $record['locations'] ?? [] ) as $loc ) {
			$loc = sanitize_key( (string) $loc );
			if ( '' !== $loc ) {
				$desired[ 'location:' . $loc ] = $menu_id;
			}
		}
		if ( ! $desired ) {
			return;
		}
		$registered = get_registered_nav_menus();
		foreach ( array_keys( $desired ) as $f ) {
			$loc = substr( $f, 9 );
			if ( ! isset( $registered[ $loc ] ) ) {
				$ctx->warn( $key, sprintf( 'Menu location "%s" is not registered by the active theme; assigning it anyway.', $loc ) );
			}
		}
		$current = self::current_locations( array_keys( $desired ) );
		$plan    = Reconcile::plan( $row ?: [ 'object_id' => $menu_id, 'field_hashes' => [] ], $desired, $current, $ctx->overwrite() );
		if ( 'skip' !== $plan['action'] ) {
			$ctx->result( $key . ':locations', $plan['action'], $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : implode( ',', array_keys( $plan['apply'] ) ) );
		}
		if ( $ctx->dry() || ! $row ) {
			return;
		}
		if ( $plan['apply'] ) {
			$locations = get_theme_mod( 'nav_menu_locations' );
			$locations = is_array( $locations ) ? $locations : [];
			foreach ( $plan['apply'] as $f => $id ) {
				$locations[ substr( $f, 9 ) ] = (int) $id;
			}
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, self::current_locations( array_keys( $desired ) ) ), $plan['before'], $this->manifest()->payload_hash( $record ) );
	}

	private function dry_items( array $record ): void {
		foreach ( $this->ordered_items( $record ) as $item ) {
			$this->ctx->result( (string) $item['key'], 'create', 'item (dry run)' );
		}
	}

	/**
	 * Items sorted parent-first, then by order.
	 *
	 * @return array[]
	 */
	private function ordered_items( array $record ): array {
		$items = array_values( array_filter( (array) ( $record['items'] ?? [] ), 'is_array' ) );
		$by    = [];
		foreach ( $items as $it ) {
			$by[ (string) $it['key'] ] = $it;
		}
		$depth = function ( array $it, array $seen ) use ( &$depth, $by ): int {
			$p = (string) ( $it['parent'] ?? '' );
			if ( '' === $p || ! isset( $by[ $p ] ) || isset( $seen[ $p ] ) ) {
				return 0;
			}
			$seen[ $p ] = true;
			return 1 + $depth( $by[ $p ], $seen );
		};
		$rows = [];
		foreach ( $items as $i => $it ) {
			$rows[] = [ $depth( $it, [] ), (int) ( $it['order'] ?? $i ), $i, $it ];
		}
		usort( $rows, static fn( $a, $b ) => [ $a[0], $a[1], $a[2] ] <=> [ $b[0], $b[1], $b[2] ] );
		return array_map( static fn( $r ) => $r[3], $rows );
	}

	private function item( string $menu_key, int $menu_id, array $item ): void {
		$ctx  = $this->ctx;
		$ikey = (string) $item['key'];
		if ( $ctx->is_failed( $ikey ) ) {
			return;
		}

		$desired = $this->desired_item( $item );
		if ( is_wp_error( $desired ) ) {
			$ctx->fail( $ikey, $desired->get_error_message() );
			return;
		}

		$row     = Map::get( $ikey );
		$item_id = $row && Map::STATUS_ACTIVE === $row['status'] ? $row['object_id'] : 0;
		if ( $item_id > 0 && 'nav_menu_item' !== get_post_type( $item_id ) ) {
			$item_id = 0;
			$row     = null;
		}

		if ( 0 === $item_id ) {
			$ctx->result( $ikey, 'create', $desired['kind'] . ' ' . ( $desired['title'] ?: $desired['url'] ) );
			if ( $ctx->dry() || 0 === $menu_id ) {
				return;
			}
			Map::reserve( $ikey, 'nav_menu_item', $ctx->run_id );
			$item_id = Map::find_orphan_post( $ikey );
			if ( 0 === $item_id ) {
				$r = wp_update_nav_menu_item( $menu_id, 0, $this->item_args( $desired ) );
				if ( is_wp_error( $r ) || 0 === (int) $r ) {
					Map::delete( $ikey );
					$ctx->fail( $ikey, 'Could not create the menu item: ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'unknown error' ) );
					return;
				}
				$item_id = (int) $r;
				update_post_meta( $item_id, '_hk9_source_key', $ikey );
				update_post_meta( $item_id, '_hk9_import_run', $ctx->run_id );
			}
			Map::bind(
				$ikey,
				'nav_menu_item',
				$item_id,
				$ctx->run_id,
				[
					'created_by_run' => $ctx->run_id,
					'payload_hash'   => \HK9\Core\Import\Hash::of( $item ),
					'field_hashes'   => Reconcile::fresh( $desired, self::current_item( $item_id ) ),
				]
			);
			return;
		}

		$current = self::current_item( $item_id );
		$plan    = Reconcile::plan( $row, $desired, $current, $ctx->overwrite() );
		$ctx->result( $ikey, $plan['action'], $plan['conflicts'] ? 'conflicts: ' . implode( ',', $plan['conflicts'] ) : '' );
		if ( $ctx->dry() ) {
			return;
		}
		if ( $plan['apply'] ) {
			// Rebuild the full argument set from current + applied values (core resets unspecified keys).
			$merged = array_merge( $current, $plan['apply'] );
			$r      = wp_update_nav_menu_item( $menu_id, $item_id, $this->item_args( $merged ) );
			if ( is_wp_error( $r ) ) {
				$ctx->fail( $ikey, $r->get_error_message() );
				return;
			}
		}
		Map::record( $ikey, $ctx->run_id, Reconcile::readback( $plan, self::current_item( $item_id ) ), $plan['before'], \HK9\Core\Import\Hash::of( $item ) );
	}

	/**
	 * @return array|WP_Error
	 */
	private function desired_item( array $item ): array|WP_Error {
		$kind    = 'custom' === ( $item['kind'] ?? '' ) ? 'custom' : 'post_type';
		$desired = [
			'kind'      => $kind,
			'object_id' => 0,
			'object'    => 'custom',
			'url'       => '',
			'title'     => (string) ( $item['title'] ?? '' ),
			'target'    => '_blank' === ( $item['target'] ?? '' ) ? '_blank' : '',
			'classes'   => array_values( array_filter( array_map( 'sanitize_html_class', (array) ( $item['classes'] ?? [] ) ) ) ),
			'parent'    => 0,
			'order'     => (int) ( $item['order'] ?? 0 ),
		];
		if ( 'post_type' === $kind ) {
			$id = $this->ctx->tokens->bake( (string) ( $item['object'] ?? '' ) );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$desired['object_id'] = (int) $id;
			$desired['object']    = (string) ( get_post_type( (int) $id ) ?: 'page' );
		} else {
			$url = (string) ( $item['url'] ?? '' );
			if ( \HK9\Core\Import\Tokens::contains( $url ) ) {
				$baked = $this->ctx->tokens->bake( $url );
				if ( is_wp_error( $baked ) ) {
					return $baked;
				}
				$url = (string) $baked;
			}
			$desired['url'] = esc_url_raw( $url );
		}
		$parent_key = (string) ( $item['parent'] ?? '' );
		if ( '' !== $parent_key ) {
			$prow = Map::get( $parent_key );
			if ( $prow && Map::STATUS_ACTIVE === $prow['status'] && $prow['object_id'] > 0 ) {
				$desired['parent'] = $prow['object_id'];
			} elseif ( ! $this->ctx->dry() ) {
				return new WP_Error( 'hk9_menu_parent', sprintf( 'Parent item %s is not imported.', $parent_key ) );
			}
		}
		return $desired;
	}

	/**
	 * wp_update_nav_menu_item() hands these to wp_insert_post()/update_post_meta(),
	 * which expect slashed data: pre-slash here.
	 */
	private function item_args( array $d ): array {
		return wp_slash( [
			'menu-item-status'    => 'publish',
			'menu-item-type'      => $d['kind'],
			'menu-item-object'    => 'post_type' === $d['kind'] ? $d['object'] : 'custom',
			'menu-item-object-id' => 'post_type' === $d['kind'] ? (int) $d['object_id'] : 0,
			'menu-item-url'       => 'custom' === $d['kind'] ? (string) $d['url'] : '',
			'menu-item-title'     => (string) $d['title'],
			'menu-item-target'    => (string) $d['target'],
			'menu-item-classes'   => implode( ' ', (array) $d['classes'] ),
			'menu-item-parent-id' => (int) $d['parent'],
			'menu-item-position'  => (int) $d['order'],
		] );
	}

	public static function current_item( int $id ): array {
		$post = get_post( $id );
		if ( ! $post ) {
			return [];
		}
		$classes = get_post_meta( $id, '_menu_item_classes', true );
		$classes = is_array( $classes ) ? array_values( array_filter( array_map( 'strval', $classes ) ) ) : [];
		$kind    = (string) get_post_meta( $id, '_menu_item_type', true );
		return [
			'kind'      => 'custom' === $kind ? 'custom' : 'post_type',
			'object_id' => (int) get_post_meta( $id, '_menu_item_object_id', true ),
			'object'    => (string) get_post_meta( $id, '_menu_item_object', true ),
			'url'       => (string) get_post_meta( $id, '_menu_item_url', true ),
			'title'     => (string) $post->post_title,
			'target'    => (string) get_post_meta( $id, '_menu_item_target', true ),
			'classes'   => $classes,
			'parent'    => (int) get_post_meta( $id, '_menu_item_menu_item_parent', true ),
			'order'     => (int) $post->menu_order,
		];
	}

	public static function current_locations( array $fields ): array {
		$locations = get_theme_mod( 'nav_menu_locations' );
		$locations = is_array( $locations ) ? $locations : [];
		$out       = [];
		foreach ( $fields as $f ) {
			$out[ $f ] = (int) ( $locations[ substr( $f, 9 ) ] ?? 0 );
		}
		return $out;
	}
}
