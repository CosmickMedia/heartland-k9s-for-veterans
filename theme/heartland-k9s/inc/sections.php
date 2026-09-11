<?php
/**
 * Section renderer + plugin-independent fallbacks for the sections API
 * (docs/ARCHITECTURE.md §5): hk9_section(), hk9_sections_layout(),
 * hk9_template_for_post(), hk9_render_sections().
 *
 * With the plugin active, HK9\Core\Support\SectionHelpers provides the real
 * accessors and these fallbacks are skipped (function_exists guards).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template slug → file map (page-templates/<slug>.php).
 *
 * @return array<string, string>
 */
function hk9_theme_template_slugs(): array {
	return [
		'home',
		'about',
		'program',
		'veterans',
		'get-involved',
		'barkode',
		'stories',
		'contact',
		'landing',
		'donate',
		'events',
		'campaigns',
		'partners',
		'people',
		'teams',
		'highlighted-team',
		'gallery',
		'application',
		'thank-you',
	];
}

if ( ! function_exists( 'hk9_template_for_post' ) ) {
	/**
	 * Template slug for a page (`home`, `about`, … `default`).
	 *
	 * @param int $post_id Page id.
	 * @return string
	 */
	function hk9_template_for_post( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return 'default';
		}

		$file = (string) get_page_template_slug( $post_id );
		if ( preg_match( '#^page-templates/([a-z0-9-]+)\.php$#', $file, $m ) && in_array( $m[1], hk9_theme_template_slugs(), true ) ) {
			return $m[1];
		}

		// The static front page renders as the Home template even without the template assigned.
		if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id ) {
			return 'home';
		}

		return 'default';
	}
}

if ( ! function_exists( 'hk9_section' ) ) {
	/**
	 * Section data with defaults (never null).
	 *
	 * Fallback: theme reference defaults merged with any `hk9_sec_<id>` meta that an
	 * earlier import may have written.
	 *
	 * @param int    $post_id Page id.
	 * @param string $id      Section id.
	 * @return array
	 */
	function hk9_section( int $post_id, string $id ): array {
		$template = hk9_template_for_post( $post_id );
		$defaults = hk9_theme_section_defaults( $template );
		$data     = $defaults[ $id ]['data'] ?? [];

		$meta = $post_id > 0 ? get_post_meta( $post_id, 'hk9_sec_' . $id, true ) : null;
		if ( is_array( $meta ) ) {
			unset( $meta['__present'] );
			$data = array_replace( $data, $meta );
		}

		return $data;
	}
}

if ( ! function_exists( 'hk9_sections_layout' ) ) {
	/**
	 * Ordered, visible section ids for a page/template.
	 *
	 * @param int    $post_id  Page id.
	 * @param string $template Template slug.
	 * @return string[]
	 */
	function hk9_sections_layout( int $post_id, string $template ): array {
		$defaults = hk9_theme_section_defaults( $template );
		$order    = array_keys( $defaults );
		$hidden   = array_keys( array_filter( $defaults, static fn( $s ) => ! empty( $s['hidden'] ) ) );

		$meta = $post_id > 0 ? get_post_meta( $post_id, 'hk9_sections_layout', true ) : null;
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['order'] ) && is_array( $meta['order'] ) ) {
				$saved = array_values( array_filter( $meta['order'], 'is_string' ) );
				// Keep only known ids; append any new defaults the saved order does not know.
				$order = array_merge( array_values( array_intersect( $saved, $order ) ), array_values( array_diff( $order, $saved ) ) );
			}
			if ( isset( $meta['hidden'] ) && is_array( $meta['hidden'] ) ) {
				$hidden = array_values( array_filter( $meta['hidden'], 'is_string' ) );
			}
		}

		return array_values( array_diff( $order, $hidden ) );
	}
}

if ( ! function_exists( 'hk9_section_type' ) ) {
	/**
	 * Template-part type for a section id.
	 *
	 * @param string $template Template slug.
	 * @param string $id       Section id.
	 * @return string
	 */
	function hk9_section_type( string $template, string $id ): string {
		// Plugin registry first (HK9\Core\Sections\Definition::$type), theme map second.
		if ( function_exists( 'hk9_section_definitions' ) ) {
			$definitions = hk9_section_definitions( $template );
			if ( is_array( $definitions ) && ! empty( $definitions[ $id ]['type'] ) ) {
				return sanitize_key( (string) $definitions[ $id ]['type'] );
			}
		}
		return hk9_theme_section_type( $template, $id );
	}
}

/**
 * Render every visible section of a page through template-parts/sections/<type>.php.
 *
 * Reads all section data before any nested loop (ARCHITECTURE §5 note F6).
 *
 * @param int    $post_id  Page id.
 * @param string $template Template slug.
 */
function hk9_render_sections( int $post_id, string $template ): void {
	$layout = hk9_sections_layout( $post_id, $template );

	$sections = [];
	foreach ( $layout as $id ) {
		$type = hk9_section_type( $template, $id );
		if ( ! locate_template( 'template-parts/sections/' . $type . '.php' ) ) {
			continue;
		}
		$sections[] = [
			'id'   => $id,
			'type' => $type,
			'data' => hk9_section( $post_id, $id ),
		];
	}

	/**
	 * Filter the resolved sections before rendering.
	 *
	 * @param array  $sections [ ['id','type','data'], … ].
	 * @param int    $post_id  Page id.
	 * @param string $template Template slug.
	 */
	$sections = apply_filters( 'hk9/theme/render_sections', $sections, $post_id, $template );

	foreach ( $sections as $section ) {
		get_template_part(
			'template-parts/sections/' . $section['type'],
			null,
			[
				'data'     => $section['data'],
				'post_id'  => $post_id,
				'id'       => $section['id'],
				'template' => $template,
			]
		);
	}
}

/**
 * Block content card used by page.php / landing after the hero band.
 *
 * @param int  $post_id Page id.
 * @param bool $wrap    Wrap in the overlap card.
 */
function hk9_the_content_card( int $post_id, bool $wrap = true ): void {
	$content = get_post_field( 'post_content', $post_id );
	if ( '' === trim( (string) $content ) ) {
		return;
	}

	if ( $wrap ) {
		echo '<div class="hk9-overlap"><div class="hk9-overlap__card hk9-prose">';
	} else {
		echo '<div class="hk9-prose">';
	}

	the_content();

	echo $wrap ? '</div></div>' : '</div>';
}
