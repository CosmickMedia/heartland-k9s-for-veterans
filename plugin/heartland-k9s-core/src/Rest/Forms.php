<?php
/**
 * REST: POST hk9/v1/forms/{id} — JS submission path returning the same result as admin-post.
 *
 * The route is public (permission_callback __return_true); it is protected by the
 * same nonce / honeypot / time-trap / single-use token / rate-limit checks as the
 * non-JS path. Rate-limited requests answer 429.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Rest;

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
		register_rest_route(
			'hk9/v1',
			'/forms/(?P<id>[a-z0-9_-]+)',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'submit' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'description'       => __( 'Form id (contact | application).', 'heartland-k9s-core' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static fn( $value ): bool => is_string( $value ) && null !== Handler::form( $value ),
					],
				],
			]
		);
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
		$response->header( 'Cache-Control', 'no-store' );
		if ( 429 === $result->status ) {
			$response->header( 'Retry-After', (string) HOUR_IN_SECONDS );
		}
		return $response;
	}
}
