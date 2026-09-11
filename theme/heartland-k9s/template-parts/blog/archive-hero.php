<?php
/**
 * Listing hero band: eyebrow (archive type), title, description, optional
 * background image (blog.hero_image / posts-page thumbnail) and, for search
 * and 404, the search form.
 *
 * @package heartland-k9s
 *
 * @var array $args {
 *     eyebrow: string, title: string, title_html: string (pre-escaped; wins over title),
 *     text: string (HTML allowed, wp_kses_post), image: int, search: bool, class: string
 * }
 */

defined( 'ABSPATH' ) || exit;

$hk9_args    = is_array( $args ) ? $args : [];
$hk9_eyebrow = trim( (string) ( $hk9_args['eyebrow'] ?? '' ) );
$hk9_title   = trim( (string) ( $hk9_args['title'] ?? '' ) );
$hk9_title_h = trim( (string) ( $hk9_args['title_html'] ?? '' ) );
$hk9_text    = trim( (string) ( $hk9_args['text'] ?? '' ) );
$hk9_image   = (int) ( $hk9_args['image'] ?? 0 );
$hk9_search  = ! empty( $hk9_args['search'] );
$hk9_extra   = trim( (string) ( $hk9_args['class'] ?? '' ) );

if ( '' === $hk9_title && '' === $hk9_title_h ) {
	$hk9_title = get_bloginfo( 'name' );
}
if ( $hk9_image > 0 && ! wp_attachment_is_image( $hk9_image ) ) {
	$hk9_image = 0;
}

$hk9_classes = [ 'hk9-hero', 'hk9-hero--band', 'hk9-hero--blog' ];
if ( $hk9_image > 0 ) {
	$hk9_classes[] = 'hk9-hero--has-image';
	$hk9_classes[] = 'hk9-hero--overlay-70';
}
if ( '' !== $hk9_extra ) {
	$hk9_classes = array_merge( $hk9_classes, preg_split( '/\s+/', $hk9_extra, -1, PREG_SPLIT_NO_EMPTY ) );
}

// Descriptions come from term/author fields (wpautop'd HTML) or plain settings text.
$hk9_text_html = '';
if ( '' !== $hk9_text ) {
	$hk9_text_html = false === strpos( $hk9_text, '<' ) ? hk9_paragraphs( $hk9_text ) : wp_kses_post( $hk9_text );
}
?>
<section class="<?php echo esc_attr( implode( ' ', array_unique( $hk9_classes ) ) ); ?>" aria-labelledby="hk9-hero-title">
	<?php if ( $hk9_image > 0 ) : ?>
		<div class="hk9-hero__bg">
			<?php echo hk9_image( $hk9_image, 'hk9-hero', [ 'sizes' => '100vw', 'alt' => '' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
			<div class="hk9-hero__overlay"></div>
			<div class="hk9-hero__gradient"></div>
		</div>
	<?php endif; ?>
	<div class="hk9-hero__content">
		<?php if ( '' !== $hk9_eyebrow ) : ?>
			<div><span class="hk9-hero__eyebrow"><?php echo esc_html( $hk9_eyebrow ); ?></span></div>
		<?php endif; ?>
		<h1 id="hk9-hero-title" class="hk9-hero__title"><?php echo '' !== $hk9_title_h ? $hk9_title_h : esc_html( $hk9_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- title_html is pre-escaped by the caller. ?></h1>
		<?php if ( '' !== $hk9_text_html ) : ?>
			<div class="hk9-hero__text hk9-archive-description"><?php echo $hk9_text_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></div>
		<?php endif; ?>
		<?php if ( $hk9_search ) : ?>
			<div class="hk9-hero__search">
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>
	</div>
</section>
