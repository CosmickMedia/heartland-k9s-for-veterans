<?php
/**
 * Single: hk9_barkode — registry record (.hk9-record): photo left; dog name (h1),
 * program badge, registry id, definition list, "do not separate" alert, notice,
 * contact line and ID card images on the right.
 *
 * Review notes (hk9_review_notes / _hk9_review_notes) are admin-only and are
 * never read or rendered here. Robots noindex/nofollow and the generic meta
 * description come from the plugin / inc/compat.php.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$hk9_id       = get_the_ID();
	$hk9_dog      = trim( (string) hk9_rec_meta( $hk9_id, 'dog_name', '' ) );
	$hk9_type     = (string) hk9_rec_meta( $hk9_id, 'program_type', 'service' );
	$hk9_reg_id   = trim( (string) hk9_rec_meta( $hk9_id, 'registry_id', '' ) );
	$hk9_breed    = trim( (string) hk9_rec_meta( $hk9_id, 'breed', '' ) );
	$hk9_task     = trim( (string) hk9_rec_meta( $hk9_id, 'task_description', '' ) );
	$hk9_tasks    = trim( (string) hk9_rec_meta( $hk9_id, 'tasks', '' ) );
	$hk9_handler  = trim( (string) hk9_rec_meta( $hk9_id, 'handler_name', '' ) );
	$hk9_emerg    = trim( (string) hk9_rec_meta( $hk9_id, 'emergency_contact', '' ) );
	$hk9_vet      = trim( (string) hk9_rec_meta( $hk9_id, 'vet_contact', '' ) );
	$hk9_cert     = trim( (string) hk9_rec_meta( $hk9_id, 'certification', '' ) );
	$hk9_separate = (bool) hk9_rec_meta( $hk9_id, 'do_not_separate', false );
	$hk9_notice   = trim( (string) hk9_rec_meta( $hk9_id, 'notice', '' ) );
	$hk9_contact  = trim( (string) hk9_rec_meta( $hk9_id, 'contact_line', '' ) );
	$hk9_cards    = hk9_rec_meta( $hk9_id, 'id_card_images', [] );
	$hk9_note     = trim( (string) hk9_rec_meta( $hk9_id, 'status_note', '' ) );
	$hk9_thumb    = (int) get_post_thumbnail_id( $hk9_id );

	if ( '' === $hk9_dog ) {
		$hk9_dog = get_the_title();
	}
	if ( '' === $hk9_contact ) {
		$hk9_phone = trim( (string) hk9_theme_option( 'contact.phone_main' ) );
		/* translators: %s: phone number */
		$hk9_contact = '' !== $hk9_phone ? sprintf( __( 'Questions? Contact Heartland Canines for Veterans at %s.', 'heartland-k9s' ), $hk9_phone ) : '';
	}

	$hk9_type_labels = [
		'service'     => __( 'Service Dog', 'heartland-k9s' ),
		'therapy'     => __( 'Therapy Dog', 'heartland-k9s' ),
		'in-training' => __( 'Service Dog in Training', 'heartland-k9s' ),
	];
	$hk9_type_label  = $hk9_type_labels[ $hk9_type ] ?? $hk9_type_labels['service'];

	$hk9_fields = [
		[ 'label' => __( 'Breed', 'heartland-k9s' ), 'value' => $hk9_breed ],
		[ 'label' => __( 'Task description', 'heartland-k9s' ), 'value' => $hk9_task ],
		[ 'label' => __( 'Responsibilities & tasks', 'heartland-k9s' ), 'value' => $hk9_tasks ],
		[ 'label' => __( 'Handler', 'heartland-k9s' ), 'value' => $hk9_handler ],
		[ 'label' => __( 'Emergency contact', 'heartland-k9s' ), 'value' => $hk9_emerg ],
		[ 'label' => __( 'Veterinary contact', 'heartland-k9s' ), 'value' => $hk9_vet ],
		[ 'label' => __( 'Certification', 'heartland-k9s' ), 'value' => $hk9_cert ],
	];
	$hk9_fields = array_values( array_filter( $hk9_fields, static fn( array $f ): bool => '' !== $f['value'] ) );
	?>
	<section class="hk9-hero hk9-hero--band hk9-hero--slim hk9-pattern hk9-pattern--grid" aria-label="<?php esc_attr_e( 'BarKode registry', 'heartland-k9s' ); ?>">
		<div class="hk9-hero__content">
			<p class="hk9-hero__badge hk9-hero__badge--static"><?php echo hk9_icon( 'qr-code', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php esc_html_e( 'BarKode Registry Record', 'heartland-k9s' ); ?></span></p>
		</div>
	</section>

	<div class="hk9-overlap hk9-record-page">
		<article class="hk9-overlap__card hk9-record-page__card" id="post-<?php echo esc_attr( (string) $hk9_id ); ?>">
			<div class="hk9-record">
				<figure class="hk9-record__photo">
					<?php if ( $hk9_thumb > 0 ) : ?>
						<?php echo hk9_image( $hk9_thumb, 'hk9-portrait', [ 'alt' => $hk9_dog, 'sizes' => '(max-width: 767px) calc(100vw - 32px), 380px' ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
					<?php else : ?>
						<span class="hk9-record__placeholder" aria-hidden="true"><?php echo hk9_icon( 'dog', [ 'size' => 40 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
					<?php endif; ?>
					<?php if ( '' !== $hk9_reg_id ) : ?>
						<figcaption><?php echo esc_html( sprintf( /* translators: 1: dog name, 2: registry id */ __( '%1$s · %2$s', 'heartland-k9s' ), $hk9_dog, $hk9_reg_id ) ); ?></figcaption>
					<?php endif; ?>
				</figure>

				<div class="hk9-record__body">
					<span class="hk9-record__badge"><?php echo esc_html( $hk9_type_label ); ?></span>
					<h1 class="hk9-record__title"><?php echo esc_html( $hk9_dog ); ?></h1>
					<?php if ( '' !== $hk9_reg_id ) : ?>
						<p class="hk9-record__id"><?php echo esc_html( sprintf( /* translators: %s: registry id */ __( 'Registry ID %s', 'heartland-k9s' ), $hk9_reg_id ) ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $hk9_note ) : ?>
						<p class="hk9-record__status-note"><?php echo esc_html( $hk9_note ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $hk9_fields ) ) : ?>
						<dl class="hk9-record__fields">
							<?php foreach ( $hk9_fields as $hk9_field ) : ?>
								<div>
									<dt><?php echo esc_html( $hk9_field['label'] ); ?></dt>
									<dd><?php echo esc_html( $hk9_field['value'] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>

					<?php if ( $hk9_separate ) : ?>
						<p class="hk9-record__alert">
							<?php echo hk9_icon( 'shield-alert', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<strong><?php esc_html_e( 'DO NOT SEPARATE FROM HANDLER', 'heartland-k9s' ); ?></strong>
						</p>
					<?php endif; ?>

					<?php if ( '' !== $hk9_notice ) : ?>
						<div class="hk9-record__notice" role="note"><?php echo hk9_paragraphs( $hk9_notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
					<?php endif; ?>

					<?php if ( '' !== $hk9_contact ) : ?>
						<p class="hk9-record__contact"><?php echo hk9_icon( 'phone', [ 'size' => 16 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php echo esc_html( $hk9_contact ); ?></span></p>
					<?php endif; ?>

					<?php $hk9_cards_html = is_array( $hk9_cards ) ? hk9_rec_gallery( $hk9_cards, [ 'columns' => 2, 'captions' => false, 'class' => 'hk9-record__cards', 'sizes' => '(max-width: 767px) calc(50vw - 24px), 280px' ] ) : ''; ?>
					<?php if ( '' !== $hk9_cards_html ) : ?>
						<section class="hk9-record__id-cards" aria-labelledby="hk9-record-cards-title">
							<h2 id="hk9-record-cards-title" class="hk9-record__subheading"><?php esc_html_e( 'ID card', 'heartland-k9s' ); ?></h2>
							<?php echo $hk9_cards_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block output. ?>
						</section>
					<?php endif; ?>
				</div>
			</div>

			<footer class="hk9-record__footer">
				<?php echo hk9_rec_back_link( 'links.barkode', __( 'About the BarKode program', 'heartland-k9s' ), '/barkode/' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</footer>
		</article>
	</div>
	<div class="hk9-section hk9-section--pb20" aria-hidden="true"></div>
	<?php
endwhile;

get_footer();
