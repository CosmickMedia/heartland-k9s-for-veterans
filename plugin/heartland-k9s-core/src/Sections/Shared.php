<?php
/**
 * Builders for the shared section definitions (§5.1 of the architecture
 * contract) and small helpers used by definitions/*.php.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Sections;

defined( 'ABSPATH' ) || exit;

final class Shared {

	/** Link value helper for defaults. */
	public static function link( string $label, string $url = '', int $post_id = 0, string $target = '_self' ): array {
		return [
			'label'   => $label,
			'url'     => $url,
			'post_id' => $post_id,
			'target'  => $target,
			'rel'     => '',
		];
	}

	/** Button row helper (link + style) for `buttons` repeaters. */
	public static function button( string $label, string $url, string $style = 'primary', string $target = '_self' ): array {
		return [
			'link'  => self::link( $label, $url, 0, $target ),
			'style' => $style,
		];
	}

	/** Link field definition. */
	public static function link_field( string $key, string $label, array $extra = [] ): array {
		return array_merge(
			[
				'type'  => 'link',
				'key'   => $key,
				'label' => $label,
			],
			$extra
		);
	}

	/** Merges a definition array with overrides (defaults merged one level deep). */
	private static function merge( array $base, array $overrides ): array {
		if ( isset( $overrides['defaults'] ) && is_array( $overrides['defaults'] ) ) {
			$base['defaults'] = array_merge( $base['defaults'] ?? [], $overrides['defaults'] );
			unset( $overrides['defaults'] );
		}
		return array_merge( $base, $overrides );
	}

	/** `hero_band`: solid navy band hero. */
	public static function hero_band( array $overrides = [] ): array {
		return self::merge(
			[
				'id'          => 'hero_band',
				'type'        => 'hero_band',
				'label'       => __( 'Hero (band)', 'heartland-k9s-core' ),
				'description' => __( 'Solid navy band at the top of the page. Heading defaults to the page title, text to the excerpt.', 'heartland-k9s-core' ),
				'can_hide'    => false,
				'can_reorder' => false,
				'fields'      => [
					[
						'type'  => 'text',
						'key'   => 'eyebrow',
						'label' => __( 'Eyebrow', 'heartland-k9s-core' ),
					],
					[
						'type'        => 'text',
						'key'         => 'heading',
						'label'       => __( 'Heading', 'heartland-k9s-core' ),
						'placeholder' => __( 'Defaults to the page title', 'heartland-k9s-core' ),
					],
					[
						'type'        => 'textarea',
						'key'         => 'text',
						'label'       => __( 'Text', 'heartland-k9s-core' ),
						'rows'        => 3,
						'placeholder' => __( 'Defaults to the page excerpt', 'heartland-k9s-core' ),
					],
					[
						'type'    => 'select',
						'key'     => 'pattern',
						'label'   => __( 'Background pattern', 'heartland-k9s-core' ),
						'options' => [
							'none'  => __( 'None', 'heartland-k9s-core' ),
							'stars' => __( 'Stars', 'heartland-k9s-core' ),
							'grid'  => __( 'Diagonal grid', 'heartland-k9s-core' ),
						],
						'default' => 'none',
					],
				],
				'defaults'    => [],
			],
			$overrides
		);
	}

	/** `hero_image`: full-bleed image hero. */
	public static function hero_image( array $overrides = [] ): array {
		return self::merge(
			[
				'id'          => 'hero_image',
				'type'        => 'hero_image',
				'label'       => __( 'Hero (image)', 'heartland-k9s-core' ),
				'can_hide'    => false,
				'can_reorder' => false,
				'fields'      => [
					[
						'type'  => 'text',
						'key'   => 'eyebrow',
						'label' => __( 'Eyebrow badge', 'heartland-k9s-core' ),
					],
					[
						'type'  => 'icon',
						'key'   => 'eyebrow_icon',
						'label' => __( 'Eyebrow icon', 'heartland-k9s-core' ),
					],
					[
						'type'     => 'text',
						'key'      => 'heading',
						'label'    => __( 'Heading', 'heartland-k9s-core' ),
						'required' => true,
					],
					[
						'type'  => 'text',
						'key'   => 'heading_break_after',
						'label' => __( 'Line break after word', 'heartland-k9s-core' ),
						'help'  => __( 'On medium screens and up a line break is inserted after this word of the heading. Leave empty for no break.', 'heartland-k9s-core' ),
					],
					[
						'type'  => 'textarea',
						'key'   => 'text',
						'label' => __( 'Text', 'heartland-k9s-core' ),
						'rows'  => 3,
					],
					[
						'type'  => 'image',
						'key'   => 'image',
						'label' => __( 'Background image', 'heartland-k9s-core' ),
						'size'  => 'medium',
					],
					[
						'type'  => 'image',
						'key'   => 'image_mobile',
						'label' => __( 'Background image (mobile)', 'heartland-k9s-core' ),
						'help'  => __( 'Optional portrait crop used on narrow screens.', 'heartland-k9s-core' ),
						'size'  => 'medium',
					],
					[
						'type'    => 'select',
						'key'     => 'focal',
						'label'   => __( 'Focal point', 'heartland-k9s-core' ),
						'options' => [
							'center' => __( 'Center', 'heartland-k9s-core' ),
							'top'    => __( 'Top', 'heartland-k9s-core' ),
							'bottom' => __( 'Bottom', 'heartland-k9s-core' ),
							'left'   => __( 'Left', 'heartland-k9s-core' ),
							'right'  => __( 'Right', 'heartland-k9s-core' ),
						],
						'default' => 'center',
					],
					[
						'type'    => 'select',
						'key'     => 'height',
						'label'   => __( 'Height', 'heartland-k9s-core' ),
						'options' => [
							'85vh' => __( 'Tall (85vh)', 'heartland-k9s-core' ),
							'70vh' => __( 'Medium (70vh)', 'heartland-k9s-core' ),
							'60vh' => __( 'Short (60vh)', 'heartland-k9s-core' ),
						],
						'default' => '85vh',
					],
					[
						'type'    => 'select',
						'key'     => 'overlay',
						'label'   => __( 'Navy overlay opacity', 'heartland-k9s-core' ),
						'options' => [
							'60' => '60%',
							'70' => '70%',
							'80' => '80%',
						],
						'default' => '60',
					],
					[
						'type'    => 'toggle',
						'key'     => 'gradient',
						'label'   => __( 'Add dark gradient', 'heartland-k9s-core' ),
						'default' => true,
					],
					[
						'type'       => 'repeater',
						'key'        => 'buttons',
						'label'      => __( 'Buttons', 'heartland-k9s-core' ),
						'max'        => 2,
						'item_label' => 'link',
						'add_label'  => __( 'Add button', 'heartland-k9s-core' ),
						'fields'     => [
							self::link_field( 'link', __( 'Link', 'heartland-k9s-core' ) ),
							[
								'type'    => 'select',
								'key'     => 'style',
								'label'   => __( 'Style', 'heartland-k9s-core' ),
								'options' => [
									'primary'       => __( 'Primary (crimson)', 'heartland-k9s-core' ),
									'outline-light' => __( 'Outline (light)', 'heartland-k9s-core' ),
								],
								'default' => 'primary',
							],
						],
					],
					[
						'type'    => 'toggle',
						'key'     => 'animate',
						'label'   => __( 'Entrance animation', 'heartland-k9s-core' ),
						'default' => true,
					],
				],
				'defaults'    => [],
			],
			$overrides
		);
	}

	/** `cta_band`: closing call-to-action band. */
	public static function cta_band( string $id = 'cta', array $overrides = [] ): array {
		return self::merge(
			[
				'id'       => $id,
				'type'     => 'cta_band',
				'label'    => __( 'Call to action', 'heartland-k9s-core' ),
				'fields'   => [
					[
						'type'  => 'text',
						'key'   => 'heading',
						'label' => __( 'Heading', 'heartland-k9s-core' ),
					],
					[
						'type'  => 'textarea',
						'key'   => 'text',
						'label' => __( 'Text', 'heartland-k9s-core' ),
						'rows'  => 3,
					],
					[
						'type'       => 'repeater',
						'key'        => 'buttons',
						'label'      => __( 'Buttons', 'heartland-k9s-core' ),
						'max'        => 2,
						'item_label' => 'link',
						'add_label'  => __( 'Add button', 'heartland-k9s-core' ),
						'fields'     => [
							self::link_field( 'link', __( 'Link', 'heartland-k9s-core' ) ),
							[
								'type'    => 'select',
								'key'     => 'style',
								'label'   => __( 'Style', 'heartland-k9s-core' ),
								'options' => [
									'primary'       => __( 'Primary (crimson)', 'heartland-k9s-core' ),
									'outline'       => __( 'Outline (navy)', 'heartland-k9s-core' ),
									'outline-light' => __( 'Outline (light)', 'heartland-k9s-core' ),
									'ghost'         => __( 'Ghost (text link)', 'heartland-k9s-core' ),
								],
								'default' => 'primary',
							],
						],
					],
					[
						'type'    => 'select',
						'key'     => 'tone',
						'label'   => __( 'Background', 'heartland-k9s-core' ),
						'options' => [
							'navy'  => __( 'Navy', 'heartland-k9s-core' ),
							'tint'  => __( 'Navy tint', 'heartland-k9s-core' ),
							'plain' => __( 'Plain', 'heartland-k9s-core' ),
							'muted' => __( 'Muted', 'heartland-k9s-core' ),
						],
						'default' => 'tint',
					],
				],
				'defaults' => [],
			],
			$overrides
		);
	}

	/** `cta_band` with the generic "Join Us in Our Mission" defaults (listing templates). */
	public static function generic_cta( string $id = 'cta', array $overrides = [] ): array {
		return self::cta_band(
			$id,
			self::merge(
				[
					'defaults' => [
						'heading' => 'Join Us in Our Mission',
						'text'    => 'Every donation, volunteer hour, and shared story helps us provide another service dog to a veteran in need.',
						'buttons' => [
							self::button( 'Make a Donation', '/donate/', 'primary' ),
							self::button( 'Ways to Volunteer', '/get-involved/', 'outline' ),
						],
						'tone'    => 'tint',
					],
				],
				$overrides
			)
		);
	}

	/** Card sub-fields shared by feature_cards variants. */
	public static function card_fields( bool $with_button = false ): array {
		$fields = [
			[
				'type'  => 'icon',
				'key'   => 'icon',
				'label' => __( 'Icon', 'heartland-k9s-core' ),
			],
			[
				'type'    => 'select',
				'key'     => 'tone',
				'label'   => __( 'Icon color', 'heartland-k9s-core' ),
				'options' => [
					'navy'    => __( 'Navy', 'heartland-k9s-core' ),
					'crimson' => __( 'Crimson', 'heartland-k9s-core' ),
				],
				'default' => 'navy',
			],
			[
				'type'  => 'text',
				'key'   => 'title',
				'label' => __( 'Title', 'heartland-k9s-core' ),
			],
			[
				'type'  => 'textarea',
				'key'   => 'text',
				'label' => __( 'Text', 'heartland-k9s-core' ),
				'rows'  => 3,
			],
		];
		if ( $with_button ) {
			$fields[] = self::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) );
			$fields[] = [
				'type'    => 'select',
				'key'     => 'button_style',
				'label'   => __( 'Button style', 'heartland-k9s-core' ),
				'options' => [
					'primary' => __( 'Primary (crimson)', 'heartland-k9s-core' ),
					'outline' => __( 'Outline (navy)', 'heartland-k9s-core' ),
				],
				'default' => 'outline',
			];
		} else {
			$fields[] = self::link_field( 'link', __( 'Link', 'heartland-k9s-core' ) );
			$fields[] = [
				'type'  => 'toggle',
				'key'   => 'decorate',
				'label' => __( 'Decorative corner accent', 'heartland-k9s-core' ),
			];
		}
		return $fields;
	}

	/** `feature_cards`: heading + intro + card grid. */
	public static function feature_cards( string $id = 'features', array $overrides = [] ): array {
		return self::merge(
			[
				'id'       => $id,
				'type'     => 'feature_cards',
				'label'    => __( 'Feature cards', 'heartland-k9s-core' ),
				'fields'   => [
					[
						'type'  => 'text',
						'key'   => 'heading',
						'label' => __( 'Heading', 'heartland-k9s-core' ),
					],
					[
						'type'  => 'textarea',
						'key'   => 'intro',
						'label' => __( 'Intro', 'heartland-k9s-core' ),
						'rows'  => 3,
					],
					[
						'type'  => 'toggle',
						'key'   => 'divider',
						'label' => __( 'Show crimson divider under the heading', 'heartland-k9s-core' ),
					],
					[
						'type'    => 'select',
						'key'     => 'align',
						'label'   => __( 'Card alignment', 'heartland-k9s-core' ),
						'options' => [
							'center' => __( 'Centered', 'heartland-k9s-core' ),
							'left'   => __( 'Left', 'heartland-k9s-core' ),
						],
						'default' => 'center',
					],
					[
						'type'    => 'select',
						'key'     => 'columns',
						'label'   => __( 'Columns', 'heartland-k9s-core' ),
						'options' => [
							'2' => '2',
							'3' => '3',
						],
						'default' => '3',
					],
					[
						'type'       => 'repeater',
						'key'        => 'cards',
						'label'      => __( 'Cards', 'heartland-k9s-core' ),
						'item_label' => 'title',
						'add_label'  => __( 'Add card', 'heartland-k9s-core' ),
						'fields'     => self::card_fields( false ),
					],
				],
				'defaults' => [],
			],
			$overrides
		);
	}

	/** `faq`: question/answer list. */
	public static function faq( array $overrides = [] ): array {
		return self::merge(
			[
				'id'       => 'faq',
				'type'     => 'faq',
				'label'    => __( 'FAQ', 'heartland-k9s-core' ),
				'fields'   => [
					[
						'type'    => 'text',
						'key'     => 'heading',
						'label'   => __( 'Heading', 'heartland-k9s-core' ),
						'default' => __( 'Frequently Asked Questions', 'heartland-k9s-core' ),
					],
					[
						'type'  => 'textarea',
						'key'   => 'intro',
						'label' => __( 'Intro', 'heartland-k9s-core' ),
						'rows'  => 3,
					],
					[
						'type'       => 'repeater',
						'key'        => 'items',
						'label'      => __( 'Questions', 'heartland-k9s-core' ),
						'item_label' => 'question',
						'add_label'  => __( 'Add question', 'heartland-k9s-core' ),
						'fields'     => [
							[
								'type'  => 'text',
								'key'   => 'question',
								'label' => __( 'Question', 'heartland-k9s-core' ),
							],
							[
								'type'  => 'richtext',
								'key'   => 'answer',
								'label' => __( 'Answer', 'heartland-k9s-core' ),
								'rows'  => 5,
							],
						],
					],
					[
						'type'    => 'text',
						'key'     => 'source_note',
						'label'   => __( 'Source note', 'heartland-k9s-core' ),
						'help'    => __( 'Internal note about where the answers come from. Not shown on the site.', 'heartland-k9s-core' ),
						'private' => true,
					],
				],
				'defaults' => [],
			],
			$overrides
		);
	}

	/**
	 * Form provider fields shared by the contact and application `form`
	 * sections: `provider` (inherit = Settings → Forms default), the Gravity
	 * Forms form picker (options from GFAPI when Gravity Forms is active; the
	 * id is stored as a numeric string by the select type) and a sanitized
	 * `shortcode` (only [shortcode] tags survive; executed with do_shortcode()).
	 *
	 * @return array[] Field definitions.
	 */
	public static function form_provider_fields(): array {
		$gravity_options = static function (): array {
			return class_exists( 'HK9\\Core\\Support\\FormProviders' ) ? \HK9\Core\Support\FormProviders::gravity_form_options() : [];
		};
		$gravity_help = class_exists( 'HK9\\Core\\Support\\FormProviders' )
			? \HK9\Core\Support\FormProviders::gravity_help()
			: __( 'Gravity Forms is not active.', 'heartland-k9s-core' );
		$providers    = class_exists( 'HK9\\Core\\Support\\FormProviders' )
			? \HK9\Core\Support\FormProviders::labels( true )
			: [
				'inherit'   => __( 'Site default (Settings → Forms)', 'heartland-k9s-core' ),
				'builtin'   => __( 'Built-in form (this plugin)', 'heartland-k9s-core' ),
				'gravity'   => __( 'Gravity Forms', 'heartland-k9s-core' ),
				'shortcode' => __( 'Form shortcode', 'heartland-k9s-core' ),
			];

		return [
			[
				'type'    => 'select',
				'key'     => 'provider',
				'label'   => __( 'Form provider', 'heartland-k9s-core' ),
				'help'    => __( '"Site default" follows Heartland → Settings → Forms → Default form provider. The built-in form emails the recipients configured there and stores submissions; Gravity Forms and shortcodes are handled by their own plugin.', 'heartland-k9s-core' ),
				'options' => $providers,
				'default' => 'inherit',
			],
			[
				'type'             => 'select',
				'key'              => 'gravity_form_id',
				'label'            => __( 'Gravity Forms form', 'heartland-k9s-core' ),
				'help'             => $gravity_help,
				'placeholder'      => __( '— Use the site default form —', 'heartland-k9s-core' ),
				'options'          => [],
				'options_callback' => $gravity_options,
				'default'          => '',
				'sanitize_callback' => static function ( mixed $value ): string {
					// Numeric id or '' (the select type stores strings; helpers cast to int).
					return is_scalar( $value ) && ctype_digit( (string) $value ) && (int) $value > 0 ? (string) (int) $value : '';
				},
			],
			[
				'type'              => 'text',
				'key'               => 'shortcode',
				'label'             => __( 'Form shortcode', 'heartland-k9s-core' ),
				'help'              => __( 'Shown when the provider is "Form shortcode", e.g. [gravityform id="2" title="false" ajax="true"] or another form plugin\'s shortcode. Only the [shortcode] itself is kept — other text and HTML are removed.', 'heartland-k9s-core' ),
				'placeholder'       => '[gravityform id="1" title="false" description="false" ajax="true"]',
				'sanitize_callback' => static function ( mixed $value ): string {
					return class_exists( 'HK9\\Core\\Support\\FormProviders' ) ? \HK9\Core\Support\FormProviders::sanitize_shortcode( $value ) : '';
				},
			],
		];
	}

	/** Common "mode" select for listing sections. */
	public static function mode_field( string $auto_label ): array {
		return [
			'type'    => 'select',
			'key'     => 'mode',
			'label'   => __( 'Source', 'heartland-k9s-core' ),
			'options' => [
				'auto'   => $auto_label,
				'manual' => __( 'Pick manually', 'heartland-k9s-core' ),
			],
			'default' => 'auto',
		];
	}
}
