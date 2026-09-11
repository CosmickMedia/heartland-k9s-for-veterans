<?php
/**
 * Admin assets for the field framework (fields.css/js) and the section
 * panels (sections.css/js). Plain CSS/JS, no build step.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Fields;

defined( 'ABSPATH' ) || exit;

final class Assets {

	private static bool $hooked = false;

	/** Post types whose edit screens load the field assets (page + CPTs with a Details box). */
	private static array $post_types = [ 'page' ];

	/** Hooks the enqueue callback once. */
	public static function register(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_action( 'admin_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
	}

	/** Adds post types whose edit screens need the assets. */
	public static function add_post_types( array $types ): void {
		self::$post_types = array_values( array_unique( array_merge( self::$post_types, array_map( 'strval', $types ) ) ) );
	}

	/** Enqueue on post edit screens of registered post types. */
	public static function maybe_enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( (string) $screen->post_type, self::$post_types, true ) ) {
			return;
		}
		self::enqueue();
	}

	/** Registers + enqueues the assets (idempotent). Usable by other admin screens too. */
	public static function enqueue(): void {
		$dir = HK9_CORE_DIR . 'assets/';
		$url = HK9_CORE_URL . 'assets/';
		$ver = static function ( string $rel ) use ( $dir ): string {
			$file = $dir . $rel;
			return file_exists( $file ) ? (string) filemtime( $file ) : HK9_CORE_VERSION;
		};

		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}

		wp_register_style( 'hk9-fields', $url . 'css/fields.css', [], $ver( 'css/fields.css' ) );
		wp_register_style( 'hk9-sections', $url . 'css/sections.css', [ 'hk9-fields' ], $ver( 'css/sections.css' ) );
		wp_register_script( 'hk9-fields', $url . 'js/fields.js', [], $ver( 'js/fields.js' ), true );
		wp_register_script( 'hk9-sections', $url . 'js/sections.js', [ 'hk9-fields' ], $ver( 'js/sections.js' ), true );

		$post_id = 0;
		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
			$post_id = (int) $GLOBALS['post']->ID;
		}

		wp_localize_script(
			'hk9-fields',
			'HK9Fields',
			[
				'spriteUrl'    => Renderer::sprite_url(),
				'symbolPrefix' => Renderer::sprite_symbol_prefix(),
				'pickUrl'      => esc_url_raw( rest_url( 'hk9/v1/pick' ) ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'panelNonce'   => wp_create_nonce( 'hk9_section_panel' ),
				'postId'       => $post_id,
				'i18n'         => [
					'selectImage'   => __( 'Select image', 'heartland-k9s-core' ),
					'selectFile'    => __( 'Select file', 'heartland-k9s-core' ),
					'selectImages'  => __( 'Select images', 'heartland-k9s-core' ),
					'use'           => __( 'Use this', 'heartland-k9s-core' ),
					'add'           => __( 'Add', 'heartland-k9s-core' ),
					'remove'        => __( 'Remove', 'heartland-k9s-core' ),
					'moveUp'        => __( 'Move up', 'heartland-k9s-core' ),
					'moveDown'      => __( 'Move down', 'heartland-k9s-core' ),
					'noResults'     => __( 'No matches.', 'heartland-k9s-core' ),
					'searching'     => __( 'Searching…', 'heartland-k9s-core' ),
					'searchFailed'  => __( 'Search is unavailable right now.', 'heartland-k9s-core' ),
					'itemLabel'     => __( 'Item', 'heartland-k9s-core' ),
					'panelFailed'   => __( 'The section panel could not be reloaded. Save the page and reload to edit its sections.', 'heartland-k9s-core' ),
					'panelLoading'  => __( 'Loading sections for the selected template…', 'heartland-k9s-core' ),
					'confirmRemove' => __( 'Remove this item?', 'heartland-k9s-core' ),
				],
			]
		);

		wp_enqueue_style( 'hk9-fields' );
		wp_enqueue_style( 'hk9-sections' );
		wp_enqueue_script( 'hk9-fields' );
		wp_enqueue_script( 'hk9-sections' );
	}
}
