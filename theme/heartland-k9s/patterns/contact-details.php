<?php
/**
 * Title: Contact details block
 * Slug: heartland-k9s/contact-details
 * Categories: heartland-k9s
 * Description: A muted card with the phone, e-mail, address and office hours from Heartland → Settings → Contact (copied in when inserted; edit or delete any line).
 * Keywords: contact, phone, email, address, hours
 * Viewport Width: 1024
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_phone       = trim( (string) hk9_theme_option( 'contact.phone_main' ) );
$hk9_phone_label = trim( (string) hk9_theme_option( 'contact.phone_main_label' ) );
$hk9_phone2      = trim( (string) hk9_theme_option( 'contact.phone_secondary' ) );
$hk9_phone2_lbl  = trim( (string) hk9_theme_option( 'contact.phone_secondary_label' ) );
$hk9_email       = trim( (string) hk9_theme_option( 'contact.email' ) );
$hk9_address     = hk9_contact_address();
$hk9_hours       = trim( (string) hk9_theme_option( 'contact.hours_days' ) . ' ' . (string) hk9_theme_option( 'contact.hours' ) );

$hk9_rows = [];
if ( '' !== $hk9_phone ) {
	$hk9_rows[] = [ '' !== $hk9_phone_label ? $hk9_phone_label : __( 'Phone', 'heartland-k9s' ), '<a href="' . esc_attr( hk9_tel_href( $hk9_phone ) ) . '">' . esc_html( $hk9_phone ) . '</a>' ];
}
if ( '' !== $hk9_phone2 ) {
	$hk9_rows[] = [ '' !== $hk9_phone2_lbl ? $hk9_phone2_lbl : __( 'Phone', 'heartland-k9s' ), '<a href="' . esc_attr( hk9_tel_href( $hk9_phone2 ) ) . '">' . esc_html( $hk9_phone2 ) . '</a>' ];
}
if ( '' !== $hk9_email ) {
	$hk9_rows[] = [ __( 'Email', 'heartland-k9s' ), '<a href="' . esc_url( 'mailto:' . $hk9_email ) . '">' . esc_html( $hk9_email ) . '</a>' ];
}
if ( '' !== $hk9_address ) {
	$hk9_rows[] = [ __( 'Address', 'heartland-k9s' ), esc_html( $hk9_address ) ];
}
if ( '' !== $hk9_hours ) {
	$hk9_rows[] = [ __( 'Office hours', 'heartland-k9s' ), esc_html( $hk9_hours ) ];
}
if ( empty( $hk9_rows ) ) {
	$hk9_rows[] = [ __( 'Phone', 'heartland-k9s' ), esc_html_x( 'Add the phone number', 'pattern placeholder', 'heartland-k9s' ) ];
	$hk9_rows[] = [ __( 'Email', 'heartland-k9s' ), esc_html_x( 'Add the e-mail address', 'pattern placeholder', 'heartland-k9s' ) ];
}
?>
<!-- wp:group {"className":"is-style-hk9-card-muted","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-hk9-card-muted"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Contact us', 'pattern placeholder', 'heartland-k9s' ); ?></h3>
<!-- /wp:heading -->

<?php foreach ( $hk9_rows as $hk9_row ) : ?>
<!-- wp:paragraph -->
<p><strong><?php echo esc_html( $hk9_row[0] ); ?>:</strong> <?php echo $hk9_row[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></p>
<!-- /wp:paragraph -->

<?php endforeach; ?></div>
<!-- /wp:group -->
