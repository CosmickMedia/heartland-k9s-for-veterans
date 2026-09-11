<?php
/**
 * Section: mail_in — "Donate by mail" panel with the mailing address from Settings.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'mail_in' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_text    = trim( (string) ( $hk9_data['text'] ?? '' ) );
$hk9_use     = ! isset( $hk9_data['use_settings_address'] ) || ! empty( $hk9_data['use_settings_address'] );

$hk9_lines = [];
if ( $hk9_use ) {
	$hk9_legal = trim( (string) hk9_theme_option( 'contact.legal_name' ) );
	$hk9_line1 = trim( (string) hk9_theme_option( 'contact.address_line1' ) );
	$hk9_line2 = trim( (string) hk9_theme_option( 'contact.address_line2' ) );
	$hk9_city  = trim( trim( (string) hk9_theme_option( 'contact.city' ) ) . ', ' . trim( (string) hk9_theme_option( 'contact.state' ) ) . ' ' . trim( (string) hk9_theme_option( 'contact.zip' ) ), ', ' );
	$hk9_lines = array_values( array_filter( [ $hk9_legal, $hk9_line1, $hk9_line2, ',' === $hk9_city ? '' : $hk9_city ], static fn( string $line ): bool => '' !== $line ) );
}

if ( '' === $hk9_heading && '' === $hk9_text && empty( $hk9_lines ) ) {
	return;
}

hk9_section_open( $hk9_id, 'hk9-section--py16 hk9-mail-in' );
?>
<div class="container hk9-narrow">
	<div class="hk9-callout hk9-callout--lg hk9-mail-in__panel">
		<span class="hk9-icon-well hk9-icon-well--crimson hk9-icon-well--lg hk9-mail-in__icon"><?php echo hk9_icon( 'mail', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
		<?php if ( '' !== $hk9_heading ) : ?>
			<h2 class="hk9-mail-in__heading"><?php echo esc_html( $hk9_heading ); ?></h2>
		<?php endif; ?>
		<?php if ( ! empty( $hk9_lines ) ) : ?>
			<address class="hk9-mail-in__address">
				<?php foreach ( $hk9_lines as $hk9_index => $hk9_line ) : ?>
					<span class="hk9-mail-in__line<?php echo 0 === $hk9_index ? ' hk9-mail-in__line--name' : ''; ?>"><?php echo esc_html( $hk9_line ); ?></span>
				<?php endforeach; ?>
			</address>
		<?php endif; ?>
		<?php echo hk9_paragraphs( $hk9_text, 'hk9-mail-in__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	</div>
</div>
<?php
hk9_section_close();
