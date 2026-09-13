<?php
/**
 * SEO plugin detection and output-mode resolution.
 *
 * Modes:
 *  - `full`   — no SEO plugin: the plugin prints title, description, canonical,
 *               robots, Open Graph/Twitter and the complete JSON-LD graph.
 *  - `plugin` — an SEO plugin is active (Slim SEO, Yoast, Rank Math, AIOSEO,
 *               SEOPress, The SEO Framework): nothing they print is printed
 *               here; only site-specific structured data they cannot know
 *               (NGO details, Events, FAQ, team members, DonateAction).
 *  - `off`    — structured data disabled in Settings → SEO (meta tags follow
 *               the same rule as `plugin`: an SEO plugin owns them, otherwise
 *               the theme/plugin prints them).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Seo;

defined( 'ABSPATH' ) || exit;

final class Detector {

	/** @var array<string, array{name:string, test:callable, version:callable}> */
	private static array $known = [];

	/** @var array{slug:string,name:string,version:string}|null|false false = not resolved yet */
	private static array|null|false $detected = false;

	/**
	 * Known SEO plugins (slug => name/test/version).
	 *
	 * @return array<string, array{name:string, test:callable, version:callable}>
	 */
	public static function known(): array {
		if ( self::$known ) {
			return self::$known;
		}
		self::$known = [
			'slim-seo'          => [
				'name'    => 'Slim SEO',
				'test'    => static fn(): bool => defined( 'SLIM_SEO_VER' ),
				'version' => static fn(): string => defined( 'SLIM_SEO_VER' ) ? (string) SLIM_SEO_VER : '',
			],
			'wordpress-seo'     => [
				'name'    => 'Yoast SEO',
				'test'    => static fn(): bool => defined( 'WPSEO_VERSION' ),
				'version' => static fn(): string => defined( 'WPSEO_VERSION' ) ? (string) WPSEO_VERSION : '',
			],
			'seo-by-rank-math'  => [
				'name'    => 'Rank Math',
				'test'    => static fn(): bool => class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ),
				'version' => static fn(): string => defined( 'RANK_MATH_VERSION' ) ? (string) RANK_MATH_VERSION : '',
			],
			'all-in-one-seo-pack' => [
				'name'    => 'All in One SEO',
				'test'    => static fn(): bool => defined( 'AIOSEO_VERSION' ),
				'version' => static fn(): string => defined( 'AIOSEO_VERSION' ) ? (string) AIOSEO_VERSION : '',
			],
			'wp-seopress'       => [
				'name'    => 'SEOPress',
				'test'    => static fn(): bool => defined( 'SEOPRESS_VERSION' ),
				'version' => static fn(): string => defined( 'SEOPRESS_VERSION' ) ? (string) SEOPRESS_VERSION : '',
			],
			'autodescription'   => [
				'name'    => 'The SEO Framework',
				'test'    => static fn(): bool => defined( 'THE_SEO_FRAMEWORK_VERSION' ) || class_exists( 'The_SEO_Framework\\Load' ),
				'version' => static fn(): string => defined( 'THE_SEO_FRAMEWORK_VERSION' ) ? (string) THE_SEO_FRAMEWORK_VERSION : '',
			],
		];
		return self::$known;
	}

	/**
	 * The active SEO plugin, or null.
	 *
	 * @return array{slug:string,name:string,version:string}|null
	 */
	public static function plugin(): ?array {
		if ( false !== self::$detected ) {
			return self::$detected;
		}
		$found = null;
		foreach ( self::known() as $slug => $info ) {
			if ( call_user_func( $info['test'] ) ) {
				$found = [
					'slug'    => $slug,
					'name'    => $info['name'],
					'version' => (string) call_user_func( $info['version'] ),
				];
				break;
			}
		}
		/**
		 * Filters the detected SEO plugin (null = none).
		 *
		 * @param array|null $found {slug, name, version}.
		 */
		$found = apply_filters( 'hk9/seo/detected_plugin', $found );
		/** This filter is documented in src/Privacy/Registry.php */
		$active = (bool) apply_filters( 'hk9/seo/plugin_active', null !== $found );
		if ( $active && null === $found ) {
			$found = [
				'slug'    => 'unknown',
				'name'    => __( 'an SEO plugin', 'heartland-k9s-core' ),
				'version' => '',
			];
		} elseif ( ! $active ) {
			$found = null;
		}
		self::$detected = is_array( $found ) ? $found : null;
		return self::$detected;
	}

	/** Whether an SEO plugin owns titles/meta/canonical/OG/schema. */
	public static function plugin_active(): bool {
		return null !== self::plugin();
	}

	/** The Settings → SEO mode selector value (auto|full|plugin|off). */
	public static function setting(): string {
		$mode = function_exists( 'hk9_option' ) ? hk9_option( 'seo.schema_mode', 'auto' ) : 'auto';
		$mode = is_scalar( $mode ) ? (string) $mode : 'auto';
		return in_array( $mode, [ 'auto', 'full', 'plugin', 'off' ], true ) ? $mode : 'auto';
	}

	/**
	 * Effective mode: full | plugin | off.
	 */
	public static function mode(): string {
		$setting = self::setting();
		$mode    = match ( $setting ) {
			'full'   => 'full',
			'plugin' => 'plugin',
			'off'    => 'off',
			default  => self::plugin_active() ? 'plugin' : 'full',
		};
		/**
		 * Filters the effective SEO output mode.
		 *
		 * @param string $mode    full | plugin | off.
		 * @param string $setting The configured mode (auto | full | plugin | off).
		 */
		$mode = (string) apply_filters( 'hk9/seo/mode', $mode, $setting );
		return in_array( $mode, [ 'full', 'plugin', 'off' ], true ) ? $mode : 'full';
	}

	/**
	 * Whether this plugin owns the head meta tags, robots rules and sitemap:
	 * full mode, or structured data switched off while no SEO plugin is active
	 * ("off" only silences the JSON-LD; forcing "plugin-managed" hands
	 * everything to the SEO plugin).
	 */
	public static function owns_meta(): bool {
		$mode = self::mode();
		return 'full' === $mode || ( 'off' === $mode && ! self::plugin_active() );
	}

	/**
	 * Whether this plugin prints the head meta tags (title parts, description,
	 * canonical, Open Graph, Twitter): owns_meta() and Settings → Advanced →
	 * "Output basic SEO meta tags" on.
	 */
	public static function prints_meta(): bool {
		if ( ! self::owns_meta() ) {
			return false;
		}
		$on = function_exists( 'hk9_option' ) ? (bool) hk9_option( 'advanced.output_seo_meta', true ) : true;
		/**
		 * Filters whether the plugin prints head meta tags.
		 *
		 * @param bool $on Default: full mode and the Advanced switch on.
		 */
		return (bool) apply_filters( 'hk9/seo/prints_meta', $on );
	}

	/** Whether structured data is printed at all (full or plugin mode). */
	public static function prints_schema(): bool {
		return 'off' !== self::mode();
	}

	/** Resets the detection cache (tests). */
	public static function flush(): void {
		self::$detected = false;
	}
}
