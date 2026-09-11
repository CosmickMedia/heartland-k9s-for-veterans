<?php
/**
 * Import map: the idempotency authority for the payload importer.
 *
 * Custom table `{$wpdb->prefix}hk9_import_map` with a UNIQUE source_key. Every
 * imported (or adopted) object has a row carrying per-field hashes (read back
 * from the database after each write) and pre-images of updated fields per run.
 * Byte-identical payload files share one attachment: its oldest row owns the
 * object, later rows for the same object are secondary (see owner()).
 * Postmeta mirrors (`_hk9_source_key`, `_hk9_import_run`, `_hk9_sha256`) exist
 * for WP_Query/repair only; the table is the source of truth.
 *
 * The run lock is a single atomic UPDATE on a dedicated options row so that two
 * REST ticks (or a REST tick and a CLI process) can never both pass the check.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Map {

	public const DB_VERSION     = '1';
	public const VERSION_OPTION = 'hk9_import_map_version';
	public const LOCK_OPTION    = 'hk9_import_lock';

	public const STATUS_RESERVED = 'reserved';
	public const STATUS_ACTIVE   = 'active';

	private static bool $ensured = false;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'hk9_import_map';
	}

	/**
	 * Activation hook: create/upgrade the table with dbDelta.
	 */
	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// dbDelta rules: one definition per line, lowercase types, indexed varchar <= 191.
		$sql = "CREATE TABLE {$table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	source_key varchar(191) NOT NULL,
	object_type varchar(32) NOT NULL DEFAULT '',
	object_id bigint(20) unsigned NOT NULL DEFAULT 0,
	sha256 char(64) DEFAULT NULL,
	created_by_run varchar(40) DEFAULT NULL,
	last_run varchar(40) DEFAULT NULL,
	payload_hash char(64) DEFAULT NULL,
	field_hashes longtext,
	before_data longtext,
	status varchar(20) NOT NULL DEFAULT 'reserved',
	updated_at datetime DEFAULT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY source_key (source_key),
	KEY sha256 (sha256),
	KEY created_by_run (created_by_run),
	KEY object (object_type,object_id)
) {$collate};";

		dbDelta( $sql );
		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
		self::$ensured = true;
	}

	/**
	 * Lazily create the table when it is missing (e.g. plugin updated in place).
	 */
	public static function ensure(): void {
		global $wpdb;
		if ( self::$ensured ) {
			return;
		}
		$table  = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $exists !== $table || get_option( self::VERSION_OPTION ) !== self::DB_VERSION ) {
			self::activate();
			return;
		}
		self::$ensured = true;
	}

	/* ------------------------------------------------------------------ rows */

	/**
	 * Fetch a row by source key (decoded).
	 */
	public static function get( string $key ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_key = %s", $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::decode( $row ) : null;
	}

	public static function get_by_object( string $type, int $id ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d AND object_id > 0 ORDER BY id ASC LIMIT 1", $type, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Every active row bound to one object, oldest first. Several payload records
	 * may share one attachment (byte-identical files): the first row is its owner.
	 *
	 * @return array[] Decoded rows.
	 */
	public static function rows_for_object( string $type, int $id ): array {
		global $wpdb;
		if ( $id <= 0 ) {
			return [];
		}
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d AND status = %s ORDER BY id ASC", $type, $id, self::STATUS_ACTIVE ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( [ self::class, 'decode' ], $rows ?: [] );
	}

	/**
	 * The row that owns an object: the oldest active row bound to it (the creating
	 * row is always reserved before the object exists, so it is the oldest; for a
	 * pre-existing object it is the first record that adopted it). Other rows bound
	 * to the same object are "secondary": they never write the object's fields.
	 */
	public static function owner( string $type, int $id ): ?array {
		$rows = self::rows_for_object( $type, $id );
		return $rows ? $rows[0] : null;
	}

	/**
	 * Drop every row bound to an object (after the object itself was deleted).
	 *
	 * @return int Rows removed.
	 */
	public static function delete_by_object( string $type, int $id ): int {
		global $wpdb;
		if ( $id <= 0 ) {
			return 0;
		}
		return (int) $wpdb->delete( self::table(), [ 'object_type' => $type, 'object_id' => $id ], [ '%s', '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Active rows carrying a content hash, oldest first (the owner of a shared
	 * attachment comes first).
	 *
	 * @return array[] Decoded rows.
	 */
	public static function rows_by_sha( string $sha ): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE sha256 = %s AND object_id > 0 AND status = %s ORDER BY id ASC", $sha, self::STATUS_ACTIVE ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( [ self::class, 'decode' ], $rows ?: [] );
	}

	/**
	 * Reserve a row BEFORE creating the object so a concurrent tick cannot create it twice.
	 * Returns the (existing or new) row.
	 */
	public static function reserve( string $key, string $type, string $run ): array {
		global $wpdb;
		$table = self::table();
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO {$table} (source_key, object_type, object_id, created_by_run, last_run, status, updated_at) VALUES (%s, %s, 0, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE id = id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$key,
				$type,
				$run,
				$run,
				self::STATUS_RESERVED,
				current_time( 'mysql', true )
			)
		);
		$row = self::get( $key );
		if ( $row && self::STATUS_RESERVED === $row['status'] && $row['created_by_run'] !== $run ) {
			// Stale reservation from an earlier, interrupted run: take it over.
			$wpdb->update( $table, [ 'created_by_run' => $run, 'last_run' => $run ], [ 'source_key' => $key ], [ '%s', '%s' ], [ '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$row['created_by_run'] = $run;
		}
		return $row ?? [];
	}

	/**
	 * Bind an object id to a row and mark it active.
	 *
	 * @param array $data Extra columns: sha256, payload_hash, field_hashes, before_data, created_by_run (null = adopted).
	 */
	public static function bind( string $key, string $type, int $object_id, string $run, array $data = [] ): void {
		global $wpdb;
		$table = self::table();
		$row   = self::get( $key );

		$cols = [
			'object_type' => $type,
			'object_id'   => $object_id,
			'last_run'    => $run,
			'status'      => self::STATUS_ACTIVE,
			'updated_at'  => current_time( 'mysql', true ),
		];
		if ( array_key_exists( 'created_by_run', $data ) ) {
			$cols['created_by_run'] = $data['created_by_run'];
		}
		foreach ( [ 'sha256', 'payload_hash' ] as $c ) {
			if ( array_key_exists( $c, $data ) ) {
				$cols[ $c ] = $data[ $c ];
			}
		}
		if ( isset( $data['field_hashes'] ) ) {
			$cols['field_hashes'] = wp_json_encode( $data['field_hashes'] );
		}
		if ( isset( $data['before_data'] ) ) {
			$cols['before_data'] = wp_json_encode( $data['before_data'] );
		}

		if ( $row ) {
			if ( ! array_key_exists( 'created_by_run', $cols ) && Map::STATUS_RESERVED === $row['status'] ) {
				$cols['created_by_run'] = $run; // Reservation turned into a real creation.
			}
			$wpdb->update( $table, $cols, [ 'source_key' => $key ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$cols['source_key'] = $key;
			if ( ! array_key_exists( 'created_by_run', $cols ) ) {
				$cols['created_by_run'] = $run;
			}
			// An explicit null (adopted object / pre-existing setting) must stay null: never rolled back by deletion.
			$wpdb->insert( $table, $cols ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Merge new field hashes / before-images into a row.
	 *
	 * @param array $field_hashes field => ['db' => sha, 'src' => sha]
	 * @param array $before       field => pre-image (stored under the run id; first write wins)
	 */
	public static function record( string $key, string $run, array $field_hashes, array $before = [], ?string $payload_hash = null ): void {
		global $wpdb;
		$row = self::get( $key );
		if ( ! $row ) {
			return;
		}
		$hashes = array_merge( $row['field_hashes'], $field_hashes );
		$pre    = $row['before_data'];
		if ( $before && $row['created_by_run'] !== $run ) {
			$pre[ $run ] = $pre[ $run ] ?? [];
			foreach ( $before as $f => $v ) {
				if ( ! array_key_exists( $f, $pre[ $run ] ) ) {
					$pre[ $run ][ $f ] = $v;
				}
			}
		}
		$cols = [
			'field_hashes' => wp_json_encode( $hashes ),
			'before_data'  => wp_json_encode( $pre ),
			'last_run'     => $run,
			'updated_at'   => current_time( 'mysql', true ),
		];
		if ( null !== $payload_hash ) {
			$cols['payload_hash'] = $payload_hash;
		}
		$wpdb->update( self::table(), $cols, [ 'source_key' => $key ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Replace the stored hashes/before-data wholesale (used by rollback).
	 */
	public static function overwrite( string $key, array $field_hashes, array $before_data ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			[
				'field_hashes' => wp_json_encode( $field_hashes ),
				'before_data'  => wp_json_encode( $before_data ),
				'updated_at'   => current_time( 'mysql', true ),
			],
			[ 'source_key' => $key ]
		);
	}

	public static function delete( string $key ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'source_key' => $key ], [ '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Rows created by a run OR carrying a pre-image for it.
	 */
	public static function rows_for_run( string $run ): array {
		global $wpdb;
		$table = self::table();
		$like  = '%' . $wpdb->esc_like( '"' . $run . '":' ) . '%';
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE created_by_run = %s OR before_data LIKE %s ORDER BY id ASC", $run, $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( [ self::class, 'decode' ], $rows ?: [] );
	}

	public static function all_keys(): array {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_col( "SELECT source_key FROM {$table} WHERE status = 'active'" ) ?: []; // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function counts(): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT object_type, status, COUNT(*) AS n FROM {$table} GROUP BY object_type, status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = [];
		foreach ( $rows ?: [] as $r ) {
			$out[ $r['object_type'] . ( self::STATUS_ACTIVE === $r['status'] ? '' : ':' . $r['status'] ) ] = (int) $r['n'];
		}
		ksort( $out );
		return $out;
	}

	public static function truncate(): void {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Locate an object that was created but whose map row never got bound (crash window).
	 */
	public static function find_orphan_post( string $key ): int {
		$types = array_values( get_post_types( [], 'names' ) );
		if ( ! in_array( 'attachment', $types, true ) ) {
			$types[] = 'attachment';
		}
		$ids = get_posts(
			[
				'post_type'        => $types,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => '_hk9_source_key', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => true,
				'no_found_rows'    => true,
			]
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Find a pre-existing attachment by content hash (mirror meta written by validate/prehash).
	 */
	public static function find_attachment_by_sha( string $sha ): int {
		$ids = get_posts(
			[
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => '_hk9_sha256', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $sha, // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => true,
				'no_found_rows'    => true,
			]
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private static function decode( array $row ): array {
		$row['object_id']    = (int) $row['object_id'];
		$row['id']           = (int) $row['id'];
		$row['field_hashes'] = $row['field_hashes'] ? ( json_decode( (string) $row['field_hashes'], true ) ?: [] ) : [];
		$row['before_data']  = $row['before_data'] ? ( json_decode( (string) $row['before_data'], true ) ?: [] ) : [];
		return $row;
	}

	/* ------------------------------------------------------------------ lock */

	/**
	 * Acquire the run lock atomically. Returns true when this token now holds it.
	 */
	public static function acquire_lock( string $token, int $ttl ): bool {
		global $wpdb;
		add_option( self::LOCK_OPTION, '', '', false );
		$now = time();
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND ( option_value = '' OR CAST(SUBSTRING_INDEX(option_value, '|', -1) AS UNSIGNED) < %d )",
			$token . '|' . ( $now + $ttl ),
			self::LOCK_OPTION,
			$now
		);
		$affected = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( self::LOCK_OPTION, 'options' );
		return 1 === (int) $affected;
	}

	public static function refresh_lock( string $token, int $ttl ): bool {
		global $wpdb;
		$affected = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value LIKE %s",
				$token . '|' . ( time() + $ttl ),
				self::LOCK_OPTION,
				$wpdb->esc_like( $token . '|' ) . '%'
			)
		);
		wp_cache_delete( self::LOCK_OPTION, 'options' );
		return 1 === (int) $affected;
	}

	public static function release_lock( string $token ): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = '' WHERE option_name = %s AND option_value LIKE %s",
				self::LOCK_OPTION,
				$wpdb->esc_like( $token . '|' ) . '%'
			)
		);
		wp_cache_delete( self::LOCK_OPTION, 'options' );
	}

	/**
	 * Force-clear the lock (reset-state).
	 */
	public static function clear_lock(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = '' WHERE option_name = %s", self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( self::LOCK_OPTION, 'options' );
	}

	/**
	 * @return array{token:string,expires:int}|null
	 */
	public static function lock_info(): ?array {
		global $wpdb;
		$value = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( '' === $value || ! str_contains( $value, '|' ) ) {
			return null;
		}
		[ $token, $expires ] = explode( '|', $value, 2 );
		if ( (int) $expires < time() ) {
			return null;
		}
		return [
			'token'   => $token,
			'expires' => (int) $expires,
		];
	}
}
