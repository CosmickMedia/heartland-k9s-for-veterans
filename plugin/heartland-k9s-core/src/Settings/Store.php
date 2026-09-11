<?php
/**
 * Settings store: typed sanitisation, dot-notation reads and a request cache.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Settings;

use HK9\Core\Support\Helpers;

defined( 'ABSPATH' ) || exit;

final class Store {

	/** @var array<string, array<string, mixed>>|null Merged (defaults + saved) cache. */
	private static ?array $cache = null;

	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'register_setting' ] );
		add_action( 'update_option_' . Schema::OPTION, [ self::class, 'flush_cache' ] );
		add_action( 'add_option_' . Schema::OPTION, [ self::class, 'flush_cache' ] );
		add_action( 'delete_option_' . Schema::OPTION, [ self::class, 'flush_cache' ] );
	}

	public static function register_setting(): void {
		register_setting(
			'hk9_settings',
			Schema::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize_submission' ],
				'show_in_rest'      => false,
				'default'           => [],
			]
		);
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Saved option only (no defaults applied).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function raw(): array {
		$raw = get_option( Schema::OPTION, [] );
		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Full settings array: saved values layered over defaults, typed.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$defaults = Schema::defaults();
		$raw      = self::raw();
		$merged   = $defaults;
		foreach ( $defaults as $group => $keys ) {
			if ( ! isset( $raw[ $group ] ) || ! is_array( $raw[ $group ] ) ) {
				continue;
			}
			foreach ( $keys as $key => $default ) {
				if ( array_key_exists( $key, $raw[ $group ] ) ) {
					$merged[ $group ][ $key ] = self::coerce( $group, $key, $raw[ $group ][ $key ], $default );
				}
			}
		}
		/**
		 * Filters the merged settings array.
		 *
		 * @param array $merged group => key => value.
		 */
		self::$cache = (array) apply_filters( 'hk9/settings/all', $merged );
		return self::$cache;
	}

	/**
	 * Dot-notation read: "group.key" or "group".
	 */
	public static function get( string $path, mixed $default = null ): mixed {
		$all   = self::all();
		$parts = explode( '.', $path, 2 );
		$group = $parts[0];
		if ( ! isset( $all[ $group ] ) ) {
			return $default;
		}
		if ( 1 === count( $parts ) ) {
			return $all[ $group ];
		}
		$key = $parts[1];
		if ( ! array_key_exists( $key, $all[ $group ] ) ) {
			return $default;
		}
		$value = $all[ $group ][ $key ];

		// Documented fallbacks.
		if ( 'branding' === $group && 'header_logo' === $key && (int) $value <= 0 ) {
			$value = (int) get_theme_mod( 'custom_logo', 0 );
		} elseif ( 'branding' === $group && 'footer_logo' === $key && (int) $value <= 0 ) {
			$value = self::get( 'branding.header_logo', 0 );
		} elseif ( 'header' === $group && 'cta_link' === $key && self::link_is_empty( $value ) ) {
			$value = $all['links']['donate'] ?? $default;
		}

		/**
		 * Filters a single setting value.
		 *
		 * @param mixed  $value Value.
		 * @param string $path  "group.key".
		 */
		return apply_filters( 'hk9/settings/get', $value, $path );
	}

	/**
	 * Deep-merge a partial array into the saved settings and persist (typed).
	 *
	 * @param array<string, array<string, mixed>> $partial group => key => value.
	 */
	public static function update( array $partial ): bool {
		$current = self::sanitize( self::raw() );
		$clean   = self::sanitize( $partial, $current );
		$result  = update_option( Schema::OPTION, $clean, true );
		self::flush_cache();
		return $result;
	}

	/**
	 * Replace the saved settings entirely (typed).
	 */
	public static function replace( array $settings ): bool {
		$result = update_option( Schema::OPTION, self::sanitize( $settings ), true );
		self::flush_cache();
		return $result;
	}

	/**
	 * Settings-API sanitize callback. The page posts one tab at a time and marks
	 * it with hk9_settings[__groups]; groups not posted keep their saved values.
	 *
	 * @param mixed $input Raw POST array.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sanitize_submission( mixed $input ): array {
		$input = is_array( $input ) ? $input : [];
		if ( ! current_user_can( 'hk9_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			return self::sanitize( self::raw() );
		}
		$posted_groups = [];
		if ( isset( $input['__groups'] ) ) {
			$posted_groups = array_filter( array_map( 'sanitize_key', explode( ',', (string) $input['__groups'] ) ) );
			unset( $input['__groups'] );
		}
		$existing = self::sanitize( self::raw() );
		if ( $posted_groups ) {
			// A posted group is authoritative: unchecked toggles/empty repeaters mean "off"/"none".
			foreach ( $posted_groups as $group ) {
				if ( isset( Schema::defaults()[ $group ] ) ) {
					$input[ $group ] = isset( $input[ $group ] ) && is_array( $input[ $group ] ) ? $input[ $group ] : [];
					$input[ $group ] = self::fill_group( $group, $input[ $group ] );
				}
			}
			return self::sanitize( $input, $existing );
		}
		return self::sanitize( $input, $existing );
	}

	/**
	 * Pure typed sanitiser. Missing keys fall back to $existing (when given) or defaults.
	 * Unknown groups/keys are dropped.
	 *
	 * @param array      $input    Partial or full settings array.
	 * @param array|null $existing Values to keep for keys absent from $input.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sanitize( array $input, ?array $existing = null ): array {
		$defaults = Schema::defaults();
		$types    = Schema::types();
		$out      = [];
		foreach ( $defaults as $group => $keys ) {
			$out[ $group ] = [];
			foreach ( $keys as $key => $default ) {
				$type = $types[ $group ][ $key ] ?? [ 'type' => 'text' ];
				if ( isset( $input[ $group ] ) && is_array( $input[ $group ] ) && array_key_exists( $key, $input[ $group ] ) ) {
					$out[ $group ][ $key ] = self::sanitize_value( $type, $input[ $group ][ $key ], $default );
				} elseif ( null !== $existing && isset( $existing[ $group ] ) && is_array( $existing[ $group ] ) && array_key_exists( $key, $existing[ $group ] ) ) {
					$out[ $group ][ $key ] = self::sanitize_value( $type, $existing[ $group ][ $key ], $default );
				} else {
					$out[ $group ][ $key ] = $default;
				}
			}
		}
		return $out;
	}

	/**
	 * For a posted group, absent toggles/repeaters become false/[] (checkbox semantics).
	 */
	private static function fill_group( string $group, array $values ): array {
		foreach ( Schema::types()[ $group ] ?? [] as $key => $type ) {
			if ( array_key_exists( $key, $values ) ) {
				continue;
			}
			if ( 'toggle' === $type['type'] ) {
				$values[ $key ] = false;
			} elseif ( 'repeater' === $type['type'] ) {
				$values[ $key ] = [];
			}
		}
		return $values;
	}

	/**
	 * Cheap read-time coercion (saved values are already sanitised; this only guards type drift).
	 */
	private static function coerce( string $group, string $key, mixed $value, mixed $default ): mixed {
		$type = Schema::types()[ $group ][ $key ]['type'] ?? 'text';
		return match ( $type ) {
			'toggle'   => (bool) $value,
			'number'   => is_numeric( $value ) ? $value + 0 : $default,
			'image'    => (int) $value,
			'link'     => Helpers::normalize_link( $value ),
			'repeater' => is_array( $value ) ? array_values( $value ) : $default,
			default    => is_scalar( $value ) ? (string) $value : $default,
		};
	}

	/**
	 * Sanitise a single value by field type.
	 */
	public static function sanitize_value( array $type, mixed $value, mixed $default ): mixed {
		switch ( $type['type'] ) {
			case 'toggle':
				if ( is_string( $value ) ) {
					return in_array( strtolower( $value ), [ '1', 'true', 'on', 'yes' ], true );
				}
				return (bool) $value;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return $default;
				}
				$num = $value + 0;
				if ( isset( $type['min'] ) && $num < $type['min'] ) {
					$num = $type['min'];
				}
				if ( isset( $type['max'] ) && $num > $type['max'] ) {
					$num = $type['max'];
				}
				return is_int( $default ) ? (int) round( (float) $num ) : $num;

			case 'select':
				$value   = is_scalar( $value ) ? (string) $value : '';
				$options = $type['options'] ?? [];
				$allowed = array_is_list( $options ) ? $options : array_keys( $options );
				return in_array( $value, array_map( 'strval', $allowed ), true ) ? $value : $default;

			case 'image':
				$id = absint( $value );
				return $id > 0 && 'attachment' === get_post_type( $id ) ? $id : 0;

			case 'color':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				$hex   = sanitize_hex_color( $value );
				if ( ! $hex ) {
					$short = sanitize_hex_color( '#' . ltrim( $value, '#' ) );
					$hex   = $short ?: null;
				}
				if ( $hex && 4 === strlen( $hex ) ) {
					$hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
				}
				return $hex ? strtolower( $hex ) : $default;

			case 'url':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' === $value ) {
					return '';
				}
				$url = esc_url_raw( $value, [ 'http', 'https', 'mailto', 'tel' ] );
				return $url ?: '';

			case 'email':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' === $value ) {
					return '';
				}
				$email = sanitize_email( $value );
				return is_email( $email ) ? $email : '';

			case 'emails':
				$value = is_scalar( $value ) ? (string) $value : '';
				$lines = preg_split( '/[\r\n,;]+/', $value ) ?: [];
				$clean = [];
				foreach ( $lines as $line ) {
					$email = sanitize_email( trim( $line ) );
					if ( $email && is_email( $email ) ) {
						$clean[] = $email;
					}
				}
				return implode( "\n", array_values( array_unique( $clean ) ) );

			case 'textarea':
				return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';

			case 'slug':
				return is_scalar( $value ) ? sanitize_title( (string) $value ) : '';

			case 'code':
				return is_scalar( $value ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ) : '';

			case 'link':
				return self::sanitize_link( $value );

			case 'repeater':
				$rows = [];
				if ( is_array( $value ) ) {
					foreach ( $value as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$clean = [];
						$empty = true;
						foreach ( $type['fields'] ?? [] as $sub_key => $sub_type ) {
							$clean[ $sub_key ] = self::sanitize_value( $sub_type, $row[ $sub_key ] ?? null, '' );
							if ( '' !== $clean[ $sub_key ] && null !== $clean[ $sub_key ] && false !== $clean[ $sub_key ] ) {
								$empty = false;
							}
						}
						if ( ! $empty ) {
							$rows[] = $clean;
						}
					}
				}
				return $rows;

			case 'text':
			default:
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}
	}

	/**
	 * Sanitise a link value {label,url,post_id,target,rel}.
	 *
	 * @return array{label:string,url:string,post_id:int,target:string,rel:string}
	 */
	public static function sanitize_link( mixed $value ): array {
		$link = Helpers::normalize_link( $value );
		$mode = is_array( $value ) && isset( $value['mode'] ) ? (string) $value['mode'] : '';
		if ( 'url' === $mode ) {
			$link['post_id'] = 0;
		} elseif ( 'post' === $mode ) {
			$link['url'] = '';
		}
		$link['label'] = sanitize_text_field( $link['label'] );
		$link['post_id'] = absint( $link['post_id'] );
		if ( $link['post_id'] > 0 && ! get_post( $link['post_id'] ) ) {
			$link['post_id'] = 0;
		}
		$url = trim( $link['url'] );
		if ( '' !== $url ) {
			if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
				$url = '/' . ltrim( wp_sanitize_redirect( $url ), '/' );
			} elseif ( str_starts_with( $url, '#' ) ) {
				$url = '#' . sanitize_title( substr( $url, 1 ) );
			} else {
				$url = esc_url_raw( $url, [ 'http', 'https', 'mailto', 'tel' ] ) ?: '';
			}
		}
		$link['url']    = $url;
		$link['target'] = '_blank' === $link['target'] ? '_blank' : '_self';
		$rel            = preg_split( '/\s+/', trim( $link['rel'] ) ) ?: [];
		$link['rel']    = implode( ' ', array_filter( array_map( 'sanitize_html_class', $rel ) ) );
		return $link;
	}

	private static function link_is_empty( mixed $value ): bool {
		$link = Helpers::normalize_link( $value );
		return $link['post_id'] <= 0 && '' === trim( $link['url'] );
	}
}
