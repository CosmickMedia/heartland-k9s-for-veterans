<?php
/**
 * Template Name: Teams (listing)
 * Template Post Type: page
 *
 * Teams listing: band hero, team cards filtered by status, CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'teams' );
endwhile;

get_footer();
