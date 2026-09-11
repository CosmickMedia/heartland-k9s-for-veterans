<?php
/**
 * Section: campaigns_list (campaigns template, id `list`) — hk9_campaign cards in manual/auto order.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data     = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id       = (string) ( $args['id'] ?? 'list' );
$hk9_heading  = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro    = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_sponsors = ! isset( $hk9_data['show_sponsors'] ) || ! empty( $hk9_data['show_sponsors'] );
$hk9_posts    = hk9_rec_section_records( $hk9_data, 'campaigns', 'hk9_campaign' );

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-campaigns-section', hk9_rec_section_attrs( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Campaigns', 'heartland-k9s' ), (int) ( $args['post_id'] ?? get_the_ID() ) ) );
?>
<div class="container">
	<?php hk9_rec_section_header( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Campaigns', 'heartland-k9s' ), $hk9_intro, (int) ( $args['post_id'] ?? get_the_ID() ) ); ?>

	<?php if ( empty( $hk9_posts ) ) : ?>
		<div class="hk9-empty">
			<span class="hk9-icon-well hk9-icon-well--lg"><?php echo hk9_icon( 'heart-handshake', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<p class="hk9-empty__text"><?php esc_html_e( 'No campaigns are running right now. Check back soon or see other ways to get involved.', 'heartland-k9s' ); ?></p>
		</div>
	<?php else : ?>
		<ul class="hk9-campaigns hk9-cards--gap8">
			<?php foreach ( $hk9_posts as $hk9_campaign ) : ?>
				<li><?php hk9_rec_card( 'campaign', $hk9_campaign, [ 'show_sponsors' => $hk9_sponsors ] ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
