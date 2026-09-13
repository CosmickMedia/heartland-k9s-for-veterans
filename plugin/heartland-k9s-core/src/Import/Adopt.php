<?php
/**
 * "Existing site" adoption: find the pre-existing object a payload record
 * describes so the importer binds it instead of creating a duplicate.
 *
 * The live heartlandk9s.org site carries the same pages (same post ids and
 * slugs the `live:page:<id>` / `live:barkode:<id>` keys were extracted from)
 * and the same attachments (`live:media:<id>`). Rules, in order:
 *
 *   page records
 *     live:page:<id>   -> the page with that id when it is not trashed and its
 *                         slug equals the record slug; when the id exists but the
 *                         slug differs, fall back to the slug (and warn)
 *     any page record  -> the page at the record's path (root slug, or
 *                         parent-slug/child-slug for records with a parent)
 *   hk9_barkode records (live:barkode:<id>)
 *     the PAGE with that id (or, failing that, the page at the record slug);
 *     the importer converts it in place (post_type) so id + slug survive —
 *     or a post that ALREADY IS an hk9_barkode at that id / slug (a page an
 *     earlier run converted whose map row is gone: a crash between the
 *     conversion and the binding, a reset map, a plugin re-install), which is
 *     re-adopted as it is (no conversion)
 *   attachments (live:media:<id>)
 *     the attachment with that id when the basename of its attached file (or
 *     of its original image) equals the payload file's basename and the file
 *     exists on disk; otherwise the sha256 index applies (MediaFiles)
 *
 * A matched post whose status differs from the record's (a private or draft
 * page holding a payload slug) is still adopted — the first bind applies the
 * payload status — but the candidate carries a warning so the visibility change
 * is never silent. Never adopted: trashed posts, posts already bound to another
 * payload key, posts of another type (a non-page is never converted into a
 * record). Every method here is read-only; the steps do the binding.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Adopt {

	public const CONVERT_TYPE = 'hk9_barkode';

	/**
	 * Post-like record -> adoptable post.
	 *
	 * @return array{id:int, how:string, convert:bool, post_type:string, warning:string} id 0 when nothing matches.
	 *               how: 'id' | 'slug' | ''. convert: the match is a page that must become the record type.
	 *               post_type: the matched post's current type ('' when nothing matches).
	 */
	public static function post_candidate( Manifest $manifest, string $key, array $record ): array {
		$type = (string) ( $record['type'] ?? '' );
		$none = [
			'id'        => 0,
			'how'       => '',
			'convert'   => false,
			'post_type' => '',
			'warning'   => '',
		];
		if ( 'page' !== $type && self::CONVERT_TYPE !== $type ) {
			return $none;
		}
		$slug = sanitize_title( (string) ( $record['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return $none;
		}
		$status   = (string) ( $record['status'] ?? 'publish' );
		$warnings = [];

		// 1. Live id (live:page:<id> / live:barkode:<id>): the match must be a page — or, for a
		//    record type, a post already of that type — with the same slug.
		$live_id = self::live_id( $key );
		if ( $live_id > 0 ) {
			$post = get_post( $live_id );
			if ( $post instanceof \WP_Post && 'trash' !== $post->post_status ) {
				if ( 'page' === $post->post_type || $post->post_type === $type ) {
					if ( $post->post_name === $slug ) {
						$blocked = self::blocked( $post, $key );
						if ( '' === $blocked ) {
							return self::match( $post, 'id', $type, $status, $warnings );
						}
						$warnings[] = sprintf( '%s #%d has the record slug but %s; matching by slug instead.', self::label( $post ), $live_id, $blocked );
					} else {
						$warnings[] = sprintf( '%s #%d exists but its slug "%s" differs from the payload slug "%s"; matching by slug instead.', self::label( $post ), $live_id, (string) $post->post_name, $slug );
					}
				} else {
					$warnings[] = sprintf( 'Post #%d exists but is of type "%s", not a page; matching by slug instead.', $live_id, (string) $post->post_type );
				}
			}
		}

		// 2. Slug / path (a record with a parent lives under parent-slug/child-slug): a page first ...
		$path = self::path_of( $manifest, $record );
		$page = '' !== $path ? get_page_by_path( $path, OBJECT, 'page' ) : null;
		if ( $page instanceof \WP_Post && 'trash' !== $page->post_status && 'page' === $page->post_type && '' === self::blocked( $page, $key ) ) {
			return self::match( $page, 'slug', $type, $status, $warnings );
		}
		// ... then, for a record type, a post that already is one at that slug (converted earlier, binding lost).
		if ( 'page' !== $type ) {
			foreach ( self::posts_at_slug( $type, $slug ) as $post ) {
				if ( '' === self::blocked( $post, $key ) ) {
					return self::match( $post, 'slug', $type, $status, $warnings );
				}
			}
		}
		$none['warning'] = implode( ' ', $warnings );
		return $none;
	}

	/**
	 * Build the candidate for a matched post: a page matched by a record type is
	 * converted; a post already of the record type is taken as it is. A status
	 * that differs from the payload's is reported (the first bind applies it).
	 */
	private static function match( \WP_Post $post, string $how, string $type, string $status, array $warnings ): array {
		if ( (string) $post->post_status !== $status ) {
			$warnings[] = sprintf( '%s #%d is "%s" on this site; the payload sets it to "%s" on the first import (roll the run back to restore it).', self::label( $post ), (int) $post->ID, (string) $post->post_status, $status );
		}
		return [
			'id'        => (int) $post->ID,
			'how'       => $how,
			'convert'   => $post->post_type !== $type,
			'post_type' => (string) $post->post_type,
			'warning'   => implode( ' ', $warnings ),
		];
	}

	/**
	 * "Page" for a page, the post type otherwise (log/warning prefix).
	 */
	private static function label( \WP_Post $post ): string {
		return 'page' === $post->post_type ? 'Page' : (string) $post->post_type;
	}

	/**
	 * Non-trashed posts of one type at a slug (non-hierarchical record types: the
	 * slug is unique per type, so at most one is expected).
	 *
	 * @return \WP_Post[]
	 */
	private static function posts_at_slug( string $type, string $slug ): array {
		if ( ! post_type_exists( $type ) ) {
			return [];
		}
		$posts = get_posts(
			[
				'post_type'        => $type,
				'name'             => $slug,
				'post_status'      => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'numberposts'      => 5,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			]
		);
		$out   = [];
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post && $post->post_name === $slug && $post->post_type === $type ) {
				$out[] = $post;
			}
		}
		return $out;
	}

	/**
	 * Attachment record (live:media:<id>) -> the attachment with that id when its
	 * file has the payload file's basename (case-insensitive) and exists on disk.
	 *
	 * @return int Attachment id or 0.
	 */
	public static function attachment_candidate( string $key, array $record ): int {
		$live_id = self::live_id( $key );
		if ( $live_id <= 0 || ! str_starts_with( $key, 'live:media:' ) ) {
			return 0;
		}
		$post = get_post( $live_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type || 'trash' === $post->post_status ) {
			return 0;
		}
		if ( '' !== self::blocked( $post, $key ) ) {
			return 0;
		}
		$want = strtolower( basename( (string) ( $record['file'] ?? '' ) ) );
		if ( '' === $want ) {
			return 0;
		}
		$attached = (string) get_post_meta( $live_id, '_wp_attached_file', true );
		$names    = [ strtolower( basename( $attached ) ) ];
		$meta     = wp_get_attachment_metadata( $live_id );
		if ( is_array( $meta ) && ! empty( $meta['original_image'] ) ) {
			$names[] = strtolower( basename( (string) $meta['original_image'] ) );
		}
		if ( ! in_array( $want, $names, true ) ) {
			return 0;
		}
		$file = get_attached_file( $live_id, true );
		if ( ! $file || ! is_file( $file ) ) {
			return 0;
		}
		return $live_id;
	}

	/**
	 * Numeric live id carried by a `live:<kind>:<id>` key (0 when the key has none).
	 */
	public static function live_id( string $key ): int {
		if ( preg_match( '/^live:(?:page|barkode|media):(\d+)$/', $key, $m ) ) {
			return (int) $m[1];
		}
		return 0;
	}

	/**
	 * Hierarchical path of a record (slugs of its parent chain, parent first).
	 */
	public static function path_of( Manifest $manifest, array $record ): string {
		$parts = [];
		$seen  = [];
		$cur   = $record;
		for ( $i = 0; $i < 10 && is_array( $cur ); $i++ ) {
			$slug = sanitize_title( (string) ( $cur['slug'] ?? '' ) );
			if ( '' === $slug ) {
				return '';
			}
			array_unshift( $parts, $slug );
			$parent = Tokens::single_key( (string) ( $cur['parent'] ?? '' ), 'post' );
			if ( null === $parent || isset( $seen[ $parent ] ) ) {
				break;
			}
			$seen[ $parent ] = true;
			$cur             = $manifest->get( $parent );
		}
		return implode( '/', $parts );
	}

	/**
	 * Why a post may not be adopted for $key ('' when it may): bound to another key
	 * in the map, or carrying another key's source marker.
	 */
	private static function blocked( \WP_Post $post, string $key ): string {
		$bound = Map::bound_post_key( (int) $post->ID );
		if ( null !== $bound && $bound !== $key ) {
			return sprintf( 'is already imported as %s', $bound );
		}
		$marker = (string) get_post_meta( (int) $post->ID, '_hk9_source_key', true );
		if ( '' !== $marker && $marker !== $key ) {
			return sprintf( 'is marked as %s', $marker );
		}
		return '';
	}
}
