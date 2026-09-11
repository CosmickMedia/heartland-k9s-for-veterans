<?php
/**
 * Template Name: Program
 * Template Post Type: page
 *
 * Reference route "/program": sections hero_image, steps, providers, cta, rendered in the
 * order/visibility saved in the "Page sections" panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_render_sections( get_the_ID(), 'program' );
endwhile;

get_footer();
