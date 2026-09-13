<?php
/**
 * Editor guidance for section-driven pages.
 *
 * - Block editor: a dismissible info notice (wp.data core/notices) telling
 *   editors that the page is built from the section panels under the editor
 *   and where the canvas content ends up (follows the template and the
 *   "Editor content" position live).
 * - Both editors: the "Sections" and "Page sections" boxes can never be hidden
 *   through Screen Options and stay first in their columns; the form sections'
 *   provider-specific fields (Gravity Forms form / shortcode) only show for
 *   the provider in effect.
 *
 * Booted from Admin\Notices (admin context only).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Admin;

use HK9\Core\Sections\Layout;
use HK9\Core\Sections\Panel;
use HK9\Core\Sections\Registry;
use HK9\Core\Support\FormProviders;

defined( 'ABSPATH' ) || exit;

final class EditorGuidance {

	private const BOXES = [ 'hk9_sections', 'hk9_sections_layout' ];

	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', [ self::class, 'block_editor_notice' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'panel_enhancements' ] );
		add_filter( 'hidden_meta_boxes', [ self::class, 'never_hidden' ], 10, 2 );
		add_filter( 'get_user_option_meta-box-order_page', [ self::class, 'keep_first' ] );
	}

	/** Whether the current screen is a page edit screen. */
	private static function is_page_editor(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen instanceof \WP_Screen && 'page' === $screen->post_type && 'post' === $screen->base;
	}

	/**
	 * Per-template guidance for the block editor notice.
	 *
	 * @return array<string, array{sections:string, native:bool}>
	 */
	private static function template_info(): array {
		$native = Panel::native_content_templates();
		$out    = [];
		foreach ( array_keys( Registry::templates() ) as $slug ) {
			$labels = [];
			foreach ( Registry::definitions( $slug ) as $def ) {
				$labels[] = $def->label;
			}
			$out[ $slug ] = [
				'sections' => implode( ', ', array_slice( $labels, 0, 4 ) ) . ( count( $labels ) > 4 ? ', …' : '' ),
				'native'   => in_array( $slug, $native, true ),
			];
		}
		return $out;
	}

	/** enqueue_block_editor_assets: the info notice (template-aware, dismissible). */
	public static function block_editor_notice(): void {
		if ( ! self::is_page_editor() ) {
			return;
		}
		wp_register_script( 'hk9-editor-guidance', false, [ 'wp-data', 'wp-notices', 'wp-editor' ], HK9_CORE_VERSION, true );
		wp_enqueue_script( 'hk9-editor-guidance' );

		$config = [
			'templates' => self::template_info(),
			'homeId'    => 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0,
			'i18n'      => [
				/* translators: %s: section labels */
				'sections' => __( 'This page is built from the section panels below the editor (%s).', 'heartland-k9s-core' ),
				'after'    => __( 'Anything you write here is shown after those sections.', 'heartland-k9s-core' ),
				'before'   => __( 'Anything you write here is shown right after the hero, before the other sections.', 'heartland-k9s-core' ),
				'hide'     => __( 'Anything you write here is not shown on the page (Page sections → Editor content).', 'heartland-k9s-core' ),
				'change'   => __( 'Change where it appears under Page sections → Editor content in the sidebar.', 'heartland-k9s-core' ),
				'native'   => __( 'The text and blocks you write here appear in the card under the hero. Optional sections (%s) are in the panels below the editor and can be switched on under Page sections.', 'heartland-k9s-core' ),
			],
		];
		$js     = <<<'JS'
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.data.select || ! wp.data.dispatch ) {
		return;
	}
	var cfg = window.HK9EditorGuidance || {};
	var ID = 'hk9-editor-guidance';
	var last = '';
	var dismissedFor = '';
	function slugOf( template, postId ) {
		var t = ( template || '' ).replace( /^.*\//, '' ).replace( /\.php$/, '' );
		if ( ! t || t === 'default' ) {
			return cfg.homeId && postId === cfg.homeId ? 'home' : 'default';
		}
		return t;
	}
	function message( slug, position ) {
		var info = cfg.templates && cfg.templates[ slug ];
		if ( ! info || slug === 'default' ) {
			return '';
		}
		if ( info.native ) {
			return cfg.i18n.native.replace( '%s', info.sections );
		}
		var where = cfg.i18n[ position ] || cfg.i18n.after;
		return cfg.i18n.sections.replace( '%s', info.sections ) + ' ' + where + ' ' + cfg.i18n.change;
	}
	function refresh() {
		var editor = wp.data.select( 'core/editor' );
		if ( ! editor || typeof editor.getEditedPostAttribute !== 'function' ) {
			return;
		}
		var postId = editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
		var template = editor.getEditedPostAttribute( 'template' );
		if ( template === undefined ) {
			return;
		}
		var meta = editor.getEditedPostAttribute( 'meta' ) || {};
		var layout = meta.hk9_sections_layout || {};
		var position = layout.content_position || 'after';
		var slug = slugOf( template, postId );
		var text = message( slug, position );
		var key = slug + '|' + position + '|' + text;
		if ( key === last ) {
			return;
		}
		last = key;
		var notices = wp.data.dispatch( 'core/notices' );
		if ( ! text ) {
			notices.removeNotice( ID );
			return;
		}
		if ( dismissedFor === slug ) {
			return; // Dismissed for this template: do not nag on every keystroke.
		}
		notices.createNotice( 'info', text, { id: ID, isDismissible: true, type: 'default', onDismiss: function () { dismissedFor = slug; } } );
	}
	// Dismissal via the notice's close button: watch the store for our id disappearing.
	var seen = false;
	wp.data.subscribe( function () {
		var list = wp.data.select( 'core/notices' ).getNotices();
		var present = list.some( function ( n ) { return n.id === ID; } );
		if ( seen && ! present && last ) {
			dismissedFor = last.split( '|' )[ 0 ];
		}
		seen = present;
		refresh();
	} );
	refresh();
} )( window.wp );
JS;
		wp_add_inline_script( 'hk9-editor-guidance', 'window.HK9EditorGuidance = ' . wp_json_encode( $config ) . ';' . "\n" . $js );
	}

	/** admin_enqueue_scripts: provider-dependent field visibility + small layout-panel styles (both editors). */
	public static function panel_enhancements( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || ! self::is_page_editor() ) {
			return;
		}
		$css = '.hk9-layout__edit-hint{margin:0 0 .75rem;color:#50575e}.hk9-layout__content{margin-top:.75rem;padding-top:.75rem;border-top:1px solid #dcdcde}.hk9-layout__content-label{display:block;font-weight:600;margin-bottom:.25rem}.hk9-layout__content-select{width:100%;max-width:100%}.hk9-layout__content-note{margin-top:.75rem}.hk9-field[data-hk9-provider-hidden]{display:none}';
		if ( wp_style_is( 'hk9-sections', 'registered' ) ) {
			wp_add_inline_style( 'hk9-sections', $css );
		} else {
			wp_register_style( 'hk9-editor-guidance', false, [], HK9_CORE_VERSION );
			wp_enqueue_style( 'hk9-editor-guidance' );
			wp_add_inline_style( 'hk9-editor-guidance', $css );
		}

		wp_register_script( 'hk9-provider-fields', false, [], HK9_CORE_VERSION, true );
		wp_enqueue_script( 'hk9-provider-fields' );
		$config = [
			'defaultProvider' => class_exists( FormProviders::class ) ? FormProviders::default_provider() : 'builtin',
		];
		$js     = <<<'JS'
( function () {
	var cfg = window.HK9ProviderFields || {};
	function apply( select ) {
		var fields = select.closest( '.hk9-fields' );
		if ( ! fields ) {
			return;
		}
		var effective = select.value === 'inherit' ? ( cfg.defaultProvider || 'builtin' ) : select.value;
		fields.querySelectorAll( ':scope > .hk9-field' ).forEach( function ( field ) {
			var key = field.getAttribute( 'data-hk9-key' );
			var show = true;
			if ( key === 'gravity_form_id' ) {
				show = effective === 'gravity';
			} else if ( key === 'shortcode' ) {
				show = effective === 'shortcode';
			}
			if ( show ) {
				field.removeAttribute( 'data-hk9-provider-hidden' );
			} else {
				field.setAttribute( 'data-hk9-provider-hidden', '1' );
			}
		} );
	}
	function applyAll( root ) {
		( root || document ).querySelectorAll( '.hk9-field[data-hk9-key="provider"] select' ).forEach( apply );
	}
	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.matches && e.target.matches( '.hk9-field[data-hk9-key="provider"] select' ) ) {
			apply( e.target );
		}
	}, true );
	function boot() {
		applyAll();
		var box = document.querySelector( '[data-hk9-sections-box]' );
		if ( box && window.MutationObserver ) {
			new MutationObserver( function () { applyAll( box ); } ).observe( box, { childList: true } );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
JS;
		wp_add_inline_script( 'hk9-provider-fields', 'window.HK9ProviderFields = ' . wp_json_encode( $config ) . ';' . "\n" . $js );
	}

	/**
	 * hidden_meta_boxes: the section boxes are always visible on page screens
	 * (Screen Options cannot hide them).
	 *
	 * @param string[]   $hidden Hidden box ids.
	 * @param \WP_Screen $screen Screen.
	 * @return string[]
	 */
	public static function never_hidden( $hidden, $screen ): array {
		if ( ! is_array( $hidden ) || ! $screen instanceof \WP_Screen || 'page' !== $screen->post_type ) {
			return is_array( $hidden ) ? $hidden : [];
		}
		return array_values( array_diff( $hidden, self::BOXES ) );
	}

	/**
	 * User meta-box order for pages: the "Sections" box stays first under the
	 * editor (normal column) and "Page sections" first in the side column,
	 * whatever a user dragged earlier.
	 *
	 * @param mixed $order Stored order (false when none).
	 * @return mixed
	 */
	public static function keep_first( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}
		$pin = [
			'normal' => 'hk9_sections',
			'side'   => 'hk9_sections_layout',
		];
		foreach ( $pin as $context => $box ) {
			foreach ( $order as $ctx => $list ) {
				if ( ! is_string( $list ) ) {
					continue;
				}
				$ids = array_values( array_filter( explode( ',', $list ) ) );
				if ( in_array( $box, $ids, true ) ) {
					$order[ $ctx ] = implode( ',', array_values( array_diff( $ids, [ $box ] ) ) );
				}
			}
			$current           = isset( $order[ $context ] ) && is_string( $order[ $context ] ) ? array_values( array_filter( explode( ',', $order[ $context ] ) ) ) : [];
			$order[ $context ] = implode( ',', array_merge( [ $box ], $current ) );
		}
		return $order;
	}
}
