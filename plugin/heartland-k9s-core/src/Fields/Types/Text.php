<?php
/**
 * Text field: single-line string.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Text extends Type {

	public function name(): string {
		return 'text';
	}

	public function rest_type( array $field ): string {
		return 'string';
	}

	public function empty_value( array $field ): mixed {
		return '';
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value || is_array( $value ) || is_object( $value ) ) {
			return $field['default'] ?? '';
		}
		$value = sanitize_text_field( (string) $value );
		if ( ! empty( $field['maxlength'] ) && (int) $field['maxlength'] > 0 ) {
			$value = mb_substr( $value, 0, (int) $field['maxlength'] );
		}
		return $value;
	}

	public function schema( array $field ): array {
		$schema = [ 'type' => 'string' ];
		if ( ! empty( $field['maxlength'] ) ) {
			$schema['maxLength'] = (int) $field['maxlength'];
		}
		return $schema;
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$attrs = $this->control_attrs(
			$field,
			$name,
			$id,
			[
				'type'             => 'text',
				'class'            => 'regular-text hk9-input',
				'value'            => (string) $value,
				'maxlength'        => ! empty( $field['maxlength'] ) ? (int) $field['maxlength'] : null,
				'data-hk9-control' => '1',
			]
		);
		return '<input' . $this->attrs( $attrs ) . ' />';
	}
}
