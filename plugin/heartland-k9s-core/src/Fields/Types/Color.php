<?php
/**
 * Color field: string `#rrggbb` or ''.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Color extends Type {

	public function name(): string {
		return 'color';
	}

	public function rest_type( array $field ): string {
		return 'string';
	}

	public function empty_value( array $field ): mixed {
		return '';
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value || ! is_scalar( $value ) ) {
			$value = $field['default'] ?? '';
		}
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#?([0-9a-f]{3})$/', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		$hex = sanitize_hex_color( $value );
		return is_string( $hex ) ? $hex : '';
	}

	public function schema( array $field ): array {
		return [ 'type' => 'string' ];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$value = (string) $value;
		$attrs = $this->control_attrs(
			$field,
			$name,
			$id,
			[
				'type'             => 'text',
				'class'            => 'hk9-input hk9-input--color',
				'value'            => $value,
				'pattern'          => '#[0-9a-fA-F]{6}',
				'placeholder'      => '#rrggbb',
				'data-hk9-control' => '1',
			]
		);
		$picker = [
			'type'       => 'color',
			'id'         => $id . '-picker',
			'class'      => 'hk9-color-picker',
			'value'      => '' !== $value ? $value : '#000000',
			'aria-label' => sprintf( /* translators: %s: field label */ __( 'Pick %s', 'heartland-k9s-core' ), $field['label'] ),
		];
		return '<span class="hk9-color" data-hk9-color><input' . $this->attrs( $attrs ) . ' /> <input' . $this->attrs( $picker ) . ' /></span>';
	}
}
