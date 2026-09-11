<?php
/**
 * Template: contact (Contact) — reference route "/contact".
 *
 * Info rows with a non-custom `source` pull their value from Settings →
 * Contact at render time; `value` is only used for 'custom' rows.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'contact',
	'label'    => __( 'Contact', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(
			[
				'defaults' => [
					'heading' => 'Contact Us',
					'text'    => 'We are here to answer your questions, discuss partnerships, and support our veterans.',
					'pattern' => 'none',
				],
			]
		),
		[
			'id'       => 'info',
			'label'    => __( 'Contact info column', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'       => 'repeater',
					'key'        => 'rows',
					'label'      => __( 'Rows', 'heartland-k9s-core' ),
					'item_label' => 'label',
					'add_label'  => __( 'Add row', 'heartland-k9s-core' ),
					'fields'     => [
						[
							'type'    => 'select',
							'key'     => 'source',
							'label'   => __( 'Value source', 'heartland-k9s-core' ),
							'options' => [
								'phone'           => __( 'Main phone (Settings)', 'heartland-k9s-core' ),
								'phone_secondary' => __( 'Secondary phone (Settings)', 'heartland-k9s-core' ),
								'email'           => __( 'Email (Settings)', 'heartland-k9s-core' ),
								'hours'           => __( 'Office hours (Settings)', 'heartland-k9s-core' ),
								'address'         => __( 'Address (Settings)', 'heartland-k9s-core' ),
								'custom'          => __( 'Custom text', 'heartland-k9s-core' ),
							],
							'default' => 'custom',
						],
						[
							'type'  => 'icon',
							'key'   => 'icon',
							'label' => __( 'Icon', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'text',
							'key'   => 'label',
							'label' => __( 'Label', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'textarea',
							'key'   => 'value',
							'label' => __( 'Custom value', 'heartland-k9s-core' ),
							'rows'  => 2,
						],
						Shared::link_field( 'link', __( 'Link (optional)', 'heartland-k9s-core' ) ),
					],
				],
			],
			'defaults' => [
				'heading' => 'Get in Touch',
				'rows'    => [
					[
						'source' => 'phone',
						'icon'   => 'phone',
						'label'  => 'Phone',
						'value'  => '',
					],
					[
						'source' => 'email',
						'icon'   => 'mail',
						'label'  => 'Email',
						'value'  => '',
					],
					[
						'source' => 'hours',
						'icon'   => 'clock',
						'label'  => 'Office Hours',
						'value'  => '',
					],
					[
						'source' => 'address',
						'icon'   => 'map-pin',
						'label'  => 'Address',
						'value'  => '',
					],
				],
			],
		],
		[
			'id'       => 'form',
			'meta_key' => 'hk9_sec_contact_form',
			'label'    => __( 'Form column', 'heartland-k9s-core' ),
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
					'rows'  => 2,
				],
				[
					'type'    => 'select',
					'key'     => 'form',
					'label'   => __( 'Form', 'heartland-k9s-core' ),
					'options' => [
						'contact'     => __( 'Contact form', 'heartland-k9s-core' ),
						'application' => __( 'Application inquiry form', 'heartland-k9s-core' ),
					],
					'default' => 'contact',
				],
				[
					'type'  => 'text',
					'key'   => 'success_heading',
					'label' => __( 'Success heading', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'success_text',
					'label' => __( 'Success text', 'heartland-k9s-core' ),
					'rows'  => 2,
				],
			],
			'defaults' => [
				'heading'         => 'Send a Message',
				'intro'           => '',
				'form'            => 'contact',
				'success_heading' => 'Message Sent',
				'success_text'    => 'Thank you for reaching out. We will get back to you shortly.',
			],
		],
	],
];
