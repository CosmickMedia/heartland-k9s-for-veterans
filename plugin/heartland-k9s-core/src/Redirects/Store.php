<?php
/**
 * Redirect rules store (option hk9_redirects).
 *
 * {version:1, rules:{ "<normalized-source>": {to:{type:"post",id}|{type:"path",path}|{type:"record",slug},
 *   status:301|302|410, enabled:bool, seed:bool, note:string, updated:int, by:int} }}
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Redirects;

defined( 'ABSPATH' ) || exit;

final class Store {

	public const OPTION  = 'hk9_redirects';
	public const VERSION = 1;

	public const STATUSES = [ 301, 302, 410 ];

	/** Legacy registry record slugs (root-level live URLs printed on QR patches). */
	public const RECORD_SLUGS = [
		'larry-and-archie-service-k9',
		'jimmy-and-riley-service-k9',
		'vern-and-bella-service-dog',
		'hk923004',
		'hk923-005',
		'madison-and-gunther-service-k9',
		'scott-and-elke-service-k9',
		'jeremy-and-nova-service-k9',
		'paul-and-mj-service-k9',
		'cody-and-willow-service-k9',
		'barkode-mosby-hk9t26-01',
		'kimber_hk92026-01',
		'caddie-service-k9_hk92026-02',
		'tex-service-k9-hk926-002',
		'sandy-therapy-k9t26-02',
	];

	/** @var array<string, array>|null */
	private static ?array $cache = null;

	public static function activate(): void {
		self::seed( false );
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * All rules keyed by normalized source.
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$option = get_option( self::OPTION, [] );
		$rules  = is_array( $option ) && isset( $option['rules'] ) && is_array( $option['rules'] ) ? $option['rules'] : [];
		$clean  = [];
		foreach ( $rules as $key => $rule ) {
			if ( ! is_string( $key ) || ! is_array( $rule ) ) {
				continue;
			}
			$clean[ $key ] = self::normalize_rule( $rule );
		}
		ksort( $clean );
		self::$cache = $clean;
		return $clean;
	}

	public static function get( string $source ): ?array {
		$key = self::normalize_key( $source );
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Persist the full rule set.
	 *
	 * @param array<string, array> $rules Rules.
	 */
	public static function save_rules( array $rules ): bool {
		$clean = [];
		foreach ( $rules as $key => $rule ) {
			if ( ! is_string( $key ) || '' === $key || ! is_array( $rule ) ) {
				continue;
			}
			$clean[ $key ] = self::normalize_rule( $rule );
		}
		ksort( $clean );
		$ok = update_option(
			self::OPTION,
			[
				'version' => self::VERSION,
				'rules'   => $clean,
			],
			true
		);
		self::flush_cache();
		return $ok || get_option( self::OPTION ) === [ 'version' => self::VERSION, 'rules' => $clean ];
	}

	/**
	 * Fill a rule with typed defaults.
	 */
	public static function normalize_rule( array $rule ): array {
		$status = isset( $rule['status'] ) ? (int) $rule['status'] : 301;
		return [
			'to'      => self::normalize_target( $rule['to'] ?? [] ),
			'status'  => in_array( $status, self::STATUSES, true ) ? $status : 301,
			'enabled' => ! isset( $rule['enabled'] ) || (bool) $rule['enabled'],
			'seed'    => ! empty( $rule['seed'] ),
			'note'    => isset( $rule['note'] ) && is_scalar( $rule['note'] ) ? sanitize_text_field( (string) $rule['note'] ) : '',
			'updated' => isset( $rule['updated'] ) ? (int) $rule['updated'] : 0,
			'by'      => isset( $rule['by'] ) ? (int) $rule['by'] : 0,
		];
	}

	/**
	 * Typed target shape.
	 *
	 * @return array{type:string,id?:int,slug?:string,path?:string}
	 */
	public static function normalize_target( mixed $to ): array {
		if ( is_string( $to ) ) {
			$to = [
				'type' => 'path',
				'path' => $to,
			];
		}
		$to   = is_array( $to ) ? $to : [];
		$type = isset( $to['type'] ) ? (string) $to['type'] : 'path';
		return match ( $type ) {
			'post'   => [
				'type' => 'post',
				'id'   => isset( $to['id'] ) ? absint( $to['id'] ) : 0,
			],
			'record' => [
				'type' => 'record',
				'slug' => isset( $to['slug'] ) ? self::sanitize_record_slug( (string) $to['slug'] ) : '',
			],
			default  => [
				'type' => 'path',
				'path' => isset( $to['path'] ) ? self::sanitize_path( (string) $to['path'] ) : '',
			],
		};
	}

	/**
	 * Record slugs keep underscores (sanitize_title_with_dashes preserves them) and case-fold.
	 */
	public static function sanitize_record_slug( string $slug ): string {
		return sanitize_title( strtolower( trim( $slug ) ) );
	}

	/**
	 * A site-relative path (normalized) or an absolute http(s) URL.
	 */
	public static function sanitize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			$url = esc_url_raw( $path, [ 'http', 'https' ] );
			return $url ?: '';
		}
		$path = '/' . ltrim( $path, '/' );
		$path = wp_sanitize_redirect( $path );
		return self::normalize_key( $path, false );
	}

	/**
	 * Normalize a source URL/path to the option key.
	 *
	 * urldecode → lowercase → strip scheme/host and the home path prefix → leading + trailing slash
	 * (no trailing slash for file-like last segments) → query rules become "/path/?k=v" with sorted keys.
	 *
	 * @param string $source      Path, URL or key.
	 * @param bool   $keep_query  Include the query string in the key.
	 */
	public static function normalize_key( string $source, bool $keep_query = true ): string {
		$source = trim( $source );
		if ( '' === $source ) {
			return '';
		}
		$parts = wp_parse_url( $source );
		if ( false === $parts ) {
			$parts = [ 'path' => $source ];
		}
		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';

		$path = rawurldecode( $path );
		$path = strtolower( $path );
		$path = preg_replace( '#/+#', '/', $path ) ?? $path;

		$home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$home_path = rtrim( strtolower( $home_path ), '/' );
		if ( '' !== $home_path && str_starts_with( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}
		$path = '/' . ltrim( $path, '/' );
		$last = basename( $path );
		if ( ! str_ends_with( $path, '/' ) && ! str_contains( $last, '.' ) ) {
			$path .= '/';
		}
		// Strip characters that never belong in a route key.
		$path = preg_replace( '/[\x00-\x1f\x7f<>"\']/', '', $path ) ?? $path;

		if ( ! $keep_query || '' === $query ) {
			return $path;
		}
		return $path . '?' . self::normalize_query( $query );
	}

	/**
	 * Sorted, decoded "k=v&k2=v2" (lowercase keys, values as given but lowercased for matching).
	 */
	public static function normalize_query( string $query ): string {
		$pairs = [];
		parse_str( $query, $params );
		foreach ( $params as $k => $v ) {
			if ( is_array( $v ) ) {
				continue;
			}
			$pairs[ strtolower( (string) $k ) ] = strtolower( trim( (string) $v ) );
		}
		ksort( $pairs );
		$out = [];
		foreach ( $pairs as $k => $v ) {
			$out[] = $k . '=' . $v;
		}
		return implode( '&', $out );
	}

	/**
	 * Split a key into [path, [k => v]].
	 *
	 * @return array{0:string,1:array<string,string>}
	 */
	public static function split_key( string $key ): array {
		$pos = strpos( $key, '?' );
		if ( false === $pos ) {
			return [ $key, [] ];
		}
		parse_str( substr( $key, $pos + 1 ), $params );
		$out = [];
		foreach ( $params as $k => $v ) {
			if ( ! is_array( $v ) ) {
				$out[ (string) $k ] = (string) $v;
			}
		}
		return [ substr( $key, 0, $pos ), $out ];
	}

	/**
	 * Resolve a target to an absolute URL (null when it points nowhere usable).
	 */
	public static function resolve_target( array $to ): ?string {
		$to = self::normalize_target( $to );
		switch ( $to['type'] ) {
			case 'post':
				$post = $to['id'] > 0 ? get_post( $to['id'] ) : null;
				if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
					return null;
				}
				$url = get_permalink( $post );
				return is_string( $url ) && '' !== $url ? $url : null;

			case 'record':
				if ( '' === $to['slug'] ) {
					return null;
				}
				$record = self::find_record( $to['slug'] );
				if ( $record instanceof \WP_Post ) {
					$url = get_permalink( $record );
					if ( is_string( $url ) && '' !== $url ) {
						return $url;
					}
				}
				// Printed QR codes must land somewhere: fall back to the BarKode program page.
				$page = get_page_by_path( 'barkode', OBJECT, 'page' );
				if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
					return (string) get_permalink( $page );
				}
				return home_url( '/barkode/' );

			default:
				$path = $to['path'];
				if ( '' === $path ) {
					return null;
				}
				if ( preg_match( '#^https?://#i', $path ) ) {
					return $path;
				}
				return home_url( $path );
		}
	}

	/**
	 * Published registry record by slug (cached per request).
	 */
	public static function find_record( string $slug ): ?\WP_Post {
		static $cache = [];
		if ( array_key_exists( $slug, $cache ) ) {
			return $cache[ $slug ];
		}
		$record = post_type_exists( 'hk9_barkode' ) ? get_page_by_path( $slug, OBJECT, 'hk9_barkode' ) : null;
		if ( ! $record instanceof \WP_Post || 'publish' !== $record->post_status ) {
			$record = null;
		}
		$cache[ $slug ] = $record;
		return $record;
	}

	/**
	 * Human-readable target description.
	 */
	public static function describe_target( array $to ): string {
		$to = self::normalize_target( $to );
		switch ( $to['type'] ) {
			case 'post':
				$post = $to['id'] > 0 ? get_post( $to['id'] ) : null;
				if ( $post instanceof \WP_Post ) {
					$obj = get_post_type_object( $post->post_type );
					/* translators: 1: post type label, 2: post title */
					return sprintf( __( '%1$s: %2$s', 'heartland-k9s-core' ), $obj ? $obj->labels->singular_name : $post->post_type, get_the_title( $post ) );
				}
				/* translators: %d: post id */
				return sprintf( __( 'Missing post #%d', 'heartland-k9s-core' ), $to['id'] );
			case 'record':
				/* translators: %s: record slug */
				return sprintf( __( 'BarKode record: %s', 'heartland-k9s-core' ), $to['slug'] );
			default:
				return $to['path'];
		}
	}

	/**
	 * Validate a candidate rule against the current set.
	 *
	 * @param string $source Source path/URL.
	 * @param array  $to     Target.
	 * @param int    $status HTTP status.
	 * @param bool   $force  Accept warnings (collisions with live content).
	 * @return array{key:string,rule:array,warnings:string[]}|\WP_Error
	 */
	public static function validate( string $source, array $to, int $status = 301, bool $force = false ): array|\WP_Error {
		$key = self::normalize_key( $source );
		if ( '' === $key || '/' === $key ) {
			return new \WP_Error( 'hk9_redirect_source', __( 'Enter a source path such as /old-page/ or /?foogallery=123 (the home page cannot be redirected).', 'heartland-k9s-core' ) );
		}
		if ( str_starts_with( $key, '/wp-admin/' ) || str_starts_with( $key, '/wp-json/' ) || str_starts_with( $key, '/wp-login.php' ) ) {
			return new \WP_Error( 'hk9_redirect_source', __( 'Admin, login and REST paths cannot be redirected.', 'heartland-k9s-core' ) );
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'hk9_redirect_status', __( 'Status must be 301, 302 or 410.', 'heartland-k9s-core' ) );
		}
		$to       = self::normalize_target( $to );
		$warnings = [];

		if ( 410 !== $status ) {
			if ( 'post' === $to['type'] && $to['id'] <= 0 ) {
				return new \WP_Error( 'hk9_redirect_target', __( 'Choose a page or record to redirect to.', 'heartland-k9s-core' ) );
			}
			if ( 'record' === $to['type'] && '' === $to['slug'] ) {
				return new \WP_Error( 'hk9_redirect_target', __( 'Enter the BarKode record slug.', 'heartland-k9s-core' ) );
			}
			if ( 'path' === $to['type'] && '' === $to['path'] ) {
				return new \WP_Error( 'hk9_redirect_target', __( 'Enter a destination path or web address.', 'heartland-k9s-core' ) );
			}
			$resolved = self::resolve_target( $to );
			if ( null === $resolved ) {
				return new \WP_Error( 'hk9_redirect_target', __( 'The destination does not resolve to a published page or a valid address.', 'heartland-k9s-core' ) );
			}
			[ $src_path ] = self::split_key( $key );
			$target_key = self::target_key( $resolved );
			if ( null !== $target_key && ( $target_key === $key || $target_key === $src_path ) ) {
				return new \WP_Error( 'hk9_redirect_loop', __( 'The destination is the same as the source (that would loop).', 'heartland-k9s-core' ) );
			}
			if ( null !== $target_key && isset( self::all()[ $target_key ] ) && self::all()[ $target_key ]['enabled'] ) {
				return new \WP_Error(
					'hk9_redirect_chain',
					sprintf(
						/* translators: %s: redirect source */
						__( 'The destination (%s) is itself redirected. Point this rule at the final destination instead.', 'heartland-k9s-core' ),
						$target_key
					)
				);
			}
			if ( 'record' === $to['type'] && ! self::find_record( $to['slug'] ) ) {
				$warnings[] = __( 'No published BarKode record has that slug yet; visitors will land on the BarKode page until it exists.', 'heartland-k9s-core' );
			}
		}

		// Collisions with live content.
		[ $src_path, $src_query ] = self::split_key( $key );
		if ( ! $src_query ) {
			$live = url_to_postid( home_url( $src_path ) );
			if ( $live > 0 && 'publish' === get_post_status( $live ) ) {
				$msg = sprintf(
					/* translators: %s: post title */
					__( 'The source currently resolves to published content ("%s"); the redirect will shadow it.', 'heartland-k9s-core' ),
					get_the_title( $live )
				);
				if ( ! $force ) {
					return new \WP_Error( 'hk9_redirect_collision', $msg . ' ' . __( 'Save again with "override" to keep the redirect anyway.', 'heartland-k9s-core' ) );
				}
				$warnings[] = $msg;
			}
		}

		return [
			'key'      => $key,
			'rule'     => [
				'to'     => $to,
				'status' => $status,
			],
			'warnings' => $warnings,
		];
	}

	/**
	 * Key form of an absolute target URL (null for external hosts).
	 */
	public static function target_key( string $url ): ?string {
		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $host ) && is_string( $home_host ) && strtolower( $host ) !== strtolower( $home_host ) ) {
			return null;
		}
		return self::normalize_key( $url );
	}

	/**
	 * Add or update a rule (validated).
	 *
	 * @return array{key:string,warnings:string[]}|\WP_Error
	 */
	public static function upsert( string $source, array $to, int $status = 301, string $note = '', bool $enabled = true, bool $force = false, ?string $replace_key = null ): array|\WP_Error {
		$result = self::validate( $source, $to, $status, $force );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$rules    = self::all();
		$existing = $rules[ $result['key'] ] ?? ( null !== $replace_key ? ( $rules[ $replace_key ] ?? null ) : null );
		if ( null !== $replace_key && $replace_key !== $result['key'] ) {
			unset( $rules[ $replace_key ] );
		}
		$rules[ $result['key'] ] = self::normalize_rule(
			array_merge(
				$existing ?? [],
				$result['rule'],
				[
					'enabled' => $enabled,
					'note'    => $note,
					'seed'    => $existing['seed'] ?? false,
					'updated' => time(),
					'by'      => get_current_user_id(),
				]
			)
		);
		if ( ! self::save_rules( $rules ) ) {
			return new \WP_Error( 'hk9_redirect_save', __( 'The redirect could not be saved.', 'heartland-k9s-core' ) );
		}
		return [
			'key'      => $result['key'],
			'warnings' => $result['warnings'],
		];
	}

	public static function delete( string $source ): bool {
		$key   = self::normalize_key( $source );
		$rules = self::all();
		if ( ! isset( $rules[ $key ] ) ) {
			return false;
		}
		unset( $rules[ $key ] );
		return self::save_rules( $rules );
	}

	public static function set_enabled( string $source, bool $enabled ): bool {
		$key   = self::normalize_key( $source );
		$rules = self::all();
		if ( ! isset( $rules[ $key ] ) ) {
			return false;
		}
		$rules[ $key ]['enabled'] = $enabled;
		$rules[ $key ]['updated'] = time();
		$rules[ $key ]['by']      = get_current_user_id();
		return self::save_rules( $rules );
	}

	/**
	 * Seed rules (normalized key => rule).
	 *
	 * @return array<string, array>
	 */
	public static function seeds(): array {
		$rules = [];
		$path  = static fn( string $p ): array => [
			'type' => 'path',
			'path' => $p,
		];
		foreach ( self::RECORD_SLUGS as $slug ) {
			$rules[ '/' . $slug . '/' ] = [
				'to'   => [
					'type' => 'record',
					'slug' => $slug,
				],
				'note' => 'Legacy registry URL printed on the BarKode patch',
			];
		}
		$rules['/master_template_barkode/']        = [ 'to' => $path( '/barkode/' ), 'note' => 'Legacy registry template page' ];
		$rules['/success/']                        = [ 'to' => $path( '/stories/' ), 'note' => 'Legacy success-story stub' ];
		$rules['/success/sample-success-story/']   = [ 'to' => $path( '/stories/' ), 'note' => 'Legacy sample success story' ];
		$rules['/slide/heartland-hero/']           = [ 'to' => $path( '/' ), 'note' => 'Legacy Avada slider item' ];
		$rules['/slide-page/homepage-fusion/']     = [ 'to' => $path( '/' ), 'note' => 'Legacy Avada slide page' ];
		$rules['/?foogallery=2468']                = [ 'to' => $path( '/photos/' ), 'note' => 'Legacy FooGallery photo gallery' ];
		$rules['/?foogallery=back-the-pack-partners'] = [ 'to' => $path( '/back-the-pack/' ), 'note' => 'Legacy FooGallery partner logos' ];
		$rules['/teams/']                          = [ 'to' => $path( '/hk9-current-teams-in-training/' ), 'note' => 'Teams listing lives on the "current teams in training" page' ];
		// The live site sends both author archives to the homepage (crawl 2026-09); keep that. The
		// resolver runs on parse_request before Privacy::block_author_query, so the 301 wins; ?author=N stays a 404.
		$rules['/author/admin/']                   = [ 'to' => $path( '/' ), 'note' => 'Author archive: the live site redirects it to the homepage' ];
		$rules['/author/hk9director/']             = [ 'to' => $path( '/' ), 'note' => 'Author archive: the live site redirects it to the homepage' ];

		$out = [];
		foreach ( $rules as $source => $rule ) {
			$key = self::normalize_key( $source );
			$out[ $key ] = self::normalize_rule(
				$rule + [
					'status'  => 301,
					'enabled' => true,
					'seed'    => true,
					'updated' => 0,
					'by'      => 0,
				]
			);
		}
		return $out;
	}

	/**
	 * Install seed rules without overwriting edited rows (unless $overwrite).
	 *
	 * @return int Number of rules written.
	 */
	public static function seed( bool $overwrite = false ): int {
		$rules = self::all();
		$count = 0;
		foreach ( self::seeds() as $key => $rule ) {
			if ( isset( $rules[ $key ] ) && ! $overwrite ) {
				continue;
			}
			$rules[ $key ] = $rule;
			++$count;
		}
		if ( $count > 0 || ! is_array( get_option( self::OPTION ) ) ) {
			self::save_rules( $rules );
		}
		return $count;
	}
}
