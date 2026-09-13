<?php
/**
 * Title: Text + image (image left)
 * Slug: heartland-k9s/text-image-left
 * Categories: heartland-k9s
 * Description: Photo on the left, heading + paragraph + button on the right; stacks on phones. Click the picture to replace it.
 * Keywords: image, photo, text, split, media
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_placeholder = esc_url( hk9_pattern_placeholder_image() );
?>
<!-- wp:media-text {"mediaPosition":"left","mediaType":"image","mediaSizeSlug":"large","verticalAlignment":"center"} -->
<div class="wp-block-media-text is-stacked-on-mobile is-vertically-aligned-center"><figure class="wp-block-media-text__media"><img src="<?php echo $hk9_placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>" alt="" /></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html_x( 'A heading for this section', 'pattern placeholder', 'heartland-k9s' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Use this space for a short story or explanation — two or three sentences work best next to a photo. Replace the picture by clicking it and choosing Replace.', 'pattern placeholder', 'heartland-k9s' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html_x( 'Learn more', 'pattern placeholder', 'heartland-k9s' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:media-text -->
