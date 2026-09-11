<?php
/**
 * Section: past — past events list (hk9_events_query('past')), shown when `show` is on.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'past' );
$hk9_show    = ! isset( $hk9_data['show'] ) || ! empty( $hk9_data['show'] );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_count   = max( 1, min( 50, (int) ( $hk9_data['count'] ?? 6 ) ) );

if ( ! $hk9_show ) {
	return;
}

$hk9_events = hk9_rec_events( 'past', $hk9_count );
if ( empty( $hk9_events ) ) {
	return;
}

if ( '' === $hk9_heading ) {
	$hk9_heading = __( 'Past Events', 'heartland-k9s' );
}

hk9_section_open( $hk9_id, 'hk9-section--py20 hk9-section--tint hk9-section--bordered hk9-events-section hk9-events-section--past', [ 'aria-labelledby' => 'hk9-' . sanitize_html_class( $hk9_id ) . '-title' ] );
?>
<div class="container">
	<div class="hk9-section__header hk9-section__header--left">
		<h2 id="<?php echo esc_attr( 'hk9-' . sanitize_html_class( $hk9_id ) . '-title' ); ?>" class="hk9-section__title"><?php echo esc_html( $hk9_heading ); ?></h2>
		<span class="hk9-divider hk9-divider--left" aria-hidden="true"></span>
	</div>

	<ul class="hk9-events hk9-events--grid">
		<?php foreach ( $hk9_events as $hk9_event ) : ?>
			<li><?php hk9_rec_card( 'event', $hk9_event, [ 'scope' => 'past' ] ); ?></li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
hk9_section_close();
