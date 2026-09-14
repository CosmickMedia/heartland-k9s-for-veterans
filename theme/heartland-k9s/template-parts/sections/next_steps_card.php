<?php
/**
 * Section: next_steps_card (thank-you) — the white card overlapping the navy
 * band after the Initial Application Inquiry: icon well + reassurance, the
 * numbered steps (<ol>, 40px crimson circles), the form download button
 * (type + size read from the attachment), a muted callout with the return
 * address from Settings → Contact (or a custom address) and a tip line.
 *
 * The page's block content (editor canvas) renders inside the same card,
 * after the callout — `$args['content']` is a callable the page template
 * passes in (page-templates/thank-you.php); when the section is hidden the
 * template prints the content in a bare card instead, so nothing is lost.
 *
 * @package heartland-k9s
 *
 * @var array $args { data: array, post_id: int, id: string, template: string, content?: callable }
 */

defined( 'ABSPATH' ) || exit;

$hk9_data    = is_array( $args['data'] ?? null ) ? $args['data'] : [];
$hk9_id      = (string) ( $args['id'] ?? 'next_steps' );
$hk9_post_id = (int) ( $args['post_id'] ?? 0 );
$hk9_content = is_callable( $args['content'] ?? null ) ? $args['content'] : null;

$hk9_icon          = (string) ( $hk9_data['icon'] ?? 'circle-check' );
$hk9_heading       = trim( (string) ( $hk9_data['heading'] ?? '' ) );
$hk9_text          = trim( (string) ( $hk9_data['text'] ?? '' ) );
$hk9_steps_heading = trim( (string) ( $hk9_data['steps_heading'] ?? '' ) );
$hk9_file_id       = (int) ( $hk9_data['file'] ?? 0 );
$hk9_file_label    = trim( (string) ( $hk9_data['file_label'] ?? '' ) );
$hk9_show_meta     = ! isset( $hk9_data['show_file_meta'] ) || ! empty( $hk9_data['show_file_meta'] );
$hk9_new_tab       = ! isset( $hk9_data['open_in_new_tab'] ) || ! empty( $hk9_data['open_in_new_tab'] );
$hk9_help_text     = trim( (string) ( $hk9_data['help_text'] ?? '' ) );
$hk9_addr_heading  = trim( (string) ( $hk9_data['address_heading'] ?? '' ) );
$hk9_use_settings  = ! isset( $hk9_data['use_settings_address'] ) || ! empty( $hk9_data['use_settings_address'] );
$hk9_addr_custom   = trim( (string) ( $hk9_data['address_custom'] ?? '' ) );
$hk9_tip           = trim( (string) ( $hk9_data['tip'] ?? '' ) );

$hk9_steps = is_array( $hk9_data['steps'] ?? null ) ? array_values( array_filter( $hk9_data['steps'], 'is_array' ) ) : [];
$hk9_steps = array_values(
	array_filter(
		$hk9_steps,
		static fn( array $step ): bool => '' !== trim( (string) ( $step['title'] ?? '' ) ) || '' !== trim( (string) ( $step['text'] ?? '' ) )
	)
);

// The download: only a real attachment of an allowed type gets a button.
$hk9_file_url  = '';
$hk9_file_meta = '';
if ( $hk9_file_id > 0 && 'attachment' === get_post_type( $hk9_file_id ) ) {
	$hk9_file_url = (string) wp_get_attachment_url( $hk9_file_id );
	if ( '' !== $hk9_file_url && $hk9_show_meta ) {
		$hk9_file_meta = hk9_attachment_meta_label( $hk9_file_id );
	}
}
if ( '' === $hk9_file_label ) {
	$hk9_file_label = __( 'Download the form', 'heartland-k9s' );
}

// Return address: Settings → Contact (like the Donate page's mail-in panel), else the custom lines.
$hk9_addr_lines = [];
$hk9_name_line  = false; // First line is the organisation's legal name from Settings (bold).
if ( $hk9_use_settings ) {
	$hk9_legal = trim( (string) hk9_theme_option( 'contact.legal_name' ) );
	$hk9_line1 = trim( (string) hk9_theme_option( 'contact.address_line1' ) );
	$hk9_line2 = trim( (string) hk9_theme_option( 'contact.address_line2' ) );
	$hk9_city  = trim( trim( (string) hk9_theme_option( 'contact.city' ) ) . ', ' . trim( (string) hk9_theme_option( 'contact.state' ) ) . ' ' . trim( (string) hk9_theme_option( 'contact.zip' ) ), ', ' );
	$hk9_addr_lines = array_values( array_filter( [ $hk9_legal, $hk9_line1, $hk9_line2, ',' === $hk9_city ? '' : $hk9_city ], static fn( string $line ): bool => '' !== $line ) );
	$hk9_name_line  = '' !== $hk9_legal && ! empty( $hk9_addr_lines );
}
if ( empty( $hk9_addr_lines ) && '' !== $hk9_addr_custom ) {
	$hk9_addr_lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $hk9_addr_custom ) ), 'strlen' ) );
	$hk9_name_line  = false;
}
$hk9_has_address = ! empty( $hk9_addr_lines );

// Heading levels follow what is actually printed (h1 hero → h2 card → h3 sub-headings → h4 steps).
$hk9_sub_tag  = '' !== $hk9_heading ? 'h3' : 'h2';
$hk9_step_tag = '' !== $hk9_steps_heading ? ( 'h3' === $hk9_sub_tag ? 'h4' : 'h3' ) : $hk9_sub_tag;

$hk9_has_content = null !== $hk9_content && $hk9_post_id > 0 && ! hk9_content_is_blank( $hk9_post_id );

if ( '' === $hk9_heading && '' === $hk9_text && empty( $hk9_steps ) && '' === $hk9_file_url && ! $hk9_has_address && ! $hk9_has_content ) {
	return;
}

$hk9_heading_id = 'hk9-' . sanitize_html_class( $hk9_id ) . '-heading';

hk9_section_open( $hk9_id, 'hk9-section--plain hk9-overlap hk9-thank-you', '' !== $hk9_heading ? [ 'aria-labelledby' => $hk9_heading_id ] : [] );
?>
<div class="hk9-overlap__card hk9-thank-you__card">
	<div class="hk9-thank-you__intro">
		<?php if ( '' !== $hk9_icon && hk9_icon_exists( $hk9_icon ) ) : ?>
			<span class="hk9-icon-well hk9-icon-well--crimson hk9-icon-well--lg hk9-thank-you__icon" aria-hidden="true"><?php echo hk9_icon( $hk9_icon, [ 'size' => 32 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
		<?php endif; ?>
		<div class="hk9-thank-you__intro-body">
			<?php if ( '' !== $hk9_heading ) : ?>
				<h2 class="hk9-thank-you__heading" id="<?php echo esc_attr( $hk9_heading_id ); ?>"><?php echo esc_html( $hk9_heading ); ?></h2>
			<?php endif; ?>
			<?php echo hk9_paragraphs( $hk9_text, 'hk9-thank-you__text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	</div>

	<?php if ( ! empty( $hk9_steps ) ) : ?>
		<div class="hk9-thank-you__steps">
			<?php if ( '' !== $hk9_steps_heading ) : ?>
				<<?php echo $hk9_sub_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3 literal. ?> class="hk9-thank-you__steps-heading"><?php echo esc_html( $hk9_steps_heading ); ?></<?php echo $hk9_sub_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3 literal. ?>>
			<?php endif; ?>
			<ol class="hk9-thank-you__list" role="list">
				<?php foreach ( $hk9_steps as $hk9_index => $hk9_step ) : ?>
					<li class="hk9-thank-you__step">
						<span class="hk9-step-num" aria-hidden="true"><?php echo esc_html( (string) ( $hk9_index + 1 ) ); ?></span>
						<div class="hk9-thank-you__step-body">
							<?php if ( '' !== trim( (string) ( $hk9_step['title'] ?? '' ) ) ) : ?>
								<<?php echo $hk9_step_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3|h4 literal. ?> class="hk9-thank-you__step-title"><?php echo esc_html( trim( (string) $hk9_step['title'] ) ); ?></<?php echo $hk9_step_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3|h4 literal. ?>>
							<?php endif; ?>
							<?php echo hk9_paragraphs( trim( (string) ( $hk9_step['text'] ?? '' ) ), 'hk9-thank-you__step-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $hk9_file_url ) : ?>
		<div class="hk9-thank-you__download">
			<a class="hk9-btn hk9-btn--primary hk9-btn--lg hk9-thank-you__button" href="<?php echo esc_url( $hk9_file_url ); ?>"<?php echo $hk9_new_tab ? ' target="_blank" rel="noopener"' : ''; ?>>
				<span class="hk9-thank-you__button-label"><?php echo hk9_icon( 'file-down', [ 'size' => 20, 'class' => 'hk9-btn__icon hk9-btn__icon--lead hk9-icon--20' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><?php echo esc_html( $hk9_file_label ); ?></span>
				<?php if ( '' !== $hk9_file_meta ) : ?>
					<span class="hk9-thank-you__button-meta">(<?php echo esc_html( $hk9_file_meta ); ?>)</span>
				<?php endif; ?>
				<?php if ( $hk9_new_tab ) : ?>
					<span class="hk9-visually-hidden"><?php esc_html_e( '(opens in a new tab)', 'heartland-k9s' ); ?></span>
				<?php endif; ?>
			</a>
			<span class="hk9-thank-you__print-url" aria-hidden="true"><?php echo esc_html( $hk9_file_url ); ?></span>
			<?php echo hk9_paragraphs( $hk9_help_text, 'hk9-thank-you__help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</div>
	<?php endif; ?>

	<?php if ( $hk9_has_address ) : ?>
		<div class="hk9-callout hk9-callout--lg hk9-thank-you__address-panel">
			<span class="hk9-icon-well hk9-thank-you__address-icon" aria-hidden="true"><?php echo hk9_icon( 'map-pin', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<div class="hk9-thank-you__address-body">
				<?php if ( '' !== $hk9_addr_heading ) : ?>
					<<?php echo $hk9_sub_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3 literal. ?> class="hk9-thank-you__address-heading"><?php echo esc_html( $hk9_addr_heading ); ?></<?php echo $hk9_sub_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3 literal. ?>>
				<?php endif; ?>
				<address class="hk9-thank-you__address">
					<?php foreach ( $hk9_addr_lines as $hk9_index => $hk9_line ) : ?>
						<span class="hk9-thank-you__address-line<?php echo 0 === $hk9_index && $hk9_name_line ? ' hk9-thank-you__address-line--name' : ''; ?>"><?php echo esc_html( $hk9_line ); ?></span>
					<?php endforeach; ?>
				</address>
				<?php if ( '' !== $hk9_tip ) : ?>
					<p class="hk9-thank-you__tip"><?php echo hk9_icon( 'lightbulb', [ 'size' => 16, 'class' => 'hk9-thank-you__tip-icon' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?><span><?php echo esc_html( $hk9_tip ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $hk9_has_content ) : ?>
		<div class="hk9-prose hk9-thank-you__content">
			<?php call_user_func( $hk9_content ); ?>
		</div>
	<?php endif; ?>
</div>
<?php
hk9_section_close();
