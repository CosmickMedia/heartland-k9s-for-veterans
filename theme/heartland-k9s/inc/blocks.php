<?php
/**
 * Block editor integration: block styles, editor token overrides, and removal of
 * remote pattern/block-directory requests (no third-party calls from the admin).
 *
 * The palette/font/spacing presets live in theme.json.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register block styles.
 */
function hk9_register_block_styles(): void {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	register_block_style(
		'core/group',
		[
			'name'  => 'hk9-callout',
			'label' => __( 'Callout panel', 'heartland-k9s' ),
		]
	);

	register_block_style(
		'core/separator',
		[
			'name'  => 'hk9-thin',
			'label' => __( 'Thin rule', 'heartland-k9s' ),
		]
	);

	register_block_style(
		'core/quote',
		[
			'name'  => 'hk9-testimonial',
			'label' => __( 'Testimonial', 'heartland-k9s' ),
		]
	);

	register_block_style(
		'core/list',
		[
			'name'  => 'hk9-checklist',
			'label' => __( 'Checklist', 'heartland-k9s' ),
		]
	);
}
add_action( 'init', 'hk9_register_block_styles' );

/**
 * Push the settings-derived token overrides into the editor canvas.
 */
function hk9_editor_root_css(): void {
	if ( ! is_admin() ) {
		return;
	}
	$css = hk9_root_css();
	if ( '' !== $css ) {
		wp_add_inline_style( 'wp-block-library', $css );
	}
}
add_action( 'enqueue_block_assets', 'hk9_editor_root_css' );

/**
 * Keep the editor offline: no remote block patterns, no block directory search.
 */
add_filter( 'should_load_remote_block_patterns', '__return_false' );
remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );

/**
 * Core patterns that do not fit the design are unregistered.
 */
function hk9_unregister_core_patterns(): void {
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', 'hk9_unregister_core_patterns', 20 );

/**
 * Register a "Heartland" pattern category for any theme patterns.
 */
function hk9_pattern_category(): void {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'heartland-k9s', [ 'label' => __( 'Heartland', 'heartland-k9s' ) ] );
	}
}
add_action( 'init', 'hk9_pattern_category' );
