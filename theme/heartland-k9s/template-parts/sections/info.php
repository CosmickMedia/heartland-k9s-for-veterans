<?php
/**
 * Section: info (contact) — "Get in Touch" column: muted panel with rows of
 * 40px navy icon wells, bold labels and values pulled from Settings → Contact
 * (`source` phone|phone_secondary|email|hours|address) or custom text.
 *
 * Rendered inside the contact card by page-templates/contact.php (column
 * lg:w-2/5 bg-muted p-10 md:p-12 border-b lg:border-r); the part also works
 * stand-alone as a plain panel.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'info' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_rows    = is_array( $hk9_data['rows'] ?? null ) ? array_values( array_filter( $hk9_data['rows'], 'is_array' ) ) : [];

$hk9_resolved = [];
foreach ( $hk9_rows as $hk9_row ) {
	$hk9_item = hk9_pages_contact_row( $hk9_row );
	if ( null !== $hk9_item ) {
		$hk9_item['icon'] = (string) ( $hk9_row['icon'] ?? '' );
		$hk9_resolved[]   = $hk9_item;
	}
}

if ( empty( $hk9_resolved ) && '' === $hk9_heading ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';
?>
<div class="hk9-contact-card__info hk9-contact-info" id="<?php echo esc_attr( 'hk9-' . sanitize_html_class( $hk9_id ) ); ?>">
	<?php if ( '' !== $hk9_heading ) : ?>
		<h2 class="hk9-contact-info__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
	<?php endif; ?>
	<?php if ( ! empty( $hk9_resolved ) ) : ?>
		<ul class="hk9-info hk9-contact-info__list" role="list"<?php echo '' !== $hk9_heading ? ' aria-labelledby="' . esc_attr( $hk9_heading_id ) . '"' : ''; ?>>
			<?php foreach ( $hk9_resolved as $hk9_item ) : ?>
				<li class="hk9-info__row hk9-contact-info__row">
					<span class="hk9-icon-well hk9-contact-info__icon" aria-hidden="true">
						<?php echo '' !== $hk9_item['icon'] && hk9_icon_exists( $hk9_item['icon'] ) ? hk9_icon( $hk9_item['icon'], [ 'size' => 20 ] ) : hk9_icon( 'info', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</span>
					<div class="hk9-contact-info__body">
						<?php if ( '' !== $hk9_item['label'] ) : ?>
							<h3 class="hk9-info__label hk9-contact-info__label"><?php echo esc_html( $hk9_item['label'] ); ?></h3>
						<?php endif; ?>
						<?php
						$hk9_lines_html = implode( '<br>', array_map( 'esc_html', $hk9_item['lines'] ) );
						if ( '' !== $hk9_item['href'] ) {
							$hk9_attrs = $hk9_item['external'] ? hk9_theme_link_attrs( $hk9_item['link'] ) : '';
							echo '<a class="hk9-info__value hk9-contact-info__value" href="' . esc_url( $hk9_item['href'], [ 'http', 'https', 'mailto', 'tel' ] ) . '"' . $hk9_attrs . '>' . $hk9_lines_html . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
						} else {
							echo '<p class="hk9-info__value hk9-contact-info__value">' . $hk9_lines_html . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
						}
						?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
