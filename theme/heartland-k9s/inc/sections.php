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
 * Templates whose block content already has a fixed slot (landing / thank-you:
 * card after the hero; application: intro card before the form; gallery: the
 * gallery itself; default page.php). The "Editor content" position setting of
 * the Page sections panel does not apply to them.
 *
 * @param string $template Template slug.
 * @return bool
 */
function hk9_template_has_native_content( string $template ): bool {
	$native = [ 'default', 'landing', 'application', 'thank-you', 'gallery' ];

	/**
	 * Filter the templates that render block content in a fixed place.
	 *
	 * @param string[] $native   Template slugs.
	 * @param string   $template Template being rendered.
	 */
	$native = (array) apply_filters( 'hk9/theme/native_content_templates', $native, $template );

	return in_array( $template, $native, true );
}

/**
 * Where a section template shows the page's block content (editor canvas):
 * 'after' (after the sections, default), 'before' (right after the hero) or
 * 'hide'. Read from `hk9_sections_layout.content_position` (preview-aware via
 * get_post_meta()). Returns '' when the template has a native content slot or
 * the page has no block content (hk9_content_is_blank(): empty paragraph
 * blocks count as no content).
 *
 * @param int    $post_id  Page id.
 * @param string $template Template slug.
 * @return string 'after' | 'before' | 'hide' | ''.
 */
function hk9_editor_content_position( int $post_id, string $template ): string {
	if ( $post_id <= 0 || hk9_template_has_native_content( $template ) ) {
		return '';
	}
	if ( hk9_content_is_blank( $post_id ) ) {
		return '';
	}
	$position = 'after';
	$meta     = get_post_meta( $post_id, 'hk9_sections_layout', true );
	if ( is_array( $meta ) && isset( $meta['content_position'] ) && is_string( $meta['content_position'] ) ) {
		$position = sanitize_key( $meta['content_position'] );
	}
	if ( ! in_array( $position, [ 'after', 'before', 'hide' ], true ) ) {
		$position = 'after';
	}

	/**
	 * Filter the editor-content position of a sections page.
	 *
	 * @param string $position 'after' | 'before' | 'hide'.
	 * @param int    $post_id  Page id.
	 * @param string $template Template slug.
	 */
	return (string) apply_filters( 'hk9/theme/editor_content_position', $position, $post_id, $template );
}

/**
 * Render the page's block content on a sections template.
 *
 * 'before' (after the hero): the overlap card used by landing pages.
 * 'after' (after the sections): the same card inside a padded section so it
 * does not overlap the section above it. Nothing is printed for 'hide' or
 * when the content is blank (hk9_content_is_blank()).
 *
 * @param int    $post_id  Page id.
 * @param string $position 'after' | 'before' | 'hide'.
 */
function hk9_the_editor_content( int $post_id, string $position ): void {
	if ( 'before' !== $position && 'after' !== $position ) {
		return;
	}
	if ( hk9_content_is_blank( $post_id ) ) {
		return;
	}
	if ( 'before' === $position ) {
		echo '<div class="hk9-editor-content hk9-editor-content--overlap">';
		hk9_the_content_card( $post_id );
		echo '</div>';
		return;
	}
	echo '<section id="hk9-editor-content" class="hk9-section hk9-editor-content hk9-editor-content--flow"><div class="hk9-gutter"><div class="hk9-overlap__card hk9-editor-content__card hk9-prose">';
	the_content();
	echo '</div></div></section>';
}

/**
 * Render every visible section of a page through template-parts/sections/<type>.php,
 * with the page's block content before/after them per hk9_editor_content_position().
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

	$content_position = hk9_editor_content_position( $post_id, $template );
	$content_pending  = in_array( $content_position, [ 'before', 'after' ], true );

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
		if ( $content_pending && 'before' === $content_position && in_array( $section['type'], [ 'hero_band', 'hero_image' ], true ) ) {
			hk9_the_editor_content( $post_id, 'before' );
			$content_pending = false;
		}
	}

	if ( $content_pending ) {
		// 'after', or 'before' on a page without a hero: the content follows the sections.
		hk9_the_editor_content( $post_id, $content_position );
	}
}

/**
 * Block content card used by page.php / landing after the hero band. Nothing
 * is printed when the content is blank (hk9_content_is_blank()).
 *
 * @param int  $post_id Page id.
 * @param bool $wrap    Wrap in the overlap card.
 */
function hk9_the_content_card( int $post_id, bool $wrap = true ): void {
	if ( hk9_content_is_blank( $post_id ) ) {
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

/* -------------------------------------------------------------------------
 * Form providers (plugin-less fallbacks; the plugin's Support\FormProviders
 * declares the same functions first when it is active)
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hk9_sanitize_form_shortcode' ) ) {
	/**
	 * Keep only `[shortcode …]` tags from a string (text, HTML and nested brackets dropped).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function hk9_sanitize_form_shortcode( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value || ! preg_match_all( '/\[\/?[a-zA-Z0-9_-]+(?:\s[^\[\]<>]*)?\]/', $value, $m ) ) {
			return '';
		}
		return implode( ' ', array_map( 'trim', $m[0] ) );
	}
}

if ( ! function_exists( 'hk9_gravity_forms_active' ) ) {
	/**
	 * Whether Gravity Forms is active.
	 *
	 * @return bool
	 */
	function hk9_gravity_forms_active(): bool {
		return class_exists( 'GFAPI' ) && function_exists( 'gravity_form' );
	}
}

if ( ! function_exists( 'hk9_form_provider' ) ) {
	/**
	 * Effective provider of a form section: the section's `provider`
	 * (inherit → Settings → Forms default), Gravity form id and shortcode, plus
	 * whether it can render right now (`available`) and an editor-facing note
	 * when it cannot (the built-in form is shown instead).
	 *
	 * @param array  $section Section data.
	 * @param string $role    'contact' | 'application'.
	 * @return array {provider, role, gravity_form_id, shortcode, source, available, notice}
	 */
	function hk9_form_provider( array $section, string $role = 'contact' ): array {
		$role     = in_array( $role, [ 'contact', 'application' ], true ) ? $role : 'contact';
		$provider = isset( $section['provider'] ) && is_scalar( $section['provider'] ) ? sanitize_key( (string) $section['provider'] ) : 'inherit';
		$source   = 'section';
		if ( ! in_array( $provider, [ 'builtin', 'gravity', 'shortcode' ], true ) ) {
			$provider = sanitize_key( (string) hk9_theme_option( 'forms.provider', 'builtin' ) );
			$provider = in_array( $provider, [ 'builtin', 'gravity', 'shortcode' ], true ) ? $provider : 'builtin';
			$source   = 'settings';
		}
		$form_id = isset( $section['gravity_form_id'] ) && is_scalar( $section['gravity_form_id'] ) ? (int) $section['gravity_form_id'] : 0;
		if ( $form_id <= 0 ) {
			$form_id = max( 0, (int) hk9_theme_option( 'forms.gravity_' . $role . '_form', 0 ) );
		}
		$shortcode = hk9_sanitize_form_shortcode( $section['shortcode'] ?? '' );
		$resolved  = [
			'provider'        => $provider,
			'role'            => $role,
			'gravity_form_id' => $form_id,
			'shortcode'       => $shortcode,
			'source'          => $source,
			'available'       => true,
			'notice'          => '',
		];
		if ( 'gravity' === $provider ) {
			if ( ! hk9_gravity_forms_active() ) {
				$resolved['available'] = false;
				$resolved['notice']    = __( 'This form is set to Gravity Forms, but Gravity Forms is not active. The built-in form is shown instead.', 'heartland-k9s' );
			} elseif ( $form_id <= 0 ) {
				$resolved['available'] = false;
				$resolved['notice']    = __( 'This form is set to Gravity Forms, but no form is selected. The built-in form is shown instead.', 'heartland-k9s' );
			} else {
				$form = GFAPI::get_form( $form_id );
				if ( ! is_array( $form ) || empty( $form['is_active'] ) || ! empty( $form['is_trash'] ) ) {
					$resolved['available'] = false;
					/* translators: %d: form id */
					$resolved['notice'] = sprintf( __( 'This form is set to Gravity Forms form #%d, which does not exist or is inactive. The built-in form is shown instead.', 'heartland-k9s' ), $form_id );
				}
			}
		} elseif ( 'shortcode' === $provider ) {
			$tag = preg_match( '/^\[([a-zA-Z0-9_-]+)/', $shortcode, $m ) ? $m[1] : '';
			if ( '' === $tag ) {
				$resolved['available'] = false;
				$resolved['notice']    = __( 'This form is set to a shortcode, but the shortcode field is empty. The built-in form is shown instead.', 'heartland-k9s' );
			} elseif ( ! shortcode_exists( $tag ) ) {
				$resolved['available'] = false;
				/* translators: %s: shortcode tag */
				$resolved['notice'] = sprintf( __( 'This form is set to the [%s] shortcode, but no active plugin provides it. The built-in form is shown instead.', 'heartland-k9s' ), $tag );
			}
		}
		/** This filter is documented in the plugin (Support/FormProviders.php). */
		return (array) apply_filters( 'hk9/forms/provider', $resolved, $section, $role );
	}
}

if ( ! function_exists( 'hk9_render_form_provider' ) ) {
	/**
	 * Markup of an external provider ('' for builtin / unavailable).
	 *
	 * @param array $resolved Value from hk9_form_provider().
	 * @param array $args     Optional `id`, `class`.
	 * @return string
	 */
	function hk9_render_form_provider( array $resolved, array $args = [] ): string {
		if ( empty( $resolved['available'] ) ) {
			return '';
		}
		$provider = (string) ( $resolved['provider'] ?? 'builtin' );
		$inner    = '';
		$class    = 'hk9-form-provider hk9-form-provider--' . sanitize_html_class( $provider );
		if ( 'gravity' === $provider && function_exists( 'gravity_form' ) ) {
			$html   = gravity_form( (int) ( $resolved['gravity_form_id'] ?? 0 ), false, false, false, null, true, 0, false );
			$inner  = is_string( $html ) ? $html : '';
			$class .= ' hk9-gf';
		} elseif ( 'shortcode' === $provider ) {
			$inner  = (string) do_shortcode( hk9_sanitize_form_shortcode( $resolved['shortcode'] ?? '' ) );
			$class .= ' hk9-shortcode-form';
		}
		if ( '' === trim( $inner ) ) {
			return '';
		}
		if ( ! empty( $args['class'] ) ) {
			$class .= ' ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $args['class'] ) ?: [] ) );
		}
		$id = ! empty( $args['id'] ) ? ' id="' . esc_attr( sanitize_html_class( (string) $args['id'] ) ) . '"' : '';
		return '<div class="' . esc_attr( $class ) . '"' . $id . ' data-hk9-form-provider="' . esc_attr( $provider ) . '">' . $inner . '</div>';
	}
}

if ( ! function_exists( 'hk9_form_provider_notice' ) ) {
	/**
	 * Editor-only note for a provider that fell back to the built-in form ('' for visitors).
	 *
	 * @param array $resolved Value from hk9_form_provider().
	 * @return string
	 */
	function hk9_form_provider_notice( array $resolved ): string {
		$notice = (string) ( $resolved['notice'] ?? '' );
		if ( '' === $notice || ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			return '';
		}
		return '<p class="hk9-notice hk9-form-provider__notice" role="note">' . esc_html( $notice ) . ' <span class="hk9-form-provider__who">' . esc_html__( '(Only editors see this note.)', 'heartland-k9s' ) . '</span></p>';
	}
}

if ( ! function_exists( 'hk9_form_provider_no_output' ) ) {
	/**
	 * Flags an available external provider whose markup came back empty
	 * (hk9_render_form_provider() returned '') as unavailable, with an editor
	 * note, so the template falls back to the built-in form like for any other
	 * unavailable provider. No-op for builtin / already-unavailable providers.
	 *
	 * @param array $resolved Value from hk9_form_provider().
	 * @return array Same shape as hk9_form_provider().
	 */
	function hk9_form_provider_no_output( array $resolved ): array {
		$provider = (string) ( $resolved['provider'] ?? 'builtin' );
		if ( empty( $resolved['available'] ) || ! in_array( $provider, [ 'gravity', 'shortcode' ], true ) ) {
			return $resolved;
		}
		$resolved['available'] = false;
		if ( 'gravity' === $provider ) {
			/* translators: %d: form id */
			$resolved['notice'] = sprintf( __( 'Gravity Forms form #%d produced no output (another plugin or a customization may be suppressing it). The built-in form is shown instead.', 'heartland-k9s' ), (int) ( $resolved['gravity_form_id'] ?? 0 ) );
		} else {
			$tag = preg_match( '/^\[([a-zA-Z0-9_-]+)/', trim( (string) ( $resolved['shortcode'] ?? '' ) ), $m ) ? $m[1] : '';
			/* translators: %s: shortcode tag */
			$resolved['notice'] = sprintf( __( 'The [%s] shortcode produced no output. The built-in form is shown instead.', 'heartland-k9s' ), $tag );
		}
		return $resolved;
	}
}
