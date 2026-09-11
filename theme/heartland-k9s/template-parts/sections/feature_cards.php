<?php
/**
 * Section: feature_cards — tinted band with 2/3 icon cards.
 * Variants by section id: `values` (about) renders muted cards with a bare 40px icon;
 * `ways` (get-involved) renders a button instead of the text link.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'features' );
$hk9_cards   = is_array( $hk9_data['cards'] ?? null ) ? array_values( array_filter( $hk9_data['cards'], 'is_array' ) ) : [];
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_align   = ( $hk9_data['align'] ?? 'center' ) === 'left' ? 'left' : 'center';
$hk9_columns = (int) ( $hk9_data['columns'] ?? 3 ) === 2 ? 2 : 3;
$hk9_variant = in_array( $hk9_id, [ 'values' ], true ) ? 'muted' : 'default';

if ( empty( $hk9_cards ) ) {
	return;
}

$hk9_section_classes = 'muted' === $hk9_variant
	? 'hk9-section--py24'
	: 'hk9-section--py20 hk9-section--tint hk9-section--bordered';

hk9_section_open( $hk9_id, $hk9_section_classes );
?>
<div class="container">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-section__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<?php if ( ! empty( $hk9_data['divider'] ) ) : ?>
				<span class="hk9-divider" aria-hidden="true"></span>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_intro, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<div class="hk9-cards<?php echo 2 === $hk9_columns ? ' hk9-cards--2' : ''; ?><?php echo 'muted' === $hk9_variant ? ' hk9-cards--gap8' : ''; ?>">
		<?php
		foreach ( $hk9_cards as $hk9_card ) :
			$hk9_title = trim( (string) ( $hk9_card['title'] ?? '' ) );
			$hk9_text  = trim( (string) ( $hk9_card['text'] ?? '' ) );
			$hk9_icon  = (string) ( $hk9_card['icon'] ?? '' );
			$hk9_tone  = ( $hk9_card['tone'] ?? 'navy' ) === 'crimson' ? 'crimson' : 'navy';
			$hk9_link  = is_array( $hk9_card['link'] ?? null ) ? $hk9_card['link'] : [];
			$hk9_btn   = is_array( $hk9_card['button'] ?? null ) ? $hk9_card['button'] : [];

			if ( '' === $hk9_title && '' === $hk9_text ) {
				continue;
			}

			$hk9_classes = [ 'hk9-card' ];
			if ( 'muted' === $hk9_variant ) {
				$hk9_classes[] = 'hk9-card--muted';
				$hk9_tone      = 'crimson';
			} else {
				$hk9_classes[] = 'hk9-card--hover';
			}
			if ( 'center' === $hk9_align ) {
				$hk9_classes[] = 'hk9-card--center';
			}
			if ( ! empty( $hk9_card['decorate'] ) ) {
				$hk9_classes[] = 'hk9-card--blob';
			}
			?>
			<div class="<?php echo esc_attr( implode( ' ', $hk9_classes ) ); ?>">
				<?php if ( ! empty( $hk9_card['decorate'] ) ) : ?>
					<span class="hk9-card__blob" aria-hidden="true"></span>
				<?php endif; ?>
				<?php if ( '' !== $hk9_icon && hk9_icon_exists( $hk9_icon ) ) : ?>
					<?php if ( 'muted' === $hk9_variant ) : ?>
						<?php echo hk9_icon( $hk9_icon, [ 'class' => 'hk9-card__glyph', 'size' => 40 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<?php else : ?>
						<span class="hk9-card__icon hk9-card__icon--<?php echo esc_attr( $hk9_tone ); ?>"><?php echo hk9_icon( $hk9_icon, [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( '' !== $hk9_title ) : ?>
					<h3 class="hk9-card__title"><?php echo esc_html( $hk9_title ); ?></h3>
				<?php endif; ?>
				<?php echo hk9_paragraphs( $hk9_text, 'hk9-card__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( hk9_link_is_set( $hk9_btn ) ) : ?>
					<div class="hk9-card__actions"><?php echo hk9_button( $hk9_btn, 'crimson' === $hk9_tone ? 'primary' : 'navy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
				<?php elseif ( hk9_link_is_set( $hk9_link ) ) : ?>
					<a class="hk9-card__link" href="<?php echo esc_url( hk9_theme_link_url( $hk9_link ) ); ?>"<?php echo hk9_theme_link_attrs( $hk9_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>><?php echo esc_html( hk9_link_label( $hk9_link, __( 'Learn more', 'heartland-k9s' ) ) ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
<?php
hk9_section_close();
