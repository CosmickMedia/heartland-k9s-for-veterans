<?php
/**
 * Section: list (stories) — grid of story cards (image, quote/summary, name,
 * attribution, link). Mode `auto` = latest published stories, featured first;
 * `manual` = the selected stories in order. The story used by the `featured`
 * section is not repeated in auto mode. Without stories the `empty_text` is
 * shown as an honest empty state.
 *
 * Reference grid: container max-w-5xl grid 1 → md:2 gap-8; cards bg-card p-8
 * rounded-xl border shadow-sm flex-col; quote icon 32px text-secondary
 * opacity-40 mb-4; text-lg italic flex-1 mb-6; attribution pt-6 border-t.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_id       = (string) ( $args['id'] ?? 'list' );
$hk9_post_id  = (int) ( $args['post_id'] ?? get_the_ID() );
$hk9_template = (string) ( $args['template'] ?? 'stories' );
$hk9_data     = hk9_pages_section( $hk9_post_id, $hk9_template, $hk9_id );
if ( empty( $hk9_data ) && is_array( $args['data'] ?? null ) ) {
	$hk9_data = $args['data'];
}

$hk9_heading = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_mode    = ( $hk9_data['mode'] ?? 'auto' ) === 'manual' ? 'manual' : 'auto';
$hk9_count   = max( 1, min( 50, (int) ( $hk9_data['count'] ?? 6 ) ) );
$hk9_empty   = trim( (string) ( $hk9_data['empty_text'] ?? '' ) );

$hk9_exclude = [];
if ( in_array( 'featured', hk9_sections_layout( $hk9_post_id, $hk9_template ), true ) ) {
	$hk9_featured = hk9_section( $hk9_post_id, 'featured' );
	if ( ( $hk9_featured['source'] ?? 'story' ) !== 'manual' ) {
		$hk9_featured_id = (int) ( $hk9_featured['story'] ?? 0 );
		if ( ! hk9_pages_is_published( $hk9_featured_id, 'hk9_story' ) ) {
			$hk9_featured_id = hk9_pages_featured_story_id();
		}
		if ( $hk9_featured_id > 0 ) {
			$hk9_exclude[] = $hk9_featured_id;
		}
	}
}

if ( 'manual' === $hk9_mode ) {
	$hk9_stories = hk9_pages_records_by_ids( hk9_pages_ids( $hk9_data['stories'] ?? [] ), 'hk9_story' );
} else {
	$hk9_stories = post_type_exists( 'hk9_story' ) ? hk9_pages_stories_auto( $hk9_count, $hk9_exclude ) : [];
}

if ( empty( $hk9_stories ) && '' === $hk9_empty ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--plain hk9-gutter hk9-stories-list', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [ 'aria-label' => __( 'Stories', 'heartland-k9s' ) ] );
?>
<div class="hk9-wide">
	<?php if ( '' !== $hk9_heading ) : ?>
		<div class="hk9-section__header hk9-stories-list__header">
			<h2 class="hk9-section__title" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $hk9_stories ) ) : ?>
		<div class="hk9-story-grid">
			<?php
			foreach ( $hk9_stories as $hk9_story ) {
				hk9_pages_story_card( $hk9_story );
			}
			?>
		</div>
	<?php else : ?>
		<div class="hk9-panel hk9-panel--sm hk9-stories-list__empty">
			<?php echo hk9_icon( 'paw-print', [ 'class' => 'hk9-stories-list__empty-icon', 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php echo hk9_paragraphs( $hk9_empty, 'hk9-stories-list__empty-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
