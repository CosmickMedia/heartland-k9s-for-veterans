<?php
/**
 * Template Name: Application
 * Template Post Type: page
 *
 * Application: band hero, block content intro in the overlapping card, then the
 * initial application inquiry form section.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id = get_the_ID();

	hk9_rec_render_sections(
		$hk9_page_id,
		'application',
		[
			'before' => [
				'form' => static function () use ( $hk9_page_id ): void {
					hk9_the_content_card( $hk9_page_id );
				},
			],
		]
	);
endwhile;

get_footer();
