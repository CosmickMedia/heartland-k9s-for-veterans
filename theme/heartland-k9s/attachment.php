<?php
/**
 * Attachment page (only reachable when attachment pages are enabled — WordPress
 * 6.4+ redirects them to the file by default; Settings → Media or
 * `wp option update wp_attachment_pages_enabled 1`): hero band + media card.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_post   = get_post();
	$hk9_parent = $hk9_post->post_parent > 0 ? get_post( $hk9_post->post_parent ) : null;
	$hk9_parent = $hk9_parent instanceof WP_Post && 'publish' === $hk9_parent->post_status ? $hk9_parent : null;

	get_template_part(
		'template-parts/blog/archive-hero',
		null,
		[
			'eyebrow' => __( 'Media', 'heartland-k9s' ),
			'title'   => get_the_title(),
			/* translators: %s: parent post title */
			'text'    => $hk9_parent ? sprintf( __( 'Attached to “%s”.', 'heartland-k9s' ), get_the_title( $hk9_parent ) ) : '',
			'class'   => 'hk9-hero--attachment',
		]
	);
	?>
	<div class="hk9-overlap hk9-blog__overlap">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'hk9-overlap__card hk9-attachment-card' ); ?>>
			<?php get_template_part( 'template-parts/content/attachment', null, [ 'post' => $hk9_post ] ); ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
