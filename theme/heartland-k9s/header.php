<?php
/**
 * Site header: skip link, sticky bar (brand, primary nav, Contact divider group,
 * Donate CTA, hamburger) and the mobile dropdown panel.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

$hk9_sticky   = (bool) hk9_theme_option( 'header.sticky' );
$hk9_show_cta = (bool) hk9_theme_option( 'header.show_cta' );
$hk9_cta_link = hk9_theme_option( 'header.cta_link' );
$hk9_cta_text = (string) hk9_theme_option( 'header.cta_label' );

if ( ! is_array( $hk9_cta_link ) || ! hk9_link_is_set( $hk9_cta_link ) ) {
	$hk9_cta_link = hk9_theme_option( 'links.donate' );
}
if ( is_array( $hk9_cta_link ) && '' !== $hk9_cta_text ) {
	$hk9_cta_link['label'] = $hk9_cta_text;
}
$hk9_has_cta = $hk9_show_cta && is_array( $hk9_cta_link ) && hk9_link_is_set( $hk9_cta_link );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="hk9-skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'heartland-k9s' ); ?></a>

<header id="hk9-header" class="hk9-header<?php echo $hk9_sticky ? ' hk9-header--sticky' : ''; ?>">
	<div class="container hk9-header__inner">
		<a class="hk9-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php echo hk9_logo_img( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
			<?php echo hk9_wordmark( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<span class="screen-reader-text"><?php bloginfo( 'name' ); ?></span>
		</a>

		<nav class="hk9-header__nav" aria-label="<?php esc_attr_e( 'Primary', 'heartland-k9s' ); ?>">
			<?php hk9_primary_menu( 'desktop' ); ?>
			<?php
			if ( $hk9_has_cta ) {
				echo hk9_button( $hk9_cta_link, 'primary', [ 'class' => 'hk9-header__cta' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			}
			?>
		</nav>

		<button class="hk9-header__toggle" type="button" aria-controls="hk9-mobile-menu" aria-expanded="false" data-hk9-toggle>
			<span class="screen-reader-text" data-hk9-toggle-label><?php esc_html_e( 'Open menu', 'heartland-k9s' ); ?></span>
			<?php echo hk9_icon( 'menu', [ 'class' => 'hk9-header__toggle-icon hk9-header__toggle-icon--open', 'size' => 24 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php echo hk9_icon( 'x', [ 'class' => 'hk9-header__toggle-icon hk9-header__toggle-icon--close', 'size' => 24 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</button>
	</div>

	<div id="hk9-mobile-menu" class="hk9-header__mobile" data-hk9-mobile hidden>
		<nav class="hk9-header__mobile-inner" aria-label="<?php esc_attr_e( 'Mobile', 'heartland-k9s' ); ?>">
			<?php hk9_primary_menu( 'mobile' ); ?>
			<?php if ( $hk9_has_cta ) : ?>
				<div class="hk9-header__mobile-actions">
					<?php echo hk9_button( $hk9_cta_link, 'primary', [ 'full' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				</div>
			<?php endif; ?>
		</nav>
	</div>
</header>

<main id="main" class="hk9-main" tabindex="-1">
