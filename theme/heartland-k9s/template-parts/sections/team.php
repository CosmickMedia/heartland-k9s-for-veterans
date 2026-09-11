<?php
/**
 * Section: team — highlighted team feature panel: image left; name, canine, status,
 * summary, story content, donate button and a link to the team record on the right.
 * `team` = 0 falls back to the featured team (then the most recent one).
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'team' );
$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_intro   = trim( (string) ( $hk9_data['intro'] ?? '' ) );
$hk9_team_id = (int) ( $hk9_data['team'] ?? 0 );

$hk9_team = $hk9_team_id > 0 ? get_post( $hk9_team_id ) : null;
if ( ! $hk9_team instanceof WP_Post || 'hk9_team' !== $hk9_team->post_type || 'publish' !== $hk9_team->post_status ) {
	$hk9_team = null;
	$hk9_pool = hk9_rec_query(
		'hk9_team',
		[
			'posts_per_page' => 1,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => 'hk9_featured',
					'value' => '1',
				],
			],
		]
	);
	if ( empty( $hk9_pool ) ) {
		$hk9_pool = hk9_rec_query( 'hk9_team', [ 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ] );
	}
	$hk9_team = $hk9_pool[0] ?? null;
}

hk9_section_open( $hk9_id, 'hk9-section--py24 hk9-highlight', hk9_rec_section_attrs( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Our Highlighted Team', 'heartland-k9s' ), (int) ( $args['post_id'] ?? get_the_ID() ) ) );
?>
<div class="container">
	<?php hk9_rec_section_header( $hk9_id, '' !== $hk9_heading ? $hk9_heading : __( 'Our Highlighted Team', 'heartland-k9s' ), $hk9_intro, (int) ( $args['post_id'] ?? get_the_ID() ) ); ?>

	<?php if ( ! $hk9_team instanceof WP_Post ) : ?>
		<div class="hk9-empty">
			<span class="hk9-icon-well hk9-icon-well--lg"><?php echo hk9_icon( 'dog', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<p class="hk9-empty__text"><?php esc_html_e( 'Our next highlighted team will be announced soon.', 'heartland-k9s' ); ?></p>
		</div>
	<?php else : ?>
		<?php
		$hk9_tid     = (int) $hk9_team->ID;
		$hk9_url     = get_permalink( $hk9_team );
		$hk9_title   = get_the_title( $hk9_team );
		$hk9_status  = (string) hk9_rec_meta( $hk9_tid, 'status', 'in-training' );
		$hk9_canine  = trim( (string) hk9_rec_meta( $hk9_tid, 'canine_name', '' ) );
		$hk9_handler = trim( (string) hk9_rec_meta( $hk9_tid, 'handler_name', '' ) );
		$hk9_year    = trim( (string) hk9_rec_meta( $hk9_tid, 'year', '' ) );
		$hk9_summary = trim( (string) hk9_rec_meta( $hk9_tid, 'summary', '' ) );
		$hk9_donate  = hk9_rec_meta( $hk9_tid, 'donate_link', [] );
		$hk9_thumb   = (int) get_post_thumbnail_id( $hk9_team );
		$hk9_content = (string) $hk9_team->post_content;
		$hk9_story   = '';
		if ( '' !== trim( $hk9_content ) ) {
			$hk9_story = wp_kses_post( has_blocks( $hk9_content ) ? do_blocks( $hk9_content ) : wpautop( $hk9_content ) );
		}
		$hk9_facts = [];
		if ( '' !== $hk9_canine ) {
			/* translators: %s: dog name */
			$hk9_facts[] = hk9_icon( 'paw-print', [ 'size' => 16 ] ) . '<span>' . esc_html( sprintf( __( 'K9: %s', 'heartland-k9s' ), $hk9_canine ) ) . '</span>';
		}
		if ( '' !== $hk9_handler ) {
			/* translators: %s: handler name */
			$hk9_facts[] = hk9_icon( 'star', [ 'size' => 16 ] ) . '<span>' . esc_html( sprintf( __( 'Handler: %s', 'heartland-k9s' ), $hk9_handler ) ) . '</span>';
		}
		if ( '' !== $hk9_year ) {
			$hk9_facts[] = hk9_icon( 'calendar', [ 'size' => 16 ] ) . '<span>' . esc_html( $hk9_year ) . '</span>';
		}
		?>
		<article class="hk9-highlight__panel hk9-card hk9-card--panel hk9-card--overflow">
			<div class="hk9-highlight__media">
				<?php if ( $hk9_thumb > 0 ) : ?>
					<?php echo hk9_image( $hk9_thumb, 'hk9-portrait', [ 'alt' => $hk9_title, 'sizes' => '(max-width: 1023px) calc(100vw - 32px), 440px' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
				<?php else : ?>
					<span class="hk9-highlight__placeholder" aria-hidden="true"><?php echo hk9_icon( 'dog', [ 'size' => 40 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
				<?php endif; ?>
			</div>
			<div class="hk9-highlight__body">
				<div class="hk9-card__meta hk9-card__meta--row">
					<?php echo hk9_rec_status_badge( $hk9_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				</div>
				<h3 class="hk9-highlight__title"><?php echo esc_html( $hk9_title ); ?></h3>
				<?php if ( ! empty( $hk9_facts ) ) : ?>
					<ul class="hk9-card__facts">
						<?php foreach ( $hk9_facts as $hk9_fact ) : ?>
							<li><?php echo $hk9_fact; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php echo hk9_paragraphs( $hk9_summary, 'hk9-highlight__summary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( '' !== $hk9_story ) : ?>
					<div class="hk9-highlight__story hk9-prose"><?php echo $hk9_story; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() above. ?></div>
				<?php endif; ?>
				<div class="hk9-highlight__actions">
					<?php if ( is_array( $hk9_donate ) && hk9_link_is_set( $hk9_donate ) ) : ?>
						<?php echo hk9_button( $hk9_donate, 'primary', [ 'size' => 'lg', 'icon' => 'heart', 'icon_size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<?php endif; ?>
					<a class="hk9-link" href="<?php echo esc_url( $hk9_url ); ?>"><?php esc_html_e( 'Read their full story', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
				</div>
			</div>
		</article>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
