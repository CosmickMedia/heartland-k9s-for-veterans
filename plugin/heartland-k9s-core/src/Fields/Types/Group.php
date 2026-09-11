<?php
/**
 * Group field: associative array of sub-fields.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;
use HK9\Core\Fields\Sanitizer;
use HK9\Core\Fields\Schema;

defined( 'ABSPATH' ) || exit;

class Group extends Type {

	public function name(): string {
		return 'group';
	}

	public function rest_type( array $field ): string {
		return 'object';
	}

	public function wrapper(): string {
		return 'fieldset';
	}

	public function empty_value( array $field ): mixed {
		return Sanitizer::sanitize( $field['fields'] ?? [], [] );
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value ) {
			$value = $field['default'] ?? [];
		}
		return Sanitizer::sanitize( $field['fields'] ?? [], is_array( $value ) ? $value : [] );
	}

	public function schema( array $field ): array {
		return Schema::for( $field['fields'] ?? [] );
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		return '<div class="hk9-group" data-hk9-group>' . $renderer->render_fields( $field['fields'] ?? [], is_array( $value ) ? $value : [], $name, $id ) . '</div>';
	}

	public function format( array $field, mixed $value ): string {
		return Renderer::format_fields( $field['fields'] ?? [], is_array( $value ) ? $value : [], '  ' );
	}
}
