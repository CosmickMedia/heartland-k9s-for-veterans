<?php
/**
 * Template: application (Application) — the intro is block content.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'application',
	'label'    => __( 'Application', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'          => 'form',
			'type'        => 'application_form',
			'meta_key'    => 'hk9_sec_application_form',
			'label'       => __( 'Application form', 'heartland-k9s-core' ),
			'can_hide'    => false,
			'can_reorder' => false,
			'fields'      => [
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'notice',
					'label' => __( 'Notice above the form', 'heartland-k9s-core' ),
					'rows'  => 3,
				],
				Shared::link_field( 'success_page', __( 'Success page', 'heartland-k9s-core' ), [ 'post_types' => [ 'page' ] ] ),
				[
					'type'    => 'toggle',
					'key'     => 'show_five_questions_link',
					'label'   => __( 'Show the "5 Questions" link', 'heartland-k9s-core' ),
					'default' => true,
				],
			],
			'defaults'    => [
				'heading'                  => 'Initial Application Inquiry',
				'notice'                   => '',
				'success_page'             => Shared::link( '', '/thank-you/' ),
				'show_five_questions_link' => true,
			],
		],
	],
];
