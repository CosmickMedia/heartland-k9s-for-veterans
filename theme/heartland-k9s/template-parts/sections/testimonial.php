<?php
/**
 * Section: testimonial — muted rounded card, image 2/5 + quote 3/5.
 * Source `story` pulls quote/name/meta/image from an hk9_story record.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data     = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id       = (string) ( $args['id'] ?? 'testimonial' );
$hk9_source   = ( $hk9_data['source'] ?? 'manual' ) === 'story' ? 'story' : 'manual';
$hk9_quote    = trim( (string) ( $hk9_data['quote'] ?? '' ) );
$hk9_name     = trim( (string) ( $hk9_data['name'] ?? '' ) );
$hk9_meta     = trim( (string) ( $hk9_data['meta'] ?? '' ) );
$hk9_image_id = (int) ( $hk9_data['image'] ?? 0 );
$hk9_button   = is_array( $hk9_data['button'] ?? null ) ? $hk9_data['button'] : [];
$hk9_story_id = (int) ( $hk9_data['story'] ?? 0 );

if ( 'story' === $hk9_source && $hk9_story_id > 0 && 'hk9_story' === get_post_type( $hk9_story_id ) && 'publish' === get_post_status( $hk9_story_id ) ) {
	$hk9_story_quote = trim( (string) get_post_meta( $hk9_story_id, 'quote', true ) );
	if ( '' !== $hk9_story_quote ) {
		$hk9_quote = $hk9_story_quote;
	}
	if ( '' === $hk9_name ) {
		$hk9_name = trim( (string) get_post_meta( $hk9_story_id, 'veteran_name', true ) ) ?: get_the_title( $hk9_story_id );
	}
	if ( '' === $hk9_meta ) {
		$hk9_branch = trim( (string) get_post_meta( $hk9_story_id, 'branch', true ) );
		$hk9_canine = trim( (string) get_post_meta( $hk9_story_id, 'canine_name', true ) );
		$hk9_meta   = implode( ' · ', array_filter( [ $hk9_branch, '' !== $hk9_canine ? sprintf( /* translators: %s: dog name */ __( 'Paired with %s', 'heartland-k9s' ), $hk9_canine ) : '' ] ) );
	}
	if ( $hk9_image_id <= 0 && has_post_thumbnail( $hk9_story_id ) ) {
		$hk9_image_id = (int) get_post_thumbnail_id( $hk9_story_id );
	}
	if ( ! hk9_link_is_set( $hk9_button ) ) {
		$hk9_button = hk9_theme_link( __( 'Read More Stories', 'heartland-k9s' ), get_permalink( $hk9_story_id ) );
	}
}

if ( '' === $hk9_quote ) {
	return;
}

// Curly quotes around the quote text (the reference wraps it in straight quotes).
$hk9_quote = trim( $hk9_quote, "\"“” \n\r\t" );

hk9_section_open( $hk9_id, 'hk9-section--py24' );
?>
<div class="container">
	<figure class="hk9-testimonial">
		<?php if ( $hk9_image_id > 0 ) : ?>
			<div class="hk9-testimonial__media">
				<?php echo hk9_image( $hk9_image_id, 'hk9-portrait', [ 'sizes' => '(max-width: 767px) calc(100vw - 32px), 410px', 'alt' => '' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
			</div>
		<?php endif; ?>
		<div class="hk9-testimonial__body">
			<svg class="hk9-testimonial__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
			<blockquote class="hk9-testimonial__quote"><p>“<?php echo esc_html( $hk9_quote ); ?>”</p></blockquote>
			<figcaption class="hk9-testimonial__author">
				<div>
					<?php if ( '' !== $hk9_name ) : ?>
						<div class="hk9-testimonial__name"><?php echo esc_html( $hk9_name ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== $hk9_meta ) : ?>
						<div class="hk9-testimonial__meta"><?php echo esc_html( $hk9_meta ); ?></div>
					<?php endif; ?>
				</div>
				<?php if ( hk9_link_is_set( $hk9_button ) ) : ?>
					<?php echo hk9_button( $hk9_button, 'ghost', [ 'icon' => 'arrow-right' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php endif; ?>
			</figcaption>
		</div>
	</figure>
</div>
<?php
hk9_section_close();
