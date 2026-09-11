<?php
/**
 * Number field: int (default) or float when the definition sets 'float' => true
 * or a non-integer 'step'.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Number extends Type {

	public function name(): string {
		return 'number';
	}

	public function rest_type( array $field ): string {
		return $this->is_float( $field ) ? 'number' : 'integer';
	}

	public function empty_value( array $field ): mixed {
		return $this->is_float( $field ) ? 0.0 : 0;
	}

	private function is_float( array $field ): bool {
		if ( ! empty( $field['float'] ) ) {
			return true;
		}
		if ( isset( $field['step'] ) && is_numeric( $field['step'] ) ) {
			return (float) $field['step'] !== floor( (float) $field['step'] );
		}
		return false;
	}

	public function sanitize( array $field, mixed $value ): mixed {
		$is_float = $this->is_float( $field );
		if ( null === $value || '' === $value || ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			$default = $field['default'] ?? null;
			if ( null === $default || ! is_numeric( $default ) ) {
				return $this->empty_value( $field );
			}
			return $is_float ? (float) $default : (int) $default;
		}
		$number = $is_float ? (float) $value : (int) round( (float) $value );
		if ( isset( $field['min'] ) && is_numeric( $field['min'] ) && $number < $field['min'] ) {
			$number = $is_float ? (float) $field['min'] : (int) $field['min'];
		}
		if ( isset( $field['max'] ) && is_numeric( $field['max'] ) && $number > $field['max'] ) {
			$number = $is_float ? (float) $field['max'] : (int) $field['max'];
		}
		return $number;
	}

	public function schema( array $field ): array {
		$schema = [ 'type' => $this->rest_type( $field ) ];
		if ( isset( $field['min'] ) && is_numeric( $field['min'] ) ) {
			$schema['minimum'] = $field['min'] + 0;
		}
		if ( isset( $field['max'] ) && is_numeric( $field['max'] ) ) {
			$schema['maximum'] = $field['max'] + 0;
		}
		return $schema;
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$attrs = $this->control_attrs(
			$field,
			$name,
			$id,
			[
				'type'             => 'number',
				'class'            => 'small-text hk9-input hk9-input--number',
				'value'            => (string) $value,
				'min'              => isset( $field['min'] ) ? (string) $field['min'] : null,
				'max'              => isset( $field['max'] ) ? (string) $field['max'] : null,
				'step'             => isset( $field['step'] ) ? (string) $field['step'] : ( $this->is_float( $field ) ? 'any' : '1' ),
				'inputmode'        => $this->is_float( $field ) ? 'decimal' : 'numeric',
				'data-hk9-control' => '1',
				'data-hk9-float'   => $this->is_float( $field ) ? '1' : null,
			]
		);
		return '<input' . $this->attrs( $attrs ) . ' />';
	}
}
