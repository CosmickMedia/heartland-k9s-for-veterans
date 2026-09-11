<?php
/**
 * Section: ways (get-involved) — overlapping grid of three action cards with a
 * 64px icon well, title, text and a full-width button pinned to the bottom.
 *
 * Reference: -mt-16; container max-w-6xl grid 1 → md:3 gap-8; cards bg-card
 * rounded-2xl shadow-xl border p-8 text-center flex-col hover:-translate-y-1;
 * icon well crimson/10 (donate) or primary/10; h3 text-2xl mb-4; p flex-1
 * mb-8; button size lg (40px) w-full, primary (crimson) or outline (navy).
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'ways' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_cards   = is_array( $hk9_data['cards'] ?? null ) ? array_values( array_filter( $hk9_data['cards'], 'is_array' ) ) : [];

$hk9_cards = array_values(
	array_filter(
		$hk9_cards,
		static fn( array $card ): bool => '' !== trim( (string) ( $card['title'] ?? '' ) ) || '' !== trim( (string) ( $card['text'] ?? '' ) )
	)
);

if ( empty( $hk9_cards ) ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';
$hk9_count      = count( $hk9_cards );

hk9_section_open( $hk9_id, 'hk9-section--plain hk9-overlap hk9-ways', [ 'aria-labelledby' => $hk9_heading_id ] );
?>
<div class="hk9-ways__inner">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-section__header hk9-ways__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php else : ?>
				<h2 class="screen-reader-text" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php esc_html_e( 'Ways to get involved', 'heartland-k9s' ); ?></h2>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_intro, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php else : ?>
		<?php // The reference shows no heading here; keep the h1 → h2 → h3 outline for assistive tech. ?>
		<h2 class="screen-reader-text" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php esc_html_e( 'Ways to get involved', 'heartland-k9s' ); ?></h2>
	<?php endif; ?>

	<div class="hk9-ways__grid<?php echo $hk9_count < 3 ? ' hk9-ways__grid--' . (int) $hk9_count : ''; ?>">
		<?php
		foreach ( $hk9_cards as $hk9_card ) :
			$hk9_title = trim( (string) ( $hk9_card['title'] ?? '' ) );
			$hk9_text  = trim( (string) ( $hk9_card['text'] ?? '' ) );
			$hk9_icon  = (string) ( $hk9_card['icon'] ?? '' );
			$hk9_tone  = ( $hk9_card['tone'] ?? 'navy' ) === 'crimson' ? 'crimson' : 'navy';
			$hk9_btn   = is_array( $hk9_card['button'] ?? null ) ? $hk9_card['button'] : [];
			$hk9_style = ( $hk9_card['button_style'] ?? ( 'crimson' === $hk9_tone ? 'primary' : 'outline' ) ) === 'primary' ? 'primary' : 'outline';
			?>
			<div class="hk9-ways__card">
				<?php if ( '' !== $hk9_icon && hk9_icon_exists( $hk9_icon ) ) : ?>
					<span class="hk9-icon-well hk9-icon-well--lg<?php echo 'crimson' === $hk9_tone ? ' hk9-icon-well--crimson' : ''; ?> hk9-ways__icon" aria-hidden="true"><?php echo hk9_icon( $hk9_icon, [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
				<?php endif; ?>
				<?php if ( '' !== $hk9_title ) : ?>
					<h3 class="hk9-ways__title"><?php echo esc_html( $hk9_title ); ?></h3>
				<?php endif; ?>
				<?php echo hk9_paragraphs( $hk9_text, 'hk9-ways__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( hk9_link_is_set( $hk9_btn ) ) : ?>
					<div class="hk9-ways__actions">
						<?php echo hk9_button( $hk9_btn, $hk9_style, [ 'class' => 'hk9-btn--md' . ( 'outline' === $hk9_style ? ' hk9-btn--outline-navy' : '' ), 'full' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
<?php
hk9_section_close();
