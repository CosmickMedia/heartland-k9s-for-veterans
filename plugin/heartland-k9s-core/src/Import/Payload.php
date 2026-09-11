<?php
/**
 * Payload directories: containment, allowed roots, ZIP upload/unpack, cleanup.
 *
 * Uploaded payloads live in uploads/hk9-payload-<random>/ (deny rules +
 * index.html; the random segment keeps them unguessable on hosts that ignore
 * .htaccess) and are deleted on a clean finalize. Server paths are accepted
 * only inside an allow-list of roots and always resolved through realpath().
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Payload {

	public const UPLOAD_PREFIX = 'hk9-payload-';

	/**
	 * Resolve a request-supplied relative path inside a root (works for
	 * not-yet-existing destinations too). Null when it escapes the root.
	 */
	public static function resolve_within( string $root, string $relative ): ?string {
		if ( '' === $relative || str_contains( $relative, "\0" ) ) {
			return null;
		}
		if ( str_starts_with( $relative, '/' ) || str_starts_with( $relative, '\\' ) ) {
			return null;
		}
		if ( preg_match( '#(^|[/\\\\])\.\.([/\\\\]|$)#', $relative ) ) {
			return null; // Payload-relative references never climb.
		}
		$real_root = realpath( $root );
		if ( false === $real_root ) {
			return null;
		}
		$target = $root . DIRECTORY_SEPARATOR . $relative;
		$real   = realpath( $target );
		if ( false === $real ) {
			// Missing file (or missing directories): resolve the nearest existing ancestor
			// and re-append the remainder, so a merely absent file is still contained
			// (only real traversal escapes the root).
			$tail   = [];
			$parent = $target;
			do {
				$tail[]   = basename( $parent );
				$parent   = dirname( $parent );
				$ancestor = realpath( $parent );
			} while ( false === $ancestor && '' !== $parent && dirname( $parent ) !== $parent );
			if ( false === $ancestor ) {
				return null;
			}
			$real = $ancestor . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, array_reverse( $tail ) );
		}
		if ( $real !== $real_root && ! str_starts_with( $real, $real_root . DIRECTORY_SEPARATOR ) ) {
			return null;
		}
		return $real;
	}

	/**
	 * Roots a payload directory may live under (REST/admin only; CLI is trusted).
	 *
	 * @return string[] realpath'd roots
	 */
	public static function allowed_roots(): array {
		$uploads = wp_upload_dir( null, false );
		$roots   = [ $uploads['basedir'] ];
		if ( defined( 'HK9_LOCAL_DEV' ) && HK9_LOCAL_DEV ) {
			$roots[] = WP_CONTENT_DIR . '/hk9-payload';
			$roots[] = HK9_CORE_DIR . 'tests';
		}
		/**
		 * Filters the directories an admin may point the importer at.
		 *
		 * @param string[] $roots Absolute directories.
		 */
		$roots = (array) apply_filters( 'hk9/import/allowed_payload_roots', $roots );
		$out   = [];
		foreach ( $roots as $r ) {
			$real = realpath( (string) $r );
			if ( false !== $real ) {
				$out[] = $real;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Suggested server paths shown in the admin screen when HK9_LOCAL_DEV is on.
	 *
	 * @return string[]
	 */
	public static function dev_paths(): array {
		if ( ! defined( 'HK9_LOCAL_DEV' ) || ! HK9_LOCAL_DEV ) {
			return [];
		}
		$paths = [];
		foreach ( [ WP_CONTENT_DIR . '/hk9-payload', HK9_CORE_DIR . 'tests/payload-mini' ] as $p ) {
			if ( is_file( $p . '/manifest.json' ) ) {
				$paths[] = $p;
			}
		}
		return $paths;
	}

	/**
	 * Validate an admin-supplied absolute payload path against the allow-list.
	 * Uploaded payload dirs (uploads/hk9-payload-*) are always allowed.
	 */
	public static function validate_admin_path( string $path ): string|WP_Error {
		$path = trim( $path );
		if ( '' === $path || str_contains( $path, "\0" ) ) {
			return new WP_Error( 'hk9_payload_path', __( 'Payload path is empty.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		$real = realpath( $path );
		if ( false === $real || ! is_dir( $real ) ) {
			return new WP_Error( 'hk9_payload_path', __( 'Payload directory does not exist.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		$uploads      = realpath( wp_upload_dir( null, false )['basedir'] );
		$in_uploads   = false !== $uploads && str_starts_with( $real, $uploads . DIRECTORY_SEPARATOR . self::UPLOAD_PREFIX );
		$allowed      = $in_uploads;
		if ( ! $allowed ) {
			foreach ( self::allowed_roots() as $root ) {
				if ( $real === $root || str_starts_with( $real, $root . DIRECTORY_SEPARATOR ) ) {
					// The uploads root itself only permits hk9-payload-* children.
					if ( $root === $uploads ) {
						continue;
					}
					$allowed = true;
					break;
				}
			}
		}
		if ( ! $allowed ) {
			return new WP_Error( 'hk9_payload_path', __( 'That directory is outside the allowed payload locations.', 'heartland-k9s-core' ), [ 'status' => 403 ] );
		}
		if ( ! is_file( $real . '/manifest.json' ) ) {
			return new WP_Error( 'hk9_payload_path', __( 'No manifest.json found in that directory.', 'heartland-k9s-core' ), [ 'status' => 400 ] );
		}
		return $real;
	}

	/**
	 * Currently selected payload (set by upload or by a run).
	 *
	 * @return array{dir:string,source:string,at:string}|null
	 */
	public static function current(): ?array {
		$v = get_option( State::PAYLOAD_OPTION, null );
		if ( ! is_array( $v ) || empty( $v['dir'] ) ) {
			return null;
		}
		if ( ! is_file( trailingslashit( $v['dir'] ) . 'manifest.json' ) ) {
			return null;
		}
		return $v;
	}

	public static function set_current( string $dir, string $source ): void {
		update_option(
			State::PAYLOAD_OPTION,
			[
				'dir'    => $dir,
				'source' => $source,
				'at'     => gmdate( 'c' ),
			],
			false
		);
	}

	public static function is_uploaded_dir( string $dir ): bool {
		$uploads = realpath( wp_upload_dir( null, false )['basedir'] );
		$real    = realpath( $dir );
		return false !== $uploads && false !== $real && str_starts_with( $real, $uploads . DIRECTORY_SEPARATOR . self::UPLOAD_PREFIX );
	}

	/**
	 * Unpack an uploaded ZIP ($_FILES entry) into a fresh protected directory.
	 *
	 * @param array $file $_FILES['payload']
	 * @return string|WP_Error Absolute directory containing manifest.json.
	 */
	public static function unpack_upload( array $file ): string|WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'hk9_upload', __( 'Upload failed (no file received). If the ZIP is large, ask your host to raise upload_max_filesize/post_max_size or use the CLI.', 'heartland-k9s-core' ) );
		}
		$name = sanitize_file_name( (string) ( $file['name'] ?? 'payload.zip' ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'zip' !== $ext ) {
			return new WP_Error( 'hk9_upload', __( 'Only .zip payload archives are accepted.', 'heartland-k9s-core' ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, [ 'zip' => 'application/zip' ] );
		if ( 'zip' !== $check['ext'] || ! in_array( $check['type'], [ 'application/zip', 'application/x-zip-compressed', 'application/octet-stream' ], true ) ) {
			return new WP_Error( 'hk9_upload', __( 'The uploaded file is not a ZIP archive.', 'heartland-k9s-core' ) );
		}

		// Refuse the archive outright when it carries anything that must never land under
		// uploads/ (dotfiles such as .htaccess/.user.ini, script-bearing or HTML files outside
		// content/, traversal) — before a single byte is extracted.
		$refused = self::scan_archive( $file['tmp_name'] );
		if ( is_wp_error( $refused ) ) {
			return $refused;
		}

		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'hk9_upload', (string) $uploads['error'] );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . self::UPLOAD_PREFIX . strtolower( wp_generate_password( 32, false, false ) );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'hk9_upload', __( 'Could not create the payload directory in uploads.', 'heartland-k9s-core' ) );
		}
		Log::protect_dir( $dir );

		WP_Filesystem();
		$result = unzip_file( $file['tmp_name'], $dir );
		if ( is_wp_error( $result ) ) {
			self::remove_dir( $dir );
			return $result;
		}

		// Accept manifest at the root or inside a single top-level folder.
		$manifest_dir = null;
		if ( is_file( $dir . '/manifest.json' ) ) {
			$manifest_dir = $dir;
		} else {
			$children = array_values( array_filter( glob( $dir . '/*' ) ?: [], 'is_dir' ) );
			foreach ( $children as $child ) {
				if ( is_file( $child . '/manifest.json' ) ) {
					$manifest_dir = $child;
					break;
				}
			}
		}
		if ( null === $manifest_dir ) {
			self::remove_dir( $dir );
			return new WP_Error( 'hk9_upload', __( 'The archive does not contain a manifest.json (at the root or in one top-level folder).', 'heartland-k9s-core' ) );
		}
		self::purge_scripts( $dir );
		Log::protect_dir( $dir, true );
		if ( $manifest_dir !== $dir ) {
			Log::protect_dir( $manifest_dir, true );
		}

		// Retire earlier uploads so stale copies never linger in uploads/.
		self::remove_other_uploads( $dir );

		return $manifest_dir;
	}

	/**
	 * Delete every uploads/hk9-payload-* directory except $keep.
	 */
	public static function remove_other_uploads( string $keep = '' ): void {
		$uploads = wp_upload_dir( null, false );
		foreach ( glob( trailingslashit( $uploads['basedir'] ) . self::UPLOAD_PREFIX . '*', GLOB_ONLYDIR ) ?: [] as $d ) {
			if ( '' !== $keep && realpath( $d ) === realpath( $keep ) ) {
				continue;
			}
			if ( self::is_uploaded_dir( $d ) ) {
				self::remove_dir( $d );
			}
		}
	}

	/**
	 * Delete an uploaded payload directory (refuses anything outside uploads/hk9-payload-*).
	 */
	public static function remove_uploaded( string $dir ): bool {
		// A manifest inside a top-level folder: delete the hk9-payload-* ancestor.
		$uploads = realpath( wp_upload_dir( null, false )['basedir'] );
		$real    = realpath( $dir );
		if ( false === $uploads || false === $real ) {
			return false;
		}
		$rel = substr( $real, strlen( $uploads ) + 1 );
		$top = explode( DIRECTORY_SEPARATOR, $rel )[0] ?? '';
		if ( ! str_starts_with( $top, self::UPLOAD_PREFIX ) ) {
			return false;
		}
		return self::remove_dir( $uploads . DIRECTORY_SEPARATOR . $top );
	}

	/**
	 * Extensions never needed by a payload and never allowed under uploads/. HTML is
	 * included: block markup is only accepted as `content/<name>.html` (at the payload
	 * root or inside the single top-level folder), see refused_entry().
	 */
	private const SCRIPT_EXTENSIONS = [ 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps', 'phar', 'pht', 'cgi', 'pl', 'py', 'sh', 'js', 'mjs', 'svg', 'svgz', 'shtml', 'htm', 'html', 'xhtml', 'xht' ];

	/** Directory (relative to the payload root) that may hold `.html` block-markup files. */
	private const CONTENT_DIR = 'content';

	/**
	 * Why a payload entry (path relative to the payload root, `/` separated) is refused:
	 * 'dotfile' (any hidden file or directory: .htaccess, .user.ini, .git/…), 'script'
	 * (any extension segment in SCRIPT_EXTENSIONS; `.html` only passes as
	 * content/<name>.html or <folder>/content/<name>.html), 'traversal' (`..` or an
	 * absolute path) or 'nul'. '' when the entry is acceptable.
	 */
	public static function refused_entry( string $relative ): string {
		if ( str_contains( $relative, "\0" ) ) {
			return 'nul';
		}
		$relative = str_replace( '\\', '/', $relative );
		if ( str_starts_with( $relative, '/' ) || preg_match( '#^[A-Za-z]:/#', $relative ) ) {
			return 'traversal';
		}
		$segments = array_values( array_filter( explode( '/', $relative ), static fn( string $s ): bool => '' !== $s ) );
		if ( [] === $segments ) {
			return '';
		}
		foreach ( $segments as $segment ) {
			if ( '..' === $segment ) {
				return 'traversal';
			}
			if ( str_starts_with( $segment, '.' ) ) {
				return 'dotfile';
			}
		}
		if ( str_ends_with( $relative, '/' ) ) {
			return ''; // Directory entry.
		}
		$name  = array_pop( $segments );
		$parts = explode( '.', strtolower( $name ) );
		array_shift( $parts ); // Every extension segment counts (x.php.jpg is refused too).
		if ( [] === $parts ) {
			return '';
		}
		$depth      = count( $segments );
		$in_content = ( 1 === $depth && self::CONTENT_DIR === $segments[0] ) || ( 2 === $depth && self::CONTENT_DIR === $segments[1] );
		$last       = array_key_last( $parts );
		foreach ( $parts as $i => $ext ) {
			if ( 'html' === $ext && $in_content && $i === $last ) {
				continue; // Block markup file of the payload format (content/<name>.html).
			}
			if ( in_array( $ext, self::SCRIPT_EXTENSIONS, true ) ) {
				return 'script';
			}
		}
		return '';
	}

	/**
	 * Pre-scan an archive's entry list (ZipArchive, PclZip fallback) and refuse the whole
	 * upload on the first entry refused_entry() rejects. `__MACOSX/` entries are ignored
	 * (unzip_file() never extracts them).
	 *
	 * @return true|WP_Error
	 */
	public static function scan_archive( string $zip_path ): true|WP_Error {
		$names = self::archive_entries( $zip_path );
		if ( is_wp_error( $names ) ) {
			return $names;
		}
		foreach ( $names as $name ) {
			if ( str_starts_with( $name, '__MACOSX/' ) ) {
				continue;
			}
			$why = self::refused_entry( $name );
			if ( '' === $why ) {
				continue;
			}
			$label = 'dotfile' === $why
				? __( 'hidden files are not allowed', 'heartland-k9s-core' )
				: ( 'script' === $why
					? __( 'script or HTML files are not allowed (block markup belongs in content/*.html)', 'heartland-k9s-core' )
					: __( 'the path is invalid', 'heartland-k9s-core' ) );
			return new WP_Error(
				'hk9_upload_refused',
				sprintf(
					/* translators: 1: archive entry, 2: reason */
					__( 'The archive was refused: entry "%1$s" — %2$s. Remove it from the ZIP and upload again.', 'heartland-k9s-core' ),
					sanitize_text_field( substr( $name, 0, 120 ) ),
					$label
				)
			);
		}
		return true;
	}

	/**
	 * Entry names of a ZIP without extracting anything.
	 *
	 * @return string[]|WP_Error
	 */
	private static function archive_entries( string $zip_path ): array|WP_Error {
		if ( class_exists( 'ZipArchive', false ) ) {
			$zip    = new \ZipArchive();
			$opened = $zip->open( $zip_path, \ZipArchive::CHECKCONS );
			if ( true !== $opened ) {
				return new WP_Error( 'hk9_upload', __( 'The uploaded file is not a readable ZIP archive.', 'heartland-k9s-core' ) );
			}
			$names = [];
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = $zip->getNameIndex( $i, \ZipArchive::FL_UNCHANGED );
				if ( is_string( $name ) ) {
					$names[] = $name;
				}
			}
			$zip->close();
			return $names;
		}
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		$archive = new \PclZip( $zip_path );
		$list    = $archive->listContent();
		if ( ! is_array( $list ) ) {
			return new WP_Error( 'hk9_upload', __( 'The uploaded file is not a readable ZIP archive.', 'heartland-k9s-core' ) );
		}
		$names = [];
		foreach ( $list as $entry ) {
			if ( is_array( $entry ) && isset( $entry['filename'] ) ) {
				$names[] = (string) $entry['filename'] . ( ! empty( $entry['folder'] ) && ! str_ends_with( (string) $entry['filename'], '/' ) ? '/' : '' );
			}
		}
		return $names;
	}

	/**
	 * A payload is JSON + block HTML + media: delete anything the archive scan would
	 * have refused (defence in depth after extraction — dotfiles, script-bearing or
	 * stray HTML files), so nothing executable ever sits under uploads/.
	 */
	public static function purge_scripts( string $dir ): int {
		$removed = 0;
		$real    = realpath( $dir );
		if ( false === $real ) {
			return 0;
		}
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $real, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $file ) {
			/** @var \SplFileInfo $file */
			if ( ! $file->isFile() ) {
				continue;
			}
			$relative = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $real ) ) ), '/' );
			if ( '' !== self::refused_entry( $relative ) ) {
				wp_delete_file( $file->getPathname() );
				++$removed;
			}
		}
		return $removed;
	}

	private static function remove_dir( string $dir ): bool {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->is_dir( $dir ) ) {
			return (bool) $wp_filesystem->delete( $dir, true );
		}
		return false;
	}

	/**
	 * Human-readable summary of a directory for the admin screen.
	 */
	public static function describe( string $dir ): array {
		$manifest = Manifest::load( $dir );
		if ( is_wp_error( $manifest ) ) {
			return [
				'dir'   => $dir,
				'ok'    => false,
				'error' => $manifest->get_error_message(),
			];
		}
		$bytes = 0;
		foreach ( $manifest->keys( 'attachment' ) as $k ) {
			$bytes += (int) ( $manifest->get( $k )['size'] ?? 0 );
		}
		return [
			'dir'          => $dir,
			'ok'           => true,
			'generated_at' => $manifest->generated_at(),
			'records'      => count( $manifest->records() ),
			'attachments'  => $manifest->count( 'attachment' ),
			'posts'        => $manifest->count( Manifest::POST_TYPES_KEY ),
			'bytes'        => $bytes,
			'uploaded'     => self::is_uploaded_dir( $dir ),
		];
	}
}
