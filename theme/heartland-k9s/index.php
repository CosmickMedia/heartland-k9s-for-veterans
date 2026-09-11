<?php
/**
 * Fallback template (replaced by the full template set in Phase 4).
 *
 * @package heartland-k9s
 */

get_header();
?>
<main id="main" class="hk9-main">
	<div class="container">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				the_title( '<h1>', '</h1>' );
				the_content();
			endwhile;
		endif;
		?>
	</div>
</main>
<?php
get_footer();
