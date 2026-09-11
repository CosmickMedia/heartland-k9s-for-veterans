<?php
/**
 * Template: campaigns (Campaigns (listing)).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'campaigns',
	'label'    => __( 'Campaigns (listing)', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'       => 'list',
			'type'     => 'campaigns_list',
			'meta_key' => 'hk9_sec_campaigns_list',
			'label'    => __( 'Campaign list', 'heartland-k9s-core' ),
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
				Shared::mode_field( __( 'All published campaigns', 'heartland-k9s-core' ) ),
				[
					'type'      => 'relationship',
					'key'       => 'campaigns',
					'label'     => __( 'Campaigns (manual)', 'heartland-k9s-core' ),
					'post_type' => 'hk9_campaign',
					'multiple'  => true,
					'orderable' => true,
				],
				[
					'type'    => 'toggle',
					'key'     => 'show_sponsors',
					'label'   => __( 'Show sponsor logos', 'heartland-k9s-core' ),
					'default' => true,
				],
			],
			'defaults' => [
				'heading'       => 'Campaigns',
				'intro'         => '',
				'mode'          => 'auto',
				'campaigns'     => [],
				'show_sponsors' => true,
			],
		],
		Shared::generic_cta(),
	],
];
