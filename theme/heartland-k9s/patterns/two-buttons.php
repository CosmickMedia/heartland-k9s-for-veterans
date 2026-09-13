<?php
/**
 * Title: Two buttons row
 * Slug: heartland-k9s/two-buttons
 * Categories: heartland-k9s
 * Description: A crimson button and a navy outline button side by side (stack on phones). Edit the labels and links in place.
 * Keywords: buttons, cta, links
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_donate = hk9_theme_option( 'links.donate' );
$hk9_donate = is_array( $hk9_donate ) && hk9_link_is_set( $hk9_donate ) ? hk9_theme_link_url( $hk9_donate ) : home_url( '/donate/' );
$hk9_apply  = hk9_theme_option( 'links.application' );
$hk9_apply  = is_array( $hk9_apply ) && hk9_link_is_set( $hk9_apply ) ? hk9_theme_link_url( $hk9_apply ) : home_url( '/online-application/' );
?>
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $hk9_donate ); ?>"><?php echo esc_html_x( 'Support a service dog', 'pattern placeholder', 'heartland-k9s' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $hk9_apply ); ?>"><?php echo esc_html_x( 'Apply for a dog', 'pattern placeholder', 'heartland-k9s' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
