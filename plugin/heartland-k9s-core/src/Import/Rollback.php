<?php
/**
 * Run-scoped rollback.
 *
 * Order (mandatory): options / reading / redirects pre-images are restored
 * FIRST (so deleting the imported front page cannot reset show_on_front
 * afterwards), then menu items -> menus -> posts -> attachments -> terms.
 * Objects created by the run are deleted only when every recorded field hash
 * still matches the database (or --force); objects merely updated by the run
 * get their pre-image restored under the same rule; adopted objects are never
 * deleted.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

use HK9\Core\Import\Steps\Menus;
use HK9\Core\Import\Steps\MediaFiles;
use HK9\Core\Import\Steps\Reading;
use HK9\Core\Import\Steps\Redirects;
use HK9\Core\Import\Steps\Terms;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Rollback {

	private const ORDER = [ 'reading', 'option', 'redirect', 'nav_menu_item', 'nav_menu', 'post', 'attachment', 'term' ];

	/**
	 * @return array{run:string,deleted:array,restored:array,skipped:array,errors:array,dry_run:bool}|WP_Error
	 */
	public static function run( string $run_id, bool $force = false, bool $dry_run = false ): array|WP_Error {
		Map::ensure();
		$run_id = Log::sanitize_run_id( $run_id );
		if ( '' === $run_id ) {
			return new WP_Error( 'hk9_rollback_run', __( 'Invalid run id.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		$state = State::load();
		if ( State::STATUS_RUNNING === $state['status'] && Map::lock_info() ) {
			return new WP_Error( 'hk9_rollback_busy', __( 'An import is running; pause it before rolling back.', 'heartland-k9s-core' ), [ 'status' => 409 ] );
		}
		$token = strtolower( wp_generate_password( 16, false, false ) );
		if ( ! Map::acquire_lock( $token, 600 ) ) {
			return new WP_Error( 'hk9_rollback_locked', __( 'Another process holds the import lock.', 'heartland-k9s-core' ), [ 'status' => 423 ] );
		}

		$report = [
			'run'      => $run_id,
			'dry_run'  => $dry_run,
			'deleted'  => [],
			'restored' => [],
			'skipped'  => [],
			'errors'   => [],
		];
		$log    = new Log( $run_id );
		$log->info( 'rollback', '', sprintf( 'Rollback started (force=%s, dry_run=%s).', $force ? 'yes' : 'no', $dry_run ? 'yes' : 'no' ) );

		try {
			$rows = Map::rows_for_run( $run_id );
			if ( ! $rows ) {
				return new WP_Error( 'hk9_rollback_empty', sprintf( __( 'No mapped objects reference run %s.', 'heartland-k9s-core' ), $run_id ), [ 'status' => 404 ] );
			}
			$buckets = [];
			foreach ( $rows as $row ) {
				$type = self::bucket( $row['object_type'] );
				$buckets[ $type ][] = $row;
			}
			foreach ( self::ORDER as $type ) {
				$list = $buckets[ $type ] ?? [];
				if ( 'nav_menu_item' === $type ) {
					// Children before parents.
					usort( $list, static fn( $a, $b ) => (int) get_post_meta( $b['object_id'], '_menu_item_menu_item_parent', true ) <=> (int) get_post_meta( $a['object_id'], '_menu_item_menu_item_parent', true ) );
				}
				if ( 'post' === $type ) {
					// Children before parents (deleting a parent re-parents children otherwise).
					usort( $list, static fn( $a, $b ) => (int) ( get_post( $b['object_id'] )->post_parent ?? 0 ) <=> (int) ( get_post( $a['object_id'] )->post_parent ?? 0 ) );
				}
				foreach ( $list as $row ) {
					self::row( $row, $run_id, $force, $dry_run, $report, $log );
				}
			}
			if ( ! $dry_run ) {
				State::mark_rolled_back( $run_id, $report );
				flush_rewrite_rules( false );
			}
		} catch ( \Throwable $e ) {
			$report['errors'][] = $e->getMessage();
			$log->error( 'rollback', '', 'FATAL ' . $e->getMessage() );
		} finally {
			Map::release_lock( $token );
		}
		$log->info( 'rollback', '', sprintf( 'Rollback finished: %d deleted, %d restored, %d skipped, %d errors.', count( $report['deleted'] ), count( $report['restored'] ), count( $report['skipped'] ), count( $report['errors'] ) ) );
		return $report;
	}

	private static function bucket( string $object_type ): string {
		if ( in_array( $object_type, [ 'reading', 'option', 'redirect', 'nav_menu_item', 'nav_menu', 'attachment', 'term' ], true ) ) {
			return $object_type;
		}
		return 'post';
	}

	private static function row( array $row, string $run_id, bool $force, bool $dry_run, array &$report, Log $log ): void {
		$key     = (string) $row['source_key'];
		$type    = self::bucket( (string) $row['object_type'] );
		$created = $row['created_by_run'] === $run_id;
		$before  = $row['before_data'][ $run_id ] ?? [];
		$sens    = self::is_sensitive_key( $key );

		if ( in_array( $type, [ 'reading', 'option', 'redirect' ], true ) ) {
			self::restore_setting( $row, $run_id, $type, $created, $before, $force, $dry_run, $report, $log );
			return;
		}

		$id = (int) $row['object_id'];
		if ( $created ) {
			if ( $id <= 0 || ! self::exists( $type, $id ) ) {
				if ( ! $dry_run ) {
					Map::delete( $key );
				}
				$report['deleted'][] = [ 'key' => $key, 'note' => 'already gone' ];
				return;
			}
			$modified = Reconcile::modified_fields( $row, self::current( $type, $id, $row ) );
			if ( 'nav_menu' === $type ) {
				$extra = self::foreign_menu_items( $id, $run_id );
				if ( $extra ) {
					$modified[] = sprintf( '%d editor-added item(s)', $extra );
				}
			}
			if ( $modified && ! $force ) {
				$report['skipped'][] = [ 'key' => $key, 'reason' => 'modified: ' . implode( ', ', $modified ) ];
				$log->warn( 'rollback', $key, 'skipped (modified: ' . implode( ', ', $modified ) . ')', $sens );
				return;
			}
			if ( ! $dry_run ) {
				$ok = self::delete( $type, $id );
				if ( ! $ok ) {
					$report['errors'][] = sprintf( '%s: delete failed', $key );
					$log->error( 'rollback', $key, 'delete failed', $sens );
					return;
				}
				Map::delete( $key );
			}
			$report['deleted'][] = [ 'key' => $key, 'id' => $id, 'type' => $type ] + ( $modified ? [ 'forced' => true ] : [] );
			$log->info( 'rollback', $key, sprintf( 'deleted %s #%d%s', $type, $id, $modified ? ' (forced)' : '' ), $sens );
			return;
		}

		// Updated (or adopted) by the run: restore the pre-image of the fields we changed.
		if ( ! $before ) {
			return;
		}
		if ( $id <= 0 || ! self::exists( $type, $id ) ) {
			$report['skipped'][] = [ 'key' => $key, 'reason' => 'object no longer exists' ];
			return;
		}
		$current  = self::current( $type, $id, $row, array_keys( $before ) );
		$modified = [];
		foreach ( $before as $f => $v ) {
			$h = $row['field_hashes'][ $f ]['db'] ?? null;
			if ( null !== $h && array_key_exists( $f, $current ) && Hash::of( $current[ $f ] ) !== $h ) {
				$modified[] = (string) $f;
			}
		}
		if ( $modified && ! $force ) {
			$report['skipped'][] = [ 'key' => $key, 'reason' => 'modified since import: ' . implode( ', ', $modified ) ];
			$log->warn( 'rollback', $key, 'restore skipped (modified: ' . implode( ', ', $modified ) . ')', $sens );
			return;
		}
		if ( ! $dry_run ) {
			$r = self::restore_fields( $type, $id, $before, $row );
			if ( is_wp_error( $r ) ) {
				$report['errors'][] = sprintf( '%s: %s', $key, $r->get_error_message() );
				return;
			}
			$hashes = $row['field_hashes'];
			$after  = self::current( $type, $id, $row, array_keys( $before ) );
			foreach ( $before as $f => $v ) {
				unset( $hashes[ $f ] );
				if ( array_key_exists( $f, $after ) ) {
					$hashes[ $f ] = [ 'db' => Hash::of( $after[ $f ] ), 'src' => '' ];
				}
			}
			$pre = $row['before_data'];
			unset( $pre[ $run_id ] );
			Map::overwrite( $key, $hashes, $pre );
		}
		$report['restored'][] = [ 'key' => $key, 'id' => $id, 'fields' => array_keys( $before ) ];
		$log->info( 'rollback', $key, 'restored ' . implode( ', ', array_keys( $before ) ), $sens );
	}

	/* ------------------------------------------------------- settings rows */

	private static function restore_setting( array $row, string $run_id, string $type, bool $created, array $before, bool $force, bool $dry_run, array &$report, Log $log ): void {
		$key = (string) $row['source_key'];

		if ( 'redirect' === $type ) {
			$from   = Redirects::normalize( substr( $key, strlen( 'redirect:' ) ) );
			$option = Redirects::option();
			$rule   = $option['rules'][ $from ] ?? null;
			$h      = $row['field_hashes']['rule']['db'] ?? null;
			$now    = $rule ? Hash::of( Redirects::comparable( $rule ) ) : Hash::of( null );
			if ( null !== $h && $now !== $h && ! $force ) {
				$report['skipped'][] = [ 'key' => $key, 'reason' => 'rule edited since import' ];
				return;
			}
			if ( $created ) {
				if ( ! $dry_run ) {
					unset( $option['rules'][ $from ] );
					Redirects::save_option( $option );
					Map::delete( $key );
				}
				$report['deleted'][] = [ 'key' => $key, 'type' => 'redirect' ];
				$log->info( 'rollback', $key, 'removed redirect rule' );
				return;
			}
			if ( array_key_exists( 'rule', $before ) ) {
				if ( ! $dry_run ) {
					if ( is_array( $before['rule'] ) ) {
						$option['rules'][ $from ] = $before['rule'];
					} else {
						unset( $option['rules'][ $from ] );
					}
					Redirects::save_option( $option );
					$pre = $row['before_data'];
					unset( $pre[ $run_id ] );
					Map::overwrite( $key, [], $pre );
				}
				$report['restored'][] = [ 'key' => $key, 'fields' => [ 'rule' ] ];
				$log->info( 'rollback', $key, 'restored redirect rule' );
			}
			return;
		}

		if ( ! $before ) {
			return;
		}

		if ( 'reading' === $type ) {
			$current  = Reading::current();
			$modified = [];
			foreach ( $before as $f => $v ) {
				$h = $row['field_hashes'][ $f ]['db'] ?? null;
				if ( null !== $h && isset( $current[ $f ] ) && Hash::of( $current[ $f ] ) !== $h ) {
					$modified[] = (string) $f;
				}
			}
			if ( $modified && ! $force ) {
				$report['skipped'][] = [ 'key' => $key, 'reason' => 'changed since import: ' . implode( ', ', $modified ) ];
				return;
			}
			if ( ! $dry_run ) {
				// show_on_front last is irrelevant here; core validates absint on the page ids.
				foreach ( [ 'page_on_front', 'page_for_posts', 'show_on_front', 'posts_per_page' ] as $f ) {
					if ( array_key_exists( $f, $before ) ) {
						update_option( $f, $before[ $f ] );
					}
				}
				self::forget_run( $row, $run_id, array_keys( $before ), Reading::current() );
			}
			$report['restored'][] = [ 'key' => $key, 'fields' => array_keys( $before ) ];
			$log->info( 'rollback', $key, 'restored ' . implode( ', ', array_keys( $before ) ) );
			return;
		}

		// option:<name>
		$name    = substr( $key, strlen( 'option:' ) );
		$raw     = get_option( $name, null );
		$grouped = ! ( 1 === count( $before ) && array_key_exists( 'value', $before ) && ! ( is_array( $raw ) && ! array_is_list( $raw ) && array_key_exists( 'value', $raw ) ) );
		$current = $grouped ? ( is_array( $raw ) ? $raw : [] ) : [ 'value' => $raw ];
		$modified = [];
		foreach ( $before as $f => $v ) {
			$h = $row['field_hashes'][ $f ]['db'] ?? null;
			if ( null !== $h && Hash::of( $current[ $f ] ?? null ) !== $h ) {
				$modified[] = (string) $f;
			}
		}
		if ( $modified && ! $force ) {
			$report['skipped'][] = [ 'key' => $key, 'reason' => 'changed since import: ' . implode( ', ', $modified ) ];
			return;
		}
		if ( ! $dry_run ) {
			if ( $grouped ) {
				$new = is_array( $raw ) ? $raw : [];
				foreach ( $before as $group => $v ) {
					if ( null === $v ) {
						unset( $new[ $group ] );
					} else {
						$new[ $group ] = $v;
					}
				}
				if ( [] === $new && ! is_array( $raw ) ) {
					delete_option( $name );
				} else {
					update_option( $name, $new );
				}
			} elseif ( null === $before['value'] ) {
				delete_option( $name );
			} else {
				update_option( $name, $before['value'] );
			}
			$after_raw = get_option( $name, null );
			self::forget_run( $row, $run_id, array_keys( $before ), $grouped ? ( is_array( $after_raw ) ? $after_raw : [] ) : [ 'value' => $after_raw ] );
		}
		$report['restored'][] = [ 'key' => $key, 'fields' => array_keys( $before ) ];
		$log->info( 'rollback', $key, 'restored ' . implode( ', ', array_keys( $before ) ) );
	}

	/**
	 * Drop the run's pre-image and re-hash the restored fields.
	 */
	private static function forget_run( array $row, string $run_id, array $fields, array $after ): void {
		$hashes = $row['field_hashes'];
		foreach ( $fields as $f ) {
			unset( $hashes[ $f ] );
			if ( array_key_exists( $f, $after ) ) {
				$hashes[ $f ] = [ 'db' => Hash::of( $after[ $f ] ), 'src' => '' ];
			}
		}
		$pre = $row['before_data'];
		unset( $pre[ $run_id ] );
		Map::overwrite( (string) $row['source_key'], $hashes, $pre );
	}

	/* -------------------------------------------------------------- objects */

	private static function exists( string $type, int $id ): bool {
		return match ( $type ) {
			'term'     => (bool) get_term( $id ),
			'nav_menu' => (bool) wp_get_nav_menu_object( $id ),
			default    => (bool) get_post( $id ),
		};
	}

	private static function current( string $type, int $id, array $row, ?array $only = null ): array {
		$fields = $only ?? array_keys( $row['field_hashes'] ?? [] );
		switch ( $type ) {
			case 'term':
				$term = get_term( $id );
				$cur  = $term && ! is_wp_error( $term ) ? Terms::current( $id, $term->taxonomy ) : [];
				break;
			case 'nav_menu':
				$cur = Menus::current_locations( array_values( array_filter( $fields, static fn( $f ) => str_starts_with( (string) $f, 'location:' ) ) ) );
				break;
			case 'nav_menu_item':
				$cur = Menus::current_item( $id );
				break;
			case 'attachment':
				$cur           = MediaFiles::current( $id );
				$cur['parent'] = (int) ( get_post( $id )->post_parent ?? 0 );
				break;
			default:
				$cur = PostFields::current( $id, array_map( 'strval', $fields ) );
		}
		return array_intersect_key( $cur, array_flip( array_map( 'strval', $fields ) ) );
	}

	private static function delete( string $type, int $id ): bool {
		switch ( $type ) {
			case 'term':
				$term = get_term( $id );
				if ( ! $term || is_wp_error( $term ) ) {
					return true;
				}
				$r = wp_delete_term( $id, $term->taxonomy );
				return true === $r;
			case 'nav_menu':
				return (bool) wp_delete_nav_menu( $id );
			case 'attachment':
				return (bool) wp_delete_attachment( $id, true );
			default:
				return (bool) wp_delete_post( $id, true );
		}
	}

	private static function restore_fields( string $type, int $id, array $before, array $row ): true|WP_Error {
		switch ( $type ) {
			case 'term':
				$term = get_term( $id );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'hk9_rollback', 'term missing' );
				}
				$r = wp_update_term( $id, $term->taxonomy, wp_slash( $before ) );
				return is_wp_error( $r ) ? $r : true;
			case 'nav_menu':
				$locations = get_theme_mod( 'nav_menu_locations' );
				$locations = is_array( $locations ) ? $locations : [];
				foreach ( $before as $f => $v ) {
					if ( str_starts_with( (string) $f, 'location:' ) ) {
						$loc = substr( (string) $f, 9 );
						if ( (int) $v > 0 ) {
							$locations[ $loc ] = (int) $v;
						} else {
							unset( $locations[ $loc ] );
						}
					}
				}
				set_theme_mod( 'nav_menu_locations', $locations );
				return true;
			case 'nav_menu_item':
				$menu   = wp_get_post_terms( $id, 'nav_menu', [ 'fields' => 'ids' ] );
				$menu   = is_array( $menu ) && $menu ? (int) $menu[0] : 0;
				$merged = array_merge( Menus::current_item( $id ), $before );
				$r      = wp_update_nav_menu_item(
					$menu,
					$id,
					[
						'menu-item-status'    => 'publish',
						'menu-item-type'      => $merged['kind'],
						'menu-item-object'    => 'post_type' === $merged['kind'] ? $merged['object'] : 'custom',
						'menu-item-object-id' => 'post_type' === $merged['kind'] ? (int) $merged['object_id'] : 0,
						'menu-item-url'       => 'custom' === $merged['kind'] ? (string) $merged['url'] : '',
						'menu-item-title'     => (string) $merged['title'],
						'menu-item-target'    => (string) $merged['target'],
						'menu-item-classes'   => implode( ' ', (array) $merged['classes'] ),
						'menu-item-parent-id' => (int) $merged['parent'],
						'menu-item-position'  => (int) $merged['order'],
					]
				);
				return is_wp_error( $r ) ? $r : true;
			case 'attachment':
				$fields = $before;
				if ( array_key_exists( 'parent', $fields ) ) {
					wp_update_post( [ 'ID' => $id, 'post_parent' => (int) $fields['parent'] ] );
					unset( $fields['parent'] );
				}
				return $fields ? MediaFiles::apply( $id, $fields ) : true;
			default:
				return PostFields::apply( $id, $before );
		}
	}

	private static function foreign_menu_items( int $menu_id, string $run_id ): int {
		$items = wp_get_nav_menu_items( $menu_id, [ 'post_status' => 'any' ] );
		if ( ! is_array( $items ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $items as $item ) {
			$row = Map::get_by_object( 'nav_menu_item', (int) $item->ID );
			if ( ! $row || $row['created_by_run'] !== $run_id ) {
				++$n;
			}
		}
		return $n;
	}

	private static function is_sensitive_key( string $key ): bool {
		return str_contains( $key, ':barkode:' );
	}
}
