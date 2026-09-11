<?php
/**
 * Section: mission — centred statement, crimson divider, lead paragraph.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'mission' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_text    = trim( (string) ( $hk9_data['text'] ?? '' ) );
$hk9_divider = ! empty( $hk9_data['divider'] );

if ( '' === $hk9_heading && '' === $hk9_text ) {
	return;
}

hk9_section_open( $hk9_id, 'hk9-section--py24' );
?>
<div class="container hk9-narrow hk9-mission">
	<?php if ( '' !== $hk9_heading ) : ?>
		<h2 class="hk9-mission__heading"><?php echo esc_html( $hk9_heading ); ?></h2>
	<?php endif; ?>
	<?php if ( $hk9_divider ) : ?>
		<span class="hk9-divider" aria-hidden="true"></span>
	<?php endif; ?>
	<?php echo hk9_paragraphs( $hk9_text, 'hk9-mission__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
</div>
<?php
hk9_section_close();
