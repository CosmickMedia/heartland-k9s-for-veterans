<?php
/**
 * Site footer: brand column, Quick Links, Get Involved, Contact Us, bottom bar.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_description = (string) hk9_theme_option( 'footer.description' );
$hk9_tagline     = (string) hk9_theme_option( 'footer.tagline' );
$hk9_col2        = (string) hk9_theme_option( 'footer.col2_heading' );
$hk9_col3        = (string) hk9_theme_option( 'footer.col3_heading' );
$hk9_col4        = (string) hk9_theme_option( 'footer.col4_heading' );
$hk9_copyright   = hk9_replace_tokens( (string) hk9_theme_option( 'footer.copyright' ) );
$hk9_credit      = trim( hk9_replace_tokens( (string) hk9_theme_option( 'footer.credit' ) ) );
$hk9_credit_by   = trim( (string) hk9_theme_option( 'footer.credit_by_label' ) );
$hk9_credit_url  = trim( (string) hk9_theme_option( 'footer.credit_by_url' ) );

$hk9_email      = (string) hk9_theme_option( 'contact.email' );
$hk9_phone      = (string) hk9_theme_option( 'contact.phone_main' );
$hk9_hours      = trim( (string) hk9_theme_option( 'contact.hours_days' ) . ' ' . (string) hk9_theme_option( 'contact.hours' ) );
$hk9_address    = hk9_contact_address();
$hk9_service    = (string) hk9_theme_option( 'contact.service_area' );
// `icon` names a brand symbol in the sprite (Simple Icons, built by tools/build-icons.mjs);
// when the sprite lacks it the visible text label is printed instead. The X logo is
// `x-social` — `x` is lucide's close glyph.
$hk9_socials    = [
	'facebook'  => [ 'label' => 'Facebook', 'icon' => 'facebook', 'url' => (string) hk9_theme_option( 'contact.facebook' ) ],
	'instagram' => [ 'label' => 'Instagram', 'icon' => 'instagram', 'url' => (string) hk9_theme_option( 'contact.instagram' ) ],
	'youtube'   => [ 'label' => 'YouTube', 'icon' => 'youtube', 'url' => (string) hk9_theme_option( 'contact.youtube' ) ],
	'linkedin'  => [ 'label' => 'LinkedIn', 'icon' => 'linkedin', 'url' => (string) hk9_theme_option( 'contact.linkedin' ) ],
	'x'         => [ 'label' => 'X', 'icon' => 'x-social', 'url' => (string) hk9_theme_option( 'contact.x' ) ],
	'tiktok'    => [ 'label' => 'TikTok', 'icon' => 'tiktok', 'url' => (string) hk9_theme_option( 'contact.tiktok' ) ],
];
$hk9_socials    = array_filter( $hk9_socials, static fn( $s ) => '' !== $s['url'] );

// "Built with ♥ for our veterans by Cosmick Media." → heart icon in place of the glyph,
// the "by …" label linked (footer.credit_by_label / footer.credit_by_url); the whole
// "by" part is omitted when the label is empty. Settings\Schema keeps the credit
// without a trailing full stop; one saved with it ("… veterans.") is trimmed before
// "by" is appended so the line never reads "veterans. by".
$hk9_credit_html = '';
if ( '' !== $hk9_credit ) {
	if ( '' !== $hk9_credit_by ) {
		$hk9_credit = rtrim( $hk9_credit, " \t." );
	}
	$hk9_credit_html = esc_html( $hk9_credit );
	if ( false !== strpos( $hk9_credit, '♥' ) ) {
		$hk9_credit_html = str_replace( '♥', hk9_icon( 'heart', [ 'fill' => true, 'size' => 12, 'title' => __( 'love', 'heartland-k9s' ) ] ), $hk9_credit_html );
	}
	if ( '' !== $hk9_credit_by ) {
		$hk9_by_html = '' !== $hk9_credit_url
			? '<a class="hk9-footer__credit-link" href="' . esc_url( $hk9_credit_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $hk9_credit_by ) . '</a>'
			: esc_html( $hk9_credit_by );
		// One <span> for "by …." so the inline-flex credit keeps the full stop next to the name.
		$hk9_credit_html .= ' <span class="hk9-footer__credit-by">' . sprintf(
			/* translators: %s: name of the site's developer (linked). */
			esc_html_x( 'by %s', 'footer credit line', 'heartland-k9s' ),
			$hk9_by_html
		) . '.</span>';
	}
}
?>
</main>

<footer id="hk9-footer" class="hk9-footer">
	<div class="container hk9-footer__inner">
		<div class="hk9-footer__grid">
			<div class="hk9-footer__brand">
				<a class="hk9-footer__brand-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php echo hk9_logo_img( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
					<?php echo hk9_wordmark( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<span class="screen-reader-text"><?php bloginfo( 'name' ); ?></span>
				</a>
				<?php if ( '' !== $hk9_description ) : ?>
					<p class="hk9-footer__description"><?php echo esc_html( $hk9_description ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $hk9_tagline ) : ?>
					<p class="hk9-footer__tagline"><?php echo esc_html( $hk9_tagline ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $hk9_socials ) ) : ?>
					<ul class="hk9-footer__social" aria-label="<?php esc_attr_e( 'Social media', 'heartland-k9s' ); ?>">
						<?php foreach ( $hk9_socials as $hk9_key => $hk9_social ) : ?>
							<?php $hk9_social_icon = hk9_icon( $hk9_social['icon'], [ 'size' => 20 ] ); ?>
							<li>
								<a href="<?php echo esc_url( $hk9_social['url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php if ( '' !== $hk9_social_icon ) : ?>
										<?php echo $hk9_social_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
										<span class="screen-reader-text"><?php echo esc_html( $hk9_social['label'] ); ?></span>
									<?php else : ?>
										<?php echo esc_html( $hk9_social['label'] ); ?>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="hk9-footer__col">
				<?php if ( '' !== $hk9_col2 ) : ?>
					<h2 class="hk9-footer__heading"><?php echo esc_html( $hk9_col2 ); ?></h2>
				<?php endif; ?>
				<?php hk9_footer_menu( 'footer_quick', hk9_footer_fallback_links( 'quick' ) ); ?>
			</div>

			<div class="hk9-footer__col">
				<?php if ( '' !== $hk9_col3 ) : ?>
					<h2 class="hk9-footer__heading"><?php echo esc_html( $hk9_col3 ); ?></h2>
				<?php endif; ?>
				<?php hk9_footer_menu( 'footer_involved', hk9_footer_fallback_links( 'involved' ) ); ?>
			</div>

			<div class="hk9-footer__col">
				<?php if ( '' !== $hk9_col4 ) : ?>
					<h2 class="hk9-footer__heading"><?php echo esc_html( $hk9_col4 ); ?></h2>
				<?php endif; ?>
				<ul class="hk9-footer__list hk9-footer__list--contact">
					<?php if ( '' !== $hk9_email ) : ?>
						<li class="hk9-footer__contact-row">
							<?php echo hk9_icon( 'mail', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<a href="<?php echo esc_url( 'mailto:' . $hk9_email ); ?>"><?php echo esc_html( $hk9_email ); ?></a>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $hk9_phone ) : ?>
						<li class="hk9-footer__contact-row">
							<?php echo hk9_icon( 'phone', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<div>
								<a href="<?php echo esc_attr( hk9_tel_href( $hk9_phone ) ); ?>"><?php echo esc_html( $hk9_phone ); ?></a>
								<?php if ( '' !== $hk9_hours ) : ?>
									<span class="hk9-footer__contact-sub"><?php echo esc_html( $hk9_hours ); ?></span>
								<?php endif; ?>
							</div>
						</li>
					<?php endif; ?>
					<?php if ( '' !== $hk9_address || '' !== $hk9_service ) : ?>
						<li class="hk9-footer__contact-row">
							<?php echo hk9_icon( 'map-pin', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<div>
								<?php if ( '' !== $hk9_address ) : ?>
									<address style="font-style:normal;margin:0"><?php echo esc_html( $hk9_address ); ?></address>
								<?php endif; ?>
								<?php if ( '' !== $hk9_service ) : ?>
									<span class="hk9-footer__contact-sub"><?php echo esc_html( $hk9_service ); ?></span>
								<?php endif; ?>
							</div>
						</li>
					<?php endif; ?>
				</ul>
				<?php
				$hk9_seal_url = (string) hk9_theme_option( 'contact.candid_url' );
				if ( hk9_theme_option( 'footer.show_seal' ) && hk9_theme_option( 'contact.show_guidestar_seal' ) && '' !== $hk9_seal_url ) :
					?>
					<p class="hk9-footer__seal"><a href="<?php echo esc_url( $hk9_seal_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View our Candid / GuideStar profile', 'heartland-k9s' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="hk9-footer__bottom">
			<p><?php echo esc_html( $hk9_copyright ); ?></p>
			<?php if ( has_nav_menu( 'legal' ) ) : ?>
				<nav aria-label="<?php esc_attr_e( 'Legal', 'heartland-k9s' ); ?>">
					<?php
					wp_nav_menu(
						[
							'theme_location' => 'legal',
							'container'      => false,
							'menu_class'     => 'hk9-footer__legal',
							'menu_id'        => '',
							'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
							'depth'          => 1,
							'fallback_cb'    => false,
						]
					);
					?>
				</nav>
			<?php endif; ?>
			<?php if ( '' !== $hk9_credit_html ) : ?>
				<p class="hk9-footer__credit"><?php echo $hk9_credit_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></p>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
