<?php
/**
 * Template: donate (Donate).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

$hk9_tax_default = function_exists( 'hk9_option' ) ? (string) hk9_option( 'contact.tax_statement', '' ) : '';
if ( '' === $hk9_tax_default ) {
	$hk9_tax_default = 'Heartland Canines for Veterans Inc is an IRS-recognized 501(c)(3) nonprofit organization. EIN 47-4991572.';
}

return [
	'template' => 'donate',
	'label'    => __( 'Donate', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'       => 'options',
			'label'    => __( 'Ways to give', 'heartland-k9s-core' ),
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
					'type'       => 'repeater',
					'key'        => 'items',
					'label'      => __( 'Options', 'heartland-k9s-core' ),
					'item_label' => 'title',
					'add_label'  => __( 'Add option', 'heartland-k9s-core' ),
					'fields'     => [
						[
							'type'  => 'icon',
							'key'   => 'icon',
							'label' => __( 'Icon', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'image',
							'key'   => 'logo',
							'label' => __( 'Logo (optional, replaces the icon)', 'heartland-k9s-core' ),
							'size'  => 'thumbnail',
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
						Shared::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) ),
						[
							'type'  => 'toggle',
							'key'   => 'primary',
							'label' => __( 'Primary option', 'heartland-k9s-core' ),
						],
					],
				],
			],
			'defaults' => [
				'heading' => 'Ways to Give',
				'intro'   => '',
				'items'   => [],
			],
		],
		[
			'id'       => 'mail_in',
			'label'    => __( 'Donate by mail', 'heartland-k9s-core' ),
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
					'type'    => 'toggle',
					'key'     => 'use_settings_address',
					'label'   => __( 'Show the mailing address from Settings', 'heartland-k9s-core' ),
					'default' => true,
				],
			],
			'defaults' => [
				'heading'              => 'Donate by Mail',
				'text'                 => '',
				'use_settings_address' => true,
			],
		],
		[
			'id'       => 'tax',
			'label'    => __( 'Tax statement', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'textarea',
					'key'   => 'text',
					'label' => __( 'Text', 'heartland-k9s-core' ),
					'rows'  => 3,
				],
			],
			'defaults' => [
				'text' => $hk9_tax_default,
			],
		],
		Shared::generic_cta(),
	],
];
