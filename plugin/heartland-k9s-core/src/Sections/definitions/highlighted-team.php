<?php
/**
 * Template: highlighted-team (Highlighted Team).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'highlighted-team',
	'label'    => __( 'Highlighted Team', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'       => 'team',
			'label'    => __( 'Highlighted team', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'      => 'relationship',
					'key'       => 'team',
					'label'     => __( 'Team', 'heartland-k9s-core' ),
					'post_type' => 'hk9_team',
					'help'      => __( 'Leave empty to show the featured team.', 'heartland-k9s-core' ),
				],
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
			],
			'defaults' => [
				'team'    => 0,
				'heading' => 'Our Highlighted Team',
				'intro'   => '',
			],
		],
		Shared::generic_cta(),
	],
];
