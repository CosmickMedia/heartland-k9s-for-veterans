<?php
/**
 * SEO module bootstrap: detection, per-post fields, head tags, structured
 * data, breadcrumbs and sitemap tweaks — and the theme-facing hk9_seo_*
 * helper functions (declared at the end of this file, guarded).
 *
 * See docs/seo.md.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo {

	defined( 'ABSPATH' ) || exit;

	final class Module {

		private static bool $booted = false;

		public static function register(): void {
			if ( self::$booted ) {
				return;
			}
			self::$booted = true;

			add_action( 'init', [ Image::class, 'register_size' ], 5 );
			Fields::register();
			Head::register();
			Schema::register();
			Sitemap::register();
			add_action( 'wp', [ self::class, 'reset_caches' ], 0 );
		}

		/** The main query is final at `wp`: drop anything computed earlier. */
		public static function reset_caches(): void {
			Context::flush();
			Breadcrumbs::flush();
		}

		/**
		 * Status lines for Settings → SEO (rendered by the `seo.status_note` field).
		 */
		public static function render_settings_note(): void {
			$plugin  = Detector::plugin();
			$setting = Detector::setting();
			$mode    = Detector::mode();
			echo '<div class="hk9-seo-status">';
			if ( $plugin ) {
				echo '<p><strong>' . esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: version */
						__( 'Detected SEO plugin: %1$s %2$s', 'heartland-k9s-core' ),
						$plugin['name'],
						$plugin['version']
					)
				) . '</strong></p>';
			} else {
				echo '<p><strong>' . esc_html__( 'No SEO plugin detected.', 'heartland-k9s-core' ) . '</strong></p>';
			}
			$lines = [
				'full'   => __( 'Full mode: this plugin prints the title, meta description, canonical address, robots directives, Open Graph / Twitter tags, the XML sitemap entries and the complete structured-data graph (Organization, WebSite, WebPage, breadcrumbs, articles, events, FAQ, team, donate action).', 'heartland-k9s-core' ),
				'plugin' => __( 'Plugin-managed mode: the SEO plugin owns titles, descriptions, canonical addresses, robots, social tags, sitemap and the generic structured data. Heartland adds only what it cannot know — the nonprofit details (501(c)(3) status, EIN, address, phone, profiles, donate action), event, FAQ and team structured data — using the same node identifiers so the two merge instead of duplicating.', 'heartland-k9s-core' ),
				'off'    => __( 'Structured data is switched off. Meta tags: an active SEO plugin prints them; without one, the basic tags from Settings → Advanced apply.', 'heartland-k9s-core' ),
			];
			echo '<p>' . esc_html( $lines[ $mode ] ?? '' ) . '</p>';
			if ( 'auto' !== $setting ) {
				echo '<p class="description">' . esc_html__( 'The mode is forced by the selector above; "Automatic" follows the detection.', 'heartland-k9s-core' ) . '</p>';
			}
			if ( 'full' === $mode && $plugin ) {
				echo '<p class="description">' . esc_html__( 'Warning: forcing full mode while an SEO plugin is active prints duplicate title, description, canonical and social tags.', 'heartland-k9s-core' ) . '</p>';
			}
			if ( 'full' === $mode && function_exists( 'hk9_option' ) && ! hk9_option( 'advanced.output_seo_meta', true ) ) {
				echo '<p class="description">' . esc_html__( 'Note: "Output basic SEO meta tags" is off under Settings → Advanced, so only the structured data and sitemap are printed.', 'heartland-k9s-core' ) . '</p>';
			}
			echo '</div>';
		}
	}
}

namespace {

	use HK9\Core\Seo\Breadcrumbs;
	use HK9\Core\Seo\Context;
	use HK9\Core\Seo\Detector;
	use HK9\Core\Seo\Schema;

	if ( ! function_exists( 'hk9_seo_mode' ) ) {
		/**
		 * Effective SEO output mode: full | plugin | off.
		 */
		function hk9_seo_mode(): string {
			return Detector::mode();
		}
	}

	if ( ! function_exists( 'hk9_seo_plugin' ) ) {
		/**
		 * The detected SEO plugin {slug, name, version} or null.
		 */
		function hk9_seo_plugin(): ?array {
			return Detector::plugin();
		}
	}

	if ( ! function_exists( 'hk9_seo_prints_meta' ) ) {
		/**
		 * Whether the plugin prints the head meta tags (full mode + Advanced switch).
		 */
		function hk9_seo_prints_meta(): bool {
			return Detector::prints_meta();
		}
	}

	if ( ! function_exists( 'hk9_breadcrumb_trail' ) ) {
		/**
		 * Breadcrumb trail for the current view: [ {name, url}, … ] (first = Home,
		 * last = the current page). Empty on the front page and 404s.
		 *
		 * @return array<int, array{name:string,url:string}>
		 */
		function hk9_breadcrumb_trail(): array {
			return Breadcrumbs::trail();
		}
	}

	if ( ! function_exists( 'hk9_seo_canonical' ) ) {
		/**
		 * Canonical URL of the current view ('' for search / 404).
		 */
		function hk9_seo_canonical(): string {
			return Context::canonical();
		}
	}

	if ( ! function_exists( 'hk9_seo_description' ) ) {
		/**
		 * Meta description of the current view ('' = none).
		 */
		function hk9_seo_description(): string {
			return Context::description();
		}
	}

	if ( ! function_exists( 'hk9_seo_image' ) ) {
		/**
		 * Social image of the current view {id,url,width,height,alt,mime} or null.
		 */
		function hk9_seo_image(): ?array {
			return Context::image();
		}
	}

	if ( ! function_exists( 'hk9_seo_schema_json' ) ) {
		/**
		 * The JSON-LD graph of the current view as a string (tests / CLI).
		 */
		function hk9_seo_schema_json(): string {
			return Schema::json();
		}
	}
}
