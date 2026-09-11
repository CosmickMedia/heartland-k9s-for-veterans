<?php
/**
 * Post meta row: date (blog.show_date), author (blog.show_author), optional
 * comment count. Author names are plain text (no /author/ link) so login-derived
 * slugs are not advertised; author archives still resolve when requested directly.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post, light: bool, comments: bool, class: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = isset( $args['post'] ) ? get_post( $args['post'] ) : get_post();
if ( ! $hk9_post instanceof WP_Post ) {
	return;
}

$hk9_show_date   = (bool) hk9_blog_option( 'show_date' );
$hk9_show_author = (bool) hk9_blog_option( 'show_author' );
$hk9_comments    = ! empty( $args['comments'] ) && post_type_supports( $hk9_post->post_type, 'comments' ) && ( comments_open( $hk9_post ) || get_comments_number( $hk9_post ) > 0 );
$hk9_items       = [];

if ( $hk9_show_date ) {
	$hk9_items[] = '<span class="hk9-blog__meta-item hk9-blog__meta-date">'
		. hk9_icon( 'calendar', [ 'size' => 16 ] )
		. '<time datetime="' . esc_attr( get_the_date( 'c', $hk9_post ) ) . '">' . esc_html( get_the_date( '', $hk9_post ) ) . '</time>'
		. '</span>';
}

if ( $hk9_show_author ) {
	$hk9_author = get_the_author_meta( 'display_name', (int) $hk9_post->post_author );
	if ( '' !== $hk9_author ) {
		$hk9_items[] = '<span class="hk9-blog__meta-item hk9-blog__meta-author">'
			/* translators: %s: author display name */
			. esc_html( sprintf( __( 'By %s', 'heartland-k9s' ), $hk9_author ) )
			. '</span>';
	}
}

if ( $hk9_comments ) {
	$hk9_count   = (int) get_comments_number( $hk9_post );
	$hk9_items[] = '<a class="hk9-blog__meta-item hk9-blog__meta-comments" href="' . esc_url( get_comments_link( $hk9_post ) ) . '">'
		/* translators: %s: number of comments */
		. esc_html( sprintf( _n( '%s comment', '%s comments', $hk9_count, 'heartland-k9s' ), number_format_i18n( $hk9_count ) ) )
		. '</a>';
}

if ( empty( $hk9_items ) ) {
	return;
}

$hk9_classes = [ 'hk9-blog__meta' ];
if ( ! empty( $args['light'] ) ) {
	$hk9_classes[] = 'hk9-blog__meta--light';
}
if ( ! empty( $args['class'] ) ) {
	$hk9_classes[] = (string) $args['class'];
}
?>
<div class="<?php echo esc_attr( implode( ' ', $hk9_classes ) ); ?>">
	<?php echo implode( '<span class="hk9-blog__meta-sep" aria-hidden="true">·</span>', $hk9_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
</div>
