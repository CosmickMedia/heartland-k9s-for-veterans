<?php
/**
 * Template Name: BarKode Program
 * Template Post Type: page
 *
 * Reference route "/barkode": sections hero_image, story, protects, cta, rendered in the
 * order/visibility saved in the "Page sections" panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_render_sections( get_the_ID(), 'barkode' );
endwhile;

get_footer();
