<?php
/**
 * Section registry: loads Sections/definitions/*.php, registers the section
 * meta keys for pages (validated revisions/preview design) and answers
 * template/section lookups.
 *
 * Meta key rules: `hk9_sec_<id>` is shared by every template that declares
 * the same section id with the same field set. When two templates declare
 * the same id with different fields, the definition sets 'meta_key' to a
 * distinct key (e.g. `hk9_sec_stories_list`); hk9_section( $post, 'list' )
 * still resolves through the page's template.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Sections;

use HK9\Core\Fields\Assets;
use HK9\Core\Meta\RevisionGuard;

defined( 'ABSPATH' ) || exit;

final class Registry {

	private static bool $loaded = false;
	private static bool $meta_registered = false;

	/** template slug => ['label'=>string, 'sections'=>Definition[]] */
	private static array $templates = [];

	/** meta_key => Definition (first registered = canonical). */
	private static array $keys = [];

	/** section id => [template => Definition]. */
	private static array $ids = [];

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_meta' ], 20 );
		add_filter( 'rest_pre_insert_page', [ self::class, 'rest_pre_insert' ], 10, 2 );
		if ( class_exists( Assets::class ) ) {
			Assets::register();
		}
	}

	/** Loads definitions once (lazily, after init so translations are available). */
	public static function load(): void {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;
		$files = glob( __DIR__ . '/definitions/*.php' ) ?: [];
		sort( $files );
		foreach ( $files as $file ) {
			$def = include $file;
			if ( is_array( $def ) ) {
				self::add_template( $def );
			}
		}
		/**
		 * Fires after the built-in section definitions are loaded. Use
		 * Registry::add_template() / Registry::add_section() to extend.
		 *
		 * @param string $registry Registry class name.
		 */
		do_action( 'hk9/sections/register', self::class );
	}

	/**
	 * Adds a template definition: ['template'=>slug,'label'=>..., 'sections'=>[...]].
	 */
	public static function add_template( array $def ): void {
		$slug = sanitize_key( (string) ( $def['template'] ?? '' ) );
		if ( '' === $slug ) {
			return;
		}
		if ( ! isset( self::$templates[ $slug ] ) ) {
			self::$templates[ $slug ] = [
				'label'    => (string) ( $def['label'] ?? $slug ),
				'sections' => [],
			];
		} elseif ( ! empty( $def['label'] ) ) {
			self::$templates[ $slug ]['label'] = (string) $def['label'];
		}
		foreach ( (array) ( $def['sections'] ?? [] ) as $section ) {
			if ( is_array( $section ) ) {
				self::add_section( $slug, $section );
			}
		}
	}

	/** Adds one section to a template. */
	public static function add_section( string $template, array $section ): void {
		$template = sanitize_key( $template );
		if ( ! isset( self::$templates[ $template ] ) ) {
			self::$templates[ $template ] = [
				'label'    => $template,
				'sections' => [],
			];
		}
		$def = new Definition( $template, $section );
		if ( '' === $def->id ) {
			return;
		}
		$key = $def->meta_key();
		if ( isset( self::$keys[ $key ] ) && self::$keys[ $key ]->signature() !== $def->signature() ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: 1: meta key, 2: template, 3: template */
					esc_html__( 'Section meta key "%1$s" is declared with different fields by templates "%2$s" and "%3$s". Set a distinct "meta_key" on one of them.', 'heartland-k9s-core' ),
					esc_html( $key ),
					esc_html( self::$keys[ $key ]->template ),
					esc_html( $template )
				),
				'1.0.0'
			);
			return;
		}
		// Replace an existing section of the same id in this template (allows overrides).
		$sections = &self::$templates[ $template ]['sections'];
		$replaced = false;
		foreach ( $sections as $i => $existing ) {
			if ( $existing->id === $def->id ) {
				$sections[ $i ] = $def;
				$replaced       = true;
				break;
			}
		}
		if ( ! $replaced ) {
			$sections[] = $def;
		}
		unset( $sections );
		if ( ! isset( self::$keys[ $key ] ) ) {
			self::$keys[ $key ] = $def;
		}
		self::$ids[ $def->id ][ $template ] = $def;
	}

	/** template slug => label. */
	public static function templates(): array {
		self::load();
		$out = [];
		foreach ( self::$templates as $slug => $tpl ) {
			$out[ $slug ] = $tpl['label'];
		}
		return $out;
	}

	/** Whether a template slug is known. */
	public static function has_template( string $template ): bool {
		self::load();
		return isset( self::$templates[ sanitize_key( $template ) ] );
	}

	/** Template label. */
	public static function template_label( string $template ): string {
		self::load();
		return self::$templates[ sanitize_key( $template ) ]['label'] ?? $template;
	}

	/**
	 * Definitions of a template in reference order.
	 *
	 * @return Definition[]
	 */
	public static function definitions( string $template ): array {
		self::load();
		return self::$templates[ sanitize_key( $template ) ]['sections'] ?? [];
	}

	/** One definition by template + section id. */
	public static function definition( string $template, string $section_id ): ?Definition {
		self::load();
		foreach ( self::definitions( $template ) as $def ) {
			if ( $def->id === $section_id ) {
				return $def;
			}
		}
		return null;
	}

	/** Definition by section id from any template (first declaring template wins). */
	public static function definition_any( string $section_id ): ?Definition {
		self::load();
		$section_id = sanitize_key( $section_id );
		if ( empty( self::$ids[ $section_id ] ) ) {
			return null;
		}
		return reset( self::$ids[ $section_id ] ) ?: null;
	}

	/** Definition by meta key (canonical). */
	public static function by_key( string $meta_key ): ?Definition {
		self::load();
		return self::$keys[ $meta_key ] ?? null;
	}

	/** All registered meta keys (unique). */
	public static function meta_keys(): array {
		self::load();
		return array_keys( self::$keys );
	}

	/** Every section id across templates. */
	public static function all_section_ids(): array {
		self::load();
		return array_keys( self::$ids );
	}

	/** Meta key for a template + section id ('' when unknown). */
	public static function meta_key( string $template, string $section_id ): string {
		$def = self::definition( $template, $section_id );
		return $def ? $def->meta_key() : '';
	}

	/**
	 * Registers every section meta key for pages, plus the layout key.
	 *
	 * Follows the validated design: no top-level `default` (it would make
	 * every save look changed and break autosave/preview lookups); the default
	 * lives in the REST schema, prepare_callback and the accessor.
	 */
	public static function register_meta(): void {
		if ( self::$meta_registered ) {
			return;
		}
		self::$meta_registered = true;
		self::load();

		foreach ( self::$keys as $key => $def ) {
			register_post_meta(
				'page',
				$key,
				[
					'type'              => 'object',
					'description'       => $def->label,
					'single'            => true,
					'revisions_enabled' => true,
					'show_in_rest'      => [
						'schema'           => $def->schema(),
						'prepare_callback' => static function ( $value, $request ) use ( $def, $key ) {
							$post_id = $request instanceof \WP_REST_Request ? absint( $request->get_param( 'id' ) ) : 0;
							if ( $post_id > 0 && 'page' === get_post_type( $post_id ) && ! metadata_exists( 'post', $post_id, $key ) ) {
								// Absent key: expose the defaults of the page's own template (shared keys differ per template).
								$value = self::template_default( $key, $post_id ) ?? $def->defaults();
							}
							$value = $def->sanitize( is_array( $value ) || $value instanceof \stdClass ? $value : [] );
							$ctx   = $request instanceof \WP_REST_Request ? (string) $request->get_param( 'context' ) : 'view';
							if ( 'edit' !== $ctx ) {
								foreach ( $def->private_keys() as $private ) {
									unset( $value[ $private ] );
								}
							}
							return $value;
						},
					],
					'sanitize_callback' => static function ( $value ) use ( $def ) {
						return $def->sanitize( $value );
					},
					'auth_callback'     => [ self::class, 'auth' ],
				]
			);
			RevisionGuard::track(
				'page',
				$key,
				$def->label,
				static fn( $value ) => $def->sanitize( $value ),
				static function ( $value ) use ( $def ): string {
					if ( null === $value ) {
						return '';
					}
					return \HK9\Core\Fields\Renderer::format_fields( $def->fields, $def->sanitize( $value ) );
				}
			);
		}

		register_post_meta(
			'page',
			Layout::META_KEY,
			[
				'type'              => 'object',
				'description'       => __( 'Section order and visibility', 'heartland-k9s-core' ),
				'single'            => true,
				'revisions_enabled' => true,
				'show_in_rest'      => [
					'schema'           => Layout::schema(),
					'prepare_callback' => static fn( $value ) => Layout::sanitize( $value ),
				],
				'sanitize_callback' => static fn( $value ) => Layout::sanitize( $value ),
				'auth_callback'     => [ self::class, 'auth' ],
			]
		);
		RevisionGuard::track(
			'page',
			Layout::META_KEY,
			__( 'Section layout', 'heartland-k9s-core' ),
			static fn( $value ) => Layout::sanitize( $value ),
			static function ( $value ): string {
				if ( null === $value ) {
					return '';
				}
				$layout = Layout::sanitize( $value );
				return sprintf( "%s: %s\n%s: %s", __( 'Order', 'heartland-k9s-core' ), implode( ', ', $layout['order'] ), __( 'Hidden', 'heartland-k9s-core' ), implode( ', ', $layout['hidden'] ) );
			}
		);
	}

	/** auth_callback: the user must be able to edit the post. */
	public static function auth( $allowed, $meta_key, $post_id, $user_id ): bool {
		return user_can( (int) $user_id, 'edit_post', (int) $post_id );
	}

	/** Defaults of the section stored under $key for the page's own template (null when the template lacks it). */
	public static function template_default( string $key, int $post_id ): ?array {
		if ( Layout::META_KEY === $key ) {
			return Layout::empty_value();
		}
		$template = Accessor::template_for_post( $post_id );
		foreach ( self::definitions( $template ) as $def ) {
			if ( $def->meta_key() === $key ) {
				return $def->defaults();
			}
		}
		return null;
	}

	/**
	 * Whether writing $clean to an absent key would only materialize defaults.
	 *
	 * Untouched keys are never stored: the accessor supplies defaults, the
	 * block editor round-trips every meta key on save, and absent keys stay
	 * protected on revision restore.
	 */
	public static function is_default_write( string $key, int $post_id, array $clean ): bool {
		if ( $post_id > 0 && metadata_exists( 'post', $post_id, $key ) ) {
			return false;
		}
		$candidates = [];
		if ( Layout::META_KEY === $key ) {
			$candidates[] = Layout::empty_value();
		} elseif ( isset( self::$keys[ $key ] ) ) {
			$candidates[] = self::$keys[ $key ]->defaults();
		}
		if ( $post_id > 0 ) {
			$tpl = self::template_default( $key, $post_id );
			if ( null !== $tpl ) {
				$candidates[] = $tpl;
			}
		}
		foreach ( $candidates as $candidate ) {
			if ( $candidate === $clean ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Pre-sanitizes section meta in REST requests before schema validation,
	 * so a mirrored object with loose types never 400s after the post fields
	 * were already written. Absent keys carrying only defaults are dropped
	 * from the request (see is_default_write()).
	 */
	public static function rest_pre_insert( $prepared_post, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $prepared_post;
		}
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || empty( $meta ) ) {
			return $prepared_post;
		}
		self::load();
		$post_id = isset( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;
		$changed = false;
		foreach ( $meta as $key => $value ) {
			if ( null === $value ) {
				continue;
			}
			if ( Layout::META_KEY === $key ) {
				$clean = Layout::sanitize( $value );
			} else {
				$def = self::$keys[ $key ] ?? null;
				if ( ! $def ) {
					continue;
				}
				$clean = $def->sanitize( $value );
			}
			$changed = true;
			if ( self::is_default_write( (string) $key, $post_id, $clean ) ) {
				unset( $meta[ $key ] );
				continue;
			}
			$meta[ $key ] = $clean;
		}
		if ( $changed ) {
			$request->set_param( 'meta', $meta );
		}
		return $prepared_post;
	}
}
