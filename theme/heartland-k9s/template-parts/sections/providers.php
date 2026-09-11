<?php
/**
 * Section: providers (program) — muted band with a 48px crimson icon, heading,
 * lead paragraph and an outline button.
 *
 * Reference: py-20 bg-muted/50 border-y; container px-4 md:px-8 max-w-4xl
 * text-center; icon w-12 h-12 text-secondary mb-6; h2 text-3xl text-foreground
 * mb-6; p text-lg leading-relaxed mb-8; outline button (navy border/text).
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'providers' );
$hk9_icon    = (string) ( $hk9_data['icon'] ?? '' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_text    = trim( (string) ( $hk9_data['text'] ?? '' ) );
$hk9_button  = is_array( $hk9_data['button'] ?? null ) ? $hk9_data['button'] : [];

if ( '' === $hk9_heading && '' === $hk9_text ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--py20 hk9-section--tint hk9-section--bordered hk9-providers', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="container hk9-narrow hk9-providers__inner">
	<?php if ( '' !== $hk9_icon && hk9_icon_exists( $hk9_icon ) ) : ?>
		<?php echo hk9_icon( $hk9_icon, [ 'class' => 'hk9-providers__icon', 'size' => 48 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<?php endif; ?>
	<?php if ( '' !== $hk9_heading ) : ?>
		<h2 class="hk9-providers__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
	<?php endif; ?>
	<?php echo hk9_paragraphs( $hk9_text, 'hk9-providers__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<?php if ( hk9_link_is_set( $hk9_button ) ) : ?>
		<div class="hk9-providers__actions"><?php echo hk9_button( $hk9_button, 'outline', [ 'class' => 'hk9-btn--outline-navy' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
