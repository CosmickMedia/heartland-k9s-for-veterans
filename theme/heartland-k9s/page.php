<?php
/**
 * Default page template — what every new page gets.
 *
 * Navy hero band (title + excerpt, or the "Hero (band)" section fields), the block
 * content inside the overlapping white card, then the optional "Call to action"
 * band (hidden until ticked under Page sections). When the hero is unticked the
 * page starts plainly: the title is printed as the first heading of the card.
 * Section ids come from the plugin's `default` template definition
 * (docs/ARCHITECTURE.md §5); inc/section-defaults.php mirrors them plugin-less.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id  = get_the_ID();
	$hk9_layout   = hk9_sections_layout( $hk9_page_id, 'default' );
	$hk9_has_hero = in_array( 'hero_band', $hk9_layout, true );
	$hk9_rest     = array_values( array_diff( $hk9_layout, [ 'hero_band' ] ) );
	$hk9_blank    = hk9_content_is_blank( $hk9_page_id );

	// Breadcrumbs (inc/seo.php): first thing in the content card, or a small strip below the band when the card is empty.
	$hk9_crumbs = function_exists( 'hk9_breadcrumbs' ) ? hk9_breadcrumbs() : '';

	if ( $hk9_has_hero ) {
		hk9_the_hero( hk9_section( $hk9_page_id, 'hero_band' ), 'band' );
		if ( ! $hk9_blank ) {
			echo '<div class="hk9-overlap"><div class="hk9-overlap__card hk9-prose">';
			echo $hk9_crumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template part.
			the_content();
			echo '</div></div>';
		} elseif ( '' !== $hk9_crumbs ) {
			echo '<div class="hk9-breadcrumbs-strip"><div class="container">' . $hk9_crumbs . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template part.
		}
	} else {
		// Plain start: no band; the title heads the content card.
		echo '<section class="hk9-section hk9-page-plain"><div class="hk9-gutter"><div class="hk9-overlap__card hk9-page-plain__card hk9-prose">';
		echo $hk9_crumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template part.
		echo '<h1 class="hk9-page-plain__title">' . esc_html( get_the_title( $hk9_page_id ) ) . '</h1>';
		if ( ! $hk9_blank ) {
			the_content();
		}
		echo '</div></div></section>';
	}

	if ( comments_open() || get_comments_number() ) {
		echo '<div class="container hk9-wide">';
		comments_template();
		echo '</div>';
	}

	// Remaining visible sections (Call to action …) in the order set under Page sections.
	$hk9_rendered = 0;
	foreach ( $hk9_rest as $hk9_id ) {
		$hk9_type = hk9_section_type( 'default', $hk9_id );
		if ( ! locate_template( 'template-parts/sections/' . $hk9_type . '.php' ) ) {
			continue;
		}
		if ( $hk9_has_hero && 0 === $hk9_rendered ) {
			// The overlapping card has no bottom margin of its own: 80px before the first band.
			echo '<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>';
		}
		get_template_part(
			'template-parts/sections/' . $hk9_type,
			null,
			[
				'data'     => hk9_section( $hk9_page_id, $hk9_id ),
				'post_id'  => $hk9_page_id,
				'id'       => $hk9_id,
				'template' => 'default',
			]
		);
		++$hk9_rendered;
	}

	if ( 0 === $hk9_rendered && $hk9_has_hero ) {
		// Nothing follows the card: keep the reference bottom spacing.
		echo '<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>';
	}
endwhile;

get_footer();
