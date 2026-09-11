<?php
/**
 * Template Name: For Veterans
 * Template Post Type: page
 *
 * Reference route "/veterans": sections hero_band, questions, expect, ada, rendered in the
 * order/visibility saved in the "Page sections" panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_render_sections( get_the_ID(), 'veterans' );
endwhile;

get_footer();
