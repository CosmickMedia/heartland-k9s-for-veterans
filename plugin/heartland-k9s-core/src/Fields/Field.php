<?php
/**
 * Field definition helpers: type registry + definition normalization.
 *
 * A field is a plain array: ['type'=>..., 'key'=>..., 'label'=>..., 'help'=>...,
 * 'default'=>..., 'required'=>bool, 'placeholder'=>..., ...type options].
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields;

use HK9\Core\Fields\Types\Type;

defined( 'ABSPATH' ) || exit;

final class Field {

	/** type name => class. */
	private const TYPES = [
		'text'         => Types\Text::class,
		'textarea'     => Types\Textarea::class,
		'richtext'     => Types\Richtext::class,
		'number'       => Types\Number::class,
		'toggle'       => Types\Toggle::class,
		'select'       => Types\Select::class,
		'icon'         => Types\Icon::class,
		'image'        => Types\Image::class,
		'gallery'      => Types\Gallery::class,
		'file'         => Types\File::class,
		'link'         => Types\Link::class,
		'datetime'     => Types\Datetime::class,
		'date'         => Types\Date::class,
		'color'        => Types\Color::class,
		'relationship' => Types\Relationship::class,
		'repeater'     => Types\Repeater::class,
		'group'        => Types\Group::class,
	];

	/** @var array<string, Type> */
	private static array $instances = [];

	/** Returns the type handler for a type name (null when unknown). */
	public static function type( string $type ): ?Type {
		$type = strtolower( trim( $type ) );
		if ( ! isset( self::TYPES[ $type ] ) ) {
			return null;
		}
		if ( ! isset( self::$instances[ $type ] ) ) {
			$class                    = self::TYPES[ $type ];
			self::$instances[ $type ] = new $class();
		}
		return self::$instances[ $type ];
	}

	/** All known type names. */
	public static function types(): array {
		return array_keys( self::TYPES );
	}

	/**
	 * Normalizes a list of field definitions: drops invalid entries, fills the
	 * shared keys and normalizes nested fields recursively. The result is a
	 * list (numeric keys) in declaration order with unique keys.
	 */
	public static function normalize_list( array $fields ): array {
		$out  = [];
		$seen = [];
		foreach ( $fields as $maybe_key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( ! isset( $field['key'] ) && is_string( $maybe_key ) ) {
				$field['key'] = $maybe_key;
			}
			$field = self::normalize( $field );
			if ( null === $field || isset( $seen[ $field['key'] ] ) ) {
				continue;
			}
			$seen[ $field['key'] ] = true;
			$out[]                 = $field;
		}
		return $out;
	}

	/** Normalizes a single field definition (null when invalid). */
	public static function normalize( array $field ): ?array {
		if ( ! empty( $field['__normalized'] ) ) {
			return $field;
		}
		$key = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
		if ( '' === $key || '__present' === $key ) {
			return null;
		}
		$type_name = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$type      = self::type( $type_name );
		if ( null === $type ) {
			return null;
		}
		$field['key']         = $key;
		$field['type']        = $type->name();
		$field['label']       = isset( $field['label'] ) ? (string) $field['label'] : ucwords( str_replace( '_', ' ', $key ) );
		$field['help']        = isset( $field['help'] ) ? (string) $field['help'] : '';
		$field['required']    = ! empty( $field['required'] );
		$field['placeholder'] = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
		$field['private']     = ! empty( $field['private'] ); // Not exposed in REST `view` context, not rendered by the theme.

		if ( in_array( $field['type'], [ 'repeater', 'group' ], true ) ) {
			$field['fields'] = self::normalize_list( isset( $field['fields'] ) && is_array( $field['fields'] ) ? $field['fields'] : [] );
		}
		if ( 'select' === $field['type'] ) {
			$field['multiple'] = ! empty( $field['multiple'] );
			if ( ! isset( $field['options'] ) ) {
				$field['options'] = [];
			}
		}
		if ( 'relationship' === $field['type'] ) {
			$field['multiple']  = ! empty( $field['multiple'] );
			$field['orderable'] = ! empty( $field['orderable'] );
			$post_type          = $field['post_type'] ?? [ 'post' ];
			$field['post_type'] = array_values( array_filter( array_map( 'strval', (array) $post_type ) ) );
		}

		$field['__normalized'] = true;
		// Canonical default (sanitized) so definitions are always schema-valid.
		$field['default'] = $type->default( $field );
		return $field;
	}

	/** Canonical default of a (normalized) field. */
	public static function default_for( array $field ): mixed {
		$field = self::normalize( $field );
		if ( null === $field ) {
			return null;
		}
		return $field['default'];
	}

	/**
	 * Resolves select options (value => label). Accepts arrays, lists and callables.
	 *
	 * @return array<string,string>
	 */
	public static function options( array $field ): array {
		$options = $field['options'] ?? [];
		if ( ! empty( $field['options_callback'] ) && is_callable( $field['options_callback'] ) ) {
			$options = call_user_func( $field['options_callback'], $field );
		}
		if ( ! is_array( $options ) ) {
			return [];
		}
		$out     = [];
		$is_list = array_keys( $options ) === range( 0, count( $options ) - 1 );
		foreach ( $options as $value => $label ) {
			if ( $is_list && ! is_array( $label ) ) {
				$value = $label;
			}
			$out[ (string) $value ] = is_scalar( $label ) ? (string) $label : (string) $value;
		}
		return $out;
	}

	/** Whether the select options are static (usable as a schema enum). */
	public static function has_static_options( array $field ): bool {
		return empty( $field['options_callback'] ) && isset( $field['options'] ) && is_array( $field['options'] );
	}
}
