<?php
/**
 * JSON schema builder for field lists (REST meta schemas).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/**
	 * Object schema for a field list: type object, properties (each carrying
	 * a `default`), additionalProperties=false.
	 */
	public static function for( array $fields ): array {
		$properties = [];
		foreach ( Field::normalize_list( $fields ) as $field ) {
			$properties[ $field['key'] ] = self::property( $field );
		}
		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		];
	}

	/** Property schema for one field, including its canonical default. */
	public static function property( array $field ): array {
		$field = Field::normalize( $field );
		if ( null === $field ) {
			return [ 'type' => 'string' ];
		}
		$type   = Field::type( $field['type'] );
		$schema = $type->schema( $field );
		if ( '' !== $field['label'] ) {
			$schema['title'] = $field['label'];
		}
		if ( '' !== $field['help'] ) {
			$schema['description'] = $field['help'];
		}
		$schema['default'] = $field['default'];
		return $schema;
	}
}
