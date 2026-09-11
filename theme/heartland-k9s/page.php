<?php
/**
 * Default page template: navy hero band (title + excerpt) and the block content
 * inside the overlapping card.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id = get_the_ID();

	hk9_the_hero( hk9_section( $hk9_page_id, 'hero_band' ), 'band' );
	hk9_the_content_card( $hk9_page_id );

	if ( comments_open() || get_comments_number() ) {
		echo '<div class="container hk9-wide">';
		comments_template();
		echo '</div>';
	}

	echo '<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>';
endwhile;

get_footer();
