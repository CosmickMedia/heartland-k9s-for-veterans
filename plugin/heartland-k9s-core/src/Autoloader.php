<?php
/**
 * Minimal PSR-4 autoloader for the HK9\Core namespace (no Composer at runtime).
 *
 * @package HK9\Core
 */

namespace HK9\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
	public static function register( string $base_dir ): void {
		$base_dir = rtrim( $base_dir, '/\\' ) . '/';
		spl_autoload_register(
			static function ( string $class ) use ( $base_dir ): void {
				$prefix = 'HK9\\Core\\';
				if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
					return;
				}
				$relative = substr( $class, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_file( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
