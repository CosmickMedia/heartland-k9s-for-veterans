<?php
/**
 * Template Name: Events (listing)
 * Template Post Type: page
 *
 * Events listing: band hero, upcoming events (with empty state), past events, CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'events' );
endwhile;

get_footer();
