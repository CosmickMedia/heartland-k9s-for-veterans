<?php
/**
 * Template Name: Highlighted Team
 * Template Post Type: page
 *
 * Highlighted team: band hero, featured team panel, CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'highlighted-team' );
endwhile;

get_footer();
