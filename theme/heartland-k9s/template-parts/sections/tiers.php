<?php
/**
 * Section: tiers — sponsor / pricing tier grid (name, price, quantity line,
 * benefits list, highlighted tier, button).
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'tiers' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_items   = is_array( $hk9_data['items'] ?? null ) ? array_values( array_filter( $hk9_data['items'], 'is_array' ) ) : [];

$hk9_items = array_values(
	array_filter(
		$hk9_items,
		static fn( array $item ): bool => '' !== trim( (string) ( $item['name'] ?? '' ) ) || '' !== trim( (string) ( $item['price'] ?? '' ) )
	)
);

if ( empty( $hk9_items ) ) {
	return;
}

$hk9_count = count( $hk9_items );
$hk9_cols  = $hk9_count >= 4 ? 4 : max( 1, $hk9_count );

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-section--tint hk9-section--bordered' );
?>
<div class="container">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-section__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<span class="hk9-divider" aria-hidden="true"></span>
			<?php echo hk9_paragraphs( $hk9_intro, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<ul class="hk9-tiers hk9-tiers--<?php echo esc_attr( (string) $hk9_cols ); ?>">
		<?php
		foreach ( $hk9_items as $hk9_item ) :
			$hk9_name      = trim( (string) ( $hk9_item['name'] ?? '' ) );
			$hk9_price     = trim( (string) ( $hk9_item['price'] ?? '' ) );
			$hk9_qty       = trim( (string) ( $hk9_item['quantity'] ?? '' ) );
			$hk9_highlight = ! empty( $hk9_item['highlight'] );
			$hk9_button    = is_array( $hk9_item['button'] ?? null ) ? $hk9_item['button'] : [];
			$hk9_benefits  = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $hk9_item['benefits'] ?? '' ) ) ), static fn( string $line ): bool => '' !== $line ) );
			?>
			<li class="hk9-tiers__item<?php echo $hk9_highlight ? ' is-highlight' : ''; ?>">
				<?php if ( $hk9_highlight ) : ?>
					<span class="hk9-tiers__flag"><?php esc_html_e( 'Featured', 'heartland-k9s' ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $hk9_name ) : ?>
					<h3 class="hk9-tiers__name"><?php echo esc_html( $hk9_name ); ?></h3>
				<?php endif; ?>
				<?php if ( '' !== $hk9_price ) : ?>
					<p class="hk9-tiers__price"><?php echo esc_html( $hk9_price ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $hk9_qty ) : ?>
					<p class="hk9-tiers__qty"><?php echo esc_html( $hk9_qty ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $hk9_benefits ) ) : ?>
					<ul class="hk9-tiers__benefits">
						<?php foreach ( $hk9_benefits as $hk9_benefit ) : ?>
							<li><?php echo hk9_icon( 'check', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php echo esc_html( $hk9_benefit ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( hk9_link_is_set( $hk9_button ) ) : ?>
					<div class="hk9-tiers__actions">
						<?php echo hk9_button( $hk9_button, $hk9_highlight ? 'primary' : 'outline', [ 'full' => true, 'aria-label' => '' !== $hk9_name ? sprintf( /* translators: 1: button label, 2: tier name */ __( '%1$s — %2$s', 'heartland-k9s' ), hk9_link_label( $hk9_button ), $hk9_name ) : hk9_link_label( $hk9_button ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
hk9_section_close();
