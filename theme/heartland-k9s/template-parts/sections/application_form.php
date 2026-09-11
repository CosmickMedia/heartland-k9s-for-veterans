<?php
/**
 * Section: application_form (application template, id `form`) — the initial
 * application inquiry form (plugin-rendered) plus a link card to the 5 Questions page.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'form' );
$hk9_post_id = (int) ( $args['post_id'] ?? get_the_ID() );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_notice  = trim( (string) ( $hk9_data['notice'] ?? '' ) );
$hk9_success = is_array( $hk9_data['success_page'] ?? null ) ? $hk9_data['success_page'] : [];
$hk9_show_5q = ! isset( $hk9_data['show_five_questions_link'] ) || ! empty( $hk9_data['show_five_questions_link'] );

$hk9_five = hk9_theme_option( 'links.five_questions' );
$hk9_five = is_array( $hk9_five ) && hk9_link_is_set( $hk9_five ) ? $hk9_five : [];

$hk9_form_args = [
	'id'                       => 'hk9-form-application',
	'post_id'                  => $hk9_post_id,
	'show_five_questions_link' => $hk9_show_5q,
];
if ( '' !== $hk9_heading ) {
	$hk9_form_args['heading'] = $hk9_heading;
}
if ( '' !== $hk9_notice ) {
	$hk9_form_args['notice'] = $hk9_notice;
}
if ( hk9_link_is_set( $hk9_success ) ) {
	$hk9_form_args['success_url'] = $hk9_success;
}

hk9_section_open( $hk9_id, 'hk9-section--py20 hk9-application' );
?>
<div class="container hk9-narrow">
	<?php if ( $hk9_show_5q && ! empty( $hk9_five ) ) : ?>
		<aside class="hk9-application__aside hk9-callout" aria-label="<?php esc_attr_e( 'Before you apply', 'heartland-k9s' ); ?>">
			<span class="hk9-icon-well hk9-icon-well--crimson"><?php echo hk9_icon( 'clipboard-check', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<div class="hk9-application__aside-body">
				<p class="hk9-application__aside-title"><?php esc_html_e( 'Before you apply', 'heartland-k9s' ); ?></p>
				<p class="hk9-application__aside-text"><?php esc_html_e( 'Please read the five questions every veteran should ask before partnering with a service dog.', 'heartland-k9s' ); ?></p>
			</div>
			<?php echo hk9_button( $hk9_five, 'outline', [ 'label' => __( 'Read the 5 Questions', 'heartland-k9s' ), 'icon' => 'arrow-right' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</aside>
	<?php endif; ?>

	<div class="hk9-application__form hk9-card hk9-card--panel">
		<?php if ( function_exists( 'hk9_the_form' ) ) : ?>
			<?php hk9_the_form( 'application', $hk9_form_args ); ?>
		<?php else : ?>
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-form__heading"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_notice, 'hk9-form__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<p class="hk9-notice"><?php esc_html_e( 'The application form is unavailable at the moment. Please contact us directly and we will send you the application.', 'heartland-k9s' ); ?></p>
			<?php
			$hk9_contact = hk9_theme_option( 'links.contact' );
			if ( is_array( $hk9_contact ) && hk9_link_is_set( $hk9_contact ) ) {
				echo '<p>' . hk9_button( $hk9_contact, 'primary', [ 'label' => __( 'Contact us', 'heartland-k9s' ) ] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			}
			?>
		<?php endif; ?>
	</div>
</div>
<?php
hk9_section_close();
