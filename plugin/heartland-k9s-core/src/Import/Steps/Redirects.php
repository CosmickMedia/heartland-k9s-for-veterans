<?php
/**
 * Step 11: redirects — merge payload rules into the hk9_redirects option
 * ({version, rules:{normalized-source:{to,status,enabled,seed,note,updated,by}}})
 * with from==to rejection, cycle walks and live-collision warnings.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Map;
use HK9\Core\Import\Reconcile;
use HK9\Core\Import\Tokens;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Redirects extends Step {

	public const NAME   = 'redirects';
	public const OPTION = 'hk9_redirects';

	protected function items(): array {
		return $this->manifest()->keys( 'redirect' );
	}

	protected function process( string $key ): void {
		$ctx    = $this->ctx;
		$record = $this->record( $key );
		if ( ! $record || $this->skip_failed( $key, $record ) ) {
			return;
		}

		$from = self::normalize( (string) $record['from'] );
		if ( '' === $from ) {
			$ctx->fail( $key, 'Empty or invalid "from".' );
			return;
		}
		$status = (int) ( $record['status'] ?? 301 );
		$to     = 410 === $status ? null : $this->target( $record['to'] ?? null );
		if ( is_wp_error( $to ) ) {
			$ctx->fail( $key, $to->get_error_message() );
			return;
		}

		$option = self::option();
		$rules  = $option['rules'];

		// Loop guards.
		if ( null !== $to ) {
			$to_path = self::target_path( $to );
			if ( null !== $to_path && $to_path === $from ) {
				$ctx->fail( $key, sprintf( 'Redirect %s points at itself.', $from ) );
				return;
			}
			$merged          = $rules;
			$merged[ $from ] = [ 'to' => $to, 'status' => $status, 'enabled' => true ];
			$cycle           = self::find_cycle( $from, $merged );
			if ( $cycle ) {
				$ctx->fail( $key, 'Redirect chain loops: ' . implode( ' -> ', $cycle ) );
				return;
			}
		}
		if ( ! str_contains( $from, '?' ) ) {
			$hit = url_to_postid( home_url( $from ) );
			if ( $hit > 0 && 'publish' === get_post_status( $hit ) && ! $this->would_be_converted( $hit ) ) {
				$ctx->warn( $key, sprintf( 'Source %s matches published post #%d; the redirect will shadow it.', $from, $hit ) );
			}
		}

		$desired = [
			'rule' => [
				'to'      => $to,
				'status'  => $status,
				'enabled' => true,
			],
		];
		$existing = $rules[ $from ] ?? null;
		$current  = [ 'rule' => $existing ? self::comparable( $existing ) : null ];

		$row  = Map::get( $key );
		$plan = Reconcile::plan( $row ?: [ 'object_id' => 0, 'field_hashes' => [] ], $desired, $current, $ctx->overwrite() );
		$act  = $plan['action'];
		if ( 'update' === $act && null === $existing ) {
			$act = 'create';
		}
		$target_desc = null === $to ? '410' : ( self::target_path( $to ) ?? ( 'post' === $to['type'] ? 'post #' . (int) $to['id'] : (string) ( $to['path'] ?? '' ) ) );
		$ctx->result( $key, $act, $plan['conflicts'] ? 'conflict (rule edited on site)' : $from . ' -> ' . $target_desc );
		if ( $ctx->dry() ) {
			return;
		}
		if ( ! $row ) {
			Map::bind( $key, 'redirect', 0, $ctx->run_id, [ 'created_by_run' => null === $existing ? $ctx->run_id : null ] );
		}
		if ( $plan['apply'] ) {
			$rules[ $from ] = array_merge(
				is_array( $existing ) ? $existing : [ 'seed' => false, 'note' => __( 'Imported from payload', 'heartland-k9s-core' ) ],
				[
					'to'      => $to,
					'status'  => $status,
					'enabled' => true,
					'updated' => time(),
					'by'      => $ctx->user_login(),
				]
			);
			$option['rules'] = $rules;
			self::save_option( $option );
		}
		$after = self::option()['rules'][ $from ] ?? null;
		// Pre-image is the full existing rule (note/seed/by included) so rollback restores it verbatim.
		$before = $plan['before'] ? [ 'rule' => $existing ] : [];
		Map::record( $key, $ctx->run_id, Reconcile::readback( $plan, [ 'rule' => $after ? self::comparable( $after ) : null ] ), $before, $this->manifest()->payload_hash( $record ) );
	}

	/* ------------------------------------------------------------ helpers */

	/**
	 * Existing-site dry run: a legacy registry page that the import would convert
	 * into a BarKode record no longer exists at its root path after the real run,
	 * so its legacy-path redirect shadows nothing.
	 */
	private function would_be_converted( int $post_id ): bool {
		if ( ! $this->ctx->dry() ) {
			return false;
		}
		foreach ( (array) ( $this->ctx->state['dry_adopted'] ?? [] ) as $key => $id ) {
			if ( (int) $id !== $post_id ) {
				continue;
			}
			$record = $this->record( (string) $key );
			if ( $record && \HK9\Core\Import\Adopt::CONVERT_TYPE === (string) ( $record['type'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array|WP_Error {type,id}|{type,path}|{type,slug}
	 */
	private function target( mixed $to ): array|WP_Error {
		if ( is_array( $to ) ) {
			if ( 'record' === ( $to['type'] ?? '' ) && ! empty( $to['slug'] ) ) {
				return [
					'type' => 'record',
					'slug' => sanitize_title( (string) $to['slug'] ),
				];
			}
			return new WP_Error( 'hk9_redirect_to', 'Object targets must be {type:"record", slug}.' );
		}
		$to = trim( (string) $to );
		if ( '' === $to ) {
			return new WP_Error( 'hk9_redirect_to', 'Missing redirect target.' );
		}
		$post_key = Tokens::single_key( $to, 'post' );
		if ( null !== $post_key ) {
			$id = $this->ctx->tokens->resolve( 'post', $post_key );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			return [
				'type' => 'post',
				'id'   => (int) $id,
			];
		}
		if ( Tokens::contains( $to ) ) {
			$baked = $this->ctx->tokens->bake( $to );
			if ( is_wp_error( $baked ) ) {
				return $baked;
			}
			$to = (string) $baked;
		}
		if ( preg_match( '#^https?://#i', $to ) ) {
			$host = wp_parse_url( $to, PHP_URL_HOST );
			$home = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $host && $home && strtolower( $host ) !== strtolower( $home ) ) {
				return [
					'type' => 'path',
					'path' => esc_url_raw( $to ),
				];
			}
			$to = (string) wp_parse_url( $to, PHP_URL_PATH ) . ( wp_parse_url( $to, PHP_URL_QUERY ) ? '?' . wp_parse_url( $to, PHP_URL_QUERY ) : '' );
		}
		return [
			'type' => 'path',
			'path' => self::normalize( $to ),
		];
	}

	/**
	 * Normalised path a target resolves to (null for external / unresolvable).
	 */
	public static function target_path( array $to ): ?string {
		switch ( $to['type'] ?? '' ) {
			case 'post':
				$url = get_permalink( (int) ( $to['id'] ?? 0 ) );
				return $url ? self::normalize( (string) wp_parse_url( $url, PHP_URL_PATH ) ) : null;
			case 'record':
				return self::normalize( '/barkode/' . (string) ( $to['slug'] ?? '' ) . '/' );
			case 'path':
				$p = (string) ( $to['path'] ?? '' );
				return preg_match( '#^https?://#i', $p ) ? null : self::normalize( $p );
		}
		return null;
	}

	/**
	 * @return string[] The loop (from ... from) or [] when none.
	 */
	public static function find_cycle( string $from, array $rules ): array {
		$seen = [ $from ];
		$cur  = $from;
		for ( $i = 0; $i < 25; $i++ ) {
			$rule = $rules[ $cur ] ?? null;
			if ( ! $rule || empty( $rule['enabled'] ) || empty( $rule['to'] ) || ! is_array( $rule['to'] ) ) {
				return [];
			}
			$next = self::target_path( $rule['to'] );
			if ( null === $next ) {
				return [];
			}
			if ( in_array( $next, $seen, true ) ) {
				$seen[] = $next;
				return $seen;
			}
			$seen[] = $next;
			$cur    = $next;
		}
		return [];
	}

	/**
	 * Key normalisation (delegates to the redirects module when present so both agree).
	 */
	public static function normalize( string $source ): string {
		if ( class_exists( '\HK9\Core\Redirects\Store' ) && method_exists( '\HK9\Core\Redirects\Store', 'normalize' ) ) {
			$n = \HK9\Core\Redirects\Store::normalize( $source );
			if ( is_string( $n ) ) {
				return $n;
			}
		}
		$source = trim( $source );
		if ( '' === $source ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $source ) ) {
			$path   = (string) wp_parse_url( $source, PHP_URL_PATH );
			$query  = (string) wp_parse_url( $source, PHP_URL_QUERY );
			$source = $path . ( '' !== $query ? '?' . $query : '' );
		}
		$query = '';
		if ( str_contains( $source, '?' ) ) {
			[ $source, $query ] = explode( '?', $source, 2 );
		}
		$path = strtolower( rawurldecode( $source ) );
		$path = '/' . trim( $path, '/' );
		if ( '/' !== $path ) {
			$path .= '/';
		}
		$path = preg_replace( '#/+#', '/', $path );
		if ( '' !== $query ) {
			$path .= '?' . strtolower( rawurldecode( $query ) );
		}
		return (string) $path;
	}

	public static function comparable( array $rule ): array {
		return [
			'to'      => $rule['to'] ?? null,
			'status'  => (int) ( $rule['status'] ?? 301 ),
			'enabled' => ! empty( $rule['enabled'] ),
		];
	}

	public static function option(): array {
		$opt = get_option( self::OPTION, [] );
		if ( ! is_array( $opt ) ) {
			$opt = [];
		}
		$opt['version'] = (int) ( $opt['version'] ?? 1 ) ?: 1;
		$opt['rules']   = isset( $opt['rules'] ) && is_array( $opt['rules'] ) ? $opt['rules'] : [];
		return $opt;
	}

	public static function save_option( array $option ): void {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, $option, '', true );
		} else {
			update_option( self::OPTION, $option, true );
		}
	}
}
