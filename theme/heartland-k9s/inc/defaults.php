<?php
/**
 * Theme-side setting defaults.
 *
 * Mirrors docs/ARCHITECTURE.md §7 (the plugin's Settings\Schema) so the theme
 * renders correct branding/contact/footer data even when the companion plugin
 * is not active. Keys use the same dot notation as hk9_option('group.key').
 *
 * Only reference-verified values are filled in; everything else is empty.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a link value in the field-framework shape.
 *
 * @param string $label  Link label.
 * @param string $url    URL (absolute or site path).
 * @param string $target `_self` | `_blank`.
 * @return array{label:string,url:string,post_id:int,target:string,rel:string}
 */
function hk9_theme_link( string $label, string $url = '', string $target = '_self' ): array {
	return [
		'label'   => $label,
		'url'     => $url,
		'post_id' => 0,
		'target'  => '_blank' === $target ? '_blank' : '_self',
		'rel'     => '_blank' === $target ? 'noopener noreferrer' : '',
	];
}

/**
 * The full default settings tree (group => key => value).
 *
 * @return array<string, array<string, mixed>>
 */
function hk9_theme_defaults(): array {
	static $defaults = null;

	if ( null !== $defaults ) {
		return $defaults;
	}

	$home = home_url( '/' );

	$defaults = [
		'branding'  => [
			'header_logo'        => 0,
			'footer_logo'        => 0,
			'header_logo_height' => 64,
			'footer_logo_height' => 80,
			'wordmark_line1'     => 'Heartland K9s',
			'wordmark_line2'     => 'For Veterans',
			'show_wordmark'      => true,
		],
		'colors'    => [
			'primary'          => '#1c2f4a',
			'secondary'        => '#b82e45',
			'background'       => '#fbfaf9',
			'foreground'       => '#151c28',
			'muted'            => '#f4f0ec',
			'muted_foreground' => '#52637a',
			'border'           => '#e5e0dc',
			'accent'           => '#eae2d7',
		],
		'fonts'     => [
			'serif' => 'fraunces',
			'sans'  => 'inter',
		],
		'contact'   => [
			'phone_main'            => '800-913-6189',
			'phone_main_label'      => 'Main',
			'phone_secondary'       => '417-312-7484',
			'phone_secondary_label' => 'Director cell',
			'email'                 => 'info@heartlandk9s.org',
			'email_director'        => 'director@heartlandk9s.org',
			'email_development'     => 'development@heartlandk9s.org',
			'address_line1'         => '12651 Gateway Dr',
			'address_line2'         => '',
			'city'                  => 'Neosho',
			'state'                 => 'MO',
			'zip'                   => '64850',
			'hours'                 => '8:00am – 5:00pm',
			'hours_days'            => '',
			'service_area'          => '',
			'facebook'              => '',
			'instagram'             => '',
			'youtube'               => '',
			'linkedin'              => '',
			'x'                     => '',
			'candid_url'            => '',
			'show_guidestar_seal'   => true,
			'ein'                   => '47-4991572',
			'legal_name'            => 'Heartland Canines for Veterans Inc',
			'tax_statement'         => '',
		],
		'links'     => [
			'donate'                 => hk9_theme_link( 'Donate', $home . 'donate/' ),
			'donate_external'        => hk9_theme_link( 'Donate', '', '_blank' ),
			'paypal_hosted_button_id' => '',
			'amazon_wishlist'        => hk9_theme_link( 'Amazon Wishlist', '', '_blank' ),
			'application'            => hk9_theme_link( 'Apply', $home . 'online-application/' ),
			'five_questions'         => hk9_theme_link( '5 Questions', $home . '5-questions/' ),
			'ada'                    => hk9_theme_link( 'Service Dogs and the ADA', $home . 'service-dogs-and-the-ada/' ),
			'volunteer'              => hk9_theme_link( 'Volunteer', $home . 'volunteer/' ),
			'provider'               => hk9_theme_link( 'The Service K9 Program', $home . 'the-service-k9-program/' ),
			'campaigns'              => hk9_theme_link( 'Campaigns', $home . 'campaigns/' ),
			'events'                 => hk9_theme_link( 'Events', $home . 'events/' ),
			'stories'                => hk9_theme_link( 'Stories', $home . 'stories/' ),
			'contact'                => hk9_theme_link( 'Contact', $home . 'contact/' ),
			'gear'                   => hk9_theme_link( 'Heartland Gear', $home . 'heartland-gear/' ),
			'coloring_book'          => hk9_theme_link( 'The HK9 Coloring Book', $home . 'the-hk9-coloring-book/' ),
			'obedience'              => hk9_theme_link( 'Obedience Training', $home . 'heartland-obedience-training-2/' ),
			'teams'                  => hk9_theme_link( 'Teams in Training', $home . 'hk9-current-teams-in-training/' ),
			'people'                 => hk9_theme_link( 'Meet the Team', $home . 'meet-the-team/' ),
			'photos'                 => hk9_theme_link( 'Photos', $home . 'photos/' ),
			'barkode'                => hk9_theme_link( 'BarKode', $home . 'barkode/' ),
		],
		'header'    => [
			'cta_label'           => 'Donate Now',
			'cta_link'            => hk9_theme_link( 'Donate Now', $home . 'donate/' ),
			'show_cta'            => true,
			'sticky'              => true,
			'divider_before_last' => true,
		],
		'footer'    => [
			'description'  => 'An IRS-recognized 501(c)(3) nonprofit pairing eligible disabled U.S. veterans with highly trained service dogs at zero cost.',
			'tagline'      => 'So They Never Walk Alone.',
			'col2_heading' => 'Quick Links',
			'col3_heading' => 'Get Involved',
			'col4_heading' => 'Contact Us',
			'copyright'    => '© {year} Heartland Canines for Veterans. All rights reserved.',
			'credit'       => 'Built with ♥ for our veterans.',
			'show_seal'    => true,
		],
		'blog'      => [
			'layout'              => 'list',
			'hero_title'          => 'News',
			'hero_text'           => '',
			'hero_image'          => 0,
			'show_author'         => false,
			'show_date'           => true,
			'show_categories'     => true,
			'show_tags'           => true,
			'show_related'        => true,
			'related_count'       => 3,
			'show_featured_image' => true,
		],
		'forms'     => [
			'contact_recipients'       => '',
			'application_recipients'   => '',
			'from_name'                => '',
			'from_email'               => '',
			'subjects'                 => [],
			'rate_limit'               => 5,
			'retention_days'           => 90,
			'store_submissions'        => true,
			'contact_success_text'     => 'Thank you for reaching out. We will get back to you shortly.',
			'application_success_page' => hk9_theme_link( 'Thank you', $home . 'thank-you/' ),
		],
		'analytics' => [
			'fathom_site_id' => '',
		],
		'advanced'  => [
			'editors_manage_registry' => false,
			'purge_on_uninstall'      => false,
			'disable_user_enumeration' => true,
			'output_seo_meta'         => true,
		],
	];

	/**
	 * Filter the theme-side defaults (used only when the plugin does not supply a value).
	 *
	 * @param array $defaults Defaults tree.
	 */
	$defaults = apply_filters( 'hk9/theme/defaults', $defaults );

	return $defaults;
}

/**
 * Read one default by dot-notation key.
 *
 * @param string $key      e.g. `contact.phone_main`.
 * @param mixed  $fallback Returned when the key is unknown.
 * @return mixed
 */
function hk9_theme_default( string $key, $fallback = null ) {
	$tree = hk9_theme_defaults();
	$node = $tree;

	foreach ( explode( '.', $key ) as $segment ) {
		if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
			return $fallback;
		}
		$node = $node[ $segment ];
	}

	return $node;
}
