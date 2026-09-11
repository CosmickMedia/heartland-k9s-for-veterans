<?php
/**
 * Section: grid — people (hk9_person) cards, 2 or 3 columns, manual/auto order.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'grid' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_columns = 2 === (int) ( $hk9_data['columns'] ?? 3 ) ? 2 : 3;
$hk9_posts   = hk9_rec_section_records( $hk9_data, 'people', 'hk9_person' );

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-people-section', hk9_rec_section_attrs( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Meet the Team', 'heartland-k9s' ), (int) ( $args['post_id'] ?? get_the_ID() ) ) );
?>
<div class="container">
	<?php hk9_rec_section_header( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Meet the Team', 'heartland-k9s' ), $hk9_intro, (int) ( $args['post_id'] ?? get_the_ID() ) ); ?>

	<?php if ( empty( $hk9_posts ) ) : ?>
		<div class="hk9-empty">
			<span class="hk9-icon-well hk9-icon-well--lg"><?php echo hk9_icon( 'users', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<p class="hk9-empty__text"><?php esc_html_e( 'Team profiles are on the way.', 'heartland-k9s' ); ?></p>
		</div>
	<?php else : ?>
		<div class="hk9-people<?php echo 2 === $hk9_columns ? ' hk9-people--2' : ''; ?>">
			<?php foreach ( $hk9_posts as $hk9_person ) : ?>
				<?php hk9_rec_card( 'person', $hk9_person ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
