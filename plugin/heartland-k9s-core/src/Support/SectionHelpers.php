<?php
/**
 * Global helper functions for sections and CPT meta (theme-facing API).
 *
 * All functions are guarded with function_exists() so Support/Helpers.php may
 * declare thin wrappers first; both must delegate to the same classes.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Support {

	defined( 'ABSPATH' ) || exit;

	final class SectionHelpers {

		/** Nothing to hook: the functions below are declared when this file loads. */
		public static function register(): void {}
	}
}

namespace {

	use HK9\Core\Meta\Registry as MetaRegistry;
	use HK9\Core\Sections\Accessor;

	if ( ! function_exists( 'hk9_section' ) ) {
		/**
		 * Sanitized section data with defaults (never null). Works for previews
		 * because values are read through get_post_meta().
		 *
		 * @param int    $post_id    Page id.
		 * @param string $section_id Section id (e.g. 'hero_band', 'cta').
		 * @return array Section data ([] when the section is unknown).
		 */
		function hk9_section( int $post_id, string $section_id ): array {
			return Accessor::section( $post_id, $section_id );
		}
	}

	if ( ! function_exists( 'hk9_sections_layout' ) ) {
		/**
		 * Ordered, visible section ids for a page + template.
		 *
		 * @param int    $post_id  Page id.
		 * @param string $template Template slug (e.g. 'about'); '' = resolve from the post.
		 * @return string[]
		 */
		function hk9_sections_layout( int $post_id, string $template = '' ): array {
			return Accessor::layout( $post_id, '' === $template ? null : $template );
		}
	}

	if ( ! function_exists( 'hk9_section_definitions' ) ) {
		/**
		 * Section definitions of a template as arrays keyed by section id.
		 */
		function hk9_section_definitions( string $template ): array {
			return Accessor::definitions( $template );
		}
	}

	if ( ! function_exists( 'hk9_template_for_post' ) ) {
		/**
		 * Template slug without .php ('default' for none; the front page with
		 * no explicit template resolves to 'home').
		 */
		function hk9_template_for_post( int $post_id ): string {
			return Accessor::template_for_post( $post_id );
		}
	}

	if ( ! function_exists( 'hk9_section_meta_key' ) ) {
		/**
		 * Meta key storing a section for a template ('' when unknown).
		 */
		function hk9_section_meta_key( string $template, string $section_id ): string {
			return HK9\Core\Sections\Registry::meta_key( $template, $section_id );
		}
	}

	if ( ! function_exists( 'hk9_cpt_meta' ) ) {
		/**
		 * CPT field value ('hk9_' + key) sanitized with defaults.
		 *
		 * @param int    $post_id Post id.
		 * @param string $key     Field key without the hk9_ prefix.
		 * @param mixed  $default Returned when the key was never saved (null = field default).
		 */
		function hk9_cpt_meta( int $post_id, string $key, mixed $default = null ): mixed {
			return MetaRegistry::get( $post_id, $key, $default );
		}
	}
}
