<?php
/**
 * Fallback template (posts index / anything without a more specific template).
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hk9_title = is_home() ? (string) hk9_theme_option( 'blog.hero_title' ) : wp_strip_all_tags( get_the_archive_title() );
$hk9_text  = is_home() ? (string) hk9_theme_option( 'blog.hero_text' ) : wp_strip_all_tags( get_the_archive_description() );

if ( is_search() ) {
	/* translators: %s: search query */
	$hk9_title = sprintf( __( 'Search results for “%s”', 'heartland-k9s' ), get_search_query() );
	$hk9_text  = '';
}

hk9_the_hero( [ 'heading' => $hk9_title ?: get_bloginfo( 'name' ), 'text' => $hk9_text ], 'band' );
?>
<div class="hk9-overlap">
	<div class="hk9-overlap__card">
		<?php if ( have_posts() ) : ?>
			<div class="hk9-blog__list<?php echo 'grid' === hk9_theme_option( 'blog.layout' ) ? ' hk9-blog__list--grid' : ''; ?>">
				<?php
				while ( have_posts() ) :
					the_post();
					hk9_post_card( get_post() );
				endwhile;
				?>
			</div>
			<?php hk9_pagination(); ?>
		<?php else : ?>
			<div class="hk9-blog__empty">
				<h2><?php esc_html_e( 'Nothing here yet', 'heartland-k9s' ); ?></h2>
				<p><?php esc_html_e( 'Check back soon — new updates are on the way.', 'heartland-k9s' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>
<?php
get_footer();
