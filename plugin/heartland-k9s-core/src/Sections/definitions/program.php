<?php
/**
 * Template: program (Program) — reference route "/program".
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'program',
	'label'    => __( 'Program', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_image(
			[
				'defaults' => [
					'eyebrow'             => '',
					'eyebrow_icon'        => '',
					'heading'             => 'The Program',
					'heading_break_after' => '',
					'text'                => 'Rigorous training, precise matching, and lifelong support.',
					'focal'               => 'center',
					'height'              => '60vh',
					'overlay'             => '70',
					'gradient'            => false,
					'buttons'             => [],
					'animate'             => false,
				],
			]
		),
		[
			'id'       => 'steps',
			'label'    => __( 'How it works (timeline)', 'heartland-k9s-core' ),
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
					'key'        => 'steps',
					'label'      => __( 'Steps', 'heartland-k9s-core' ),
					'item_label' => 'title',
					'add_label'  => __( 'Add step', 'heartland-k9s-core' ),
					'fields'     => [
						[
							'type'  => 'icon',
							'key'   => 'icon',
							'label' => __( 'Icon', 'heartland-k9s-core' ),
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
					],
				],
			],
			'defaults' => [
				'heading' => 'How It Works',
				'intro'   => 'Our decision-making process is informed by comprehensive empirical data. We don\'t just assign a dog; we carefully craft a team designed for mutual success and safety.',
				'steps'   => [
					[
						'icon'  => 'clipboard-check',
						'title' => 'Application & Assessment',
						'text'  => 'Veterans complete a thorough application detailing their needs, lifestyle, and environment. We review this to ensure our program is the right fit.',
					],
					[
						'icon'  => 'users',
						'title' => 'Precision Matching',
						'text'  => 'Using proven empirical strategies, we match the veteran with a canine whose temperament, drive, and skills perfectly complement the veteran\'s needs.',
					],
					[
						'icon'  => 'dog',
						'title' => 'Rigorous Training',
						'text'  => 'Our dogs undergo extensive training tailored to the specific disabilities of their matched veteran, learning precise commands and behaviors.',
					],
					[
						'icon'  => 'graduation-cap',
						'title' => 'Team Placement',
						'text'  => 'The veteran and dog train together as a unit. Upon successful completion, they graduate as a certified Heartland K9s team.',
					],
				],
			],
		],
		[
			'id'       => 'providers',
			'label'    => __( 'K9 providers', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'icon',
					'key'   => 'icon',
					'label' => __( 'Icon', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'text',
					'label' => __( 'Text', 'heartland-k9s-core' ),
					'rows'  => 4,
				],
				Shared::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) ),
			],
			'defaults' => [
				'icon'    => 'shield-alert',
				'heading' => 'Our K9 Providers',
				'text'    => 'We partner with elite K9 providers and trainers across the country. These professionals specialize in breeding and preparing working dogs of the highest caliber, ensuring that our veterans receive a partner capable of performing complex, life-saving tasks under any circumstance.',
				'button'  => Shared::link( 'View Our Providers', '/the-service-k9-program/' ),
			],
		],
		Shared::cta_band(
			'cta',
			[
				'defaults' => [
					'heading' => 'Are You a Veteran in Need?',
					'text'    => 'If you are a disabled U.S. veteran seeking the support of a service dog, we invite you to review our eligibility requirements and begin the application process.',
					'buttons' => [
						Shared::button( 'Review 5 Questions & Apply', '/veterans/', 'primary' ),
					],
					'tone'    => 'navy',
				],
			]
		),
	],
];
