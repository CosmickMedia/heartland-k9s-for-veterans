<?php
/**
 * Template Name: Donate
 * Template Post Type: page
 *
 * Donate: band hero, ways-to-give grid, mail-in panel, tax statement, CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'donate' );
endwhile;

get_footer();
