<?php
/**
 * Payload token parsing and resolution.
 *
 *   {{media:K}}              -> attachment id (int)
 *   {{media_url:K}}          -> attachment URL (scaled/full)
 *   {{media_url:K|original}} -> original (unscaled) URL
 *   {{post:K}}               -> post id (int)
 *   {{post_url:K}}           -> permalink
 *   {{term:tax:slug}}        -> term id (int)
 *
 * Resolution goes through the import map; in dry-run mode a key that exists in
 * the manifest but not yet in the map resolves to a placeholder so the walk can
 * continue without writes.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Tokens {

	public const PATTERN = '/\{\{(media_url|media|post_url|post|term):([^{}|]+?)(?:\|([a-z_]+))?\}\}/';

	private Manifest $manifest;
	private bool $dry_run;
	/** @var array<string, array> Resolution cache per tick. */
	private array $cache = [];
	/** @var array<string, string> Keys known to have failed in this pass. */
	private array $failed;

	public function __construct( Manifest $manifest, bool $dry_run, array $failed_keys = [] ) {
		$this->manifest = $manifest;
		$this->dry_run  = $dry_run;
		$this->failed   = $failed_keys;
	}

	public function set_failed( array $failed_keys ): void {
		$this->failed = $failed_keys;
	}

	public static function contains( string $value ): bool {
		return str_contains( $value, '{{' ) && (bool) preg_match( self::PATTERN, $value );
	}

	/**
	 * True when the string still carries anything that looks like a token.
	 */
	public static function has_unbaked( string $value ): bool {
		return (bool) preg_match( '/\{\{[a-z_]+:[^}]*\}\}/', $value );
	}

	/**
	 * Every token in a string/array structure: list of [kind, key, modifier].
	 *
	 * @return array<int, array{0:string,1:string,2:string}>
	 */
	public static function extract( mixed $value ): array {
		$out = [];
		self::walk_strings(
			$value,
			static function ( string $s ) use ( &$out ): void {
				if ( ! str_contains( $s, '{{' ) ) {
					return;
				}
				if ( preg_match_all( self::PATTERN, $s, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $t ) {
						$out[] = [ $t[1], trim( $t[2] ), $t[3] ?? '' ];
					}
				}
			}
		);
		return $out;
	}

	/**
	 * When the string is exactly one token of the given kind family, return its key.
	 */
	public static function single_key( string $value, string $kind ): ?string {
		if ( '' === $value || ! preg_match( '/^' . substr( self::PATTERN, 1, -1 ) . '$/', $value, $m ) ) {
			return null;
		}
		$k = $m[1];
		if ( 'post' === $kind && ! in_array( $k, [ 'post', 'post_url' ], true ) ) {
			return null;
		}
		if ( 'media' === $kind && ! in_array( $k, [ 'media', 'media_url' ], true ) ) {
			return null;
		}
		if ( 'term' === $kind && 'term' !== $k ) {
			return null;
		}
		return self::normalise_key( $k, trim( $m[2] ) );
	}

	/**
	 * Manifest key a token points at.
	 */
	public static function normalise_key( string $kind, string $key ): string {
		if ( 'term' === $kind && ! str_starts_with( $key, 'term:' ) ) {
			return 'term:' . $key;
		}
		return $key;
	}

	/**
	 * Does a token target exist in the manifest (structural check for validate)?
	 */
	public function target_type_ok( string $kind, string $key ): bool {
		$mkey   = self::normalise_key( $kind, $key );
		$record = $this->manifest->get( $mkey );
		if ( ! $record ) {
			if ( 'term' === $kind ) {
				// Terms may pre-exist on the site (seeded taxonomy terms).
				$parts = explode( ':', $mkey, 3 );
				return 3 === count( $parts ) && taxonomy_exists( $parts[1] ) && (bool) get_term_by( 'slug', $parts[2], $parts[1] );
			}
			return false;
		}
		$type = Manifest::type_of( $record );
		return match ( $kind ) {
			'media', 'media_url' => 'attachment' === $type,
			'post', 'post_url'   => Manifest::POST_TYPES_KEY === $type,
			'term'               => 'term' === $type,
			default              => false,
		};
	}

	/**
	 * Resolve one token. Returns int|string or WP_Error.
	 */
	public function resolve( string $kind, string $key, string $modifier = '' ): int|string|WP_Error {
		$key   = trim( $key );
		$mkey  = self::normalise_key( $kind, $key );
		$ckey  = $kind . '|' . $mkey . '|' . $modifier;
		if ( array_key_exists( $ckey, $this->cache ) ) {
			return $this->cache[ $ckey ];
		}
		$result               = $this->do_resolve( $kind, $mkey, $modifier );
		$this->cache[ $ckey ] = $result;
		return $result;
	}

	private function do_resolve( string $kind, string $mkey, string $modifier ): int|string|WP_Error {
		if ( isset( $this->failed[ $mkey ] ) ) {
			return new WP_Error( 'hk9_token_failed_dep', sprintf( 'Depends on failed record %s (%s).', $mkey, $this->failed[ $mkey ] ) );
		}

		$row = Map::get( $mkey );
		$id  = $row && Map::STATUS_ACTIVE === $row['status'] ? (int) $row['object_id'] : 0;

		if ( 0 === $id && 'term' === $kind ) {
			$parts = explode( ':', $mkey, 3 );
			if ( 3 === count( $parts ) && taxonomy_exists( $parts[1] ) ) {
				$term = get_term_by( 'slug', $parts[2], $parts[1] );
				if ( $term ) {
					$id = (int) $term->term_id;
				}
			}
		}

		if ( 0 === $id ) {
			if ( $this->dry_run && $this->manifest->has( $mkey ) ) {
				return match ( $kind ) {
					'media_url' => home_url( '/hk9-dry-run/' . rawurlencode( $mkey ) . ( 'original' === $modifier ? '/original' : '' ) ),
					'post_url'  => home_url( '/hk9-dry-run/' . rawurlencode( $mkey ) . '/' ),
					default     => 0,
				};
			}
			return new WP_Error( 'hk9_token_unresolved', sprintf( 'Unresolvable token {{%s:%s}}.', $kind, $mkey ) );
		}

		switch ( $kind ) {
			case 'media':
			case 'post':
			case 'term':
				return $id;
			case 'media_url':
				if ( 'original' === $modifier ) {
					$url = wp_get_original_image_url( $id );
					if ( $url ) {
						return $url;
					}
				}
				$url = wp_get_attachment_url( $id );
				return $url ? $url : new WP_Error( 'hk9_token_unresolved', sprintf( 'Attachment %d for %s has no URL.', $id, $mkey ) );
			case 'post_url':
				$url = get_permalink( $id );
				return $url ? $url : new WP_Error( 'hk9_token_unresolved', sprintf( 'Post %d for %s has no permalink.', $id, $mkey ) );
		}
		return new WP_Error( 'hk9_token_kind', sprintf( 'Unknown token kind %s.', $kind ) );
	}

	/**
	 * Resolve every token inside a value (recursively). A string that is exactly
	 * one id token becomes an int; embedded tokens are replaced textually.
	 *
	 * @param array $skip_kinds Token kinds to leave untouched (e.g. ['post_url'] before URLs are final).
	 */
	public function bake( mixed $value, array $skip_kinds = [] ): mixed {
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$r = $this->bake( $v, $skip_kinds );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				$out[ $k ] = $r;
			}
			return $out;
		}
		if ( ! is_string( $value ) || ! str_contains( $value, '{{' ) ) {
			return $value;
		}
		// Exactly one token -> typed value.
		if ( preg_match( '/^' . substr( self::PATTERN, 1, -1 ) . '$/', $value, $m ) ) {
			if ( in_array( $m[1], $skip_kinds, true ) ) {
				return $value;
			}
			return $this->resolve( $m[1], $m[2], $m[3] ?? '' );
		}
		$error = null;
		$baked = preg_replace_callback(
			self::PATTERN,
			function ( array $m ) use ( &$error, $skip_kinds ): string {
				if ( $error ) {
					return $m[0];
				}
				if ( in_array( $m[1], $skip_kinds, true ) ) {
					return $m[0];
				}
				$r = $this->resolve( $m[1], $m[2], $m[3] ?? '' );
				if ( is_wp_error( $r ) ) {
					$error = $r;
					return $m[0];
				}
				return (string) $r;
			},
			$value
		);
		return $error ?? $baked;
	}

	/**
	 * Does the value contain a token of one of the given kinds?
	 */
	public static function has_kind( mixed $value, array $kinds ): bool {
		foreach ( self::extract( $value ) as [ $kind ] ) {
			if ( in_array( $kind, $kinds, true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function walk_strings( mixed $value, callable $fn ): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				self::walk_strings( $v, $fn );
			}
		} elseif ( is_string( $value ) ) {
			$fn( $value );
		}
	}
}
