<?php
/**
 * Step 1: validate.
 *
 * Item 'structure' — manifest schema, unique keys, token graph, dates, templates.
 * Item 'env'       — permalink structure, active theme, uploads writable, post types.
 * Item <attachment key> — file containment, existence, size cap, sha256, MIME (per-record failure).
 * Item 'prehash:<n>' — hash pre-existing attachments lacking _hk9_sha256 so manual uploads are adopted.
 *
 * Structural/environment problems are fatal; file problems fail only that record.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Import\Steps;

use HK9\Core\Import\Context;
use HK9\Core\Import\Hash;
use HK9\Core\Import\Manifest;
use HK9\Core\Import\Tokens;

defined( 'ABSPATH' ) || exit;

final class Validate extends Step {

	public const NAME = 'validate';

	public const PREHASH_CHUNK = 50;

	public const POST_STATUSES = [ 'publish', 'draft', 'private', 'pending', 'future' ];

	private static ?int $existing_attachments = null;

	protected function items(): array {
		$items = [ 'structure', 'env' ];
		foreach ( $this->manifest()->keys( 'attachment' ) as $k ) {
			$items[] = $k;
		}
		$chunks = (int) ceil( $this->existing_attachment_count() / self::PREHASH_CHUNK );
		for ( $i = 0; $i < $chunks; $i++ ) {
			$items[] = 'prehash:' . $i;
		}
		return $items;
	}

	private function existing_attachment_count(): int {
		if ( null === self::$existing_attachments ) {
			self::$existing_attachments = (int) ( wp_count_posts( 'attachment' )->inherit ?? 0 );
		}
		return self::$existing_attachments;
	}

	protected function process( string $item ): void {
		if ( 'structure' === $item ) {
			$this->structure();
			return;
		}
		if ( 'env' === $item ) {
			$this->environment();
			return;
		}
		if ( str_starts_with( $item, 'prehash:' ) ) {
			$this->prehash( (int) substr( $item, 8 ) );
			return;
		}
		$record = $this->record( $item );
		if ( $record ) {
			$this->check_file( $item, $record );
		}
	}

	/* ---------------------------------------------------------- structure */

	private function structure(): void {
		$ctx      = $this->ctx;
		$manifest = $this->manifest();
		$fatal    = [];
		$seen     = [];
		$records  = 0;
		$bytes    = 0;

		foreach ( $manifest->records() as $i => $record ) {
			++$records;
			$key = $record['key'] ?? null;
			if ( ! is_string( $key ) || '' === trim( $key ) || strlen( $key ) > 191 ) {
				$fatal[] = sprintf( 'Record #%d has no valid "key".', $i );
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				$fatal[] = sprintf( 'Duplicate record key "%s".', $key );
				continue;
			}
			$seen[ $key ] = true;
			$type         = $record['type'] ?? null;
			if ( ! is_string( $type ) || '' === $type ) {
				$fatal[] = sprintf( '%s: missing "type".', $key );
				continue;
			}
			$errors = $this->check_shape( $key, $record );
			foreach ( $errors as $e ) {
				$fatal[] = $key . ': ' . $e;
			}
			if ( 'attachment' === $type ) {
				$bytes += (int) ( $record['size'] ?? 0 );
			}

			// Token graph: every token must point at a record of the right kind.
			foreach ( Tokens::extract( $this->tokenised_parts( $record ) ) as [ $kind, $tkey, $mod ] ) {
				if ( ! $ctx->tokens->target_type_ok( $kind, $tkey ) ) {
					$fatal[] = sprintf( '%s: dangling token {{%s:%s}}.', $key, $kind, $tkey );
				}
				if ( '' !== $mod && ( 'media_url' !== $kind || 'original' !== $mod ) ) {
					$fatal[] = sprintf( '%s: unsupported token modifier "|%s" on {{%s:%s}}.', $key, $mod, $kind, $tkey );
				}
			}
			// Tokens inside the content file.
			if ( Manifest::POST_TYPES_KEY === Manifest::type_of( $record ) && ! empty( $record['content'] ) ) {
				$html = $manifest->content( $record );
				if ( is_wp_error( $html ) ) {
					$fatal[] = $key . ': ' . $html->get_error_message();
				} elseif ( is_string( $html ) ) {
					foreach ( Tokens::extract( $html ) as [ $kind, $tkey ] ) {
						if ( ! $ctx->tokens->target_type_ok( $kind, $tkey ) ) {
							$fatal[] = sprintf( '%s: dangling token {{%s:%s}} in %s.', $key, $kind, $tkey, (string) $record['content'] );
						}
					}
				}
			}
		}

		$ctx->state['totals'] = [
			'records'     => $records,
			'attachments' => $manifest->count( 'attachment' ),
			'bytes'       => $bytes,
		];

		if ( $fatal ) {
			$shown = array_slice( $fatal, 0, 25 );
			foreach ( $shown as $msg ) {
				$ctx->fatal( $msg );
			}
			if ( count( $fatal ) > 25 ) {
				$ctx->fatal( sprintf( '… and %d more structural errors (see log).', count( $fatal ) - 25 ) );
				foreach ( array_slice( $fatal, 25 ) as $msg ) {
					$ctx->log->error( self::NAME, '', $msg );
				}
			}
			throw new \RuntimeException( sprintf( 'Manifest validation failed with %d error(s).', count( $fatal ) ) );
		}
		$ctx->info( '', sprintf( 'Structure OK: %d records, %d attachments, %s.', $records, $manifest->count( 'attachment' ), size_format( $bytes ) ) );
	}

	/**
	 * Parts of a record that may carry tokens (excludes the content path itself).
	 */
	private function tokenised_parts( array $record ): array {
		$copy = $record;
		unset( $copy['content'], $copy['key'], $copy['type'], $copy['file'], $copy['sha256'] );
		return $copy;
	}

	/**
	 * @return string[] Error messages (empty when the shape is fine).
	 */
	private function check_shape( string $key, array $r ): array {
		$e    = [];
		$type = (string) $r['type'];
		$str  = static fn( $v ): bool => is_string( $v );

		switch ( $type ) {
			case 'attachment':
				if ( ! $str( $r['file'] ?? null ) || '' === $r['file'] ) {
					$e[] = 'attachment needs "file".';
				}
				if ( ! $str( $r['sha256'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) $r['sha256'] ) ) {
					$e[] = 'attachment needs a 64-hex "sha256".';
				}
				if ( ! $str( $r['mime'] ?? null ) || '' === $r['mime'] ) {
					$e[] = 'attachment needs "mime".';
				}
				if ( isset( $r['parent'] ) && ! $str( $r['parent'] ) ) {
					$e[] = '"parent" must be a {{post:K}} token.';
				}
				$d = Manifest::date_pair( $r['date'] ?? null );
				if ( is_wp_error( $d ) ) {
					$e[] = $d->get_error_message();
				}
				break;

			case 'term':
				foreach ( [ 'taxonomy', 'slug', 'name' ] as $f ) {
					if ( ! $str( $r[ $f ] ?? null ) || '' === $r[ $f ] ) {
						$e[] = sprintf( 'term needs "%s".', $f );
					}
				}
				if ( $str( $r['taxonomy'] ?? null ) && $str( $r['slug'] ?? null ) && $key !== 'term:' . $r['taxonomy'] . ':' . $r['slug'] ) {
					$e[] = sprintf( 'term key must be "term:%s:%s".', (string) $r['taxonomy'], (string) $r['slug'] );
				}
				break;

			case 'menu':
				if ( ! $str( $r['name'] ?? null ) || '' === $r['name'] ) {
					$e[] = 'menu needs "name".';
				}
				if ( isset( $r['locations'] ) && ! is_array( $r['locations'] ) ) {
					$e[] = '"locations" must be an array.';
				}
				$item_keys = [];
				foreach ( (array) ( $r['items'] ?? [] ) as $i => $item ) {
					if ( ! is_array( $item ) || ! $str( $item['key'] ?? null ) || '' === $item['key'] ) {
						$e[] = sprintf( 'menu item #%d needs "key".', (int) $i );
						continue;
					}
					$item_keys[ $item['key'] ] = true;
					$kind                      = $item['kind'] ?? '';
					if ( ! in_array( $kind, [ 'post_type', 'custom' ], true ) ) {
						$e[] = sprintf( 'menu item %s: "kind" must be post_type|custom.', (string) $item['key'] );
					}
					if ( 'post_type' === $kind && null === Tokens::single_key( (string) ( $item['object'] ?? '' ), 'post' ) ) {
						$e[] = sprintf( 'menu item %s: post_type items need "object" = {{post:K}}.', (string) $item['key'] );
					}
					if ( 'custom' === $kind && ( ! $str( $item['url'] ?? null ) || '' === $item['url'] ) ) {
						$e[] = sprintf( 'menu item %s: custom items need "url".', (string) $item['key'] );
					}
				}
				foreach ( (array) ( $r['items'] ?? [] ) as $item ) {
					if ( is_array( $item ) && ! empty( $item['parent'] ) && ! isset( $item_keys[ $item['parent'] ] ) ) {
						$e[] = sprintf( 'menu item %s: parent "%s" is not an item of this menu.', (string) ( $item['key'] ?? '?' ), (string) $item['parent'] );
					}
				}
				break;

			case 'option':
				$name = $r['name'] ?? null;
				if ( ! $str( $name ) || '' === $name ) {
					$e[] = 'option needs "name".';
				} elseif ( ! self::option_allowed( (string) $name ) ) {
					$e[] = sprintf( 'option "%s" is not importable (only hk9_* options and a small site-info allow-list).', (string) $name );
				}
				if ( isset( $r['merge'] ) && ! in_array( $r['merge'], [ 'deep', 'replace' ], true ) ) {
					$e[] = '"merge" must be deep|replace.';
				}
				if ( ! array_key_exists( 'value', $r ) ) {
					$e[] = 'option needs "value".';
				}
				break;

			case 'reading':
				if ( 'reading' !== $key ) {
					$e[] = 'reading record key must be "reading".';
				}
				if ( isset( $r['show_on_front'] ) && ! in_array( $r['show_on_front'], [ 'page', 'posts' ], true ) ) {
					$e[] = '"show_on_front" must be page|posts.';
				}
				foreach ( [ 'page_on_front', 'page_for_posts' ] as $f ) {
					if ( ! empty( $r[ $f ] ) && null === Tokens::single_key( (string) $r[ $f ], 'post' ) ) {
						$e[] = sprintf( '"%s" must be a {{post:K}} token.', $f );
					}
				}
				break;

			case 'redirect':
				if ( ! $str( $r['from'] ?? null ) || '' === $r['from'] ) {
					$e[] = 'redirect needs "from".';
				}
				$to = $r['to'] ?? null;
				if ( is_array( $to ) ) {
					if ( ( $to['type'] ?? '' ) !== 'record' || ! $str( $to['slug'] ?? null ) ) {
						$e[] = 'object "to" must be {type:"record", slug}.';
					}
				} elseif ( ! $str( $to ) || '' === $to ) {
					if ( 410 !== (int) ( $r['status'] ?? 301 ) ) {
						$e[] = 'redirect needs "to" (path, {{post_url:K}} or {type:"record",slug}).';
					}
				}
				if ( isset( $r['status'] ) && ! in_array( (int) $r['status'], [ 301, 302, 410 ], true ) ) {
					$e[] = '"status" must be 301, 302 or 410.';
				}
				break;

			default:
				// Post-like.
				if ( ! $str( $r['title'] ?? null ) ) {
					$e[] = 'post needs "title".';
				}
				if ( ! $str( $r['slug'] ?? null ) || '' === $r['slug'] ) {
					$e[] = 'post needs "slug".';
				}
				if ( isset( $r['status'] ) && ! in_array( $r['status'], self::POST_STATUSES, true ) ) {
					$e[] = '"status" must be one of ' . implode( '|', self::POST_STATUSES ) . '.';
				}
				if ( isset( $r['parent'] ) && '' !== $r['parent'] && null === Tokens::single_key( (string) $r['parent'], 'post' ) ) {
					$e[] = '"parent" must be a {{post:K}} token.';
				}
				if ( isset( $r['featured'] ) && '' !== $r['featured'] && null === Tokens::single_key( (string) $r['featured'], 'media' ) ) {
					$e[] = '"featured" must be a {{media:K}} token.';
				}
				if ( isset( $r['content'] ) && ! $str( $r['content'] ) ) {
					$e[] = '"content" must be a payload-relative path.';
				}
				if ( isset( $r['meta'] ) ) {
					if ( ! is_array( $r['meta'] ) ) {
						$e[] = '"meta" must be an object.';
					} else {
						foreach ( $r['meta'] as $mk => $mv ) {
							if ( ! is_string( $mk ) || ! is_array( $mv ) || ! array_key_exists( 'value', $mv ) ) {
								$e[] = sprintf( 'meta "%s" must be {type, value}.', (string) $mk );
							} elseif ( isset( $mv['type'] ) && ! in_array( $mv['type'], [ 'string', 'integer', 'number', 'boolean', 'array', 'object' ], true ) ) {
								$e[] = sprintf( 'meta "%s": unknown type "%s".', (string) $mk, (string) $mv['type'] );
							}
						}
					}
				}
				if ( isset( $r['terms'] ) && ! is_array( $r['terms'] ) ) {
					$e[] = '"terms" must be an object of taxonomy => [tokens|slugs].';
				}
				$d = Manifest::date_pair( $r['date'] ?? null );
				if ( is_wp_error( $d ) ) {
					$e[] = $d->get_error_message();
				}
		}
		return $e;
	}

	public static function option_allowed( string $name ): bool {
		$allow = [ 'blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'start_of_week', 'posts_per_page', 'posts_per_rss', 'site_icon', 'default_comment_status', 'default_ping_status' ];
		/**
		 * Filters option names the payload may write (besides hk9_*).
		 *
		 * @param string[] $allow Option names.
		 */
		$allow = (array) apply_filters( 'hk9/import/allowed_options', $allow );
		return str_starts_with( $name, 'hk9_' ) || in_array( $name, $allow, true );
	}

	/* -------------------------------------------------------- environment */

	private function environment(): void {
		$ctx   = $this->ctx;
		$fatal = [];

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$fatal[] = 'Permalinks are set to "Plain". Choose a permalink structure (e.g. /%postname%/) before importing so page URLs can be baked.';
		}
		$expected = $this->manifest()->requires_theme();
		if ( '' !== $expected && get_stylesheet() !== $expected ) {
			$fatal[] = sprintf( 'Active theme is "%s" but the payload targets "%s". Activate the theme first (menu locations and templates are theme-scoped).', get_stylesheet(), $expected );
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			$fatal[] = 'Uploads directory is not writable: ' . $uploads['error'];
		}
		if ( ! extension_loaded( 'fileinfo' ) ) {
			$ctx->warn( '', 'PHP fileinfo extension is missing: MIME types are checked by extension only.' );
		}
		if ( $fatal ) {
			foreach ( $fatal as $m ) {
				$ctx->fatal( $m );
			}
			throw new \RuntimeException( 'Environment checks failed.' );
		}

		// Per-record environment checks (registered post types / taxonomies / templates).
		$templates = wp_get_theme()->get_page_templates( null, 'page' );
		foreach ( $this->manifest()->records() as $record ) {
			$key  = (string) $record['key'];
			$type = (string) $record['type'];
			$sens = Context::is_sensitive( $record );
			if ( 'term' === $type ) {
				if ( ! taxonomy_exists( (string) $record['taxonomy'] ) ) {
					$ctx->fail( $key, sprintf( 'Taxonomy "%s" is not registered.', (string) $record['taxonomy'] ), $sens );
				}
				continue;
			}
			if ( Manifest::POST_TYPES_KEY !== Manifest::type_of( $record ) ) {
				continue;
			}
			if ( ! post_type_exists( $type ) ) {
				$ctx->fail( $key, sprintf( 'Post type "%s" is not registered (is the companion plugin fully active?).', $type ), $sens );
				continue;
			}
			if ( ! empty( $record['template'] ) && 'default' !== $record['template'] ) {
				$file = self::normalise_template( (string) $record['template'] );
				if ( ! isset( $templates[ $file ] ) ) {
					$ctx->warn( $key, sprintf( 'Page template "%s" is not present in the active theme; _wp_page_template will be stored anyway.', $file ), $sens );
				}
			}
			foreach ( (array) ( $record['terms'] ?? [] ) as $tax => $list ) {
				if ( ! taxonomy_exists( (string) $tax ) ) {
					$ctx->warn( $key, sprintf( 'Taxonomy "%s" is not registered; its terms will be skipped.', (string) $tax ), $sens );
				}
			}
		}
		$ctx->info( '', sprintf( 'Environment OK: theme %s, permalinks %s.', get_stylesheet(), (string) get_option( 'permalink_structure' ) ) );
	}

	/**
	 * "about.php" -> "page-templates/about.php"; values with a slash are kept.
	 */
	public static function normalise_template( string $template ): string {
		$template = ltrim( trim( $template ), '/' );
		if ( '' === $template || 'default' === $template ) {
			return 'default';
		}
		if ( ! str_contains( $template, '/' ) ) {
			return 'page-templates/' . $template;
		}
		return $template;
	}

	/* -------------------------------------------------------------- files */

	public static function allowed_mimes(): array {
		$mimes = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'application/pdf' ];
		/**
		 * Filters MIME types the importer accepts for attachments (HEIC/SVG are deliberately absent).
		 *
		 * @param string[] $mimes
		 */
		return (array) apply_filters( 'hk9/import/allowed_mimes', $mimes );
	}

	public static function max_file_bytes(): int {
		/**
		 * Filters the per-file size cap.
		 *
		 * @param int $bytes Default 64 MB.
		 */
		return (int) apply_filters( 'hk9/import/max_file_bytes', 64 * MB_IN_BYTES );
	}

	private function check_file( string $key, array $record ): void {
		$ctx  = $this->ctx;
		$sens = Context::is_sensitive( $record );
		$path = $this->manifest()->path( (string) $record['file'] );
		if ( null === $path ) {
			$ctx->fail( $key, sprintf( 'File reference "%s" escapes the payload directory.', (string) $record['file'] ), $sens );
			return;
		}
		if ( ! is_file( $path ) ) {
			$ctx->fail( $key, sprintf( 'File "%s" is missing from the payload.', (string) $record['file'] ), $sens );
			return;
		}
		$size = (int) filesize( $path );
		if ( $size <= 0 ) {
			$ctx->fail( $key, sprintf( 'File "%s" is empty.', (string) $record['file'] ), $sens );
			return;
		}
		if ( $size > self::max_file_bytes() ) {
			$ctx->fail( $key, sprintf( 'File "%s" is %s, above the %s cap.', (string) $record['file'], size_format( $size ), size_format( self::max_file_bytes() ) ), $sens );
			return;
		}
		$declared = strtolower( (string) $record['mime'] );
		if ( ! in_array( $declared, self::allowed_mimes(), true ) ) {
			$ctx->fail( $key, sprintf( 'MIME type "%s" is not allowed.', $declared ), $sens );
			return;
		}
		$check = wp_check_filetype_and_ext( $path, basename( $path ) );
		if ( empty( $check['type'] ) || ! in_array( (string) $check['type'], self::allowed_mimes(), true ) ) {
			$ctx->fail( $key, sprintf( 'File "%s" does not look like an allowed type (detected: %s).', (string) $record['file'], (string) ( $check['type'] ?: 'unknown' ) ), $sens );
			return;
		}
		if ( ! empty( $check['proper_filename'] ) ) {
			$ctx->warn( $key, sprintf( 'File extension does not match its content; WordPress will store it as "%s".', (string) $check['proper_filename'] ), $sens );
		}
		$sha = Hash::file( $path );
		if ( $sha !== strtolower( (string) $record['sha256'] ) ) {
			$ctx->fail( $key, sprintf( 'sha256 mismatch for "%s".', (string) $record['file'] ), $sens );
			return;
		}
		if ( isset( $record['size'] ) && (int) $record['size'] !== $size ) {
			$ctx->warn( $key, sprintf( 'Declared size %d differs from actual %d.', (int) $record['size'], $size ), $sens );
		}
	}

	/* ------------------------------------------------------------ prehash */

	/**
	 * Hash pre-existing attachments (that we did not import) so manual uploads
	 * with identical bytes are adopted instead of duplicated. Dry runs keep the
	 * results in state instead of writing meta.
	 */
	private function prehash( int $chunk ): void {
		$ctx = $this->ctx;
		$ids = get_posts(
			[
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'numberposts'      => self::PREHASH_CHUNK,
				'offset'           => $chunk * self::PREHASH_CHUNK,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			]
		);
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( '' !== (string) get_post_meta( $id, '_hk9_sha256', true ) ) {
				continue;
			}
			$path = wp_get_original_image_path( $id, true );
			if ( ! $path ) {
				$path = get_attached_file( $id, true );
			}
			if ( ! $path || ! is_file( $path ) ) {
				continue;
			}
			$sha = Hash::file( $path );
			if ( null === $sha ) {
				continue;
			}
			if ( $ctx->dry() ) {
				$ctx->state['prehash'][ $sha ] = $id;
			} else {
				update_post_meta( $id, '_hk9_sha256', $sha );
			}
		}
	}
}
