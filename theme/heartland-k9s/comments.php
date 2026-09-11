<?php
/**
 * Comments: threaded list (initials avatars, reply links, pagination) and the
 * comment form in .hk9-form markup. Closed comments show a notice when
 * comments already exist; a closed post without comments renders nothing.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}

$hk9_count = (int) get_comments_number();
$hk9_open  = comments_open();

if ( ! $hk9_open && 0 === $hk9_count ) {
	return;
}
?>
<section id="comments" class="hk9-comments" aria-label="<?php esc_attr_e( 'Comments', 'heartland-k9s' ); ?>">
	<?php if ( have_comments() ) : ?>
		<h2 class="hk9-comments__title">
			<?php
			if ( 1 === $hk9_count ) {
				esc_html_e( 'One comment', 'heartland-k9s' );
			} else {
				/* translators: %s: number of comments */
				echo esc_html( sprintf( _n( '%s comment', '%s comments', $hk9_count, 'heartland-k9s' ), number_format_i18n( $hk9_count ) ) );
			}
			?>
		</h2>

		<ol class="comment-list hk9-comments__list">
			<?php
			wp_list_comments(
				[
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 0,
					'callback'    => 'hk9_comment_callback',
				]
			);
			?>
		</ol>

		<?php
		the_comments_navigation(
			[
				'prev_text' => hk9_icon( 'arrow-right', [ 'size' => 16, 'class' => 'hk9-icon--flip' ] ) . '<span>' . esc_html__( 'Older comments', 'heartland-k9s' ) . '</span>',
				'next_text' => '<span>' . esc_html__( 'Newer comments', 'heartland-k9s' ) . '</span>' . hk9_icon( 'arrow-right', [ 'size' => 16 ] ),
				'class'     => 'comment-navigation',
			]
		);
		?>
	<?php endif; ?>

	<?php if ( ! $hk9_open && $hk9_count > 0 ) : ?>
		<p class="hk9-comments__closed no-comments"><?php esc_html_e( 'Comments are closed.', 'heartland-k9s' ); ?></p>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
