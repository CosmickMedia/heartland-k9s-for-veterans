<?php
/**
 * Page meta boxes for sections (content + "Page sections" layout), the
 * template-scoped save handler, the classic-preview fallback and the ajax
 * template-switch endpoint.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Sections;

defined( 'ABSPATH' ) || exit;

final class MetaBox {

	public const NONCE_FIELD  = 'hk9_sections_nonce';
	public const NONCE_ACTION = 'hk9_sections_';

	public static function register(): void {
		add_action( 'add_meta_boxes_page', [ self::class, 'add_boxes' ] );
		add_action( 'save_post_page', [ self::class, 'save' ], 10, 3 );
		add_action( 'admin_action_editpost', [ self::class, 'classic_preview_fallback' ], 5 );
		add_action( 'wp_ajax_hk9_section_panel', [ self::class, 'ajax_panel' ] );
	}

	/** Registers the two boxes for the page's current template. */
	public static function add_boxes( \WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$template = Accessor::template_for_post( $post->ID );
		$label    = Registry::has_template( $template ) ? Registry::template_label( $template ) : __( 'Default template', 'heartland-k9s-core' );

		add_meta_box(
			'hk9_sections',
			sprintf( /* translators: %s: template label */ __( 'Sections — %s', 'heartland-k9s-core' ), $label ),
			[ self::class, 'render_sections_box' ],
			'page',
			'normal',
			'high',
			[
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			]
		);
		add_meta_box(
			'hk9_sections_layout',
			__( 'Page sections', 'heartland-k9s-core' ),
			[ self::class, 'render_layout_box' ],
			'page',
			'side',
			'default',
			[
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			]
		);
	}

	public static function render_sections_box( \WP_Post $post ): void {
		$template = Accessor::template_for_post( $post->ID );
		wp_nonce_field( self::NONCE_ACTION . $post->ID, self::NONCE_FIELD );
		echo '<div data-hk9-sections-box>' . Panel::render_sections( $post, $template ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel output is escaped.
	}

	public static function render_layout_box( \WP_Post $post ): void {
		$template = Accessor::template_for_post( $post->ID );
		echo '<div data-hk9-layout-box>' . Panel::render_layout( $post, $template ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel output is escaped.
	}

	/**
	 * save_post_page prio 10: writes only the current template's section keys
	 * (never deletes), bails without nonce/cap or on autosaves/revisions.
	 */
	public static function save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$template = Accessor::template_for_post( $post_id );
		foreach ( Registry::definitions( $template ) as $def ) {
			$key = $def->meta_key();
			if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
				continue;
			}
			$clean = $def->sanitize( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by the field sanitizer.
			if ( Registry::is_default_write( $key, $post_id, $clean ) ) {
				continue; // Untouched defaults are never materialized.
			}
			update_post_meta( $post_id, $key, wp_slash( $clean ) );
		}

		if ( isset( $_POST[ Layout::META_KEY ] ) && is_array( $_POST[ Layout::META_KEY ] ) ) {
			$clean = Layout::sanitize( wp_unslash( $_POST[ Layout::META_KEY ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! Registry::is_default_write( Layout::META_KEY, $post_id, $clean ) ) {
				update_post_meta( $post_id, Layout::META_KEY, wp_slash( $clean ) );
			}
		}
	}

	/**
	 * admin_action_editpost prio 5: when the classic editor previews a
	 * non-draft page, core deletes an unchanged autosave and returns 0, so
	 * meta-only changes would not preview. Deleting the user's autosave first
	 * forces the "new autosave" branch, which copies parent meta and then
	 * applies the posted section fields.
	 */
	public static function classic_preview_fallback(): void {
		if ( ! isset( $_POST['wp-preview'] ) || 'dopreview' !== $_POST['wp-preview'] ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) && ! isset( $_POST['hk9_details_nonce'] ) ) {
			return;
		}
		$post_id = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0;
		if ( $post_id <= 0 || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-post_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}
		// post_preview() saves drafts by their author directly; only the autosave branch needs help.
		if ( ! wp_check_post_lock( $post->ID ) && get_current_user_id() === (int) $post->post_author && in_array( $post->post_status, [ 'draft', 'auto-draft' ], true ) ) {
			return;
		}
		$autosave = wp_get_post_autosave( $post_id, get_current_user_id() );
		if ( $autosave ) {
			wp_delete_post_revision( $autosave->ID );
		}
	}

	/** Normalizes a template value (file path or slug) to a registry slug. */
	public static function normalize_template( string $template ): string {
		$template = trim( $template );
		if ( '' === $template || 'default' === $template ) {
			return 'default';
		}
		$slug = sanitize_key( preg_replace( '/\.php$/', '', wp_basename( $template ) ) ?? '' );
		return '' === $slug ? 'default' : $slug;
	}

	/**
	 * wp_ajax_hk9_section_panel: returns the panels for another template
	 * (used when the template is switched in the editor). Accepts the editor's
	 * unsaved meta (JSON) so the reloaded panel shows in-progress values.
	 */
	public static function ajax_panel(): void {
		check_ajax_referer( 'hk9_section_panel', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to edit this page.', 'heartland-k9s-core' ) ], 403 );
		}
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			wp_send_json_error( [ 'message' => __( 'Invalid page.', 'heartland-k9s-core' ) ], 400 );
		}
		$requested = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$slug      = self::normalize_template( $requested );
		if ( 'default' !== $slug ) {
			$known = false;
			foreach ( array_keys( wp_get_theme()->get_page_templates( $post ) ) as $file ) {
				if ( self::normalize_template( (string) $file ) === $slug ) {
					$known = true;
					break;
				}
			}
			if ( ! $known && ! Registry::has_template( $slug ) ) {
				wp_send_json_error( [ 'message' => __( 'Unknown template.', 'heartland-k9s-core' ) ], 400 );
			}
		}
		if ( 'default' === $slug && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id ) {
			$slug = 'home';
		}

		$values = [];
		if ( isset( $_POST['meta'] ) && is_string( $_POST['meta'] ) ) {
			$meta = json_decode( wp_unslash( $_POST['meta'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per key below.
			if ( is_array( $meta ) ) {
				foreach ( $meta as $key => $value ) {
					if ( ! is_array( $value ) ) {
						continue;
					}
					if ( Layout::META_KEY === $key ) {
						$clean = Layout::sanitize( $value );
					} else {
						$def = Registry::by_key( (string) $key );
						if ( ! $def ) {
							continue;
						}
						$clean = $def->sanitize( $value );
					}
					// Untouched values (still the previous template's defaults) must not leak into the new template's panel.
					if ( Registry::is_default_write( (string) $key, $post_id, $clean ) ) {
						continue;
					}
					$values[ $key ] = $clean;
				}
			}
		}

		wp_send_json_success(
			[
				'template' => $slug,
				'label'    => Registry::has_template( $slug ) ? Registry::template_label( $slug ) : __( 'Default template', 'heartland-k9s-core' ),
				'sections' => Panel::render_sections( $post, $slug, $values ),
				'layout'   => Panel::render_layout( $post, $slug, $values[ Layout::META_KEY ] ?? null ),
			]
		);
	}
}
