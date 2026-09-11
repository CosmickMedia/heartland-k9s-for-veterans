<?php
/**
 * Write-path access checks for post references (relationship ids, link
 * post_ids).
 *
 * Sanitizers stay pure (CLI/import call them without a user), so the check
 * that the *current user* may reference a post lives here and is applied by
 * the interactive write paths only (REST pre-insert, meta-box saves). An id is
 * kept when the user can edit the target post — the same rule the hk9/v1/pick
 * search applies — or when the id is already referenced by the stored value
 * (an editor saving a page must never lose links an administrator added).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields;

defined( 'ABSPATH' ) || exit;

final class Access {

	/**
	 * Zeroes/drops post references in $clean that the current user may not
	 * introduce, keeping every id already present in $stored.
	 *
	 * @param array $fields Field definitions (normalized or not).
	 * @param array $clean  Sanitized value about to be written.
	 * @param mixed $stored Currently stored value for the same key (any shape).
	 */
	public static function restrict( array $fields, array $clean, mixed $stored ): array {
		$fields = Field::normalize_list( $fields );
		$known  = self::collect( $fields, is_array( $stored ) ? $stored : [] );
		return self::apply( $fields, $clean, $known );
	}

	/** Single-field variant (CPT meta keys hold one field each). */
	public static function restrict_field( array $field, mixed $clean, mixed $stored ): mixed {
		$field = Field::normalize( $field );
		if ( null === $field ) {
			return $clean;
		}
		$known = self::collect_field( $field, $stored );
		return self::apply_field( $field, $clean, $known );
	}

	/** Whether the current user may reference $id (already stored ids are always allowed). */
	public static function allowed( int $id, array $known ): bool {
		if ( $id <= 0 || in_array( $id, $known, true ) ) {
			return true;
		}
		/**
		 * Filters whether the current user may reference a post from a field.
		 *
		 * @param bool $allowed Default: current_user_can( 'edit_post', $id ).
		 * @param int  $id      Post id.
		 */
		return (bool) apply_filters( 'hk9/fields/can_reference_post', current_user_can( 'edit_post', $id ), $id );
	}

	/** @return int[] Every post id referenced anywhere in $value (per the field list). */
	private static function collect( array $fields, array $value ): array {
		$ids = [];
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field['key'], $value ) ) {
				$ids = array_merge( $ids, self::collect_field( $field, $value[ $field['key'] ] ) );
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** @return int[] */
	private static function collect_field( array $field, mixed $value ): array {
		switch ( $field['type'] ) {
			case 'relationship':
				return array_values( array_filter( array_map( 'intval', (array) $value ) ) );
			case 'link':
				$id = is_array( $value ) ? (int) ( $value['post_id'] ?? 0 ) : 0;
				return $id > 0 ? [ $id ] : [];
			case 'repeater':
				$ids = [];
				foreach ( is_array( $value ) ? $value : [] as $row ) {
					if ( is_array( $row ) ) {
						$ids = array_merge( $ids, self::collect( $field['fields'], $row ) );
					}
				}
				return $ids;
			case 'group':
				return is_array( $value ) ? self::collect( $field['fields'], $value ) : [];
		}
		return [];
	}

	private static function apply( array $fields, array $clean, array $known ): array {
		foreach ( $fields as $field ) {
			$key = $field['key'];
			if ( array_key_exists( $key, $clean ) ) {
				$clean[ $key ] = self::apply_field( $field, $clean[ $key ], $known );
			}
		}
		return $clean;
	}

	private static function apply_field( array $field, mixed $value, array $known ): mixed {
		switch ( $field['type'] ) {
			case 'relationship':
				if ( ! empty( $field['multiple'] ) ) {
					return array_values( array_filter( array_map( 'intval', (array) $value ), static fn( int $id ): bool => self::allowed( $id, $known ) ) );
				}
				$id = (int) ( is_array( $value ) ? 0 : $value );
				return self::allowed( $id, $known ) ? $id : 0;
			case 'link':
				if ( is_array( $value ) && (int) ( $value['post_id'] ?? 0 ) > 0 && ! self::allowed( (int) $value['post_id'], $known ) ) {
					$value['post_id'] = 0;
				}
				return $value;
			case 'repeater':
				if ( ! is_array( $value ) ) {
					return $value;
				}
				foreach ( $value as $i => $row ) {
					if ( is_array( $row ) ) {
						$value[ $i ] = self::apply( $field['fields'], $row, $known );
					}
				}
				return $value;
			case 'group':
				return is_array( $value ) ? self::apply( $field['fields'], $value, $known ) : $value;
		}
		return $value;
	}
}
