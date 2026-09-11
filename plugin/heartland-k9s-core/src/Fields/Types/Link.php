<?php
/**
 * Link field: object {label, url, post_id, target, rel}. When post_id > 0 the
 * frontend uses get_permalink(post_id) and ignores url.
 *
 * Options: 'allow_target' (bool, default true), 'allow_internal' (bool,
 * default true), 'post_types' (string[] searchable via hk9/v1/pick).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields\Types;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

class Link extends Type {

	public const DEFAULT_POST_TYPES = [ 'page', 'post', 'hk9_story', 'hk9_team', 'hk9_campaign', 'hk9_event', 'hk9_barkode' ];

	public function name(): string {
		return 'link';
	}

	public function rest_type( array $field ): string {
		return 'object';
	}

	public function empty_value( array $field ): mixed {
		return [
			'label'   => '',
			'url'     => '',
			'post_id' => 0,
			'target'  => '_self',
			'rel'     => '',
		];
	}

	/** Post types an internal link may point at. */
	public static function post_types( array $field ): array {
		$types = ! empty( $field['post_types'] ) ? (array) $field['post_types'] : self::DEFAULT_POST_TYPES;
		return array_values( array_filter( array_map( 'strval', $types ) ) );
	}

	/** Validates an internal target id. */
	public static function valid_post_id( mixed $value, array $field ): int {
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return 0;
		}
		$id = absint( $value );
		if ( $id <= 0 ) {
			return 0;
		}
		$post = get_post( $id );
		if ( ! $post || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
			return 0;
		}
		if ( ! in_array( $post->post_type, self::post_types( $field ), true ) ) {
			return 0;
		}
		return $id;
	}

	public function sanitize( array $field, mixed $value ): mixed {
		$empty = $this->empty_value( $field );
		if ( null === $value ) {
			$value = $field['default'] ?? $empty;
		}
		if ( is_string( $value ) ) {
			$value = [ 'url' => $value ];
		}
		if ( ! is_array( $value ) ) {
			return $empty;
		}
		$out          = $empty;
		$out['label'] = isset( $value['label'] ) && is_scalar( $value['label'] ) ? sanitize_text_field( (string) $value['label'] ) : '';
		$url          = isset( $value['url'] ) && is_scalar( $value['url'] ) ? trim( (string) $value['url'] ) : '';
		$out['url']   = '' === $url ? '' : (string) esc_url_raw( $url );
		if ( false === ( $field['allow_internal'] ?? true ) ) {
			$out['post_id'] = 0;
		} else {
			$out['post_id'] = self::valid_post_id( $value['post_id'] ?? 0, $field );
		}
		$target = isset( $value['target'] ) && is_scalar( $value['target'] ) ? (string) $value['target'] : '_self';
		if ( is_bool( $value['target'] ?? null ) ) {
			$target = $value['target'] ? '_blank' : '_self';
		}
		$out['target'] = ( '_blank' === $target && false !== ( $field['allow_target'] ?? true ) ) ? '_blank' : '_self';
		$rel           = isset( $value['rel'] ) && is_scalar( $value['rel'] ) ? strtolower( sanitize_text_field( (string) $value['rel'] ) ) : '';
		$rel           = implode( ' ', array_values( array_unique( array_filter( preg_split( '/\s+/', $rel ) ?: [], static fn( $t ) => (bool) preg_match( '/^[a-z-]+$/', $t ) ) ) ) );
		$out['rel']    = $rel;
		return $out;
	}

	public function schema( array $field ): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'label'   => [
					'type'    => 'string',
					'default' => '',
				],
				'url'     => [
					'type'    => 'string',
					'default' => '',
				],
				'post_id' => [
					'type'    => 'integer',
					'minimum' => 0,
					'default' => 0,
				],
				'target'  => [
					'type'    => 'string',
					'enum'    => [ '_self', '_blank' ],
					'default' => '_self',
				],
				'rel'     => [
					'type'    => 'string',
					'default' => '',
				],
			],
			'additionalProperties' => false,
		];
	}

	public function render( array $field, mixed $value, string $name, string $id, Renderer $renderer ): string {
		$value          = is_array( $value ) ? array_merge( $this->empty_value( $field ), $value ) : $this->empty_value( $field );
		$allow_internal = false !== ( $field['allow_internal'] ?? true );
		$allow_target   = false !== ( $field['allow_target'] ?? true );
		$post_id        = (int) $value['post_id'];
		$post_title     = $post_id > 0 ? get_the_title( $post_id ) : '';
		$mode           = $post_id > 0 ? 'internal' : 'external';
		$types          = implode( ',', self::post_types( $field ) );

		$html = sprintf(
			'<div class="hk9-link" data-hk9-link data-hk9-mode="%s" data-hk9-post-types="%s">',
			esc_attr( $mode ),
			esc_attr( $types )
		);

		// Label.
		$html .= '<div class="hk9-link__row">';
		$html .= sprintf( '<label class="hk9-link__sublabel" for="%s">%s</label>', esc_attr( $id ), esc_html__( 'Label', 'heartland-k9s-core' ) );
		$html .= sprintf(
			'<input type="text" class="regular-text hk9-input" id="%s" name="%s" value="%s" data-hk9-link-label />',
			esc_attr( $id ),
			esc_attr( $name . '[label]' ),
			esc_attr( (string) $value['label'] )
		);
		$html .= '</div>';

		// Mode tabs.
		if ( $allow_internal ) {
			$html .= '<div class="hk9-link__tabs" role="tablist" aria-label="' . esc_attr__( 'Link destination', 'heartland-k9s-core' ) . '">';
			$html .= sprintf(
				'<button type="button" role="tab" class="hk9-link__tab" data-hk9-link-tab="internal" aria-selected="%s" id="%s-tab-internal" aria-controls="%s-panel-internal">%s</button>',
				'internal' === $mode ? 'true' : 'false',
				esc_attr( $id ),
				esc_attr( $id ),
				esc_html__( 'Page / record', 'heartland-k9s-core' )
			);
			$html .= sprintf(
				'<button type="button" role="tab" class="hk9-link__tab" data-hk9-link-tab="external" aria-selected="%s" id="%s-tab-external" aria-controls="%s-panel-external">%s</button>',
				'external' === $mode ? 'true' : 'false',
				esc_attr( $id ),
				esc_attr( $id ),
				esc_html__( 'External URL', 'heartland-k9s-core' )
			);
			$html .= '</div>';

			// Internal panel.
			$html .= sprintf(
				'<div class="hk9-link__panel" role="tabpanel" id="%s-panel-internal" aria-labelledby="%s-tab-internal" data-hk9-link-panel="internal"%s>',
				esc_attr( $id ),
				esc_attr( $id ),
				'internal' === $mode ? '' : ' hidden'
			);
			$html .= sprintf( '<input type="hidden" name="%s" value="%d" data-hk9-link-post />', esc_attr( $name . '[post_id]' ), $post_id );
			$html .= '<div class="hk9-link__chosen" data-hk9-link-chosen' . ( $post_id > 0 ? '' : ' hidden' ) . '>';
			$html .= '<span class="hk9-chip"><span class="hk9-chip__label" data-hk9-link-chosen-label>' . esc_html( $post_title ) . '</span>';
			$html .= sprintf( '<button type="button" class="hk9-chip__remove" data-hk9-link-clear aria-label="%s">&times;</button></span>', esc_attr__( 'Clear selection', 'heartland-k9s-core' ) );
			$html .= '</div>';
			$html .= sprintf(
				'<label class="screen-reader-text" for="%1$s-search">%2$s</label><input type="search" class="regular-text hk9-input hk9-link__search" id="%1$s-search" placeholder="%3$s" autocomplete="off" data-hk9-link-search aria-controls="%1$s-results" aria-expanded="false" />',
				esc_attr( $id ),
				esc_html__( 'Search pages and records', 'heartland-k9s-core' ),
				esc_attr__( 'Search pages and records…', 'heartland-k9s-core' )
			);
			$html .= sprintf( '<ul class="hk9-pick__results" id="%s-results" role="listbox" data-hk9-link-results hidden></ul>', esc_attr( $id ) );
			$html .= '</div>';
		} else {
			$html .= sprintf( '<input type="hidden" name="%s" value="0" data-hk9-link-post />', esc_attr( $name . '[post_id]' ) );
		}

		// External panel.
		$html .= sprintf(
			'<div class="hk9-link__panel" role="tabpanel" id="%s-panel-external"%s data-hk9-link-panel="external"%s>',
			esc_attr( $id ),
			$allow_internal ? ' aria-labelledby="' . esc_attr( $id ) . '-tab-external"' : '',
			( 'external' === $mode || ! $allow_internal ) ? '' : ' hidden'
		);
		$html .= sprintf( '<label class="hk9-link__sublabel" for="%s-url">%s</label>', esc_attr( $id ), esc_html__( 'URL', 'heartland-k9s-core' ) );
		$html .= sprintf(
			'<input type="text" class="regular-text hk9-input" id="%s-url" name="%s" value="%s" placeholder="https://" inputmode="url" data-hk9-link-url />',
			esc_attr( $id ),
			esc_attr( $name . '[url]' ),
			esc_attr( (string) $value['url'] )
		);
		$html .= '</div>';

		// Target + rel.
		$html .= '<div class="hk9-link__row hk9-link__row--options">';
		if ( $allow_target ) {
			$html .= sprintf( '<input type="hidden" name="%s" value="_self" />', esc_attr( $name . '[target]' ) );
			$html .= sprintf(
				'<label class="hk9-toggle"><input type="checkbox" class="hk9-toggle__input" name="%s" value="_blank"%s data-hk9-link-target /> <span>%s</span></label>',
				esc_attr( $name . '[target]' ),
				'_blank' === $value['target'] ? ' checked' : '',
				esc_html__( 'Open in a new tab', 'heartland-k9s-core' )
			);
		} else {
			$html .= sprintf( '<input type="hidden" name="%s" value="_self" data-hk9-link-target-fixed />', esc_attr( $name . '[target]' ) );
		}
		$html .= sprintf( '<input type="hidden" name="%s" value="%s" data-hk9-link-rel />', esc_attr( $name . '[rel]' ), esc_attr( (string) $value['rel'] ) );
		$html .= '</div>';

		$html .= '</div>';
		return $html;
	}

	public function format( array $field, mixed $value ): string {
		if ( ! is_array( $value ) ) {
			return '';
		}
		$label  = (string) ( $value['label'] ?? '' );
		$target = (int) ( $value['post_id'] ?? 0 ) > 0 ? sprintf( '#%d %s', (int) $value['post_id'], get_the_title( (int) $value['post_id'] ) ) : (string) ( $value['url'] ?? '' );
		$parts  = array_filter( [ $label, $target ] );
		if ( '_blank' === ( $value['target'] ?? '' ) ) {
			$parts[] = __( '(new tab)', 'heartland-k9s-core' );
		}
		return implode( ' → ', $parts );
	}
}
