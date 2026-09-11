<?php
/**
 * hk9/v1/pick — relationship/link picker search for the admin field framework.
 *
 * GET /wp-json/hk9/v1/pick?type=hk9_story,page&s=term&per_page=20[&include=1,2][&status=any]
 * → [ { id, title, type, type_label, status, url, edit_url } ]
 *
 * Permission: edit_posts. Types the current user cannot edit are silently skipped
 * (registry records therefore only appear for users who can edit them).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Rest;

use HK9\Core\PostTypes\Registrar;

defined( 'ABSPATH' ) || exit;

final class Pick {

	public const NAMESPACE = 'hk9/v1';
	public const ROUTE     = '/pick';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => [
					'type'     => [
						'type'              => 'string',
						'default'           => 'page',
						'sanitize_callback' => static fn( $v ): string => is_scalar( $v ) ? preg_replace( '/[^a-z0-9_,]/', '', strtolower( (string) $v ) ) : 'page',
					],
					's'        => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn( $v ): string => is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '',
					],
					'per_page' => [
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					],
					'include'  => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn( $v ): string => is_scalar( $v ) ? implode( ',', wp_parse_id_list( (string) $v ) ) : '',
					],
					'status'   => [
						'type'              => 'string',
						'default'           => 'editable',
						'enum'              => [ 'editable', 'publish', 'any' ],
					],
				],
			]
		);
	}

	/**
	 * Post types the current user may pick from, for a comma list ('all'/'any' = every hk9 type + page + post).
	 *
	 * @return string[]
	 */
	public static function allowed_types( string $requested ): array {
		$hk9 = array_values( array_diff( Registrar::TYPES, [ 'hk9_submission' ] ) );
		$req = array_filter( array_map( 'trim', explode( ',', $requested ) ) );
		if ( ! $req || in_array( 'all', $req, true ) || in_array( 'any', $req, true ) ) {
			$req = array_merge( [ 'page', 'post' ], $hk9 );
		}
		$out = [];
		foreach ( array_unique( $req ) as $type ) {
			if ( 'hk9_submission' === $type ) {
				continue;
			}
			$obj = get_post_type_object( $type );
			if ( ! $obj || ! $obj->show_ui ) {
				continue;
			}
			if ( ! current_user_can( $obj->cap->edit_posts ) ) {
				continue;
			}
			$out[] = $type;
		}
		return $out;
	}

	public static function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$types = self::allowed_types( (string) $request['type'] );
		if ( ! $types ) {
			return rest_ensure_response( [] );
		}
		$status = (string) $request['status'];
		$statuses = match ( $status ) {
			'publish' => [ 'publish' ],
			'any'     => [ 'publish', 'draft', 'pending', 'future', 'private' ],
			default   => [ 'publish', 'draft', 'pending', 'future', 'private' ],
		};

		$args = [
			'post_type'              => $types,
			'post_status'            => $statuses,
			'posts_per_page'         => (int) $request['per_page'],
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		];
		$include = wp_parse_id_list( (string) $request['include'] );
		if ( $include ) {
			$args['post__in']       = $include;
			$args['orderby']        = 'post__in';
			$args['posts_per_page'] = max( count( $include ), (int) $request['per_page'] );
		} else {
			$search = (string) $request['s'];
			if ( '' !== $search ) {
				$args['s']       = $search;
				$args['orderby'] = 'relevance';
			} else {
				$args['orderby'] = [ 'post_type' => 'ASC', 'menu_order' => 'ASC', 'title' => 'ASC' ];
			}
		}

		$query = new \WP_Query( $args );
		$items = [];
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$items[] = self::item( $post );
		}
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) count( $items ) );
		return $response;
	}

	/**
	 * @return array{id:int,title:string,type:string,type_label:string,status:string,url:string,edit_url:string}
	 */
	public static function item( \WP_Post $post ): array {
		$obj      = get_post_type_object( $post->post_type );
		$viewable = $obj && is_post_type_viewable( $obj ) && 'publish' === $post->post_status;
		$url      = $viewable ? (string) get_permalink( $post ) : '';
		$edit     = get_edit_post_link( $post->ID, 'raw' );
		$title    = get_the_title( $post );
		return [
			'id'         => (int) $post->ID,
			'title'      => '' !== $title ? html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) : __( '(no title)', 'heartland-k9s-core' ),
			'type'       => $post->post_type,
			'type_label' => $obj ? (string) $obj->labels->singular_name : $post->post_type,
			'status'     => $post->post_status,
			'url'        => $url,
			'edit_url'   => is_string( $edit ) ? $edit : '',
		];
	}
}
