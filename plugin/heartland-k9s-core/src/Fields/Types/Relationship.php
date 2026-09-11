<?php
/**
 * Relationship field: post id (or ordered id list when multiple), validated
 * to exist and to be of the configured post type(s).
 *
 * Options: 'post_type' (string|string[]), 'multiple', 'max', 'orderable'.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Relationship extends Type {

	public function name(): string {
		return 'relationship';
	}

	public function rest_type( array $field ): string {
		return ! empty( $field['multiple'] ) ? 'array' : 'integer';
	}

	public function empty_value( array $field ): mixed {
		return ! empty( $field['multiple'] ) ? [] : 0;
	}

	/** Validates one id against the field's post types. */
	public static function valid_id( mixed $value, array $field ): int {
		if ( is_array( $value ) ) {
			$value = $value['id'] ?? 0;
		}
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return 0;
		}
		$id = absint( $value );
		if ( $id <= 0 ) {
			return 0;
		}
		$post = get_post( $id );
		if ( ! $post || 'trash' === $post->post_status || 'auto-draft' === $post->post_status ) {
			return 0;
		}
		$types = ! empty( $field['post_type'] ) ? (array) $field['post_type'] : [];
		if ( ! empty( $types ) && ! in_array( $post->post_type, $types, true ) ) {
			return 0;
		}
		return $id;
	}

	public function sanitize( array $field, mixed $value ): mixed {
		$multiple = ! empty( $field['multiple'] );
		if ( null === $value ) {
			$value = $field['default'] ?? $this->empty_value( $field );
		}
		if ( ! $multiple ) {
			if ( is_array( $value ) && isset( $value[0] ) && ! isset( $value['id'] ) ) {
				$value = $value[0];
			}
			return self::valid_id( $value, $field );
		}
		if ( is_scalar( $value ) ) {
			$value = '' === (string) $value ? [] : explode( ',', (string) $value );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}
		$out = [];
		$max = ! empty( $field['max'] ) ? (int) $field['max'] : 0;
		foreach ( $value as $item ) {
			$id = self::valid_id( $item, $field );
			if ( $id <= 0 || in_array( $id, $out, true ) ) {
				continue;
			}
			$out[] = $id;
			if ( $max > 0 && count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	public function schema( array $field ): array {
		if ( ! empty( $field['multiple'] ) ) {
			$schema = [
				'type'  => 'array',
				'items' => [
					'type'    => 'integer',
					'minimum' => 1,
				],
			];
			if ( ! empty( $field['max'] ) ) {
				$schema['maxItems'] = (int) $field['max'];
			}
			return $schema;
		}
		return [
			'type'    => 'integer',
			'minimum' => 0,
		];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$multiple = ! empty( $field['multiple'] );
		$ids      = $multiple ? array_map( 'intval', (array) $value ) : ( (int) $value > 0 ? [ (int) $value ] : [] );
		$types    = implode( ',', (array) $field['post_type'] );
		$max      = $multiple ? ( ! empty( $field['max'] ) ? (int) $field['max'] : 0 ) : 1;
		$input    = $multiple ? $name . '[]' : $name;

		$html = sprintf(
			'<div class="hk9-relationship%s" data-hk9-relationship data-hk9-post-types="%s" data-hk9-max="%d" data-hk9-multiple="%s" data-hk9-orderable="%s" data-hk9-name="%s">',
			$multiple ? ' is-multiple' : ' is-single',
			esc_attr( $types ),
			$max,
			$multiple ? '1' : '0',
			! empty( $field['orderable'] ) ? '1' : '0',
			esc_attr( $input )
		);
		// Single relationships need a value even when empty (0).
		if ( ! $multiple ) {
			$html .= sprintf( '<input type="hidden" name="%s" value="%d" data-hk9-relationship-single />', esc_attr( $name ), $ids[0] ?? 0 );
		}
		$html .= '<ul class="hk9-chips" role="list" data-hk9-chips>';
		foreach ( $ids as $post_id ) {
			$html .= self::chip_html( $multiple ? $input : '', $post_id, ! empty( $field['orderable'] ) );
		}
		$html .= '</ul>';
		$html .= sprintf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label><input type="search" class="regular-text hk9-input hk9-pick__search" id="%1$s" placeholder="%3$s" autocomplete="off" data-hk9-pick-search aria-controls="%1$s-results" aria-expanded="false" aria-describedby="%1$s-help" />',
			esc_attr( $id ),
			esc_html( sprintf( /* translators: %s: field label */ __( 'Search %s', 'heartland-k9s-core' ), $field['label'] ) ),
			esc_attr__( 'Type to search…', 'heartland-k9s-core' )
		);
		$html .= sprintf( '<ul class="hk9-pick__results" id="%s-results" role="listbox" data-hk9-pick-results hidden></ul>', esc_attr( $id ) );
		$html .= '</div>';
		return $html;
	}

	/** Markup for one selected chip. Empty $input_name = no hidden input (single mode keeps its own). */
	public static function chip_html( string $input_name, int $post_id, bool $orderable ): string {
		$title = get_the_title( $post_id );
		$type  = get_post_type_object( (string) get_post_type( $post_id ) );
		$badge = $type ? $type->labels->singular_name : '';
		$html  = sprintf( '<li class="hk9-chip" data-hk9-chip data-id="%d" draggable="%s">', $post_id, $orderable ? 'true' : 'false' );
		if ( '' !== $input_name ) {
			$html .= sprintf( '<input type="hidden" name="%s" value="%d" />', esc_attr( $input_name ), $post_id );
		}
		$html .= '<span class="hk9-chip__label">' . esc_html( $title ) . '</span>';
		if ( $badge ) {
			$html .= '<span class="hk9-chip__badge">' . esc_html( $badge ) . '</span>';
		}
		if ( $orderable ) {
			$html .= sprintf( '<button type="button" class="hk9-iconbtn" data-hk9-move="up" aria-label="%s">&#8593;</button>', esc_attr( sprintf( /* translators: %s: title */ __( 'Move %s earlier', 'heartland-k9s-core' ), $title ) ) );
			$html .= sprintf( '<button type="button" class="hk9-iconbtn" data-hk9-move="down" aria-label="%s">&#8595;</button>', esc_attr( sprintf( /* translators: %s: title */ __( 'Move %s later', 'heartland-k9s-core' ), $title ) ) );
		}
		$html .= sprintf( '<button type="button" class="hk9-chip__remove" data-hk9-chip-remove aria-label="%s">&times;</button>', esc_attr( sprintf( /* translators: %s: title */ __( 'Remove %s', 'heartland-k9s-core' ), $title ) ) );
		$html .= '</li>';
		return $html;
	}

	public function format( array $field, mixed $value ): string {
		$labels = [];
		foreach ( (array) $value as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$labels[] = sprintf( '#%d %s', $id, get_the_title( $id ) );
			}
		}
		return implode( ', ', $labels );
	}
}
