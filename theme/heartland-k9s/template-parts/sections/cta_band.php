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
			// Reference: only the navy band's primary button (program "Review 5 Questions & Apply")
			// carries a trailing arrow, rendered at 16px because the shadcn button's [&_svg]:size-4
			// rule beats its w-5 h-5 classes; tint/plain/muted bands render plain buttons.
			$hk9_icon       = ( 'navy' === $hk9_tone && 'primary' === $hk9_style ) ? 'arrow-right' : '';
			$hk9_rendered[] = hk9_button( $hk9_link, $hk9_style, [ 'size' => 'lg', 'icon' => $hk9_icon ] );
		}
		if ( ! empty( $hk9_rendered ) ) {
			// Two buttons stretch full-width below 640px (flex-col sm:flex-row); one stays inline.
			$hk9_actions_class = 'hk9-cta-band__actions' . ( count( $hk9_rendered ) > 1 ? ' hk9-cta-band__actions--multi' : '' );
			echo '<div class="' . esc_attr( $hk9_actions_class ) . '">' . implode( '', $hk9_rendered ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}
		?>
	</div>
</div>
<?php
hk9_section_close();
