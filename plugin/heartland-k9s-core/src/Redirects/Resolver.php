<?php
/**
 * Runtime legacy-redirect resolver.
 *
 * Runs on parse_request priority 1 (before the main query, 404 handling, old-slug
 * and canonical redirects), GET/HEAD only, never for admin/REST/cron. The offline
 * resolve() is shared with the admin "Test" action and `wp hk9 redirects test`.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Redirects;

defined( 'ABSPATH' ) || exit;

final class Resolver {

	public const REDIRECT_BY = 'hk9-legacy';

	public static function register(): void {
		add_action( 'parse_request', [ self::class, 'maybe_redirect' ], 1 );
		add_filter( 'pre_redirect_guess_404_permalink', '__return_false' );
		add_action( 'post_updated', [ self::class, 'flag_collisions' ], 10, 3 );
	}

	/**
	 * Pure resolution of a request URI against the rules.
	 *
	 * @param string $request_uri Path with optional query string (site-relative or absolute).
	 * @return array{key:string,rule:array,url:?string,status:int,reason:string}|null
	 */
	public static function resolve( string $request_uri ): ?array {
		$rules = Store::all();
		if ( ! $rules ) {
			return null;
		}
		$parts = wp_parse_url( $request_uri );
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query = is_array( $parts ) && isset( $parts['query'] ) ? (string) $parts['query'] : '';
		$path_key = Store::normalize_key( $path, false );

		parse_str( $query, $params );
		$params = array_filter( $params, 'is_scalar' );

		$candidates = [];
		if ( $params ) {
			$candidates[] = $path_key . '?' . Store::normalize_query( $query );
			foreach ( $params as $k => $v ) {
				$candidates[] = $path_key . '?' . Store::normalize_query( (string) $k . '=' . (string) $v );
			}
		}
		$candidates[] = $path_key;

		foreach ( $candidates as $key ) {
			if ( ! isset( $rules[ $key ] ) ) {
				continue;
			}
			$rule = $rules[ $key ];
			if ( ! $rule['enabled'] ) {
				return [
					'key'    => $key,
					'rule'   => $rule,
					'url'    => null,
					'status' => 0,
					'reason' => 'disabled',
				];
			}
			if ( 410 === $rule['status'] ) {
				return [
					'key'    => $key,
					'rule'   => $rule,
					'url'    => null,
					'status' => 410,
					'reason' => 'gone',
				];
			}
			$url = Store::resolve_target( $rule['to'] );
			if ( null === $url ) {
				return [
					'key'    => $key,
					'rule'   => $rule,
					'url'    => null,
					'status' => 0,
					'reason' => 'unresolved',
				];
			}
			// Preserve the remaining query string.
			[ , $consumed ] = Store::split_key( $key );
			$remaining = [];
			foreach ( $params as $k => $v ) {
				if ( ! array_key_exists( strtolower( (string) $k ), $consumed ) ) {
					$remaining[ (string) $k ] = (string) $v;
				}
			}
			if ( $remaining ) {
				$url = add_query_arg( array_map( 'rawurlencode', $remaining ), $url );
			}
			// Runtime loop guard: never redirect a URL to itself.
			$target_key = Store::target_key( $url );
			if ( null !== $target_key && ( $target_key === $key || $target_key === $path_key . ( $params ? '?' . Store::normalize_query( $query ) : '' ) ) ) {
				return [
					'key'    => $key,
					'rule'   => $rule,
					'url'    => $url,
					'status' => 0,
					'reason' => 'loop',
				];
			}
			return [
				'key'    => $key,
				'rule'   => $rule,
				'url'    => $url,
				'status' => $rule['status'],
				'reason' => 'ok',
			];
		}
		return null;
	}

	/**
	 * Current request URI relative to the site (path + query).
	 */
	public static function current_request_uri(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalized by the store.
		return $uri;
	}

	/**
	 * parse_request handler.
	 *
	 * @param \WP $wp WP environment.
	 */
	public static function maybe_redirect( \WP $wp ): void {
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return;
		}
		$uri = self::current_request_uri();
		if ( ! empty( $wp->query_vars['rest_route'] ) || str_starts_with( $uri, '/wp-json/' ) || str_contains( $uri, '/wp-admin/' ) ) {
			return;
		}
		$result = self::resolve( $uri );
		if ( null === $result || ( 'ok' !== $result['reason'] && 'gone' !== $result['reason'] ) ) {
			return;
		}

		/**
		 * Fires before a legacy redirect is sent.
		 *
		 * @param array $result Resolution result.
		 */
		do_action( 'hk9/redirects/before_redirect', $result );

		if ( 'gone' === $result['reason'] ) {
			$wp->query_vars = [ 'error' => '404' ];
			add_action( 'template_redirect', static fn() => status_header( 410 ), 0 );
			return;
		}

		$status = (int) $result['status'];
		if ( 301 === $status ) {
			header( 'Cache-Control: public, max-age=86400' );
		} else {
			nocache_headers();
		}
		header( 'X-HK9-Redirect-Rule: ' . rawurlencode( $result['key'] ) );
		$url = (string) $result['url'];
		if ( null === Store::target_key( $url ) ) {
			// External host: scheme was validated at save time.
			wp_redirect( $url, $status, self::REDIRECT_BY ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external destination validated on save.
		} else {
			wp_safe_redirect( $url, $status, self::REDIRECT_BY );
		}
		exit;
	}

	/**
	 * Offline + optional HTTP test of one rule.
	 *
	 * @return array{ok:bool,key:string,expected:?string,expected_status:int,actual_status:?int,actual_location:?string,message:string}
	 */
	public static function test( string $source, bool $http = false ): array {
		$key    = Store::normalize_key( $source );
		$result = self::resolve( $key );
		$out    = [
			'ok'              => false,
			'key'             => $key,
			'expected'        => null,
			'expected_status' => 0,
			'actual_status'   => null,
			'actual_location' => null,
			'message'         => '',
		];
		if ( null === $result ) {
			$out['message'] = __( 'No rule matches this source.', 'heartland-k9s-core' );
			return $out;
		}
		$out['expected']        = $result['url'];
		$out['expected_status'] = (int) $result['status'];
		switch ( $result['reason'] ) {
			case 'disabled':
				$out['message'] = __( 'Rule is disabled.', 'heartland-k9s-core' );
				return $out;
			case 'unresolved':
				$out['message'] = __( 'Destination does not resolve (missing or unpublished).', 'heartland-k9s-core' );
				return $out;
			case 'loop':
				$out['message'] = __( 'Destination equals the source (loop); the rule is skipped at runtime.', 'heartland-k9s-core' );
				return $out;
		}
		$out['ok']      = true;
		$out['message'] = 'gone' === $result['reason']
			? __( 'Resolves offline: 410 Gone.', 'heartland-k9s-core' )
			/* translators: 1: status code, 2: URL */
			: sprintf( __( 'Resolves offline: %1$d → %2$s', 'heartland-k9s-core' ), $result['status'], $result['url'] );

		if ( ! $http ) {
			return $out;
		}
		$response = wp_remote_head(
			home_url( $key ),
			[
				'redirection' => 0,
				'timeout'     => 10,
				'sslverify'   => false,
			]
		);
		if ( is_wp_error( $response ) ) {
			$out['ok']      = false;
			$out['message'] = __( 'HTTP check inconclusive: ', 'heartland-k9s-core' ) . $response->get_error_message();
			return $out;
		}
		$out['actual_status']   = (int) wp_remote_retrieve_response_code( $response );
		$location               = wp_remote_retrieve_header( $response, 'location' );
		$out['actual_location'] = is_string( $location ) ? $location : null;
		if ( 'gone' === $result['reason'] ) {
			$out['ok'] = 410 === $out['actual_status'];
		} else {
			$out['ok'] = $out['actual_status'] === $result['status'] && $out['actual_location'] === $result['url'];
		}
		$out['message'] = $out['ok']
			/* translators: 1: status code, 2: URL */
			? sprintf( __( 'HTTP OK: %1$d → %2$s', 'heartland-k9s-core' ), $out['actual_status'], (string) $out['actual_location'] )
			/* translators: 1: expected status, 2: expected URL, 3: actual status, 4: actual URL */
			: sprintf( __( 'HTTP mismatch: expected %1$d → %2$s, got %3$d → %4$s', 'heartland-k9s-core' ), $result['status'], (string) $result['url'], $out['actual_status'], (string) $out['actual_location'] );
		return $out;
	}

	/**
	 * When a post's slug/status changes, disable rules that now collide with it
	 * (source resolves to live content) or would loop (target equals source).
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  New post.
	 * @param \WP_Post $post_before Old post.
	 */
	public static function flag_collisions( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( $post_after->post_name === $post_before->post_name && $post_after->post_status === $post_before->post_status && $post_after->post_parent === $post_before->post_parent ) {
			return;
		}
		if ( 'publish' !== $post_after->post_status || ! is_post_type_viewable( $post_after->post_type ) ) {
			return;
		}
		$permalink = get_permalink( $post_after );
		if ( ! is_string( $permalink ) ) {
			return;
		}
		$live_key = Store::target_key( $permalink );
		if ( null === $live_key ) {
			return;
		}
		$rules   = Store::all();
		$changed = false;
		foreach ( $rules as $key => $rule ) {
			if ( ! $rule['enabled'] ) {
				continue;
			}
			[ $path_key ] = Store::split_key( $key );
			if ( $path_key === $live_key || $key === $live_key ) {
				$rules[ $key ]['enabled'] = false;
				$rules[ $key ]['note']    = trim( $rule['note'] . ' ' . __( '[auto-disabled: source now resolves to live content]', 'heartland-k9s-core' ) );
				$rules[ $key ]['updated'] = time();
				$changed = true;
			}
		}
		if ( $changed ) {
			Store::save_rules( $rules );
		}
	}
}
