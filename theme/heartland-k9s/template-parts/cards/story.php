<?php
/**
 * Card: story — image, title, meta line (veteran · branch · canine), quote/excerpt, read link.
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

$hk9_id    = (int) $hk9_post->ID;
$hk9_url   = get_permalink( $hk9_post );
$hk9_title = get_the_title( $hk9_post );
$hk9_quote = trim( (string) hk9_rec_meta( $hk9_id, 'quote', '' ) );
$hk9_meta  = hk9_rec_story_meta( $hk9_id );
$hk9_thumb = (int) get_post_thumbnail_id( $hk9_post );
$hk9_text  = '' !== $hk9_quote ? '“' . trim( $hk9_quote, "\"“” \n\r\t" ) . '”' : ( has_excerpt( $hk9_post ) ? wp_strip_all_tags( get_the_excerpt( $hk9_post ) ) : '' );
if ( '' !== $hk9_text ) {
	$hk9_text = wp_html_excerpt( $hk9_text, 200, '…' );
}
?>
<article class="hk9-card hk9-card--hover hk9-stories__card">
	<?php if ( $hk9_thumb > 0 ) : ?>
		<a class="hk9-card__media" href="<?php echo esc_url( $hk9_url ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo hk9_image( $hk9_thumb, 'hk9-card', [ 'alt' => '', 'sizes' => '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) 50vw, 400px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		</a>
	<?php endif; ?>
	<div class="hk9-card__body">
		<h3 class="hk9-card__title"><a href="<?php echo esc_url( $hk9_url ); ?>"><?php echo esc_html( $hk9_title ); ?></a></h3>
		<?php if ( ! empty( $hk9_meta ) ) : ?>
			<ul class="hk9-card__facts">
				<?php foreach ( $hk9_meta as $hk9_item ) : ?>
					<li><?php echo $hk9_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( '' !== $hk9_text ) : ?>
			<p class="hk9-card__text<?php echo '' !== $hk9_quote ? ' hk9-card__text--quote' : ''; ?>"><?php echo esc_html( $hk9_text ); ?></p>
		<?php endif; ?>
		<a class="hk9-card__link" href="<?php echo esc_url( $hk9_url ); ?>"><?php esc_html_e( 'Read the story', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: story title */ __( ': %s', 'heartland-k9s' ), $hk9_title ) ); ?></span></a>
	</div>
</article>
