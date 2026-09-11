<?php
/**
 * Template Name: Photo Gallery
 * Template Post Type: page
 *
 * Photo gallery: band hero, gallery (block content gallery with the core lightbox, or the gallery_options images).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	hk9_rec_render_sections( get_the_ID(), 'gallery' );
endwhile;

get_footer();
