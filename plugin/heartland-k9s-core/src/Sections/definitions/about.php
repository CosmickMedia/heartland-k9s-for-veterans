<?php
/**
 * Template: about (About / Mission) — reference route "/about".
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'about',
	'label'    => __( 'About / Mission', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(
			[
				'defaults' => [
					'heading' => 'Our Mission',
					'text'    => 'Driven by a single goal: to do our part in making the world a better place for the veterans who have served this great nation.',
					'pattern' => 'none',
				],
			]
		),
		[
			'id'       => 'legacy',
			'label'    => __( 'Legacy (image + text card)', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'text',
					'key'   => 'eyebrow',
					'label' => __( 'Eyebrow badge', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'richtext',
					'key'   => 'body',
					'label' => __( 'Body', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'image',
					'key'   => 'image',
					'label' => __( 'Image', 'heartland-k9s-core' ),
				],
				[
					'type'    => 'select',
					'key'     => 'image_side',
					'label'   => __( 'Image position', 'heartland-k9s-core' ),
					'options' => [
						'left'  => __( 'Left', 'heartland-k9s-core' ),
						'right' => __( 'Right', 'heartland-k9s-core' ),
					],
					'default' => 'left',
				],
			],
			'defaults' => [
				'eyebrow'    => 'Est. 2015',
				'heading'    => 'A Legacy of Service',
				'body'       => '<p>Heartland Canines for Veterans was founded on a simple premise: a veteran has already paid the price. We believe that no eligible disabled veteran should face financial barriers to receiving a life-changing service dog.</p><p>We use proven canine training strategies to produce a highly skilled and capable team, ensuring the right canine is paired with the right person.</p>',
				'image'      => 0,
				'image_side' => 'left',
			],
		],
		Shared::feature_cards(
			'values',
			[
				'label'       => __( 'Core values', 'heartland-k9s-core' ),
				'description' => __( 'Muted cards with crimson icons.', 'heartland-k9s-core' ),
				'defaults'    => [
					'heading' => 'Our Core Values',
					'intro'   => '',
					'divider' => true,
					'align'   => 'left',
					'columns' => '3',
					'cards'   => [
						[
							'icon'  => 'shield-check',
							'tone'  => 'crimson',
							'title' => 'Dignity',
							'text'  => 'We treat every veteran with the utmost respect, ensuring the application and pairing process honors their service and sacrifices.',
						],
						[
							'icon'  => 'circle-check',
							'tone'  => 'crimson',
							'title' => 'Excellence',
							'text'  => 'We partner with premier K9 providers and utilize rigorous training standards to deliver highly capable service dogs.',
						],
						[
							'icon'  => 'heart',
							'tone'  => 'crimson',
							'title' => 'Commitment',
							'text'  => 'Our support doesn\'t end at placement. We remain committed to the veteran-canine team throughout their journey together.',
						],
					],
				],
			]
		),
		Shared::cta_band(
			'cta',
			[
				'defaults' => [
					'heading' => 'Join Us in Our Mission',
					'text'    => 'Every donation, volunteer hour, and shared story helps us provide another service dog to a veteran in need.',
					'buttons' => [
						Shared::button( 'Make a Donation', '/donate/', 'primary' ),
						Shared::button( 'Ways to Volunteer', '/get-involved/', 'outline' ),
					],
					'tone'    => 'tint',
				],
			]
		),
	],
];
