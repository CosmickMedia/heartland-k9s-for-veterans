<?php
/**
 * Template: barkode (BarKode Program) — reference route "/barkode".
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'barkode',
	'label'    => __( 'BarKode Program', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_image(
			[
				'defaults' => [
					'eyebrow'             => 'Proprietary System',
					'eyebrow_icon'        => '',
					'heading'             => 'The BarKode Program',
					'heading_break_after' => '',
					'text'                => 'A vital safety net dedicated to the memory of Derron and his service dog, Rosie.',
					'focal'               => 'center',
					'height'              => '70vh',
					'overlay'             => '80',
					'gradient'            => true,
					'buttons'             => [],
					'animate'             => false,
				],
			]
		),
		[
			'id'       => 'story',
			'label'    => __( 'Dedication story', 'heartland-k9s-core' ),
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
					'type'  => 'richtext',
					'key'   => 'body',
					'label' => __( 'Body', 'heartland-k9s-core' ),
				],
			],
			'defaults' => [
				'icon'    => 'heart-pulse',
				'heading' => 'Dedicated to Derron & Rosie',
				'body'    => '<p>The BarKode program was born out of profound necessity and heartbreaking loss. It is dedicated to Derron, a veteran, and his beloved service dog, Rosie. Their story highlighted a critical gap in the safety protocols for working dog teams when emergencies strike.</p><p>When a veteran experiences a medical emergency in public, first responders need immediate access to vital information—not only about the veteran, but also about the service dog standing guard. Without clear identification and instructions, dogs can be separated from their handlers or misunderstood in chaotic situations. BarKode ensures this never happens again.</p>',
			],
		],
		Shared::feature_cards(
			'protects',
			[
				'label'    => __( 'How BarKode protects', 'heartland-k9s-core' ),
				'defaults' => [
					'heading' => 'How BarKode Protects',
					'intro'   => '',
					'divider' => true,
					'align'   => 'left',
					'columns' => '3',
					'cards'   => [
						[
							'icon'  => 'qr-code',
							'tone'  => 'navy',
							'title' => 'Instant Identification',
							'text'  => 'Every Heartland K9 wears a specialized patch featuring a unique BarKode. First responders can instantly identify the dog as a certified, trained service animal.',
						],
						[
							'icon'  => 'phone-call',
							'tone'  => 'navy',
							'title' => 'Emergency Contacts',
							'text'  => 'Scanning the BarKode provides immediate access to emergency contact protocols, ensuring the dog\'s well-being is managed safely while the veteran receives care.',
						],
						[
							'icon'  => 'shield-check',
							'tone'  => 'navy',
							'title' => 'Legitimacy & Trust',
							'text'  => 'In an era of fraudulent service dogs, BarKode serves as undeniable proof of the team\'s rigorous training and certification through Heartland K9s.',
						],
					],
				],
			]
		),
		Shared::cta_band(
			'cta',
			[
				'defaults' => [
					'heading' => 'Support the BarKode Initiative',
					'text'    => 'Providing these specialized patches and maintaining the underlying registry requires ongoing resources. Your donation directly supports the safety of our veterans and their K9 partners.',
					'buttons' => [
						Shared::button( 'Donate to Protect a Team', '/donate/', 'primary' ),
					],
					'tone'    => 'plain',
				],
			]
		),
	],
];
