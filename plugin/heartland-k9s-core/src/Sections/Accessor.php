<?php
/**
 * Canonical readers used by the theme helpers (hk9_section(), hk9_sections_layout(), ...).
 *
 * Values are read with get_post_meta() so the preview meta filter
 * (_wp_preview_meta_filter) applies, then sanitized so defaults fill in and
 * the result is never null.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Sections;

defined( 'ABSPATH' ) || exit;

final class Accessor {

	/** Small per-request cache: "post:key:hash" => data. */
	private static array $cache = [];

	/**
	 * Template slug for a post: file name without `.php`, 'default' for none.
	 * The front page resolves to 'home' when it has no explicit template.
	 */
	public static function template_for_post( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return 'default';
		}
		$slug = (string) get_page_template_slug( $post_id );
		$slug = '' === $slug ? 'default' : sanitize_key( preg_replace( '/\.php$/', '', wp_basename( $slug ) ) ?? 'default' );
		if ( '' === $slug ) {
			$slug = 'default';
		}
		if ( 'default' === $slug && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id ) {
			$slug = 'home';
		}
		/**
		 * Filters the resolved template slug for a post.
		 *
		 * @param string $slug    Template slug.
		 * @param int    $post_id Post id.
		 */
		return (string) apply_filters( 'hk9/sections/template_for_post', $slug, $post_id );
	}

	/**
	 * Sanitized section data with defaults (never null; [] when the section is unknown).
	 */
	public static function section( int $post_id, string $section_id, ?string $template = null ): array {
		$template = $template ?? self::template_for_post( $post_id );
		$def      = Registry::definition( $template, $section_id ) ?? Registry::definition_any( $section_id );
		if ( ! $def ) {
			return [];
		}
		$raw   = $post_id > 0 ? get_post_meta( $post_id, $def->meta_key(), true ) : '';
		$raw   = is_array( $raw ) ? $raw : [];
		$ckey  = $post_id . ':' . $def->meta_key() . ':' . md5( serialize( $raw ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		if ( ! isset( self::$cache[ $ckey ] ) ) {
			self::$cache[ $ckey ] = $def->sanitize( $raw );
		}
		$data = self::$cache[ $ckey ];

		if ( 'hero_band' === $def->type && $post_id > 0 ) {
			if ( '' === (string) ( $data['heading'] ?? '' ) ) {
				$data['heading'] = (string) get_the_title( $post_id );
			}
			if ( '' === (string) ( $data['text'] ?? '' ) ) {
				$data['text'] = (string) get_post_field( 'post_excerpt', $post_id, 'raw' );
			}
		}

		/**
		 * Filters section data before it reaches templates.
		 *
		 * @param array      $data       Sanitized section data.
		 * @param int        $post_id    Post id.
		 * @param Definition $def        Section definition.
		 */
		return (array) apply_filters( 'hk9/sections/data', $data, $post_id, $def );
	}

	/** Raw layout meta, sanitized. */
	public static function layout_raw( int $post_id ): array {
		$raw = $post_id > 0 ? get_post_meta( $post_id, Layout::META_KEY, true ) : [];
		return Layout::sanitize( is_array( $raw ) ? $raw : [] );
	}

	/**
	 * Ordered visible section ids for a template.
	 *
	 * @return string[]
	 */
	public static function layout( int $post_id, ?string $template = null ): array {
		$template = $template ?? self::template_for_post( $post_id );
		$sections = Registry::definitions( $template );
		if ( empty( $sections ) ) {
			return [];
		}
		return Layout::resolve( self::layout_raw( $post_id ), $sections );
	}

	/** Definitions of a template as plain arrays keyed by section id. */
	public static function definitions( string $template ): array {
		$out = [];
		foreach ( Registry::definitions( $template ) as $def ) {
			$out[ $def->id ] = $def->to_array();
		}
		return $out;
	}

	/** Clears the per-request cache (tests). */
	public static function flush(): void {
		self::$cache = [];
	}
}
