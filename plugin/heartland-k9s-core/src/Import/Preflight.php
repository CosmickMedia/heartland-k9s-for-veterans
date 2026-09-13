<?php
/**
 * Pre-flight facts shown before an import (admin panel, REST, `wp hk9 preflight`):
 * PHP / WordPress versions, active theme, permalinks, uploads, the legacy
 * page-builder plugins that should be inactive before the run, the selected
 * payload, and — computed cheaply from the manifest — how much of the payload
 * already exists on this site (pages / registry pages by live id or slug,
 * attachments by live id + file name — the very rules the run applies), which
 * is what "Existing site: adopt matching content" would take over.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import;

defined( 'ABSPATH' ) || exit;

final class Preflight {

	public const MIN_PHP = '8.1';
	public const MIN_WP  = '6.4';

	/**
	 * @param string|null $dir Payload directory (null = the currently selected payload).
	 * @return array{ok:bool,checks:array<int,array{id:string,label:string,status:string,detail:string,action?:array{label:string,url:string}}>,payload:?array,existing:?array}
	 */
	public static function run( ?string $dir = null ): array {
		if ( null === $dir || '' === $dir ) {
			$current = Payload::current();
			$dir     = $current ? (string) $current['dir'] : '';
		}
		$manifest = '' !== $dir ? Manifest::load( $dir ) : null;
		$expected = $manifest instanceof Manifest ? $manifest->requires_theme() : (string) apply_filters( 'hk9/import/expected_stylesheet', 'heartland-k9s' );

		$checks = [];

		$php_ok   = version_compare( PHP_VERSION, self::MIN_PHP, '>=' );
		$checks[] = [
			'id'     => 'php',
			'label'  => __( 'PHP version', 'heartland-k9s-core' ),
			'status' => $php_ok ? 'pass' : 'fail',
			/* translators: 1: running version, 2: minimum version */
			'detail' => sprintf( __( '%1$s (minimum %2$s)', 'heartland-k9s-core' ), PHP_VERSION, self::MIN_PHP ),
		];

		$wp_ok    = version_compare( get_bloginfo( 'version' ), self::MIN_WP, '>=' );
		$checks[] = [
			'id'     => 'wordpress',
			'label'  => __( 'WordPress version', 'heartland-k9s-core' ),
			'status' => $wp_ok ? 'pass' : 'fail',
			/* translators: 1: running version, 2: minimum version */
			'detail' => sprintf( __( '%1$s (minimum %2$s)', 'heartland-k9s-core' ), get_bloginfo( 'version' ), self::MIN_WP ),
		];

		$theme_ok = '' === $expected || get_stylesheet() === $expected;
		$theme    = [
			'id'     => 'theme',
			'label'  => __( 'Active theme', 'heartland-k9s-core' ),
			'status' => $theme_ok ? 'pass' : 'fail',
			'detail' => $theme_ok
				? sprintf( '%s (%s)', wp_get_theme()->get( 'Name' ), get_stylesheet() )
				/* translators: 1: active stylesheet, 2: expected stylesheet */
				: sprintf( __( '"%1$s" is active but the payload targets "%2$s". Activate the Heartland theme first (the importer refuses to run otherwise).', 'heartland-k9s-core' ), get_stylesheet(), $expected ),
		];
		if ( ! $theme_ok ) {
			$theme['action'] = [
				'label' => __( 'Appearance → Themes', 'heartland-k9s-core' ),
				'url'   => admin_url( 'themes.php' ),
			];
		}
		$checks[] = $theme;

		$permalinks = (string) get_option( 'permalink_structure' );
		$perma      = [
			'id'     => 'permalinks',
			'label'  => __( 'Pretty permalinks', 'heartland-k9s-core' ),
			'status' => '' !== $permalinks ? 'pass' : 'fail',
			'detail' => '' !== $permalinks ? $permalinks : __( 'Permalinks are set to "Plain"; choose "Post name" under Settings → Permalinks.', 'heartland-k9s-core' ),
		];
		if ( '' === $permalinks ) {
			$perma['action'] = [
				'label' => __( 'Settings → Permalinks', 'heartland-k9s-core' ),
				'url'   => admin_url( 'options-permalink.php' ),
			];
		}
		$checks[] = $perma;

		$uploads  = wp_upload_dir( null, false );
		$writable = empty( $uploads['error'] ) && wp_is_writable( (string) $uploads['basedir'] );
		$checks[] = [
			'id'     => 'uploads',
			'label'  => __( 'Uploads directory writable', 'heartland-k9s-core' ),
			'status' => $writable ? 'pass' : 'fail',
			'detail' => $writable ? (string) $uploads['basedir'] : ( (string) ( $uploads['error'] ?: $uploads['basedir'] ) ),
		];

		// The old page-builder plugins hook save_post / content filters the importer's writes
		// would run through; nothing needs them once the Heartland theme is active.
		$legacy = self::active_legacy_plugins();
		$plug   = [
			'id'     => 'legacy_plugins',
			'label'  => __( 'Legacy builder plugins', 'heartland-k9s-core' ),
			'status' => $legacy ? 'warn' : 'pass',
			'detail' => $legacy
				/* translators: %s: comma-separated plugin names */
				? sprintf( __( 'Still active: %s. Deactivate them before the import — nothing needs them once the Heartland theme is active, and the importer\'s writes should not run through their filters. Leave them installed until the migration is signed off.', 'heartland-k9s-core' ), implode( ', ', $legacy ) )
				: __( 'None active (Avada Builder, Avada Core, FooGallery, FooBox).', 'heartland-k9s-core' ),
		];
		if ( $legacy ) {
			$plug['action'] = [
				'label' => __( 'Plugins', 'heartland-k9s-core' ),
				'url'   => admin_url( 'plugins.php' ),
			];
		}
		$checks[] = $plug;

		$payload  = null;
		$existing = null;
		if ( '' === $dir ) {
			$checks[] = [
				'id'     => 'payload',
				'label'  => __( 'Payload', 'heartland-k9s-core' ),
				'status' => 'warn',
				'detail' => __( 'No payload selected yet. Upload the ZIP or enter a server path.', 'heartland-k9s-core' ),
			];
		} elseif ( ! $manifest instanceof Manifest ) {
			$checks[] = [
				'id'     => 'payload',
				'label'  => __( 'Payload', 'heartland-k9s-core' ),
				'status' => 'fail',
				'detail' => $manifest->get_error_message(),
			];
		} else {
			$payload  = Payload::describe( $dir );
			$checks[] = [
				'id'     => 'payload',
				'label'  => __( 'Payload', 'heartland-k9s-core' ),
				'status' => 'pass',
				/* translators: 1: record count, 2: posts/pages count, 3: media count, 4: size */
				'detail' => sprintf( __( '%1$d records (%2$d posts/pages, %3$d media, %4$s) in %5$s', 'heartland-k9s-core' ), (int) $payload['records'], (int) $payload['posts'], (int) $payload['attachments'], size_format( (int) $payload['bytes'] ), (string) $dir ),
			];
			$existing = self::existing( $manifest );
			$total    = (int) $existing['total'];
			$checks[] = [
				'id'     => 'existing',
				'label'  => __( 'Existing content detected', 'heartland-k9s-core' ),
				'status' => $total > 0 ? 'warn' : 'pass',
				'detail' => $total > 0
					? sprintf(
						/* translators: 1: pages, 2: registry pages, 3: attachments */
						__( '%1$d page(s) and %2$d legacy BarKode page(s) match payload records by id or slug, and %3$d attachment(s) match by id. Tick "Existing site: adopt matching content" so they are taken over in place (same ids and URLs) instead of duplicated as "-2" copies.', 'heartland-k9s-core' ),
						(int) $existing['pages'],
						(int) $existing['registry'],
						(int) $existing['media']
					)
					: __( 'None of the payload pages or attachments exist on this site yet (fresh install, or everything is already imported).', 'heartland-k9s-core' ),
			];
		}

		$ok = true;
		foreach ( $checks as $c ) {
			if ( 'fail' === $c['status'] ) {
				$ok = false;
				break;
			}
		}
		return [
			'ok'       => $ok,
			'checks'   => $checks,
			'payload'  => $payload,
			'existing' => $existing,
		];
	}

	/**
	 * How much of the payload already exists on this site and is not mapped yet:
	 * pages / registry pages and attachments by the adoption rules (Adopt).
	 *
	 * @return array{pages:int,registry:int,media:int,total:int,page_keys:string[],media_ids:int[]}
	 */
	public static function existing( Manifest $manifest ): array {
		Map::ensure();
		self::$mapped = Map::active_objects();
		if ( self::$mapped ) {
			_prime_post_caches( array_values( array_unique( self::$mapped ) ), false, false );
		}

		$pages    = 0;
		$registry = 0;
		$keys     = [];
		foreach ( $manifest->keys( Manifest::POST_TYPES_KEY ) as $key ) {
			$record = $manifest->get( $key );
			if ( ! $record ) {
				continue;
			}
			$type = (string) ( $record['type'] ?? '' );
			if ( 'page' !== $type && Adopt::CONVERT_TYPE !== $type ) {
				continue;
			}
			if ( self::is_mapped( $key ) ) {
				continue;
			}
			$found = Adopt::post_candidate( $manifest, $key, $record );
			if ( $found['id'] > 0 ) {
				$keys[] = $key;
				// A registry record counts as such whether its page still has to be converted
				// or was converted by an earlier run and only needs its binding back.
				if ( Adopt::CONVERT_TYPE === $type ) {
					++$registry;
				} else {
					++$pages;
				}
			}
		}

		// Attachments: the same rule the run applies (id + file name + file on disk; no hashing).
		$ids = [];
		foreach ( $manifest->keys( 'attachment' ) as $key ) {
			$live = Adopt::live_id( $key );
			if ( $live > 0 && str_starts_with( $key, 'live:media:' ) && ! self::is_mapped( $key ) ) {
				$ids[ $live ] = $key;
			}
		}
		$media_ids = [];
		if ( $ids ) {
			_prime_post_caches( array_keys( $ids ), false, true );
			foreach ( $ids as $key ) {
				$record = $manifest->get( $key );
				$att    = $record ? Adopt::attachment_candidate( $key, $record ) : 0;
				if ( $att > 0 ) {
					$media_ids[] = $att;
				}
			}
		}

		return [
			'pages'     => $pages,
			'registry'  => $registry,
			'media'     => count( $media_ids ),
			'total'     => $pages + $registry + count( $media_ids ),
			'page_keys' => $keys,
			'media_ids' => $media_ids,
		];
	}

	/**
	 * Plugin directory slugs of the old page-builder stack that must be inactive
	 * while the import writes (their save_post / content filters are untested on
	 * the migration's writes and nothing needs them under the Heartland theme).
	 */
	public const LEGACY_PLUGIN_SLUGS = [ 'fusion-builder', 'fusion-core', 'foogallery', 'foobox' ];

	/**
	 * Names of the active plugins (site or network) that belong to the legacy builder stack.
	 *
	 * @return string[]
	 */
	public static function active_legacy_plugins(): array {
		$active = array_values( (array) get_option( 'active_plugins', [] ) );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}
		$names = [];
		foreach ( array_unique( array_map( 'strval', $active ) ) as $file ) {
			$dir = strtolower( (string) strtok( str_replace( '\\', '/', $file ), '/' ) );
			$dir = preg_replace( '/\.php$/', '', $dir ) ?? $dir;
			$hit = false;
			foreach ( self::LEGACY_PLUGIN_SLUGS as $slug ) {
				if ( $dir === $slug || str_starts_with( $dir, $slug . '-' ) ) {
					$hit = true;
					break;
				}
			}
			if ( ! $hit ) {
				continue;
			}
			$name = '';
			$path = WP_PLUGIN_DIR . '/' . $file;
			if ( is_file( $path ) ) {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$data = get_plugin_data( $path, false, false );
				$name = (string) ( $data['Name'] ?? '' );
			}
			$names[] = '' !== $name ? $name : $file;
		}
		sort( $names );
		return $names;
	}

	/** @var array<string,int> Active map rows (key => object id) for the current existing() call. */
	private static array $mapped = [];

	private static function is_mapped( string $key ): bool {
		$id = self::$mapped[ $key ] ?? 0;
		return $id > 0 && (bool) get_post( $id );
	}
}
