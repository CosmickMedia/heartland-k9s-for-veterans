<?php
/**
 * Toggle field: boolean. Rendered as a checkbox preceded by a hidden "0" so an
 * unchecked box still posts a value.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Toggle extends Type {

	public function name(): string {
		return 'toggle';
	}

	public function rest_type( array $field ): string {
		return 'boolean';
	}

	public function empty_value( array $field ): mixed {
		return false;
	}

	public function wrapper(): string {
		return 'toggle';
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value ) {
			return (bool) ( $field['default'] ?? false );
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			// A hidden "0" + checked "1" posted as an array (should not happen, but be safe).
			return in_array( '1', array_map( 'strval', $value ), true );
		}
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, [ '1', 'true', 'on', 'yes' ], true );
		}
		return (bool) $value;
	}

	public function schema( array $field ): array {
		return [ 'type' => 'boolean' ];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$attrs = $this->control_attrs(
			$field,
			$name,
			$id,
			[
				'type'             => 'checkbox',
				'class'            => 'hk9-toggle__input',
				'value'            => '1',
				'checked'          => (bool) $value,
				'data-hk9-control' => '1',
			]
		);
		unset( $attrs['placeholder'] );
		return '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" /><input' . $this->attrs( $attrs ) . ' />';
	}
}
