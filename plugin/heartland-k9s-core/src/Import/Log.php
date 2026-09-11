<?php
/**
 * Import log files: uploads/hk9-import/<run>.log inside a protected directory.
 *
 * Sensitive records (registry) are logged as key + hash only; no titles, slugs
 * or field values ever reach the log for them.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Log {

	public const DIR_NAME = 'hk9-import';

	private string $file;
	private string $run_id;

	public function __construct( string $run_id ) {
		$this->run_id = $run_id;
		$this->file   = self::path_for( $run_id );
	}

	public static function dir(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . self::DIR_NAME;
	}

	/**
	 * Create the directory with deny rules (Apache) + index.html; run ids are
	 * unguessable so nginx hosts (which ignore .htaccess) are covered too.
	 */
	public static function ensure_dir(): string {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		self::protect_dir( $dir );
		return $dir;
	}

	public static function protect_dir( string $dir, bool $force = false ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( $force || ! file_exists( $htaccess ) ) {
			$rules = "# Heartland K9s: never serve import payloads or logs.\nOptions -Indexes\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";
			file_put_contents( $htaccess, $rules, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '', LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	public static function path_for( string $run_id ): string {
		return trailingslashit( self::dir() ) . self::sanitize_run_id( $run_id ) . '.log';
	}

	public static function sanitize_run_id( string $run_id ): string {
		return (string) preg_replace( '/[^A-Za-z0-9\-]/', '', $run_id );
	}

	public function file(): string {
		return $this->file;
	}

	public function open( array $header = [] ): void {
		self::ensure_dir();
		$this->write( 'info', 'run', '', 'Run ' . $this->run_id . ' ' . wp_json_encode( $header ) );
	}

	/**
	 * Append one line.
	 */
	public function write( string $level, string $step, string $key, string $message, bool $sensitive = false ): void {
		$who = $key;
		if ( $sensitive && '' !== $key ) {
			$who = 'sensitive#' . Hash::short( $key );
		}
		$line = sprintf(
			"[%s] [%s] [%s] %s%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$step,
			'' !== $who ? $who . ' ' : '',
			$sensitive ? '(details withheld)' : str_replace( [ "\r", "\n" ], ' ', $message )
		);
		file_put_contents( $this->file, $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	public function info( string $step, string $key, string $message, bool $sensitive = false ): void {
		$this->write( 'info', $step, $key, $message, $sensitive );
	}

	public function warn( string $step, string $key, string $message, bool $sensitive = false ): void {
		$this->write( 'warn', $step, $key, $message, $sensitive );
	}

	public function error( string $step, string $key, string $message, bool $sensitive = false ): void {
		$this->write( 'error', $step, $key, $message, $sensitive );
	}
}
