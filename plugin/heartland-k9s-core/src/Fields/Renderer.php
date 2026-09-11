<?php
/**
 * Server-side renderer for field lists.
 *
 * Produces admin form markup with bracketed names (`hk9_sec_hero[heading]`,
 * `hk9_sec_features[cards][2][icon]`), labelled controls, help text wired via
 * aria-describedby and required markers. The JS layer (assets/js/fields.js)
 * enhances media pickers, repeaters, link/relationship pickers and editors.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/** Composite types whose label is a group label (no single focusable control). */
	private const GROUP_TYPES = [ 'image', 'gallery', 'file', 'link', 'relationship' ];

	/**
	 * Renders a list of fields inside a `.hk9-fields` container.
	 *
	 * @param array  $fields      Field definitions.
	 * @param array  $values      Canonical values keyed by field key.
	 * @param string $name_prefix Input name prefix, e.g. `hk9_sec_hero`.
	 * @param string $id_prefix   Element id prefix.
	 */
	public function render_fields( array $fields, array $values, string $name_prefix, string $id_prefix ): string {
		$html = '<div class="hk9-fields">';
		foreach ( Field::normalize_list( $fields ) as $field ) {
			$value = array_key_exists( $field['key'], $values ) ? $values[ $field['key'] ] : $field['default'];
			$html .= $this->render_field( $field, $value, $name_prefix, $id_prefix );
		}
		$html .= '</div>';
		return $html;
	}

	/** Renders one field with its wrapper. */
	public function render_field( array $field, mixed $value, string $name_prefix, string $id_prefix ): string {
		$field = Field::normalize( $field );
		if ( null === $field ) {
			return '';
		}
		$type  = Field::type( $field['type'] );
		$key   = $field['key'];
		$name  = '' === $name_prefix ? $key : $name_prefix . '[' . $key . ']';
		$id    = self::id( '' === $id_prefix ? $key : $id_prefix . '__' . $key );
		$value = $type->sanitize( $field, $value );

		$control  = $type->render( $field, $value, $name, $id, $this );
		$help     = '' !== $field['help'] ? sprintf( '<p class="hk9-field__help" id="%s-help">%s</p>', esc_attr( $id ), wp_kses( $field['help'], [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'code' => [], 'em' => [], 'strong' => [], 'br' => [] ] ) ) : '';
		$required = $field['required'] ? '<span class="hk9-field__required" aria-hidden="true">*</span><span class="screen-reader-text"> ' . esc_html__( '(required)', 'heartland-k9s-core' ) . '</span>' : '';
		$classes  = 'hk9-field hk9-field--' . $field['type'] . ( $field['required'] ? ' is-required' : '' ) . ( ! empty( $field['width'] ) ? ' hk9-field--' . sanitize_html_class( (string) $field['width'] ) : '' );
		$data     = sprintf( ' data-hk9-field data-hk9-key="%s" data-hk9-type="%s"', esc_attr( $key ), esc_attr( $field['type'] ) );
		if ( 'select' === $field['type'] && $field['multiple'] ) {
			$data .= ' data-hk9-multiple="1"';
		}
		if ( 'relationship' === $field['type'] && $field['multiple'] ) {
			$data .= ' data-hk9-multiple="1"';
		}
		if ( $field['private'] ) {
			$data .= ' data-hk9-private="1"';
		}

		$wrapper = $type->wrapper();
		if ( 'toggle' === $wrapper ) {
			return sprintf(
				'<div class="%s"%s><label class="hk9-toggle" for="%s">%s<span class="hk9-toggle__text">%s%s</span></label>%s</div>',
				esc_attr( $classes ),
				$data,
				esc_attr( $id ),
				$control,
				esc_html( $field['label'] ),
				$required,
				$help
			);
		}
		if ( 'fieldset' === $wrapper ) {
			return sprintf(
				'<fieldset class="%s"%s%s><legend class="hk9-field__label">%s%s</legend>%s<div class="hk9-field__control">%s</div></fieldset>',
				esc_attr( $classes ),
				$data,
				'' !== $field['help'] ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '',
				esc_html( $field['label'] ),
				$required,
				$help,
				$control
			);
		}
		if ( in_array( $field['type'], self::GROUP_TYPES, true ) ) {
			return sprintf(
				'<div class="%s"%s role="group" aria-labelledby="%s-label"%s><span class="hk9-field__label" id="%s-label">%s%s</span><div class="hk9-field__control">%s</div>%s</div>',
				esc_attr( $classes ),
				$data,
				esc_attr( $id ),
				'' !== $field['help'] ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '',
				esc_attr( $id ),
				esc_html( $field['label'] ),
				$required,
				$control,
				$help
			);
		}
		return sprintf(
			'<div class="%s"%s><label class="hk9-field__label" for="%s">%s%s</label><div class="hk9-field__control">%s</div>%s</div>',
			esc_attr( $classes ),
			$data,
			esc_attr( $id ),
			esc_html( $field['label'] ),
			$required,
			$control,
			$help
		);
	}

	/** Makes a safe element id from a name-like string. */
	public static function id( string $raw ): string {
		$id = preg_replace( '/[^A-Za-z0-9_\-]+/', '_', $raw ) ?? '';
		return trim( $id, '_' ) ?: 'hk9_field';
	}

	/** Sprite URL for icon previews (theme assets/dist/icons.svg), '' when absent. */
	public static function sprite_url(): string {
		static $url = null;
		if ( null !== $url ) {
			return $url;
		}
		$url  = '';
		$file = get_template_directory() . '/assets/dist/icons.svg';
		if ( file_exists( $file ) ) {
			$url = get_template_directory_uri() . '/assets/dist/icons.svg?ver=' . (string) filemtime( $file );
		}
		/**
		 * Filters the icon sprite URL used for admin previews.
		 *
		 * @param string $url Sprite URL or '' when unavailable.
		 */
		$url = (string) apply_filters( 'hk9/fields/icon_sprite_url', $url );
		return $url;
	}

	/** Prefix of the symbol ids inside the sprite (filterable, default none). */
	public static function sprite_symbol_prefix(): string {
		return (string) apply_filters( 'hk9/fields/icon_symbol_prefix', '' );
	}

	/** Inline `<svg><use>` preview for an icon name ('' when no sprite or no name). */
	public static function icon_svg( string $name ): string {
		$name   = sanitize_key( $name );
		$sprite = self::sprite_url();
		if ( '' === $name || '' === $sprite ) {
			return '';
		}
		return sprintf(
			'<svg class="hk9-icon" width="20" height="20" aria-hidden="true" focusable="false"><use href="%s#%s"></use></svg>',
			esc_url( $sprite ),
			esc_attr( self::sprite_symbol_prefix() . $name )
		);
	}

	/**
	 * Formats values as "Label: value" lines (revision diffs).
	 */
	public static function format_fields( array $fields, array $values, string $indent = '' ): string {
		$lines = [];
		foreach ( Field::normalize_list( $fields ) as $field ) {
			$type  = Field::type( $field['type'] );
			$value = array_key_exists( $field['key'], $values ) ? $values[ $field['key'] ] : $field['default'];
			$text  = $type->format( $field, $value );
			if ( in_array( $field['type'], [ 'repeater', 'group' ], true ) ) {
				$lines[] = $indent . $field['label'] . ':';
				if ( '' !== $text ) {
					$lines[] = preg_replace( '/^/m', $indent, $text );
				}
				continue;
			}
			$lines[] = $indent . $field['label'] . ': ' . $text;
		}
		return implode( "\n", $lines );
	}
}
