<?php
/**
 * Section: story (barkode) — dedication panel: navy-tint rounded box with a
 * crimson heart-pulse icon, centred heading and left-aligned lead paragraphs.
 *
 * Reference: py-20 px-4 md:px-8; container max-w-4xl; panel bg-primary/5
 * rounded-2xl p-8 md:p-12 border border-primary/10 text-center; icon w-12
 * h-12 text-secondary mb-6; h2 text-3xl text-primary mb-6; p text-lg
 * leading-relaxed text-left mb-6 (last mb-0).
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'story' );
$hk9_icon    = (string) ( $hk9_data['icon'] ?? '' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_body    = hk9_pages_richtext( (string) ( $hk9_data['body'] ?? '' ) );

if ( '' === $hk9_heading && '' === $hk9_body ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--py20 hk9-gutter', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-narrow">
	<div class="hk9-callout hk9-callout--lg hk9-story-panel">
		<?php if ( '' !== $hk9_icon && hk9_icon_exists( $hk9_icon ) ) : ?>
			<?php echo hk9_icon( $hk9_icon, [ 'class' => 'hk9-story-panel__icon', 'size' => 48 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php endif; ?>
		<?php if ( '' !== $hk9_heading ) : ?>
			<h2 class="hk9-story-panel__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $hk9_body ) : ?>
			<div class="hk9-story-panel__body"><?php echo $hk9_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in hk9_pages_richtext(). ?></div>
		<?php endif; ?>
	</div>
</div>
<?php
hk9_section_close();
