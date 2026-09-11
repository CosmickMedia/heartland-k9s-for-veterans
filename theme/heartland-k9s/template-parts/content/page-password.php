<?php
/**
 * Password form for protected posts/pages (via the `the_password_form` filter).
 * Shows an inline error when a wrong password cookie is present.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = isset( $args['post'] ) ? get_post( $args['post'] ) : get_post();
if ( ! $hk9_post instanceof WP_Post ) {
	return;
}

$hk9_field_id = 'pwbox-' . $hk9_post->ID;
$hk9_error_id = $hk9_field_id . '-error';
// A postpass cookie that still fails post_password_required() means the last attempt was wrong.
$hk9_wrong = isset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] ) && post_password_required( $hk9_post ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state check.
?>
<form class="post-password-form hk9-form hk9-password-form" action="<?php echo esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ); ?>" method="post" aria-labelledby="<?php echo esc_attr( $hk9_field_id . '-title' ); ?>">
	<div class="hk9-password-form__icon hk9-icon-well hk9-icon-well--lg" aria-hidden="true">
		<?php echo hk9_icon( 'shield-check', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	</div>
	<h2 id="<?php echo esc_attr( $hk9_field_id . '-title' ); ?>" class="hk9-password-form__title"><?php esc_html_e( 'This content is password protected', 'heartland-k9s' ); ?></h2>
	<p class="hk9-password-form__text"><?php esc_html_e( 'To view it, please enter the password below.', 'heartland-k9s' ); ?></p>

	<?php if ( $hk9_wrong ) : ?>
		<p id="<?php echo esc_attr( $hk9_error_id ); ?>" class="hk9-form__summary hk9-password-form__error" role="alert"><?php esc_html_e( 'The password you entered is incorrect. Please try again.', 'heartland-k9s' ); ?></p>
	<?php endif; ?>

	<div class="hk9-form__field hk9-password-form__field">
		<label for="<?php echo esc_attr( $hk9_field_id ); ?>" class="hk9-form__label"><?php esc_html_e( 'Password', 'heartland-k9s' ); ?></label>
		<input name="post_password" id="<?php echo esc_attr( $hk9_field_id ); ?>" type="password" class="hk9-form__input" size="20" spellcheck="false" autocomplete="current-password" required<?php echo $hk9_wrong ? ' aria-invalid="true" aria-describedby="' . esc_attr( $hk9_error_id ) . '"' : ''; ?>>
	</div>
	<div class="hk9-form__actions">
		<button type="submit" class="hk9-btn hk9-btn--navy hk9-btn--lg"><?php esc_html_e( 'Unlock', 'heartland-k9s' ); ?></button>
	</div>
</form>
