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

	// Every style below has matching rules in assets/src/scss/_prose.scss, which
	// compiles into content.css (front end, .hk9-prose) and editor.css (editor
	// canvas), so the Heartland patterns look the same in both places.
	$styles = [
		'core/group'     => [
			'hk9-card'       => __( 'Card', 'heartland-k9s' ),
			'hk9-card-muted' => __( 'Card (muted)', 'heartland-k9s' ),
			'hk9-band'       => __( 'Navy band', 'heartland-k9s' ),
			'hk9-band-tint'  => __( 'Tinted band', 'heartland-k9s' ),
			'hk9-stat'       => __( 'Statistic', 'heartland-k9s' ),
			'hk9-callout'    => __( 'Callout panel', 'heartland-k9s' ),
		],
		'core/paragraph' => [
			'hk9-lead' => __( 'Lead (large, muted)', 'heartland-k9s' ),
		],
		'core/separator' => [
			'hk9-divider' => __( 'Crimson bar', 'heartland-k9s' ),
			'hk9-thin'    => __( 'Thin rule', 'heartland-k9s' ),
		],
		'core/quote'     => [
			'hk9-testimonial' => __( 'Testimonial', 'heartland-k9s' ),
		],
		'core/list'      => [
			'hk9-checklist' => __( 'Checklist', 'heartland-k9s' ),
		],
		'core/button'    => [
			'hk9-navy' => __( 'Navy', 'heartland-k9s' ),
		],
	];

	foreach ( $styles as $block => $variants ) {
		foreach ( $variants as $name => $label ) {
			register_block_style(
				$block,
				[
					'name'  => $name,
					'label' => $label,
				]
			);
		}
	}
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
 * Register the "Heartland" pattern category before core reads patterns/*.php
 * (`_register_theme_block_patterns`, init 10): every file there declares
 * `Categories: heartland-k9s`.
 */
function hk9_pattern_category(): void {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'heartland-k9s',
			[
				'label'       => __( 'Heartland', 'heartland-k9s' ),
				'description' => __( 'Page building blocks in the site design: text + image, cards, calls to action, FAQ, stats, quotes, contact details.', 'heartland-k9s' ),
			]
		);
	}
}
add_action( 'init', 'hk9_pattern_category', 9 );

/**
 * Placeholder image used by the image patterns until staff pick a photo
 * (theme asset, 4:3, muted with the paw glyph — never a third-party request).
 *
 * @return string URL.
 */
function hk9_pattern_placeholder_image(): string {
	return get_theme_file_uri( 'assets/img/placeholder-4x3.svg' );
}
