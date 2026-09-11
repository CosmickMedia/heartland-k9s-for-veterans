<?php
/**
 * Card: event — date block (month/day), title, time range, venue, status badge,
 * registration button (external ticket links open in a new tab).
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post, scope: 'upcoming'|'past' }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = $args['post'] ?? null;
if ( ! $hk9_post instanceof WP_Post ) {
	return;
}

$hk9_id       = (int) $hk9_post->ID;
$hk9_scope    = ( $args['scope'] ?? '' ) === 'past' ? 'past' : 'upcoming';
$hk9_url      = get_permalink( $hk9_post );
$hk9_title    = get_the_title( $hk9_post );
$hk9_range    = hk9_rec_event_range( $hk9_id );
$hk9_venue    = trim( (string) hk9_rec_meta( $hk9_id, 'venue', '' ) );
$hk9_status   = (string) hk9_rec_meta( $hk9_id, 'status', 'scheduled' );
$hk9_reg      = hk9_rec_meta( $hk9_id, 'registration', [] );
$hk9_reg      = is_array( $hk9_reg ) ? hk9_rec_event_registration_link( $hk9_reg ) : [];
$hk9_excerpt  = has_excerpt( $hk9_post ) ? wp_strip_all_tags( get_the_excerpt( $hk9_post ) ) : '';
$hk9_thumb    = (int) get_post_thumbnail_id( $hk9_post );
$hk9_flyer    = (int) hk9_rec_meta( $hk9_id, 'flyer', 0 );
$hk9_image_id = $hk9_thumb > 0 ? $hk9_thumb : $hk9_flyer;
$hk9_classes  = [ 'hk9-event-card', 'hk9-event-card--' . $hk9_scope ];
if ( in_array( $hk9_status, [ 'cancelled', 'postponed' ], true ) ) {
	$hk9_classes[] = 'is-' . $hk9_status;
}
?>
<article class="<?php echo esc_attr( implode( ' ', $hk9_classes ) ); ?>">
	<?php echo hk9_rec_event_date_block( $hk9_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<div class="hk9-event-card__body">
		<?php if ( 'scheduled' !== $hk9_status ) : ?>
			<?php echo hk9_rec_status_badge( $hk9_status, 'hk9-event-card__status' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php endif; ?>
		<h3 class="hk9-event-card__title"><a href="<?php echo esc_url( $hk9_url ); ?>"><?php echo esc_html( $hk9_title ); ?></a></h3>
		<ul class="hk9-event-card__meta">
			<?php if ( '' !== $hk9_range ) : ?>
				<li><?php echo hk9_icon( 'clock', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php echo esc_html( $hk9_range ); ?></span></li>
			<?php endif; ?>
			<?php if ( '' !== $hk9_venue ) : ?>
				<li><?php echo hk9_icon( 'map-pin', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php echo esc_html( $hk9_venue ); ?></span></li>
			<?php endif; ?>
		</ul>
		<?php if ( '' !== $hk9_excerpt ) : ?>
			<p class="hk9-event-card__excerpt"><?php echo esc_html( $hk9_excerpt ); ?></p>
		<?php endif; ?>
		<div class="hk9-event-card__actions">
			<?php if ( 'upcoming' === $hk9_scope && 'cancelled' !== $hk9_status && hk9_link_is_set( $hk9_reg ) ) : ?>
				<?php echo hk9_button( $hk9_reg, 'primary', [ 'icon' => '_blank' === ( $hk9_reg['target'] ?? '' ) ? 'external-link' : 'ticket' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php endif; ?>
			<a class="hk9-link" href="<?php echo esc_url( $hk9_url ); ?>"><?php esc_html_e( 'Event details', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: event title */ __( ': %s', 'heartland-k9s' ), $hk9_title ) ); ?></span></a>
		</div>
	</div>
	<?php if ( $hk9_image_id > 0 ) : ?>
		<a class="hk9-event-card__media" href="<?php echo esc_url( $hk9_url ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo hk9_image( $hk9_image_id, 'hk9-card', [ 'alt' => '', 'sizes' => '(max-width: 767px) calc(100vw - 32px), 260px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		</a>
	<?php endif; ?>
</article>
