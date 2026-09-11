<?php
/**
 * Attachment body: the media (image at large size linking to the original, or a
 * download link for other files), caption, description and a link to the parent.
 *
 * @package heartland-k9s
 *
 * @var array $args { post: WP_Post }
 */

defined( 'ABSPATH' ) || exit;

$hk9_post = isset( $args['post'] ) ? get_post( $args['post'] ) : get_post();
if ( ! $hk9_post instanceof WP_Post || 'attachment' !== $hk9_post->post_type ) {
	return;
}

$hk9_id       = (int) $hk9_post->ID;
$hk9_file_url = (string) wp_get_attachment_url( $hk9_id );
$hk9_is_image = wp_attachment_is_image( $hk9_id );
$hk9_caption  = trim( (string) wp_get_attachment_caption( $hk9_id ) );
$hk9_parent   = $hk9_post->post_parent > 0 ? get_post( $hk9_post->post_parent ) : null;
$hk9_parent   = $hk9_parent instanceof WP_Post && 'publish' === $hk9_parent->post_status ? $hk9_parent : null;
$hk9_meta     = wp_get_attachment_metadata( $hk9_id );
$hk9_file     = get_attached_file( $hk9_id );
$hk9_size     = is_string( $hk9_file ) && file_exists( $hk9_file ) ? size_format( (int) filesize( $hk9_file ) ) : '';
$hk9_type     = (string) $hk9_post->post_mime_type;
?>
<div class="hk9-attachment">
	<?php if ( $hk9_is_image ) : ?>
		<figure class="hk9-attachment__figure">
			<a class="hk9-attachment__link" href="<?php echo esc_url( $hk9_file_url ); ?>">
				<?php echo hk9_image( $hk9_id, 'large', [ 'sizes' => '(max-width: 1023px) calc(100vw - 32px), 928px' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
				<span class="screen-reader-text"><?php esc_html_e( 'View full-size image', 'heartland-k9s' ); ?></span>
			</a>
			<?php if ( '' !== $hk9_caption ) : ?>
				<figcaption class="hk9-attachment__caption"><?php echo wp_kses_post( $hk9_caption ); ?></figcaption>
			<?php endif; ?>
		</figure>
	<?php else : ?>
		<p class="hk9-attachment__file">
			<a class="hk9-btn hk9-btn--navy hk9-btn--lg" href="<?php echo esc_url( $hk9_file_url ); ?>">
				<?php echo hk9_icon( 'download', [ 'size' => 20, 'class' => 'hk9-icon--20' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php esc_html_e( 'Download file', 'heartland-k9s' ); ?>
			</a>
		</p>
		<?php if ( '' !== $hk9_caption ) : ?>
			<p class="hk9-attachment__caption"><?php echo wp_kses_post( $hk9_caption ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<dl class="hk9-attachment__details">
		<?php if ( $hk9_is_image && is_array( $hk9_meta ) && ! empty( $hk9_meta['width'] ) && ! empty( $hk9_meta['height'] ) ) : ?>
			<div><dt><?php esc_html_e( 'Dimensions', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( sprintf( '%1$s × %2$s', number_format_i18n( (int) $hk9_meta['width'] ), number_format_i18n( (int) $hk9_meta['height'] ) ) ); ?></dd></div>
		<?php endif; ?>
		<?php if ( '' !== $hk9_type ) : ?>
			<div><dt><?php esc_html_e( 'Type', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( $hk9_type ); ?></dd></div>
		<?php endif; ?>
		<?php if ( '' !== $hk9_size ) : ?>
			<div><dt><?php esc_html_e( 'Size', 'heartland-k9s' ); ?></dt><dd><?php echo esc_html( $hk9_size ); ?></dd></div>
		<?php endif; ?>
		<div><dt><?php esc_html_e( 'Published', 'heartland-k9s' ); ?></dt><dd><time datetime="<?php echo esc_attr( get_the_date( 'c', $hk9_post ) ); ?>"><?php echo esc_html( get_the_date( '', $hk9_post ) ); ?></time></dd></div>
	</dl>

	<?php if ( '' !== trim( (string) $hk9_post->post_content ) ) : ?>
		<div class="hk9-prose hk9-attachment__description">
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<p class="hk9-attachment__back">
		<?php if ( $hk9_parent ) : ?>
			<a class="hk9-link" href="<?php echo esc_url( (string) get_permalink( $hk9_parent ) ); ?>">
				<?php echo hk9_icon( 'arrow-right', [ 'size' => 16, 'class' => 'hk9-icon--flip' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php
				/* translators: %s: parent post title */
				echo esc_html( sprintf( __( 'Back to %s', 'heartland-k9s' ), get_the_title( $hk9_parent ) ) );
				?>
			</a>
		<?php else : ?>
			<a class="hk9-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo hk9_icon( 'arrow-right', [ 'size' => 16, 'class' => 'hk9-icon--flip' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php esc_html_e( 'Back to home', 'heartland-k9s' ); ?>
			</a>
		<?php endif; ?>
	</p>
</div>
