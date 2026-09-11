<?php
/**
 * Empty-state panel for listings (home, archives, search).
 *
 * @package heartland-k9s
 *
 * @var array $args { title: string, text: string, search: bool, links: bool }
 */

defined( 'ABSPATH' ) || exit;

$hk9_copy  = hk9_blog_empty_copy();
$hk9_title = trim( (string) ( $args['title'] ?? $hk9_copy['title'] ) );
$hk9_text  = trim( (string) ( $args['text'] ?? $hk9_copy['text'] ) );
$hk9_links = ! empty( $args['links'] ) ? hk9_blog_helpful_links() : [];
$hk9_news  = hk9_blog_url();
?>
<div class="hk9-overlap__card hk9-blog__empty">
	<?php echo hk9_icon( is_search() ? 'search' : 'paw-print', [ 'size' => 32, 'class' => 'hk9-blog__empty-icon' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<h2 class="hk9-blog__empty-title"><?php echo esc_html( $hk9_title ); ?></h2>
	<?php if ( '' !== $hk9_text ) : ?>
		<p class="hk9-blog__empty-text"><?php echo esc_html( $hk9_text ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $args['search'] ) ) : ?>
		<div class="hk9-blog__empty-search">
			<?php get_search_form(); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $hk9_links ) ) : ?>
		<nav class="hk9-blog__empty-links" aria-label="<?php esc_attr_e( 'Helpful pages', 'heartland-k9s' ); ?>">
			<ul>
				<?php foreach ( $hk9_links as $hk9_link ) : ?>
					<li><a class="hk9-btn hk9-btn--outline" href="<?php echo esc_url( $hk9_link['url'] ); ?>"<?php echo $hk9_link['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>><?php echo esc_html( $hk9_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php elseif ( ! is_home() ) : ?>
		<div class="hk9-blog__empty-actions">
			<a class="hk9-btn hk9-btn--navy hk9-btn--lg" href="<?php echo esc_url( $hk9_news ); ?>"><?php esc_html_e( 'Browse all news', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 20, 'class' => 'hk9-icon--20' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
		</div>
	<?php endif; ?>
</div>
