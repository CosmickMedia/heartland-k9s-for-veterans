<?php
/**
 * Front page.
 *
 * A static front page renders the Home template sections (the page should have
 * the "Home (sections)" template assigned; an unassigned static front page still
 * resolves to `home` through hk9_template_for_post()). When the front page shows
 * the blog, home.php / index.php take over.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

if ( 'page' !== get_option( 'show_on_front' ) || ! is_page() ) {
	// Blog on the front page: defer to the posts index.
	$hk9_blog_template = locate_template( [ 'home.php', 'index.php' ] );
	if ( $hk9_blog_template ) {
		load_template( $hk9_blog_template );
	}
	return;
}

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id  = get_the_ID();
	$hk9_template = hk9_template_for_post( $hk9_page_id );

	if ( 'home' === $hk9_template ) {
		hk9_render_sections( $hk9_page_id, 'home' );
	} elseif ( 'default' !== $hk9_template && locate_template( 'page-templates/' . $hk9_template . '.php' ) ) {
		// A different sections template assigned to the front page: reuse its body.
		get_template_part( 'template-parts/page', 'sections', [ 'template' => $hk9_template, 'post_id' => $hk9_page_id ] );
	} else {
		hk9_the_hero( hk9_section( $hk9_page_id, 'hero_band' ), 'band' );
		hk9_the_content_card( $hk9_page_id );
		echo '<div class="hk9-section hk9-section--pb20"></div>';
	}
endwhile;

get_footer();
