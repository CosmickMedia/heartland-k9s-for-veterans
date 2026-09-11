<?php
/**
 * Single: hk9_campaign — band hero (title, status), split card (image + summary,
 * dates, goal, CTA buttons), content, sponsor logos, gallery, back link.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_id       = get_the_ID();
	$hk9_status   = (string) hk9_rec_meta( $hk9_id, 'status', 'active' );
	$hk9_summary  = trim( (string) hk9_rec_meta( $hk9_id, 'summary', '' ) );
	$hk9_start    = trim( (string) hk9_rec_meta( $hk9_id, 'start', '' ) );
	$hk9_end      = trim( (string) hk9_rec_meta( $hk9_id, 'end', '' ) );
	$hk9_cta      = hk9_rec_meta( $hk9_id, 'cta', [] );
	$hk9_cta2     = hk9_rec_meta( $hk9_id, 'secondary_cta', [] );
	$hk9_sponsors = hk9_rec_meta( $hk9_id, 'sponsors', [] );
	$hk9_gallery  = hk9_rec_meta( $hk9_id, 'gallery', [] );
	$hk9_goal     = trim( (string) hk9_rec_meta( $hk9_id, 'goal_text', '' ) );
	$hk9_thumb    = (int) get_post_thumbnail_id( $hk9_id );
	$hk9_has_body = '' !== trim( (string) get_post_field( 'post_content', $hk9_id ) );

	if ( '' === $hk9_summary && has_excerpt( $hk9_id ) ) {
		$hk9_summary = wp_strip_all_tags( get_the_excerpt( $hk9_id ) );
	}

	$hk9_format = static function ( string $date ): string {
		$dt = '' !== $date ? DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() ) : false;
		return $dt instanceof DateTimeImmutable ? wp_date( (string) get_option( 'date_format', 'F j, Y' ), $dt->getTimestamp(), wp_timezone() ) : '';
	};
	$hk9_dates  = array_filter( [ $hk9_format( $hk9_start ), $hk9_format( $hk9_end ) ] );
	$hk9_period = implode( ' – ', $hk9_dates );

	$hk9_meta = [];
	if ( '' !== $hk9_period ) {
		$hk9_meta[] = hk9_icon( 'calendar', [ 'size' => 16 ] ) . '<span>' . esc_html( $hk9_period ) . '</span>';
	}
	if ( '' !== $hk9_goal ) {
		$hk9_meta[] = hk9_icon( 'star', [ 'size' => 16 ] ) . '<span>' . esc_html( $hk9_goal ) . '</span>';
	}

	hk9_rec_hero(
		[
			'eyebrow' => __( 'Campaign', 'heartland-k9s' ),
			'badge'   => hk9_rec_status_badge( $hk9_status, 'hk9-status--on-navy' ),
			'title'   => get_the_title(),
			'meta'    => $hk9_meta,
			'pattern' => 'grid',
		]
	);

	$hk9_logos = is_array( $hk9_sponsors ) ? hk9_rec_sponsor_logos( $hk9_sponsors ) : '';
	?>
	<div class="hk9-overlap hk9-single hk9-single--campaign">
		<article class="hk9-overlap__card <?php echo $hk9_thumb > 0 ? 'hk9-overlap__card--split ' : ''; ?>hk9-single__card" id="post-<?php echo esc_attr( (string) $hk9_id ); ?>">
			<?php if ( $hk9_thumb > 0 ) : ?>
				<div class="hk9-overlap__media hk9-single__media">
					<?php echo hk9_image( $hk9_thumb, 'hk9-card', [ 'alt' => get_the_title(), 'sizes' => '(max-width: 767px) calc(100vw - 32px), 512px' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
				</div>
			<?php endif; ?>
			<div class="<?php echo $hk9_thumb > 0 ? 'hk9-overlap__body ' : ''; ?>hk9-single__body">
				<h2 class="hk9-single__subheading"><?php esc_html_e( 'About this campaign', 'heartland-k9s' ); ?></h2>
				<?php echo hk9_paragraphs( $hk9_summary, 'hk9-lead' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<dl class="hk9-single__facts">
					<div><dt><?php esc_html_e( 'Status', 'heartland-k9s' ); ?></dt><dd><?php echo hk9_rec_status_badge( $hk9_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></dd></div>
					<?php if ( '' !== $hk9_period ) : ?>
						<div><dt><?php esc_html_e( 'Dates', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( $hk9_period ); ?></dd></div>
					<?php endif; ?>
					<?php if ( '' !== $hk9_goal ) : ?>
						<div><dt><?php esc_html_e( 'Goal', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( $hk9_goal ); ?></dd></div>
					<?php endif; ?>
				</dl>
				<?php $hk9_buttons = array_filter( [ hk9_rec_button( $hk9_cta, 'primary', [ 'size' => 'lg', 'icon' => 'arrow-right', 'icon_size' => 20 ] ), hk9_rec_button( $hk9_cta2, 'outline', [ 'size' => 'lg' ] ) ] ); ?>
				<?php if ( ! empty( $hk9_buttons ) ) : ?>
					<div class="hk9-single__actions"><?php echo implode( '', $hk9_buttons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
				<?php endif; ?>
			</div>
		</article>
	</div>

	<?php $hk9_gallery_html = is_array( $hk9_gallery ) ? hk9_rec_gallery( $hk9_gallery, [ 'columns' => 3 ] ) : ''; ?>
	<?php if ( $hk9_has_body || '' !== $hk9_gallery_html ) : ?>
		<section class="hk9-section hk9-section--py20 hk9-single__more" aria-labelledby="hk9-campaign-details-title">
			<div class="container hk9-narrow">
				<h2 id="hk9-campaign-details-title" class="hk9-single__subheading"><?php esc_html_e( 'Campaign details', 'heartland-k9s' ); ?></h2>
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

	<?php if ( '' !== $hk9_logos ) : ?>
		<section class="hk9-section hk9-section--py20 hk9-section--tint hk9-section--bordered hk9-single__sponsors" aria-labelledby="hk9-campaign-sponsors-title">
			<div class="container">
				<div class="hk9-section__header">
					<h2 id="hk9-campaign-sponsors-title" class="hk9-section__title"><?php esc_html_e( 'Campaign sponsors', 'heartland-k9s' ); ?></h2>
					<span class="hk9-divider" aria-hidden="true"></span>
				</div>
				<?php echo $hk9_logos; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</div>
		</section>
	<?php endif; ?>

	<div class="hk9-section hk9-section--pb20 hk9-single__footer-bar">
		<div class="container hk9-narrow">
			<footer class="hk9-single__footer">
				<?php echo hk9_rec_back_link( 'links.campaigns', __( 'All campaigns', 'heartland-k9s' ), '/campaigns/' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</footer>
		</div>
	</div>
	<?php
endwhile;

get_footer();
