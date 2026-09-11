<?php
/**
 * Section: featured (stories) — overlapping split card: story image on the
 * left, quote on the right (muted/30) with a crimson quote glyph.
 *
 * Source `story` reads the hk9_story record (hk9_quote / hk9_veteran_name /
 * hk9_branch / hk9_canine_name / featured image); non-empty manual overrides
 * from the section win. With no story selected, the latest featured story is
 * used. Nothing renders without a quote.
 *
 * Reference: -mt-16 mb-24 card max-w-5xl rounded-2xl shadow-xl md:flex-row;
 * image md:w-1/2 h-80 md:h-auto; text md:w-1/2 p-10 md:p-12 bg-muted/30;
 * quote icon 48px text-secondary opacity-40 mb-6; blockquote text-2xl serif
 * leading-snug mb-8; name font-bold text-primary text-lg; meta text-sm muted.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data     = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id       = (string) ( $args['id'] ?? 'featured' );
$hk9_source   = ( $hk9_data['source'] ?? 'story' ) === 'manual' ? 'manual' : 'story';
$hk9_quote    = trim( (string) ( $hk9_data['quote'] ?? '' ) );
$hk9_name     = trim( (string) ( $hk9_data['name'] ?? '' ) );
$hk9_meta     = trim( (string) ( $hk9_data['meta'] ?? '' ) );
$hk9_image_id = (int) ( $hk9_data['image'] ?? 0 );
$hk9_story_id = (int) ( $hk9_data['story'] ?? 0 );
$hk9_link     = '';

if ( 'story' === $hk9_source ) {
	if ( ! hk9_pages_is_published( $hk9_story_id, 'hk9_story' ) ) {
		$hk9_story_id = hk9_pages_featured_story_id();
	}
	if ( $hk9_story_id > 0 ) {
		if ( '' === $hk9_quote ) {
			$hk9_quote = hk9_pages_text( $hk9_story_id, 'quote' );
		}
		if ( '' === $hk9_name ) {
			$hk9_name = hk9_pages_text( $hk9_story_id, 'veteran_name' );
			if ( '' === $hk9_name ) {
				$hk9_name = get_the_title( $hk9_story_id );
			}
		}
		if ( '' === $hk9_meta ) {
			$hk9_meta = hk9_pages_story_attribution( $hk9_story_id );
		}
		if ( ( $hk9_image_id <= 0 || 'attachment' !== get_post_type( $hk9_image_id ) ) && has_post_thumbnail( $hk9_story_id ) ) {
			$hk9_image_id = (int) get_post_thumbnail_id( $hk9_story_id );
		}
		$hk9_link = (string) get_permalink( $hk9_story_id );
	}
}

$hk9_quote = trim( $hk9_quote, "\"“” \n\r\t" );
if ( '' === $hk9_quote ) {
	return;
}

$hk9_has_image = $hk9_image_id > 0 && 'attachment' === get_post_type( $hk9_image_id );

hk9_section_open( $hk9_id, 'hk9-section--plain hk9-overlap hk9-featured', [ 'aria-label' => __( 'Featured story', 'heartland-k9s' ) ] );
?>
<figure class="hk9-overlap__card hk9-overlap__card--split hk9-featured__card<?php echo $hk9_has_image ? '' : ' hk9-featured__card--no-image'; ?>">
	<?php if ( $hk9_has_image ) : ?>
		<div class="hk9-overlap__media hk9-featured__media">
			<?php // The overlap card starts inside the first viewport: load eagerly (no priority hint). ?>
			<?php echo hk9_image( $hk9_image_id, 'large', [ 'sizes' => '(max-width: 767px) calc(100vw - 32px), (max-width: 1087px) calc(50vw - 32px), 512px', 'alt' => '' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		</div>
	<?php endif; ?>
	<div class="hk9-overlap__body hk9-featured__body">
		<?php echo hk9_icon( 'quote', [ 'class' => 'hk9-featured__glyph', 'size' => 48 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<blockquote class="hk9-featured__quote"><p>“<?php echo esc_html( $hk9_quote ); ?>”</p></blockquote>
		<?php if ( '' !== $hk9_name || '' !== $hk9_meta || '' !== $hk9_link ) : ?>
			<figcaption class="hk9-featured__author">
				<?php if ( '' !== $hk9_name ) : ?>
					<div class="hk9-featured__name"><?php echo esc_html( $hk9_name ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $hk9_meta ) : ?>
					<div class="hk9-featured__meta"><?php echo esc_html( $hk9_meta ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $hk9_link ) : ?>
					<a class="hk9-link hk9-featured__link" href="<?php echo esc_url( $hk9_link ); ?>"><?php esc_html_e( 'Read the full story', 'heartland-k9s' ); ?><?php echo hk9_icon( 'arrow-right', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
				<?php endif; ?>
			</figcaption>
		<?php endif; ?>
	</div>
</figure>
<?php
hk9_section_close();
