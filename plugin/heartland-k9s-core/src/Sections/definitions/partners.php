<?php
/**
 * Template: partners (Partners (Back the Pack)).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'partners',
	'label'    => __( 'Partners (Back the Pack)', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		[
			'id'       => 'logos',
			'label'    => __( 'Partner logos', 'heartland-k9s-core' ),
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
				Shared::mode_field( __( 'All partners of the selected type', 'heartland-k9s-core' ) ),
				[
					'type'             => 'select',
					'key'              => 'type',
					'label'            => __( 'Partner type', 'heartland-k9s-core' ),
					'options'          => [
						'back-the-pack'    => 'Back the Pack Partner',
						'campaign-sponsor' => 'Campaign Sponsor',
						'provider'         => 'K9 Provider',
						'community'        => 'Community Partner',
					],
					'options_callback' => static function (): array {
						$out = [];
						if ( taxonomy_exists( 'hk9_partner_type' ) ) {
							$terms = get_terms(
								[
									'taxonomy'   => 'hk9_partner_type',
									'hide_empty' => false,
								]
							);
							if ( is_array( $terms ) ) {
								foreach ( $terms as $term ) {
									$out[ $term->slug ] = $term->name;
								}
							}
						}
						if ( empty( $out ) ) {
							$out = [
								'back-the-pack'    => 'Back the Pack Partner',
								'campaign-sponsor' => 'Campaign Sponsor',
								'provider'         => 'K9 Provider',
								'community'        => 'Community Partner',
							];
						}
						return $out;
					},
					'default'          => 'back-the-pack',
				],
				[
					'type'      => 'relationship',
					'key'       => 'partners',
					'label'     => __( 'Partners (manual)', 'heartland-k9s-core' ),
					'post_type' => 'hk9_partner',
					'multiple'  => true,
					'orderable' => true,
				],
				[
					'type'    => 'select',
					'key'     => 'columns',
					'label'   => __( 'Columns', 'heartland-k9s-core' ),
					'options' => [
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					],
					'default' => '4',
				],
			],
			'defaults' => [
				'heading'  => 'Back the Pack Partners',
				'intro'    => '',
				'mode'     => 'auto',
				'type'     => 'back-the-pack',
				'partners' => [],
				'columns'  => '4',
			],
		],
		Shared::generic_cta(),
	],
];
