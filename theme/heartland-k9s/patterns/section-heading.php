<?php
/**
 * Title: Section heading with crimson divider
 * Slug: heartland-k9s/section-heading
 * Categories: heartland-k9s
 * Description: Centred heading, the short crimson bar and an optional intro line — the section header used across the site.
 * Keywords: heading, divider, title, section
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html_x( 'Section heading', 'pattern placeholder', 'heartland-k9s' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:separator {"className":"is-style-hk9-divider"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-hk9-divider"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"align":"center","className":"is-style-hk9-lead"} -->
<p class="has-text-align-center is-style-hk9-lead"><?php echo esc_html_x( 'One or two sentences introducing the section. Delete this paragraph if you do not need it.', 'pattern placeholder', 'heartland-k9s' ); ?></p>
<!-- /wp:paragraph -->
