<?php
/**
 * Pure, idempotent sanitizer for field lists.
 *
 * sanitize( $fields, $input ) walks the field definitions in declaration
 * order, coerces every value to its canonical stored type, applies defaults
 * for missing keys and drops unknown keys (including the `__present` marker).
 * The output always validates against Schema::for( $fields ).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	/**
	 * Sanitizes an input array against a field list.
	 *
	 * @param array $fields Field definitions (normalized or not).
	 * @param mixed $input  Arbitrary input (array expected; anything else = empty).
	 * @return array Canonical associative array (declaration order).
	 */
	public static function sanitize( array $fields, mixed $input ): array {
		if ( $input instanceof \stdClass ) {
			$input = (array) $input;
		}
		if ( ! is_array( $input ) ) {
			$input = [];
		}
		$out = [];
		foreach ( Field::normalize_list( $fields ) as $field ) {
			$key         = $field['key'];
			$value       = array_key_exists( $key, $input ) ? $input[ $key ] : null;
			$out[ $key ] = self::sanitize_field( $field, $value );
		}
		return $out;
	}

	/**
	 * Sanitizes one value for one field. `null` means "missing" → default.
	 */
	public static function sanitize_field( array $field, mixed $value ): mixed {
		$field = Field::normalize( $field );
		if ( null === $field ) {
			return null;
		}
		if ( $value instanceof \stdClass ) {
			$value = (array) $value;
		}
		$type  = Field::type( $field['type'] );
		$value = $type->sanitize( $field, $value );
		// Optional extra validation (must be idempotent and keep the stored type).
		if ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			$value = call_user_func( $field['sanitize_callback'], $value, $field );
		}
		return $value;
	}

	/** Canonical defaults for a field list. */
	public static function defaults( array $fields ): array {
		return self::sanitize( $fields, [] );
	}
}
