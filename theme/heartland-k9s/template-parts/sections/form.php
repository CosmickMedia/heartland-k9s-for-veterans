<?php
/**
 * Section: form (contact) — "Send a Message" column. Shows the form of the
 * section's provider: the plugin's built-in form (`hk9_the_form()`), a Gravity
 * Forms form or a form shortcode (`hk9_form_provider()` resolves the section
 * value, falling back to Settings → Forms). Guarded so the theme keeps working
 * without the plugin.
 *
 * Rendered inside the contact card by page-templates/contact.php (column
 * lg:w-3/5 p-10 md:p-12 bg-card); the part also works stand-alone.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_id       = (string) ( $args['id'] ?? 'form' );
$hk9_post_id  = (int) ( $args['post_id'] ?? get_the_ID() );
$hk9_template = (string) ( $args['template'] ?? 'contact' );
$hk9_data     = hk9_pages_section( $hk9_post_id, $hk9_template, $hk9_id );
if ( empty( $hk9_data ) && is_array( $args['data'] ?? null ) ) {
	$hk9_data = $args['data'];
}

$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_form    = ( $hk9_data['form'] ?? 'contact' ) === 'application' ? 'application' : 'contact';
$hk9_success_heading = trim( (string) ( $hk9_data['success_heading'] ?? '' ) );
$hk9_success_text    = trim( (string) ( $hk9_data['success_text'] ?? '' ) );

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';
$hk9_form_args  = [
	'id'      => 'hk9-' . sanitize_html_class( $hk9_id ) . '-' . $hk9_form,
	'heading' => '',
	'post_id' => $hk9_post_id,
];
if ( '' !== $hk9_success_heading ) {
	$hk9_form_args['success_heading'] = $hk9_success_heading;
}
if ( '' !== $hk9_success_text ) {
	$hk9_form_args['success_text'] = $hk9_success_text;
}

// Provider: built-in (default) / Gravity Forms / shortcode. External providers that cannot render fall back to the built-in form.
$hk9_provider      = hk9_form_provider( $hk9_data, $hk9_form );
$hk9_provider_html = 'builtin' !== ( $hk9_provider['provider'] ?? 'builtin' ) ? hk9_render_form_provider( $hk9_provider, [ 'id' => 'hk9-' . sanitize_html_class( $hk9_id ) . '-provider' ] ) : '';
?>
<div class="hk9-contact-card__form hk9-contact-form" id="<?php echo esc_attr( 'hk9-' . sanitize_html_class( $hk9_id ) ); ?>">
	<?php if ( '' !== $hk9_heading ) : ?>
		<h2 class="hk9-contact-form__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
	<?php endif; ?>
	<?php echo hk9_paragraphs( $hk9_intro, 'hk9-contact-form__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<?php
	if ( '' !== $hk9_provider_html ) {
		echo $hk9_provider_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- provider markup (Gravity Forms / shortcode output).
	} else {
		echo hk9_form_provider_notice( $hk9_provider ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper; '' for visitors.
		if ( function_exists( 'hk9_the_form' ) ) {
			hk9_the_form( $hk9_form, $hk9_form_args );
		} else {
			$hk9_email = sanitize_email( (string) hk9_theme_option( 'contact.email' ) );
			echo '<p class="hk9-notice hk9-contact-form__notice">';
			if ( '' !== $hk9_email ) {
				printf(
					/* translators: %s: email link */
					esc_html__( 'Our contact form is temporarily unavailable. Please email us at %s and we will get back to you.', 'heartland-k9s' ),
					'<a href="' . esc_url( 'mailto:' . $hk9_email ) . '">' . esc_html( $hk9_email ) . '</a>'
				);
			} else {
				esc_html_e( 'Our contact form is temporarily unavailable. Please check back soon.', 'heartland-k9s' );
			}
			echo '</p>';
		}
	}
	?>
</div>
