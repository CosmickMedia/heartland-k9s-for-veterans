<?php
/**
 * Section: upcoming — upcoming events list (hk9_events_query('upcoming')) with an empty state.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'upcoming' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_empty   = trim( (string) ( $hk9_data['empty_text'] ?? '' ) );
$hk9_count   = max( 1, min( 50, (int) ( $hk9_data['count'] ?? 10 ) ) );
$hk9_events  = hk9_rec_events( 'upcoming', $hk9_count );

if ( '' === $hk9_heading ) {
	$hk9_heading = __( 'Upcoming Events', 'heartland-k9s' );
}
if ( '' === $hk9_empty ) {
	$hk9_empty = __( 'There are no upcoming events scheduled right now. Check back soon.', 'heartland-k9s' );
}

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-events-section', [ 'aria-labelledby' => 'hk9-' . sanitize_html_class( $hk9_id ) . '-title' ] );
?>
<div class="container">
	<div class="hk9-section__header hk9-section__header--left">
		<h2 id="<?php echo esc_attr( 'hk9-' . sanitize_html_class( $hk9_id ) . '-title' ); ?>" class="hk9-section__title"><?php echo esc_html( $hk9_heading ); ?></h2>
		<span class="hk9-divider hk9-divider--left" aria-hidden="true"></span>
	</div>

	<?php if ( empty( $hk9_events ) ) : ?>
		<div class="hk9-empty">
			<span class="hk9-icon-well hk9-icon-well--lg"><?php echo hk9_icon( 'calendar', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<?php echo hk9_paragraphs( $hk9_empty, 'hk9-empty__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php else : ?>
		<ul class="hk9-events hk9-events--list">
			<?php foreach ( $hk9_events as $hk9_event ) : ?>
				<li><?php hk9_rec_card( 'event', $hk9_event, [ 'scope' => 'upcoming' ] ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
