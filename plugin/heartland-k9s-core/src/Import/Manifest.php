<?php
/**
 * Payload manifest reader (`hk9-payload/1`).
 *
 * Loads `manifest.json` from a payload directory, normalises records (ISO dates
 * -> post_date_gmt/post_date pairs, meta value casting) and exposes typed
 * accessors. Structural validation lives in Steps\Validate; this class only
 * refuses to load something that is not a manifest at all.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Manifest {

	public const FORMAT = 'hk9-payload/1';

	public const POST_TYPES_KEY = 'post_like';

	/** Record types that are "post-like" (created as posts). */
	public const STRUCTURAL_TYPES = [ 'attachment', 'term', 'menu', 'option', 'reading', 'redirect' ];

	private string $dir;
	private array $data;
	/** @var array<string, array> */
	private array $by_key = [];
	/** @var array<string, string[]> */
	private array $by_type = [];

	private function __construct( string $dir, array $data ) {
		$this->dir  = $dir;
		$this->data = $data;
		foreach ( $data['records'] as $i => $record ) {
			$key = (string) ( $record['key'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$this->by_key[ $key ] = $record;
			$type                 = self::type_of( $record );
			$this->by_type[ $type ][] = $key;
		}
	}

	/**
	 * Load from a directory containing manifest.json.
	 */
	public static function load( string $dir ): self|WP_Error {
		$dir  = untrailingslashit( $dir );
		$file = $dir . '/manifest.json';
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return new WP_Error( 'hk9_manifest_missing', sprintf( 'manifest.json not found in %s', $dir ) );
		}
		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return new WP_Error( 'hk9_manifest_unreadable', 'manifest.json could not be read.' );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'hk9_manifest_invalid', 'manifest.json is not valid JSON: ' . json_last_error_msg() );
		}
		if ( ( $data['format'] ?? '' ) !== self::FORMAT ) {
			return new WP_Error( 'hk9_manifest_format', sprintf( 'Unsupported payload format "%s" (expected %s).', (string) ( $data['format'] ?? '' ), self::FORMAT ) );
		}
		if ( ! isset( $data['records'] ) || ! is_array( $data['records'] ) ) {
			return new WP_Error( 'hk9_manifest_records', 'manifest.json has no "records" array.' );
		}
		$data['records'] = array_values( array_filter( $data['records'], 'is_array' ) );
		return new self( $dir, $data );
	}

	public function dir(): string {
		return $this->dir;
	}

	public function raw(): array {
		return $this->data;
	}

	public function generated_at(): string {
		return (string) ( $this->data['generated_at'] ?? '' );
	}

	public function requires_theme(): string {
		$theme = $this->data['requires']['theme'] ?? 'heartland-k9s';
		/**
		 * Filters the stylesheet the importer expects to be active.
		 *
		 * @param string $theme Stylesheet slug.
		 */
		return (string) apply_filters( 'hk9/import/expected_stylesheet', (string) $theme );
	}

	/** @return array[] */
	public function records(): array {
		return $this->data['records'];
	}

	public function has( string $key ): bool {
		return isset( $this->by_key[ $key ] );
	}

	public function get( string $key ): ?array {
		return $this->by_key[ $key ] ?? null;
	}

	/**
	 * Keys of a given record type, in manifest order.
	 *
	 * @param string $type 'attachment' | 'term' | 'menu' | 'option' | 'reading' | 'redirect' | 'post_like'
	 * @return string[]
	 */
	public function keys( string $type ): array {
		return $this->by_type[ $type ] ?? [];
	}

	public function count( string $type ): int {
		return count( $this->keys( $type ) );
	}

	/**
	 * Post-like keys ordered parent-first (depth order, stable within a depth).
	 *
	 * @return string[]
	 */
	public function post_keys_parent_first(): array {
		$keys  = $this->keys( self::POST_TYPES_KEY );
		$depth = [];
		$calc  = function ( string $key, array $seen ) use ( &$calc, &$depth ): int {
			if ( isset( $depth[ $key ] ) ) {
				return $depth[ $key ];
			}
			$record = $this->by_key[ $key ] ?? null;
			$parent = $record ? Tokens::single_key( (string) ( $record['parent'] ?? '' ), 'post' ) : null;
			if ( null === $parent || ! isset( $this->by_key[ $parent ] ) || isset( $seen[ $parent ] ) ) {
				$depth[ $key ] = 0;
				return 0;
			}
			$seen[ $key ] = true;
			$d            = 1 + $calc( $parent, $seen );
			$depth[ $key ] = $d;
			return $d;
		};
		foreach ( $keys as $k ) {
			$calc( $k, [] );
		}
		$indexed = [];
		foreach ( $keys as $i => $k ) {
			$indexed[] = [ $depth[ $k ], $i, $k ];
		}
		usort( $indexed, static fn( $a, $b ) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1] );
		return array_map( static fn( $r ) => $r[2], $indexed );
	}

	/**
	 * The classification used for step routing.
	 */
	public static function type_of( array $record ): string {
		$type = (string) ( $record['type'] ?? '' );
		return in_array( $type, self::STRUCTURAL_TYPES, true ) ? $type : self::POST_TYPES_KEY;
	}

	/**
	 * Absolute path of a payload-relative file, contained inside the payload dir.
	 * Returns null when the reference escapes the directory.
	 */
	public function path( string $relative ): ?string {
		return Payload::resolve_within( $this->dir, $relative );
	}

	/**
	 * Read a content file referenced by a record.
	 */
	public function content( array $record ): string|WP_Error|null {
		$ref = $record['content'] ?? null;
		if ( null === $ref || '' === $ref ) {
			return null;
		}
		if ( ! is_string( $ref ) ) {
			return new WP_Error( 'hk9_content_ref', 'content must be a payload-relative path.' );
		}
		$path = $this->path( $ref );
		if ( null === $path || ! is_file( $path ) ) {
			return new WP_Error( 'hk9_content_missing', sprintf( 'Content file "%s" is missing.', $ref ) );
		}
		$html = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $html ) {
			return new WP_Error( 'hk9_content_unreadable', sprintf( 'Content file "%s" could not be read.', $ref ) );
		}
		return str_replace( "\r\n", "\n", $html );
	}

	/**
	 * Stable hash of a record as authored (including its content file bytes).
	 */
	public function payload_hash( array $record ): string {
		$copy = $record;
		if ( ! empty( $record['content'] ) && is_string( $record['content'] ) ) {
			$path             = $this->path( $record['content'] );
			$copy['_content'] = $path && is_file( $path ) ? Hash::file( $path ) : 'missing';
		}
		return Hash::of( $copy );
	}

	/**
	 * Convert an ISO-8601 date to ['gmt' => 'Y-m-d H:i:s', 'local' => 'Y-m-d H:i:s'].
	 * Returns null for empty input and WP_Error for unparsable input.
	 */
	public static function date_pair( mixed $iso ): array|WP_Error|null {
		if ( null === $iso || '' === $iso ) {
			return null;
		}
		if ( ! is_string( $iso ) ) {
			return new WP_Error( 'hk9_date', 'date must be an ISO-8601 string.' );
		}
		$ts = strtotime( $iso );
		if ( false === $ts ) {
			return new WP_Error( 'hk9_date', sprintf( 'Unparsable date "%s".', $iso ) );
		}
		$gmt = gmdate( 'Y-m-d H:i:s', $ts );
		return [
			'gmt'   => $gmt,
			'local' => get_date_from_gmt( $gmt ),
		];
	}

	/**
	 * Cast a manifest meta entry {type, value} to its PHP value (tokens untouched).
	 */
	public static function cast_meta( array $entry ): mixed {
		$type  = (string) ( $entry['type'] ?? 'string' );
		$value = $entry['value'] ?? null;
		switch ( $type ) {
			case 'integer':
				return is_string( $value ) && Tokens::contains( $value ) ? $value : (int) $value;
			case 'number':
				if ( is_string( $value ) && Tokens::contains( $value ) ) {
					return $value;
				}
				return is_int( $value ) ? $value : (float) $value;
			case 'boolean':
				return (bool) $value;
			case 'array':
				return is_array( $value ) ? array_values( $value ) : [];
			case 'object':
				return is_array( $value ) ? $value : [];
			default:
				return is_scalar( $value ) || null === $value ? (string) $value : $value;
		}
	}
}
