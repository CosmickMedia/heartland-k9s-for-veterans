<?php
/**
 * Section: teams_list (teams template, id `list`) — hk9_team cards filtered by status.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'list' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_status  = sanitize_key( (string) ( $hk9_data['status'] ?? 'in-training' ) );
$hk9_status  = in_array( $hk9_status, [ 'all', 'in-training', 'graduated', 'therapy' ], true ) ? $hk9_status : 'all';

$hk9_auto_args = [];
if ( 'all' !== $hk9_status ) {
	$hk9_auto_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'relation' => 'OR',
		[
			'key'   => 'hk9_status',
			'value' => $hk9_status,
		],
	];
	if ( 'in-training' === $hk9_status ) {
		// Records saved without a status carry the field default (in-training).
		$hk9_auto_args['meta_query'][] = [
			'key'     => 'hk9_status',
			'compare' => 'NOT EXISTS',
		];
	}
}
$hk9_posts = hk9_rec_section_records( $hk9_data, 'teams', 'hk9_team', $hk9_auto_args );

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-teams-section', hk9_rec_section_attrs( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Our Teams', 'heartland-k9s' ), (int) ( $args['post_id'] ?? get_the_ID() ) ) );
?>
<div class="container">
	<?php hk9_rec_section_header( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Our Teams', 'heartland-k9s' ), $hk9_intro, (int) ( $args['post_id'] ?? get_the_ID() ) ); ?>

	<?php if ( empty( $hk9_posts ) ) : ?>
		<div class="hk9-empty">
			<span class="hk9-icon-well hk9-icon-well--lg"><?php echo hk9_icon( 'dog', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<p class="hk9-empty__text"><?php esc_html_e( 'No teams to show right now. Check back soon to meet our next veteran and K9 pairing.', 'heartland-k9s' ); ?></p>
		</div>
	<?php else : ?>
		<ul class="hk9-teams hk9-cards--gap8">
			<?php foreach ( $hk9_posts as $hk9_team ) : ?>
				<li><?php hk9_rec_card( 'team', $hk9_team ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
