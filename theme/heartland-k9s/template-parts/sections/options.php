<?php
/**
 * Section: options — "Ways to give" grid: logo or icon, title, text, button.
 * The primary option gets the crimson button and a "Recommended" badge.
 *
 * PayPal: an item whose button points at paypal.com/cgi-bin/webscr is driven by
 * Settings → Destinations → "PayPal hosted button ID" (links.paypal_hosted_button_id):
 * the href becomes the hosted-button checkout URL for that id, and the item is
 * skipped entirely while the id is empty ("Leave empty to hide the PayPal option").
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'options' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_items   = is_array( $hk9_data['items'] ?? null ) ? array_values( array_filter( $hk9_data['items'], 'is_array' ) ) : [];

$hk9_items = array_values(
	array_filter(
		$hk9_items,
		static fn( array $item ): bool => '' !== trim( (string) ( $item['title'] ?? '' ) ) || '' !== trim( (string) ( $item['text'] ?? '' ) )
	)
);

// PayPal hosted button: hide the item without an id, otherwise rebuild the href from it.
$hk9_paypal_id = trim( (string) hk9_theme_option( 'links.paypal_hosted_button_id' ) );
$hk9_is_paypal = static function ( array $item ): bool {
	$url = is_array( $item['button'] ?? null ) ? strtolower( trim( (string) ( $item['button']['url'] ?? '' ) ) ) : '';
	return '' !== $url && (bool) preg_match( '#^https?://(www\.)?paypal\.com/cgi-bin/webscr#', $url );
};
foreach ( $hk9_items as $hk9_i => $hk9_item ) {
	if ( ! $hk9_is_paypal( $hk9_item ) ) {
		continue;
	}
	if ( '' === $hk9_paypal_id ) {
		unset( $hk9_items[ $hk9_i ] );
		continue;
	}
	$hk9_items[ $hk9_i ]['button']['url']     = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=' . rawurlencode( $hk9_paypal_id );
	$hk9_items[ $hk9_i ]['button']['post_id'] = 0;
}
$hk9_items = array_values( $hk9_items );
unset( $hk9_i, $hk9_item, $hk9_is_paypal );

if ( empty( $hk9_items ) ) {
	return;
}

$hk9_count = count( $hk9_items );
$hk9_cols  = $hk9_count >= 4 ? 4 : ( 3 === $hk9_count ? 3 : 2 );

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-donate' );
?>
<div class="container">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-section__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<span class="hk9-divider" aria-hidden="true"></span>
			<?php echo hk9_paragraphs( $hk9_intro, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<ul class="hk9-donate__grid hk9-donate__grid--<?php echo esc_attr( (string) $hk9_cols ); ?>">
		<?php
		foreach ( $hk9_items as $hk9_item ) :
			$hk9_title   = trim( (string) ( $hk9_item['title'] ?? '' ) );
			$hk9_text    = trim( (string) ( $hk9_item['text'] ?? '' ) );
			$hk9_icon    = (string) ( $hk9_item['icon'] ?? '' );
			$hk9_logo    = (int) ( $hk9_item['logo'] ?? 0 );
			$hk9_primary = ! empty( $hk9_item['primary'] );
			$hk9_button  = is_array( $hk9_item['button'] ?? null ) ? $hk9_item['button'] : [];
			$hk9_logo_html = $hk9_logo > 0 ? hk9_image( $hk9_logo, 'hk9-logo', [ 'alt' => '', 'sizes' => '160px', 'class' => 'hk9-donate__logo' ] ) : '';
			if ( '' === $hk9_icon || ! hk9_icon_exists( $hk9_icon ) ) {
				$hk9_icon = 'heart';
			}
			?>
			<li class="hk9-card hk9-card--hover hk9-donate__item<?php echo $hk9_primary ? ' is-primary' : ''; ?>">
				<?php if ( $hk9_primary ) : ?>
					<span class="hk9-badge hk9-badge--solid hk9-donate__flag"><?php esc_html_e( 'Recommended', 'heartland-k9s' ); ?></span>
				<?php endif; ?>
				<div class="hk9-donate__media">
					<?php if ( '' !== $hk9_logo_html ) : ?>
						<?php echo $hk9_logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
					<?php else : ?>
						<span class="hk9-card__icon hk9-card__icon--<?php echo $hk9_primary ? 'crimson' : 'navy'; ?>"><?php echo hk9_icon( $hk9_icon, [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $hk9_title ) : ?>
					<h3 class="hk9-card__title"><?php echo esc_html( $hk9_title ); ?></h3>
				<?php endif; ?>
				<?php echo hk9_paragraphs( $hk9_text, 'hk9-card__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( hk9_link_is_set( $hk9_button ) ) : ?>
					<div class="hk9-card__actions">
						<?php echo hk9_button( $hk9_button, $hk9_primary ? 'primary' : 'outline', [ 'full' => true, 'icon' => '_blank' === ( $hk9_button['target'] ?? '' ) ? 'external-link' : '' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
hk9_section_close();
