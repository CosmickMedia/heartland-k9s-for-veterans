<?php
/**
 * 404: navy band + card with a search form and helpful links
 * (Home, About, Program, Contact, Donate, News).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hk9_context = hk9_archive_context();
$hk9_links   = hk9_blog_helpful_links();

get_template_part( 'template-parts/blog/archive-hero', null, $hk9_context );
?>
<div class="hk9-overlap hk9-blog__overlap">
	<div class="hk9-overlap__card hk9-404">
		<div class="hk9-404__search">
			<h2 class="hk9-404__title"><?php esc_html_e( 'Search the site', 'heartland-k9s' ); ?></h2>
			<?php get_search_form(); ?>
		</div>

		<?php if ( ! empty( $hk9_links ) ) : ?>
			<nav class="hk9-404__links" aria-labelledby="hk9-404-links-title">
				<h2 id="hk9-404-links-title" class="hk9-404__title"><?php esc_html_e( 'Or try one of these pages', 'heartland-k9s' ); ?></h2>
				<ul>
					<?php foreach ( $hk9_links as $hk9_link ) : ?>
						<li><a class="hk9-btn hk9-btn--outline" href="<?php echo esc_url( $hk9_link['url'] ); ?>"<?php echo $hk9_link['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>><?php echo esc_html( $hk9_link['label'] ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
