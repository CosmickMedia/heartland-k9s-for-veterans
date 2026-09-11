<?php
/**
 * Template Name: Home (sections)
 * Template Post Type: page
 *
 * Renders the Home sections (hero_image, mission, features, barkode_feature,
 * testimonial) in the order/visibility saved in the "Page sections" panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_render_sections( get_the_ID(), 'home' );
endwhile;

get_footer();
