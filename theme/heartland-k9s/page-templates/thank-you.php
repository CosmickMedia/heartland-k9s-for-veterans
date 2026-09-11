<?php
/**
 * Template Name: Thank You
 * Template Post Type: page
 *
 * Thank-you page: band hero, block content card (next steps), optional CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id = get_the_ID();
	$hk9_layout  = hk9_sections_layout( $hk9_page_id, 'thank-you' );
	$hk9_spacer  = 'hero_band' === end( $hk9_layout ); // Nothing follows the content card.

	hk9_rec_render_sections(
		$hk9_page_id,
		'thank-you',
		[
			'after' => [
				'hero_band' => static function () use ( $hk9_page_id, $hk9_spacer ): void {
					if ( '' !== trim( (string) get_post_field( 'post_content', $hk9_page_id ) ) ) {
						echo '<div class="hk9-overlap hk9-thank-you"><div class="hk9-overlap__card hk9-thank-you__card"><span class="hk9-icon-well hk9-icon-well--crimson hk9-icon-well--lg hk9-thank-you__icon">' . hk9_icon( 'circle-check', [ 'size' => 32 ] ) . '</span><div class="hk9-prose hk9-thank-you__content">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
						the_content();
						echo '</div></div></div>';
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
