<?php
/**
 * Template Name: Landing Page
 * Template Post Type: page
 *
 * Landing page: band hero, block content in the overlapping card, then the
 * optional sections (feature cards, FAQ, sponsor tiers, CTA) in saved order.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id = get_the_ID();
	$hk9_layout  = hk9_sections_layout( $hk9_page_id, 'landing' );
	$hk9_spacer  = 'hero_band' === end( $hk9_layout ); // Nothing follows the content card.
	$hk9_crumbs  = function_exists( 'hk9_breadcrumbs' ) ? hk9_breadcrumbs() : ''; // inc/seo.php

	hk9_rec_render_sections(
		$hk9_page_id,
		'landing',
		[
			'after' => [
				'hero_band' => static function () use ( $hk9_page_id, $hk9_spacer, $hk9_crumbs ): void {
					if ( ! hk9_content_is_blank( $hk9_page_id ) ) {
						// The content card with the breadcrumbs as its first line.
						echo '<div class="hk9-overlap"><div class="hk9-overlap__card hk9-prose">';
						echo $hk9_crumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template part.
						the_content();
						echo '</div></div>';
					} elseif ( '' !== $hk9_crumbs ) {
						echo '<div class="hk9-breadcrumbs-strip"><div class="container">' . $hk9_crumbs . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template part.
					}
					if ( $hk9_spacer ) {
						echo '<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>';
					}
				},
			],
		]
	);
endwhile;

get_footer();
