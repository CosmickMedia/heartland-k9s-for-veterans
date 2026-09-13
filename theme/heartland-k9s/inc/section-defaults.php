<?php
/**
 * Reference section defaults used when the companion plugin is not active.
 *
 * The plugin's Sections\Registry is the source of truth (docs/ARCHITECTURE.md §5);
 * this file lets the theme render the Home template with the reference content
 * (discovery/ref/reference-content.json) when the plugin is missing, and maps
 * section ids to their template-part type.
 *
 * The reference testimonial is fabricated, so the `testimonial` section ships with
 * empty fields and is hidden by default here.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * "Support a Service Dog" destination: the external donation link when configured,
 * otherwise the Donate page.
 *
 * @return array Link value.
 */
function hk9_theme_support_link(): array {
	$external = hk9_theme_option( 'links.donate_external' );
	$label    = 'Support a Service Dog';

	if ( is_array( $external ) && ! empty( $external['url'] ) ) {
		return array_merge( $external, [ 'label' => $label ] );
	}

	$donate = hk9_theme_option( 'links.donate' );
	if ( is_array( $donate ) && ( ! empty( $donate['url'] ) || ! empty( $donate['post_id'] ) ) ) {
		return array_merge( $donate, [ 'label' => $label ] );
	}

	return hk9_theme_link( $label, home_url( '/donate/' ) );
}

/**
 * Section definitions per template: id => [ type, hidden, data ].
 *
 * @param string $template Template slug (`home`, `about`, ... `default`).
 * @return array<string, array{type:string,hidden:bool,data:array}>
 */
function hk9_theme_section_defaults( string $template ): array {
	static $cache = [];

	if ( isset( $cache[ $template ] ) ) {
		return $cache[ $template ];
	}

	$home = home_url( '/' );
	$link = static fn( string $label, string $path, string $target = '_self' ): array => hk9_theme_link( $label, $path, $target );

	$hero_band = [
		'type'   => 'hero_band',
		'hidden' => false,
		'data'   => [
			'eyebrow' => '',
			'heading' => '',
			'text'    => '',
			'pattern' => 'none',
		],
	];

	$cta_band = static fn( string $heading = '', string $text = '', array $buttons = [], string $tone = 'navy' ): array => [
		'type'   => 'cta_band',
		'hidden' => false,
		'data'   => [
			'heading' => $heading,
			'text'    => $text,
			'buttons' => $buttons,
			'tone'    => $tone,
		],
	];

	switch ( $template ) {
		case 'home':
			$sections = [
				'hero_image'      => [
					'type'   => 'hero_image',
					'hidden' => false,
					'data'   => [
						'eyebrow'             => 'IRS-Recognized 501(c)(3) Nonprofit',
						'eyebrow_icon'        => 'shield-check',
						'heading'             => 'So They Never Walk Alone.',
						'heading_break_after' => 'Never',
						'text'                => 'Pairing eligible disabled U.S. veterans with highly trained service dogs at zero cost. Restoring independence, dignity, and purpose.',
						'image'               => 0,
						'image_mobile'        => 0,
						'focal'               => 'center',
						'height'              => '85vh',
						'overlay'             => '60',
						'gradient'            => true,
						'buttons'             => [
							[
								'link'  => hk9_theme_support_link(),
								'style' => 'primary',
							],
							[
								'link'  => $link( 'Apply for a Dog', $home . 'veterans/' ),
								'style' => 'outline-light',
							],
						],
						'animate'             => true,
					],
				],
				'mission'         => [
					'type'   => 'mission',
					'hidden' => false,
					'data'   => [
						'heading' => 'We believe those who served our nation deserve the highest level of care and support upon their return.',
						'divider' => true,
						'text'    => 'At Heartland Canines for Veterans, our mission is simple yet profound: to place exceptional service dogs with disabled veterans at no financial burden to the veteran. Through empirical strategies, rigorous training, and a deep understanding of our veterans\' needs, we forge partnerships that save lives.',
					],
				],
				'features'        => [
					'type'   => 'feature_cards',
					'hidden' => false,
					'data'   => [
						'heading' => '',
						'intro'   => '',
						'divider' => false,
						'align'   => 'center',
						'columns' => '3',
						'cards'   => [
							[
								'icon'     => 'shield-check',
								'tone'     => 'navy',
								'title'    => 'Zero Cost to Veterans',
								'text'     => 'We believe a veteran has already paid the price. Every service dog, complete with training and equipment, is provided entirely free of charge.',
								'link'     => $link( 'Learn about funding', $home . 'program/' ),
								'decorate' => false,
							],
							[
								'icon'     => 'heart-handshake',
								'tone'     => 'crimson',
								'title'    => 'Unbreakable Bond',
								'text'     => 'We don\'t just hand over a leash. We ensure the right canine is paired with the right person, fostering a bond built on trust and mutual support.',
								'link'     => $link( 'Our matching process', $home . 'program/' ),
								'decorate' => true,
							],
							[
								'icon'     => 'map-pin',
								'tone'     => 'navy',
								'title'    => 'Nationwide Reach',
								'text'     => 'While our roots are in the heartland, our impact spans the country. We serve eligible disabled veterans wherever they call home.',
								'link'     => $link( 'Read our story', $home . 'about/' ),
								'decorate' => false,
							],
						],
					],
				],
				'barkode_feature' => [
					'type'   => 'barkode_feature',
					'hidden' => false,
					'data'   => [
						'eyebrow'       => 'Proprietary System',
						'heading'       => 'The BarKode Program',
						'text'          => 'Every Heartland K9 is equipped with a specialized BarKode patch. This vital tool serves as a certification beacon and emergency contact system, ensuring the safety of the veteran and their dog in critical situations.',
						'image'         => 0,
						'image_caption' => 'Dedicated to Derron & Rosie',
						'button'        => $link( 'Explore BarKode', $home . 'barkode/' ),
						'pattern'       => true,
					],
				],
				'testimonial'     => [
					'type'   => 'testimonial',
					'hidden' => true, // The reference quote is fabricated; nothing ships by default.
					'data'   => [
						'source' => 'manual',
						'story'  => 0,
						'quote'  => '',
						'name'   => '',
						'meta'   => '',
						'image'  => 0,
						'button' => $link( 'Read More Stories', $home . 'stories/' ),
					],
				],
			];
			break;

		case 'landing':
			$sections = [
				'hero_band' => $hero_band,
				'cards'     => [ 'type' => 'feature_cards', 'hidden' => true, 'data' => [ 'heading' => '', 'intro' => '', 'divider' => false, 'align' => 'center', 'columns' => '3', 'cards' => [] ] ],
				'faq'       => [ 'type' => 'faq', 'hidden' => true, 'data' => [ 'heading' => '', 'intro' => '', 'items' => [], 'source_note' => '' ] ],
				'cta'       => array_merge( $cta_band(), [ 'hidden' => true ] ),
			];
			break;

		default:
			// page.php: hero band (can be unticked) + optional CTA band (hidden by default).
			$sections = [
				'hero_band' => $hero_band,
				'cta'       => array_merge( $cta_band(), [ 'hidden' => true ] ),
			];
			break;
	}

	/**
	 * Filter the theme-side fallback section definitions.
	 *
	 * @param array  $sections id => [type, hidden, data].
	 * @param string $template Template slug.
	 */
	$cache[ $template ] = apply_filters( 'hk9/theme/section_defaults', $sections, $template );

	return $cache[ $template ];
}

/**
 * Map a section id to its template-part type for the given template.
 *
 * Shared ids (`values`, `ways`, `protects`, `cards` → feature_cards; `cta`, `ada` →
 * cta_band; `featured` → testimonial) follow docs/ARCHITECTURE.md §5.2.
 *
 * @param string $template Template slug.
 * @param string $id       Section id.
 * @return string
 */
function hk9_theme_section_type( string $template, string $id ): string {
	$defaults = hk9_theme_section_defaults( $template );
	if ( isset( $defaults[ $id ]['type'] ) ) {
		return $defaults[ $id ]['type'];
	}

	$shared = [
		'features' => 'feature_cards',
		'values'   => 'feature_cards',
		'ways'     => 'feature_cards',
		'protects' => 'feature_cards',
		'cards'    => 'feature_cards',
		'cta'      => 'cta_band',
		'ada'      => 'cta_band',
		'featured' => 'testimonial',
	];

	return $shared[ $id ] ?? $id;
}
