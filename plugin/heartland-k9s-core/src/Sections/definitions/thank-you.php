<?php
/**
 * Template: thank-you (Thank You) — the page a veteran lands on after the
 * Initial Application Inquiry: band hero, the "next steps" card (reassurance,
 * numbered steps, the Medical History Form download, the return address from
 * Settings), a settings-driven help strip, "While You Wait" reading cards and
 * an optional (hidden by default) call to action.
 *
 * Every string is editable; the shipped copy states what happens, never when.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

$hk9_contact_source = [
	'type'    => 'select',
	'key'     => 'source',
	'label'   => __( 'Value source', 'heartland-k9s-core' ),
	'options' => [
		'phone'           => __( 'Main phone (Settings)', 'heartland-k9s-core' ),
		'phone_secondary' => __( 'Secondary phone (Settings)', 'heartland-k9s-core' ),
		'email'           => __( 'Email (Settings)', 'heartland-k9s-core' ),
		'hours'           => __( 'Office hours (Settings)', 'heartland-k9s-core' ),
		'address'         => __( 'Address (Settings)', 'heartland-k9s-core' ),
		'custom'          => __( 'Custom text', 'heartland-k9s-core' ),
	],
	'default' => 'custom',
	'help'    => __( 'Settings values come from Heartland → Settings → Contact. "Secondary phone" is the number labelled "Director cell" there — a personal mobile; add it only if the office wants it on this page.', 'heartland-k9s-core' ),
];

return [
	'template' => 'thank-you',
	'label'    => __( 'Thank You', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(
			[
				'defaults' => [
					'eyebrow' => 'Application received',
					'heading' => 'Thank You',
					'text'    => 'We have your Initial Application Inquiry. Here is the one thing we still need from you, and how to reach us if you have questions.',
					'pattern' => 'none',
				],
			]
		),
		[
			'id'          => 'next_steps',
			'type'        => 'next_steps_card',
			'label'       => __( 'Next steps card', 'heartland-k9s-core' ),
			'description' => __( 'The white card under the hero: reassurance, the numbered steps, the form download button and the return address. Anything written in the editor canvas is shown inside this card, after the address — use it for extra notes only (a holiday closure, a seasonal notice).', 'heartland-k9s-core' ),
			'fields'      => [
				[
					'type'    => 'icon',
					'key'     => 'icon',
					'label'   => __( 'Icon', 'heartland-k9s-core' ),
					'default' => 'circle-check',
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
					'type'  => 'text',
					'key'   => 'steps_heading',
					'label' => __( 'Steps heading', 'heartland-k9s-core' ),
				],
				[
					'type'       => 'repeater',
					'key'        => 'steps',
					'label'      => __( 'Steps', 'heartland-k9s-core' ),
					'max'        => 5,
					'item_label' => 'title',
					'add_label'  => __( 'Add step', 'heartland-k9s-core' ),
					'help'       => __( 'Describe what happens, not when: do not add response times, deadlines or eligibility promises.', 'heartland-k9s-core' ),
					'fields'     => [
						[
							'type'  => 'text',
							'key'   => 'title',
							'label' => __( 'Title', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'textarea',
							'key'   => 'text',
							'label' => __( 'Text', 'heartland-k9s-core' ),
							'rows'  => 2,
						],
					],
				],
				[
					'type'  => 'file',
					'key'   => 'file',
					'label' => __( 'Form to download', 'heartland-k9s-core' ),
					'mimes' => [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ],
					'help'  => __( 'PDF or Word document from the Media Library. The button below links to it; without a file the button is not shown.', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'text',
					'key'   => 'file_label',
					'label' => __( 'Download button label', 'heartland-k9s-core' ),
				],
				[
					'type'    => 'toggle',
					'key'     => 'show_file_meta',
					'label'   => __( 'Show the file type and size after the label (e.g. "PDF, 1.5 MB")', 'heartland-k9s-core' ),
					'default' => true,
				],
				[
					'type'    => 'toggle',
					'key'     => 'open_in_new_tab',
					'label'   => __( 'Open the file in a new tab', 'heartland-k9s-core' ),
					'default' => true,
				],
				[
					'type'  => 'textarea',
					'key'   => 'help_text',
					'label' => __( 'Line under the button', 'heartland-k9s-core' ),
					'rows'  => 2,
				],
				[
					'type'  => 'text',
					'key'   => 'address_heading',
					'label' => __( 'Return address heading', 'heartland-k9s-core' ),
				],
				[
					'type'    => 'toggle',
					'key'     => 'use_settings_address',
					'label'   => __( 'Show the organisation name and mailing address from Settings → Contact', 'heartland-k9s-core' ),
					'default' => true,
				],
				[
					'type'  => 'textarea',
					'key'   => 'address_custom',
					'label' => __( 'Custom address', 'heartland-k9s-core' ),
					'rows'  => 4,
					'help'  => __( 'One line per row. Used when the toggle above is off, or when Settings has no address.', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'tip',
					'label' => __( 'Tip under the address', 'heartland-k9s-core' ),
					'rows'  => 2,
				],
			],
			'defaults'    => [
				'icon'                 => 'circle-check',
				'heading'              => 'Your inquiry is on its way to our team',
				'text'                 => "Thank you for reaching out to Heartland Canines for Veterans. Taking this step takes courage, and we're glad you did. Our team will review your inquiry and be in touch.",
				'steps_heading'        => 'One more step: the Medical History Form',
				'steps'                => [
					[
						'title' => 'Download or print the form',
						'text'  => 'Use the button below to open the Medical History Form.',
					],
					[
						'title' => 'Have your physician complete it',
						'text'  => 'The form must be filled out and signed by your physician.',
					],
					[
						'title' => 'Return it to our office',
						'text'  => "Mail the completed form to the address below. Your physician's office can send it directly if that is easier.",
					],
					[
						'title' => 'We review your inquiry',
						'text'  => 'Our team will review your inquiry and the completed form together and be in touch.',
					],
				],
				'file'                 => 0,
				'file_label'           => 'Download the Medical History Form',
				'show_file_meta'       => true,
				'open_in_new_tab'      => true,
				'help_text'            => "Trouble opening the file? Call or email our office and we'll help you get a copy.",
				'address_heading'      => 'Mail the completed form to',
				'use_settings_address' => true,
				'address_custom'       => '',
				'tip'                  => 'Keep a copy of the completed form for your records before you mail it.',
			],
		],
		[
			'id'          => 'help',
			'type'        => 'contact_strip',
			'label'       => __( 'Help strip', 'heartland-k9s-core' ),
			'description' => __( 'Phone, email and hours are read from Heartland → Settings → Contact when the page renders; a row whose value is empty in Settings is skipped.', 'heartland-k9s-core' ),
			'fields'      => [
				[
					'type'  => 'text',
					'key'   => 'heading',
					'label' => __( 'Heading', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'textarea',
					'key'   => 'text',
					'label' => __( 'Text', 'heartland-k9s-core' ),
					'rows'  => 2,
				],
				[
					'type'       => 'repeater',
					'key'        => 'rows',
					'label'      => __( 'Rows', 'heartland-k9s-core' ),
					'max'        => 4,
					'item_label' => 'label',
					'add_label'  => __( 'Add row', 'heartland-k9s-core' ),
					'fields'     => [
						$hk9_contact_source,
						[
							'type'  => 'icon',
							'key'   => 'icon',
							'label' => __( 'Icon', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'text',
							'key'   => 'label',
							'label' => __( 'Label', 'heartland-k9s-core' ),
						],
						[
							'type'  => 'textarea',
							'key'   => 'value',
							'label' => __( 'Custom value', 'heartland-k9s-core' ),
							'rows'  => 2,
						],
						Shared::link_field( 'link', __( 'Link (optional)', 'heartland-k9s-core' ) ),
					],
				],
				Shared::link_field( 'link', __( 'Link under the rows (optional)', 'heartland-k9s-core' ) ),
				[
					'type'    => 'select',
					'key'     => 'tone',
					'label'   => __( 'Background', 'heartland-k9s-core' ),
					'options' => [
						'muted' => __( 'Muted panel', 'heartland-k9s-core' ),
						'plain' => __( 'Plain', 'heartland-k9s-core' ),
					],
					'default' => 'muted',
				],
			],
			'defaults'    => [
				'heading' => "Questions? We're here to help.",
				'text'    => 'If anything about the form or your inquiry is unclear, reach out during office hours.',
				'rows'    => [
					[
						'source' => 'phone',
						'icon'   => 'phone',
						'label'  => 'Call us',
						'value'  => '',
					],
					[
						'source' => 'email',
						'icon'   => 'mail',
						'label'  => 'Email us',
						'value'  => '',
					],
					[
						'source' => 'hours',
						'icon'   => 'clock',
						'label'  => 'Office hours',
						'value'  => '',
					],
				],
				'link'    => Shared::link( 'More ways to reach us', '/contact/' ),
				'tone'    => 'muted',
			],
		],
		Shared::feature_cards(
			'reading',
			[
				'label'       => __( 'While you wait', 'heartland-k9s-core' ),
				'description' => __( 'Optional reading for the applicant. Untick it under Page sections to end the page after the help strip.', 'heartland-k9s-core' ),
				'defaults'    => [
					'heading' => 'While You Wait',
					'intro'   => 'A few pages that answer the questions veterans ask us most.',
					'divider' => true,
					'align'   => 'center',
					'columns' => '3',
					'cards'   => [
						[
							'icon'     => 'circle-help',
							'tone'     => 'navy',
							'title'    => '5 Questions to Ask',
							'text'     => 'Things to think through before partnering with a service dog.',
							'link'     => Shared::link( 'Read the 5 questions', '/5-questions/' ),
							'decorate' => false,
						],
						[
							'icon'     => 'scale',
							'tone'     => 'navy',
							'title'    => 'Service Dogs and the ADA',
							'text'     => 'What the law says about where a service dog can go with you.',
							'link'     => Shared::link( 'Read the ADA FAQs', '/service-dogs-and-the-ada/' ),
							'decorate' => false,
						],
						[
							'icon'     => 'heart',
							'tone'     => 'crimson',
							'title'    => 'Success Stories',
							'text'     => 'Veterans and dogs who have gone through the program.',
							'link'     => Shared::link( 'Read their stories', '/stories/' ),
							'decorate' => false,
						],
					],
				],
			]
		),
		Shared::generic_cta(
			'cta',
			[
				'hidden_default' => true,
				'description'    => __( 'Hidden by default on this template: the reader has just asked for help. Tick it under Page sections if you want an ask here, and keep the copy free of promises.', 'heartland-k9s-core' ),
			]
		),
	],
];
