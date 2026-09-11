<?php
/**
 * Section: gallery_options — the photo gallery. Renders the page's block content
 * when it contains a core/gallery block (with the core lightbox switched on per the
 * `lightbox` option, captions/columns applied), otherwise the `images` ids.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data     = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id       = (string) ( $args['id'] ?? 'gallery_options' );
$hk9_post_id  = (int) ( $args['post_id'] ?? get_the_ID() );
$hk9_lightbox = ! isset( $hk9_data['lightbox'] ) || ! empty( $hk9_data['lightbox'] );
$hk9_captions = ! isset( $hk9_data['captions'] ) || ! empty( $hk9_data['captions'] );
$hk9_columns  = (int) ( $hk9_data['columns'] ?? 3 );
$hk9_columns  = in_array( $hk9_columns, [ 2, 3, 4 ], true ) ? $hk9_columns : 3;
$hk9_images   = is_array( $hk9_data['images'] ?? null ) ? $hk9_data['images'] : [];
$hk9_content  = (string) get_post_field( 'post_content', $hk9_post_id );
$hk9_has_gal  = '' !== trim( $hk9_content ) && has_block( 'core/gallery', $hk9_content );
$hk9_options  = [
	'lightbox' => $hk9_lightbox,
	'captions' => $hk9_captions,
	'columns'  => $hk9_columns,
];
?>
<div class="hk9-overlap hk9-gallery-page">
	<div class="hk9-overlap__card hk9-gallery-page__card">
		<?php if ( $hk9_has_gal ) : ?>
			<div class="hk9-gallery-wrap hk9-gallery-wrap--<?php echo esc_attr( (string) $hk9_columns ); ?> hk9-prose">
				<?php
				hk9_rec_gallery_begin( $hk9_options );
				the_content();
				hk9_rec_gallery_end();
				?>
			</div>
		<?php else : ?>
			<?php if ( '' !== trim( $hk9_content ) ) : ?>
				<div class="hk9-prose hk9-gallery-page__intro"><?php the_content(); ?></div>
			<?php endif; ?>
			<?php $hk9_gallery = hk9_rec_gallery( $hk9_images, $hk9_options + [ 'class' => 'hk9-gallery-page__grid' ] ); ?>
			<?php if ( '' !== $hk9_gallery ) : ?>
				<?php echo $hk9_gallery; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block output. ?>
			<?php elseif ( '' === trim( $hk9_content ) ) : ?>
				<div class="hk9-empty">
					<span class="hk9-icon-well hk9-icon-well--lg"><?php echo hk9_icon( 'image', [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
					<p class="hk9-empty__text"><?php esc_html_e( 'Photos are coming soon.', 'heartland-k9s' ); ?></p>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>
