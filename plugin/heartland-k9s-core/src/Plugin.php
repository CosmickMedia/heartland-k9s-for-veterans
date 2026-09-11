<?php
/**
 * Plugin bootstrap (skeleton; modules are wired in Phase 3).
 *
 * @package HK9\Core
 */

namespace HK9\Core;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public static function boot(): void {
		load_plugin_textdomain( 'heartland-k9s-core', false, dirname( plugin_basename( HK9_CORE_FILE ) ) . '/languages' );
	}

	public static function activate(): void {
		// Activation never imports content. Schema/rewrite setup only (Phase 3).
		update_option( 'hk9_core_version', HK9_CORE_VERSION, false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
