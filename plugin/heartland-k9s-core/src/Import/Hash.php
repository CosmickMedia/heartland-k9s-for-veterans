<?php
/**
 * Canonical hashing for import conflict detection.
 *
 * Values are hashed from a byte-stable representation (sorted object keys,
 * normalised line endings, scalar types preserved) so that a value read back
 * from the database hashes identically on every run.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Hash {

	/**
	 * Hash any PHP value.
	 */
	public static function of( mixed $value ): string {
		return hash( 'sha256', self::canonical( $value ) );
	}

	/**
	 * Hash a file's contents (streamed).
	 */
	public static function file( string $path ): ?string {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$hash = hash_file( 'sha256', $path );
		return false === $hash ? null : $hash;
	}

	/**
	 * Canonical JSON representation used for hashing.
	 */
	public static function canonical( mixed $value ): string {
		$json = wp_json_encode( self::normalise( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return false === $json ? serialize( $value ) : $json; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Recursively sort associative keys, normalise line endings, keep list order.
	 */
	public static function normalise( mixed $value ): mixed {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}
		if ( is_array( $value ) ) {
			$is_list = array_is_list( $value );
			$out     = [];
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::normalise( $v );
			}
			if ( ! $is_list ) {
				ksort( $out, SORT_STRING );
			}
			return $out;
		}
		if ( is_string( $value ) ) {
			return str_replace( [ "\r\n", "\r" ], "\n", $value );
		}
		if ( is_float( $value ) && floor( $value ) === $value && abs( $value ) < PHP_INT_MAX ) {
			return (int) $value;
		}
		return $value;
	}

	/**
	 * Short, non-reversible identifier for logs of sensitive records.
	 */
	public static function short( string $value ): string {
		return substr( hash( 'sha256', $value ), 0, 12 );
	}
}
