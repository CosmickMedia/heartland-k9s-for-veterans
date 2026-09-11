<?php
/**
 * Template Name: People (Meet the Team)
 * Template Post Type: page
 *
 * People: band hero, people grid, CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'people' );
endwhile;

get_footer();
