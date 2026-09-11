<?php
/**
 * `hk9_sections_layout` meta: {order: string[], hidden: string[]} per page,
 * shared by every template. Resolution against a template's definitions
 * yields the ordered list of visible section ids.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Sections;

defined( 'ABSPATH' ) || exit;

final class Layout {

	public const META_KEY = 'hk9_sections_layout';

	/** Canonical empty value. */
	public static function empty_value(): array {
		return [
			'order'  => [],
			'hidden' => [],
		];
	}

	/** REST schema. */
	public static function schema(): array {
		$ids = [
			'type'  => 'array',
			'items' => [ 'type' => 'string' ],
		];
		return [
			'type'                 => 'object',
			'properties'           => [
				'order'  => $ids + [ 'default' => [] ],
				'hidden' => $ids + [ 'default' => [] ],
			],
			'additionalProperties' => false,
			'default'              => self::empty_value(),
		];
	}

	/**
	 * Sanitizes any input into the canonical {order, hidden} value.
	 *
	 * Accepts the REST/JS shape {order, hidden} and the form shape
	 * {order[], shown[]} (unchecked "shown" boxes = hidden). Ids are validated
	 * against every registered section id.
	 */
	public static function sanitize( mixed $input ): array {
		if ( $input instanceof \stdClass ) {
			$input = (array) $input;
		}
		if ( ! is_array( $input ) ) {
			return self::empty_value();
		}
		$known = Registry::all_section_ids();
		$clean = static function ( mixed $list ) use ( $known ): array {
			if ( is_string( $list ) ) {
				$list = '' === $list ? [] : explode( ',', $list );
			}
			if ( ! is_array( $list ) ) {
				return [];
			}
			$out = [];
			foreach ( $list as $id ) {
				if ( ! is_scalar( $id ) ) {
					continue;
				}
				$id = sanitize_key( (string) $id );
				if ( '' === $id || ! in_array( $id, $known, true ) || in_array( $id, $out, true ) ) {
					continue;
				}
				$out[] = $id;
			}
			return $out;
		};

		$order = $clean( $input['order'] ?? [] );
		if ( array_key_exists( 'shown', $input ) && ! array_key_exists( 'hidden', $input ) ) {
			$shown  = $clean( $input['shown'] );
			$hidden = array_values( array_diff( $order, $shown ) );
		} else {
			$hidden = $clean( $input['hidden'] ?? [] );
		}
		return [
			'order'  => $order,
			'hidden' => $hidden,
		];
	}

	/**
	 * The reference layout of a template: every section id in declaration
	 * order, with the hide-by-default sections hidden. Storing it changes
	 * nothing compared with an absent key, so writes equal to it are skipped.
	 *
	 * @param Definition[] $sections Template definitions in reference order.
	 */
	public static function reference( array $sections ): array {
		$order  = [];
		$hidden = [];
		foreach ( $sections as $def ) {
			$order[] = $def->id;
			if ( $def->can_hide && $def->hidden_default ) {
				$hidden[] = $def->id;
			}
		}
		return [
			'order'  => $order,
			'hidden' => $hidden,
		];
	}

	/**
	 * Resolves the visible, ordered section ids for a template given a stored
	 * layout. Sections missing from the stored order are appended in the
	 * template's default order; unknown ids are ignored.
	 *
	 * @param array        $layout   Sanitized {order, hidden}.
	 * @param Definition[] $sections Template definitions in reference order.
	 * @return string[] Visible section ids.
	 */
	public static function resolve( array $layout, array $sections ): array {
		$by_id = [];
		foreach ( $sections as $def ) {
			$by_id[ $def->id ] = $def;
		}
		return array_values(
			array_filter(
				self::ordered_ids( $layout, $sections ),
				static function ( string $id ) use ( $layout, $by_id ): bool {
					$def = $by_id[ $id ];
					if ( ! $def->can_hide ) {
						return true;
					}
					$seen = in_array( $id, $layout['order'] ?? [], true );
					if ( $seen ) {
						return ! in_array( $id, $layout['hidden'] ?? [], true );
					}
					return ! $def->hidden_default;
				}
			)
		);
	}

	/**
	 * All template section ids in effective order (visible or not).
	 *
	 * @param Definition[] $sections
	 * @return string[]
	 */
	public static function ordered_ids( array $layout, array $sections ): array {
		$ids     = array_map( static fn( Definition $d ): string => $d->id, $sections );
		$fixed   = [];
		$movable = [];
		foreach ( $sections as $def ) {
			if ( $def->can_reorder ) {
				$movable[] = $def->id;
			} else {
				$fixed[] = $def->id;
			}
		}
		$stored = array_values( array_filter( (array) ( $layout['order'] ?? [] ), static fn( $id ) => in_array( $id, $movable, true ) ) );
		foreach ( $movable as $id ) {
			if ( ! in_array( $id, $stored, true ) ) {
				$stored[] = $id;
			}
		}
		// Non-reorderable sections keep their reference position.
		$result = [];
		$cursor = 0;
		foreach ( $ids as $id ) {
			if ( in_array( $id, $fixed, true ) ) {
				$result[] = $id;
				continue;
			}
			if ( isset( $stored[ $cursor ] ) ) {
				$result[] = $stored[ $cursor ];
				++$cursor;
			}
		}
		while ( isset( $stored[ $cursor ] ) ) {
			$result[] = $stored[ $cursor ];
			++$cursor;
		}
		return $result;
	}

	/** Whether a section is visible under a layout (helper for the panel UI). */
	public static function is_visible( array $layout, Definition $def ): bool {
		if ( ! $def->can_hide ) {
			return true;
		}
		if ( in_array( $def->id, $layout['order'] ?? [], true ) ) {
			return ! in_array( $def->id, $layout['hidden'] ?? [], true );
		}
		return ! $def->hidden_default;
	}
}
