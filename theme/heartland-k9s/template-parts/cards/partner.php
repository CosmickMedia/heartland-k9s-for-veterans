<?php
/**
 * Card: partner — logo tile linked to the partner website (rel noopener), name caption, tier.
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
$hk9_name    = get_the_title( $hk9_post );
$hk9_tier    = trim( (string) hk9_rec_meta( $hk9_id, 'tier', '' ) );
$hk9_since   = trim( (string) hk9_rec_meta( $hk9_id, 'since', '' ) );
$hk9_website = hk9_rec_meta( $hk9_id, 'website', [] );
$hk9_website = is_array( $hk9_website ) ? $hk9_website : [];
$hk9_url     = hk9_theme_link_url( $hk9_website );
$hk9_thumb   = (int) get_post_thumbnail_id( $hk9_post );
$hk9_logo    = $hk9_thumb > 0 ? hk9_image( $hk9_thumb, 'hk9-logo', [ 'alt' => '', 'sizes' => '(max-width: 767px) 40vw, 220px' ] ) : '';
$hk9_tag     = '' !== $hk9_url ? 'a' : 'div';
$hk9_attrs   = '' !== $hk9_url ? ' href="' . esc_url( $hk9_url ) . '"' . hk9_theme_link_attrs( $hk9_website ) : '';
?>
<<?php echo $hk9_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal tag name. ?> class="hk9-partners__item<?php echo '' === $hk9_logo ? ' hk9-partners__item--text' : ''; ?>"<?php echo $hk9_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<?php if ( '' !== $hk9_logo ) : ?>
		<span class="hk9-partners__logo"><?php echo $hk9_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></span>
	<?php endif; ?>
	<span class="hk9-partners__name"><?php echo esc_html( $hk9_name ); ?></span>
	<?php if ( '' !== $hk9_tier || '' !== $hk9_since ) : ?>
		<span class="hk9-partners__tier">
			<?php
			echo esc_html(
				implode(
					' · ',
					array_filter(
						[
							$hk9_tier,
							/* translators: %s: year */
							'' !== $hk9_since ? sprintf( __( 'Since %s', 'heartland-k9s' ), $hk9_since ) : '',
						]
					)
				)
			);
			?>
		</span>
	<?php endif; ?>
	<?php if ( '' !== $hk9_url ) : ?>
		<span class="screen-reader-text"><?php esc_html_e( '(opens partner website)', 'heartland-k9s' ); ?></span>
	<?php endif; ?>
</<?php echo $hk9_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal tag name. ?>>
