<?php
/**
 * Global helper functions (hk9_*), always loaded.
 *
 * The theme calls these behind function_exists() guards so it can run without
 * the plugin; every function here is therefore also wrapped in a guard so the
 * theme's own fallbacks (loaded later) never collide.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Support {

	defined( 'ABSPATH' ) || exit;

	/**
	 * Module entry: nothing to hook; loading this file defines the functions.
	 */
	final class Helpers {

		/** The 26 lucide icon names used by the reference design (fallback list). */
		public const REFERENCE_ICONS = [
			'arrow-right',
			'calendar',
			'check',
			'circle-alert',
			'circle-check',
			'clipboard-check',
			'clock',
			'dog',
			'dollar-sign',
			'file-text',
			'graduation-cap',
			'hand-heart',
			'heart',
			'heart-handshake',
			'heart-pulse',
			'mail',
			'map-pin',
			'menu',
			'phone',
			'phone-call',
			'qr-code',
			'quote',
			'shield-alert',
			'shield-check',
			'users',
			'x',
		];

		public static function register(): void {
			// Functions below are defined at file load; nothing else to do.
		}

		/**
		 * Icon names: the theme's built sprite manifest when present, else the reference list.
		 *
		 * @return string[]
		 */
		public static function icon_names(): array {
			static $cache = null;
			if ( null !== $cache ) {
				return $cache;
			}
			$names = [];
			$paths = [];
			if ( function_exists( 'get_template_directory' ) ) {
				$paths[] = get_template_directory() . '/assets/dist/icons.json';
			}
			if ( defined( 'WP_CONTENT_DIR' ) ) {
				$paths[] = WP_CONTENT_DIR . '/themes/heartland-k9s/assets/dist/icons.json';
			}
			foreach ( array_unique( $paths ) as $path ) {
				if ( ! is_readable( $path ) ) {
					continue;
				}
				$json = file_get_contents( $path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local file.
				$data = is_string( $json ) ? json_decode( $json, true ) : null;
				if ( is_array( $data ) ) {
					// Accept ["name", ...] or {"icons": [...]} or {"name": "<svg>"} shapes.
					if ( isset( $data['icons'] ) && is_array( $data['icons'] ) ) {
						$data = $data['icons'];
					}
					foreach ( $data as $k => $v ) {
						$name = is_string( $v ) && ! is_string( $k ) ? $v : (string) $k;
						if ( is_array( $v ) && isset( $v['name'] ) ) {
							$name = (string) $v['name'];
						}
						if ( '' !== $name && preg_match( '/^[a-z0-9-]+$/', $name ) ) {
							$names[] = $name;
						}
					}
				}
				if ( $names ) {
					break;
				}
			}
			if ( ! $names ) {
				$names = self::REFERENCE_ICONS;
			}
			$names = array_values( array_unique( $names ) );
			sort( $names );
			/**
			 * Filters the list of valid icon names.
			 *
			 * @param string[] $names Icon names.
			 */
			$cache = (array) apply_filters( 'hk9/icons/names', $names );
			return $cache;
		}

		/**
		 * Normalise a link value to the canonical shape.
		 *
		 * @param mixed $link Raw link value.
		 * @return array{label:string,url:string,post_id:int,target:string,rel:string}
		 */
		public static function normalize_link( mixed $link ): array {
			$link = is_array( $link ) ? $link : [];
			$target = ( $link['target'] ?? '_self' ) === '_blank' ? '_blank' : '_self';
			return [
				'label'   => isset( $link['label'] ) ? (string) $link['label'] : '',
				'url'     => isset( $link['url'] ) ? (string) $link['url'] : '',
				'post_id' => isset( $link['post_id'] ) ? (int) $link['post_id'] : 0,
				'target'  => $target,
				'rel'     => isset( $link['rel'] ) ? (string) $link['rel'] : '',
			];
		}

		/**
		 * Resolve a link value to an absolute URL ('' when it points nowhere).
		 */
		public static function link_url( mixed $link ): string {
			$link = self::normalize_link( $link );
			if ( $link['post_id'] > 0 ) {
				$post = get_post( $link['post_id'] );
				if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
					$url = get_permalink( $post );
					if ( is_string( $url ) ) {
						return $url;
					}
				}
				// Fall through to the URL when the post is gone/unpublished.
			}
			$url = trim( $link['url'] );
			if ( '' === $url ) {
				return '';
			}
			if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
				return home_url( $url );
			}
			if ( str_starts_with( $url, '#' ) ) {
				return $url;
			}
			return $url;
		}

		/**
		 * Escaped attribute string for an <a>: href, target and rel.
		 */
		public static function link_attrs( mixed $link ): string {
			$link = self::normalize_link( $link );
			$url  = self::link_url( $link );
			$attrs = [];
			if ( '' !== $url ) {
				$attrs[] = 'href="' . esc_url( $url ) . '"';
			}
			$rel = preg_split( '/\s+/', trim( $link['rel'] ) ) ?: [];
			$rel = array_filter( array_map( 'sanitize_html_class', $rel ) );
			if ( '_blank' === $link['target'] ) {
				$attrs[] = 'target="_blank"';
				$rel[]   = 'noopener';
				$is_external = '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST );
				if ( $is_external ) {
					$rel[] = 'noreferrer';
				}
			}
			$rel = array_values( array_unique( array_filter( $rel ) ) );
			if ( $rel ) {
				$attrs[] = 'rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
			}
			return implode( ' ', $attrs );
		}
	}
}

namespace {

	use HK9\Core\Events\Dates;
	use HK9\Core\Settings\Schema;
	use HK9\Core\Settings\Store;
	use HK9\Core\Support\Helpers;

	/* ---------------------------------------------------------------------
	 * Global functions (theme-facing API). Each is guarded so the theme's own
	 * fallbacks, loaded later, never collide.
	 * ------------------------------------------------------------------ */
	if ( ! function_exists( 'hk9_option' ) ) {
		/**
		 * Read a setting with dot notation, e.g. hk9_option( 'contact.phone_main' ).
		 *
		 * @param string $path    "group.key" (or "group" for the whole group).
		 * @param mixed  $default Fallback when the key is unknown.
		 */
		function hk9_option( string $path, mixed $default = null ): mixed {
			return Store::get( $path, $default );
		}
	}

	if ( ! function_exists( 'hk9_settings_defaults' ) ) {
		/**
		 * The complete default settings array (group => key => value).
		 *
		 * @return array<string, array<string, mixed>>
		 */
		function hk9_settings_defaults(): array {
			return Schema::defaults();
		}
	}

	if ( ! function_exists( 'hk9_link_url' ) ) {
		/**
		 * Resolve a link field value to a URL.
		 *
		 * @param array $link {label,url,post_id,target,rel}.
		 */
		function hk9_link_url( array $link ): string {
			return Helpers::link_url( $link );
		}
	}

	if ( ! function_exists( 'hk9_link_attrs' ) ) {
		/**
		 * Escaped href/target/rel attributes for a link field value.
		 *
		 * @param array $link {label,url,post_id,target,rel}.
		 */
		function hk9_link_attrs( array $link ): string {
			return Helpers::link_attrs( $link );
		}
	}

	if ( ! function_exists( 'hk9_icon_name_list' ) ) {
		/**
		 * Valid icon names (theme sprite manifest or the built-in reference list).
		 *
		 * @return string[]
		 */
		function hk9_icon_name_list(): array {
			return Helpers::icon_names();
		}
	}

	if ( ! function_exists( 'hk9_event_is_upcoming' ) ) {
		/**
		 * Whether the event's end (or start) is in the future, in the event's timezone.
		 */
		function hk9_event_is_upcoming( int|\WP_Post $event ): bool {
			return Dates::is_upcoming( $event );
		}
	}

	if ( ! function_exists( 'hk9_event_datetime_range' ) ) {
		/**
		 * Human-readable date/time range for an event, e.g. "Sat, Jul 25, 2026 · 6:00 pm – 9:00 pm CDT".
		 */
		function hk9_event_datetime_range( int|\WP_Post $event, array $args = [] ): string {
			return Dates::datetime_range( $event, $args );
		}
	}

	if ( ! function_exists( 'hk9_event_dates' ) ) {
		/**
		 * Parsed event date data (start/end DateTimeImmutable in the event timezone, flags).
		 *
		 * @return array{start:?\DateTimeImmutable,end:?\DateTimeImmutable,all_day:bool,time_tbd:bool,timezone:\DateTimeZone,status:string}
		 */
		function hk9_event_dates( int|\WP_Post $event ): array {
			return Dates::get( $event );
		}
	}

	if ( ! function_exists( 'hk9_events_query' ) ) {
		/**
		 * Events by scope ('upcoming' | 'past'), ordered by start (asc for upcoming, desc for past).
		 *
		 * @param array $args {scope, count, offset, featured, return: 'posts'|'ids'}.
		 * @return \WP_Post[]|int[]
		 */
		function hk9_events_query( array $args = [] ): array {
			return Dates::query( $args );
		}
	}
}
