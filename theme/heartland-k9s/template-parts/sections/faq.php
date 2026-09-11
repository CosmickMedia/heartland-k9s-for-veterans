<?php
/**
 * Section: faq — accessible accordion built from native <details>/<summary>.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'faq' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_items   = is_array( $hk9_data['items'] ?? null ) ? array_values( array_filter( $hk9_data['items'], 'is_array' ) ) : [];
$hk9_note    = trim( (string) ( $hk9_data['source_note'] ?? '' ) );

$hk9_items = array_values(
	array_filter(
		$hk9_items,
		static fn( $item ) => '' !== trim( (string) ( $item['question'] ?? '' ) ) && '' !== trim( (string) ( $item['answer'] ?? '' ) )
	)
);

if ( empty( $hk9_items ) ) {
	return;
}

hk9_section_open( $hk9_id, 'hk9-section--py24' );
?>
<div class="container">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-section__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_intro, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<div class="hk9-faq" data-hk9-accordion>
		<?php foreach ( $hk9_items as $hk9_index => $hk9_item ) : ?>
			<?php $hk9_item_id = 'hk9-faq-' . sanitize_html_class( $hk9_id ) . '-' . ( $hk9_index + 1 ); ?>
			<details class="hk9-faq__item" id="<?php echo esc_attr( $hk9_item_id ); ?>">
				<summary class="hk9-faq__question">
					<span><?php echo esc_html( trim( (string) $hk9_item['question'] ) ); ?></span>
					<?php echo hk9_icon( 'chevron-down', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				</summary>
				<div class="hk9-faq__answer hk9-prose">
					<?php echo wp_kses_post( wpautop( (string) $hk9_item['answer'] ) ); ?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>

	<?php if ( '' !== $hk9_note ) : ?>
		<p class="hk9-faq__note"><?php echo esc_html( $hk9_note ); ?></p>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
