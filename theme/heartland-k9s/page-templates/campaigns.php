<?php
/**
 * Template Name: Campaigns (listing)
 * Template Post Type: page
 *
 * Campaigns listing: band hero, campaign cards (manual/auto order), CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'campaigns' );
endwhile;

get_footer();
