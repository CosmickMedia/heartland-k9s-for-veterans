<?php
/**
 * Single: hk9_team — band hero (title, status badge, canine/handler meta), split
 * card (image + summary, donate button, BarKode link), content, gallery, back link.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_id       = get_the_ID();
	$hk9_status   = (string) hk9_rec_meta( $hk9_id, 'status', 'in-training' );
	$hk9_canine   = trim( (string) hk9_rec_meta( $hk9_id, 'canine_name', '' ) );
	$hk9_handler  = trim( (string) hk9_rec_meta( $hk9_id, 'handler_name', '' ) );
	$hk9_year     = trim( (string) hk9_rec_meta( $hk9_id, 'year', '' ) );
	$hk9_summary  = trim( (string) hk9_rec_meta( $hk9_id, 'summary', '' ) );
	$hk9_donate   = hk9_rec_meta( $hk9_id, 'donate_link', [] );
	$hk9_gallery  = hk9_rec_meta( $hk9_id, 'gallery', [] );
	$hk9_barkode  = (int) hk9_rec_meta( $hk9_id, 'barkode', 0 );
	$hk9_thumb    = (int) get_post_thumbnail_id( $hk9_id );
	$hk9_has_body = '' !== trim( (string) get_post_field( 'post_content', $hk9_id ) );

	if ( '' === $hk9_summary && has_excerpt( $hk9_id ) ) {
		$hk9_summary = wp_strip_all_tags( get_the_excerpt( $hk9_id ) );
	}

	$hk9_barkode_ok = $hk9_barkode > 0 && 'hk9_barkode' === get_post_type( $hk9_barkode ) && 'publish' === get_post_status( $hk9_barkode );

	$hk9_meta = [];
	if ( '' !== $hk9_canine ) {
		/* translators: %s: dog name */
		$hk9_meta[] = hk9_icon( 'paw-print', [ 'size' => 16 ] ) . '<span>' . esc_html( sprintf( __( 'K9: %s', 'heartland-k9s' ), $hk9_canine ) ) . '</span>';
	}
	if ( '' !== $hk9_handler ) {
		/* translators: %s: handler name */
		$hk9_meta[] = hk9_icon( 'star', [ 'size' => 16 ] ) . '<span>' . esc_html( sprintf( __( 'Handler: %s', 'heartland-k9s' ), $hk9_handler ) ) . '</span>';
	}
	if ( '' !== $hk9_year ) {
		$hk9_meta[] = hk9_icon( 'calendar', [ 'size' => 16 ] ) . '<span>' . esc_html( $hk9_year ) . '</span>';
	}

	hk9_rec_hero(
		[
			'eyebrow' => __( 'Heartland K9 Team', 'heartland-k9s' ),
			'badge'   => hk9_rec_status_badge( $hk9_status, 'hk9-status--on-navy' ),
			'title'   => get_the_title(),
			'meta'    => $hk9_meta,
		]
	);
	?>
	<div class="hk9-overlap hk9-single hk9-single--team">
		<article class="hk9-overlap__card hk9-overlap__card--split hk9-single__card" id="post-<?php echo esc_attr( (string) $hk9_id ); ?>">
			<div class="hk9-overlap__media hk9-single__media">
				<?php if ( $hk9_thumb > 0 ) : ?>
					<?php echo hk9_image( $hk9_thumb, 'hk9-portrait', [ 'alt' => get_the_title(), 'sizes' => '(max-width: 767px) calc(100vw - 32px), 512px' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
				<?php else : ?>
					<span class="hk9-single__placeholder" aria-hidden="true"><?php echo hk9_icon( 'dog', [ 'size' => 40 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
				<?php endif; ?>
			</div>
			<div class="hk9-overlap__body hk9-single__body">
				<h2 class="hk9-single__subheading"><?php esc_html_e( 'About this team', 'heartland-k9s' ); ?></h2>
				<?php if ( '' !== $hk9_summary ) : ?>
					<?php echo hk9_paragraphs( $hk9_summary, 'hk9-lead' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php endif; ?>
				<dl class="hk9-single__facts">
					<div><dt><?php esc_html_e( 'Status', 'heartland-k9s' ); ?></dt><dd><?php echo hk9_rec_status_badge( $hk9_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></dd></div>
					<?php if ( '' !== $hk9_canine ) : ?>
						<div><dt><?php esc_html_e( 'Canine', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( $hk9_canine ); ?></dd></div>
					<?php endif; ?>
					<?php if ( '' !== $hk9_handler ) : ?>
						<div><dt><?php esc_html_e( 'Handler', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( $hk9_handler ); ?></dd></div>
					<?php endif; ?>
					<?php if ( '' !== $hk9_year ) : ?>
						<div><dt><?php esc_html_e( 'Year', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( $hk9_year ); ?></dd></div>
					<?php endif; ?>
				</dl>
				<div class="hk9-single__actions">
					<?php echo hk9_rec_button( $hk9_donate, 'primary', [ 'size' => 'lg', 'icon' => 'heart', 'icon_size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<?php if ( $hk9_barkode_ok ) : ?>
						<a class="hk9-btn hk9-btn--outline hk9-btn--lg" href="<?php echo esc_url( get_permalink( $hk9_barkode ) ); ?>"><?php echo hk9_icon( 'qr-code', [ 'size' => 20, 'class' => 'hk9-icon--20' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><?php esc_html_e( 'BarKode record', 'heartland-k9s' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</article>
	</div>

	<?php $hk9_gallery_html = is_array( $hk9_gallery ) ? hk9_rec_gallery( $hk9_gallery, [ 'columns' => 3 ] ) : ''; ?>
	<?php if ( $hk9_has_body || '' !== $hk9_gallery_html ) : ?>
		<section class="hk9-section hk9-section--py20 hk9-single__more" aria-labelledby="hk9-team-story-title">
			<div class="container hk9-narrow">
				<h2 id="hk9-team-story-title" class="hk9-single__subheading"><?php esc_html_e( 'Their story', 'heartland-k9s' ); ?></h2>
				<?php if ( $hk9_has_body ) : ?>
					<div class="hk9-prose hk9-single__content">
						<?php the_content(); ?>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $hk9_gallery_html ) : ?>
					<div class="hk9-single__gallery">
						<?php echo $hk9_gallery_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block output. ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<div class="hk9-section hk9-section--pb20 hk9-single__footer-bar">
		<div class="container hk9-narrow">
			<footer class="hk9-single__footer">
				<?php echo hk9_rec_back_link( 'links.teams', __( 'All teams in training', 'heartland-k9s' ), '/hk9-current-teams-in-training/' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</footer>
		</div>
	</div>
	<?php
endwhile;

get_footer();
