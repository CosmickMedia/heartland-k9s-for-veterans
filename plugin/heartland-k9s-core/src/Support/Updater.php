<?php
/**
 * Self-updates from GitHub releases (plugin-update-checker by Yahnis Elsts, MIT).
 *
 * The theme and this plugin are versioned in lockstep and released together as tag vX.Y.Z on
 * https://github.com/CosmickMedia/heartland-k9s-for-veterans with two assets: heartland-k9s-core.zip
 * (this plugin) and heartland-k9s.zip (the theme). Each package's checker only accepts its own asset,
 * so WordPress offers the plugin and theme updates independently under Dashboard → Updates.
 *
 * Optional: define HK9_GITHUB_TOKEN in wp-config.php to raise the GitHub API rate limit / use a private repo.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Support;

defined( 'ABSPATH' ) || exit;

final class Updater {

	public const REPO_URL = 'https://github.com/CosmickMedia/heartland-k9s-for-veterans/';

	/** @var object|null */
	private static $checker = null;

	public static function register(): void {
		$loader = HK9_CORE_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $loader ) ) {
			return;
		}
		require_once $loader;
		if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::REPO_URL,
			HK9_CORE_FILE,
			'heartland-k9s-core'
		);
		$api = $checker->getVcsApi();
		// Only published (non pre-release) GitHub releases that carry the plugin ZIP count as updates.
		$api->enableReleaseAssets( '/^heartland-k9s-core\.zip$/i', \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS );
		if ( defined( 'HK9_GITHUB_TOKEN' ) && HK9_GITHUB_TOKEN ) {
			$api->setAuthentication( HK9_GITHUB_TOKEN );
		}
		self::$checker = $checker;
	}

	/** The update checker instance (null when the library is missing). */
	public static function checker(): ?object {
		return self::$checker;
	}
}
