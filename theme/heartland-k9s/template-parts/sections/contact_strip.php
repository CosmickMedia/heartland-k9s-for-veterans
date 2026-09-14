<?php
/**
 * Section: contact_strip (thank-you) — "Questions? We're here to help.":
 * centred heading + intro, a muted panel with up to four rows (48px navy icon
 * well, small uppercase label, value) resolved from Settings → Contact through
 * hk9_pages_contact_row() (phone → tel:, email → mailto:, hours, address,
 * custom text), and an optional ghost link under the panel.
 *
 * Rows whose Settings value is empty are skipped; with no rows left the panel
 * is dropped and only heading, text and link print.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'help' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_text    = trim( (string) ( $hk9_data['text'] ?? '' ) );
$hk9_link    = is_array( $hk9_data['link'] ?? null ) ? $hk9_data['link'] : [];
$hk9_tone    = (string) ( $hk9_data['tone'] ?? 'muted' );
$hk9_tone    = in_array( $hk9_tone, [ 'muted', 'plain' ], true ) ? $hk9_tone : 'muted';
$hk9_rows    = is_array( $hk9_data['rows'] ?? null ) ? array_slice( array_values( array_filter( $hk9_data['rows'], 'is_array' ) ), 0, 4 ) : [];

$hk9_resolved = [];
foreach ( $hk9_rows as $hk9_row ) {
	$hk9_item = hk9_pages_contact_row( $hk9_row );
	if ( null !== $hk9_item ) {
		$hk9_item['icon'] = (string) ( $hk9_row['icon'] ?? '' );
		$hk9_resolved[]   = $hk9_item;
	}
}

if ( '' === $hk9_heading && '' === $hk9_text && empty( $hk9_resolved ) && ! hk9_link_is_set( $hk9_link ) ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--py20 hk9-gutter hk9-help-strip hk9-help-strip--' . $hk9_tone, '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-narrow">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_text ) : ?>
		<div class="hk9-section__header hk9-help-strip__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
				<span class="hk9-divider" aria-hidden="true"></span>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_text, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $hk9_resolved ) ) : ?>
		<ul class="hk9-help-strip__panel hk9-help-strip__panel--<?php echo esc_attr( (string) min( 4, count( $hk9_resolved ) ) ); ?>" role="list"<?php echo '' !== $hk9_heading ? ' aria-labelledby="' . esc_attr( $hk9_heading_id ) . '"' : ''; ?>>
			<?php foreach ( $hk9_resolved as $hk9_item ) : ?>
				<li class="hk9-help-strip__row">
					<span class="hk9-icon-well hk9-help-strip__icon" aria-hidden="true">
						<?php echo '' !== $hk9_item['icon'] && hk9_icon_exists( $hk9_item['icon'] ) ? hk9_icon( $hk9_item['icon'], [ 'size' => 20 ] ) : hk9_icon( 'info', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</span>
					<div class="hk9-help-strip__body">
						<?php if ( '' !== $hk9_item['label'] ) : ?>
							<span class="hk9-help-strip__label"><?php echo esc_html( $hk9_item['label'] ); ?></span>
						<?php endif; ?>
						<?php
						$hk9_lines_html = implode( '<br>', array_map( 'esc_html', $hk9_item['lines'] ) );
						if ( '' !== $hk9_item['href'] ) {
							$hk9_attrs = $hk9_item['external'] ? hk9_theme_link_attrs( $hk9_item['link'] ) : '';
							echo '<a class="hk9-help-strip__value" href="' . esc_url( $hk9_item['href'], [ 'http', 'https', 'mailto', 'tel' ] ) . '"' . $hk9_attrs . '>' . $hk9_lines_html . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
						} else {
							echo '<span class="hk9-help-strip__value">' . $hk9_lines_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
						}
						?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( hk9_link_is_set( $hk9_link ) ) : ?>
		<div class="hk9-help-strip__more"><?php echo hk9_button( $hk9_link, 'ghost', [ 'icon' => 'arrow-right' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
