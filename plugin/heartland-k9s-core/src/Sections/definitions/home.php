<?php
/**
 * Template: home (Home (sections)) — reference route "/".
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'home',
	'label'    => __( 'Home (sections)', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_image(
			[
				'defaults' => [
					'eyebrow'             => 'IRS-Recognized 501(c)(3) Nonprofit',
					'eyebrow_icon'        => 'shield-check',
					'heading'             => 'So They Never Walk Alone.',
					'heading_break_after' => 'Never',
					'text'                => 'Pairing eligible disabled U.S. veterans with highly trained service dogs at zero cost. Restoring independence, dignity, and purpose.',
					'focal'               => 'center',
					'height'              => '85vh',
					'overlay'             => '60',
					'gradient'            => true,
					'buttons'             => [
						Shared::button( 'Support a Service Dog', '/donate/', 'primary' ),
						Shared::button( 'Apply for a Dog', '/veterans/', 'outline-light' ),
					],
					'animate'             => true,
				],
			]
		),
		[
			'id'       => 'mission',
			'label'    => __( 'Mission statement', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'    => 'toggle',
					'key'     => 'divider',
					'label'   => __( 'Show crimson divider', 'heartland-k9s-core' ),
					'default' => true,
				],
				[
					'type'  => 'textarea',
					'key'   => 'text',
					'label' => __( 'Text', 'heartland-k9s-core' ),
					'rows'  => 5,
				],
			],
			'defaults' => [
				'heading' => 'We believe those who served our nation deserve the highest level of care and support upon their return.',
				'divider' => true,
				'text'    => 'At Heartland Canines for Veterans, our mission is simple yet profound: to place exceptional service dogs with disabled veterans at no financial burden to the veteran. Through empirical strategies, rigorous training, and a deep understanding of our veterans\' needs, we forge partnerships that save lives.',
			],
		],
		Shared::feature_cards(
			'features',
			[
				'label'    => __( 'Feature cards', 'heartland-k9s-core' ),
				'defaults' => [
					'heading' => '',
					'intro'   => '',
					'divider' => false,
					'align'   => 'center',
					'columns' => '3',
					'cards'   => [
						[
							'icon'     => 'shield-check',
							'tone'     => 'navy',
							'title'    => 'Zero Cost to Veterans',
							'text'     => 'We believe a veteran has already paid the price. Every service dog, complete with training and equipment, is provided entirely free of charge.',
							'link'     => Shared::link( 'Learn about funding', '/program/' ),
							'decorate' => false,
						],
						[
							'icon'     => 'heart-handshake',
							'tone'     => 'crimson',
							'title'    => 'Unbreakable Bond',
							'text'     => 'We don\'t just hand over a leash. We ensure the right canine is paired with the right person, fostering a bond built on trust and mutual support.',
							'link'     => Shared::link( 'Our matching process', '/program/' ),
							'decorate' => true,
						],
						[
							'icon'     => 'map-pin',
							'tone'     => 'navy',
							'title'    => 'Nationwide Reach',
							'text'     => 'While our roots are in the heartland, our impact spans the country. We serve eligible disabled veterans wherever they call home.',
							'link'     => Shared::link( 'Read our story', '/about/' ),
							'decorate' => false,
						],
					],
				],
			]
		),
		[
			'id'       => 'barkode_feature',
			'label'    => __( 'BarKode feature', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'text',
					'key'   => 'eyebrow',
					'label' => __( 'Eyebrow', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'text',
					'label' => __( 'Text', 'heartland-k9s-core' ),
					'rows'  => 4,
				],
				[
					'type'  => 'image',
					'key'   => 'image',
					'label' => __( 'Image', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'text',
					'key'   => 'image_caption',
					'label' => __( 'Image caption', 'heartland-k9s-core' ),
				],
				Shared::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) ),
				[
					'type'    => 'toggle',
					'key'     => 'pattern',
					'label'   => __( 'Show diagonal grid pattern', 'heartland-k9s-core' ),
					'default' => true,
				],
			],
			'defaults' => [
				'eyebrow'       => 'Proprietary System',
				'heading'       => 'The BarKode Program',
				'text'          => 'Every Heartland K9 is equipped with a specialized BarKode patch. This vital tool serves as a certification beacon and emergency contact system, ensuring the safety of the veteran and their dog in critical situations.',
				'image_caption' => 'Dedicated to Derron & Rosie',
				'button'        => Shared::link( 'Explore BarKode', '/barkode/' ),
				'pattern'       => true,
			],
		],
		[
			'id'          => 'testimonial',
			'label'       => __( 'Testimonial', 'heartland-k9s-core' ),
			'description' => __( 'Pull the quote from a published Story (leave the story empty to use the latest featured one), or enter a verified quote manually.', 'heartland-k9s-core' ),
			'fields'      => [
				[
					'type'    => 'select',
					'key'     => 'source',
					'label'   => __( 'Source', 'heartland-k9s-core' ),
					'options' => [
						'story'  => __( 'From a Story', 'heartland-k9s-core' ),
						'manual' => __( 'Manual quote', 'heartland-k9s-core' ),
					],
					'default' => 'story',
				],
				[
					'type'      => 'relationship',
					'key'       => 'story',
					'label'     => __( 'Story', 'heartland-k9s-core' ),
					'post_type' => 'hk9_story',
				],
				[
					'type'  => 'textarea',
					'key'   => 'quote',
					'label' => __( 'Quote', 'heartland-k9s-core' ),
					'rows'  => 4,
				],
				[
					'type'  => 'text',
					'key'   => 'name',
					'label' => __( 'Name', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'text',
					'key'   => 'meta',
					'label' => __( 'Attribution line', 'heartland-k9s-core' ),
					'help'  => __( 'e.g. branch of service and role. Only publish verified details.', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'image',
					'key'   => 'image',
					'label' => __( 'Image', 'heartland-k9s-core' ),
				],
				Shared::link_field( 'button', __( 'Button', 'heartland-k9s-core' ) ),
			],
			'defaults'    => [
				'source' => 'story',
				'story'  => 0,
				'quote'  => '',
				'name'   => '',
				'meta'   => '',
				'image'  => 0,
				'button' => Shared::link( 'Read More Stories', '/stories/' ),
			],
		],
	],
];
