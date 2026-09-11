<?php
/**
 * Template tags (docs/ARCHITECTURE.md §11 helpers).
 *
 * hk9_image(), hk9_button(), hk9_the_hero(), hk9_section_open()/close(),
 * hk9_pagination(), hk9_post_card(), hk9_link_url(), hk9_link_attrs(),
 * plus brand/logo helpers used by header.php and footer.php.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a link value (`{label,url,post_id,target,rel}`) to a URL.
 *
 * The theme always uses this implementation; the plugin ships its own
 * hk9_link_url()/hk9_link_attrs() pair (whose attrs string also carries href),
 * so the global names below are only defined when the plugin is absent.
 *
 * @param array $link Link value.
 * @return string
 */
function hk9_theme_link_url( array $link ): string {
	$post_id = (int) ( $link['post_id'] ?? 0 );
	if ( $post_id > 0 ) {
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
			$permalink = get_permalink( $post );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}
	}

	$url = isset( $link['url'] ) ? trim( (string) $link['url'] ) : '';
	if ( '' === $url || '#' === $url ) {
		return '';
	}

	// Site-relative paths are allowed ("/donate/").
	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		return home_url( $url );
	}

	return $url;
}

/**
 * target/rel attributes for a link value (escaped, leading space included, no href).
 *
 * @param array $link Link value.
 * @return string
 */
function hk9_theme_link_attrs( array $link ): string {
	$attrs  = '';
	$target = isset( $link['target'] ) ? (string) $link['target'] : '_self';
	$rel    = isset( $link['rel'] ) ? trim( (string) $link['rel'] ) : '';
	$url    = hk9_theme_link_url( $link );

	$external = '' !== $url && ! str_starts_with( $url, home_url() ) && preg_match( '#^https?://#i', $url );

	if ( '_blank' === $target ) {
		$attrs    .= ' target="_blank"';
		$rel_parts = array_unique( array_filter( array_merge( explode( ' ', $rel ), [ 'noopener', 'noreferrer' ] ) ) );
		$rel       = implode( ' ', $rel_parts );
	} elseif ( $external && '' === $rel ) {
		$rel = 'noopener';
	}

	if ( '' !== $rel ) {
		$attrs .= ' rel="' . esc_attr( $rel ) . '"';
	}

	return $attrs;
}

if ( ! function_exists( 'hk9_link_url' ) ) {
	/**
	 * Plugin-compatible global (defined only when the plugin is absent).
	 *
	 * @param array $link Link value.
	 * @return string
	 */
	function hk9_link_url( array $link ): string {
		return hk9_theme_link_url( $link );
	}
}

if ( ! function_exists( 'hk9_link_attrs' ) ) {
	/**
	 * Plugin-compatible global (defined only when the plugin is absent).
	 *
	 * @param array $link Link value.
	 * @return string
	 */
	function hk9_link_attrs( array $link ): string {
		return hk9_theme_link_attrs( $link );
	}
}

/**
 * Label for a link value with a fallback.
 *
 * @param array  $link     Link value.
 * @param string $fallback Fallback label.
 * @return string
 */
function hk9_link_label( array $link, string $fallback = '' ): string {
	$label = isset( $link['label'] ) ? trim( (string) $link['label'] ) : '';
	if ( '' === $label && ! empty( $link['post_id'] ) ) {
		$label = get_the_title( (int) $link['post_id'] );
	}
	return '' !== $label ? $label : $fallback;
}

/**
 * Whether a link value resolves to something usable.
 *
 * @param mixed $link Link value.
 * @return bool
 */
function hk9_link_is_set( $link ): bool {
	return is_array( $link ) && '' !== hk9_theme_link_url( $link );
}

/**
 * Responsive attachment image markup.
 *
 * @param int    $id    Attachment id.
 * @param string $size  Registered size.
 * @param array  $attrs Extra attributes (`class`, `alt`, `sizes`, `loading`, `fetchpriority`, `decoding`…).
 * @param bool   $eager Above the fold: eager loading + high fetch priority.
 * @return string
 */
function hk9_image( int $id, string $size = 'large', array $attrs = [], bool $eager = false ): string {
	if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
		return '';
	}

	$defaults = [
		'decoding' => 'async',
	];

	if ( $eager ) {
		$defaults['loading']       = 'eager';
		$defaults['fetchpriority'] = 'high';
	} else {
		$defaults['loading'] = 'lazy';
	}

	$attrs = array_merge( $defaults, $attrs );

	// Explicit dimensions come from wp_get_attachment_image (width/height attributes).
	$html = wp_get_attachment_image( $id, $size, false, $attrs );

	// The theme passes a hand-measured `sizes` for every image it renders, so the
	// `auto` keyword core (6.7+) prepends to lazy images adds nothing here — and it
	// makes full-page capture tooling that flips `loading` re-select a candidate
	// (the "auto" hint is invalid on eager images and falls back to 100vw).
	if ( ! empty( $attrs['sizes'] ) && is_string( $html ) ) {
		$html = preg_replace( '/\ssizes="auto,\s*/', ' sizes="', $html, 1 );
	}

	// `fetchpriority => 'auto'` opts an eager image out of core's "first eager image gets
	// fetchpriority=high" heuristic without emitting the (default) attribute.
	if ( isset( $attrs['fetchpriority'] ) && 'auto' === $attrs['fetchpriority'] && is_string( $html ) ) {
		$html = str_replace( ' fetchpriority="auto"', '', $html );
	}

	return $html;
}

/**
 * Button markup from a link value.
 *
 * @param array  $link  Link value.
 * @param string $style `primary` | `navy` | `outline` | `outline-light` | `glass` | `ghost` | `ghost-light`.
 * @param array  $attrs Optional: `class`, `size` (`lg`), `full` (bool), `icon` (name appended),
 *                      `icon_size` (px), `label` (override), `text_sm` (bool), plus data-* attributes.
 * @return string
 */
function hk9_button( array $link, string $style = 'primary', array $attrs = [] ): string {
	$url = hk9_theme_link_url( $link );
	if ( '' === $url ) {
		return '';
	}

	$label = isset( $attrs['label'] ) ? (string) $attrs['label'] : hk9_link_label( $link );
	if ( '' === $label ) {
		return '';
	}

	$style_map = [
		'primary'       => [ 'hk9-btn--primary' ],
		'navy'          => [ 'hk9-btn--navy' ],
		'outline'       => [ 'hk9-btn--outline' ],
		'outline-light' => [ 'hk9-btn--outline-light' ],
		'glass'         => [ 'hk9-btn--outline-light', 'hk9-btn--glass' ],
		'ghost'         => [ 'hk9-btn--ghost' ],
		'ghost-light'   => [ 'hk9-btn--ghost-light' ],
	];

	$classes = array_merge( [ 'hk9-btn' ], $style_map[ $style ] ?? $style_map['primary'] );

	if ( ! empty( $attrs['size'] ) && 'lg' === $attrs['size'] ) {
		$classes[] = 'hk9-btn--lg';
	}
	if ( ! empty( $attrs['text_sm'] ) ) {
		$classes[] = 'hk9-btn--text-sm';
	}
	if ( ! empty( $attrs['full'] ) ) {
		$classes[] = 'hk9-btn--full';
	}
	if ( ! empty( $attrs['class'] ) ) {
		$classes = array_merge( $classes, preg_split( '/\s+/', (string) $attrs['class'], -1, PREG_SPLIT_NO_EMPTY ) );
	}

	$extra = '';
	foreach ( $attrs as $key => $value ) {
		if ( str_starts_with( (string) $key, 'data-' ) || 'id' === $key || 'aria-label' === $key ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $value ) );
		}
	}

	// Trailing icon (reference: `<ArrowRight className="w-4 h-4 ml-2" />` after the label).
	$icon = '';
	if ( ! empty( $attrs['icon'] ) ) {
		$icon_class = 'hk9-btn__icon' . ( ! empty( $attrs['icon_size'] ) && 20 === (int) $attrs['icon_size'] ? ' hk9-icon--20' : '' );
		$icon       = hk9_icon( (string) $attrs['icon'], [ 'size' => (int) ( $attrs['icon_size'] ?? 16 ), 'class' => $icon_class ] );
	}

	return sprintf(
		'<a class="%s" href="%s"%s%s>%s%s</a>',
		esc_attr( implode( ' ', array_unique( $classes ) ) ),
		esc_url( $url ),
		hk9_theme_link_attrs( $link ),
		$extra,
		esc_html( $label ),
		$icon
	);
}

/**
 * Open a section wrapper.
 *
 * @param string       $id      Section id (rendered as id="hk9-{id}").
 * @param string|array $classes Extra classes (modifiers).
 * @param array        $attrs   Extra attributes.
 */
function hk9_section_open( string $id, $classes = '', array $attrs = [] ): void {
	$class_list = array_merge( [ 'hk9-section' ], is_array( $classes ) ? $classes : preg_split( '/\s+/', (string) $classes, -1, PREG_SPLIT_NO_EMPTY ) );
	$html       = '';

	foreach ( $attrs as $key => $value ) {
		$html .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $value ) );
	}

	printf(
		'<section id="%s" class="%s"%s>',
		esc_attr( 'hk9-' . sanitize_html_class( $id ) ),
		esc_attr( implode( ' ', array_unique( array_filter( $class_list ) ) ) ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	);
}

/**
 * Close a section wrapper.
 */
function hk9_section_close(): void {
	echo '</section>';
}

/**
 * Hero renderer.
 *
 * @param array  $data    Section data (hero_image or hero_band fields).
 * @param string $variant `image` | `band`.
 */
function hk9_the_hero( array $data, string $variant = 'band' ): void {
	$post_id = get_queried_object_id();
	$heading = isset( $data['heading'] ) ? trim( (string) $data['heading'] ) : '';
	$text    = isset( $data['text'] ) ? trim( (string) $data['text'] ) : '';

	if ( '' === $heading && $post_id ) {
		$heading = get_the_title( $post_id );
	}
	if ( '' === $text && $post_id && has_excerpt( $post_id ) ) {
		$text = wp_strip_all_tags( get_the_excerpt( $post_id ) );
	}

	$heading_html = esc_html( $heading );
	$break_after  = isset( $data['heading_break_after'] ) ? trim( (string) $data['heading_break_after'] ) : '';
	if ( '' !== $break_after && '' !== $heading ) {
		$pos = strpos( $heading, $break_after );
		if ( false !== $pos ) {
			$cut          = $pos + strlen( $break_after );
			// The space stays so the words do not run together when the break is hidden (<768px).
			$heading_html = esc_html( substr( $heading, 0, $cut ) ) . ' <br class="hk9-md-br">' . esc_html( ltrim( substr( $heading, $cut ) ) );
		}
	}

	$eyebrow      = isset( $data['eyebrow'] ) ? trim( (string) $data['eyebrow'] ) : '';
	$eyebrow_icon = isset( $data['eyebrow_icon'] ) ? (string) $data['eyebrow_icon'] : '';

	if ( 'image' === $variant ) {
		$height  = $data['height'] ?? '85vh';
		$height  = in_array( $height, [ '85vh', '70vh', '60vh' ], true ) ? $height : '85vh';
		$overlay = (string) ( $data['overlay'] ?? '60' );
		$overlay = in_array( $overlay, [ '60', '70', '80' ], true ) ? $overlay : '60';
		$focal   = $data['focal'] ?? 'center';
		$focal   = in_array( $focal, [ 'center', 'top', 'bottom', 'left', 'right' ], true ) ? $focal : 'center';

		$classes = [ 'hk9-hero', 'hk9-hero--image', 'hk9-hero--h' . substr( $height, 0, 2 ), 'hk9-hero--overlay-' . $overlay, 'hk9-hero--focal-' . $focal ];
		if ( ! empty( $data['animate'] ) ) {
			$classes[] = 'hk9-hero--animate';
		}

		$image_id = (int) ( $data['image'] ?? 0 );
		if ( $image_id <= 0 && $post_id && has_post_thumbnail( $post_id ) ) {
			$image_id = (int) get_post_thumbnail_id( $post_id );
		}
		$mobile_id = (int) ( $data['image_mobile'] ?? 0 );

		echo '<section class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-labelledby="hk9-hero-title">';
		echo '<div class="hk9-hero__bg">';
		if ( $image_id > 0 ) {
			if ( $mobile_id > 0 && $mobile_id !== $image_id ) {
				$mobile = wp_get_attachment_image_src( $mobile_id, 'hk9-hero' );
				if ( $mobile ) {
					echo '<picture>';
					printf( '<source media="(max-width: 767px)" srcset="%s">', esc_url( $mobile[0] ) );
					echo hk9_image( $image_id, 'hk9-hero', [ 'sizes' => '100vw', 'alt' => '' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
					echo '</picture>';
				}
			} else {
				echo hk9_image( $image_id, 'hk9-hero', [ 'sizes' => '100vw', 'alt' => '' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
			}
		}
		echo '<div class="hk9-hero__overlay"></div>';
		if ( ! empty( $data['gradient'] ) ) {
			echo '<div class="hk9-hero__gradient"></div>';
		}
		echo '</div>';

		echo '<div class="container hk9-hero__content">';
		if ( '' !== $eyebrow ) {
			echo '<div class="hk9-hero__badge">' . hk9_icon( $eyebrow_icon, [ 'size' => 16 ] ) . '<span>' . esc_html( $eyebrow ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hk9_icon() escapes.
		}
		echo '<h1 id="hk9-hero-title" class="hk9-hero__title">' . $heading_html . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		if ( '' !== $text ) {
			echo '<p class="hk9-hero__text">' . esc_html( $text ) . '</p>';
		}

		$buttons = is_array( $data['buttons'] ?? null ) ? array_slice( $data['buttons'], 0, 2 ) : [];
		$rendered = [];
		foreach ( $buttons as $button ) {
			if ( ! is_array( $button ) || ! isset( $button['link'] ) || ! is_array( $button['link'] ) ) {
				continue;
			}
			$style      = ( $button['style'] ?? 'primary' ) === 'outline-light' ? 'glass' : 'primary';
			$rendered[] = hk9_button( $button['link'], $style, [ 'size' => 'lg' ] );
		}
		$rendered = array_filter( $rendered );
		if ( ! empty( $rendered ) ) {
			echo '<div class="hk9-hero__actions">' . implode( '', $rendered ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hk9_button() escapes.
		}
		echo '</div>';
		echo '</section>';
		return;
	}

	// Band variant.
	$pattern = $data['pattern'] ?? 'none';
	$pattern = in_array( $pattern, [ 'none', 'stars', 'grid' ], true ) ? $pattern : 'none';
	$classes = [ 'hk9-hero', 'hk9-hero--band' ];
	if ( 'none' !== $pattern ) {
		$classes[] = 'hk9-pattern';
		$classes[] = 'hk9-pattern--' . $pattern;
	}

	echo '<section class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-labelledby="hk9-hero-title">';
	echo '<div class="hk9-hero__content">';
	if ( '' !== $eyebrow ) {
		echo '<div><span class="hk9-hero__eyebrow">' . esc_html( $eyebrow ) . '</span></div>';
	}
	echo '<h1 id="hk9-hero-title" class="hk9-hero__title">' . $heading_html . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	if ( '' !== $text ) {
		echo '<p class="hk9-hero__text hk9-copy">' . esc_html( $text ) . '</p>';
	}
	echo '</div>';
	echo '</section>';
}

/**
 * Posts pagination with icons and ARIA labels.
 */
function hk9_pagination(): void {
	$prev = hk9_icon( 'arrow-right', [ 'class' => 'hk9-icon--flip', 'size' => 16 ] );
	$next = hk9_icon( 'arrow-right', [ 'size' => 16 ] );

	the_posts_pagination(
		[
			'mid_size'           => 1,
			'prev_text'          => $prev . '<span class="screen-reader-text">' . esc_html__( 'Previous page', 'heartland-k9s' ) . '</span>',
			'next_text'          => '<span class="screen-reader-text">' . esc_html__( 'Next page', 'heartland-k9s' ) . '</span>' . $next,
			'screen_reader_text' => esc_html__( 'Posts navigation', 'heartland-k9s' ),
			'aria_label'         => esc_attr__( 'Posts', 'heartland-k9s' ),
			'class'              => 'hk9-blog__pagination',
		]
	);
}

/**
 * Blog post card.
 *
 * @param WP_Post|int|null $post Post.
 */
function hk9_post_card( $post = null ): void {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$show_image = (bool) hk9_theme_option( 'blog.show_featured_image' );
	$show_date  = (bool) hk9_theme_option( 'blog.show_date' );
	$show_cats  = (bool) hk9_theme_option( 'blog.show_categories' );
	$show_author = (bool) hk9_theme_option( 'blog.show_author' );

	echo '<article class="hk9-blog__card" id="post-' . esc_attr( (string) $post->ID ) . '">';

	if ( $show_image && has_post_thumbnail( $post ) ) {
		echo '<a class="hk9-blog__card-media" href="' . esc_url( get_permalink( $post ) ) . '" tabindex="-1" aria-hidden="true">';
		echo hk9_image( (int) get_post_thumbnail_id( $post ), 'hk9-card', [ 'sizes' => '(max-width: 767px) calc(100vw - 32px), 400px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
		echo '</a>';
	}

	echo '<div class="hk9-blog__card-body">';

	$meta = [];
	if ( $show_date ) {
		$meta[] = '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
	}
	if ( $show_author ) {
		$meta[] = '<span>' . esc_html( get_the_author_meta( 'display_name', (int) $post->post_author ) ) . '</span>';
	}
	if ( $show_cats ) {
		$cats = get_the_category_list( ', ', '', $post->ID );
		if ( '' !== $cats ) {
			$meta[] = '<span>' . $cats . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
		}
	}
	if ( ! empty( $meta ) ) {
		echo '<div class="hk9-blog__meta">' . implode( '', $meta ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	echo '<h2 class="hk9-blog__card-title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2>';
	echo '<p class="hk9-blog__excerpt">' . esc_html( wp_strip_all_tags( get_the_excerpt( $post ) ) ) . '</p>';
	echo '<a class="hk9-link" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Read more', 'heartland-k9s' ) . hk9_icon( 'arrow-right', [ 'size' => 16 ] ) . '<span class="screen-reader-text">' . esc_html( sprintf( /* translators: %s: post title */ __( ': %s', 'heartland-k9s' ), get_the_title( $post ) ) ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hk9_icon() escapes.
	echo '</div>';
	echo '</article>';
}

/**
 * Logo <img> for the header or footer.
 *
 * Order: settings image (branding.header_logo / footer_logo) → core custom logo → ''.
 *
 * @param string $context `header` | `footer`.
 * @return string
 */
function hk9_logo_img( string $context = 'header' ): string {
	$setting = 'footer' === $context ? 'branding.footer_logo' : 'branding.header_logo';
	$id      = (int) hk9_theme_option( $setting, 0 );

	if ( $id <= 0 && 'footer' === $context ) {
		$id = (int) hk9_theme_option( 'branding.header_logo', 0 );
	}
	if ( $id <= 0 ) {
		$id = (int) get_theme_mod( 'custom_logo', 0 );
	}
	if ( $id <= 0 ) {
		return '';
	}

	$class = 'footer' === $context ? 'hk9-footer__logo' : 'hk9-header__logo';
	$alt   = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	if ( '' === $alt ) {
		$alt = get_bloginfo( 'name' );
	}

	// The logo renders at a fixed CSS height (branding.*_logo_height, same clamps as
	// hk9_root_css()); `sizes` is its rendered WIDTH so the browser picks the
	// smallest srcset candidate that covers it (the 140×160 `hk9-logo-sm` on 1×–2×
	// screens instead of the 263×300 medium file).
	$height = 'footer' === $context
		? max( 24, min( 160, (int) hk9_theme_option( 'branding.footer_logo_height' ) ?: 80 ) )
		: max( 24, min( 80, (int) hk9_theme_option( 'branding.header_logo_height' ) ?: 64 ) );
	$meta   = wp_get_attachment_metadata( $id );
	$ratio  = is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ? (int) $meta['width'] / (int) $meta['height'] : 1;
	$width  = max( 1, (int) round( $height * $ratio ) );

	$size = hk9_ensure_image_size( $id, 'hk9-logo-sm' ) ? 'hk9-logo-sm' : 'hk9-logo';

	// Header logo: eager (above the fold) but without fetchpriority=high — that is reserved
	// for the hero / LCP image; the footer logo lazy-loads.
	$attrs = [ 'class' => $class, 'alt' => $alt, 'sizes' => $width . 'px' ];
	if ( 'header' === $context ) {
		$attrs['loading']       = 'eager';
		$attrs['fetchpriority'] = 'auto';
	}

	return hk9_image( $id, $size, $attrs );
}

/**
 * Two-line wordmark markup.
 *
 * @param string $context `header` | `footer`.
 * @return string
 */
function hk9_wordmark( string $context = 'header' ): string {
	if ( ! hk9_theme_option( 'branding.show_wordmark' ) ) {
		return '';
	}
	$line1  = (string) hk9_theme_option( 'branding.wordmark_line1' );
	$line2  = (string) hk9_theme_option( 'branding.wordmark_line2' );
	$prefix = 'footer' === $context ? 'hk9-footer' : 'hk9-header';

	if ( '' === $line1 && '' === $line2 ) {
		return '';
	}

	$html = '<span class="' . esc_attr( $prefix . '__wordmark' ) . '">';
	if ( '' !== $line1 ) {
		$html .= '<span class="' . esc_attr( $prefix . '__wordmark-line1' ) . '">' . esc_html( $line1 ) . '</span>';
	}
	if ( '' !== $line2 ) {
		$html .= '<span class="' . esc_attr( $prefix . '__wordmark-line2' ) . '">' . esc_html( $line2 ) . '</span>';
	}
	return $html . '</span>';
}

/**
 * Convert a plain-text field with line breaks into paragraphs (escaped).
 *
 * @param string $text  Textarea value.
 * @param string $class Paragraph class.
 * @return string
 */
function hk9_paragraphs( string $text, string $class = '' ): string {
	$text = trim( $text );
	if ( '' === $text ) {
		return '';
	}
	$class_attr = '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '';
	$paragraphs = preg_split( '/\n\s*\n/', str_replace( "\r", '', $text ) );
	$html       = '';
	foreach ( $paragraphs as $p ) {
		$p = trim( $p );
		if ( '' === $p ) {
			continue;
		}
		$html .= '<p' . $class_attr . '>' . nl2br( esc_html( $p ) ) . '</p>';
	}
	return $html;
}
