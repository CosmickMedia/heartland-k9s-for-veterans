<?php
/**
 * Adjacent post navigation (older / newer) with titles.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = isset( $args['post'] ) ? get_post( $args['post'] ) : get_post();
if ( ! $hk9_post instanceof WP_Post ) {
	return;
}

$hk9_older = get_previous_post();
$hk9_newer = get_next_post();

if ( ! $hk9_older instanceof WP_Post && ! $hk9_newer instanceof WP_Post ) {
	return;
}
?>
<nav class="hk9-blog__nav" aria-label="<?php esc_attr_e( 'Article navigation', 'heartland-k9s' ); ?>">
	<?php if ( $hk9_older instanceof WP_Post ) : ?>
		<a class="hk9-blog__nav-prev" href="<?php echo esc_url( (string) get_permalink( $hk9_older ) ); ?>" rel="prev">
			<span class="hk9-blog__nav-label"><?php echo hk9_icon( 'arrow-right', [ 'size' => 14, 'class' => 'hk9-icon--flip' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><?php esc_html_e( 'Older article', 'heartland-k9s' ); ?></span>
			<span class="hk9-blog__nav-title"><?php echo esc_html( get_the_title( $hk9_older ) ); ?></span>
		</a>
	<?php endif; ?>
	<?php if ( $hk9_newer instanceof WP_Post ) : ?>
		<a class="hk9-blog__nav-next" href="<?php echo esc_url( (string) get_permalink( $hk9_newer ) ); ?>" rel="next">
			<span class="hk9-blog__nav-label"><?php esc_html_e( 'Newer article', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 14 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<span class="hk9-blog__nav-title"><?php echo esc_html( get_the_title( $hk9_newer ) ); ?></span>
		</a>
	<?php endif; ?>
</nav>
