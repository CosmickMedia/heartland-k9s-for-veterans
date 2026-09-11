<?php
/**
 * Template: landing (Landing Page) — generic band hero + overlap card with
 * block content, plus optional sections hidden by default.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'landing',
	'label'    => __( 'Landing Page', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		Shared::feature_cards(
			'cards',
			[
				'label'          => __( 'Feature cards', 'heartland-k9s-core' ),
				'hidden_default' => true,
				'defaults'       => [
					'divider' => true,
					'align'   => 'left',
					'columns' => '3',
					'cards'   => [],
				],
			]
		),
		Shared::faq( [ 'hidden_default' => true ] ),
		[
			'id'             => 'tiers',
			'label'          => __( 'Sponsor tiers', 'heartland-k9s-core' ),
			'hidden_default' => true,
			'fields'         => [
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
					'label'      => __( 'Tiers', 'heartland-k9s-core' ),
					'item_label' => 'name',
					'add_label'  => __( 'Add tier', 'heartland-k9s-core' ),
					'fields'     => [
						[
							'type'  => 'text',
							'key'   => 'name',
							'label' => __( 'Name', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'text',
							'key'   => 'price',
							'label' => __( 'Price', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'text',
							'key'   => 'quantity',
							'label' => __( 'Quantity / availability', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'textarea',
							'key'   => 'benefits',
							'label' => __( 'Benefits (one per line)', 'heartland-k9s-core' ),
							'rows'  => 4,
						],
						[
							'type'  => 'toggle',
							'key'   => 'highlight',
							'label' => __( 'Highlight this tier', 'heartland-k9s-core' ),
						],
						Shared::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) ),
					],
				],
			],
			'defaults'       => [
				'heading' => '',
				'intro'   => '',
				'items'   => [],
			],
		],
		Shared::generic_cta( 'cta', [ 'hidden_default' => true ] ),
	],
];
