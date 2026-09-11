<?php
/**
 * Related posts (same category, blog.related_count) as compact cards.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = isset( $args['post'] ) ? get_post( $args['post'] ) : get_post();
if ( ! $hk9_post instanceof WP_Post || ! hk9_blog_option( 'show_related' ) ) {
	return;
}

$hk9_related = hk9_blog_related_posts( $hk9_post, (int) hk9_blog_option( 'related_count', 3 ) );
if ( empty( $hk9_related ) ) {
	return;
}
?>
<section class="hk9-section hk9-section--tint hk9-section--py16 hk9-blog__related" aria-labelledby="hk9-related-title">
	<div class="container">
		<div class="hk9-section__header hk9-blog__related-header">
			<span class="hk9-badge hk9-badge--tint"><?php esc_html_e( 'Keep reading', 'heartland-k9s' ); ?></span>
			<h2 id="hk9-related-title" class="hk9-section__title"><?php esc_html_e( 'Related articles', 'heartland-k9s' ); ?></h2>
		</div>
		<div class="hk9-blog__list hk9-blog__list--grid hk9-blog__list--related">
			<?php
			foreach ( $hk9_related as $hk9_item ) {
				hk9_blog_post_card( $hk9_item, [ 'layout' => 'compact', 'heading' => 'h3' ] );
			}
			?>
		</div>
	</div>
</section>
