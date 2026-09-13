<?php
/**
 * Title: Quote / testimonial
 * Slug: heartland-k9s/testimonial
 * Categories: heartland-k9s
 * Description: A quote in the muted rounded panel used for testimonials, with the name and detail line underneath. Only publish approved names and quotes.
 * Keywords: quote, testimonial, story, veteran
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:quote {"className":"is-style-hk9-testimonial"} -->
<blockquote class="wp-block-quote is-style-hk9-testimonial"><!-- wp:paragraph -->
<p><?php echo esc_html_x( 'Paste the approved quote here. Keep it to a few sentences so it reads well as a pull quote.', 'pattern placeholder', 'heartland-k9s' ); ?></p>
<!-- /wp:paragraph --><cite><?php echo esc_html_x( 'Approved name, branch of service', 'pattern placeholder', 'heartland-k9s' ); ?></cite></blockquote>
<!-- /wp:quote -->
