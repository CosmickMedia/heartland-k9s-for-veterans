<?php
/**
 * Section: logos — partner logo grid filtered by hk9_partner_type term (auto) or picked manually.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'logos' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_type    = sanitize_key( (string) ( $hk9_data['type'] ?? 'back-the-pack' ) );
$hk9_columns = (int) ( $hk9_data['columns'] ?? 4 );
$hk9_columns = in_array( $hk9_columns, [ 3, 4, 5, 6 ], true ) ? $hk9_columns : 4;

$hk9_auto_args = [];
if ( '' !== $hk9_type && taxonomy_exists( 'hk9_partner_type' ) && term_exists( $hk9_type, 'hk9_partner_type' ) ) {
	$hk9_auto_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		[
			'taxonomy' => 'hk9_partner_type',
			'field'    => 'slug',
			'terms'    => $hk9_type,
		],
	];
}
$hk9_posts = hk9_rec_section_records( $hk9_data, 'partners', 'hk9_partner', $hk9_auto_args );

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-partners-section', hk9_rec_section_attrs( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Our Partners', 'heartland-k9s' ), (int) ( $args['post_id'] ?? get_the_ID() ) ) );
?>
<div class="container">
	<?php hk9_rec_section_header( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Our Partners', 'heartland-k9s' ), $hk9_intro, (int) ( $args['post_id'] ?? get_the_ID() ) ); ?>

	<?php if ( empty( $hk9_posts ) ) : ?>
		<div class="hk9-empty">
			<span class="hk9-icon-well hk9-icon-well--lg"><?php echo hk9_icon( 'building-2', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<p class="hk9-empty__text"><?php esc_html_e( 'Partner logos will appear here soon.', 'heartland-k9s' ); ?></p>
		</div>
	<?php else : ?>
		<div class="hk9-partners hk9-partners--<?php echo esc_attr( (string) $hk9_columns ); ?>">
			<?php foreach ( $hk9_posts as $hk9_partner ) : ?>
				<?php hk9_rec_card( 'partner', $hk9_partner ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
