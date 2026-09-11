<?php
/**
 * Section: barkode_feature — navy band with grid pattern, square image tile
 * (gradient + caption) and copy with badge, heading, text, outline button.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data     = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id       = (string) ( $args['id'] ?? 'barkode_feature' );
$hk9_eyebrow  = trim( (string) ( $hk9_data['eyebrow'] ?? '' ) );
$hk9_heading  = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_text     = trim( (string) ( $hk9_data['text'] ?? '' ) );
$hk9_image_id = (int) ( $hk9_data['image'] ?? 0 );
$hk9_caption  = trim( (string) ( $hk9_data['image_caption'] ?? '' ) );
$hk9_button   = is_array( $hk9_data['button'] ?? null ) ? $hk9_data['button'] : [];
$hk9_pattern  = ! empty( $hk9_data['pattern'] );

if ( '' === $hk9_heading && '' === $hk9_text ) {
	return;
}

$hk9_classes = 'hk9-section--py24 hk9-section--navy hk9-section--overflow';
if ( $hk9_pattern ) {
	$hk9_classes .= ' hk9-pattern hk9-pattern--grid';
}

hk9_section_open( $hk9_id, $hk9_classes );
?>
<div class="container hk9-feature">
	<?php if ( $hk9_image_id > 0 || '' !== $hk9_caption ) : ?>
		<div class="hk9-feature__media">
			<figure class="hk9-feature__tile">
				<?php echo hk9_image( $hk9_image_id, 'hk9-square', [ 'sizes' => '(max-width: 767px) calc(100vw - 32px), 448px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
				<?php if ( '' !== $hk9_caption ) : ?>
					<figcaption class="hk9-feature__caption"><?php echo esc_html( $hk9_caption ); ?></figcaption>
				<?php endif; ?>
			</figure>
		</div>
	<?php endif; ?>
	<div class="hk9-feature__body">
		<?php if ( '' !== $hk9_eyebrow ) : ?>
			<div><span class="hk9-badge hk9-badge--solid"><?php echo esc_html( $hk9_eyebrow ); ?></span></div>
		<?php endif; ?>
		<?php if ( '' !== $hk9_heading ) : ?>
			<h2 class="hk9-feature__heading"><?php echo esc_html( $hk9_heading ); ?></h2>
		<?php endif; ?>
		<?php echo hk9_paragraphs( $hk9_text, 'hk9-feature__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php if ( hk9_link_is_set( $hk9_button ) ) : ?>
			<div class="hk9-feature__actions"><?php echo hk9_button( $hk9_button, 'outline-light', [ 'size' => 'lg', 'text_sm' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
		<?php endif; ?>
	</div>
</div>
<?php
hk9_section_close();
