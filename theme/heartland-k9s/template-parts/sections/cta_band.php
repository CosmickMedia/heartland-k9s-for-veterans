<?php
/**
 * Section: cta_band — heading, text, up to two buttons; tones navy / tint / plain / muted.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'cta' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_text    = trim( (string) ( $hk9_data['text'] ?? '' ) );
$hk9_tone    = (string) ( $hk9_data['tone'] ?? 'navy' );
$hk9_tone    = in_array( $hk9_tone, [ 'navy', 'tint', 'plain', 'muted' ], true ) ? $hk9_tone : 'navy';
$hk9_buttons = is_array( $hk9_data['buttons'] ?? null ) ? array_slice( array_values( array_filter( $hk9_data['buttons'], 'is_array' ) ), 0, 2 ) : [];

if ( '' === $hk9_heading && '' === $hk9_text ) {
	return;
}

$hk9_section_classes = [ 'hk9-section--py24', 'hk9-cta-band', 'hk9-cta-band--' . $hk9_tone ];
if ( 'navy' === $hk9_tone ) {
	$hk9_section_classes[] = 'hk9-section--navy';
} elseif ( 'muted' === $hk9_tone ) {
	$hk9_section_classes[] = 'hk9-section--muted';
}

hk9_section_open( $hk9_id, $hk9_section_classes );
?>
<div class="container">
	<div class="hk9-cta-band__inner">
		<?php if ( '' !== $hk9_heading ) : ?>
			<h2 class="hk9-cta-band__heading"><?php echo esc_html( $hk9_heading ); ?></h2>
		<?php endif; ?>
		<?php echo hk9_paragraphs( $hk9_text, 'hk9-cta-band__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php
		$hk9_rendered = [];
		foreach ( $hk9_buttons as $hk9_button ) {
			$hk9_link = is_array( $hk9_button['link'] ?? null ) ? $hk9_button['link'] : [];
			if ( ! hk9_link_is_set( $hk9_link ) ) {
				continue;
			}
			$hk9_style = (string) ( $hk9_button['style'] ?? 'primary' );
			$hk9_style = in_array( $hk9_style, [ 'primary', 'outline', 'outline-light', 'ghost' ], true ) ? $hk9_style : 'primary';
			if ( 'navy' !== $hk9_tone && 'outline-light' === $hk9_style ) {
				$hk9_style = 'outline';
			}
			$hk9_rendered[] = hk9_button( $hk9_link, $hk9_style, [ 'size' => 'lg', 'icon' => 'primary' === $hk9_style ? 'arrow-right' : '', 'icon_size' => 20 ] );
		}
		if ( ! empty( $hk9_rendered ) ) {
			echo '<div class="hk9-cta-band__actions">' . implode( '', $hk9_rendered ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}
		?>
	</div>
</div>
<?php
hk9_section_close();
