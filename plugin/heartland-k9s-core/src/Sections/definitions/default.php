<?php
/**
 * Template: default (page.php) — the template every new page gets.
 *
 * Navy band hero (title + excerpt by default; eyebrow / heading / intro /
 * pattern editable in the "Hero (band)" panel, and — unlike the reference
 * templates — it can be unticked under "Page sections" so a page starts
 * plainly with its title inside the content card), the block content card,
 * then an optional call-to-action band (hidden until switched on).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'default',
	'label'    => __( 'Default template', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(
			[
				'label'       => __( 'Hero (band)', 'heartland-k9s-core' ),
				'description' => __( 'Navy band at the top of the page. Heading defaults to the page title, text to the excerpt (Page → Excerpt in the sidebar). To start the page plainly, untick "Hero (band)" under Page sections: the title is then shown inside the content card.', 'heartland-k9s-core' ),
				'can_hide'    => true,
			]
		),
		Shared::generic_cta(
			'cta',
			[
				'hidden_default' => true,
				'description'    => __( 'Optional band after the content. Tick "Call to action" under Page sections to show it.', 'heartland-k9s-core' ),
			]
		),
	],
];
