<?php
/**
 * Datetime field: string `Y-m-d H:i` (site-local). With 'time_optional' the
 * stored value may be a bare `Y-m-d`.
 *
 * Rendered as a native datetime-local input (mapped from `Y-m-dTH:i`), or as
 * a date + time pair when the time is optional. The sanitizer accepts both a
 * string and a {date,time} pair.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Datetime extends Type {

	public function name(): string {
		return 'datetime';
	}

	public function rest_type( array $field ): string {
		return 'string';
	}

	public function empty_value( array $field ): mixed {
		return '';
	}

	/** Normalizes any accepted representation into `Y-m-d H:i` / `Y-m-d` / ''. */
	public static function normalize( mixed $value, bool $time_optional ): string {
		if ( is_array( $value ) ) {
			$date  = isset( $value['date'] ) && is_scalar( $value['date'] ) ? trim( (string) $value['date'] ) : '';
			$time  = isset( $value['time'] ) && is_scalar( $value['time'] ) ? trim( (string) $value['time'] ) : '';
			$value = '' === $time ? $date : $date . ' ' . $time;
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2})(?::\d{2})?)?$/', $value, $m ) ) {
			$date = Date::valid_date( $m[1] );
			if ( '' === $date ) {
				return '';
			}
			$time = $m[2] ?? '';
			if ( '' !== $time ) {
				[ $h, $i ] = array_map( 'intval', explode( ':', $time ) );
				if ( $h > 23 || $i > 59 ) {
					return '';
				}
				return $date . ' ' . sprintf( '%02d:%02d', $h, $i );
			}
			return $time_optional ? $date : '';
		}
		return '';
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value ) {
			$value = $field['default'] ?? '';
		}
		return self::normalize( $value, ! empty( $field['time_optional'] ) );
	}

	public function schema( array $field ): array {
		return [ 'type' => 'string' ];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$value = (string) $value;
		$date  = '';
		$time  = '';
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2}))?$/', $value, $m ) ) {
			$date = $m[1];
			$time = $m[2] ?? '';
		}
		if ( ! empty( $field['time_optional'] ) ) {
			$date_attrs = $this->control_attrs(
				$field,
				$name . '[date]',
				$id,
				[
					'type'  => 'date',
					'class' => 'hk9-input hk9-input--date',
					'value' => $date,
				]
			);
			unset( $date_attrs['placeholder'] );
			$time_attrs = [
				'type'       => 'time',
				'id'         => $id . '-time',
				'name'       => $name . '[time]',
				'class'      => 'hk9-input hk9-input--time',
				'value'      => $time,
				'aria-label' => sprintf( /* translators: %s: field label */ __( '%s time (optional)', 'heartland-k9s-core' ), $field['label'] ),
			];
			return '<span class="hk9-datetime" data-hk9-datetime="pair"><input' . $this->attrs( $date_attrs ) . ' /> <input' . $this->attrs( $time_attrs ) . ' /></span>';
		}
		$attrs = $this->control_attrs(
			$field,
			$name,
			$id,
			[
				'type'             => 'datetime-local',
				'class'            => 'hk9-input hk9-input--datetime',
				'value'            => '' !== $date ? $date . 'T' . ( '' !== $time ? $time : '00:00' ) : '',
				'data-hk9-control' => '1',
			]
		);
		unset( $attrs['placeholder'] );
		return '<span class="hk9-datetime" data-hk9-datetime="single"><input' . $this->attrs( $attrs ) . ' /></span>';
	}

	public function format( array $field, mixed $value ): string {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return $value;
		}
		$fmt = strlen( $value ) > 10 ? get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) : get_option( 'date_format' );
		return date_i18n( $fmt, $ts );
	}
}
