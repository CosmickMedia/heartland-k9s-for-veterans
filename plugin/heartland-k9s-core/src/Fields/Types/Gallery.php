<?php
/**
 * Gallery field: ordered list of image attachment ids.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Gallery extends Type {

	public function name(): string {
		return 'gallery';
	}

	public function rest_type( array $field ): string {
		return 'array';
	}

	public function empty_value( array $field ): mixed {
		return [];
	}

	public function sanitize( array $field, mixed $value ): mixed {
		if ( null === $value ) {
			$value = $field['default'] ?? [];
		}
		if ( is_string( $value ) ) {
			$value = '' === trim( $value ) ? [] : explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}
		$out = [];
		$max = ! empty( $field['max'] ) ? (int) $field['max'] : 0;
		foreach ( $value as $item ) {
			$id = Image::attachment_id( $item, true );
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

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$ids  = array_map( 'intval', (array) $value );
		$max  = ! empty( $field['max'] ) ? (int) $field['max'] : 0;
		$html = sprintf(
			'<div class="hk9-gallery" data-hk9-gallery data-hk9-name="%s" data-hk9-max="%d" id="%s">',
			esc_attr( $name . '[]' ),
			$max,
			esc_attr( $id )
		);
		$html .= '<ul class="hk9-gallery__list" role="list" data-hk9-gallery-list>';
		foreach ( $ids as $img_id ) {
			$html .= self::item_html( $name . '[]', $img_id );
		}
		$html .= '</ul>';
		$html .= '<p class="hk9-gallery__empty" data-hk9-gallery-empty' . ( empty( $ids ) ? '' : ' hidden' ) . '>' . esc_html__( 'No images selected.', 'heartland-k9s-core' ) . '</p>';
		$html .= '<div class="hk9-media__actions">';
		$html .= sprintf( '<button type="button" class="button" data-hk9-gallery-add%s>%s</button>', $this->describedby( $field, $id ), esc_html__( 'Add images', 'heartland-k9s-core' ) );
		$html .= sprintf( '<button type="button" class="button-link hk9-media__remove" data-hk9-gallery-clear>%s</button>', esc_html__( 'Remove all', 'heartland-k9s-core' ) );
		$html .= '</div></div>';
		return $html;
	}

	/** Markup for one gallery item (also used by JS via a data template). */
	public static function item_html( string $input_name, int $id ): string {
		$thumb = wp_get_attachment_image( $id, 'thumbnail', false, [ 'class' => 'hk9-gallery__img' ] );
		$title = get_the_title( $id );
		return sprintf(
			'<li class="hk9-gallery__item" draggable="true" data-hk9-gallery-item data-id="%1$d">' .
			'<input type="hidden" name="%2$s" value="%1$d" />' .
			'<span class="hk9-gallery__thumb">%3$s</span>' .
			'<span class="hk9-gallery__tools">' .
			'<button type="button" class="hk9-iconbtn" data-hk9-move="up" aria-label="%4$s">&#8593;</button>' .
			'<button type="button" class="hk9-iconbtn" data-hk9-move="down" aria-label="%5$s">&#8595;</button>' .
			'<button type="button" class="hk9-iconbtn hk9-iconbtn--danger" data-hk9-gallery-remove aria-label="%6$s">&times;</button>' .
			'</span></li>',
			$id,
			esc_attr( $input_name ),
			$thumb,
			esc_attr( sprintf( /* translators: %s: image title */ __( 'Move %s earlier', 'heartland-k9s-core' ), $title ) ),
			esc_attr( sprintf( /* translators: %s: image title */ __( 'Move %s later', 'heartland-k9s-core' ), $title ) ),
			esc_attr( sprintf( /* translators: %s: image title */ __( 'Remove %s', 'heartland-k9s-core' ), $title ) )
		);
	}

	public function format( array $field, mixed $value ): string {
		$labels = [];
		foreach ( (array) $value as $id ) {
			$labels[] = $this->attachment_label( (int) $id );
		}
		return implode( ', ', $labels );
	}
}
