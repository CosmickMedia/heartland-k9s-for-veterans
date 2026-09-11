<?php
/**
 * Fathom Analytics: deferred script in wp_head, only when a site ID is configured.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Analytics;

defined( 'ABSPATH' ) || exit;

final class Fathom {

	public const HANDLE = 'hk9-fathom';
	public const SRC    = 'https://cdn.usefathom.com/script.js';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'script_loader_tag', [ self::class, 'tag' ], 10, 3 );
	}

	public static function site_id(): string {
		$id = function_exists( 'hk9_option' ) ? hk9_option( 'analytics.fathom_site_id', '' ) : '';
		$id = is_scalar( $id ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id ) : '';
		/**
		 * Filters the Fathom site ID ('' disables output).
		 *
		 * @param string $id Site ID.
		 */
		return (string) apply_filters( 'hk9/analytics/fathom_site_id', $id );
	}

	public static function enqueue(): void {
		if ( is_admin() || is_preview() || is_customize_preview() ) {
			return;
		}
		$id = self::site_id();
		if ( '' === $id ) {
			return;
		}
		/**
		 * Filters whether the analytics script should load for the current request.
		 *
		 * @param bool $load Default true.
		 */
		if ( ! apply_filters( 'hk9/analytics/load', true ) ) {
			return;
		}
		wp_enqueue_script( self::HANDLE, self::SRC, [], null, [ 'in_footer' => false, 'strategy' => 'defer' ] ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party script, no version.
	}

	/**
	 * Add the data-site attribute (and keep defer).
	 */
	public static function tag( string $tag, string $handle, string $src ): string {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		$id = self::site_id();
		if ( '' === $id || str_contains( $tag, 'data-site=' ) ) {
			return $tag;
		}
		$tag = str_replace( '<script ', '<script data-site="' . esc_attr( $id ) . '" ', $tag );
		if ( ! str_contains( $tag, ' defer' ) ) {
			$tag = str_replace( '<script ', '<script defer ', $tag );
		}
		return $tag;
	}
}
