<?php
/**
 * Section: tax — tax-deductibility statement (defaults to the Settings EIN statement).
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id   = (string) ( $args['id'] ?? 'tax' );
$hk9_text = trim( (string) ( $hk9_data['text'] ?? '' ) );

if ( '' === $hk9_text ) {
	$hk9_text = trim( (string) hk9_theme_option( 'contact.tax_statement' ) );
}
if ( '' === $hk9_text ) {
	$hk9_legal = trim( (string) hk9_theme_option( 'contact.legal_name' ) );
	$hk9_ein   = trim( (string) hk9_theme_option( 'contact.ein' ) );
	if ( '' !== $hk9_legal && '' !== $hk9_ein ) {
		/* translators: 1: legal name, 2: EIN */
		$hk9_text = sprintf( __( '%1$s is an IRS-recognized 501(c)(3) nonprofit organization. EIN %2$s. Donations are tax deductible to the extent allowed by law.', 'heartland-k9s' ), $hk9_legal, $hk9_ein );
	}
}

if ( '' === $hk9_text ) {
	return;
}

hk9_section_open( $hk9_id, 'hk9-section--pb20 hk9-tax' );
?>
<div class="container hk9-narrow">
	<div class="hk9-tax__box" role="note">
		<?php echo hk9_icon( 'shield-check', [ 'class' => 'hk9-tax__icon', 'size' => 24 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<div class="hk9-tax__body">
			<?php echo hk9_paragraphs( $hk9_text, 'hk9-tax__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	</div>
</div>
<?php
hk9_section_close();
