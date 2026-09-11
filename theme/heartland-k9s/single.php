<?php
/**
 * Single post: navy header band (category chips, title, date/author meta),
 * featured image overlapping the band, reading-width block content with
 * in-article pagination, tags, author box, older/newer navigation, related
 * posts and comments.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_post      = get_post();
	$hk9_post_id   = (int) get_the_ID();
	$hk9_protected = post_password_required( $hk9_post );
	$hk9_show_cats = (bool) hk9_blog_option( 'show_categories' );
	$hk9_show_tags = (bool) hk9_blog_option( 'show_tags' ) && ! $hk9_protected;
	$hk9_show_auth = (bool) hk9_blog_option( 'show_author' );
	$hk9_thumb_id  = (bool) hk9_blog_option( 'show_featured_image' ) && ! $hk9_protected && has_post_thumbnail( $hk9_post ) ? (int) get_post_thumbnail_id( $hk9_post ) : 0;
	$hk9_caption   = $hk9_thumb_id > 0 ? trim( (string) wp_get_attachment_caption( $hk9_thumb_id ) ) : '';
	$hk9_author_id = (int) $hk9_post->post_author;
	$hk9_author    = get_the_author_meta( 'display_name', $hk9_author_id );
	$hk9_bio       = trim( (string) get_the_author_meta( 'description', $hk9_author_id ) );
	?>
	<article id="post-<?php echo esc_attr( (string) $hk9_post_id ); ?>" <?php post_class( 'hk9-post' ); ?>>
		<header class="hk9-hero hk9-hero--band hk9-hero--post<?php echo $hk9_thumb_id > 0 ? ' hk9-hero--post-has-image' : ''; ?>">
			<div class="hk9-hero__content">
				<?php
				if ( $hk9_show_cats ) {
					echo hk9_blog_post_terms( $hk9_post, 'category', [ 'class' => 'hk9-blog__terms--light hk9-post__terms' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				}
				?>
				<h1 id="hk9-hero-title" class="hk9-hero__title hk9-post__title"><?php the_title(); ?></h1>
				<?php hk9_blog_post_meta( $hk9_post, [ 'light' => true, 'comments' => true, 'class' => 'hk9-post__meta' ] ); ?>
			</div>
		</header>

		<?php if ( $hk9_thumb_id > 0 ) : ?>
			<div class="hk9-overlap hk9-post__overlap">
				<figure class="hk9-post__featured">
					<?php echo hk9_image( $hk9_thumb_id, 'hk9-hero', [ 'sizes' => '(max-width: 1087px) calc(100vw - 32px), 1024px' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
					<?php if ( '' !== $hk9_caption ) : ?>
						<figcaption class="hk9-post__featured-caption"><?php echo wp_kses_post( $hk9_caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			</div>
		<?php endif; ?>

		<div class="hk9-post__body container">
			<div class="hk9-prose hk9-prose--reading hk9-post__content">
				<?php the_content(); ?>
			</div>

			<?php if ( ! $hk9_protected ) : ?>
				<div class="hk9-post__after">
					<?php get_template_part( 'template-parts/blog/post-navigation' ); ?>

					<?php
					if ( $hk9_show_tags ) {
						$hk9_tags_html = hk9_blog_post_terms( $hk9_post, 'post_tag', [ 'class' => 'hk9-blog__terms--tags' ] );
						if ( '' !== $hk9_tags_html ) {
							echo '<div class="hk9-post__tags"><span class="hk9-post__tags-label">' . esc_html__( 'Tagged', 'heartland-k9s' ) . '</span>' . $hk9_tags_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
						}
					}
					?>

					<?php if ( $hk9_show_auth && '' !== $hk9_author ) : ?>
						<aside class="hk9-author-box" aria-label="<?php esc_attr_e( 'About the author', 'heartland-k9s' ); ?>">
							<?php echo hk9_blog_avatar( $hk9_author, 56, 'hk9-author-box__avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<div class="hk9-author-box__body">
								<span class="hk9-author-box__eyebrow"><?php esc_html_e( 'Written by', 'heartland-k9s' ); ?></span>
								<span class="hk9-author-box__name"><?php echo esc_html( $hk9_author ); ?></span>
								<?php if ( '' !== $hk9_bio ) : ?>
									<p class="hk9-author-box__bio"><?php echo esc_html( $hk9_bio ); ?></p>
								<?php endif; ?>
							</div>
						</aside>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! $hk9_protected ) : ?>
			<footer class="hk9-post__footer container">
				<?php get_template_part( 'template-parts/blog/prev-next', null, [ 'post' => $hk9_post ] ); ?>
			</footer>
		<?php endif; ?>
	</article>

	<?php
	if ( ! $hk9_protected ) {
		get_template_part( 'template-parts/blog/related', null, [ 'post' => $hk9_post ] );
	}

	if ( ! $hk9_protected && ( comments_open() || get_comments_number() ) ) {
		echo '<div class="container hk9-post__comments">';
		comments_template();
		echo '</div>';
	}
endwhile;

get_footer();
