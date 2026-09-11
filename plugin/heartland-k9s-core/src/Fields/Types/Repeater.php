<?php
/**
 * Repeater field: ordered list of rows (associative arrays of sub-fields).
 *
 * Options: 'fields', 'min', 'max', 'item_label' (sub-field key used as the
 * row title), 'collapsible' (default true), 'add_label', 'empty_text'.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;
use HK9\Core\Fields\Sanitizer;
use HK9\Core\Fields\Schema;

defined( 'ABSPATH' ) || exit;

class Repeater extends Type {

	public const INDEX_PLACEHOLDER = '__INDEX__';

	public function name(): string {
		return 'repeater';
	}

	public function rest_type( array $field ): string {
		return 'array';
	}

	public function wrapper(): string {
		return 'fieldset';
	}

	public function empty_value( array $field ): mixed {
		return [];
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value ) {
			$value = $field['default'] ?? [];
		}
		if ( ! is_array( $value ) ) {
			return [];
		}
		$fields = $field['fields'] ?? [];
		$max    = ! empty( $field['max'] ) ? (int) $field['max'] : 0;
		$rows   = [];
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rows[] = Sanitizer::sanitize( $fields, $row );
			if ( $max > 0 && count( $rows ) >= $max ) {
				break;
			}
		}
		return $rows;
	}

	public function schema( array $field ): array {
		$schema = [
			'type'  => 'array',
			'items' => Schema::for( $field['fields'] ?? [] ),
		];
		if ( ! empty( $field['max'] ) ) {
			$schema['maxItems'] = (int) $field['max'];
		}
		return $schema;
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$rows        = is_array( $value ) ? array_values( $value ) : [];
		$fields      = $field['fields'] ?? [];
		$min         = ! empty( $field['min'] ) ? (int) $field['min'] : 0;
		$max         = ! empty( $field['max'] ) ? (int) $field['max'] : 0;
		$item_label  = ! empty( $field['item_label'] ) ? (string) $field['item_label'] : '';
		$collapsible = false !== ( $field['collapsible'] ?? true );
		$add_label   = ! empty( $field['add_label'] ) ? (string) $field['add_label'] : __( 'Add item', 'heartland-k9s-core' );
		$empty_text  = ! empty( $field['empty_text'] ) ? (string) $field['empty_text'] : __( 'No items yet.', 'heartland-k9s-core' );

		$html = sprintf(
			'<div class="hk9-repeater" data-hk9-repeater data-hk9-min="%d" data-hk9-max="%d" data-hk9-item-label="%s" data-hk9-collapsible="%s" data-hk9-next-index="%d" data-hk9-name="%s" data-hk9-id="%s">',
			$min,
			$max,
			esc_attr( $item_label ),
			$collapsible ? '1' : '0',
			count( $rows ),
			esc_attr( $name ),
			esc_attr( $id )
		);
		$html .= '<ul class="hk9-repeater__rows" role="list" data-hk9-repeater-rows>';
		foreach ( $rows as $index => $row ) {
			$html .= $this->row_html( $field, $fields, $row, $name, $id, (string) $index, $renderer, $item_label, $collapsible );
		}
		$html .= '</ul>';
		$html .= '<p class="hk9-repeater__empty" data-hk9-repeater-empty' . ( empty( $rows ) ? '' : ' hidden' ) . '>' . esc_html( $empty_text ) . '</p>';
		$html .= sprintf(
			'<button type="button" class="button hk9-repeater__add" data-hk9-repeater-add%s>%s</button>',
			( $max > 0 && count( $rows ) >= $max ) ? ' disabled' : '',
			esc_html( $add_label )
		);
		$html .= '<template data-hk9-repeater-template>' . $this->row_html( $field, $fields, Sanitizer::sanitize( $fields, [] ), $name, $id, self::INDEX_PLACEHOLDER, $renderer, $item_label, $collapsible ) . '</template>';
		$html .= '</div>';
		return $html;
	}

	private function row_html( array $field, array $fields, array $row, string $name, string $id, string $index, Renderer $renderer, string $item_label, bool $collapsible ): string {
		$row_name = $name . '[' . $index . ']';
		$row_id   = $id . '__' . $index;
		$title    = '';
		if ( '' !== $item_label && isset( $row[ $item_label ] ) && is_scalar( $row[ $item_label ] ) ) {
			$title = (string) $row[ $item_label ];
		}
		$body_id = $row_id . '__body';

		$html  = sprintf( '<li class="hk9-repeater__row" data-hk9-repeater-row data-index="%s" draggable="true">', esc_attr( $index ) );
		$html .= '<div class="hk9-repeater__head">';
		$html .= '<span class="hk9-repeater__handle" aria-hidden="true" title="' . esc_attr__( 'Drag to reorder', 'heartland-k9s-core' ) . '">&#8942;&#8942;</span>';
		if ( $collapsible ) {
			$html .= sprintf(
				'<button type="button" class="hk9-repeater__toggle" data-hk9-repeater-toggle aria-expanded="true" aria-controls="%s"><span class="hk9-repeater__num" data-hk9-repeater-num></span> <span class="hk9-repeater__title" data-hk9-repeater-title>%s</span></button>',
				esc_attr( $body_id ),
				esc_html( $title )
			);
		} else {
			$html .= sprintf( '<span class="hk9-repeater__static"><span class="hk9-repeater__num" data-hk9-repeater-num></span> <span class="hk9-repeater__title" data-hk9-repeater-title>%s</span></span>', esc_html( $title ) );
		}
		$html .= '<span class="hk9-repeater__tools">';
		$html .= sprintf( '<button type="button" class="hk9-iconbtn" data-hk9-move="up" aria-label="%s">&#8593;</button>', esc_attr__( 'Move up', 'heartland-k9s-core' ) );
		$html .= sprintf( '<button type="button" class="hk9-iconbtn" data-hk9-move="down" aria-label="%s">&#8595;</button>', esc_attr__( 'Move down', 'heartland-k9s-core' ) );
		$html .= sprintf( '<button type="button" class="hk9-iconbtn hk9-iconbtn--danger" data-hk9-repeater-remove aria-label="%s">&times;</button>', esc_attr__( 'Remove item', 'heartland-k9s-core' ) );
		$html .= '</span></div>';
		$html .= sprintf( '<div class="hk9-repeater__body" id="%s" data-hk9-repeater-body>', esc_attr( $body_id ) );
		$html .= $renderer->render_fields( $fields, $row, $row_name, $row_id );
		$html .= '</div></li>';
		return $html;
	}

	public function format( array $field, mixed $value ): string {
		$fields = $field['fields'] ?? [];
		$lines  = [];
		foreach ( (array) $value as $i => $row ) {
			$lines[] = sprintf( '#%d', (int) $i + 1 );
			$lines[] = Renderer::format_fields( $fields, is_array( $row ) ? $row : [], '  ' );
		}
		return implode( "\n", $lines );
	}
}
