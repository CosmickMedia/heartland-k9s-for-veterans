<?php
/**
 * REST: POST hk9/v1/forms/{id} — JS submission path returning the same result as admin-post.
 *       GET  hk9/v1/forms/{id}/token — fresh {nonce, ts, token} for a rendered form (never cached),
 *       so a page served from a full-page cache does not hand every visitor the same single-use token.
 *
 * The routes are public (permission_callback __return_true); submissions are protected
 * by the same nonce / honeypot / time-trap / single-use token / rate-limit checks as the
 * non-JS path. Rate-limited requests answer 429.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Rest;

use HK9\Core\Forms\Antispam;
use HK9\Core\Forms\Handler;

defined( 'ABSPATH' ) || exit;

final class Forms {

	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$id_arg = [
			'description'       => __( 'Form id (contact | application).', 'heartland-k9s-core' ),
			'type'              => 'string',
			'required'          => true,
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => static fn( $value ): bool => is_string( $value ) && null !== Handler::form( $value ),
		];
		register_rest_route(
			'hk9/v1',
			'/forms/(?P<id>[a-z0-9_-]+)',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'submit' ],
				'permission_callback' => '__return_true',
				'args'                => [ 'id' => $id_arg ],
			]
		);
		register_rest_route(
			'hk9/v1',
			'/forms/(?P<id>[a-z0-9_-]+)/token',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'token' ],
				'permission_callback' => '__return_true',
				'args'                => [ 'id' => $id_arg ],
			]
		);
	}

	/** GET /forms/{id}/token: fresh anti-spam fields for the current visitor (no-store). */
	public static function token( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = sanitize_key( (string) $request['id'] );
		$tokens = Antispam::issue( $id );
		$response = new \WP_REST_Response(
			[
				'nonce' => wp_create_nonce( Antispam::nonce_action( $id ) ),
				'ts'    => (string) $tokens['ts'],
				'token' => (string) $tokens['token'],
			],
			200
		);
		foreach ( self::no_store_headers() as $name => $value ) {
			$response->header( $name, $value );
		}
		return $response;
	}

	/** Headers that keep a response out of every cache layer (browser, proxy, CDN). */
	private static function no_store_headers(): array {
		return [
			'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
			'Pragma'        => 'no-cache',
			'Expires'       => 'Wed, 11 Jan 1984 05:00:00 GMT',
		];
	}

	public static function submit( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = sanitize_key( (string) $request['id'] );
		$params = $request->get_body_params();
		if ( [] === $params ) {
			$json   = $request->get_json_params();
			$params = is_array( $json ) ? $json : [];
		}

		$result   = Handler::process( $id, $params );
		$response = new \WP_REST_Response( $result->to_array(), $result->status );
		foreach ( self::no_store_headers() as $name => $value ) {
			$response->header( $name, $value );
		}
		if ( 429 === $result->status ) {
			$response->header( 'Retry-After', (string) HOUR_IN_SECONDS );
		}
		return $response;
	}
}
