<?php
/**
 * Per-post SEO fields ("Search & social" meta box): SEO title, meta
 * description, social image, noindex, canonical override.
 *
 * Meta keys `hk9_seo_<key>` are registered per post type with typed REST
 * schemas (block editor saves), sanitized with the field framework, tracked in
 * revisions and mirrored into the editor store by assets/js/sections.js
 * (`data-hk9-mirror="fields"`). The values are only read in full mode; when an
 * SEO plugin is active the box shows a note instead of the fields (the SEO
 * plugin's own fields apply) and stored values are left untouched.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

use HK9\Core\Fields\Assets;
use HK9\Core\Fields\Field;
use HK9\Core\Fields\Renderer;
use HK9\Core\Fields\Sanitizer;
use HK9\Core\Fields\Schema;
use HK9\Core\Meta\RevisionGuard;

defined( 'ABSPATH' ) || exit;

final class Fields {

	public const PREFIX       = 'hk9_seo_';
	public const NONCE_FIELD  = 'hk9_seo_nonce';
	public const NONCE_ACTION = 'hk9_seo_';
	public const INPUT        = 'hk9_seo';
	public const BOX_ID       = 'hk9_seo';

	private static bool $hooked = false;

	/** @var array|null Normalized field list. */
	private static ?array $fields = null;

	/** Post types that get the box + meta. */
	public static function post_types(): array {
		/**
		 * Filters the post types with a "Search & social" box.
		 *
		 * @param string[] $types Post types.
		 */
		return (array) apply_filters( 'hk9/seo/post_types', [ 'page', 'post', 'hk9_story', 'hk9_team', 'hk9_campaign', 'hk9_event' ] );
	}

	public static function register(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_action( 'init', [ self::class, 'register_meta' ], 21 );
		add_action( 'add_meta_boxes', [ self::class, 'add_box' ], 10, 2 );
		foreach ( self::post_types() as $type ) {
			add_action( "save_post_{$type}", [ self::class, 'save' ], 10, 3 );
			add_filter( "rest_pre_insert_{$type}", [ self::class, 'rest_pre_insert' ], 10, 2 );
		}
		if ( class_exists( Assets::class ) ) {
			Assets::register();
			Assets::add_post_types( self::post_types() );
		}
	}

	/**
	 * Field definitions (normalized). Labels are built lazily so they may be
	 * requested from init or later only.
	 */
	public static function fields(): array {
		if ( null !== self::$fields ) {
			return self::$fields;
		}
		$defs = [
			[
				'type'      => 'text',
				'key'       => 'title',
				'label'     => __( 'SEO title', 'heartland-k9s-core' ),
				'help'      => __( 'Replaces the browser-tab / search-result title. Leave empty for "Page title – Site name". Aim for 50–60 characters.', 'heartland-k9s-core' ),
				'maxlength' => 120,
			],
			[
				'type'      => 'textarea',
				'key'       => 'description',
				'label'     => __( 'Meta description', 'heartland-k9s-core' ),
				'help'      => __( 'Search-result snippet and social-share text (about 155 characters). When empty, the excerpt, the hero text or the first paragraph is used.', 'heartland-k9s-core' ),
				'rows'      => 3,
				'maxlength' => 320,
			],
			[
				'type'  => 'image',
				'key'   => 'image',
				'label' => __( 'Social image', 'heartland-k9s-core' ),
				'help'  => __( 'Shown when the page is shared (cropped to 1200×630). When empty: the featured image, the hero image or the default from Settings → SEO.', 'heartland-k9s-core' ),
			],
			[
				'type'  => 'toggle',
				'key'   => 'noindex',
				'label' => __( 'Hide from search engines (noindex)', 'heartland-k9s-core' ),
				'help'  => __( 'Adds a noindex robots directive and removes this page from the XML sitemap. Links on it are still followed.', 'heartland-k9s-core' ),
			],
			[
				'type'              => 'text',
				'key'               => 'canonical',
				'label'             => __( 'Canonical URL override', 'heartland-k9s-core' ),
				'help'              => __( 'Only when this content is a copy of another page: the full https:// address search engines should credit instead of this one.', 'heartland-k9s-core' ),
				'placeholder'       => 'https://',
				'sanitize_callback' => [ self::class, 'sanitize_canonical' ],
			],
		];
		/**
		 * Filters the per-post SEO field definitions.
		 *
		 * @param array $defs Field definitions.
		 */
		self::$fields = Field::normalize_list( (array) apply_filters( 'hk9/seo/fields', $defs ) );
		return self::$fields;
	}

	/** One normalized field by key (null when unknown). */
	public static function field( string $key ): ?array {
		foreach ( self::fields() as $field ) {
			if ( $field['key'] === $key ) {
				return $field;
			}
		}
		return null;
	}

	public static function meta_key( string $key ): string {
		return self::PREFIX . $key;
	}

	/** Canonical override: an absolute http(s) URL or ''. */
	public static function sanitize_canonical( mixed $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$url = esc_url_raw( $value, [ 'http', 'https' ] );
		if ( '' === $url || ! preg_match( '#^https?://[^\s/]+#i', $url ) ) {
			return '';
		}
		return $url;
	}

	/** Registers the meta keys for every post type. */
	public static function register_meta(): void {
		foreach ( self::post_types() as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$revisions = post_type_supports( $post_type, 'revisions' );
			foreach ( self::fields() as $field ) {
				$key  = self::meta_key( $field['key'] );
				$type = Field::type( $field['type'] );
				$args = [
					'type'              => $type->rest_type( $field ),
					'description'       => $field['label'],
					'single'            => true,
					'sanitize_callback' => static fn( $value ) => Sanitizer::sanitize_field( $field, $value ),
					'auth_callback'     => [ self::class, 'auth' ],
					'show_in_rest'      => [
						'schema'           => Schema::property( $field ),
						'prepare_callback' => static function ( $value ) use ( $field ) {
							if ( '' === $value || null === $value ) {
								return $field['default'];
							}
							return Sanitizer::sanitize_field( $field, $value );
						},
					],
				];
				if ( $revisions ) {
					$args['revisions_enabled'] = true;
				}
				register_post_meta( $post_type, $key, $args );

				if ( $revisions && class_exists( RevisionGuard::class ) ) {
					RevisionGuard::track(
						$post_type,
						$key,
						$field['label'],
						static fn( $value ) => Sanitizer::sanitize_field( $field, $value ),
						static function ( $value ) use ( $field ): string {
							if ( null === $value || '' === $value ) {
								return '';
							}
							return (string) Field::type( $field['type'] )->format( $field, Sanitizer::sanitize_field( $field, $value ) );
						}
					);
				}
			}
		}
	}

	/** auth_callback: the user must be able to edit the post. */
	public static function auth( $allowed, $meta_key, $post_id, $user_id ): bool {
		return user_can( (int) $user_id, 'edit_post', (int) $post_id );
	}

	/** Pre-sanitizes hk9_seo_* meta in REST requests before schema validation. */
	public static function rest_pre_insert( $prepared_post, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $prepared_post;
		}
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || empty( $meta ) ) {
			return $prepared_post;
		}
		$changed = false;
		foreach ( self::fields() as $field ) {
			$key = self::meta_key( $field['key'] );
			if ( ! array_key_exists( $key, $meta ) || null === $meta[ $key ] ) {
				continue;
			}
			$meta[ $key ] = Sanitizer::sanitize_field( $field, $meta[ $key ] );
			$changed      = true;
		}
		if ( $changed ) {
			$request->set_param( 'meta', $meta );
		}
		return $prepared_post;
	}

	/**
	 * Reads one SEO field value (sanitized, with the field default).
	 */
	public static function get( int $post_id, string $key, mixed $default = null ): mixed {
		$field = self::field( $key );
		if ( ! $field || $post_id <= 0 ) {
			return $default;
		}
		$meta_key = self::meta_key( $key );
		if ( ! metadata_exists( 'post', $post_id, $meta_key ) ) {
			return null !== $default ? $default : $field['default'];
		}
		return Sanitizer::sanitize_field( $field, get_post_meta( $post_id, $meta_key, true ) );
	}

	/** All SEO values of a post keyed by field key. */
	public static function all( int $post_id ): array {
		$out = [];
		foreach ( self::fields() as $field ) {
			$out[ $field['key'] ] = self::get( $post_id, $field['key'] );
		}
		return $out;
	}

	public static function add_box( string $post_type, $post ): void {
		if ( ! in_array( $post_type, self::post_types(), true ) ) {
			return;
		}
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		add_meta_box(
			self::BOX_ID,
			__( 'Search & social', 'heartland-k9s-core' ),
			[ self::class, 'render' ],
			$post_type,
			'normal',
			'default',
			[
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			]
		);
	}

	public static function render( \WP_Post $post ): void {
		$plugin = Detector::plugin();
		if ( ! Detector::owns_meta() ) {
			echo '<div class="hk9-details hk9-seo-box hk9-seo-box--managed" data-hk9-details data-hk9-mirror="none">';
			if ( $plugin ) {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %s: SEO plugin name */
						__( '%s is active and manages this page\'s title, description, canonical address, robots directives and social-sharing tags: use its own fields on this screen. The Heartland fields are hidden while it is active (their saved values are kept).', 'heartland-k9s-core' ),
						$plugin['name']
					)
				) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'The output mode under Heartland → Settings → SEO is set to "Plugin-managed", so these fields are not used.', 'heartland-k9s-core' ) . '</p>';
			}
			echo '</div>';
			return;
		}

		$values = self::all( $post->ID );
		wp_nonce_field( self::NONCE_ACTION . $post->ID, self::NONCE_FIELD );
		$renderer = new Renderer();
		printf(
			'<div class="hk9-details hk9-seo-box" data-hk9-details data-hk9-mirror="fields" data-hk9-meta-prefix="%s">',
			esc_attr( self::PREFIX )
		);
		echo '<input type="hidden" name="' . esc_attr( self::INPUT ) . '[__present]" value="1" />';
		echo $renderer->render_fields( self::fields(), $values, self::INPUT, 'hk9_seo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes.
		echo '</div>';
	}

	/** save_post_{type}: writes every SEO field of the post (never deletes). */
	public static function save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::INPUT ] ) || ! is_array( $_POST[ self::INPUT ] ) ) {
			return;
		}
		$input = wp_unslash( $_POST[ self::INPUT ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		foreach ( self::fields() as $field ) {
			$value = array_key_exists( $field['key'], $input ) ? $input[ $field['key'] ] : null;
			$clean = Sanitizer::sanitize_field( $field, $value );
			update_post_meta( $post_id, self::meta_key( $field['key'] ), wp_slash( $clean ) );
		}
	}

	/** Whether a post is marked noindex through the field. */
	public static function is_noindex( int $post_id ): bool {
		return (bool) self::get( $post_id, 'noindex', false );
	}
}
