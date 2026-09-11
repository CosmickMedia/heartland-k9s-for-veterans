<?php
/**
 * Section: expect (veterans) — "What to Expect" numbered steps on the left and
 * a muted rounded feature card (icon, title, text, outline button) on the right.
 *
 * Reference: py-24; container max-w-5xl grid 1 → md:2 gap-16; heading text-2xl
 * text-primary mb-6; steps space-y-8 with 40px crimson number circles, h3
 * text-lg mb-1, muted text; card bg-muted p-8 rounded-2xl border flex-col
 * justify-center with a 48px navy icon, h3 text-2xl mb-4, text mb-6, button.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data        = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id          = (string) ( $args['id'] ?? 'expect' );
$hk9_heading     = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_card_icon   = (string) ( $hk9_data['card_icon'] ?? '' );
$hk9_card_title  = trim( (string) ( $hk9_data['card_title'] ?? '' ) );
$hk9_card_text   = trim( (string) ( $hk9_data['card_text'] ?? '' ) );
$hk9_card_button = is_array( $hk9_data['card_button'] ?? null ) ? $hk9_data['card_button'] : [];
$hk9_steps       = is_array( $hk9_data['steps'] ?? null ) ? array_values( array_filter( $hk9_data['steps'], 'is_array' ) ) : [];

$hk9_steps = array_values(
	array_filter(
		$hk9_steps,
		static fn( array $step ): bool => '' !== trim( (string) ( $step['title'] ?? '' ) ) || '' !== trim( (string) ( $step['text'] ?? '' ) )
	)
);

$hk9_has_steps = ! empty( $hk9_steps );
$hk9_has_card  = '' !== $hk9_card_title || '' !== $hk9_card_text;

if ( ! $hk9_has_steps && ! $hk9_has_card ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-gutter', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-wide hk9-expect<?php echo ( $hk9_has_steps && $hk9_has_card ) ? '' : ' hk9-expect--single'; ?>">
	<?php if ( $hk9_has_steps ) : ?>
		<div class="hk9-expect__steps">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-expect__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<ol class="hk9-expect__list" role="list">
				<?php foreach ( $hk9_steps as $hk9_index => $hk9_step ) : ?>
					<li class="hk9-expect__step">
						<span class="hk9-step-num" aria-hidden="true"><?php echo esc_html( (string) ( $hk9_index + 1 ) ); ?></span>
						<div class="hk9-expect__step-body">
							<?php if ( '' !== trim( (string) ( $hk9_step['title'] ?? '' ) ) ) : ?>
								<h3 class="hk9-expect__step-title"><?php echo esc_html( trim( (string) $hk9_step['title'] ) ); ?></h3>
							<?php endif; ?>
							<?php echo hk9_paragraphs( trim( (string) ( $hk9_step['text'] ?? '' ) ), 'hk9-expect__step-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endif; ?>

	<?php if ( $hk9_has_card ) : ?>
		<?php if ( ! $hk9_has_steps && '' !== $hk9_heading ) : ?>
			<h2 class="hk9-expect__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
		<?php endif; ?>
		<div class="hk9-expect__card">
			<?php if ( '' !== $hk9_card_icon && hk9_icon_exists( $hk9_card_icon ) ) : ?>
				<?php echo hk9_icon( $hk9_card_icon, [ 'class' => 'hk9-expect__card-icon', 'size' => 48 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php endif; ?>
			<?php if ( '' !== $hk9_card_title ) : ?>
				<h3 class="hk9-expect__card-title"><?php echo esc_html( $hk9_card_title ); ?></h3>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_card_text, 'hk9-expect__card-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( hk9_link_is_set( $hk9_card_button ) ) : ?>
				<div class="hk9-expect__card-actions"><?php echo hk9_button( $hk9_card_button, 'outline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
