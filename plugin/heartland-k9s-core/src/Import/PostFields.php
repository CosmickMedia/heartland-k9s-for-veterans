<?php
/**
 * Post-like records: desired values, current values, and the single-call write.
 *
 * Field names used in map rows: title, slug, status, parent, template, excerpt,
 * date, menu_order, featured, content, meta:<key>, terms:<taxonomy>.
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
				return new WP_Error( 'hk9_unbaked', 'Content still contains unresolved {{tokens}} after baking.' );
			}
			$blocks = [ count( parse_blocks( $raw ) ), count( parse_blocks( (string) $baked ) ) ];
			if ( $blocks[0] !== $blocks[1] ) {
				return new WP_Error( 'hk9_block_count', sprintf( 'Block count changed while baking tokens (%d -> %d).', $blocks[0], $blocks[1] ) );
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
				$out[ $f ] = get_post_meta( $id, substr( $f, 5 ), true );
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
		$args  = [ 'ID' => $id ];
		$meta  = [];
		$terms = [];
		$unset_thumb = false;

		foreach ( $apply as $f => $v ) {
			if ( str_starts_with( $f, 'meta:' ) ) {
				$meta[ substr( $f, 5 ) ] = $v;
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
				case 'date':
					if ( '' !== (string) $v ) {
						$args['post_date_gmt'] = $v;
						$args['post_date']     = get_date_from_gmt( (string) $v );
						$args['edit_date']     = true;
					}
					break;
				case 'template':
					$meta['_wp_page_template'] = $v;
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
			// wp_update_post() re-validates the page template stored on the post against the active
			// theme and returns WP_Error (after writing the row) when the file is absent. A template
			// the theme does not ship yet is a warning, not a failure: whitelist it for this write.
			$template = (string) ( $meta['_wp_page_template'] ?? get_post_meta( $id, '_wp_page_template', true ) );
			$allow    = static function ( $templates ) use ( $template ) {
				if ( '' !== $template && 'default' !== $template && is_array( $templates ) && ! isset( $templates[ $template ] ) ) {
					$templates[ $template ] = 'Heartland (template file not in theme yet)';
				}
				return $templates;
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
