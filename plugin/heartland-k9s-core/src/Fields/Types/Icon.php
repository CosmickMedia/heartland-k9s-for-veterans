<?php
/**
 * Icon field: a lucide icon name validated against hk9_icon_name_list().
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Icon extends Type {

	/** Icons used by the reference design; the fallback when hk9_icon_name_list() is unavailable. */
	public const FALLBACK = [
		'arrow-right',
		'calendar',
		'check',
		'circle-alert',
		'circle-check',
		'clipboard-check',
		'clock',
		'dog',
		'dollar-sign',
		'file-text',
		'graduation-cap',
		'hand-heart',
		'heart-handshake',
		'heart-pulse',
		'heart',
		'mail',
		'map-pin',
		'menu',
		'phone-call',
		'phone',
		'qr-code',
		'quote',
		'shield-alert',
		'shield-check',
		'users',
		'x',
	];

	public function name(): string {
		return 'icon';
	}

	public function rest_type( array $field ): string {
		return 'string';
	}

	public function empty_value( array $field ): mixed {
		return '';
	}

	/**
	 * Available icon names (sorted, unique). Uses the theme/plugin helper
	 * hk9_icon_name_list() when it exists and returns a non-empty list.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$names = [];
		if ( function_exists( 'hk9_icon_name_list' ) ) {
			$list = hk9_icon_name_list();
			if ( is_array( $list ) ) {
				$names = array_values( array_filter( array_map( 'strval', $list ) ) );
			}
		}
		if ( empty( $names ) ) {
			$names = self::FALLBACK;
		}
		$names = array_values( array_unique( array_map( 'sanitize_key', $names ) ) );
		sort( $names );
		/**
		 * Filters the icon names offered by icon fields.
		 *
		 * @param string[] $names Icon names.
		 */
		$cache = (array) apply_filters( 'hk9/fields/icon_names', $names );
		return $cache;
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value || ! is_scalar( $value ) ) {
			$value = $field['default'] ?? '';
		}
		$value = sanitize_key( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! in_array( $value, self::names(), true ) ) {
			return '';
		}
		return $value;
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
				'class'            => 'hk9-select hk9-icon-select',
				'data-hk9-control' => '1',
			]
		);
		unset( $attrs['placeholder'] );
		$html  = '<div class="hk9-icon-field">';
		$html .= '<span class="hk9-icon-preview" data-hk9-icon-preview aria-hidden="true">' . Renderer::icon_svg( (string) $value ) . '</span>';
		$html .= '<select' . $this->attrs( $attrs ) . '>';
		$html .= '<option value=""' . ( '' === (string) $value ? ' selected' : '' ) . '>' . esc_html__( '— None —', 'heartland-k9s-core' ) . '</option>';
		foreach ( self::names() as $icon ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $icon ), $icon === (string) $value ? ' selected' : '' );
		}
		$html .= '</select></div>';
		return $html;
	}
}
