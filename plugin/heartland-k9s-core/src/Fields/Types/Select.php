<?php
/**
 * Select field: string (or string[] when multiple). Options are value=>label;
 * a callable 'options_callback' resolves dynamic options (e.g. terms).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Field;
use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Select extends Type {

	public function name(): string {
		return 'select';
	}

	public function rest_type( array $field ): string {
		return ! empty( $field['multiple'] ) ? 'array' : 'string';
	}

	public function empty_value( array $field ): mixed {
		return ! empty( $field['multiple'] ) ? [] : '';
	}

	public function sanitize( array $field, mixed $value ): mixed {
		$options  = Field::options( $field );
		$has_opts = ! empty( $options );
		$multiple = ! empty( $field['multiple'] );

		if ( $multiple ) {
			if ( null === $value ) {
				$default = $field['default'] ?? [];
				return is_array( $default ) ? array_values( array_map( 'strval', $default ) ) : [];
			}
			if ( is_string( $value ) ) {
				$value = '' === $value ? [] : [ $value ];
			}
			if ( ! is_array( $value ) ) {
				return [];
			}
			$out = [];
			foreach ( $value as $item ) {
				if ( ! is_scalar( $item ) ) {
					continue;
				}
				$item = sanitize_text_field( (string) $item );
				if ( '' === $item || ( $has_opts && ! isset( $options[ $item ] ) ) || in_array( $item, $out, true ) ) {
					continue;
				}
				$out[] = $item;
			}
			return $out;
		}

		$default = isset( $field['default'] ) && is_scalar( $field['default'] ) ? (string) $field['default'] : '';
		if ( null === $value || ! is_scalar( $value ) ) {
			return $default;
		}
		$value = sanitize_text_field( (string) $value );
		// Anything that is not a valid option (including the "— Select —" placeholder '') becomes the
		// default, so the stored value always passes the schema enum (the default is always in it).
		if ( $has_opts && ! isset( $options[ $value ] ) ) {
			return $default;
		}
		return $value;
	}

	public function schema( array $field ): array {
		$item = [ 'type' => 'string' ];
		if ( Field::has_static_options( $field ) ) {
			$enum = array_map( 'strval', array_keys( Field::options( $field ) ) );
			if ( empty( $field['multiple'] ) ) {
				$default = isset( $field['default'] ) && is_scalar( $field['default'] ) ? (string) $field['default'] : '';
				if ( ! in_array( $default, $enum, true ) ) {
					$enum[] = $default;
				}
			}
			$item['enum'] = array_values( $enum );
		}
		if ( ! empty( $field['multiple'] ) ) {
			return [
				'type'  => 'array',
				'items' => $item,
			];
		}
		return $item;
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$multiple = ! empty( $field['multiple'] );
		$options  = Field::options( $field );
		$attrs    = $this->control_attrs(
			$field,
			$name . ( $multiple ? '[]' : '' ),
			$id,
			[
				'class'            => 'hk9-select',
				'multiple'         => $multiple,
				'data-hk9-control' => '1',
			]
		);
		unset( $attrs['placeholder'] );
		$default = isset( $field['default'] ) && is_scalar( $field['default'] ) ? (string) $field['default'] : '';
		if ( $multiple ) {
			$selected = array_map( 'strval', (array) $value );
		} else {
			// An empty value on a select with a non-empty default shows the default: '' is not a legal option there.
			$selected = [ ( '' === (string) $value && '' !== $default && ! empty( $options ) ) ? $default : (string) $value ];
		}

		$html = '<select' . $this->attrs( $attrs ) . '>';
		// The placeholder is only offered when '' is a storable value (empty default); otherwise the
		// sanitizer would silently turn it into the default and the control would misrepresent the state.
		if ( ! $multiple && ! isset( $options[''] ) && '' === $default && ( empty( $field['required'] ) || '' === (string) $value ) ) {
			$html .= '<option value=""' . ( '' === (string) $value ? ' selected' : '' ) . '>' . esc_html( $field['placeholder'] ?: __( '— Select —', 'heartland-k9s-core' ) ) . '</option>';
		}
		foreach ( $options as $opt_value => $label ) {
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $opt_value ),
				in_array( (string) $opt_value, $selected, true ) ? ' selected' : '',
				esc_html( $label )
			);
		}
		$html .= '</select>';
		return $html;
	}

	public function format( array $field, mixed $value ): string {
		$options = Field::options( $field );
		$values  = is_array( $value ) ? $value : [ $value ];
		$labels  = [];
		foreach ( $values as $v ) {
			$labels[] = $options[ (string) $v ] ?? (string) $v;
		}
		return implode( ', ', $labels );
	}
}
