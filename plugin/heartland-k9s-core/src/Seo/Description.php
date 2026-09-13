<?php
/**
 * Meta description resolver: SEO field → excerpt → hero intro / section text →
 * first paragraph of the content, trimmed to ~155 characters at a word
 * boundary. Registry records get the generic text from Privacy\Registry
 * through the `hk9/seo/description` filter.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

defined( 'ABSPATH' ) || exit;

final class Description {

	public const LENGTH = 155;

	/** Description for the current main-query view ('' = none). */
	public static function for_view(): string {
		$kind = Context::kind();
		if ( '404' === $kind || 'search' === $kind ) {
			return '';
		}
		$text = '';
		$post = Context::post();

		if ( 'home' === $kind ) {
			// The posts page: its own "Search & social" description, then the Blog tab intro, then the page.
			$text = $post ? (string) Fields::get( (int) $post->ID, 'description', '' ) : '';
			if ( '' === self::clean( $text ) ) {
				$text = self::option_text( 'blog.hero_text' );
			}
			if ( '' === $text && $post ) {
				$text = self::for_post( $post, false );
			}
		} elseif ( $post ) {
			$text = self::for_post( $post, true );
		} elseif ( 'archive' === $kind ) {
			$text = (string) get_the_archive_description();
			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				$obj = get_queried_object();
				if ( $obj instanceof \WP_Term ) {
					/* translators: 1: term name, 2: site name */
					$text = sprintf( __( 'Posts about %1$s from %2$s.', 'heartland-k9s-core' ), $obj->name, get_bloginfo( 'name' ) );
				}
			}
		}

		if ( '' === self::clean( $text ) ) {
			$text = (string) get_bloginfo( 'description' );
		}
		$text = self::trim( self::clean( $text ) );

		/**
		 * Filters the meta description for the current view.
		 *
		 * @param string $text    Description (already trimmed).
		 * @param int    $post_id Queried post id (0 for archives).
		 */
		return self::trim( self::clean( (string) apply_filters( 'hk9/seo/description', $text, $post ? (int) $post->ID : 0 ) ) );
	}

	/**
	 * Untrimmed description candidate for a post (field, excerpt, hero text,
	 * first paragraph). '' when nothing usable exists.
	 *
	 * @param \WP_Post $post      Post.
	 * @param bool     $use_field Whether the SEO field counts (main view only).
	 */
	public static function for_post( \WP_Post $post, bool $use_field = true ): string {
		if ( 'hk9_barkode' === $post->post_type ) {
			return ''; // The generic registry text comes from Privacy\Registry via the filter.
		}
		if ( $use_field ) {
			$field = (string) Fields::get( (int) $post->ID, 'description', '' );
			if ( '' !== self::clean( $field ) ) {
				return $field;
			}
		}
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			return (string) $post->post_excerpt;
		}
		$hero = self::hero_text( $post );
		if ( '' !== $hero ) {
			return $hero;
		}
		$summary = self::cpt_summary( $post );
		if ( '' !== $summary ) {
			return $summary;
		}
		return self::first_paragraph( (string) $post->post_content );
	}

	/** A settings value as plain text. */
	private static function option_text( string $key ): string {
		$value = function_exists( 'hk9_option' ) ? hk9_option( $key, '' ) : '';
		return is_scalar( $value ) ? self::clean( (string) $value ) : '';
	}

	/** Hero / mission text of a templated page. */
	private static function hero_text( \WP_Post $post ): string {
		if ( 'page' !== $post->post_type || ! function_exists( 'hk9_section' ) || ! function_exists( 'hk9_template_for_post' ) ) {
			return '';
		}
		$template = (string) hk9_template_for_post( (int) $post->ID );
		$hero     = in_array( $template, [ 'home', 'program', 'barkode' ], true ) ? hk9_section( (int) $post->ID, 'hero_image' ) : hk9_section( (int) $post->ID, 'hero_band' );
		$text     = is_array( $hero ) ? self::clean( (string) ( $hero['text'] ?? '' ) ) : '';
		if ( '' !== $text ) {
			return $text;
		}
		if ( 'home' === $template ) {
			$mission = hk9_section( (int) $post->ID, 'mission' );
			$text    = is_array( $mission ) ? self::clean( (string) ( $mission['text'] ?? '' ) ) : '';
		}
		return $text;
	}

	/** Summary-like CPT fields (team summary, campaign summary, story quote). */
	private static function cpt_summary( \WP_Post $post ): string {
		if ( ! function_exists( 'hk9_cpt_meta' ) ) {
			return '';
		}
		$key = match ( $post->post_type ) {
			'hk9_team', 'hk9_campaign' => 'summary',
			'hk9_story'                => 'quote',
			default                    => '',
		};
		if ( '' === $key ) {
			return '';
		}
		$value = hk9_cpt_meta( (int) $post->ID, $key, '' );
		return is_scalar( $value ) ? self::clean( (string) $value ) : '';
	}

	/** First non-empty paragraph of block/HTML content (no shortcodes, no tags). */
	public static function first_paragraph( string $content ): string {
		if ( '' === trim( $content ) ) {
			return '';
		}
		$content = excerpt_remove_blocks( $content );
		$content = strip_shortcodes( $content );
		$content = preg_replace( '/<(script|style|noscript|template)[^>]*>.*?<\/\1>/is', '', $content ) ?? $content;
		// Split on block-level closers so one paragraph is one candidate.
		$chunks = preg_split( '/<\/(p|div|li|h[1-6]|blockquote|figure|section|article|td|dd)>|<br\s*\/?>\s*<br\s*\/?>/i', $content ) ?: [];
		foreach ( $chunks as $chunk ) {
			$text = self::clean( $chunk );
			if ( mb_strlen( $text ) >= 40 ) {
				return $text;
			}
		}
		return self::clean( $content );
	}

	/** Plain single-line text. */
	public static function clean( string $text ): string {
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		return trim( $text );
	}

	/** Trims to ~LENGTH characters at a word boundary with an ellipsis. */
	public static function trim( string $text, int $length = self::LENGTH ): string {
		$text = trim( $text );
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, $length );
		$pos = mb_strrpos( $cut, ' ' );
		if ( false !== $pos && $pos > (int) ( $length * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $pos );
		}
		return rtrim( $cut, " \t\n\r\0\x0B,;:-–—" ) . '…';
	}
}
