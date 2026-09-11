<?php
/**
 * "Details" meta box for the custom post types, rendered with the field
 * framework. Inputs are named `hk9_meta[<key>]`; each field is its own meta
 * key (`hk9_<key>`) so the admin script mirrors them individually into the
 * block editor store when present.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Meta;

use HK9\Core\Fields\Renderer;
use HK9\Core\Fields\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class MetaBox {

	public const NONCE_FIELD  = 'hk9_details_nonce';
	public const NONCE_ACTION = 'hk9_details_';
	public const INPUT        = 'hk9_meta';

	private static bool $hooked = false;

	public static function register(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_action( 'add_meta_boxes', [ self::class, 'add_box' ], 10, 2 );
		foreach ( Definitions::post_types() as $type ) {
			add_action( "save_post_{$type}", [ self::class, 'save' ], 10, 3 );
		}
	}

	public static function add_box( string $post_type, $post ): void {
		if ( ! in_array( $post_type, Definitions::post_types(), true ) || empty( Definitions::for( $post_type ) ) ) {
			return;
		}
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		add_meta_box(
			'hk9_details',
			__( 'Details', 'heartland-k9s-core' ),
			[ self::class, 'render' ],
			$post_type,
			'normal',
			'high',
			[
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			]
		);
	}

	public static function render( \WP_Post $post ): void {
		$fields = Definitions::for( $post->post_type );
		$values = [];
		foreach ( $fields as $field ) {
			$values[ $field['key'] ] = Registry::get( $post->ID, $field['key'] );
		}
		wp_nonce_field( self::NONCE_ACTION . $post->ID, self::NONCE_FIELD );
		$renderer = new Renderer();
		$in_rest  = 'hk9_barkode' !== $post->post_type;
		echo sprintf(
			'<div class="hk9-details" data-hk9-details data-hk9-mirror="%s" data-hk9-meta-prefix="%s">',
			$in_rest ? 'fields' : 'none',
			esc_attr( Definitions::PREFIX )
		);
		echo '<input type="hidden" name="' . esc_attr( self::INPUT ) . '[__present]" value="1" />';
		echo $renderer->render_fields( $fields, $values, self::INPUT, 'hk9_meta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes.
		echo '</div>';
	}

	/** save_post_{type}: writes every field of the type (never deletes). */
	public static function save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::INPUT ] ) || ! is_array( $_POST[ self::INPUT ] ) ) {
			return;
		}
		$input = wp_unslash( $_POST[ self::INPUT ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		foreach ( Definitions::for( $post->post_type ) as $field ) {
			$value = array_key_exists( $field['key'], $input ) ? $input[ $field['key'] ] : null;
			$clean = Sanitizer::sanitize_field( $field, $value );
			update_post_meta( $post_id, Definitions::meta_key( $field['key'] ), wp_slash( $clean ) );
		}
	}
}
