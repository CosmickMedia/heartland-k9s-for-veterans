<?php
/**
 * Template Name: Partners (Back the Pack)
 * Template Post Type: page
 *
 * Partners: band hero, partner logo grid by type, CTA.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'partners' );
endwhile;

get_footer();
