<?php
/**
 * Section: questions (veterans) — "The 5 Questions" overlapping checklist card.
 *
 * Reference: -mt-16 card max-w-4xl p-8 md:p-12; header row (48px crimson/10
 * circle with circle-alert 24px, h2 text-2xl, muted intro) mb-8; ul space-y-6
 * with p-4 bg-muted/50 rounded rows + navy check icon; footer callout mt-10
 * p-6 bg-primary/5 rounded-xl centred with muted text, a navy button (w-full
 * on mobile) and an optional secondary text link.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data      = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id        = (string) ( $args['id'] ?? 'questions' );
$hk9_heading   = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro     = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_footer    = trim( (string) ( $hk9_data['footer_text'] ?? '' ) );
$hk9_button    = is_array( $hk9_data['button'] ?? null ) ? $hk9_data['button'] : [];
$hk9_secondary = is_array( $hk9_data['secondary_link'] ?? null ) ? $hk9_data['secondary_link'] : [];
$hk9_items     = is_array( $hk9_data['items'] ?? null ) ? array_values( array_filter( $hk9_data['items'], 'is_array' ) ) : [];

$hk9_questions = [];
foreach ( $hk9_items as $hk9_item ) {
	$hk9_question = trim( (string) ( $hk9_item['question'] ?? '' ) );
	if ( '' !== $hk9_question ) {
		$hk9_questions[] = $hk9_question;
	}
}

if ( empty( $hk9_questions ) && '' === $hk9_heading ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--plain hk9-overlap', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-overlap__card hk9-overlap__card--narrow hk9-questions">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-questions__header">
			<span class="hk9-icon-well hk9-icon-well--crimson hk9-questions__icon" aria-hidden="true"><?php echo hk9_icon( 'circle-alert', [ 'size' => 24 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<div>
				<?php if ( '' !== $hk9_heading ) : ?>
					<h2 class="hk9-questions__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $hk9_intro ) : ?>
					<p class="hk9-questions__intro"><?php echo esc_html( $hk9_intro ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $hk9_questions ) ) : ?>
		<ul class="hk9-row-list hk9-questions__list" role="list">
			<?php foreach ( $hk9_questions as $hk9_question ) : ?>
				<li class="hk9-row-list__item hk9-questions__item">
					<?php echo hk9_icon( 'check', [ 'class' => 'hk9-questions__check', 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<span class="hk9-questions__question"><?php echo esc_html( $hk9_question ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( '' !== $hk9_footer || hk9_link_is_set( $hk9_button ) || hk9_link_is_set( $hk9_secondary ) ) : ?>
		<div class="hk9-callout hk9-questions__footer">
			<?php echo hk9_paragraphs( $hk9_footer, 'hk9-questions__footer-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( hk9_link_is_set( $hk9_button ) || hk9_link_is_set( $hk9_secondary ) ) : ?>
				<div class="hk9-questions__actions">
					<?php echo hk9_button( $hk9_button, 'navy', [ 'class' => 'hk9-btn--md hk9-questions__button', 'icon' => 'file-text' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<?php if ( hk9_link_is_set( $hk9_secondary ) ) : ?>
						<a class="hk9-link hk9-questions__secondary" href="<?php echo esc_url( hk9_theme_link_url( $hk9_secondary ) ); ?>"<?php echo hk9_theme_link_attrs( $hk9_secondary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>><?php echo esc_html( hk9_link_label( $hk9_secondary, __( 'Read the full 5 Questions', 'heartland-k9s' ) ) ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
