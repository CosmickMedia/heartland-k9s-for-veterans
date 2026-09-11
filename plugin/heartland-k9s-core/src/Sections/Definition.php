<?php
/**
 * One section definition (per template): id, label, fields, defaults, layout flags.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Sections;

use HK9\Core\Fields\Field;
use HK9\Core\Fields\Sanitizer;
use HK9\Core\Fields\Schema;

defined( 'ABSPATH' ) || exit;

final class Definition {

	public const META_PREFIX = 'hk9_sec_';

	public readonly string $template;
	public readonly string $id;
	public readonly string $label;
	public readonly string $description;
	/** @var array Normalized field list. */
	public readonly array $fields;
	public readonly bool $can_hide;
	public readonly bool $can_reorder;
	public readonly bool $hidden_default;
	/** Section type (e.g. hero_band, feature_cards) for the theme's template-part lookup. */
	public readonly string $type;
	private readonly string $meta_key;
	private array $defaults;
	private ?array $schema = null;

	/**
	 * @param string $template Template slug.
	 * @param array  $def      Raw section definition array.
	 */
	public function __construct( string $template, array $def ) {
		$this->template       = $template;
		$this->id             = sanitize_key( (string) ( $def['id'] ?? '' ) );
		$this->label          = (string) ( $def['label'] ?? ucwords( str_replace( '_', ' ', $this->id ) ) );
		$this->description    = (string) ( $def['description'] ?? '' );
		$this->type           = sanitize_key( (string) ( $def['type'] ?? $this->id ) );
		$this->fields         = Field::normalize_list( is_array( $def['fields'] ?? null ) ? $def['fields'] : [] );
		$this->can_hide       = (bool) ( $def['can_hide'] ?? true );
		$this->can_reorder    = (bool) ( $def['can_reorder'] ?? true );
		$this->hidden_default = (bool) ( $def['hidden_default'] ?? false );
		$this->meta_key       = ! empty( $def['meta_key'] ) ? sanitize_key( (string) $def['meta_key'] ) : self::META_PREFIX . $this->id;
		$this->defaults       = Sanitizer::sanitize( $this->fields, is_array( $def['defaults'] ?? null ) ? $def['defaults'] : [] );
	}

	/** Meta key storing this section (`hk9_sec_<id>` unless overridden). */
	public function meta_key(): string {
		return $this->meta_key;
	}

	/** Canonical defaults (schema-valid). */
	public function defaults(): array {
		return $this->defaults;
	}

	/** REST schema for the stored object (includes `default`). */
	public function schema(): array {
		if ( null === $this->schema ) {
			$this->schema            = Schema::for( $this->fields );
			$this->schema['default'] = $this->defaults;
		}
		return $this->schema;
	}

	/**
	 * Sanitizes arbitrary input into the canonical stored value. Keys missing
	 * from the input take the section's declared defaults (so a partial or
	 * empty value renders the reference copy), then every value is coerced.
	 */
	public function sanitize( mixed $input ): array {
		if ( $input instanceof \stdClass ) {
			$input = (array) $input;
		}
		if ( ! is_array( $input ) ) {
			$input = $this->defaults;
		} elseif ( empty( $input['__present'] ) ) {
			$input = array_merge( $this->defaults, $input );
		}
		// A submitted form (`__present`) posts every control: keys it omits are
		// emptied lists (repeaters, galleries, multi-selects), not defaults.
		return Sanitizer::sanitize( $this->fields, $input );
	}

	/** Keys of fields flagged private (not exposed in REST view context / not rendered). */
	public function private_keys(): array {
		$keys = [];
		foreach ( $this->fields as $field ) {
			if ( ! empty( $field['private'] ) ) {
				$keys[] = $field['key'];
			}
		}
		return $keys;
	}

	/** Stable signature of the field set (used to detect key conflicts across templates). */
	public function signature(): string {
		return md5( wp_json_encode( $this->strip_for_signature( $this->fields ) ) ?: '' );
	}

	private function strip_for_signature( array $fields ): array {
		$out = [];
		foreach ( $fields as $field ) {
			$entry = [
				'key'  => $field['key'],
				'type' => $field['type'],
			];
			foreach ( [ 'multiple', 'post_type', 'max', 'min', 'float', 'time_optional' ] as $opt ) {
				if ( isset( $field[ $opt ] ) ) {
					$entry[ $opt ] = $field[ $opt ];
				}
			}
			if ( 'select' === $field['type'] && Field::has_static_options( $field ) ) {
				$entry['options'] = array_keys( Field::options( $field ) );
			}
			if ( isset( $field['fields'] ) ) {
				$entry['fields'] = $this->strip_for_signature( $field['fields'] );
			}
			$out[] = $entry;
		}
		return $out;
	}

	/** Plain-array form for helpers/JS. */
	public function to_array(): array {
		return [
			'id'             => $this->id,
			'type'           => $this->type,
			'template'       => $this->template,
			'label'          => $this->label,
			'description'    => $this->description,
			'meta_key'       => $this->meta_key,
			'fields'         => $this->fields,
			'defaults'       => $this->defaults,
			'can_hide'       => $this->can_hide,
			'can_reorder'    => $this->can_reorder,
			'hidden_default' => $this->hidden_default,
		];
	}
}
