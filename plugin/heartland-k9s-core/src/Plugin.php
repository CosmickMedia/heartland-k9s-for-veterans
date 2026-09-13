<?php
/**
 * Plugin bootstrap.
 *
 * Every module exposes `public static function register(): void` and is booted
 * here in dependency order. Missing classes are skipped so modules can be
 * developed independently.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** Modules in boot order (class => context: 'always' | 'admin' | 'cli'). */
	private const MODULES = [
		'HK9\\Core\\Support\\Helpers'         => 'always',
		'HK9\\Core\\Support\\Updater'         => 'always',
		'HK9\\Core\\Support\\SectionHelpers'  => 'always',
		'HK9\\Core\\Settings\\Store'          => 'always',
		'HK9\\Core\\PostTypes\\Capabilities'  => 'always',
		'HK9\\Core\\PostTypes\\Registrar'     => 'always',
		'HK9\\Core\\Meta\\Registry'           => 'always',
		'HK9\\Core\\Sections\\Registry'       => 'always',
		'HK9\\Core\\Sections\\MetaBox'        => 'always',
		'HK9\\Core\\Redirects\\Resolver'      => 'always',
		'HK9\\Core\\Redirects\\LegacyPaths'   => 'always',
		'HK9\\Core\\PostTypes\\MenuOrder'     => 'always',
		'HK9\\Core\\Privacy\\Registry'        => 'always',
		'HK9\\Core\\Forms\\Handler'           => 'always',
		'HK9\\Core\\Forms\\Submissions'       => 'always',
		'HK9\\Core\\Forms\\GravityProvisioner' => 'always',
		'HK9\\Core\\Events\\Dates'            => 'always',
		'HK9\\Core\\Analytics\\Fathom'        => 'always',
		'HK9\\Core\\Rest\\Pick'               => 'always',
		'HK9\\Core\\Admin\\Menu'              => 'admin',
		'HK9\\Core\\Admin\\Notices'           => 'admin',
		'HK9\\Core\\Admin\\ListTables'        => 'admin',
		'HK9\\Core\\Settings\\Page'           => 'admin',
		'HK9\\Core\\Redirects\\Admin'         => 'admin',
		'HK9\\Core\\Import\\Admin'            => 'admin',
		'HK9\\Core\\Import\\Rest'             => 'always',
		'HK9\\Core\\CLI\\Commands'            => 'cli',
	];

	public static function boot(): void {
		load_plugin_textdomain( 'heartland-k9s-core', false, dirname( plugin_basename( HK9_CORE_FILE ) ) . '/languages' );

		$is_cli = defined( 'WP_CLI' ) && WP_CLI;
		foreach ( self::MODULES as $class => $context ) {
			if ( 'admin' === $context && ! is_admin() && ! $is_cli ) {
				continue;
			}
			if ( 'cli' === $context && ! $is_cli ) {
				continue;
			}
			if ( class_exists( $class ) && method_exists( $class, 'register' ) ) {
				$class::register();
			}
		}

		/**
		 * Fires after all core modules are registered.
		 *
		 * @param string[] $modules Module class names that were booted.
		 */
		do_action( 'hk9/core/booted', array_keys( self::MODULES ) );
	}

	public static function activate(): void {
		// Activation never imports content: schema, capabilities, seed redirects, rewrite flush only.
		foreach ( [ 'HK9\\Core\\Import\\Map', 'HK9\\Core\\PostTypes\\Capabilities', 'HK9\\Core\\Redirects\\Store', 'HK9\\Core\\PostTypes\\Registrar' ] as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'activate' ) ) {
				$class::activate();
			}
		}
		update_option( 'hk9_core_version', HK9_CORE_VERSION, false );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		foreach ( [ 'HK9\\Core\\Forms\\Submissions' ] as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'deactivate' ) ) {
				$class::deactivate();
			}
		}
		flush_rewrite_rules();
	}
}
