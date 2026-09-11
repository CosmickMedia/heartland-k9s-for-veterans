<?php
/**
 * Image field: attachment id (0 = none). Uses the wp.media picker.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Image extends Type {

	public function name(): string {
		return 'image';
	}

	public function rest_type( array $field ): string {
		return 'integer';
	}

	public function empty_value( array $field ): mixed {
		return 0;
	}

	/** Validates an attachment id (optionally restricted to image mime types). */
	public static function attachment_id( mixed $value, bool $images_only = false ): int {
		if ( is_array( $value ) ) {
			$value = $value['id'] ?? 0;
		}
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return 0;
		}
		$id = absint( $value );
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
			return 0;
		}
		if ( $images_only && ! wp_attachment_is_image( $id ) ) {
			return 0;
		}
		return $id;
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value ) {
			$value = $field['default'] ?? 0;
		}
		return self::attachment_id( $value, true );
	}

	public function schema( array $field ): array {
		return [
			'type'    => 'integer',
			'minimum' => 0,
		];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$id_value = (int) $value;
		$size     = ! empty( $field['size'] ) ? (string) $field['size'] : 'medium';
		$preview  = $id_value > 0 ? wp_get_attachment_image( $id_value, $size, false, [ 'class' => 'hk9-media__img' ] ) : '';

		$html  = sprintf(
			'<div class="hk9-media hk9-media--image%s" data-hk9-media="image" data-hk9-size="%s">',
			$id_value > 0 ? ' has-value' : '',
			esc_attr( $size )
		);
		$html .= sprintf( '<input type="hidden" id="%s" name="%s" value="%d" data-hk9-control="1" />', esc_attr( $id ), esc_attr( $name ), $id_value );
		$html .= '<div class="hk9-media__preview" data-hk9-media-preview>' . $preview . '</div>';
		$html .= '<div class="hk9-media__actions">';
		$html .= sprintf( '<button type="button" class="button hk9-media__select" data-hk9-media-select%s>%s</button>', $this->describedby( $field, $id ), esc_html__( 'Select image', 'heartland-k9s-core' ) );
		$html .= sprintf( '<button type="button" class="button hk9-media__replace" data-hk9-media-select>%s</button>', esc_html__( 'Replace', 'heartland-k9s-core' ) );
		$html .= sprintf( '<button type="button" class="button-link hk9-media__remove" data-hk9-media-remove>%s</button>', esc_html__( 'Remove', 'heartland-k9s-core' ) );
		$html .= '</div></div>';
		return $html;
	}

	public function format( array $field, mixed $value ): string {
		return $this->attachment_label( (int) $value );
	}
}
