<?php
/**
 * Section: legacy (about) — overlapping split card: image on one side, eyebrow
 * badge + heading + rich body on the other.
 *
 * Reference: -mt-16 card max-w-5xl rounded-2xl shadow-xl md:flex-row; image
 * md:w-1/2 h-80 md:h-auto; text md:w-1/2 p-10 md:p-12 space-y-6.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data     = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id       = (string) ( $args['id'] ?? 'legacy' );
$hk9_eyebrow  = trim( (string) ( $hk9_data['eyebrow'] ?? '' ) );
$hk9_heading  = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_body     = hk9_pages_richtext( (string) ( $hk9_data['body'] ?? '' ) );
$hk9_image_id = (int) ( $hk9_data['image'] ?? 0 );
$hk9_side     = ( $hk9_data['image_side'] ?? 'left' ) === 'right' ? 'right' : 'left';

if ( '' === $hk9_heading && '' === $hk9_body ) {
	return;
}

$hk9_has_image = $hk9_image_id > 0 && 'attachment' === get_post_type( $hk9_image_id );
$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--plain hk9-overlap', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-overlap__card hk9-overlap__card--split hk9-legacy<?php echo 'right' === $hk9_side ? ' hk9-legacy--image-right' : ''; ?><?php echo $hk9_has_image ? '' : ' hk9-legacy--no-image'; ?>">
	<?php if ( $hk9_has_image ) : ?>
		<div class="hk9-overlap__media hk9-legacy__media">
			<?php // The overlap card starts inside the first viewport: load eagerly (no priority hint). ?>
			<?php echo hk9_image( $hk9_image_id, 'large', [ 'sizes' => hk9_legacy_image_sizes() ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		</div>
	<?php endif; ?>
	<div class="hk9-overlap__body hk9-legacy__body">
		<?php if ( '' !== $hk9_eyebrow ) : ?>
			<div class="hk9-legacy__eyebrow"><span class="hk9-badge hk9-badge--tint"><?php echo esc_html( $hk9_eyebrow ); ?></span></div>
		<?php endif; ?>
		<?php if ( '' !== $hk9_heading ) : ?>
			<h2 class="hk9-legacy__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $hk9_body ) : ?>
			<div class="hk9-legacy__text"><?php echo $hk9_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in hk9_pages_richtext(). ?></div>
		<?php endif; ?>
	</div>
</div>
<?php
hk9_section_close();
