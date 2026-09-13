<?php
/**
 * Title: Call to action band
 * Slug: heartland-k9s/cta-band
 * Categories: heartland-k9s
 * Description: Navy band with a heading, one line of text and two buttons (crimson + outline). Switch to "Tinted band" in the block Styles for the light version.
 * Keywords: cta, call to action, band, donate, buttons
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_donate = hk9_theme_option( 'links.donate' );
$hk9_donate = is_array( $hk9_donate ) && hk9_link_is_set( $hk9_donate ) ? hk9_theme_link_url( $hk9_donate ) : home_url( '/donate/' );
$hk9_help   = hk9_theme_option( 'links.volunteer' );
$hk9_help   = is_array( $hk9_help ) && hk9_link_is_set( $hk9_help ) ? hk9_theme_link_url( $hk9_help ) : home_url( '/get-involved/' );
?>
<!-- wp:group {"className":"is-style-hk9-band","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-hk9-band"><!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html_x( 'Join us in our mission', 'pattern placeholder', 'heartland-k9s' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php echo esc_html_x( 'Every donation, volunteer hour and shared story helps us provide another service dog to a veteran in need.', 'pattern placeholder', 'heartland-k9s' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $hk9_donate ); ?>"><?php echo esc_html_x( 'Make a donation', 'pattern placeholder', 'heartland-k9s' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $hk9_help ); ?>"><?php echo esc_html_x( 'Ways to help', 'pattern placeholder', 'heartland-k9s' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
