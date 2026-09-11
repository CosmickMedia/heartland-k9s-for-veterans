<?php
/**
 * Section: partners (get-involved) — centred muted panel with a crimson heart
 * icon, heading, rich body, navy button and (optionally) the Back the Pack
 * partner logos.
 *
 * Reference: py-24; container max-w-4xl bg-muted rounded-3xl p-10 md:p-16
 * text-center border shadow-sm; icon w-12 h-12 text-secondary mb-6; h2
 * text-3xl mb-6; first paragraph text-lg mb-8, second mb-10 (max-w-2xl);
 * button size lg (40px) bg-primary.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data       = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id         = (string) ( $args['id'] ?? 'partners' );
$hk9_icon       = (string) ( $hk9_data['icon'] ?? '' );
$hk9_heading    = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_body       = hk9_pages_richtext( (string) ( $hk9_data['body'] ?? '' ) );
$hk9_button     = is_array( $hk9_data['button'] ?? null ) ? $hk9_data['button'] : [];
$hk9_show_logos = ! empty( $hk9_data['show_logos'] );

if ( '' === $hk9_heading && '' === $hk9_body ) {
	return;
}

$hk9_partners = $hk9_show_logos ? hk9_pages_partners_by_type( 'back-the-pack', 24 ) : [];
$hk9_columns  = count( $hk9_partners ) >= 6 ? 6 : ( 5 === count( $hk9_partners ) ? 5 : 4 );
$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-gutter', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-panel hk9-partners-panel">
	<?php if ( '' !== $hk9_icon && hk9_icon_exists( $hk9_icon ) ) : ?>
		<?php echo hk9_icon( $hk9_icon, [ 'class' => 'hk9-partners-panel__icon', 'size' => 48 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<?php endif; ?>
	<?php if ( '' !== $hk9_heading ) : ?>
		<h2 class="hk9-partners-panel__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
	<?php endif; ?>
	<?php if ( '' !== $hk9_body ) : ?>
		<div class="hk9-partners-panel__body"><?php echo $hk9_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in hk9_pages_richtext(). ?></div>
	<?php endif; ?>
	<?php if ( hk9_link_is_set( $hk9_button ) ) : ?>
		<div class="hk9-partners-panel__actions"><?php echo hk9_button( $hk9_button, 'navy', [ 'class' => 'hk9-btn--md' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $hk9_partners ) ) : ?>
		<div class="hk9-partners-panel__logos">
			<h3 class="hk9-partners-panel__logos-title"><?php esc_html_e( 'Back the Pack Partners', 'heartland-k9s' ); ?></h3>
			<ul class="hk9-partners hk9-partners--<?php echo (int) $hk9_columns; ?> hk9-partners-panel__grid" role="list">
				<?php
				foreach ( $hk9_partners as $hk9_partner ) {
					hk9_pages_partner_logo_item( $hk9_partner );
				}
				?>
			</ul>
		</div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
