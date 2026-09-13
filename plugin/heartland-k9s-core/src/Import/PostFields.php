<?php
/**
 * Post-like records: desired values, current values, and the single-call write.
 *
 * Field names used in map rows: title, slug, status, parent, template, excerpt,
 * date, menu_order, featured, content, post_type, meta:<key>, terms:<taxonomy>.
 * A meta field reads as null when the key is absent (and writing null deletes
 * it), so a rollback of an adopted page restores "no meta" exactly.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

use HK9\Core\Import\Steps\Validate;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class PostFields {

	/**
	 * Resolved values for the hierarchy pass. `{{post_url}}` tokens in meta are
	 * deferred to the content pass (URLs are not final yet).
	 *
	 * @return array{fields:array,deferred:string[],warnings:string[]}|WP_Error
	 */
	public static function desired_hierarchy( array $record, Tokens $tokens ): array|WP_Error {
		$fields   = [];
		$warnings = [];
		$deferred = [];

		$fields['title']  = (string) ( $record['title'] ?? '' );
		$fields['slug']   = sanitize_title( (string) ( $record['slug'] ?? '' ) );
		$fields['status'] = (string) ( $record['status'] ?? 'publish' );

		$parent = 0;
		if ( ! empty( $record['parent'] ) ) {
			$p = $tokens->bake( (string) $record['parent'] );
			if ( is_wp_error( $p ) ) {
				return $p;
			}
			$parent = (int) $p;
		}
		$fields['parent'] = $parent;

		if ( array_key_exists( 'template', $record ) && 'page' === ( $record['type'] ?? '' ) ) {
			$fields['template'] = Validate::normalise_template( (string) $record['template'] );
		}
		if ( array_key_exists( 'excerpt', $record ) ) {
			$fields['excerpt'] = (string) $record['excerpt'];
		}
		$date = Manifest::date_pair( $record['date'] ?? null );
		if ( is_wp_error( $date ) ) {
			return $date;
		}
		if ( $date ) {
			$fields['date'] = $date['gmt'];
		}
		if ( array_key_exists( 'menu_order', $record ) ) {
			$fields['menu_order'] = (int) $record['menu_order'];
		}
		if ( array_key_exists( 'featured', $record ) ) {
			$featured = 0;
			if ( '' !== (string) $record['featured'] ) {
				$f = $tokens->bake( (string) $record['featured'] );
				if ( is_wp_error( $f ) ) {
					return $f;
				}
				$featured = (int) $f;
			}
			$fields['featured'] = $featured;
		}

		foreach ( (array) ( $record['meta'] ?? [] ) as $mkey => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$value = Manifest::cast_meta( $entry );
			if ( Tokens::has_kind( $value, [ 'post_url' ] ) ) {
				$deferred[] = (string) $mkey;
				continue;
			}
			$baked = $tokens->bake( $value );
			if ( is_wp_error( $baked ) ) {
				return $baked;
			}
			$fields[ 'meta:' . $mkey ] = $baked;
		}

		foreach ( (array) ( $record['terms'] ?? [] ) as $tax => $list ) {
			$tax = (string) $tax;
			if ( ! taxonomy_exists( $tax ) ) {
				continue; // Warned in validate.
			}
			$ids = [];
			foreach ( (array) $list as $ref ) {
				$ref = (string) $ref;
				if ( Tokens::contains( $ref ) ) {
					$id = $tokens->bake( $ref );
					if ( is_wp_error( $id ) ) {
						return $id;
					}
					$ids[] = (int) $id;
					continue;
				}
				$term = get_term_by( 'slug', sanitize_title( $ref ), $tax );
				if ( $term ) {
					$ids[] = (int) $term->term_id;
				} else {
					$warnings[] = sprintf( 'Term "%s" (%s) does not exist; skipped.', $ref, $tax );
				}
			}
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			sort( $ids );
			$fields[ 'terms:' . $tax ] = $ids;
		}

		return [
			'fields'   => $fields,
			'deferred' => $deferred,
			'warnings' => $warnings,
		];
	}

	/**
	 * Resolved values for the content pass: baked content + deferred meta.
	 *
	 * @return array{fields:array,blocks:array{0:int,1:int}}|WP_Error
	 */
	public static function desired_content( array $record, Manifest $manifest, Tokens $tokens ): array|WP_Error {
		$fields = [];
		$blocks = [ 0, 0 ];

		$raw = $manifest->content( $record );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) ) {
			$baked = $tokens->bake( $raw );
			if ( is_wp_error( $baked ) ) {
				return $baked;
			}
			if ( Tokens::has_unbaked( (string) $baked ) ) {
				return new WP_Error( 'hk9_unbaked', __( 'Content still contains unresolved {{tokens}} after baking.', 'heartland-k9s-core' ) );
			}
			$blocks = [ count( parse_blocks( $raw ) ), count( parse_blocks( (string) $baked ) ) ];
			if ( $blocks[0] !== $blocks[1] ) {
				return new WP_Error( 'hk9_block_count', sprintf( /* translators: 1: blocks before, 2: blocks after */ __( 'Block count changed while baking tokens (%1$d -> %2$d).', 'heartland-k9s-core' ), $blocks[0], $blocks[1] ) );
			}
			$fields['content'] = (string) $baked;
		}

		foreach ( (array) ( $record['meta'] ?? [] ) as $mkey => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$value = Manifest::cast_meta( $entry );
			if ( ! Tokens::has_kind( $value, [ 'post_url' ] ) ) {
				continue;
			}
			$baked = $tokens->bake( $value );
			if ( is_wp_error( $baked ) ) {
				return $baked;
			}
			$fields[ 'meta:' . $mkey ] = $baked;
		}

		return [
			'fields' => $fields,
			'blocks' => $blocks,
		];
	}

	/**
	 * Pre-flight for the hierarchy pass: will the content pass be able to bake this
	 * record? Every token in the content file and in deferred (`{{post_url}}`) meta
	 * must resolve now — media/term ids directly, post URLs as post ids (the stub
	 * exists and its record has not failed) — and the block structure must survive
	 * baking. Failing here keeps the stub an unpublished draft instead of publishing
	 * a page with empty content and fixing it up later.
	 */
	public static function preflight_content( array $record, Manifest $manifest, Tokens $tokens ): true|WP_Error {
		$raw = $manifest->content( $record );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$post_url_keys = [];
		if ( is_string( $raw ) ) {
			$partial = $tokens->bake( $raw, [ 'post_url' ] );
			if ( is_wp_error( $partial ) ) {
				return $partial;
			}
			if ( count( parse_blocks( $raw ) ) !== count( parse_blocks( (string) $partial ) ) ) {
				return new WP_Error( 'hk9_block_count', __( 'Block count changes while baking tokens.', 'heartland-k9s-core' ) );
			}
			foreach ( Tokens::extract( $raw ) as [ $kind, $tkey ] ) {
				if ( 'post_url' === $kind ) {
					$post_url_keys[ $tkey ] = true;
				}
			}
		}
		foreach ( (array) ( $record['meta'] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			foreach ( Tokens::extract( Manifest::cast_meta( $entry ) ) as [ $kind, $tkey ] ) {
				if ( 'post_url' === $kind ) {
					$post_url_keys[ $tkey ] = true;
				}
			}
		}
		foreach ( array_keys( $post_url_keys ) as $tkey ) {
			$id = $tokens->resolve( 'post', (string) $tkey );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}
		return true;
	}

	/**
	 * Fields the hierarchy pass writes to an ADOPTED post: everything the payload
	 * defines except the publish date (an adopted page keeps its id, slug, date
	 * and author; the slug is equal by construction).
	 */
	public static function for_adopted( array $fields ): array {
		unset( $fields['date'] );
		return $fields;
	}

	/**
	 * Current database values for the named fields.
	 */
	public static function current( int $id, array $fields ): array {
		$post = get_post( $id );
		if ( ! $post ) {
			return [];
		}
		$out = [];
		foreach ( $fields as $f ) {
			if ( str_starts_with( $f, 'meta:' ) ) {
				$mk        = substr( $f, 5 );
				$out[ $f ] = metadata_exists( 'post', $id, $mk ) ? get_post_meta( $id, $mk, true ) : null;
				continue;
			}
			if ( str_starts_with( $f, 'terms:' ) ) {
				$ids = wp_get_object_terms( $id, substr( $f, 6 ), [ 'fields' => 'ids' ] );
				$ids = is_wp_error( $ids ) ? [] : array_map( 'intval', $ids );
				sort( $ids );
				$out[ $f ] = $ids;
				continue;
			}
			$out[ $f ] = match ( $f ) {
				'title'      => (string) $post->post_title,
				'slug'       => (string) $post->post_name,
				'status'     => (string) $post->post_status,
				'parent'     => (int) $post->post_parent,
				'template'   => ( (string) get_post_meta( $id, '_wp_page_template', true ) ) ?: 'default',
				'excerpt'    => (string) $post->post_excerpt,
				'date'       => (string) $post->post_date_gmt,
				'menu_order' => (int) $post->menu_order,
				'featured'   => (int) get_post_meta( $id, '_thumbnail_id', true ),
				'content'    => (string) $post->post_content,
				'post_type'  => (string) $post->post_type,
				default      => null,
			};
		}
		return $out;
	}

	/**
	 * Write the given fields in ONE wp_update_post() call (meta via meta_input so
	 * the revision created by that update carries the section meta), then terms.
	 */
	public static function apply( int $id, array $apply ): true|WP_Error {
		$args        = [ 'ID' => $id ];
		$meta        = [];
		$delete_meta = [];
		$terms       = [];
		$unset_thumb = false;

		foreach ( $apply as $f => $v ) {
			if ( str_starts_with( $f, 'meta:' ) ) {
				if ( null === $v ) {
					$delete_meta[] = substr( $f, 5 ); // Pre-image "key absent" (rollback of an adopted page).
				} else {
					$meta[ substr( $f, 5 ) ] = $v;
				}
				continue;
			}
			if ( str_starts_with( $f, 'terms:' ) ) {
				$terms[ substr( $f, 6 ) ] = (array) $v;
				continue;
			}
			switch ( $f ) {
				case 'title':
					$args['post_title'] = $v;
					break;
				case 'slug':
					$args['post_name'] = $v;
					break;
				case 'status':
					$args['post_status'] = $v;
					break;
				case 'parent':
					$args['post_parent'] = (int) $v;
					break;
				case 'excerpt':
					$args['post_excerpt'] = $v;
					break;
				case 'menu_order':
					$args['menu_order'] = (int) $v;
					break;
				case 'content':
					$args['post_content'] = $v;
					break;
				case 'post_type':
					// Only ever page <-> hk9_barkode (existing-site adoption and its rollback).
					if ( '' !== (string) $v && post_type_exists( (string) $v ) ) {
						$args['post_type'] = (string) $v;
					}
					break;
				case 'date':
					if ( '' !== (string) $v ) {
						$args['post_date_gmt'] = $v;
						$args['post_date']     = get_date_from_gmt( (string) $v );
						$args['edit_date']     = true;
					}
					break;
				case 'template':
					// NOT via meta_input: wp_update_post() merges the post's current page_template
					// (WP_Post::__get reads the meta) and wp_insert_post() re-writes
					// _wp_page_template from it AFTER meta_input, silently reverting the change.
					$args['page_template'] = '' !== (string) $v ? (string) $v : 'default';
					break;
				case 'featured':
					if ( (int) $v > 0 ) {
						$meta['_thumbnail_id'] = (int) $v;
					} else {
						$unset_thumb = true;
					}
					break;
			}
		}

		if ( $meta ) {
			$args['meta_input'] = $meta;
		}
		if ( count( $args ) > 1 ) {
			// wp_update_post() re-validates the page template (the one being written, or the one
			// already stored) against the active theme and returns WP_Error (after writing the
			// row) when the file is absent. A template the theme does not ship yet is a warning,
			// not a failure: whitelist both the current and the new value for this write.
			$templates = array_filter(
				array_unique(
					[
						(string) ( $args['page_template'] ?? '' ),
						(string) get_post_meta( $id, '_wp_page_template', true ),
					]
				),
				static fn( string $t ): bool => '' !== $t && 'default' !== $t
			);
			$allow     = static function ( $list ) use ( $templates ) {
				if ( ! is_array( $list ) ) {
					return $list;
				}
				foreach ( $templates as $t ) {
					if ( ! isset( $list[ $t ] ) ) {
						$list[ $t ] = 'Heartland (template file not in theme yet)';
					}
				}
				return $list;
			};
			add_filter( 'theme_page_templates', $allow, 999 );
			$r = wp_update_post( wp_slash( $args ), true );
			remove_filter( 'theme_page_templates', $allow, 999 );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( $unset_thumb ) {
			delete_post_meta( $id, '_thumbnail_id' );
		}
		foreach ( $delete_meta as $mk ) {
			delete_post_meta( $id, $mk );
		}
		foreach ( $terms as $tax => $ids ) {
			$r = wp_set_object_terms( $id, array_map( 'intval', $ids ), $tax, false );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		clean_post_cache( $id );
		return true;
	}
}
