<?php
/**
 * Template: veterans (For Veterans) — reference route "/veterans".
 *
 * The reference's five yes/no "eligibility questions" are not the live
 * site's 5 Questions, so the `items` default is intentionally empty: the
 * payload supplies the verified questions.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'veterans',
	'label'    => __( 'For Veterans', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(
			[
				'defaults' => [
					'heading' => 'For Veterans',
					'text'    => 'Your service earned this. We are here to provide the support you deserve at zero cost.',
					'pattern' => 'stars',
				],
			]
		),
		[
			'id'       => 'questions',
			'label'    => __( 'The 5 Questions (checklist card)', 'heartland-k9s-core' ),
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
					],
				],
				[
					'type'  => 'textarea',
					'key'   => 'footer_text',
					'label' => __( 'Footer text', 'heartland-k9s-core' ),
					'rows'  => 3,
				],
				Shared::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) ),
				Shared::link_field( 'secondary_link', __( 'Secondary link', 'heartland-k9s-core' ) ),
			],
			'defaults' => [
				'heading'        => 'The 5 Questions',
				'intro'          => 'Basic eligibility requirements before applying.',
				'items'          => [],
				'footer_text'    => 'If you answered "Yes" to these questions, you may be eligible to receive a highly trained service dog from Heartland Canines for Veterans.',
				'button'         => Shared::link( 'Start Application', '/online-application/' ),
				'secondary_link' => Shared::link( '', '' ),
			],
		],
		[
			'id'       => 'expect',
			'label'    => __( 'What to expect', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'       => 'repeater',
					'key'        => 'steps',
					'label'      => __( 'Steps', 'heartland-k9s-core' ),
					'item_label' => 'title',
					'add_label'  => __( 'Add step', 'heartland-k9s-core' ),
					'fields'     => [
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
				[
					'type'  => 'icon',
					'key'   => 'card_icon',
					'label' => __( 'Card icon', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'text',
					'key'   => 'card_title',
					'label' => __( 'Card title', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'card_text',
					'label' => __( 'Card text', 'heartland-k9s-core' ),
					'rows'  => 4,
				],
				Shared::link_field( 'card_button', __( 'Card button', 'heartland-k9s-core' ) ),
			],
			'defaults' => [
				'heading'     => 'What to Expect',
				'steps'       => [
					[
						'title' => 'Application Review',
						'text'  => 'Our team carefully reviews your application to understand your unique needs and circumstances.',
					],
					[
						'title' => 'Interview & Assessment',
						'text'  => 'We conduct in-depth interviews to ensure our program is the right fit and to gather data for a precision match.',
					],
					[
						'title' => 'Team Training',
						'text'  => 'You will train alongside your matched dog under the guidance of our expert handlers.',
					],
				],
				'card_icon'   => 'shield-check',
				'card_title'  => 'The BarKode System',
				'card_text'   => 'As a Heartland K9s recipient, your service dog will be equipped with our proprietary BarKode system—a digital certification and emergency contact patch designed to protect both you and your canine partner in any situation.',
				'card_button' => Shared::link( 'Learn about BarKode', '/barkode/' ),
			],
		],
		Shared::cta_band(
			'ada',
			[
				'label'    => __( 'ADA rights band', 'heartland-k9s-core' ),
				'defaults' => [
					'heading' => 'ADA Rights & FAQs',
					'text'    => 'As a service dog handler, you have specific rights protected under the Americans with Disabilities Act (ADA). We provide comprehensive education on these rights during your training.',
					'buttons' => [
						Shared::button( 'Read ADA FAQs', '/service-dogs-and-the-ada/', 'outline-light' ),
					],
					'tone'    => 'navy',
				],
			]
		),
	],
];
