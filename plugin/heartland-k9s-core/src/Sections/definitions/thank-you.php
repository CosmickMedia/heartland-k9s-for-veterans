<?php
/**
 * Template: thank-you (Thank You) — band hero + block content, optional CTA.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

use HK9\Core\Sections\Shared;

defined( 'ABSPATH' ) || exit;

return [
	'template' => 'thank-you',
	'label'    => __( 'Thank You', 'heartland-k9s-core' ),
	'sections' => [
		Shared::hero_band(),
		Shared::generic_cta( 'cta', [ 'hidden_default' => true ] ),
	],
];
