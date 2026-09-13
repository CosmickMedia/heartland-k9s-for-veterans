<?php
/**
 * Single: hk9_event — band hero (title, status, date range), details card with
 * date block, time range, venue/address + "Get directions" link (plain Google
 * Maps search link, nothing embedded), registration button, organizer, ticket
 * info, flyer image and the event content.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_id        = get_the_ID();
	$hk9_status    = (string) hk9_rec_meta( $hk9_id, 'status', 'scheduled' );
	$hk9_range     = hk9_rec_event_range( $hk9_id );
	$hk9_venue     = trim( (string) hk9_rec_meta( $hk9_id, 'venue', '' ) );
	$hk9_address   = trim( (string) hk9_rec_meta( $hk9_id, 'address', '' ) );
	$hk9_reg       = hk9_rec_meta( $hk9_id, 'registration', [] );
	$hk9_reg       = is_array( $hk9_reg ) ? hk9_rec_event_registration_link( $hk9_reg ) : [];
	$hk9_tickets   = trim( (string) hk9_rec_meta( $hk9_id, 'ticket_info', '' ) );
	$hk9_org_name  = trim( (string) hk9_rec_meta( $hk9_id, 'organizer_name', '' ) );
	$hk9_org_line  = trim( (string) hk9_rec_meta( $hk9_id, 'organizer_contact', '' ) );
	$hk9_flyer     = (int) hk9_rec_meta( $hk9_id, 'flyer', 0 );
	$hk9_thumb     = (int) get_post_thumbnail_id( $hk9_id );
	$hk9_upcoming  = hk9_rec_event_upcoming( $hk9_id );
	$hk9_maps      = hk9_rec_maps_url( trim( $hk9_venue . ', ' . $hk9_address, ', ' ) );
	$hk9_has_body  = '' !== trim( (string) get_post_field( 'post_content', $hk9_id ) );
	$hk9_excerpt   = has_excerpt( $hk9_id ) ? wp_strip_all_tags( get_the_excerpt( $hk9_id ) ) : '';
	$hk9_flyer_id  = $hk9_flyer > 0 ? $hk9_flyer : $hk9_thumb;
	$hk9_org_link  = '';
	if ( '' !== $hk9_org_line ) {
		if ( is_email( $hk9_org_line ) ) {
			$hk9_org_link = 'mailto:' . sanitize_email( $hk9_org_line );
		} elseif ( preg_match( '/^[\d\s().+-]{7,}$/', $hk9_org_line ) ) {
			$hk9_org_link = hk9_tel_href( $hk9_org_line );
		}
	}

	$hk9_meta = [];
	if ( '' !== $hk9_range ) {
		$hk9_meta[] = hk9_icon( 'calendar', [ 'size' => 16 ] ) . '<span>' . esc_html( $hk9_range ) . '</span>';
	}
	if ( '' !== $hk9_venue ) {
		$hk9_meta[] = hk9_icon( 'map-pin', [ 'size' => 16 ] ) . '<span>' . esc_html( $hk9_venue ) . '</span>';
	}

	hk9_rec_hero(
		[
			'eyebrow' => $hk9_upcoming ? __( 'Upcoming Event', 'heartland-k9s' ) : __( 'Past Event', 'heartland-k9s' ),
			'badge'   => 'scheduled' !== $hk9_status ? hk9_rec_status_badge( $hk9_status, 'hk9-status--on-navy' ) : '',
			'title'   => get_the_title(),
			'meta'    => $hk9_meta,
		]
	);
	?>
	<div class="hk9-overlap hk9-single hk9-single--event">
		<article class="hk9-overlap__card hk9-single__card hk9-event" id="post-<?php echo esc_attr( (string) $hk9_id ); ?>">
			<?php hk9_the_breadcrumbs(); ?>
			<div class="hk9-event__layout">
				<div class="hk9-event__main">
					<?php if ( in_array( $hk9_status, [ 'cancelled', 'postponed' ], true ) ) : ?>
						<p class="hk9-event__alert" role="status">
							<?php echo hk9_icon( 'circle-alert', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<span><?php echo 'cancelled' === $hk9_status ? esc_html__( 'This event has been cancelled.', 'heartland-k9s' ) : esc_html__( 'This event has been postponed. A new date will be announced.', 'heartland-k9s' ); ?></span>
						</p>
					<?php endif; ?>

					<div class="hk9-event__when">
						<?php echo hk9_rec_event_date_block( $hk9_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						<dl class="hk9-event__details">
							<?php if ( '' !== $hk9_range ) : ?>
								<div>
									<dt><?php echo hk9_icon( 'clock', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><?php esc_html_e( 'When', 'heartland-k9s' ); ?></dt>
									<dd><?php echo esc_html( $hk9_range ); ?></dd>
								</div>
							<?php endif; ?>
							<?php if ( '' !== $hk9_venue || '' !== $hk9_address ) : ?>
								<div>
									<dt><?php echo hk9_icon( 'map-pin', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><?php esc_html_e( 'Where', 'heartland-k9s' ); ?></dt>
									<dd>
										<?php if ( '' !== $hk9_venue ) : ?>
											<span class="hk9-event__venue"><?php echo esc_html( $hk9_venue ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $hk9_address ) : ?>
											<address class="hk9-event__address"><?php echo nl2br( esc_html( $hk9_address ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></address>
										<?php endif; ?>
										<?php if ( '' !== $hk9_maps ) : ?>
											<a class="hk9-link hk9-event__directions" href="<?php echo esc_url( $hk9_maps ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get directions', 'heartland-k9s' ); ?><?php echo hk9_icon( 'external-link', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'heartland-k9s' ); ?></span></a>
										<?php endif; ?>
									</dd>
								</div>
							<?php endif; ?>
							<?php if ( '' !== $hk9_org_name || '' !== $hk9_org_line ) : ?>
								<div>
									<dt><?php echo hk9_icon( 'users', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><?php esc_html_e( 'Organizer', 'heartland-k9s' ); ?></dt>
									<dd>
										<?php if ( '' !== $hk9_org_name ) : ?>
											<span><?php echo esc_html( $hk9_org_name ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $hk9_org_line ) : ?>
											<?php if ( '' !== $hk9_org_link ) : ?>
												<a href="<?php echo esc_url( $hk9_org_link ); ?>"><?php echo esc_html( $hk9_org_line ); ?></a>
											<?php else : ?>
												<span><?php echo esc_html( $hk9_org_line ); ?></span>
											<?php endif; ?>
										<?php endif; ?>
									</dd>
								</div>
							<?php endif; ?>
						</dl>
					</div>

					<?php if ( '' !== $hk9_excerpt ) : ?>
						<p class="hk9-lead hk9-event__lead"><?php echo esc_html( $hk9_excerpt ); ?></p>
					<?php endif; ?>

					<?php if ( $hk9_has_body ) : ?>
						<div class="hk9-prose hk9-single__content">
							<?php the_content(); ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $hk9_tickets ) : ?>
						<section class="hk9-callout hk9-event__tickets" aria-labelledby="hk9-event-tickets-title">
							<h2 id="hk9-event-tickets-title" class="hk9-event__tickets-title"><?php echo hk9_icon( 'ticket', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><?php esc_html_e( 'Tickets', 'heartland-k9s' ); ?></h2>
							<?php echo hk9_paragraphs( $hk9_tickets ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						</section>
					<?php endif; ?>
				</div>

				<aside class="hk9-event__aside" aria-label="<?php esc_attr_e( 'Registration', 'heartland-k9s' ); ?>">
					<?php if ( $hk9_flyer_id > 0 ) : ?>
						<figure class="hk9-event__flyer">
							<?php echo hk9_image( $hk9_flyer_id, 'large', [ 'alt' => sprintf( /* translators: %s: event title */ __( 'Flyer: %s', 'heartland-k9s' ), get_the_title() ), 'sizes' => '(max-width: 1023px) calc(100vw - 32px), 320px' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
						</figure>
					<?php endif; ?>
					<?php if ( $hk9_upcoming && 'cancelled' !== $hk9_status && hk9_link_is_set( $hk9_reg ) ) : ?>
						<div class="hk9-event__register">
							<?php echo hk9_button( $hk9_reg, 'primary', [ 'size' => 'lg', 'full' => true, 'icon' => '_blank' === ( $hk9_reg['target'] ?? '' ) ? 'external-link' : 'ticket', 'icon_size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<?php if ( '_blank' === ( $hk9_reg['target'] ?? '' ) ) : ?>
								<p class="hk9-event__register-note"><?php esc_html_e( 'Opens the ticket page in a new tab.', 'heartland-k9s' ); ?></p>
							<?php endif; ?>
						</div>
					<?php elseif ( ! $hk9_upcoming ) : ?>
						<p class="hk9-event__past-note"><?php esc_html_e( 'This event has already taken place. Thank you to everyone who joined us!', 'heartland-k9s' ); ?></p>
					<?php endif; ?>
				</aside>
			</div>

			<footer class="hk9-single__footer">
				<?php echo hk9_rec_back_link( 'links.events', __( 'All events', 'heartland-k9s' ), '/events/' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</footer>
		</article>
	</div>
	<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>
	<?php
endwhile;

get_footer();
