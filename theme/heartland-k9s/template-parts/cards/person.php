<?php
/**
 * Card: person — portrait (hk9-portrait), name, role, bio (post_content), org email + links.
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
$hk9_name  = get_the_title( $hk9_post );
$hk9_role  = trim( (string) hk9_rec_meta( $hk9_id, 'role', '' ) );
$hk9_email = sanitize_email( (string) hk9_rec_meta( $hk9_id, 'email', '' ) );
$hk9_quote = trim( (string) hk9_rec_meta( $hk9_id, 'quote', '' ) );
$hk9_links = hk9_rec_meta( $hk9_id, 'links', [] );
$hk9_links = is_array( $hk9_links ) ? array_values( array_filter( $hk9_links, 'is_array' ) ) : [];
$hk9_thumb = (int) get_post_thumbnail_id( $hk9_post );

$hk9_content = (string) $hk9_post->post_content;
$hk9_bio     = '';
if ( '' !== trim( $hk9_content ) ) {
	$hk9_bio = has_blocks( $hk9_content ) ? do_blocks( $hk9_content ) : wpautop( $hk9_content );
	$hk9_bio = wp_kses_post( $hk9_bio );
}
?>
<article class="hk9-people__card" id="<?php echo esc_attr( 'person-' . $hk9_id ); ?>">
	<div class="hk9-people__portrait">
		<?php if ( $hk9_thumb > 0 ) : ?>
			<?php echo hk9_image( $hk9_thumb, 'hk9-portrait', [ 'alt' => $hk9_name, 'sizes' => '160px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		<?php else : ?>
			<span class="hk9-people__initials" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $hk9_name, 0, 1 ) ) ); ?></span>
		<?php endif; ?>
	</div>
	<h3 class="hk9-people__name"><?php echo esc_html( $hk9_name ); ?></h3>
	<?php if ( '' !== $hk9_role ) : ?>
		<p class="hk9-people__role"><?php echo esc_html( $hk9_role ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $hk9_quote ) : ?>
		<blockquote class="hk9-people__quote"><p>“<?php echo esc_html( trim( $hk9_quote, "\"“” \n\r\t" ) ); ?>”</p></blockquote>
	<?php endif; ?>
	<?php if ( '' !== $hk9_bio ) : ?>
		<div class="hk9-people__bio hk9-prose"><?php echo $hk9_bio; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() above. ?></div>
	<?php endif; ?>
	<?php if ( '' !== $hk9_email || ! empty( $hk9_links ) ) : ?>
		<ul class="hk9-people__links">
			<?php if ( '' !== $hk9_email && is_email( $hk9_email ) ) : ?>
				<li><a class="hk9-link" href="<?php echo esc_url( 'mailto:' . $hk9_email ); ?>"><?php echo hk9_icon( 'mail', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php echo esc_html( $hk9_email ); ?></span></a></li>
			<?php endif; ?>
			<?php
			foreach ( $hk9_links as $hk9_row ) :
				$hk9_link = is_array( $hk9_row['link'] ?? null ) ? $hk9_row['link'] : $hk9_row;
				if ( ! hk9_link_is_set( $hk9_link ) ) {
					continue;
				}
				$hk9_label = hk9_link_label( $hk9_link, __( 'Website', 'heartland-k9s' ) );
				?>
				<li><a class="hk9-link" href="<?php echo esc_url( hk9_theme_link_url( $hk9_link ) ); ?>"<?php echo hk9_theme_link_attrs( $hk9_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>><?php echo hk9_icon( 'external-link', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php echo esc_html( $hk9_label ); ?></span></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</article>
