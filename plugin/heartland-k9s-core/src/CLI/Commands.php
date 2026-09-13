<?php
/**
 * WP-CLI dispatcher: registers `wp hk9 <sub>` commands from the classes that exist.
 *
 *   wp hk9 redirects list|add|delete|seed|test   (CLI\RedirectsCommand, this module)
 *   wp hk9 import ...                            (CLI\ImportCommand, importer module)
 *   wp hk9 status                                (CLI\StatusCommand, importer module)
 *   wp hk9 rollback / reset-state                (CLI\RollbackCommand / CLI\ResetStateCommand when present)
 *   wp hk9 preflight [<dir>]                     (CLI\PreflightCommand, importer module)
 *
 * Other modules can add entries through the `hk9/cli/commands` filter:
 *   [ 'hk9 something' => ClassOrCallable ].
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

defined( 'ABSPATH' ) || exit;

final class Commands {

	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		$commands = [
			'hk9 redirects'   => RedirectsCommand::class,
			'hk9 import'      => 'HK9\\Core\\CLI\\ImportCommand',
			'hk9 status'      => 'HK9\\Core\\CLI\\StatusCommand',
			'hk9 rollback'    => 'HK9\\Core\\CLI\\RollbackCommand',
			'hk9 reset-state' => 'HK9\\Core\\CLI\\ResetStateCommand',
			'hk9 preflight'   => 'HK9\\Core\\CLI\\PreflightCommand',
		];
		/**
		 * Filters the CLI command map (command name => class name or callable).
		 *
		 * @param array<string, string|callable> $commands Commands.
		 */
		$commands = (array) apply_filters( 'hk9/cli/commands', $commands );

		foreach ( $commands as $name => $handler ) {
			if ( is_string( $handler ) && ! is_callable( $handler ) && ! class_exists( $handler ) ) {
				continue;
			}
			\WP_CLI::add_command( $name, $handler );
		}
	}
}
