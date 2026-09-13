<?php
/**
 * Theme self-updates from GitHub releases (plugin-update-checker by Yahnis Elsts, MIT).
 *
 * Released together with the companion plugin as tag vX.Y.Z on
 * https://github.com/CosmickMedia/heartland-k9s-for-veterans; this checker only accepts the
 * heartland-k9s.zip asset of a published release. Optional HK9_GITHUB_TOKEN raises the API limit.
 *
 * @package heartland-k9s
 */

defined( 'ABSPATH' ) || exit;

function hk9_theme_register_updater(): void {
	$loader = HK9_THEME_DIR . '/vendor/plugin-update-checker/plugin-update-checker.php';
	if ( ! is_readable( $loader ) ) {
		return;
	}
	require_once $loader;
	if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
		return;
	}
	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/CosmickMedia/heartland-k9s-for-veterans/',
		HK9_THEME_DIR . '/style.css',
		'heartland-k9s'
	);
	$api = $checker->getVcsApi();
	$api->enableReleaseAssets( '/^heartland-k9s\.zip$/i', \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS );
	if ( defined( 'HK9_GITHUB_TOKEN' ) && HK9_GITHUB_TOKEN ) {
		$api->setAuthentication( HK9_GITHUB_TOKEN );
	}
}
hk9_theme_register_updater();
