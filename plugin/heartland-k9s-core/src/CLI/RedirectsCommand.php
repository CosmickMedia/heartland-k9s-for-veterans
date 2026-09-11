<?php
/**
 * `wp hk9 redirects` — manage legacy redirects.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\CLI;

use HK9\Core\Redirects\Resolver;
use HK9\Core\Redirects\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Manage legacy URL redirects.
 */
final class RedirectsCommand {

	/**
	 * List redirect rules.
	 *
	 * ## OPTIONS
	 *
	 * [--enabled]
	 * : Only enabled rules.
	 *
	 * [--seed]
	 * : Only seeded (migration) rules.
	 *
	 * [--format=<format>]
	 * : table, json, csv, yaml, count or ids.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 redirects list
	 *     wp hk9 redirects list --format=json
	 *
	 * @subcommand list
	 */
	public function list_( array $args, array $assoc ): void {
		$rows = [];
		foreach ( Store::all() as $key => $rule ) {
			if ( isset( $assoc['enabled'] ) && ! $rule['enabled'] ) {
				continue;
			}
			if ( isset( $assoc['seed'] ) && ! $rule['seed'] ) {
				continue;
			}
			$rows[] = [
				'source'   => $key,
				'to'       => Store::describe_target( $rule['to'] ),
				'resolves' => 410 === $rule['status'] ? '(gone)' : (string) ( Store::resolve_target( $rule['to'] ) ?? '' ),
				'status'   => $rule['status'],
				'enabled'  => $rule['enabled'] ? 'yes' : 'no',
				'seed'     => $rule['seed'] ? 'yes' : 'no',
				'note'     => $rule['note'],
			];
		}
		$format = $assoc['format'] ?? 'table';
		if ( 'ids' === $format ) {
			\WP_CLI::line( implode( ' ', array_column( $rows, 'source' ) ) );
			return;
		}
		if ( 'count' === $format ) {
			\WP_CLI::line( (string) count( $rows ) );
			return;
		}
		\WP_CLI\Utils\format_items( $format, $rows, [ 'source', 'to', 'resolves', 'status', 'enabled', 'seed', 'note' ] );
	}

	/**
	 * Add or update a redirect.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Source path, e.g. /old-page/ or "/?foogallery=2468".
	 *
	 * <target>
	 * : Destination: a path (/stories/), an absolute URL, post:<id> or record:<slug>.
	 *
	 * [--status=<status>]
	 * : 301, 302 or 410.
	 * ---
	 * default: 301
	 * ---
	 *
	 * [--note=<note>]
	 * : Free-text note.
	 *
	 * [--disabled]
	 * : Save the rule disabled.
	 *
	 * [--force]
	 * : Keep the rule even when the source currently resolves to published content.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 redirects add /old/ /stories/
	 *     wp hk9 redirects add /hk923-005/ record:hk923-005
	 *     wp hk9 redirects add /about-us/ post:12 --note="Renamed page"
	 */
	public function add( array $args, array $assoc ): void {
		[ $source, $target ] = $args;
		$to = self::parse_target( $target );
		$result = Store::upsert(
			$source,
			$to,
			(int) ( $assoc['status'] ?? 301 ),
			(string) ( $assoc['note'] ?? '' ),
			! isset( $assoc['disabled'] ),
			isset( $assoc['force'] )
		);
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		foreach ( $result['warnings'] as $warning ) {
			\WP_CLI::warning( $warning );
		}
		\WP_CLI::success( sprintf( 'Saved %s → %s', $result['key'], Store::describe_target( $to ) ) );
	}

	/**
	 * Delete a redirect.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Source path.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--force]
	 * : Also delete a seeded rule (normally disable-only).
	 */
	public function delete( array $args, array $assoc ): void {
		$key  = Store::normalize_key( $args[0] );
		$rule = Store::get( $key );
		if ( ! $rule ) {
			\WP_CLI::error( sprintf( 'No rule for %s.', $key ) );
		}
		if ( $rule['seed'] && ! isset( $assoc['force'] ) ) {
			\WP_CLI::error( 'Seeded rules are disable-only; pass --force to delete anyway (wp hk9 redirects seed restores it).' );
		}
		\WP_CLI::confirm( sprintf( 'Delete redirect %s?', $key ), $assoc );
		Store::delete( $key );
		\WP_CLI::success( sprintf( 'Deleted %s.', $key ) );
	}

	/**
	 * Enable or disable a redirect.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Source path.
	 *
	 * <state>
	 * : on or off.
	 * ---
	 * options:
	 *   - on
	 *   - off
	 * ---
	 */
	public function toggle( array $args, array $assoc ): void {
		$key = Store::normalize_key( $args[0] );
		if ( ! Store::set_enabled( $key, 'on' === $args[1] ) ) {
			\WP_CLI::error( sprintf( 'No rule for %s.', $key ) );
		}
		\WP_CLI::success( sprintf( '%s is now %s.', $key, 'on' === $args[1] ? 'enabled' : 'disabled' ) );
	}

	/**
	 * Install the seeded migration redirects (never overwrites edited rules unless --overwrite).
	 *
	 * ## OPTIONS
	 *
	 * [--overwrite]
	 * : Reset seeded rules to their defaults.
	 */
	public function seed( array $args, array $assoc ): void {
		$count = Store::seed( isset( $assoc['overwrite'] ) );
		\WP_CLI::success( sprintf( '%d seed rule(s) written (%d total rules).', $count, count( Store::all() ) ) );
	}

	/**
	 * Test redirects: offline resolution by default, real HTTP with --http.
	 *
	 * ## OPTIONS
	 *
	 * [<source>...]
	 * : Sources to test. Defaults to every enabled rule.
	 *
	 * [--http]
	 * : Send a HEAD request to the site and compare status + Location.
	 *
	 * [--all]
	 * : Include disabled rules.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp hk9 redirects test
	 *     wp hk9 redirects test /hk923-005/ --http
	 */
	public function test( array $args, array $assoc ): void {
		$http    = isset( $assoc['http'] );
		$sources = $args;
		if ( ! $sources ) {
			foreach ( Store::all() as $key => $rule ) {
				if ( $rule['enabled'] || isset( $assoc['all'] ) ) {
					$sources[] = $key;
				}
			}
		}
		$rows   = [];
		$failed = 0;
		foreach ( $sources as $source ) {
			$result = Resolver::test( $source, $http );
			if ( ! $result['ok'] ) {
				++$failed;
			}
			$rows[] = [
				'source'   => $result['key'],
				'result'   => $result['ok'] ? 'PASS' : 'FAIL',
				'expected' => $result['expected_status'] > 0 ? $result['expected_status'] . ' ' . (string) $result['expected'] : '',
				'actual'   => null !== $result['actual_status'] ? $result['actual_status'] . ' ' . (string) $result['actual_location'] : ( $http ? '' : 'offline' ),
				'message'  => $result['message'],
			];
		}
		\WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, [ 'source', 'result', 'expected', 'actual', 'message' ] );
		if ( $failed > 0 ) {
			\WP_CLI::error( sprintf( '%d of %d redirect(s) failed.', $failed, count( $rows ) ) );
		}
		\WP_CLI::success( sprintf( '%d redirect(s) passed%s.', count( $rows ), $http ? ' (HTTP)' : ' (offline)' ) );
	}

	/**
	 * Parse a CLI target spec into a target array.
	 */
	private static function parse_target( string $target ): array {
		if ( preg_match( '/^post:(\d+)$/', $target, $m ) ) {
			return [
				'type' => 'post',
				'id'   => (int) $m[1],
			];
		}
		if ( preg_match( '/^record:(.+)$/', $target, $m ) ) {
			return [
				'type' => 'record',
				'slug' => $m[1],
			];
		}
		return [
			'type' => 'path',
			'path' => $target,
		];
	}
}
