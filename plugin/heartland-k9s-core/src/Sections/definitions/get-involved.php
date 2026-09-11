<?php
/**
 * Template: get-involved (Get Involved) — reference route "/get-involved".
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'get-involved',
	'label'    => __( 'Get Involved', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(
			[
				'defaults' => [
					'heading' => 'Get Involved',
					'text'    => 'It takes a community to save a life. Join us in providing independence to those who served.',
					'pattern' => 'none',
				],
			]
		),
		[
			'id'       => 'ways',
			'type'     => 'ways',
			'label'    => __( 'Ways to help (cards with buttons)', 'heartland-k9s-core' ),
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
					'key'        => 'cards',
					'label'      => __( 'Cards', 'heartland-k9s-core' ),
					'item_label' => 'title',
					'add_label'  => __( 'Add card', 'heartland-k9s-core' ),
					'fields'     => Shared::card_fields( true ),
				],
			],
			'defaults' => [
				'heading' => '',
				'intro'   => '',
				'cards'   => [
					[
						'icon'         => 'dollar-sign',
						'tone'         => 'crimson',
						'title'        => 'Donate',
						'text'         => 'Your financial support directly funds the acquisition, training, and placement of service dogs for veterans. Every dollar makes a tangible difference.',
						'button'       => Shared::link( 'Make a Donation', '/donate/' ),
						'button_style' => 'primary',
					],
					[
						'icon'         => 'calendar',
						'tone'         => 'navy',
						'title'        => 'Campaigns',
						'text'         => 'Participate in our fundraising campaigns and events throughout the year. Help us spread awareness and raise crucial funds for our mission.',
						'button'       => Shared::link( 'View Campaigns', '/campaigns/' ),
						'button_style' => 'outline',
					],
					[
						'icon'         => 'hand-heart',
						'tone'         => 'navy',
						'title'        => 'Volunteer',
						'text'         => 'Give your time and skills. Whether helping at events, assisting with administration, or spreading the word, volunteers are the backbone of our organization.',
						'button'       => Shared::link( 'Contact Us to Help', '/contact/' ),
						'button_style' => 'outline',
					],
				],
			],
		],
		[
			'id'       => 'partners',
			'label'    => __( 'Corporate & community partners', 'heartland-k9s-core' ),
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
				Shared::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) ),
				[
					'type'  => 'toggle',
					'key'   => 'show_logos',
					'label' => __( 'Show partner logos', 'heartland-k9s-core' ),
				],
			],
			'defaults' => [
				'icon'       => 'heart',
				'heading'    => 'Corporate & Community Partners',
				'body'       => '<p>We are deeply grateful for the businesses and organizations that stand with us. Partnering with Heartland K9s demonstrates a profound commitment to our nation\'s veterans.</p><p>If your organization is interested in sponsorship opportunities, matching gifts, or hosting an event, we would love to connect.</p>',
				'button'     => Shared::link( 'Discuss a Partnership', '/contact/' ),
				'show_logos' => false,
			],
		],
	],
];
