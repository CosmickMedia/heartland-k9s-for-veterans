<?php
/**
 * Template: people (People (Meet the Team)).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'people',
	'label'    => __( 'People (Meet the Team)', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'       => 'grid',
			'label'    => __( 'People grid', 'heartland-k9s-core' ),
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
				Shared::mode_field( __( 'All people (by order)', 'heartland-k9s-core' ) ),
				[
					'type'      => 'relationship',
					'key'       => 'people',
					'label'     => __( 'People (manual)', 'heartland-k9s-core' ),
					'post_type' => 'hk9_person',
					'multiple'  => true,
					'orderable' => true,
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
			],
			'defaults' => [
				'heading' => 'Meet the Team',
				'intro'   => '',
				'mode'    => 'auto',
				'people'  => [],
				'columns' => '3',
			],
		],
		Shared::generic_cta(),
	],
];
