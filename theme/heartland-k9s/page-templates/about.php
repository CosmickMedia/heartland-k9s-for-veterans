<?php
/**
 * Template Name: About / Mission
 * Template Post Type: page
 *
 * Reference route "/about": sections hero_band, legacy, values, cta, rendered in the
 * order/visibility saved in the "Page sections" panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_render_sections( get_the_ID(), 'about' );
endwhile;

get_footer();
