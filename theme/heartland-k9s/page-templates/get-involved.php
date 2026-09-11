<?php
/**
 * Template Name: Get Involved
 * Template Post Type: page
 *
 * Reference route "/get-involved": sections hero_band, ways, partners, rendered in the
 * order/visibility saved in the "Page sections" panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_render_sections( get_the_ID(), 'get-involved' );
endwhile;

get_footer();
