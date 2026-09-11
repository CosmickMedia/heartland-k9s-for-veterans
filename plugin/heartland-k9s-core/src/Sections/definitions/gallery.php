<?php
/**
 * Template: gallery (Photo Gallery).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'gallery',
	'label'    => __( 'Photo Gallery', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'          => 'gallery_options',
			'label'       => __( 'Gallery options', 'heartland-k9s-core' ),
			'can_hide'    => false,
			'can_reorder' => false,
			'description' => __( 'The images below are used when the page content has no gallery block.', 'heartland-k9s-core' ),
			'fields'      => [
				[
					'type'    => 'toggle',
					'key'     => 'lightbox',
					'label'   => __( 'Open images in a lightbox', 'heartland-k9s-core' ),
					'default' => true,
				],
				[
					'type'    => 'select',
					'key'     => 'columns',
					'label'   => __( 'Columns', 'heartland-k9s-core' ),
					'options' => [
						'2' => '2',
						'3' => '3',
						'4' => '4',
					],
					'default' => '3',
				],
				[
					'type'    => 'toggle',
					'key'     => 'captions',
					'label'   => __( 'Show captions', 'heartland-k9s-core' ),
					'default' => true,
				],
				[
					'type'  => 'gallery',
					'key'   => 'images',
					'label' => __( 'Images', 'heartland-k9s-core' ),
				],
			],
			'defaults'    => [
				'lightbox' => true,
				'columns'  => '3',
				'captions' => true,
				'images'   => [],
			],
		],
	],
];
