<?php
/**
 * Post card: featured image (16:10, hk9-card size) or neutral placeholder,
 * category chips, title, excerpt, date/author meta (per settings toggles), read more.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post, layout: list|grid|compact, highlight: string, heading: h2|h3 }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = isset( $args['post'] ) ? get_post( $args['post'] ) : get_post();
if ( ! $hk9_post instanceof WP_Post ) {
	return;
}

$hk9_layout    = (string) ( $args['layout'] ?? 'list' );
$hk9_compact   = 'compact' === $hk9_layout;
$hk9_highlight = (string) ( $args['highlight'] ?? '' );
$hk9_heading   = 'h3' === ( $args['heading'] ?? '' ) ? 'h3' : 'h2';
$hk9_url       = (string) get_permalink( $hk9_post );
$hk9_title     = get_the_title( $hk9_post );
$hk9_protected = post_password_required( $hk9_post );
$hk9_sticky    = is_sticky( $hk9_post->ID ) && is_home() && ! is_paged();

$hk9_show_image = (bool) hk9_blog_option( 'show_featured_image' );
$hk9_show_cats  = (bool) hk9_blog_option( 'show_categories' );

$hk9_title_html = esc_html( $hk9_title );
$hk9_excerpt    = $hk9_compact ? '' : hk9_blog_card_excerpt( $hk9_post );
$hk9_excerpt_h  = esc_html( $hk9_excerpt );
if ( '' !== $hk9_highlight ) {
	$hk9_title_html = hk9_search_highlight( $hk9_title_html, $hk9_highlight );
	$hk9_excerpt_h  = hk9_search_highlight( $hk9_excerpt_h, $hk9_highlight );
}

$hk9_classes = [ 'hk9-blog__card' ];
if ( $hk9_compact ) {
	$hk9_classes[] = 'hk9-blog__card--compact';
}
if ( $hk9_sticky ) {
	$hk9_classes[] = 'hk9-blog__card--sticky';
}
if ( $hk9_protected ) {
	$hk9_classes[] = 'hk9-blog__card--protected';
}

$hk9_thumb_id = $hk9_show_image && ! $hk9_protected && has_post_thumbnail( $hk9_post ) ? (int) get_post_thumbnail_id( $hk9_post ) : 0;
$hk9_sizes    = 'grid' === $hk9_layout || $hk9_compact
	? '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) calc(50vw - 48px), 400px'
	: '(max-width: 767px) calc(100vw - 32px), 410px';
?>
<article id="post-<?php echo esc_attr( (string) $hk9_post->ID ); ?>" class="<?php echo esc_attr( implode( ' ', $hk9_classes ) ); ?>">
	<?php if ( $hk9_thumb_id > 0 ) : ?>
		<a class="hk9-blog__card-media" href="<?php echo esc_url( $hk9_url ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo hk9_image( $hk9_thumb_id, 'hk9-card', [ 'sizes' => $hk9_sizes ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		</a>
	<?php elseif ( $hk9_show_image ) : ?>
		<div class="hk9-blog__card-media hk9-blog__card-media--placeholder hk9-pattern hk9-pattern--grid" aria-hidden="true">
			<?php echo hk9_icon( $hk9_protected ? 'shield-check' : 'paw-print', [ 'size' => 40 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<div class="hk9-blog__card-body">
		<?php if ( $hk9_sticky || ( $hk9_show_cats && ! $hk9_compact ) ) : ?>
			<div class="hk9-blog__card-top">
				<?php if ( $hk9_sticky ) : ?>
					<span class="hk9-badge hk9-badge--solid hk9-blog__card-badge"><?php esc_html_e( 'Featured', 'heartland-k9s' ); ?></span>
				<?php endif; ?>
				<?php
				if ( $hk9_show_cats && ! $hk9_compact ) {
					echo hk9_blog_post_terms( $hk9_post, 'category', [ 'limit' => 3 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				}
				?>
			</div>
		<?php endif; ?>

		<<?php echo $hk9_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag name. ?> class="hk9-blog__card-title">
			<a href="<?php echo esc_url( $hk9_url ); ?>"><?php echo $hk9_title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></a>
		</<?php echo $hk9_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag name. ?>>

		<?php if ( '' !== $hk9_excerpt ) : ?>
			<p class="hk9-blog__excerpt"><?php echo $hk9_excerpt_h; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></p>
		<?php endif; ?>

		<div class="hk9-blog__card-footer">
			<?php hk9_blog_post_meta( $hk9_post, [ 'class' => 'hk9-blog__card-meta' ] ); ?>
			<?php if ( ! $hk9_compact ) : ?>
				<a class="hk9-link hk9-blog__more" href="<?php echo esc_url( $hk9_url ); ?>">
					<?php echo $hk9_protected ? esc_html__( 'Unlock', 'heartland-k9s' ) : esc_html__( 'Read more', 'heartland-k9s' ); ?>
					<span class="screen-reader-text">
						<?php
						/* translators: %s: post title */
						echo esc_html( sprintf( __( ': %s', 'heartland-k9s' ), $hk9_title ) );
						?>
					</span>
					<?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</article>
