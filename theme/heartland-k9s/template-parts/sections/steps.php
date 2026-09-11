<?php
/**
 * Section: steps (program) — "How It Works" staggered two-column timeline.
 *
 * Reference: py-24; centred h2 + crimson divider + intro (max-w-3xl); grid
 * 1 → md:2 gap-12 with a 1px centre spine; odd steps in the left column, even
 * steps in the right column pushed down md:mt-24; a 32px crimson number circle
 * hangs off each card's inner edge (toward the spine).
 *
 * The cards are laid out as ONE grid in DOM order (not two independent
 * columns), so: (a) an odd number of steps leaves the last card alone in the
 * left column, (b) the mobile order is 1…N (the reference's two-column DOM
 * reads 1,3,2,4 on mobile), (c) rows stay aligned when texts differ in length.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'steps' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_steps   = is_array( $hk9_data['steps'] ?? null ) ? array_values( array_filter( $hk9_data['steps'], 'is_array' ) ) : [];

$hk9_steps = array_values(
	array_filter(
		$hk9_steps,
		static fn( array $step ): bool => '' !== trim( (string) ( $step['title'] ?? '' ) ) || '' !== trim( (string) ( $step['text'] ?? '' ) )
	)
);

if ( empty( $hk9_steps ) ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-gutter', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-wide">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-section__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<span class="hk9-divider" aria-hidden="true"></span>
			<?php echo hk9_paragraphs( $hk9_intro, 'hk9-section__intro hk9-steps__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<div class="hk9-timeline hk9-timeline--zigzag">
		<ol class="hk9-timeline__grid" role="list">
		<?php foreach ( $hk9_steps as $hk9_index => $hk9_step ) : ?>
			<?php
			$hk9_number = $hk9_index + 1;
			$hk9_title  = trim( (string) ( $hk9_step['title'] ?? '' ) );
			$hk9_text   = trim( (string) ( $hk9_step['text'] ?? '' ) );
			$hk9_icon   = (string) ( $hk9_step['icon'] ?? '' );
			$hk9_side   = 0 === $hk9_index % 2 ? 'left' : 'right';
			?>
			<li class="hk9-timeline__item hk9-timeline__item--<?php echo esc_attr( $hk9_side ); ?>">
				<span class="hk9-timeline__num" aria-hidden="true"><?php echo esc_html( (string) $hk9_number ); ?></span>
				<?php if ( '' !== $hk9_icon && hk9_icon_exists( $hk9_icon ) ) : ?>
					<?php echo hk9_icon( $hk9_icon, [ 'class' => 'hk9-card__glyph hk9-card__glyph--navy', 'size' => 40 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php endif; ?>
				<?php if ( '' !== $hk9_title ) : ?>
					<h3 class="hk9-timeline__title"><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %d: step number */ __( 'Step %d: ', 'heartland-k9s' ), $hk9_number ) ); ?></span><?php echo esc_html( $hk9_title ); ?></h3>
				<?php endif; ?>
				<?php echo hk9_paragraphs( $hk9_text, 'hk9-timeline__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</li>
		<?php endforeach; ?>
		</ol>
		<span class="hk9-timeline__line" aria-hidden="true"></span>
	</div>
</div>
<?php
hk9_section_close();
