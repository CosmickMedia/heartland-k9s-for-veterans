<?php
/**
 * Single: hk9_story — band hero (title + veteran/branch/canine meta), featured
 * image, quote callout, content, gallery, back link to the stories listing.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_id       = get_the_ID();
	$hk9_quote    = trim( (string) hk9_rec_meta( $hk9_id, 'quote', '' ) );
	$hk9_veteran  = trim( (string) hk9_rec_meta( $hk9_id, 'veteran_name', '' ) );
	$hk9_relation = (string) hk9_rec_meta( $hk9_id, 'relationship', 'service' );
	$hk9_gallery  = hk9_rec_meta( $hk9_id, 'gallery', [] );
	$hk9_team_id  = (int) hk9_rec_meta( $hk9_id, 'team', 0 );
	$hk9_thumb    = (int) get_post_thumbnail_id( $hk9_id );
	$hk9_excerpt  = has_excerpt( $hk9_id ) ? wp_strip_all_tags( get_the_excerpt( $hk9_id ) ) : '';

	$hk9_eyebrows = [
		'service'     => __( 'Success Story', 'heartland-k9s' ),
		'therapy'     => __( 'Therapy Dog Story', 'heartland-k9s' ),
		'in-training' => __( 'Team in Training', 'heartland-k9s' ),
	];

	hk9_rec_hero(
		[
			'eyebrow' => $hk9_eyebrows[ $hk9_relation ] ?? $hk9_eyebrows['service'],
			'title'   => get_the_title(),
			'meta'    => hk9_rec_story_meta( $hk9_id ),
			'pattern' => 'stars',
		]
	);
	?>
	<div class="hk9-overlap hk9-single hk9-single--story">
		<article class="hk9-overlap__card hk9-single__card" id="post-<?php echo esc_attr( (string) $hk9_id ); ?>">
			<?php if ( $hk9_thumb > 0 ) : ?>
				<figure class="hk9-single__figure">
					<?php echo hk9_image( $hk9_thumb, 'large', [ 'alt' => $hk9_veteran ? sprintf( /* translators: %s: veteran name */ __( 'Photo: %s', 'heartland-k9s' ), $hk9_veteran ) : get_the_title(), 'sizes' => '(max-width: 1023px) calc(100vw - 32px), 928px' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
				</figure>
			<?php endif; ?>
			<?php hk9_the_breadcrumbs(); // After the edge-to-edge figure (its negative top margin needs to stay the card's first line). ?>

			<?php if ( '' !== $hk9_quote ) : ?>
				<blockquote class="hk9-single__quote">
					<svg class="hk9-single__quote-glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
					<p>“<?php echo esc_html( trim( $hk9_quote, "\"“” \n\r\t" ) ); ?>”</p>
					<?php if ( '' !== $hk9_veteran ) : ?>
						<footer class="hk9-single__quote-author"><?php echo esc_html( $hk9_veteran ); ?></footer>
					<?php endif; ?>
				</blockquote>
			<?php endif; ?>

			<?php if ( '' !== $hk9_excerpt && '' === $hk9_quote ) : ?>
				<p class="hk9-lead hk9-single__lead"><?php echo esc_html( $hk9_excerpt ); ?></p>
			<?php endif; ?>

			<div class="hk9-prose hk9-single__content">
				<?php the_content(); ?>
			</div>

			<?php $hk9_gallery_html = is_array( $hk9_gallery ) ? hk9_rec_gallery( $hk9_gallery, [ 'columns' => 3 ] ) : ''; ?>
			<?php if ( '' !== $hk9_gallery_html ) : ?>
				<section class="hk9-single__gallery" aria-labelledby="hk9-story-gallery-title">
					<h2 id="hk9-story-gallery-title" class="hk9-single__subheading"><?php esc_html_e( 'Photos', 'heartland-k9s' ); ?></h2>
					<?php echo $hk9_gallery_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block output. ?>
				</section>
			<?php endif; ?>

			<footer class="hk9-single__footer">
				<?php echo hk9_rec_back_link( 'links.stories', __( 'All success stories', 'heartland-k9s' ), '/stories/' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( $hk9_team_id > 0 && 'hk9_team' === get_post_type( $hk9_team_id ) && 'publish' === get_post_status( $hk9_team_id ) ) : ?>
					<a class="hk9-link" href="<?php echo esc_url( get_permalink( $hk9_team_id ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: team title */ __( 'Meet %s', 'heartland-k9s' ), get_the_title( $hk9_team_id ) ) ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
				<?php endif; ?>
			</footer>
		</article>
	</div>
	<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>
	<?php
endwhile;

get_footer();
