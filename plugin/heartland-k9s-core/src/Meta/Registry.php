<?php
/**
 * CPT meta registration (§6): register_post_meta per type with typed
 * schemas, sanitizers, auth and revisions (where the type supports them),
 * REST pre-sanitization and the "Details" meta box.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Meta;

use HK9\Core\Fields\Access;
use HK9\Core\Fields\Assets;
use HK9\Core\Fields\Field;
use HK9\Core\Fields\Sanitizer;
use HK9\Core\Fields\Schema;

defined( 'ABSPATH' ) || exit;

final class Registry {

	private static bool $registered = false;

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_meta' ], 20 );
		foreach ( Definitions::post_types() as $type ) {
			add_filter( "rest_pre_insert_{$type}", [ self::class, 'rest_pre_insert' ], 10, 2 );
		}
		if ( class_exists( Assets::class ) ) {
			Assets::register();
			Assets::add_post_types( Definitions::post_types() );
		}
		if ( class_exists( MetaBox::class ) ) {
			MetaBox::register();
		}
	}

	/**
	 * Whether a field is exposed through REST for a post type. Registry
	 * records never are; private (admin-only) fields never are either — the
	 * meta box form saves them through save_post, not the REST API.
	 */
	public static function in_rest( string $post_type, array $field ): bool {
		if ( 'hk9_barkode' === $post_type ) {
			return false;
		}
		return empty( $field['no_rest'] ) && empty( $field['private'] );
	}

	/** Registers every CPT field as post meta. */
	public static function register_meta(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		foreach ( Definitions::post_types() as $post_type ) {
			$revisions = post_type_supports( $post_type, 'revisions' );
			foreach ( Definitions::for( $post_type ) as $field ) {
				$key  = Definitions::meta_key( $field['key'] );
				$type = Field::type( $field['type'] );
				$args = [
					'type'              => $type->rest_type( $field ),
					'description'       => $field['label'],
					'single'            => true,
					'sanitize_callback' => static fn( $value ) => Sanitizer::sanitize_field( $field, $value ),
					'auth_callback'     => [ self::class, 'auth' ],
					'show_in_rest'      => false,
				];
				if ( $revisions ) {
					$args['revisions_enabled'] = true;
				}
				if ( self::in_rest( $post_type, $field ) ) {
					$args['show_in_rest'] = [
						'schema'           => Schema::property( $field ),
						'prepare_callback' => static function ( $value, $request ) use ( $field ) {
							if ( '' === $value || null === $value ) {
								return $field['default'];
							}
							return Sanitizer::sanitize_field( $field, $value );
						},
					];
				}
				register_post_meta( $post_type, $key, $args );

				if ( $revisions ) {
					RevisionGuard::track(
						$post_type,
						$key,
						$field['label'],
						static fn( $value ) => Sanitizer::sanitize_field( $field, $value ),
						static function ( $value ) use ( $field ): string {
							if ( null === $value || '' === $value ) {
								return '';
							}
							return (string) Field::type( $field['type'] )->format( $field, Sanitizer::sanitize_field( $field, $value ) );
						}
					);
				}
			}
		}
	}

	/** auth_callback: the user must be able to edit the post. */
	public static function auth( $allowed, $meta_key, $post_id, $user_id ): bool {
		return user_can( (int) $user_id, 'edit_post', (int) $post_id );
	}

	/** Pre-sanitizes hk9_* meta in REST requests before schema validation. */
	public static function rest_pre_insert( $prepared_post, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $prepared_post;
		}
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || empty( $meta ) ) {
			return $prepared_post;
		}
		$post_type = isset( $prepared_post->post_type ) ? (string) $prepared_post->post_type : '';
		if ( '' === $post_type && ! empty( $prepared_post->ID ) ) {
			$post_type = (string) get_post_type( (int) $prepared_post->ID );
		}
		if ( '' === $post_type ) {
			$filter    = current_filter();
			$post_type = str_starts_with( $filter, 'rest_pre_insert_' ) ? substr( $filter, strlen( 'rest_pre_insert_' ) ) : '';
		}
		$post_id = ! empty( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;
		$changed = false;
		foreach ( Definitions::for( $post_type ) as $field ) {
			$key = Definitions::meta_key( $field['key'] );
			if ( ! array_key_exists( $key, $meta ) || null === $meta[ $key ] ) {
				continue;
			}
			$clean = Sanitizer::sanitize_field( $field, $meta[ $key ] );
			// Post references the current user may not introduce are dropped (stored ones are kept).
			$meta[ $key ] = Access::restrict_field( $field, $clean, $post_id > 0 ? get_post_meta( $post_id, $key, true ) : null );
			$changed      = true;
		}
		if ( $changed ) {
			$request->set_param( 'meta', $meta );
		}
		return $prepared_post;
	}

	/**
	 * Reads one CPT field value with defaults (helper for hk9_cpt_meta()).
	 */
	public static function get( int $post_id, string $key, mixed $default = null ): mixed {
		$post_type = (string) get_post_type( $post_id );
		$field     = Definitions::field( $post_type, $key );
		$meta_key  = Definitions::meta_key( $key );
		// Private (admin-only) fields such as review_notes / source_note never reach the frontend.
		if ( $field && ! empty( $field['private'] ) && ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $field['default'];
		}
		if ( ! metadata_exists( 'post', $post_id, $meta_key ) ) {
			if ( null !== $default ) {
				return $default;
			}
			return $field ? $field['default'] : null;
		}
		$raw = get_post_meta( $post_id, $meta_key, true );
		if ( ! $field ) {
			return $raw;
		}
		return Sanitizer::sanitize_field( $field, $raw );
	}

	/** Readable value formatter (admin columns etc.). */
	public static function format( int $post_id, string $key ): string {
		$post_type = (string) get_post_type( $post_id );
		$field     = Definitions::field( $post_type, $key );
		if ( ! $field ) {
			return '';
		}
		return (string) Field::type( $field['type'] )->format( $field, self::get( $post_id, $key ) );
	}
}
