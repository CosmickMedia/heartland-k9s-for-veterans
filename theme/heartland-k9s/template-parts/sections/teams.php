<?php
/**
 * Section: teams (stories) — "Teams in Training" cards from hk9_team records
 * (image, status badge, title, canine/handler names, summary, link).
 * Mode `auto` = published teams with status in-training (menu order);
 * `manual` = the selected teams in order. Hidden when `show` is off or no
 * team matches.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'teams' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_mode    = ( $hk9_data['mode'] ?? 'auto' ) === 'manual' ? 'manual' : 'auto';

if ( array_key_exists( 'show', $hk9_data ) && empty( $hk9_data['show'] ) ) {
	return;
}
if ( ! post_type_exists( 'hk9_team' ) ) {
	return;
}

if ( 'manual' === $hk9_mode ) {
	$hk9_teams = hk9_pages_records_by_ids( hk9_pages_ids( $hk9_data['teams'] ?? [] ), 'hk9_team' );
} else {
	$hk9_teams = get_posts(
		[
			'post_type'      => 'hk9_team',
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'no_found_rows'  => true,
			'orderby'        => [
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			],
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small curated set.
				[
					'key'   => 'hk9_status',
					'value' => 'in-training',
				],
			],
		]
	);
}

if ( empty( $hk9_teams ) ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--plain hk9-gutter hk9-teams-block', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [ 'aria-label' => __( 'Teams', 'heartland-k9s' ) ] );
?>
<div class="hk9-wide">
	<?php if ( '' !== $hk9_heading || '' !== $hk9_intro ) : ?>
		<div class="hk9-section__header hk9-teams-block__header">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-section__title" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_intro, 'hk9-section__intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<div class="hk9-team-grid<?php echo count( $hk9_teams ) < 3 ? ' hk9-team-grid--' . count( $hk9_teams ) : ''; ?>">
		<?php
		$hk9_card_sizes = count( $hk9_teams ) < 3
			? '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) calc(50vw - 48px), 496px'
			: '(max-width: 767px) calc(100vw - 32px), (max-width: 1023px) calc(50vw - 48px), 320px';
		foreach ( $hk9_teams as $hk9_team ) {
			hk9_pages_team_card( $hk9_team, [ 'sizes' => $hk9_card_sizes ] );
		}
		?>
	</div>
</div>
<?php
hk9_section_close();
