<?php
/**
 * Capability model: per-type primitive caps, role grants and dynamic resolution.
 *
 * Roles are granted at activation (so the caps show in role editors) AND resolved
 * dynamically through user_has_cap so a rebuilt role or a later setting change
 * never leaves an administrator locked out.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const VERSION = '1';

	/** Types every Editor may manage. */
	public const EDITORIAL_TYPES = [ 'hk9_story', 'hk9_team', 'hk9_person', 'hk9_partner', 'hk9_campaign', 'hk9_event' ];

	/** Administrator-only types (Editors get hk9_barkode when advanced.editors_manage_registry is on). */
	public const RESTRICTED_TYPES = [ 'hk9_barkode', 'hk9_submission' ];

	/** Singular / plural capability_type pairs. */
	public const CAP_TYPES = [
		'hk9_story'      => [ 'hk9_story', 'hk9_stories' ],
		'hk9_team'       => [ 'hk9_team', 'hk9_teams' ],
		'hk9_person'     => [ 'hk9_person', 'hk9_people' ],
		'hk9_partner'    => [ 'hk9_partner', 'hk9_partners' ],
		'hk9_campaign'   => [ 'hk9_campaign', 'hk9_campaigns' ],
		'hk9_event'      => [ 'hk9_event', 'hk9_events' ],
		'hk9_barkode'    => [ 'hk9_barkode', 'hk9_barkodes' ],
		'hk9_submission' => [ 'hk9_submission', 'hk9_submissions' ],
	];

	/** Site-level caps (administrators). */
	public const ADMIN_CAPS = [ 'hk9_manage_settings', 'hk9_run_import', 'hk9_manage_redirects', 'hk9_view_submissions' ];

	public static function register(): void {
		add_filter( 'user_has_cap', [ self::class, 'user_has_cap' ], 10, 4 );
		add_action( 'admin_init', [ self::class, 'maybe_upgrade' ] );
	}

	public static function activate(): void {
		self::grant();
		update_option( 'hk9_caps_version', self::VERSION, true );
	}

	/** Re-grant after an in-place plugin update (activation hooks do not fire then). */
	public static function maybe_upgrade(): void {
		if ( (string) get_option( 'hk9_caps_version', '0' ) === self::VERSION ) {
			return;
		}
		self::grant();
		update_option( 'hk9_caps_version', self::VERSION, true );
	}

	/**
	 * Primitive capabilities WordPress derives for a capability_type pair.
	 *
	 * @return string[]
	 */
	public static function caps_for_type( string $type ): array {
		if ( ! isset( self::CAP_TYPES[ $type ] ) ) {
			return [];
		}
		[ $s, $p ] = self::CAP_TYPES[ $type ];
		return [
			"edit_{$s}",
			"read_{$s}",
			"delete_{$s}",
			"edit_{$p}",
			"edit_others_{$p}",
			"delete_{$p}",
			"publish_{$p}",
			"read_private_{$p}",
			"delete_private_{$p}",
			"delete_published_{$p}",
			"delete_others_{$p}",
			"edit_private_{$p}",
			"edit_published_{$p}",
			"create_{$p}",
		];
	}

	/**
	 * Every capability this plugin defines.
	 *
	 * @return string[]
	 */
	public static function all_caps(): array {
		$caps = self::ADMIN_CAPS;
		foreach ( array_keys( self::CAP_TYPES ) as $type ) {
			$caps = array_merge( $caps, self::caps_for_type( $type ) );
		}
		return array_values( array_unique( $caps ) );
	}

	/**
	 * Grant caps to the administrator and editor roles (idempotent).
	 */
	public static function grant(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		$editor = get_role( 'editor' );
		if ( $editor ) {
			foreach ( self::EDITORIAL_TYPES as $type ) {
				foreach ( self::caps_for_type( $type ) as $cap ) {
					$editor->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Remove all plugin caps from every role (uninstall purge helper).
	 */
	public static function revoke_all(): void {
		$roles = wp_roles();
		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::all_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Dynamic resolution: administrators always hold every hk9 cap; editor-level users
	 * hold editorial caps, plus registry caps when the setting allows it.
	 *
	 * @param array    $allcaps Effective caps.
	 * @param string[] $caps    Required primitive caps.
	 * @param array    $args    [cap, user_id, ...].
	 * @param \WP_User $user    User.
	 * @return array
	 */
	public static function user_has_cap( array $allcaps, array $caps, array $args, \WP_User $user ): array {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( self::all_caps() as $cap ) {
				$allcaps[ $cap ] = true;
			}
			return $allcaps;
		}
		$is_editor_level = ! empty( $allcaps['edit_others_pages'] ) && ! empty( $allcaps['publish_pages'] );
		if ( ! $is_editor_level ) {
			return $allcaps;
		}
		foreach ( self::EDITORIAL_TYPES as $type ) {
			foreach ( self::caps_for_type( $type ) as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}
		if ( function_exists( 'hk9_option' ) && hk9_option( 'advanced.editors_manage_registry', false ) ) {
			foreach ( self::caps_for_type( 'hk9_barkode' ) as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}
}
