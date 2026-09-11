<?php
/**
 * Card: team — image, status badge, title, canine / handler line, summary, links.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = $args['post'] ?? null;
if ( ! $hk9_post instanceof WP_Post ) {
	return;
}

$hk9_id      = (int) $hk9_post->ID;
$hk9_url     = get_permalink( $hk9_post );
$hk9_title   = get_the_title( $hk9_post );
$hk9_status  = (string) hk9_rec_meta( $hk9_id, 'status', 'in-training' );
$hk9_canine  = trim( (string) hk9_rec_meta( $hk9_id, 'canine_name', '' ) );
$hk9_handler = trim( (string) hk9_rec_meta( $hk9_id, 'handler_name', '' ) );
$hk9_year    = trim( (string) hk9_rec_meta( $hk9_id, 'year', '' ) );
$hk9_summary = trim( (string) hk9_rec_meta( $hk9_id, 'summary', '' ) );
$hk9_donate  = hk9_rec_meta( $hk9_id, 'donate_link', [] );
$hk9_thumb   = (int) get_post_thumbnail_id( $hk9_post );

if ( '' === $hk9_summary && has_excerpt( $hk9_post ) ) {
	$hk9_summary = wp_strip_all_tags( get_the_excerpt( $hk9_post ) );
}
if ( '' !== $hk9_summary ) {
	$hk9_summary = wp_html_excerpt( $hk9_summary, 200, '…' );
}

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
?>
<article class="hk9-card hk9-card--hover hk9-teams__card">
	<?php if ( $hk9_thumb > 0 ) : ?>
		<a class="hk9-card__media" href="<?php echo esc_url( $hk9_url ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo hk9_image( $hk9_thumb, 'hk9-card', [ 'alt' => '', 'sizes' => '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) 50vw, 400px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		</a>
	<?php endif; ?>
	<div class="hk9-card__body">
		<div class="hk9-card__meta hk9-card__meta--row">
			<?php echo hk9_rec_status_badge( $hk9_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
		<h3 class="hk9-card__title"><a href="<?php echo esc_url( $hk9_url ); ?>"><?php echo esc_html( $hk9_title ); ?></a></h3>
		<?php if ( ! empty( $hk9_meta ) ) : ?>
			<ul class="hk9-card__facts">
				<?php foreach ( $hk9_meta as $hk9_item ) : ?>
					<li><?php echo $hk9_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( '' !== $hk9_summary ) : ?>
			<p class="hk9-card__text"><?php echo esc_html( $hk9_summary ); ?></p>
		<?php endif; ?>
		<div class="hk9-card__actions hk9-card__actions--row">
			<?php if ( is_array( $hk9_donate ) && hk9_link_is_set( $hk9_donate ) ) : ?>
				<?php echo hk9_button( $hk9_donate, 'primary', [ 'label' => __( 'Support this team', 'heartland-k9s' ), 'icon' => 'heart' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php endif; ?>
			<a class="hk9-link" href="<?php echo esc_url( $hk9_url ); ?>"><?php esc_html_e( 'Meet the team', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: team title */ __( ': %s', 'heartland-k9s' ), $hk9_title ) ); ?></span></a>
		</div>
	</div>
</article>
