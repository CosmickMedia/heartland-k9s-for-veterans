<?php
/**
 * Template: default (page.php) — band hero + block content card, optional CTA.
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
		Shared::hero_band(),
		Shared::generic_cta( 'cta', [ 'hidden_default' => true ] ),
	],
];
