<?php
/**
 * Template Name: Stories (listing)
 * Template Post Type: page
 *
 * Reference route "/stories": sections hero_band, featured, list, teams, cta, rendered in the
 * order/visibility saved in the "Page sections" panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_render_sections( get_the_ID(), 'stories' );
endwhile;

get_footer();
