<?php
/**
 * BarKode "Legacy path" → redirect rule.
 *
 * When a published hk9_barkode record is saved with a non-empty `hk9_legacy_path`
 * (the root-level path printed on the patch, e.g. /hk923-005/), a 301 rule
 * `<legacy path> → {type:'record', slug:<record slug>}` is added to the redirects
 * store if — and only if — no rule exists for that source yet. Existing rules are
 * never touched (staff may have edited them) and the record's own permalink path
 * is never redirected. Rules are managed under Heartland → Redirects.
 *
 * Runs after Meta\MetaBox::save (priority 10) so the value just posted from the
 * Details box is the one read back. Skipped while an import step holds the import
 * lock (payload redirects are the importer's job and are tracked for rollback).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Redirects;

defined( 'ABSPATH' ) || exit;

final class LegacyPaths {

	public const POST_TYPE = 'hk9_barkode';
	public const META_KEY  = 'hk9_legacy_path';

	public static function register(): void {
		add_action( 'save_post_' . self::POST_TYPE, [ self::class, 'on_save' ], 20, 3 );
	}

	/**
	 * save_post_hk9_barkode (priority 20).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function on_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// The importer writes records and their payload redirect rules itself (with map rows so a
		// rollback can remove them): stay out of its way while an import step holds the lock.
		if ( class_exists( 'HK9\\Core\\Import\\Map' ) && null !== \HK9\Core\Import\Map::lock_info() ) {
			return;
		}
		self::ensure_rule( $post );
	}

	/**
	 * Add the legacy-path rule for a record when it is missing.
	 *
	 * @return array{key:string,created:bool,reason:string} Outcome (reason is '' when created).
	 */
	public static function ensure_rule( \WP_Post $post ): array {
		$out = [
			'key'     => '',
			'created' => false,
			'reason'  => '',
		];
		if ( self::POST_TYPE !== $post->post_type ) {
			$out['reason'] = 'not_record';
			return $out;
		}
		if ( 'publish' !== $post->post_status ) {
			$out['reason'] = 'not_published';
			return $out;
		}
		$legacy = trim( (string) get_post_meta( $post->ID, self::META_KEY, true ) );
		if ( '' === $legacy ) {
			$out['reason'] = 'empty';
			return $out;
		}
		$key = Store::normalize_key( $legacy );
		if ( '' === $key || '/' === $key ) {
			$out['reason'] = 'invalid_source';
			return $out;
		}
		$out['key'] = $key;

		$slug = Store::sanitize_record_slug( (string) $post->post_name );
		if ( '' === $slug ) {
			$out['reason'] = 'no_slug';
			return $out;
		}

		// Never redirect the record's own address (that would loop).
		$permalink = get_permalink( $post );
		$own_key   = is_string( $permalink ) && '' !== $permalink ? Store::target_key( $permalink ) : null;
		if ( null !== $own_key ) {
			[ $src_path ] = Store::split_key( $key );
			if ( $own_key === $key || $own_key === $src_path ) {
				$out['reason'] = 'own_permalink';
				return $out;
			}
		}

		// Never overwrite an existing rule for that source (seeded, imported or hand-edited).
		if ( isset( Store::all()[ $key ] ) ) {
			$out['reason'] = 'exists';
			return $out;
		}

		$result = Store::upsert(
			$legacy,
			[
				'type' => 'record',
				'slug' => $slug,
			],
			301,
			sprintf(
				/* translators: %s: record title */
				__( 'Legacy path of BarKode record "%s"', 'heartland-k9s-core' ),
				(string) $post->post_title
			)
		);
		if ( is_wp_error( $result ) ) {
			// Validation refused it (e.g. the path resolves to live content, or the chain would loop): leave it to staff.
			$out['reason'] = $result->get_error_code();
			return $out;
		}
		$out['created'] = true;
		return $out;
	}
}
