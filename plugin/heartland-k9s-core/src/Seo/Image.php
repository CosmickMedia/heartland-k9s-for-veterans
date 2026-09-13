<?php
/**
 * Social / structured-data image resolver: SEO field → featured image →
 * event flyer → hero image → Settings → SEO default; served from the
 * `hk9-og` size (1200×630, cropped) with width, height, alt and mime type.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

defined( 'ABSPATH' ) || exit;

final class Image {

	public const SIZE   = 'hk9-og';
	public const WIDTH  = 1200;
	public const HEIGHT = 630;

	/** Registers the 1200×630 crop (init; the theme registers its own sizes at after_setup_theme). */
	public static function register_size(): void {
		add_image_size( self::SIZE, self::WIDTH, self::HEIGHT, true );
		add_filter( 'image_size_names_choose', [ self::class, 'size_name' ] );
	}

	/**
	 * @param array<string, string> $sizes Size labels.
	 * @return array<string, string>
	 */
	public static function size_name( array $sizes ): array {
		$sizes[ self::SIZE ] = __( 'Social share (1200×630)', 'heartland-k9s-core' );
		return $sizes;
	}

	/**
	 * Image for the current view.
	 *
	 * @return array{id:int,url:string,width:int,height:int,alt:string,mime:string}|null
	 */
	public static function for_view(): ?array {
		if ( is_singular( 'hk9_barkode' ) ) {
			return self::default_image(); // Never a registry photo.
		}
		$post = Context::post();
		$id   = $post ? self::id_for_post( $post ) : 0;
		if ( $id <= 0 && is_home() ) {
			$id = self::option_id( 'blog.hero_image' );
		}
		$image = $id > 0 ? self::data( $id ) : null;
		if ( ! $image ) {
			$image = self::default_image();
		}
		/**
		 * Filters the social image for the current view (null = none).
		 *
		 * @param array|null $image {id,url,width,height,alt,mime}.
		 */
		$image = apply_filters( 'hk9/seo/image', $image );
		return is_array( $image ) && ! empty( $image['url'] ) ? $image : null;
	}

	/** Attachment id for a post following the fallback chain (0 = none). */
	public static function id_for_post( \WP_Post $post ): int {
		if ( 'hk9_barkode' === $post->post_type ) {
			return 0;
		}
		$post_id = (int) $post->ID;
		$id      = (int) Fields::get( $post_id, 'image', 0 );
		if ( $id > 0 && wp_attachment_is_image( $id ) ) {
			return $id;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return (int) get_post_thumbnail_id( $post_id );
		}
		if ( 'hk9_event' === $post->post_type && function_exists( 'hk9_cpt_meta' ) ) {
			$flyer = (int) hk9_cpt_meta( $post_id, 'flyer', 0 );
			if ( $flyer > 0 && wp_attachment_is_image( $flyer ) ) {
				return $flyer;
			}
		}
		if ( 'page' === $post->post_type && function_exists( 'hk9_section' ) && function_exists( 'hk9_template_for_post' ) ) {
			$template = (string) hk9_template_for_post( $post_id );
			if ( in_array( $template, [ 'home', 'program', 'barkode' ], true ) ) {
				$hero = hk9_section( $post_id, 'hero_image' );
				$hero = is_array( $hero ) ? (int) ( $hero['image'] ?? 0 ) : 0;
				if ( $hero > 0 ) {
					return $hero;
				}
			}
			if ( 'about' === $template ) {
				$legacy = hk9_section( $post_id, 'legacy' );
				$legacy = is_array( $legacy ) ? (int) ( $legacy['image'] ?? 0 ) : 0;
				if ( $legacy > 0 ) {
					return $legacy;
				}
			}
		}
		return 0;
	}

	/** The Settings → SEO default social image (falls back to the header logo). */
	public static function default_image(): ?array {
		$id = self::option_id( 'seo.default_social_image' );
		if ( $id <= 0 ) {
			$id = self::option_id( 'branding.header_logo' );
		}
		if ( $id <= 0 ) {
			$id = (int) get_theme_mod( 'custom_logo', 0 );
		}
		return $id > 0 ? self::data( $id ) : null;
	}

	private static function option_id( string $key ): int {
		$id = function_exists( 'hk9_option' ) ? hk9_option( $key, 0 ) : 0;
		$id = is_scalar( $id ) ? (int) $id : 0;
		return $id > 0 && wp_attachment_is_image( $id ) ? $id : 0;
	}

	/**
	 * Image data for an attachment at the social size (the full image when the
	 * crop cannot be produced — small originals, SVGs).
	 *
	 * @return array{id:int,url:string,width:int,height:int,alt:string,mime:string}|null
	 */
	public static function data( int $id, string $size = self::SIZE ): ?array {
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $id, $size );
		if ( $src && empty( $src[3] ) && function_exists( 'hk9_ensure_image_size' ) && 'full' !== $size ) {
			// The crop does not exist yet (media uploaded before the size was registered): create it once.
			if ( hk9_ensure_image_size( $id, $size ) ) {
				$src = wp_get_attachment_image_src( $id, $size );
			}
		}
		if ( ! $src || empty( $src[0] ) ) {
			return null;
		}
		$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( '' === trim( $alt ) ) {
			$alt = (string) get_the_title( $id );
		}
		// The served file may differ from the original's type (WebP sub-sizes): read it from the URL.
		$type = wp_check_filetype( (string) wp_parse_url( (string) $src[0], PHP_URL_PATH ) );
		$mime = is_array( $type ) && ! empty( $type['type'] ) ? (string) $type['type'] : (string) get_post_mime_type( $id );
		return [
			'id'     => $id,
			'url'    => (string) $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => Description::clean( $alt ),
			'mime'   => $mime,
		];
	}

	/** Schema.org ImageObject for an attachment (null when unavailable). */
	public static function object( int $id, string $size = self::SIZE, string $node_id = '' ): ?array {
		$data = self::data( $id, $size );
		if ( ! $data ) {
			return null;
		}
		$node = [ '@type' => 'ImageObject' ];
		if ( '' !== $node_id ) {
			$node['@id'] = $node_id;
		}
		$node['url']        = $data['url'];
		$node['contentUrl'] = $data['url'];
		if ( $data['width'] > 0 && $data['height'] > 0 ) {
			$node['width']  = $data['width'];
			$node['height'] = $data['height'];
		}
		if ( '' !== $data['alt'] ) {
			$node['caption'] = $data['alt'];
		}
		return $node;
	}
}
