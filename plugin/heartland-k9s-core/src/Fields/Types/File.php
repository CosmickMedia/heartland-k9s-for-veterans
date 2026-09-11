<?php
/**
 * File field: attachment id of any allowed mime type (0 = none).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class File extends Type {

	public function name(): string {
		return 'file';
	}

	public function rest_type( array $field ): string {
		return 'integer';
	}

	public function empty_value( array $field ): mixed {
		return 0;
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value ) {
			$value = $field['default'] ?? 0;
		}
		$id = Image::attachment_id( $value, false );
		if ( $id > 0 && ! empty( $field['mimes'] ) ) {
			$mime    = (string) get_post_mime_type( $id );
			$allowed = array_map( 'strval', (array) $field['mimes'] );
			$ok      = false;
			foreach ( $allowed as $pattern ) {
				if ( $pattern === $mime || ( str_ends_with( $pattern, '/*' ) && str_starts_with( $mime, substr( $pattern, 0, -1 ) ) ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return 0;
			}
		}
		return $id;
	}

	public function schema( array $field ): array {
		return [
			'type'    => 'integer',
			'minimum' => 0,
		];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$id_value = (int) $value;
		$label    = $this->attachment_label( $id_value );
		$url      = $id_value > 0 ? wp_get_attachment_url( $id_value ) : '';
		$mimes    = ! empty( $field['mimes'] ) ? implode( ',', array_map( 'strval', (array) $field['mimes'] ) ) : '';

		$html  = sprintf(
			'<div class="hk9-media hk9-media--file%s" data-hk9-media="file" data-hk9-mimes="%s">',
			$id_value > 0 ? ' has-value' : '',
			esc_attr( $mimes )
		);
		$html .= sprintf( '<input type="hidden" id="%s" name="%s" value="%d" data-hk9-control="1" />', esc_attr( $id ), esc_attr( $name ), $id_value );
		$html .= '<div class="hk9-media__preview" data-hk9-media-preview>';
		if ( $url ) {
			$html .= sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $label ) );
		}
		$html .= '</div>';
		$html .= '<div class="hk9-media__actions">';
		$html .= sprintf( '<button type="button" class="button" data-hk9-media-select aria-describedby="%s">%s</button>', esc_attr( $id . '-help' ), esc_html__( 'Select file', 'heartland-k9s-core' ) );
		$html .= sprintf( '<button type="button" class="button hk9-media__replace" data-hk9-media-select>%s</button>', esc_html__( 'Replace', 'heartland-k9s-core' ) );
		$html .= sprintf( '<button type="button" class="button-link hk9-media__remove" data-hk9-media-remove>%s</button>', esc_html__( 'Remove', 'heartland-k9s-core' ) );
		$html .= '</div></div>';
		return $html;
	}

	public function format( array $field, mixed $value ): string {
		return $this->attachment_label( (int) $value );
	}
}
