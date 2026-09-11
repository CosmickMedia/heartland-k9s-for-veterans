<?php
/**
 * WP-CLI: wp hk9 reset-state --yes
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

defined( 'ABSPATH' ) || exit;

final class ResetStateCommand {

	/**
	 * Clear the import state option and lock (map table and run history are kept).
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc ): void {
		ImportCommand::reset_state( $args, $assoc );
	}
}
