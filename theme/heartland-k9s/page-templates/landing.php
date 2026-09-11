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

	hk9_rec_render_sections(
		$hk9_page_id,
		'landing',
		[
			'after' => [
				'hero_band' => static function () use ( $hk9_page_id, $hk9_spacer ): void {
					hk9_the_content_card( $hk9_page_id );
					if ( $hk9_spacer ) {
						echo '<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>';
					}
				},
			],
		]
	);
endwhile;

get_footer();
