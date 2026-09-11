<?php
/**
 * WP-CLI: wp hk9 rollback --run=<id> [--force] [--yes] [--dry-run]
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

defined( 'ABSPATH' ) || exit;

final class RollbackCommand {

	/**
	 * Roll back one import run (deletes what it created, restores what it changed).
	 *
	 * ## OPTIONS
	 *
	 * --run=<id>
	 * : Run id (see wp hk9 status).
	 *
	 * [--force]
	 * : Also remove/restore records edited since the import.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--dry-run]
	 * : Report what would happen without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 rollback --run=20260911-101500-ab12cd34 --yes --user=admin
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc ): void {
		ImportCommand::rollback( $args, $assoc );
	}
}
