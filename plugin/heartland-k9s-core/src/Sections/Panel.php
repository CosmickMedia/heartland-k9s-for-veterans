<?php
/**
 * HTML for the section content panel and the "Page sections" layout panel.
 * Used by the meta boxes and by the ajax template-switch endpoint.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Sections;

use HK9\Core\Fields\Renderer;

defined( 'ABSPATH' ) || exit;

final class Panel {

	/**
	 * Renders every section form of a template.
	 *
	 * @param \WP_Post $post     Post being edited.
	 * @param string   $template Template slug.
	 * @param array    $values   Optional overrides keyed by meta key (unsaved editor values).
	 */
	public static function render_sections( \WP_Post $post, string $template, array $values = [] ): string {
		$defs   = Registry::definitions( $template );
		$layout = isset( $values[ Layout::META_KEY ] ) && is_array( $values[ Layout::META_KEY ] ) ? Layout::sanitize( $values[ Layout::META_KEY ] ) : Accessor::layout_raw( $post->ID );
		$html   = sprintf(
			'<div class="hk9-sections" data-hk9-sections data-hk9-template="%s" data-hk9-post="%d">',
			esc_attr( $template ),
			$post->ID
		);

		if ( empty( $defs ) ) {
			$html .= '<p class="hk9-sections__intro">' . esc_html__( 'This template has no editable sections. Page content comes from the block editor.', 'heartland-k9s-core' ) . '</p></div>';
			return $html;
		}

		$html .= '<p class="hk9-sections__intro">' . sprintf(
			/* translators: %s: template label */
			esc_html__( 'Template: %s. Sections appear in the order set in the "Page sections" panel; hidden sections keep their content.', 'heartland-k9s-core' ),
			'<strong>' . esc_html( Registry::template_label( $template ) ) . '</strong>'
		) . '</p>';

		$renderer = new Renderer();
		$ordered  = Layout::ordered_ids( $layout, $defs );
		$by_id    = [];
		foreach ( $defs as $def ) {
			$by_id[ $def->id ] = $def;
		}
		foreach ( $ordered as $id ) {
			$def = $by_id[ $id ] ?? null;
			if ( ! $def ) {
				continue;
			}
			$key = $def->meta_key();
			if ( isset( $values[ $key ] ) && is_array( $values[ $key ] ) ) {
				$data = $def->sanitize( $values[ $key ] );
			} else {
				$raw  = get_post_meta( $post->ID, $key, true );
				$data = $def->sanitize( is_array( $raw ) ? $raw : [] );
			}
			$visible = Layout::is_visible( $layout, $def );

			$html .= sprintf(
				'<div class="hk9-section%s" id="hk9-section-%s" data-hk9-section="%s" data-hk9-mirror="object" data-hk9-meta-key="%s">',
				$visible ? '' : ' is-section-hidden',
				esc_attr( $id ),
				esc_attr( $id ),
				esc_attr( $key )
			);
			$html .= '<details class="hk9-section__details"' . ( $visible ? ' open' : '' ) . '>';
			$html .= '<summary class="hk9-section__summary"><span class="hk9-section__label">' . esc_html( $def->label ) . '</span>';
			$html .= '<span class="hk9-section__badge" data-hk9-section-status' . ( $visible ? ' hidden' : '' ) . '>' . esc_html__( 'Hidden', 'heartland-k9s-core' ) . '</span>';
			$html .= '<code class="hk9-section__key">' . esc_html( $id ) . '</code></summary>';
			$html .= '<div class="hk9-section__body">';
			if ( '' !== $def->description ) {
				$html .= '<p class="hk9-section__desc">' . esc_html( $def->description ) . '</p>';
			}
			$html .= sprintf( '<input type="hidden" name="%s[__present]" value="1" />', esc_attr( $key ) );
			$html .= $renderer->render_fields( $def->fields, $data, $key, $key );
			$html .= '</div></details></div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Renders the "Page sections" layout panel (order + visibility).
	 */
	public static function render_layout( \WP_Post $post, string $template, ?array $layout = null ): string {
		$defs   = Registry::definitions( $template );
		$layout = null !== $layout ? Layout::sanitize( $layout ) : Accessor::layout_raw( $post->ID );
		$html   = sprintf( '<div class="hk9-layout" data-hk9-layout data-hk9-mirror="layout" data-hk9-meta-key="%s" data-hk9-template="%s">', esc_attr( Layout::META_KEY ), esc_attr( $template ) );
		$html  .= sprintf( '<input type="hidden" name="%s[__present]" value="1" />', esc_attr( Layout::META_KEY ) );

		if ( empty( $defs ) ) {
			$html .= '<p class="description">' . esc_html__( 'This template has no sections to arrange.', 'heartland-k9s-core' ) . '</p></div>';
			return $html;
		}

		$html .= '<p class="description">' . esc_html__( 'Drag, or use the arrow buttons, to reorder. Untick a section to hide it without losing its content.', 'heartland-k9s-core' ) . '</p>';
		$html .= '<ul class="hk9-layout__list" role="list" data-hk9-layout-list>';
		$by_id = [];
		foreach ( $defs as $def ) {
			$by_id[ $def->id ] = $def;
		}
		foreach ( Layout::ordered_ids( $layout, $defs ) as $id ) {
			$def = $by_id[ $id ] ?? null;
			if ( ! $def ) {
				continue;
			}
			$visible  = Layout::is_visible( $layout, $def );
			$check_id = 'hk9-layout-' . $id;
			$html    .= sprintf(
				'<li class="hk9-layout__item%s" data-hk9-layout-item data-section="%s" data-can-hide="%s" data-can-reorder="%s" draggable="%s">',
				$def->can_reorder ? '' : ' is-fixed',
				esc_attr( $id ),
				$def->can_hide ? '1' : '0',
				$def->can_reorder ? '1' : '0',
				$def->can_reorder ? 'true' : 'false'
			);
			$html .= sprintf( '<input type="hidden" name="%s[order][]" value="%s" />', esc_attr( Layout::META_KEY ), esc_attr( $id ) );
			$html .= '<span class="hk9-layout__handle" aria-hidden="true">' . ( $def->can_reorder ? '&#8942;&#8942;' : '&nbsp;' ) . '</span>';
			if ( $def->can_hide ) {
				$html .= sprintf(
					'<input type="checkbox" class="hk9-layout__check" id="%s" name="%s[shown][]" value="%s"%s />',
					esc_attr( $check_id ),
					esc_attr( Layout::META_KEY ),
					esc_attr( $id ),
					$visible ? ' checked' : ''
				);
			} else {
				$html .= sprintf( '<input type="hidden" name="%s[shown][]" value="%s" />', esc_attr( Layout::META_KEY ), esc_attr( $id ) );
				$html .= sprintf( '<input type="checkbox" class="hk9-layout__check" id="%s" checked disabled title="%s" />', esc_attr( $check_id ), esc_attr__( 'Always shown', 'heartland-k9s-core' ) );
			}
			$html .= sprintf( '<label class="hk9-layout__label" for="%s">%s</label>', esc_attr( $check_id ), esc_html( $def->label ) );
			$html .= '<span class="hk9-layout__tools">';
			if ( $def->can_reorder ) {
				$html .= sprintf( '<button type="button" class="hk9-iconbtn" data-hk9-move="up" aria-label="%s">&#8593;</button>', esc_attr( sprintf( /* translators: %s: section label */ __( 'Move %s up', 'heartland-k9s-core' ), $def->label ) ) );
				$html .= sprintf( '<button type="button" class="hk9-iconbtn" data-hk9-move="down" aria-label="%s">&#8595;</button>', esc_attr( sprintf( /* translators: %s: section label */ __( 'Move %s down', 'heartland-k9s-core' ), $def->label ) ) );
			}
			$html .= '</span></li>';
		}
		$html .= '</ul></div>';
		return $html;
	}
}
