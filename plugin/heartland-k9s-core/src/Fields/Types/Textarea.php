<?php
/**
 * Textarea field: multi-line string (newlines kept).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Textarea extends Type {

	public function name(): string {
		return 'textarea';
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
		$value = str_replace( [ "\r\n", "\r" ], "\n", (string) $value );
		return sanitize_textarea_field( $value );
	}

	public function schema( array $field ): array {
		return [ 'type' => 'string' ];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$attrs = $this->control_attrs(
			$field,
			$name,
			$id,
			[
				'class'            => 'large-text hk9-textarea',
				'rows'             => ! empty( $field['rows'] ) ? (int) $field['rows'] : 4,
				'data-hk9-control' => '1',
			]
		);
		return '<textarea' . $this->attrs( $attrs ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
	}
}
