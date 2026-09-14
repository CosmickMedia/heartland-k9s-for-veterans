<?php
/**
 * Template Name: Thank You
 * Template Post Type: page
 *
 * The page a veteran lands on after the Initial Application Inquiry (and any
 * other confirmation page): band hero, the "next steps" card (reassurance,
 * numbered steps, form download, return address from Settings), a
 * settings-driven help strip, optional "While You Wait" cards and a hidden-by-
 * default call to action — all from the "Page sections" panels.
 *
 * The block content (editor canvas) renders inside the next-steps card, after
 * the address; when that section is hidden the content falls back to a bare
 * overlap card with the check icon (today's markup), so nothing is lost.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_page_id  = get_the_ID();
	$hk9_layout   = hk9_sections_layout( $hk9_page_id, 'thank-you' );
	$hk9_has_card = in_array( 'next_steps', $hk9_layout, true );
	$hk9_last     = (string) end( $hk9_layout ); // The card and the hero carry no bottom padding of their own.
	$hk9_spacer   = static function (): void {
		echo '<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>';
	};
	$hk9_content  = static function (): void {
		the_content();
	};

	hk9_rec_render_sections(
		$hk9_page_id,
		'thank-you',
		[
			// The next-steps card prints the canvas content itself (after the address).
			'args'  => [ 'next_steps' => [ 'content' => $hk9_content ] ],
			'after' => [
				'hero_band'  => static function () use ( $hk9_page_id, $hk9_has_card, $hk9_last, $hk9_spacer ): void {
					// Card hidden per page: keep the canvas content in the plain confirmation card.
					if ( ! $hk9_has_card && ! hk9_content_is_blank( $hk9_page_id ) ) {
						echo '<div class="hk9-overlap hk9-thank-you"><div class="hk9-overlap__card hk9-thank-you__card hk9-thank-you__card--plain"><span class="hk9-icon-well hk9-icon-well--crimson hk9-icon-well--lg hk9-thank-you__icon">' . hk9_icon( 'circle-check', [ 'size' => 32 ] ) . '</span><div class="hk9-prose hk9-thank-you__content">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
						the_content();
						echo '</div></div></div>';
					}
					if ( 'hero_band' === $hk9_last ) {
						$hk9_spacer();
					}
				},
				'next_steps' => static function () use ( $hk9_last, $hk9_spacer ): void {
					if ( 'next_steps' === $hk9_last ) {
						$hk9_spacer();
					}
				},
			],
		]
	);
endwhile;

get_footer();
