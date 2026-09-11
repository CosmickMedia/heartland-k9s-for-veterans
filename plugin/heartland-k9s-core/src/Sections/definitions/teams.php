<?php
/**
 * Template: teams (Teams (listing)).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'teams',
	'label'    => __( 'Teams (listing)', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'       => 'list',
			'type'     => 'teams_list',
			'meta_key' => 'hk9_sec_teams_list',
			'label'    => __( 'Team list', 'heartland-k9s-core' ),
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
				Shared::mode_field( __( 'All teams with the selected status', 'heartland-k9s-core' ) ),
				[
					'type'    => 'select',
					'key'     => 'status',
					'label'   => __( 'Status filter', 'heartland-k9s-core' ),
					'options' => [
						'all'         => __( 'All', 'heartland-k9s-core' ),
						'in-training' => __( 'In training', 'heartland-k9s-core' ),
						'graduated'   => __( 'Graduated', 'heartland-k9s-core' ),
						'therapy'     => __( 'Therapy', 'heartland-k9s-core' ),
					],
					'default' => 'in-training',
				],
				[
					'type'      => 'relationship',
					'key'       => 'teams',
					'label'     => __( 'Teams (manual)', 'heartland-k9s-core' ),
					'post_type' => 'hk9_team',
					'multiple'  => true,
					'orderable' => true,
				],
			],
			'defaults' => [
				'heading' => 'Current Teams in Training',
				'intro'   => '',
				'mode'    => 'auto',
				'status'  => 'in-training',
				'teams'   => [],
			],
		],
		Shared::generic_cta(),
	],
];
