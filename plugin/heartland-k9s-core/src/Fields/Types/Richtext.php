<?php
/**
 * Rich text field: HTML string filtered with wp_kses_post().
 *
 * Rendered as a textarea that the admin script upgrades to a teeny TinyMCE
 * editor (wp.editor.initialize) so it also works inside repeater rows. The
 * stored value is full HTML (paragraph tags included, wpautop disabled).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Richtext extends Type {

	public function name(): string {
		return 'richtext';
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
		return trim( wp_kses_post( $value ) );
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
				'class'            => 'large-text hk9-richtext',
				'rows'             => ! empty( $field['rows'] ) ? (int) $field['rows'] : 8,
				'data-hk9-control' => '1',
				'data-hk9-editor'  => '1',
			]
		);
		return '<div class="hk9-richtext-wrap"><textarea' . $this->attrs( $attrs ) . '>' . esc_textarea( (string) $value ) . '</textarea></div>';
	}

	public function format( array $field, mixed $value ): string {
		return trim( wp_strip_all_tags( (string) $value, false ) );
	}
}
