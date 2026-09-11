<?php
/**
 * Date field: string `Y-m-d` (site-local) or '' when unset.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Date extends Type {

	public function name(): string {
		return 'date';
	}

	public function rest_type( array $field ): string {
		return 'string';
	}

	public function empty_value( array $field ): mixed {
		return '';
	}

	/** Validates a `Y-m-d` string strictly. */
	public static function valid_date( string $value ): string {
		$value = trim( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $value ) {
			return '';
		}
		return $value;
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value || ! is_scalar( $value ) ) {
			$value = $field['default'] ?? '';
		}
		$value = (string) $value;
		// Accept datetime input and keep the date part.
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})[T ]/', $value, $m ) ) {
			$value = $m[1];
		}
		return self::valid_date( $value );
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
				'type'             => 'date',
				'class'            => 'hk9-input hk9-input--date',
				'value'            => (string) $value,
				'data-hk9-control' => '1',
			]
		);
		unset( $attrs['placeholder'] );
		return '<input' . $this->attrs( $attrs ) . ' />';
	}

	public function format( array $field, mixed $value ): string {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		return date_i18n( get_option( 'date_format' ), strtotime( $value . ' 00:00:00' ) );
	}
}
