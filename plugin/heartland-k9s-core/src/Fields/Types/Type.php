<?php
/**
 * Base class for field types.
 *
 * A type knows how to (1) produce a canonical default, (2) sanitize an
 * arbitrary input into the stored PHP value, (3) describe that value as a
 * JSON schema property, (4) render an admin control and (5) format the value
 * as readable text for revision diffs.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

abstract class Type {

	/** Type name as used in field definitions. */
	abstract public function name(): string;

	/** Type used for register_post_meta() when this field is a standalone meta key. */
	abstract public function rest_type( array $field ): string;

	/** Canonical empty value for this type. */
	abstract public function empty_value( array $field ): mixed;

	/**
	 * Sanitizes a raw input into the canonical stored value.
	 *
	 * Must be idempotent: sanitize( sanitize( $x ) ) === sanitize( $x ), and the
	 * result must validate against schema().
	 */
	abstract public function sanitize( array $field, mixed $value ): mixed;

	/** JSON schema for the stored value (without the `default` key, added by Schema). */
	abstract public function schema( array $field ): array;

	/**
	 * Renders the form control (without the shared label/help wrapper).
	 *
	 * @param array    $field    Normalized field definition.
	 * @param mixed    $value    Canonical value.
	 * @param string   $name     Form input name (e.g. `hk9_sec_hero[heading]`).
	 * @param string   $id       Base element id.
	 * @param Renderer $renderer Renderer (for nested fields).
	 */
	abstract public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string;

	/** Wrapper style: 'default' (label above control), 'toggle' (label after checkbox) or 'fieldset'. */
	public function wrapper(): string {
		return 'default';
	}

	/** Canonical default for the field: the declared default, sanitized, or the empty value. */
	public function default( array $field ): mixed {
		if ( array_key_exists( 'default', $field ) && null !== $field['default'] ) {
			return $this->sanitize( $field, $field['default'] );
		}
		return $this->empty_value( $field );
	}

	/** Formats the value as readable text (revision diffs). */
	public function format( array $field, mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'heartland-k9s-core' ) : __( 'No', 'heartland-k9s-core' );
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		return wp_json_encode( $value ) ?: '';
	}

	/** Shared attribute builder for controls. */
	protected function attrs( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $attr => $val ) {
			if ( null === $val || false === $val ) {
				continue;
			}
			if ( true === $val ) {
				$out .= ' ' . esc_attr( $attr );
				continue;
			}
			$out .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( (string) $val ) );
		}
		return $out;
	}

	/** Common attributes for a control: id, name, aria wiring. */
	protected function control_attrs( array $field, string $name, string $id, array $extra = [] ): array {
		$attrs = [
			'id'   => $id,
			'name' => $name,
		];
		if ( ! empty( $field['help'] ) ) {
			$attrs['aria-describedby'] = $id . '-help';
		}
		if ( ! empty( $field['required'] ) ) {
			$attrs['aria-required'] = 'true';
		}
		if ( ! empty( $field['placeholder'] ) ) {
			$attrs['placeholder'] = $field['placeholder'];
		}
		return array_merge( $attrs, $extra );
	}

	/** ` aria-describedby="<id>-help"` only when the field has help text (the referenced element must exist). */
	protected function describedby( array $field, string $id ): string {
		if ( empty( $field['help'] ) ) {
			return '';
		}
		return ' aria-describedby="' . esc_attr( $id . '-help' ) . '"';
	}

	/** Resolves a filename label for an attachment id. */
	protected function attachment_label( int $id ): string {
		if ( $id <= 0 ) {
			return '';
		}
		$file = get_attached_file( $id );
		if ( $file ) {
			return wp_basename( $file );
		}
		$title = get_the_title( $id );
		return $title ? $title : sprintf( '#%d', $id );
	}
}
