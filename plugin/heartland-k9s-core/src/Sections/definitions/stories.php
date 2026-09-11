<?php
/**
 * Template: stories (Stories (listing)) — reference route "/stories".
 *
 * The reference testimonials are placeholders, so quote/name/meta default
 * to empty strings; the featured block defaults to the latest featured Story.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'stories',
	'label'    => __( 'Stories (listing)', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(
			[
				'defaults' => [
					'heading' => 'Success Stories',
					'text'    => 'The true measure of our mission is found in the lives we help restore.',
					'pattern' => 'none',
				],
			]
		),
		[
			'id'          => 'featured',
			'type'        => 'testimonial',
			'label'       => __( 'Featured story (overlap card)', 'heartland-k9s-core' ),
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
				],
				[
					'type'  => 'image',
					'key'   => 'image',
					'label' => __( 'Image', 'heartland-k9s-core' ),
				],
			],
			'defaults'    => [
				'source' => 'story',
				'story'  => 0,
				'quote'  => '',
				'name'   => '',
				'meta'   => '',
				'image'  => 0,
			],
		],
		[
			'id'       => 'list',
			'meta_key' => 'hk9_sec_stories_list',
			'label'    => __( 'Story list', 'heartland-k9s-core' ),
			'fields'   => [
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				Shared::mode_field( __( 'Latest published stories', 'heartland-k9s-core' ) ),
				[
					'type'      => 'relationship',
					'key'       => 'stories',
					'label'     => __( 'Stories (manual)', 'heartland-k9s-core' ),
					'post_type' => 'hk9_story',
					'multiple'  => true,
					'orderable' => true,
				],
				[
					'type'    => 'number',
					'key'     => 'count',
					'label'   => __( 'Number of stories', 'heartland-k9s-core' ),
					'min'     => 1,
					'max'     => 50,
					'default' => 6,
				],
				[
					'type'  => 'textarea',
					'key'   => 'empty_text',
					'label' => __( 'Empty state text', 'heartland-k9s-core' ),
					'rows'  => 2,
				],
			],
			'defaults' => [
				'heading'    => 'More Stories',
				'mode'       => 'auto',
				'stories'    => [],
				'count'      => 6,
				'empty_text' => 'Stories from our veteran and canine teams are on the way. Check back soon.',
			],
		],
		[
			'id'       => 'teams',
			'label'    => __( 'Teams in training', 'heartland-k9s-core' ),
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
				Shared::mode_field( __( 'Teams currently in training', 'heartland-k9s-core' ) ),
				[
					'type'      => 'relationship',
					'key'       => 'teams',
					'label'     => __( 'Teams (manual)', 'heartland-k9s-core' ),
					'post_type' => 'hk9_team',
					'multiple'  => true,
					'orderable' => true,
				],
				[
					'type'    => 'toggle',
					'key'     => 'show',
					'label'   => __( 'Show this block', 'heartland-k9s-core' ),
					'default' => true,
				],
			],
			'defaults' => [
				'heading' => 'Teams in Training',
				'intro'   => '',
				'mode'    => 'auto',
				'teams'   => [],
				'show'    => true,
			],
		],
		Shared::cta_band(
			'cta',
			[
				'defaults' => [
					'heading' => 'Help Us Write the Next Chapter',
					'text'    => 'There are many more eligible disabled veterans waiting for their lifeline. Your support makes these stories possible.',
					'buttons' => [
						Shared::button( 'Support Our Mission', '/donate/', 'primary' ),
					],
					'tone'    => 'plain',
				],
			]
		),
	],
];
