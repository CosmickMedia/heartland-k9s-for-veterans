<?php
/**
 * Revision support shared by section meta and CPT meta:
 *
 *  - `sanitize_post_meta_{key}_for_revision` filters (the subtype-scoped page
 *    sanitizer does not run for revision/autosave writes). A key tracked by
 *    several post types keeps the first sanitizer that leaves the value
 *    unchanged (revision copies are already canonical);
 *  - absent-key protection on restore (`wp_restore_post_revision` prio 9/11):
 *    core deletes every revisioned key and re-copies it from the revision, so
 *    a key missing from an older revision would silently wipe current data;
 *  - readable revision diffs via `_wp_post_revision_fields` (diff mode only —
 *    the same filter feeds `_wp_post_revision_data`/`wp_save_post_revision`,
 *    so it is enabled only on the revisions screen and its ajax diff endpoint,
 *    never while restoring).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Meta;

defined( 'ABSPATH' ) || exit;

final class RevisionGuard {

	private static bool $hooked = false;
	private static bool $diff_mode = false;

	/** post_type => meta_key => ['label'=>string,'sanitize'=>callable,'format'=>callable] */
	private static array $tracked = [];

	/** meta_key => true once the per-key filters are attached. */
	private static array $key_hooked = [];

	/** Captured values during a restore: post_id => key => value. */
	private static array $captured = [];

	/**
	 * Tracks a revisioned meta key.
	 *
	 * @param string   $post_type Post type the key belongs to.
	 * @param string   $meta_key  Meta key.
	 * @param string   $label     Human label for the diff UI.
	 * @param callable $sanitize  fn( mixed $value ): mixed — canonical sanitizer.
	 * @param callable $format    fn( mixed $value ): string — readable text for diffs.
	 */
	public static function track( string $post_type, string $meta_key, string $label, callable $sanitize, callable $format ): void {
		self::hook();
		self::$tracked[ $post_type ][ $meta_key ] = [
			'label'    => $label,
			'sanitize' => $sanitize,
			'format'   => $format,
		];
		if ( isset( self::$key_hooked[ $meta_key ] ) ) {
			return;
		}
		self::$key_hooked[ $meta_key ] = true;
		add_filter(
			"sanitize_post_meta_{$meta_key}_for_revision",
			static function ( $value ) use ( $meta_key ) {
				return self::sanitize_for_revision( $meta_key, $value );
			},
			10,
			1
		);
		add_filter(
			"_wp_post_revision_field_{$meta_key}",
			static function ( $value, $field = '', $post = null, $context = '' ) use ( $meta_key ) {
				return self::format_for_diff( $meta_key, $value, $post );
			},
			10,
			4
		);
	}

	private static function hook(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_action( 'wp_restore_post_revision', [ self::class, 'capture_before_restore' ], 9, 2 );
		add_action( 'wp_restore_post_revision', [ self::class, 'reinstate_after_restore' ], 11, 2 );
		add_action( 'load-revision.php', [ self::class, 'enable_diff_mode' ] );
		add_action( 'wp_ajax_get-revision-diffs', [ self::class, 'enable_diff_mode' ], 0 );
		add_filter( '_wp_post_revision_fields', [ self::class, 'revision_fields' ], 10, 2 );
		add_filter( 'get_post_metadata', [ self::class, 'preview_meta' ], 11, 4 );
	}

	/**
	 * Preview fix for array/object meta (prio 11, right after core's
	 * _wp_preview_meta_filter): core returns the autosave's bare value, but
	 * get_metadata_raw() expects a list of values when $single is true and
	 * reads `$check[0]` — for an associative array that yields a notice and
	 * an empty value. Also treats keys absent from the autosave as untouched
	 * (falls through to the page's stored value) instead of returning ''.
	 */
	public static function preview_meta( $value, $object_id, $meta_key, $single ) {
		if ( ! has_filter( 'get_post_metadata', '_wp_preview_meta_filter' ) ) {
			return $value;
		}
		$post = get_post();
		if ( ! $post || (int) $post->ID !== (int) $object_id || 'revision' === $post->post_type ) {
			return $value;
		}
		if ( ! isset( self::$tracked[ $post->post_type ][ $meta_key ] ) ) {
			return $value;
		}
		$preview = wp_get_post_autosave( $post->ID );
		if ( ! $preview ) {
			return $value;
		}
		if ( ! metadata_exists( 'post', $preview->ID, $meta_key ) ) {
			return null; // Absent in the autosave = untouched: use the saved value.
		}
		$values = get_metadata_raw( 'post', $preview->ID, $meta_key, false );
		if ( ! is_array( $values ) ) {
			return $value;
		}
		return $values; // A list of values; get_metadata_raw() picks [0] when $single.
	}

	/** Tracked keys for a post type. */
	public static function keys( string $post_type ): array {
		return array_keys( self::$tracked[ $post_type ] ?? [] );
	}

	/** Revision-subtype sanitizer: first tracked sanitizer that keeps the value, else the first result. */
	public static function sanitize_for_revision( string $meta_key, mixed $value ): mixed {
		$first = null;
		$seen  = false;
		foreach ( self::$tracked as $keys ) {
			if ( ! isset( $keys[ $meta_key ] ) ) {
				continue;
			}
			$out = ( $keys[ $meta_key ]['sanitize'] )( $value );
			if ( $out === $value ) {
				return $value;
			}
			if ( ! $seen ) {
				$first = $out;
				$seen  = true;
			}
		}
		return $seen ? $first : $value;
	}

	/** Formats a revision value for the diff UI using the parent post type's formatter. */
	public static function format_for_diff( string $meta_key, mixed $value, $post ): string {
		$post_type = '';
		if ( $post instanceof \WP_Post ) {
			$post_type = 'revision' === $post->post_type && $post->post_parent ? (string) get_post_type( $post->post_parent ) : $post->post_type;
		}
		$info = self::$tracked[ $post_type ][ $meta_key ] ?? null;
		if ( ! $info ) {
			foreach ( self::$tracked as $keys ) {
				if ( isset( $keys[ $meta_key ] ) ) {
					$info = $keys[ $meta_key ];
					break;
				}
			}
		}
		if ( ! $info ) {
			return is_scalar( $value ) ? (string) $value : '';
		}
		if ( '' === $value || null === $value ) {
			return '';
		}
		return (string) ( $info['format'] )( $value );
	}

	/** Prio 9: remember current values of tracked keys before core deletes them. */
	public static function capture_before_restore( $post_id, $revision_id ): void {
		$post_id                    = (int) $post_id;
		$post_type                  = (string) get_post_type( $post_id );
		self::$captured[ $post_id ] = [];
		foreach ( self::keys( $post_type ) as $key ) {
			if ( metadata_exists( 'post', $post_id, $key ) ) {
				self::$captured[ $post_id ][ $key ] = get_metadata_raw( 'post', $post_id, $key, true );
			}
		}
	}

	/** Prio 11: re-add keys the revision did not carry ("absent = untouched"). */
	public static function reinstate_after_restore( $post_id, $revision_id ): void {
		$post_id     = (int) $post_id;
		$revision_id = (int) $revision_id;
		if ( empty( self::$captured[ $post_id ] ) ) {
			return;
		}
		foreach ( self::$captured[ $post_id ] as $key => $value ) {
			if ( metadata_exists( 'post', $revision_id, $key ) ) {
				continue;
			}
			if ( ! metadata_exists( 'post', $post_id, $key ) ) {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}
		unset( self::$captured[ $post_id ] );
	}

	public static function enable_diff_mode(): void {
		// Never while restoring: wp_save_post_revision() compares every revision
		// field with normalize_whitespace(), which cannot take arrays.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'restore' === $action ) {
			return;
		}
		self::$diff_mode = true;
	}

	/** Adds tracked keys as diffable fields on the revisions screen only. */
	public static function revision_fields( $fields, $post = null ) {
		if ( ! self::$diff_mode || ! is_array( $fields ) ) {
			return $fields;
		}
		$post_type = '';
		if ( $post instanceof \WP_Post ) {
			$post_type = $post->post_type;
			if ( 'revision' === $post_type && $post->post_parent ) {
				$post_type = (string) get_post_type( $post->post_parent );
			}
		} elseif ( is_array( $post ) && ! empty( $post['post_type'] ) ) {
			$post_type = (string) $post['post_type'];
			if ( 'revision' === $post_type && ! empty( $post['post_parent'] ) ) {
				$post_type = (string) get_post_type( (int) $post['post_parent'] );
			}
		}
		if ( '' === $post_type ) {
			return $fields;
		}
		foreach ( self::$tracked[ $post_type ] ?? [] as $key => $info ) {
			$fields[ $key ] = $info['label'];
		}
		return $fields;
	}
}
