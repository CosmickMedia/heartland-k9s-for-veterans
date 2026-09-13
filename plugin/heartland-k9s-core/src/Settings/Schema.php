<?php
/**
 * Settings schema: every hk9_settings key, its type, default and admin label.
 *
 * Defaults are pure data (no translation calls) so they can be read on any
 * request; labels/help are built inside fields() and must only be requested
 * from admin hooks (admin_menu/admin_init) to respect the 6.7+ i18n timing.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const OPTION = 'hk9_settings';

	/** Empty link value. */
	public const EMPTY_LINK = [
		'label'   => '',
		'url'     => '',
		'post_id' => 0,
		'target'  => '_self',
		'rel'     => '',
	];

	/**
	 * Tabs → groups. Tab slugs are used in the URL (?page=hk9-settings&tab=branding).
	 *
	 * @return array<string, string[]>
	 */
	public static function tab_groups(): array {
		return [
			'branding'     => [ 'branding' ],
			'appearance'   => [ 'colors', 'fonts' ],
			'contact'      => [ 'contact' ],
			'destinations' => [ 'links' ],
			'header'       => [ 'header' ],
			'footer'       => [ 'footer' ],
			'blog'         => [ 'blog' ],
			'forms'        => [ 'forms' ],
			'analytics'    => [ 'analytics' ],
			'advanced'     => [ 'advanced' ],
		];
	}

	/**
	 * Tab labels (admin only).
	 *
	 * @return array<string, string>
	 */
	public static function tab_labels(): array {
		return [
			'branding'     => __( 'Branding', 'heartland-k9s-core' ),
			'appearance'   => __( 'Colors & Fonts', 'heartland-k9s-core' ),
			'contact'      => __( 'Contact', 'heartland-k9s-core' ),
			'destinations' => __( 'Destinations', 'heartland-k9s-core' ),
			'header'       => __( 'Header', 'heartland-k9s-core' ),
			'footer'       => __( 'Footer', 'heartland-k9s-core' ),
			'blog'         => __( 'Blog', 'heartland-k9s-core' ),
			'forms'        => __( 'Forms', 'heartland-k9s-core' ),
			'analytics'    => __( 'Analytics', 'heartland-k9s-core' ),
			'advanced'     => __( 'Advanced', 'heartland-k9s-core' ),
		];
	}

	/** Provider labels for the "Default form provider" select (value => label). */
	private static function provider_options(): array {
		if ( class_exists( 'HK9\\Core\\Support\\FormProviders' ) ) {
			return \HK9\Core\Support\FormProviders::labels( false );
		}
		return [
			'builtin'   => __( 'Built-in form (this plugin)', 'heartland-k9s-core' ),
			'gravity'   => __( 'Gravity Forms', 'heartland-k9s-core' ),
			'shortcode' => __( 'Form shortcode', 'heartland-k9s-core' ),
		];
	}

	/** Help text for the Gravity Forms pickers (states whether Gravity Forms is active). */
	private static function gravity_help(): string {
		if ( class_exists( 'HK9\\Core\\Support\\FormProviders' ) ) {
			return \HK9\Core\Support\FormProviders::gravity_help();
		}
		return __( 'Gravity Forms is not active.', 'heartland-k9s-core' );
	}

	/**
	 * Renders a `number` setting (stored int form id) as a select of the active
	 * Gravity Forms forms; the stored id is kept as an option while unavailable.
	 */
	private static function gravity_select( string $key ): array {
		$current = 0;
		if ( class_exists( 'HK9\\Core\\Settings\\Store' ) ) {
			$current = (int) ( Store::raw()['forms'][ $key ] ?? 0 );
		}
		$options = [ '' => __( '— Not set —', 'heartland-k9s-core' ) ];
		// The form list is only needed when the settings screen renders (fields() also runs on admin_init for every admin request).
		$on_settings_screen = isset( $_GET['page'] ) && Page::SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		if ( $on_settings_screen && class_exists( 'HK9\\Core\\Support\\FormProviders' ) ) {
			$options += \HK9\Core\Support\FormProviders::gravity_form_options( $current );
		} elseif ( $current > 0 ) {
			/* translators: %d: form id */
			$options[ (string) $current ] = sprintf( __( 'Form #%d', 'heartland-k9s-core' ), $current );
		}
		return [
			'type'    => 'select',
			'options' => $options,
		];
	}

	private static function link( string $url = '', string $target = '_self' ): array {
		return array_merge(
			self::EMPTY_LINK,
			[
				'url'    => $url,
				'target' => $target,
			]
		);
	}

	/**
	 * Default values, group => key => value. Pure data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults(): array {
		static $defaults = null;
		if ( null !== $defaults ) {
			return $defaults;
		}
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
				'facebook'              => 'https://www.facebook.com/heartlandk9s/',
				'instagram'             => '',
				'youtube'               => '',
				'linkedin'              => '',
				'x'                     => '',
				'tiktok'                => '',
				'candid_url'            => 'https://app.candid.org/profile/9494475/heartland-canines-for-veterans-inc-47-4991572/',
				'show_guidestar_seal'   => true,
				'ein'                   => '47-4991572',
				'legal_name'            => 'Heartland Canines for Veterans Inc',
				// Verbatim from the live /donate/ page (typo "extend" -> "extent" corrected; conflict-log S6).
				'tax_statement'         => 'Heartland Canines For Veterans is an IRS recognized 501(c)(3) non-profit organization. IRS EIN 47-4991572. All donations are tax deductible to the extent allowed by law. Consult your CPA if you have questions.',
			],
			'links'     => [
				'donate'                 => self::link( '/donate/' ),
				'donate_external'        => self::link( 'https://www.zeffy.com/en-US/donation-form/donate-to-heartland-k9s-it-will-change-lives', '_blank' ),
				'paypal_hosted_button_id' => 'METNHXQ9EBUMQ',
				'amazon_wishlist'        => self::link( 'https://www.amazon.com/hz/wishlist/ls/35NF4IVCKN76G?ref_=wl_share', '_blank' ),
				'application'            => self::link( '/online-application/' ),
				'five_questions'         => self::link( '/5-questions/' ),
				'ada'                    => self::link( '/service-dogs-and-the-ada/' ),
				'volunteer'              => self::link( '/volunteer/' ),
				'provider'               => self::link( '/the-service-k9-program/' ),
				'campaigns'              => self::link( '/campaigns/' ),
				'events'                 => self::link( '/events/' ),
				'stories'                => self::link( '/stories/' ),
				'contact'                => self::link( '/contact/' ),
				'gear'                   => self::link( 'https://linksinkllc.com/?s=Heartland', '_blank' ),
				'coloring_book'          => self::link( '/the-hk9-coloring-book/' ),
				'obedience'              => self::link( '/heartland-obedience-training-2/' ),
				'teams'                  => self::link( '/hk9-current-teams-in-training/' ),
				'people'                 => self::link( '/meet-the-team/' ),
				'photos'                 => self::link( '/photos/' ),
				'barkode'                => self::link( '/barkode/' ),
			],
			'header'    => [
				'cta_label' => 'Donate Now',
				'cta_link'  => self::EMPTY_LINK, // Empty = falls back to links.donate at read time.
				'show_cta'  => true,
				'sticky'    => true,
			],
			'footer'    => [
				'description'  => 'An IRS-recognized 501(c)(3) nonprofit pairing eligible disabled U.S. veterans with highly trained service dogs at zero cost.',
				'tagline'      => 'So They Never Walk Alone.',
				'col2_heading' => 'Quick Links',
				'col3_heading' => 'Get Involved',
				'col4_heading' => 'Contact Us',
				'copyright'       => '© {year} Heartland Canines for Veterans. All rights reserved.',
				'credit'          => 'Built with ♥ for our veterans',
				'credit_by_label' => 'Cosmick Media',
				'credit_by_url'   => 'https://www.cosmickmedia.com',
				'show_seal'       => true,
			],
			'blog'      => [
				'layout'              => 'list',
				'search_placeholder'  => 'Search…',
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
				'subjects'                 => [
					[ 'value' => 'veteran-application', 'label' => 'Veteran Application Inquiry' ],
					[ 'value' => 'donation', 'label' => 'Donation Inquiry' ],
					[ 'value' => 'volunteer', 'label' => 'Volunteer / Campaign' ],
					[ 'value' => 'provider', 'label' => 'K9 Provider Partnership' ],
					[ 'value' => 'other', 'label' => 'Other' ],
				],
				'rate_limit'               => 5,
				'trusted_proxies'          => '',
				'proxy_header'             => 'X-Forwarded-For',
				'retention_days'           => 90,
				'store_submissions'        => true,
				'contact_success_text'     => 'Thank you — your message has been sent. We will get back to you as soon as we can.',
				'application_success_page' => self::link( '/thank-you/' ),
				'provider'                 => 'builtin',
				'gravity_contact_form'     => 0,
				'gravity_application_form' => 0,
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
		return $defaults;
	}

	/**
	 * Field types per group/key (pure data, used by the sanitizer).
	 *
	 * @return array<string, array<string, array>>
	 */
	public static function types(): array {
		static $types = null;
		if ( null !== $types ) {
			return $types;
		}
		$types = [
			'branding'  => [
				'header_logo'        => [ 'type' => 'image' ],
				'footer_logo'        => [ 'type' => 'image' ],
				'header_logo_height' => [ 'type' => 'number', 'min' => 24, 'max' => 200 ],
				'footer_logo_height' => [ 'type' => 'number', 'min' => 24, 'max' => 300 ],
				'wordmark_line1'     => [ 'type' => 'text' ],
				'wordmark_line2'     => [ 'type' => 'text' ],
				'show_wordmark'      => [ 'type' => 'toggle' ],
			],
			'colors'    => array_fill_keys( [ 'primary', 'secondary', 'background', 'foreground', 'muted', 'muted_foreground', 'border', 'accent' ], [ 'type' => 'color' ] ),
			'fonts'     => [
				'serif' => [ 'type' => 'select', 'options' => [ 'fraunces', 'system-serif' ] ],
				'sans'  => [ 'type' => 'select', 'options' => [ 'inter', 'system-sans' ] ],
			],
			'contact'   => [
				'phone_main'            => [ 'type' => 'text' ],
				'phone_main_label'      => [ 'type' => 'text' ],
				'phone_secondary'       => [ 'type' => 'text' ],
				'phone_secondary_label' => [ 'type' => 'text' ],
				'email'                 => [ 'type' => 'email' ],
				'email_director'        => [ 'type' => 'email' ],
				'email_development'     => [ 'type' => 'email' ],
				'address_line1'         => [ 'type' => 'text' ],
				'address_line2'         => [ 'type' => 'text' ],
				'city'                  => [ 'type' => 'text' ],
				'state'                 => [ 'type' => 'text' ],
				'zip'                   => [ 'type' => 'text' ],
				'hours'                 => [ 'type' => 'text' ],
				'hours_days'            => [ 'type' => 'text' ],
				'service_area'          => [ 'type' => 'text' ],
				'facebook'              => [ 'type' => 'url' ],
				'instagram'             => [ 'type' => 'url' ],
				'youtube'               => [ 'type' => 'url' ],
				'linkedin'              => [ 'type' => 'url' ],
				'x'                     => [ 'type' => 'url' ],
				'tiktok'                => [ 'type' => 'url' ],
				'candid_url'            => [ 'type' => 'url' ],
				'show_guidestar_seal'   => [ 'type' => 'toggle' ],
				'ein'                   => [ 'type' => 'text' ],
				'legal_name'            => [ 'type' => 'text' ],
				'tax_statement'         => [ 'type' => 'textarea' ],
			],
			'links'     => [
				'donate'                  => [ 'type' => 'link' ],
				'donate_external'         => [ 'type' => 'link' ],
				'paypal_hosted_button_id' => [ 'type' => 'text' ],
				'amazon_wishlist'         => [ 'type' => 'link' ],
				'application'             => [ 'type' => 'link' ],
				'five_questions'          => [ 'type' => 'link' ],
				'ada'                     => [ 'type' => 'link' ],
				'volunteer'               => [ 'type' => 'link' ],
				'provider'                => [ 'type' => 'link' ],
				'campaigns'               => [ 'type' => 'link' ],
				'events'                  => [ 'type' => 'link' ],
				'stories'                 => [ 'type' => 'link' ],
				'contact'                 => [ 'type' => 'link' ],
				'gear'                    => [ 'type' => 'link' ],
				'coloring_book'           => [ 'type' => 'link' ],
				'obedience'               => [ 'type' => 'link' ],
				'teams'                   => [ 'type' => 'link' ],
				'people'                  => [ 'type' => 'link' ],
				'photos'                  => [ 'type' => 'link' ],
				'barkode'                 => [ 'type' => 'link' ],
			],
			'header'    => [
				'cta_label' => [ 'type' => 'text' ],
				'cta_link'  => [ 'type' => 'link' ],
				'show_cta'  => [ 'type' => 'toggle' ],
				'sticky'    => [ 'type' => 'toggle' ],
			],
			'footer'    => [
				'description'  => [ 'type' => 'textarea' ],
				'tagline'      => [ 'type' => 'text' ],
				'col2_heading' => [ 'type' => 'text' ],
				'col3_heading' => [ 'type' => 'text' ],
				'col4_heading' => [ 'type' => 'text' ],
				'copyright'       => [ 'type' => 'text' ],
				'credit'          => [ 'type' => 'text' ],
				'credit_by_label' => [ 'type' => 'text' ],
				'credit_by_url'   => [ 'type' => 'url' ],
				'show_seal'       => [ 'type' => 'toggle' ],
			],
			'blog'      => [
				'layout'              => [ 'type' => 'select', 'options' => [ 'list', 'grid' ] ],
				'search_placeholder'  => [ 'type' => 'text' ],
				'hero_title'          => [ 'type' => 'text' ],
				'hero_text'           => [ 'type' => 'textarea' ],
				'hero_image'          => [ 'type' => 'image' ],
				'show_author'         => [ 'type' => 'toggle' ],
				'show_date'           => [ 'type' => 'toggle' ],
				'show_categories'     => [ 'type' => 'toggle' ],
				'show_tags'           => [ 'type' => 'toggle' ],
				'show_related'        => [ 'type' => 'toggle' ],
				'related_count'       => [ 'type' => 'number', 'min' => 0, 'max' => 12 ],
				'show_featured_image' => [ 'type' => 'toggle' ],
			],
			'forms'     => [
				'contact_recipients'       => [ 'type' => 'emails' ],
				'application_recipients'   => [ 'type' => 'emails' ],
				'from_name'                => [ 'type' => 'text' ],
				'from_email'               => [ 'type' => 'email' ],
				'subjects'                 => [
					'type'   => 'repeater',
					'fields' => [
						'value' => [ 'type' => 'slug' ],
						'label' => [ 'type' => 'text' ],
					],
				],
				'rate_limit'               => [ 'type' => 'number', 'min' => 1, 'max' => 1000 ],
				'trusted_proxies'          => [ 'type' => 'cidrs' ],
				'proxy_header'             => [ 'type' => 'header' ],
				'retention_days'           => [ 'type' => 'number', 'min' => 0, 'max' => 3650 ],
				'store_submissions'        => [ 'type' => 'toggle' ],
				'contact_success_text'     => [ 'type' => 'textarea' ],
				'application_success_page' => [ 'type' => 'link' ],
				'provider'                 => [ 'type' => 'select', 'options' => [ 'builtin', 'gravity', 'shortcode' ] ],
				'gravity_contact_form'     => [ 'type' => 'number', 'min' => 0 ],
				'gravity_application_form' => [ 'type' => 'number', 'min' => 0 ],
			],
			'analytics' => [
				'fathom_site_id' => [ 'type' => 'code' ],
			],
			'advanced'  => [
				'editors_manage_registry'  => [ 'type' => 'toggle' ],
				'purge_on_uninstall'       => [ 'type' => 'toggle' ],
				'disable_user_enumeration' => [ 'type' => 'toggle' ],
				'output_seo_meta'          => [ 'type' => 'toggle' ],
			],
		];
		return $types;
	}

	/**
	 * Full field definitions with labels/help for the admin page. Admin hooks only.
	 *
	 * @return array<string, array{label:string, description:string, fields:array<string, array>}>
	 */
	public static function fields(): array {
		$d = self::defaults();
		$t = self::types();

		$def = static function ( string $group, string $key, string $label, string $help = '', array $extra = [] ) use ( $d, $t ): array {
			return array_merge(
				$t[ $group ][ $key ],
				[
					'label'   => $label,
					'help'    => $help,
					'default' => $d[ $group ][ $key ],
				],
				$extra
			);
		};

		return [
			'branding'  => [
				'label'       => __( 'Branding', 'heartland-k9s-core' ),
				'description' => __( 'Logos and the wordmark shown in the header and footer.', 'heartland-k9s-core' ),
				'fields'      => [
					'header_logo'        => $def( 'branding', 'header_logo', __( 'Header logo', 'heartland-k9s-core' ), __( 'Transparent PNG or SVG. When empty, the Site Logo from Appearance → Customize is used.', 'heartland-k9s-core' ) ),
					'header_logo_height' => $def( 'branding', 'header_logo_height', __( 'Header logo height (px)', 'heartland-k9s-core' ), __( 'The reference design uses 64px inside an 80px header.', 'heartland-k9s-core' ) ),
					'footer_logo'        => $def( 'branding', 'footer_logo', __( 'Footer logo', 'heartland-k9s-core' ), __( 'Falls back to the header logo when empty.', 'heartland-k9s-core' ) ),
					'footer_logo_height' => $def( 'branding', 'footer_logo_height', __( 'Footer logo height (px)', 'heartland-k9s-core' ) ),
					'show_wordmark'      => $def( 'branding', 'show_wordmark', __( 'Show the two-line wordmark next to the logo', 'heartland-k9s-core' ) ),
					'wordmark_line1'     => $def( 'branding', 'wordmark_line1', __( 'Wordmark line 1', 'heartland-k9s-core' ) ),
					'wordmark_line2'     => $def( 'branding', 'wordmark_line2', __( 'Wordmark line 2', 'heartland-k9s-core' ), __( 'Displayed in small caps under line 1.', 'heartland-k9s-core' ) ),
					'site_icon_note'     => [
						'type'  => 'note',
						'label' => __( 'Site icon (favicon)', 'heartland-k9s-core' ),
						'help'  => sprintf(
							/* translators: %s: URL of the Customizer site identity panel. */
							__( 'The browser tab icon is managed by WordPress: <a href="%s">Appearance → Customize → Site Identity → Site Icon</a>.', 'heartland-k9s-core' ),
							esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) )
						),
					],
				],
			],
			'colors'    => [
				'label'       => __( 'Colors', 'heartland-k9s-core' ),
				'description' => __( 'Brand palette emitted as CSS variables. Defaults match the reference design.', 'heartland-k9s-core' ),
				'fields'      => [
					'primary'          => $def( 'colors', 'primary', __( 'Primary (navy)', 'heartland-k9s-core' ), __( 'Header text, navy bands, buttons.', 'heartland-k9s-core' ) ),
					'secondary'        => $def( 'colors', 'secondary', __( 'Secondary (crimson)', 'heartland-k9s-core' ), __( 'Primary call-to-action buttons and accents.', 'heartland-k9s-core' ) ),
					'background'       => $def( 'colors', 'background', __( 'Page background', 'heartland-k9s-core' ) ),
					'foreground'       => $def( 'colors', 'foreground', __( 'Body text', 'heartland-k9s-core' ) ),
					'muted'            => $def( 'colors', 'muted', __( 'Muted background', 'heartland-k9s-core' ), __( 'Tinted sections and muted cards.', 'heartland-k9s-core' ) ),
					'muted_foreground' => $def( 'colors', 'muted_foreground', __( 'Muted text', 'heartland-k9s-core' ), __( 'Secondary paragraph color.', 'heartland-k9s-core' ) ),
					'border'           => $def( 'colors', 'border', __( 'Borders', 'heartland-k9s-core' ) ),
					'accent'           => $def( 'colors', 'accent', __( 'Accent', 'heartland-k9s-core' ) ),
				],
			],
			'fonts'     => [
				'label'       => __( 'Fonts', 'heartland-k9s-core' ),
				'description' => __( 'Self-hosted fonts ship with the theme; system fonts avoid any font download.', 'heartland-k9s-core' ),
				'fields'      => [
					'serif' => $def( 'fonts', 'serif', __( 'Heading font', 'heartland-k9s-core' ), '', [ 'options' => [ 'fraunces' => __( 'Fraunces (self-hosted)', 'heartland-k9s-core' ), 'system-serif' => __( 'System serif', 'heartland-k9s-core' ) ] ] ),
					'sans'  => $def( 'fonts', 'sans', __( 'Body font', 'heartland-k9s-core' ), '', [ 'options' => [ 'inter' => __( 'Inter (self-hosted)', 'heartland-k9s-core' ), 'system-sans' => __( 'System sans-serif', 'heartland-k9s-core' ) ] ] ),
				],
			],
			'contact'   => [
				'label'       => __( 'Contact', 'heartland-k9s-core' ),
				'description' => __( 'Organisation details used in the footer, Contact page, Donate page and structured data.', 'heartland-k9s-core' ),
				'fields'      => [
					'phone_main'            => $def( 'contact', 'phone_main', __( 'Main phone', 'heartland-k9s-core' ) ),
					'phone_main_label'      => $def( 'contact', 'phone_main_label', __( 'Main phone label', 'heartland-k9s-core' ) ),
					'phone_secondary'       => $def( 'contact', 'phone_secondary', __( 'Secondary phone', 'heartland-k9s-core' ) ),
					'phone_secondary_label' => $def( 'contact', 'phone_secondary_label', __( 'Secondary phone label', 'heartland-k9s-core' ) ),
					'email'                 => $def( 'contact', 'email', __( 'General email', 'heartland-k9s-core' ) ),
					'email_director'        => $def( 'contact', 'email_director', __( 'Director email', 'heartland-k9s-core' ) ),
					'email_development'     => $def( 'contact', 'email_development', __( 'Development email', 'heartland-k9s-core' ) ),
					'address_line1'         => $def( 'contact', 'address_line1', __( 'Address line 1', 'heartland-k9s-core' ) ),
					'address_line2'         => $def( 'contact', 'address_line2', __( 'Address line 2', 'heartland-k9s-core' ) ),
					'city'                  => $def( 'contact', 'city', __( 'City', 'heartland-k9s-core' ) ),
					'state'                 => $def( 'contact', 'state', __( 'State', 'heartland-k9s-core' ) ),
					'zip'                   => $def( 'contact', 'zip', __( 'ZIP', 'heartland-k9s-core' ) ),
					'hours'                 => $def( 'contact', 'hours', __( 'Office hours', 'heartland-k9s-core' ), __( 'Shown as written, e.g. "8:00am – 5:00pm".', 'heartland-k9s-core' ) ),
					'hours_days'            => $def( 'contact', 'hours_days', __( 'Office days', 'heartland-k9s-core' ), __( 'Optional, e.g. "Mon–Fri". Left empty until confirmed.', 'heartland-k9s-core' ) ),
					'service_area'          => $def( 'contact', 'service_area', __( 'Service area line', 'heartland-k9s-core' ), __( 'Optional footer line shown with the map-pin icon. Left empty until confirmed.', 'heartland-k9s-core' ) ),
					'facebook'              => $def( 'contact', 'facebook', __( 'Facebook URL', 'heartland-k9s-core' ) ),
					'instagram'             => $def( 'contact', 'instagram', __( 'Instagram URL', 'heartland-k9s-core' ) ),
					'youtube'               => $def( 'contact', 'youtube', __( 'YouTube URL', 'heartland-k9s-core' ) ),
					'linkedin'              => $def( 'contact', 'linkedin', __( 'LinkedIn URL', 'heartland-k9s-core' ) ),
					'x'                     => $def( 'contact', 'x', __( 'X (Twitter) URL', 'heartland-k9s-core' ) ),
					'tiktok'                => $def( 'contact', 'tiktok', __( 'TikTok URL', 'heartland-k9s-core' ), __( 'Social links appear in the footer only when filled in.', 'heartland-k9s-core' ) ),
					'candid_url'            => $def( 'contact', 'candid_url', __( 'Candid / GuideStar profile URL', 'heartland-k9s-core' ) ),
					'show_guidestar_seal'   => $def( 'contact', 'show_guidestar_seal', __( 'Show the GuideStar transparency seal', 'heartland-k9s-core' ) ),
					'ein'                   => $def( 'contact', 'ein', __( 'EIN', 'heartland-k9s-core' ) ),
					'legal_name'            => $def( 'contact', 'legal_name', __( 'Legal name', 'heartland-k9s-core' ) ),
					'tax_statement'         => $def( 'contact', 'tax_statement', __( 'Tax-deductibility statement', 'heartland-k9s-core' ), __( 'Shown on the Donate page.', 'heartland-k9s-core' ) ),
				],
			],
			'links'     => [
				'label'       => __( 'Destinations', 'heartland-k9s-core' ),
				'description' => __( 'Where the site\'s recurring buttons and menu shortcuts point. Pick a page or enter an external address — never an ID.', 'heartland-k9s-core' ),
				'fields'      => [
					'donate'                  => $def( 'links', 'donate', __( 'Donate page', 'heartland-k9s-core' ), __( 'Used by "Donate" buttons that stay on the site.', 'heartland-k9s-core' ) ),
					'donate_external'         => $def( 'links', 'donate_external', __( 'Online donation form (external)', 'heartland-k9s-core' ), __( 'The Zeffy donation form used by "Support a Service Dog" buttons.', 'heartland-k9s-core' ) ),
					'paypal_hosted_button_id' => $def( 'links', 'paypal_hosted_button_id', __( 'PayPal hosted button ID', 'heartland-k9s-core' ), __( 'Used by the Donate page\'s PayPal option: the PayPal button links to the hosted button checkout for this ID; leave empty to hide the PayPal option.', 'heartland-k9s-core' ) ),
					'amazon_wishlist'         => $def( 'links', 'amazon_wishlist', __( 'Amazon wishlist', 'heartland-k9s-core' ) ),
					'application'             => $def( 'links', 'application', __( 'Veteran application', 'heartland-k9s-core' ) ),
					'five_questions'          => $def( 'links', 'five_questions', __( '5 Questions page', 'heartland-k9s-core' ) ),
					'ada'                     => $def( 'links', 'ada', __( 'Service dogs & the ADA', 'heartland-k9s-core' ) ),
					'volunteer'               => $def( 'links', 'volunteer', __( 'Volunteer', 'heartland-k9s-core' ) ),
					'provider'                => $def( 'links', 'provider', __( 'K9 provider information', 'heartland-k9s-core' ) ),
					'campaigns'               => $def( 'links', 'campaigns', __( 'Campaigns listing', 'heartland-k9s-core' ) ),
					'events'                  => $def( 'links', 'events', __( 'Events listing', 'heartland-k9s-core' ) ),
					'stories'                 => $def( 'links', 'stories', __( 'Stories listing', 'heartland-k9s-core' ) ),
					'contact'                 => $def( 'links', 'contact', __( 'Contact page', 'heartland-k9s-core' ) ),
					'gear'                    => $def( 'links', 'gear', __( 'Heartland gear shop', 'heartland-k9s-core' ) ),
					'coloring_book'           => $def( 'links', 'coloring_book', __( 'Coloring book page', 'heartland-k9s-core' ) ),
					'obedience'               => $def( 'links', 'obedience', __( 'Obedience training page', 'heartland-k9s-core' ) ),
					'teams'                   => $def( 'links', 'teams', __( 'Teams in training', 'heartland-k9s-core' ) ),
					'people'                  => $def( 'links', 'people', __( 'Meet the team', 'heartland-k9s-core' ) ),
					'photos'                  => $def( 'links', 'photos', __( 'Photo gallery', 'heartland-k9s-core' ) ),
					'barkode'                 => $def( 'links', 'barkode', __( 'BarKode program page', 'heartland-k9s-core' ) ),
				],
			],
			'header'    => [
				'label'       => __( 'Header', 'heartland-k9s-core' ),
				'description' => __( 'Navigation menus are managed under Appearance → Menus (location "Primary").', 'heartland-k9s-core' ),
				'fields'      => [
					'show_cta'  => $def( 'header', 'show_cta', __( 'Show the header button', 'heartland-k9s-core' ) ),
					'cta_label' => $def( 'header', 'cta_label', __( 'Header button label', 'heartland-k9s-core' ) ),
					'cta_link'  => $def( 'header', 'cta_link', __( 'Header button destination', 'heartland-k9s-core' ), __( 'When empty, the Donate page from Destinations is used.', 'heartland-k9s-core' ) ),
					'sticky'    => $def( 'header', 'sticky', __( 'Sticky header', 'heartland-k9s-core' ), __( 'Keeps the header pinned while scrolling.', 'heartland-k9s-core' ) ),
				],
			],
			'footer'    => [
				'label'       => __( 'Footer', 'heartland-k9s-core' ),
				'description' => sprintf(
					/* translators: %s: URL of the Appearance → Menus screen. */
					__( 'Footer text. The link columns (Quick Links, Get Involved) and the bottom-bar legal links are menus: assign them to the "Footer — Quick Links", "Footer — Get Involved" and "Footer — Legal" locations under <a href="%s">Appearance → Menus</a> (Manage Locations tab).', 'heartland-k9s-core' ),
					esc_url( admin_url( 'nav-menus.php?action=locations' ) )
				),
				'fields'      => [
					'description'     => $def( 'footer', 'description', __( 'Description', 'heartland-k9s-core' ), __( 'Short paragraph under the footer logo.', 'heartland-k9s-core' ) ),
					'tagline'         => $def( 'footer', 'tagline', __( 'Tagline', 'heartland-k9s-core' ), __( 'Serif italic line in crimson.', 'heartland-k9s-core' ) ),
					'col2_heading'    => $def( 'footer', 'col2_heading', __( 'Column 2 heading', 'heartland-k9s-core' ), __( 'Above the "Footer — Quick Links" menu.', 'heartland-k9s-core' ) ),
					'col3_heading'    => $def( 'footer', 'col3_heading', __( 'Column 3 heading', 'heartland-k9s-core' ), __( 'Above the "Footer — Get Involved" menu.', 'heartland-k9s-core' ) ),
					'col4_heading'    => $def( 'footer', 'col4_heading', __( 'Column 4 heading', 'heartland-k9s-core' ), __( 'Above the contact details (from the Contact tab).', 'heartland-k9s-core' ) ),
					'menus_note'      => [
						'type'  => 'note',
						'label' => __( 'Column links', 'heartland-k9s-core' ),
						'help'  => sprintf(
							/* translators: %s: URL of the Appearance → Menus screen. */
							__( 'Edit the links in each column under <a href="%s">Appearance → Menus</a>: pick the menu assigned to "Footer — Quick Links" or "Footer — Get Involved", add or remove pages, then Save Menu. When no menu is assigned, the theme prints its reference links.', 'heartland-k9s-core' ),
							esc_url( admin_url( 'nav-menus.php' ) )
						),
					],
					'copyright'       => $def( 'footer', 'copyright', __( 'Copyright line', 'heartland-k9s-core' ), __( '{year} is replaced with the current year.', 'heartland-k9s-core' ) ),
					'credit'          => $def( 'footer', 'credit', __( 'Credit line', 'heartland-k9s-core' ), __( 'Bottom-right line of the footer. The ♥ character is rendered as the crimson heart icon. The "by …" link below is appended after it; leave this empty to hide the whole line.', 'heartland-k9s-core' ) ),
					'credit_by_label' => $def( 'footer', 'credit_by_label', __( 'Credit "by" label', 'heartland-k9s-core' ), __( 'Printed as "… by [label]." after the credit line, e.g. "Built with ♥ for our veterans by Cosmick Media." Leave empty to omit the "by …" part.', 'heartland-k9s-core' ) ),
					'credit_by_url'   => $def( 'footer', 'credit_by_url', __( 'Credit "by" link', 'heartland-k9s-core' ), __( 'Where the label links to (opens in a new tab). Leave empty to print the label without a link.', 'heartland-k9s-core' ) ),
					'show_seal'       => $def( 'footer', 'show_seal', __( 'Show the GuideStar seal in the footer', 'heartland-k9s-core' ), __( 'Also needs the Candid / GuideStar profile URL and the seal switch on the Contact tab.', 'heartland-k9s-core' ) ),
				],
			],
			'blog'      => [
				'label'       => __( 'Blog', 'heartland-k9s-core' ),
				'description' => __( 'The news listing is the page selected as "Posts page" under Settings → Reading.', 'heartland-k9s-core' ),
				'fields'      => [
					'layout'              => $def( 'blog', 'layout', __( 'Listing layout', 'heartland-k9s-core' ), '', [ 'options' => [ 'list' => __( 'List', 'heartland-k9s-core' ), 'grid' => __( 'Grid', 'heartland-k9s-core' ) ] ] ),
					'hero_title'          => $def( 'blog', 'hero_title', __( 'Listing title', 'heartland-k9s-core' ) ),
					'hero_text'           => $def( 'blog', 'hero_text', __( 'Listing intro', 'heartland-k9s-core' ) ),
					'hero_image'          => $def( 'blog', 'hero_image', __( 'Listing hero image', 'heartland-k9s-core' ), __( 'Optional. The navy band is used when empty.', 'heartland-k9s-core' ) ),
					'show_featured_image' => $def( 'blog', 'show_featured_image', __( 'Show featured images', 'heartland-k9s-core' ) ),
					'show_date'           => $def( 'blog', 'show_date', __( 'Show dates', 'heartland-k9s-core' ) ),
					'show_author'         => $def( 'blog', 'show_author', __( 'Show author names', 'heartland-k9s-core' ) ),
					'show_categories'     => $def( 'blog', 'show_categories', __( 'Show categories', 'heartland-k9s-core' ) ),
					'show_tags'           => $def( 'blog', 'show_tags', __( 'Show tags', 'heartland-k9s-core' ) ),
					'show_related'        => $def( 'blog', 'show_related', __( 'Show related posts', 'heartland-k9s-core' ) ),
					'related_count'       => $def( 'blog', 'related_count', __( 'Related posts count', 'heartland-k9s-core' ) ),
					'search_placeholder'  => $def( 'blog', 'search_placeholder', __( 'Search box placeholder', 'heartland-k9s-core' ), __( 'Hint text inside the search field (404 page, search results, empty listings).', 'heartland-k9s-core' ) ),
					'helpful_links_note'  => [
						'type'  => 'note',
						'label' => __( 'Helpful links (404 & search)', 'heartland-k9s-core' ),
						'help'  => sprintf(
							/* translators: %s: URL of the Appearance → Menus screen. */
							__( 'The "Or try one of these pages" buttons on the 404 page and on empty search results come from the menu assigned to the "Helpful links (404 & search)" location under <a href="%s">Appearance → Menus</a>. When no menu is assigned, the theme links Home, About, the K9 provider page, Contact, Donate and News.', 'heartland-k9s-core' ),
							esc_url( admin_url( 'nav-menus.php?action=locations' ) )
						),
					],
				],
			],
			'forms'     => [
				'label'       => __( 'Forms', 'heartland-k9s-core' ),
				'description' => __( 'Contact and application-inquiry forms. The provider decides which form the Contact and Application pages show (each page can override it in its "Form" section). Recipients: one email address per line.', 'heartland-k9s-core' ),
				'fields'      => [
					'provider'                 => $def( 'forms', 'provider', __( 'Default form provider', 'heartland-k9s-core' ), __( 'Built-in: the plugin\'s own contact and application forms (recipients, subjects and submissions below). Gravity Forms: the forms picked below. Form shortcode: each page\'s "Form" section supplies the shortcode.', 'heartland-k9s-core' ), [ 'options' => self::provider_options() ] ),
					'gravity_contact_form'     => $def( 'forms', 'gravity_contact_form', __( 'Gravity Forms: contact form', 'heartland-k9s-core' ), self::gravity_help(), self::gravity_select( 'gravity_contact_form' ) ),
					'gravity_application_form' => $def( 'forms', 'gravity_application_form', __( 'Gravity Forms: application form', 'heartland-k9s-core' ), self::gravity_help(), self::gravity_select( 'gravity_application_form' ) ),
					'gravity_provision'        => [
						'type'   => 'note',
						'label'  => __( 'Heartland forms in Gravity Forms', 'heartland-k9s-core' ),
						'help'   => '',
						'render' => static function (): void {
							if ( class_exists( 'HK9\\Core\\Forms\\GravityProvisioner' ) ) {
								\HK9\Core\Forms\GravityProvisioner::render_settings_panel();
							}
						},
					],
					'contact_recipients'       => $def( 'forms', 'contact_recipients', __( 'Contact form recipients', 'heartland-k9s-core' ), __( 'One address per line. When empty, the site admin email is used.', 'heartland-k9s-core' ) ),
					'application_recipients'   => $def( 'forms', 'application_recipients', __( 'Application inquiry recipients', 'heartland-k9s-core' ), __( 'One address per line. When empty, the contact recipients are used.', 'heartland-k9s-core' ) ),
					'from_name'                => $def( 'forms', 'from_name', __( 'From name', 'heartland-k9s-core' ), __( 'Defaults to the site title.', 'heartland-k9s-core' ) ),
					'from_email'               => $def( 'forms', 'from_email', __( 'From email', 'heartland-k9s-core' ), __( 'Should be an address on this domain so mail is not rejected. Visitor replies use Reply-To.', 'heartland-k9s-core' ) ),
					'subjects'                 => $def( 'forms', 'subjects', __( 'Contact form subjects', 'heartland-k9s-core' ), __( 'The "value" is used in the email subject line; the label is what visitors see.', 'heartland-k9s-core' ) ),
					'rate_limit'               => $def( 'forms', 'rate_limit', __( 'Submissions per hour per visitor', 'heartland-k9s-core' ) ),
					'trusted_proxies'          => $def( 'forms', 'trusted_proxies', __( 'Trusted proxy addresses', 'heartland-k9s-core' ), __( 'Off when empty (the connecting address is the visitor). Behind a CDN or reverse proxy that does not rewrite the connecting address, list its IPs or CIDR ranges (one per line, e.g. 10.0.0.0/8) so the per-visitor limit is read from the header below instead of applying to all visitors at once. Only list proxies you control or your CDN publishes.', 'heartland-k9s-core' ) ),
					'proxy_header'             => $def( 'forms', 'proxy_header', __( 'Client IP header', 'heartland-k9s-core' ), __( 'Header the trusted proxy sets, e.g. X-Forwarded-For, CF-Connecting-IP or X-Real-IP. Only read when the request comes from a trusted proxy address.', 'heartland-k9s-core' ) ),
					'store_submissions'        => $def( 'forms', 'store_submissions', __( 'Keep a copy of each submission in Heartland → Submissions', 'heartland-k9s-core' ) ),
					'retention_days'           => $def( 'forms', 'retention_days', __( 'Delete stored submissions after (days)', 'heartland-k9s-core' ), __( '0 keeps them indefinitely.', 'heartland-k9s-core' ) ),
					'contact_success_text'     => $def( 'forms', 'contact_success_text', __( 'Contact form success message', 'heartland-k9s-core' ) ),
					'application_success_page' => $def( 'forms', 'application_success_page', __( 'Application inquiry success page', 'heartland-k9s-core' ) ),
				],
			],
			'analytics' => [
				'label'       => __( 'Analytics', 'heartland-k9s-core' ),
				'description' => __( 'Privacy-friendly analytics. The script is only added when a site ID is set.', 'heartland-k9s-core' ),
				'fields'      => [
					'fathom_site_id' => $def( 'analytics', 'fathom_site_id', __( 'Fathom site ID', 'heartland-k9s-core' ), __( 'Found in your Fathom dashboard under Settings → Sites (e.g. ABCDEFGH).', 'heartland-k9s-core' ) ),
				],
			],
			'advanced'  => [
				'label'       => __( 'Advanced', 'heartland-k9s-core' ),
				'description' => __( 'Access and privacy switches. Change with care.', 'heartland-k9s-core' ),
				'fields'      => [
					'editors_manage_registry'  => $def( 'advanced', 'editors_manage_registry', __( 'Let Editors manage BarKode registry records', 'heartland-k9s-core' ), __( 'By default only Administrators can view and edit registry records because they contain contact details.', 'heartland-k9s-core' ) ),
					'disable_user_enumeration' => $def( 'advanced', 'disable_user_enumeration', __( 'Block public user listing', 'heartland-k9s-core' ), __( 'Hides /wp-json/wp/v2/users and ?author=N from visitors who are not logged in.', 'heartland-k9s-core' ) ),
					'output_seo_meta'          => $def( 'advanced', 'output_seo_meta', __( 'Output basic SEO meta tags', 'heartland-k9s-core' ), __( 'Description and social-sharing tags. Turn off if you install an SEO plugin.', 'heartland-k9s-core' ) ),
					'purge_on_uninstall'       => $def( 'advanced', 'purge_on_uninstall', __( 'Delete all Heartland content and settings when the plugin is deleted', 'heartland-k9s-core' ), __( 'Off by default: uninstalling keeps stories, teams, records and settings in the database.', 'heartland-k9s-core' ) ),
				],
			],
		];
	}
}
