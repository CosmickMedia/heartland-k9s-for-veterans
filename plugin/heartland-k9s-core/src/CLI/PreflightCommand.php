<?php
/**
 * WP-CLI: wp hk9 preflight [<dir>] [--format=table|json]
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

use HK9\Core\Import\Preflight;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class PreflightCommand {

	/**
	 * Print the pre-flight facts the Setup & Import screen shows before a run:
	 * PHP/WordPress versions, active theme, permalinks, uploads, the payload and
	 * how much of it already exists on this site (what --adopt-existing would take over).
	 *
	 * ## OPTIONS
	 *
	 * [<dir>]
	 * : Payload directory (default: the payload selected in the admin screen / last run).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 preflight /home/site/hk9-payload
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc ): void {
		$dir = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' !== $dir ) {
			$real = realpath( $dir );
			if ( false === $real || ! is_dir( $real ) ) {
				WP_CLI::error( sprintf( 'Directory "%s" does not exist.', $dir ) );
			}
			$dir = $real;
		}
		$report = Preflight::run( '' !== $dir ? $dir : null );

		if ( 'json' === (string) ( $assoc['format'] ?? 'table' ) ) {
			WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT ) );
			WP_CLI::halt( $report['ok'] ? 0 : 1 );
		}

		foreach ( $report['checks'] as $c ) {
			$tag = 'pass' === $c['status'] ? 'PASS' : ( 'warn' === $c['status'] ? 'NOTE' : 'FAIL' );
			WP_CLI::log( sprintf( '  %-4s  %-28s %s', $tag, $c['label'], $c['detail'] ) );
			if ( ! empty( $c['action']['url'] ) ) {
				WP_CLI::log( sprintf( '        -> %s: %s', $c['action']['label'], $c['action']['url'] ) );
			}
		}
		if ( ! empty( $report['existing'] ) && (int) $report['existing']['total'] > 0 ) {
			WP_CLI::log( '' );
			WP_CLI::log( sprintf( 'Existing content: %d page(s), %d legacy BarKode page(s), %d attachment(s) would be adopted. Run: wp hk9 import <dir> --adopt-existing --dry-run --user=<admin>', (int) $report['existing']['pages'], (int) $report['existing']['registry'], (int) $report['existing']['media'] ) );
		}
		if ( $report['ok'] ) {
			WP_CLI::success( 'Pre-flight passed.' );
			return;
		}
		WP_CLI::error( 'Pre-flight failed; fix the FAIL lines before importing.' );
	}
}
