<?php
/**
 * Card: campaign — image, title, summary, status badge, CTA button, sponsor logos.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post, show_sponsors: bool }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = $args['post'] ?? null;
if ( ! $hk9_post instanceof WP_Post ) {
	return;
}

$hk9_id        = (int) $hk9_post->ID;
$hk9_url       = get_permalink( $hk9_post );
$hk9_title     = get_the_title( $hk9_post );
$hk9_summary   = trim( (string) hk9_rec_meta( $hk9_id, 'summary', '' ) );
$hk9_status    = (string) hk9_rec_meta( $hk9_id, 'status', 'active' );
$hk9_cta       = hk9_rec_meta( $hk9_id, 'cta', [] );
$hk9_goal      = trim( (string) hk9_rec_meta( $hk9_id, 'goal_text', '' ) );
$hk9_sponsors  = ! isset( $args['show_sponsors'] ) || ! empty( $args['show_sponsors'] ) ? (array) hk9_rec_meta( $hk9_id, 'sponsors', [] ) : [];
$hk9_thumb     = (int) get_post_thumbnail_id( $hk9_post );

if ( '' === $hk9_summary && has_excerpt( $hk9_post ) ) {
	$hk9_summary = wp_strip_all_tags( get_the_excerpt( $hk9_post ) );
}
if ( '' !== $hk9_summary ) {
	$hk9_summary = wp_html_excerpt( $hk9_summary, 220, '…' );
}
?>
<article class="hk9-card hk9-card--hover hk9-campaigns__card">
	<?php if ( $hk9_thumb > 0 ) : ?>
		<a class="hk9-card__media" href="<?php echo esc_url( $hk9_url ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo hk9_image( $hk9_thumb, 'hk9-card', [ 'alt' => '', 'sizes' => '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) 50vw, 400px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		</a>
	<?php endif; ?>
	<div class="hk9-card__body">
		<div class="hk9-card__meta hk9-card__meta--row">
			<?php echo hk9_rec_status_badge( $hk9_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $hk9_goal ) : ?>
				<span class="hk9-card__goal"><?php echo esc_html( $hk9_goal ); ?></span>
			<?php endif; ?>
		</div>
		<h3 class="hk9-card__title"><a href="<?php echo esc_url( $hk9_url ); ?>"><?php echo esc_html( $hk9_title ); ?></a></h3>
		<?php if ( '' !== $hk9_summary ) : ?>
			<p class="hk9-card__text"><?php echo esc_html( $hk9_summary ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $hk9_sponsors ) ) : ?>
			<?php $hk9_logos = hk9_rec_sponsor_logos( $hk9_sponsors, 'hk9-sponsors--sm' ); ?>
			<?php if ( '' !== $hk9_logos ) : ?>
				<div class="hk9-card__sponsors">
					<span class="hk9-card__sponsors-label"><?php esc_html_e( 'Sponsored by', 'heartland-k9s' ); ?></span>
					<?php echo $hk9_logos; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<div class="hk9-card__actions hk9-card__actions--row">
			<?php if ( is_array( $hk9_cta ) && hk9_link_is_set( $hk9_cta ) ) : ?>
				<?php echo hk9_button( $hk9_cta, 'primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php endif; ?>
			<a class="hk9-link" href="<?php echo esc_url( $hk9_url ); ?>"><?php esc_html_e( 'Learn more', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: campaign title */ __( ': %s', 'heartland-k9s' ), $hk9_title ) ); ?></span></a>
		</div>
	</div>
</article>
