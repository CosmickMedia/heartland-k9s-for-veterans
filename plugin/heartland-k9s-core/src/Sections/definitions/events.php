<?php
/**
 * Template: events (Events (listing)).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'events',
	'label'    => __( 'Events (listing)', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'       => 'upcoming',
			'label'    => __( 'Upcoming events', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'empty_text',
					'label' => __( 'Empty state text', 'heartland-k9s-core' ),
					'rows'  => 2,
				],
				[
					'type'    => 'number',
					'key'     => 'count',
					'label'   => __( 'Number of events', 'heartland-k9s-core' ),
					'min'     => 1,
					'max'     => 50,
					'default' => 10,
				],
			],
			'defaults' => [
				'heading'    => 'Upcoming Events',
				'empty_text' => 'There are no upcoming events scheduled right now. Check back soon or follow us on Facebook.',
				'count'      => 10,
			],
		],
		[
			'id'       => 'past',
			'label'    => __( 'Past events', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'    => 'toggle',
					'key'     => 'show',
					'label'   => __( 'Show past events', 'heartland-k9s-core' ),
					'default' => true,
				],
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'    => 'number',
					'key'     => 'count',
					'label'   => __( 'Number of events', 'heartland-k9s-core' ),
					'min'     => 1,
					'max'     => 50,
					'default' => 6,
				],
			],
			'defaults' => [
				'show'    => true,
				'heading' => 'Past Events',
				'count'   => 6,
			],
		],
		Shared::generic_cta(),
	],
];
