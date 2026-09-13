<?php
/**
 * Gravity Forms provisioning: builds the site's two forms ("Contact" and
 * "Initial Application Inquiry") in Gravity Forms through GFAPI, stores their
 * ids in Settings → Forms and switches the site to the Gravity provider the
 * first time — so a site that installs Gravity Forms (the client's licence;
 * never bundled) gets the same forms as the built-in provider without
 * building them by hand.
 *
 * Entry points (all guarded: nothing runs without Gravity Forms):
 *   - Settings → Forms → "Create the Heartland forms in Gravity Forms" (admin-post `hk9_gravity_provision`, manage_options + nonce)
 *   - `wp hk9 gravity provision [--force]` / `wp hk9 gravity status` (CLI\GravityCommand)
 *   - `hk9/import/finalized` (end of a non-dry import run — after the Options step wrote the recipient
 *     settings) and the first visit to Settings → Forms: provisions automatically when Gravity Forms is
 *     active, both ids are empty and nothing was provisioned before. Forms provisioned before an import
 *     ran (that first visit, or the Create button) keep their notification, but the recipient / sender
 *     values that are still exactly as seeded are refreshed from the imported settings at the end of the run.
 *
 * Idempotency: every provisioned form carries `hk9_provisioned` = {role, version, plugin, time}
 * in its form meta and the ids live in `forms.gravity_contact_form` / `forms.gravity_application_form`.
 * A second run finds the stored (or marked) forms and creates nothing; `--force` updates a live form
 * in place (same id, field ids stable so entries keep their columns; other notifications/confirmations
 * added in Gravity Forms are kept) and re-creates one that was trashed or deleted. A selected form that
 * does not carry the marker for its role (a form the client picked or built by hand, or the Heartland
 * form of the other role) is never touched — not even by `--force` (action `skipped`). Forms are never
 * deleted. `forms.provider` is only set to `gravity` when both ids were empty before the run — a
 * provider chosen by hand is never overridden.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

use HK9\Core\Support\FormProviders;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class GravityProvisioner {

	/**
	 * Bump when the form definitions change (status reports forms built with an older version as drift).
	 * v2: application "I am a" select (was a radio), "Tell us about yourself" label, "Submit Inquiry" button,
	 *     Name field validation message — aligned with Forms\ApplicationForm.
	 */
	public const VERSION = 2;

	/** Form meta key carrying the provisioning marker (inside the Gravity Forms form array). */
	public const META_KEY = 'hk9_provisioned';

	/**
	 * Option recording the last provisioning run: {time, by, user, version, plugin, forms:{role:id}, force,
	 * seeded:{role:{to, fromName, from}}} — `seeded` holds the notification values written from the settings
	 * at that time, so a later import can tell an untouched seed from a value edited in Gravity Forms.
	 */
	public const OPTION = 'hk9_gravity_provision';

	public const ACTION = 'hk9_gravity_provision';
	public const NONCE  = 'hk9_gravity_provision';
	/** Nonce query arg (not `_wpnonce`: the settings form posts its own `_wpnonce`, which would shadow ours in $_REQUEST). */
	public const NONCE_ARG = 'hk9_gravity_nonce';

	/** Stable ids of the notification / confirmation this plugin owns inside each form. */
	public const NOTIFICATION_ID = 'hk9_admin';
	public const CONFIRMATION_ID = 'hk9_default';

	/** Role → settings key. */
	private const SETTING_KEYS = [
		'contact'     => 'gravity_contact_form',
		'application' => 'gravity_application_form',
	];

	/** Stable field ids (entries keep their columns across --force updates). */
	private const CONTACT_FIELDS = [
		'name'    => 1,
		'email'   => 2,
		'subject' => 3,
		'message' => 4,
	];

	private const APPLICATION_FIELDS = [
		'name'       => 1,
		'email'      => 2,
		'phone'      => 3,
		'city'       => 4,
		'state'      => 5,
		'type'       => 6,
		'heard_from' => 7,
		'message'    => 8,
		'consent'    => 9,
	];

	/** Per-request notice from an automatic run (shown once on the screen that triggered it). */
	private static ?array $auto_result = null;

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle_admin_post' ] );
		add_action( 'hk9/import/finalized', [ self::class, 'on_import_finalized' ], 10, 2 );
		add_filter( 'hk9/cli/commands', [ self::class, 'cli_commands' ] );
		if ( is_admin() ) {
			// Priority 5: before Settings\Page::register_fields() (admin_init 10) builds the form pickers, so a first visit already lists the new forms.
			add_action( 'admin_init', [ self::class, 'maybe_auto_provision' ], 5 );
			add_action( 'admin_notices', [ self::class, 'admin_notices' ] );
		}
	}

	/** @param array<string,string|callable> $commands */
	public static function cli_commands( array $commands ): array {
		$commands['hk9 gravity'] = 'HK9\\Core\\CLI\\GravityCommand';
		return $commands;
	}

	/* -----------------------------------------------------------------
	 * Status
	 * -------------------------------------------------------------- */

	/** Whether Gravity Forms is active (GFAPI available). */
	public static function available(): bool {
		return class_exists( 'GFAPI' ) && class_exists( 'GFFormsModel' );
	}

	/** Stored settings id of a role (0 = none). */
	public static function stored_id( string $role ): int {
		return max( 0, (int) Config::get( 'forms.' . self::SETTING_KEYS[ $role ], 0 ) );
	}

	/**
	 * Status snapshot for the admin panel / CLI.
	 *
	 * @return array{
	 *   gravity_active: bool,
	 *   gravity_version: string,
	 *   provider: string,
	 *   last_run: array|null,
	 *   forms: array<string, array{id:int, title:string, state:string, provisioned:bool, version:int, marker_role:string, drift:string[], note:string, candidates:int[]}>,
	 *   needs_provisioning: bool,
	 *   ok: bool
	 * }
	 * `state`: unset | missing | trashed | inactive | ok. `drift` lists what needs attention (trashed,
	 * inactive, deleted, outdated definition, wrong role); `note` is informational (a form picked by
	 * hand rather than provisioned); `marker_role` is the role the form was provisioned for ('' without
	 * a marker). `ok` = both forms present and active with no drift.
	 */
	public static function status(): array {
		$active = self::available();
		$status = [
			'gravity_active'     => $active,
			'gravity_version'    => $active && class_exists( 'GFForms' ) ? (string) \GFForms::$version : '',
			'provider'           => FormProviders::default_provider(),
			'last_run'           => self::last_run(),
			'forms'              => [],
			'needs_provisioning' => false,
			'ok'                 => false,
		];
		$marked = $active ? self::marked_forms() : [];
		$all_ok = $active;
		$empty  = true;
		foreach ( array_keys( self::SETTING_KEYS ) as $role ) {
			$id    = self::stored_id( $role );
			$entry = [
				'id'          => $id,
				'title'       => '',
				'state'       => 0 === $id ? 'unset' : 'missing',
				'provisioned' => false,
				'version'     => 0,
				'marker_role' => '',
				'drift'       => [],
				'note'        => '',
				'candidates'  => array_values( array_map( 'intval', array_keys( array_filter( $marked, static fn( array $m ): bool => $m['role'] === $role ) ) ) ),
			];
			if ( $id > 0 ) {
				$empty = false;
			}
			if ( $active && $id > 0 ) {
				$form = \GFAPI::get_form( $id );
				if ( is_array( $form ) ) {
					$entry['title'] = (string) ( $form['title'] ?? '' );
					$marker         = self::marker( $form );
					if ( ! empty( $form['is_trash'] ) ) {
						$entry['state']   = 'trashed';
						$entry['drift'][] = __( 'form is in the trash — restore it in Gravity Forms or re-create it (Create / --force)', 'heartland-k9s-core' );
					} elseif ( empty( $form['is_active'] ) ) {
						$entry['state']   = 'inactive';
						$entry['drift'][] = __( 'form is inactive — activate it in Gravity Forms (the built-in form is shown meanwhile)', 'heartland-k9s-core' );
					} else {
						$entry['state'] = 'ok';
					}
					if ( null !== $marker ) {
						$entry['provisioned'] = true;
						$entry['version']     = (int) ( $marker['version'] ?? 0 );
						$entry['marker_role'] = (string) ( $marker['role'] ?? '' );
						if ( ( $marker['role'] ?? '' ) !== $role ) {
							/* translators: %s: role name */
							$entry['drift'][] = sprintf( __( 'form was provisioned for the "%s" role', 'heartland-k9s-core' ), (string) ( $marker['role'] ?? '' ) );
						}
						if ( $entry['version'] < self::VERSION ) {
							/* translators: 1: form definition version, 2: current version */
							$entry['drift'][] = sprintf( __( 'built with definition v%1$d, current is v%2$d — update it (Re-create / --force) to pick up the changes', 'heartland-k9s-core' ), $entry['version'], self::VERSION );
						}
					} else {
						$entry['note'] = __( 'not created by Heartland (no hk9_provisioned marker) — a form picked by hand; left as is, also by Re-create / --force', 'heartland-k9s-core' );
					}
				} else {
					$entry['drift'][] = __( 'form no longer exists — re-create it (Create / --force)', 'heartland-k9s-core' );
				}
			}
			if ( 'ok' !== $entry['state'] || [] !== $entry['drift'] ) {
				$all_ok = false;
			}
			$status['forms'][ $role ] = $entry;
		}
		$status['needs_provisioning'] = $active && $empty;
		$status['ok']                 = $all_ok;
		return $status;
	}

	/** Last provisioning run record, or null. */
	public static function last_run(): ?array {
		$run = get_option( self::OPTION, null );
		return is_array( $run ) ? $run : null;
	}

	/** Provisioning marker of a form array, or null. */
	public static function marker( array $form ): ?array {
		$marker = $form[ self::META_KEY ] ?? null;
		return is_array( $marker ) && isset( $marker['role'] ) ? $marker : null;
	}

	/**
	 * Non-trashed forms carrying the provisioning marker: id => marker.
	 *
	 * @return array<int, array>
	 */
	public static function marked_forms(): array {
		if ( ! self::available() ) {
			return [];
		}
		$out   = [];
		$forms = \GFAPI::get_forms( null, false );
		foreach ( is_array( $forms ) ? $forms : [] as $form ) {
			if ( ! is_array( $form ) || ! empty( $form['is_trash'] ) ) {
				continue;
			}
			$marker = self::marker( $form );
			if ( null !== $marker && in_array( (string) $marker['role'], array_keys( self::SETTING_KEYS ), true ) ) {
				$out[ (int) $form['id'] ] = $marker;
			}
		}
		ksort( $out );
		/**
		 * Filters the Gravity Forms forms recognised as Heartland-provisioned (id => marker).
		 * Tests return [] to simulate a site without them.
		 *
		 * @param array<int, array> $out
		 */
		return (array) apply_filters( 'hk9/forms/gravity_marked_forms', $out );
	}

	/* -----------------------------------------------------------------
	 * Provisioning
	 * -------------------------------------------------------------- */

	/**
	 * Create (or, with $force, update / re-create) both forms and store their ids.
	 *
	 * Per-role `action`: created | recreated | kept | updated | drift (trashed/deleted, no --force) |
	 * skipped (the selected form carries no marker for this role — hand-picked or the other role's form;
	 * never touched, --force included).
	 *
	 * @param bool   $force  Update live Heartland forms in place and re-create trashed/deleted ones.
	 * @param string $source Who triggered it ('admin' | 'cli' | 'import' | 'auto').
	 * @return array{forms: array<string, array{role:string, id:int, action:string, message:string}>, provider_set: bool, ids_changed: bool}|WP_Error
	 */
	public static function provision( bool $force = false, string $source = 'admin' ): array|WP_Error {
		if ( ! self::available() ) {
			return new WP_Error( 'hk9_gravity_inactive', __( 'Gravity Forms is not active. Install and activate it first (it is the client\'s licensed plugin and is not bundled).', 'heartland-k9s-core' ) );
		}
		if ( function_exists( 'gf_upgrade' ) && gf_upgrade()->get_submissions_block() ) {
			return new WP_Error( 'hk9_gravity_busy', __( 'Gravity Forms is upgrading its database; try again in a minute.', 'heartland-k9s-core' ) );
		}

		$before_empty = 0 === self::stored_id( 'contact' ) && 0 === self::stored_id( 'application' );
		$marked       = self::marked_forms();
		$previous     = self::last_run();
		$results      = [];
		$new_ids      = [];
		$seeded       = [];

		foreach ( array_keys( self::SETTING_KEYS ) as $role ) {
			$result = self::provision_role( $role, $force, $marked );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$results[ $role ] = $result;
			if ( $result['id'] !== self::stored_id( $role ) ) {
				$new_ids[ self::SETTING_KEYS[ $role ] ] = $result['id'];
			}
			// What the Heartland notification was seeded with: written now, or carried over from the
			// earlier run when this run left the same form alone (refresh_seeded_notifications() compares against it).
			if ( in_array( $result['action'], [ 'created', 'recreated', 'updated' ], true ) ) {
				$seeded[ $role ] = self::notification_seed( $role );
			} elseif ( is_array( $previous['seeded'][ $role ] ?? null ) && (int) ( $previous['forms'][ $role ] ?? 0 ) === $result['id'] ) {
				$seeded[ $role ] = $previous['seeded'][ $role ];
			}
		}

		$update = [];
		if ( [] !== $new_ids ) {
			$update = $new_ids;
		}
		$provider_set = false;
		if ( $before_empty && FormProviders::GRAVITY !== FormProviders::default_provider() ) {
			$update['provider'] = FormProviders::GRAVITY;
			$provider_set       = true;
		}
		if ( [] !== $update ) {
			self::save_settings( $update );
		}
		FormProviders::flush();

		update_option(
			self::OPTION,
			[
				'time'    => gmdate( 'c' ),
				'by'      => $source,
				'user'    => get_current_user_id(),
				'version' => self::VERSION,
				'plugin'  => defined( 'HK9_CORE_VERSION' ) ? HK9_CORE_VERSION : '',
				'forms'   => array_map( static fn( array $r ): int => $r['id'], $results ),
				'force'   => $force,
				'seeded'  => $seeded,
			],
			false
		);

		/**
		 * Fires after the Heartland forms were provisioned in Gravity Forms.
		 *
		 * @param array  $results      role => {role, id, action, message}.
		 * @param bool   $provider_set Whether forms.provider was switched to "gravity".
		 * @param string $source       'admin' | 'cli' | 'import' | 'auto'.
		 */
		do_action( 'hk9/forms/gravity_provisioned', $results, $provider_set, $source );

		return [
			'forms'        => $results,
			'provider_set' => $provider_set,
			'ids_changed'  => [] !== $new_ids,
		];
	}

	/**
	 * @param array<int, array> $marked marked_forms().
	 * @return array{role:string, id:int, action:string, message:string}|WP_Error
	 */
	private static function provision_role( string $role, bool $force, array $marked ): array|WP_Error {
		$id       = self::stored_id( $role );
		$existing = $id > 0 ? \GFAPI::get_form( $id ) : false;
		$existing = is_array( $existing ) ? $existing : null;

		// No usable stored id: adopt a form that already carries our marker for this role (a settings reset, a re-import).
		if ( null === $existing || ! empty( $existing['is_trash'] ) ) {
			foreach ( $marked as $marked_id => $marker ) {
				if ( $marker['role'] === $role && $marked_id !== $id ) {
					$candidate = \GFAPI::get_form( $marked_id );
					if ( is_array( $candidate ) && empty( $candidate['is_trash'] ) ) {
						$existing = $candidate;
						$id       = $marked_id;
						break;
					}
				}
			}
		}

		$definition = self::definition( $role );

		if ( null !== $existing && empty( $existing['is_trash'] ) ) {
			if ( ! $force ) {
				return [
					'role'    => $role,
					'id'      => $id,
					'action'  => 'kept',
					/* translators: 1: form title, 2: form id */
					'message' => sprintf( __( '"%1$s" (#%2$d) already exists — kept as is.', 'heartland-k9s-core' ), (string) $existing['title'], $id ),
				];
			}
			// --force only rewrites a form this plugin built for this role. A selected form without the
			// marker (picked or built by hand in Gravity Forms) or the Heartland form of the other role
			// is left untouched: rewriting it would destroy the client's fields.
			$marker = self::marker( $existing );
			if ( null === $marker || ( $marker['role'] ?? '' ) !== $role ) {
				$why = null === $marker
					? __( 'was not created by Heartland (no hk9_provisioned marker)', 'heartland-k9s-core' )
					/* translators: %s: role name */
					: sprintf( __( 'is the Heartland "%s" form', 'heartland-k9s-core' ), (string) $marker['role'] );
				return [
					'role'    => $role,
					'id'      => $id,
					'action'  => 'skipped',
					/* translators: 1: form title, 2: form id, 3: reason */
					'message' => sprintf( __( '"%1$s" (#%2$d) %3$s — left as is. To use the Heartland definition, clear the selection under Settings → Forms and run Create again.', 'heartland-k9s-core' ), (string) $existing['title'], $id, $why ),
				];
			}
			$updated = self::merge_for_update( $existing, $definition );
			$ok      = \GFAPI::update_form( $updated, $id );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
			if ( empty( $existing['is_active'] ) ) {
				\GFAPI::update_form_property( $id, 'is_active', '1' );
			}
			self::flush_gf_cache( $id );
			return [
				'role'    => $role,
				'id'      => $id,
				'action'  => 'updated',
				/* translators: 1: form title, 2: form id */
				'message' => sprintf( __( '"%1$s" (#%2$d) updated in place (fields, Heartland notification and confirmation; other notifications kept).', 'heartland-k9s-core' ), (string) $definition['title'], $id ),
			];
		}

		if ( $id > 0 && ! $force ) {
			// Stored id points at a trashed or deleted form: never silently replace a form the client removed.
			$state = null !== $existing ? __( 'is in the trash', 'heartland-k9s-core' ) : __( 'no longer exists', 'heartland-k9s-core' );
			return [
				'role'    => $role,
				'id'      => $id,
				'action'  => 'drift',
				/* translators: 1: form id, 2: "is in the trash" / "no longer exists" */
				'message' => sprintf( __( 'Form #%1$d %2$s — restore it in Gravity Forms, or re-create it with Re-create / --force.', 'heartland-k9s-core' ), $id, $state ),
			];
		}

		$new_id = \GFAPI::add_form( $definition, true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;
		self::flush_gf_cache( $new_id );
		return [
			'role'    => $role,
			'id'      => $new_id,
			'action'  => $id > 0 ? 'recreated' : 'created',
			/* translators: 1: form title, 2: form id */
			'message' => sprintf( __( '"%1$s" created as form #%2$d.', 'heartland-k9s-core' ), (string) $definition['title'], $new_id ),
		];
	}

	/**
	 * The canonical definition applied over an existing form: fields, settings and
	 * our notification/confirmation replace the previous ones; notifications and
	 * confirmations added by hand in Gravity Forms are kept (ours becomes the default
	 * confirmation again).
	 */
	private static function merge_for_update( array $existing, array $definition ): array {
		$form = $definition;
		$form['id']        = (int) $existing['id'];
		$form['is_active'] = '1';
		if ( ! empty( $existing['date_created'] ) ) {
			$form['date_created'] = $existing['date_created'];
		}
		if ( ! empty( $existing['entries_grid_meta'] ) ) {
			$form['entries_grid_meta'] = $existing['entries_grid_meta'];
		}

		$notifications = [];
		foreach ( (array) ( $existing['notifications'] ?? [] ) as $nid => $n ) {
			if ( is_array( $n ) && (string) $nid !== self::NOTIFICATION_ID ) {
				$notifications[ (string) $nid ] = $n;
			}
		}
		foreach ( $definition['notifications'] as $n ) {
			$notifications[ (string) $n['id'] ] = $n;
		}
		$form['notifications'] = $notifications;

		$confirmations = [];
		foreach ( (array) ( $existing['confirmations'] ?? [] ) as $cid => $c ) {
			if ( is_array( $c ) && (string) $cid !== self::CONFIRMATION_ID ) {
				$c['isDefault']              = false;
				$confirmations[ (string) $cid ] = $c;
			}
		}
		foreach ( $definition['confirmations'] as $c ) {
			$confirmations[ (string) $c['id'] ] = $c;
		}
		$form['confirmations'] = $confirmations;

		return $form;
	}

	private static function flush_gf_cache( int $form_id ): void {
		if ( class_exists( 'GFFormsModel' ) && method_exists( 'GFFormsModel', 'flush_current_form' ) ) {
			\GFFormsModel::flush_current_form( \GFFormsModel::get_form_cache_key( $form_id ) );
		}
		if ( class_exists( 'GFFormsModel' ) && method_exists( 'GFFormsModel', 'flush_current_forms' ) ) {
			\GFFormsModel::flush_current_forms();
		}
		FormProviders::flush();
	}

	/** @param array<string,mixed> $forms_values forms.* keys to write. */
	private static function save_settings( array $forms_values ): void {
		if ( class_exists( 'HK9\\Core\\Settings\\Store' ) ) {
			\HK9\Core\Settings\Store::update( [ 'forms' => $forms_values ] );
			return;
		}
		$settings = get_option( 'hk9_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];
		$settings['forms'] = array_merge( is_array( $settings['forms'] ?? null ) ? $settings['forms'] : [], $forms_values );
		update_option( 'hk9_settings', $settings, true );
	}

	/* -----------------------------------------------------------------
	 * Form definitions (public so tests / status can compare)
	 * -------------------------------------------------------------- */

	/** Form array for a role ('contact' | 'application'). */
	public static function definition( string $role ): array {
		return 'application' === $role ? self::application_definition() : self::contact_definition();
	}

	/**
	 * Settings shared by both forms. Theme "orbital": Gravity Forms 2.7+'s
	 * default theme is driven by `--gf-*` custom properties and `:where()`
	 * rules of zero specificity, so the theme's `_gravity-forms.scss` can
	 * re-declare the variables on the <form> and override the few fixed
	 * measurements with ordinary selectors; the classic "gravity-theme"
	 * stylesheet uses high-specificity rules that would need `!important`
	 * fights, and "legacy" markup has no field grid.
	 */
	private static function base( string $role, string $title, string $button ): array {
		return [
			'title'                => $title,
			'description'          => '',
			'labelPlacement'       => 'top_label',
			'descriptionPlacement' => 'below',
			'validationPlacement'  => 'below',
			'subLabelPlacement'    => 'above',
			'validationSummary'    => true,
			'requiredIndicator'    => 'asterisk',
			'cssClass'             => 'hk9-gf-form hk9-gf-form--' . $role,
			'theme'                => 'orbital',
			'button'               => [
				'type'     => 'text',
				'text'     => $button,
				'imageUrl' => '',
				'width'    => 'full',
				'location' => 'bottom',
				'layoutGridColumnSpan' => 12,
			],
			'enableHoneypot'       => true,
			'honeypotAction'       => 'abort',
			'enableAnimation'      => false,
			'requireLogin'         => false,
			'requireLoginMessage'  => '',
			'limitEntries'         => false,
			'scheduleForm'         => false,
			'save'                 => [
				'enabled' => false,
				'button'  => [
					'type' => 'link',
					'text' => __( 'Save and Continue Later', 'heartland-k9s-core' ),
				],
			],
			'markupVersion'        => 2,
			'version'              => class_exists( 'GFForms' ) ? (string) \GFForms::$version : '',
			'is_active'            => '1',
			self::META_KEY         => [
				'role'    => $role,
				'version' => self::VERSION,
				'plugin'  => defined( 'HK9_CORE_VERSION' ) ? HK9_CORE_VERSION : '',
				'time'    => gmdate( 'c' ),
			],
		];
	}

	public static function contact_definition(): array {
		$f     = self::CONTACT_FIELDS;
		$form  = self::base( 'contact', __( 'Contact', 'heartland-k9s-core' ), __( 'Send Message', 'heartland-k9s-core' ) );
		$form['fields'] = [
			self::name_field( $f['name'] ),
			self::email_field( $f['email'] ),
			self::select_field( $f['subject'], __( 'Subject', 'heartland-k9s-core' ), Config::subjects(), __( 'Select a subject', 'heartland-k9s-core' ), true ),
			self::textarea_field( $f['message'], __( 'Message', 'heartland-k9s-core' ), __( 'How can we help you?', 'heartland-k9s-core' ) ),
		];
		$form['notifications'] = [
			self::notification(
				'contact',
				sprintf( '[HK9 Contact] {Subject:%1$d} from {Name (First):%2$d.3} {Name (Last):%2$d.6}', $f['subject'], $f['name'] ),
				$f['email']
			),
		];
		$form['confirmations'] = [
			self::message_confirmation( __( 'Message Sent', 'heartland-k9s-core' ), Config::contact_success_text() ),
		];
		return $form;
	}

	public static function application_definition(): array {
		$f    = self::APPLICATION_FIELDS;
		$form = self::base( 'application', __( 'Initial Application Inquiry', 'heartland-k9s-core' ), __( 'Submit Inquiry', 'heartland-k9s-core' ) );

		$five_url  = Config::five_questions_url();
		$ack_text  = __( 'I have read the 5 Questions to Ask Before Partnering With a Service Dog', 'heartland-k9s-core' );
		$ack_label = esc_html( $ack_text );
		if ( '' !== $five_url ) {
			$ack_label = sprintf(
				/* translators: 1: link open tag, 2: link close tag */
				esc_html__( 'I have read the %1$s5 Questions to Ask Before Partnering With a Service Dog%2$s', 'heartland-k9s-core' ),
				'<a href="' . esc_url( $five_url ) . '" target="_blank" rel="noopener">',
				'</a>'
			);
		}

		$form['fields'] = [
			self::name_field( $f['name'] ),
			self::email_field( $f['email'], 'gf_left_half' ),
			[
				'id'          => $f['phone'],
				'type'        => 'phone',
				'label'       => __( 'Phone', 'heartland-k9s-core' ),
				'phoneFormat' => 'international',
				'placeholder' => __( '(555) 555-5555', 'heartland-k9s-core' ),
				'isRequired'  => false,
				'size'        => 'large',
				'cssClass'    => 'gf_right_half',
				'autocompleteAttribute' => 'tel',
				'enableAutocomplete'    => true,
			],
			[
				'id'          => $f['city'],
				'type'        => 'text',
				'label'       => __( 'City', 'heartland-k9s-core' ),
				'placeholder' => '',
				'isRequired'  => false,
				'size'        => 'large',
				'cssClass'    => 'gf_left_half',
				'autocompleteAttribute' => 'address-level2',
				'enableAutocomplete'    => true,
			],
			self::select_field( $f['state'], __( 'State', 'heartland-k9s-core' ), ApplicationForm::states(), __( 'Select a state', 'heartland-k9s-core' ), false, 'gf_right_half' ),
			// Same select as ApplicationForm's `applicant_type` (label, choices, placeholder) — not a radio group.
			self::select_field( $f['type'], __( 'I am a', 'heartland-k9s-core' ), ApplicationForm::applicant_type_options(), __( 'Select one', 'heartland-k9s-core' ), true, 'gf_left_half' ),
			self::select_field( $f['heard_from'], __( 'How did you hear about us?', 'heartland-k9s-core' ), ApplicationForm::heard_from_options(), __( 'Select one (optional)', 'heartland-k9s-core' ), false, 'gf_right_half' ),
			self::textarea_field( $f['message'], __( 'Tell us about yourself', 'heartland-k9s-core' ), __( 'A little about you, your service, and why you are interested in partnering with a service dog.', 'heartland-k9s-core' ) ),
			[
				'id'             => $f['consent'],
				'type'           => 'consent',
				'label'          => __( 'Acknowledgement', 'heartland-k9s-core' ),
				'labelPlacement' => 'hidden_label',
				'checkboxLabel'  => $ack_label,
				'description'    => '',
				'isRequired'     => true,
				'errorMessage'   => __( 'Please confirm that you have read the 5 Questions before submitting.', 'heartland-k9s-core' ),
				'inputs'         => [
					[ 'id' => $f['consent'] . '.1', 'label' => __( 'Consent', 'heartland-k9s-core' ), 'name' => '' ],
					[ 'id' => $f['consent'] . '.2', 'label' => __( 'Text', 'heartland-k9s-core' ), 'name' => '', 'isHidden' => true ],
					[ 'id' => $f['consent'] . '.3', 'label' => __( 'Description', 'heartland-k9s-core' ), 'name' => '', 'isHidden' => true ],
				],
			],
		];

		$form['notifications'] = [
			self::notification(
				'application',
				sprintf( '[HK9 Application Inquiry] {Name (First):%1$d.3} {Name (Last):%1$d.6}', $f['name'] ),
				$f['email']
			),
		];

		$success_url = Config::application_success_url();
		if ( '' !== $success_url ) {
			$form['confirmations'] = [ self::redirect_confirmation( $success_url ) ];
		} else {
			$form['confirmations'] = [ self::message_confirmation( __( 'Inquiry Received', 'heartland-k9s-core' ), __( 'Thank you. Our director will contact you about the next steps and the full application.', 'heartland-k9s-core' ) ) ];
		}
		return $form;
	}

	/* ---- field builders ---- */

	/** Validation message of a required field, worded like the built-in form. */
	private static function required_message( string $label, bool $choice = false ): string {
		return $choice
			/* translators: %s: field label */
			? sprintf( __( 'Please select an option for %s.', 'heartland-k9s-core' ), $label )
			/* translators: %s: field label */
			: sprintf( __( '%s is required.', 'heartland-k9s-core' ), $label );
	}

	/** Advanced Name field (First/Last). Custom validation message: GF's default lists the empty sub-fields in a long sentence the built-in form has no equivalent of. */
	private static function name_field( int $id ): array {
		return [
			'id'                => $id,
			'type'              => 'name',
			'label'             => __( 'Name', 'heartland-k9s-core' ),
			'labelPlacement'    => 'hidden_label',
			'subLabelPlacement' => 'above',
			'nameFormat'        => 'advanced',
			'isRequired'        => true,
			'errorMessage'      => __( 'First Name and Last Name are required.', 'heartland-k9s-core' ),
			'inputs'            => [
				[ 'id' => $id . '.2', 'label' => __( 'Prefix', 'heartland-k9s-core' ), 'name' => '', 'isHidden' => true ],
				[ 'id' => $id . '.3', 'label' => __( 'First', 'heartland-k9s-core' ), 'name' => '', 'customLabel' => __( 'First Name', 'heartland-k9s-core' ), 'placeholder' => __( 'John', 'heartland-k9s-core' ), 'autocompleteAttribute' => 'given-name' ],
				[ 'id' => $id . '.4', 'label' => __( 'Middle', 'heartland-k9s-core' ), 'name' => '', 'isHidden' => true ],
				[ 'id' => $id . '.6', 'label' => __( 'Last', 'heartland-k9s-core' ), 'name' => '', 'customLabel' => __( 'Last Name', 'heartland-k9s-core' ), 'placeholder' => __( 'Doe', 'heartland-k9s-core' ), 'autocompleteAttribute' => 'family-name' ],
				[ 'id' => $id . '.8', 'label' => __( 'Suffix', 'heartland-k9s-core' ), 'name' => '', 'isHidden' => true ],
			],
			'enableAutocomplete' => true,
		];
	}

	private static function email_field( int $id, string $css = '' ): array {
		return [
			'id'                    => $id,
			'type'                  => 'email',
			'label'                 => __( 'Email Address', 'heartland-k9s-core' ),
			'placeholder'           => __( 'john@example.com', 'heartland-k9s-core' ),
			'isRequired'            => true,
			'errorMessage'          => self::required_message( __( 'Email Address', 'heartland-k9s-core' ) ),
			'size'                  => 'large',
			'emailConfirmEnabled'   => false,
			'cssClass'              => $css,
			'autocompleteAttribute' => 'email',
			'enableAutocomplete'    => true,
		];
	}

	/** @param array<string,string> $options value => label. */
	private static function select_field( int $id, string $label, array $options, string $placeholder, bool $required, string $css = '' ): array {
		$choices = [];
		foreach ( $options as $value => $text ) {
			$choices[] = [
				'text'       => (string) $text,
				'value'      => (string) $value,
				'isSelected' => false,
			];
		}
		return [
			'id'                => $id,
			'type'              => 'select',
			'label'             => $label,
			'placeholder'       => $placeholder,
			'isRequired'        => $required,
			'errorMessage'      => $required ? self::required_message( $label, true ) : '',
			'enableChoiceValue' => true,
			'choices'           => $choices,
			'size'              => 'large',
			'cssClass'          => $css,
		];
	}

	/** Textarea without a max length: Gravity Forms would print a character counter under the box, which the reference form has none of. */
	private static function textarea_field( int $id, string $label, string $placeholder, string $error = '' ): array {
		return [
			'id'                => $id,
			'type'              => 'textarea',
			'label'             => $label,
			'placeholder'       => $placeholder,
			'isRequired'        => true,
			'errorMessage'      => '' !== $error ? $error : self::required_message( $label ),
			'size'              => 'medium',
			'useRichTextEditor' => false,
		];
	}

	/* ---- notifications / confirmations ---- */

	/**
	 * Notification values taken from Settings → Forms right now (recipients, sender):
	 * what the Heartland notification is seeded with, and what an import refresh compares against.
	 *
	 * @return array{to:string, fromName:string, from:string}
	 */
	public static function notification_seed( string $role ): array {
		$from       = Config::from();
		$from_email = sanitize_email( (string) Config::get( 'forms.from_email', '' ) );
		return [
			'to'       => implode( ', ', Config::recipients( $role ) ),
			'fromName' => $from['name'],
			'from'     => '' !== $from_email && is_email( $from_email ) ? $from_email : '{admin_email}',
		];
	}

	private static function notification( string $role, string $subject, int $email_field_id ): array {
		$seed = self::notification_seed( $role );
		return [
			'id'                => self::NOTIFICATION_ID,
			'isActive'          => true,
			'name'              => __( 'Admin notification', 'heartland-k9s-core' ),
			'event'             => 'form_submission',
			'toType'            => 'email',
			'to'                => $seed['to'],
			'fromName'          => $seed['fromName'],
			'from'              => $seed['from'],
			'replyTo'           => sprintf( '{Email Address:%d}', $email_field_id ),
			'bcc'               => '',
			'subject'           => $subject,
			'message'           => '{all_fields}',
			'disableAutoformat' => false,
			'enableAttachments' => false,
		];
	}

	private static function message_confirmation( string $heading, string $text ): array {
		$message = '<h2 class="hk9-gf-confirmation__title">' . esc_html( $heading ) . '</h2>';
		$message .= wp_kses( wpautop( $text ), [ 'p' => [], 'strong' => [], 'em' => [], 'br' => [], 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] );
		return [
			'id'                => self::CONFIRMATION_ID,
			'name'              => __( 'Default Confirmation', 'heartland-k9s-core' ),
			'isDefault'         => true,
			'type'              => 'message',
			'message'           => $message,
			'disableAutoformat' => true,
			'url'               => '',
			'pageId'            => '',
			'queryString'       => '',
			'event'             => '',
		];
	}

	private static function redirect_confirmation( string $url ): array {
		return [
			'id'                => self::CONFIRMATION_ID,
			'name'              => __( 'Default Confirmation', 'heartland-k9s-core' ),
			'isDefault'         => true,
			'type'              => 'redirect',
			'message'           => '',
			'disableAutoformat' => false,
			'url'               => $url,
			'pageId'            => '',
			'queryString'       => '',
			'event'             => '',
		];
	}

	/* -----------------------------------------------------------------
	 * Hooks: import, auto-provisioning, admin-post, notices, settings panel
	 * -------------------------------------------------------------- */

	/**
	 * End of a non-dry import run (`hk9/import/finalized`, after the Options step
	 * wrote `forms.*_recipients` / `from_*`): provision when Gravity Forms is active
	 * and neither id is set yet; otherwise refresh the notification values of forms
	 * provisioned before the import that are still exactly as seeded (a first visit
	 * to Settings → Forms on a fresh site seeds them with the admin e-mail fallback).
	 * Failures are logged as warnings; the import itself never fails because of it.
	 *
	 * @param array                     $state Import state.
	 * @param \HK9\Core\Import\Context|null $ctx  Context (for the run log).
	 */
	public static function on_import_finalized( array $state, $ctx = null ): void {
		$log = static function ( string $level, string $message ) use ( $ctx ): void {
			if ( is_object( $ctx ) && method_exists( $ctx, $level ) ) {
				$ctx->{$level}( '', $message );
			}
		};
		if ( ! self::should_auto_provision() ) {
			foreach ( self::refresh_seeded_notifications() as $line ) {
				$log( 'info', 'Gravity Forms: ' . $line );
			}
			return;
		}
		$result = self::provision( false, 'import' );
		if ( is_wp_error( $result ) ) {
			$log( 'warn', 'Gravity Forms: ' . $result->get_error_message() );
			return;
		}
		foreach ( $result['forms'] as $r ) {
			$log( 'info', 'Gravity Forms: ' . $r['message'] );
		}
		if ( $result['provider_set'] ) {
			$log( 'info', 'Gravity Forms: forms.provider set to "gravity".' );
		}
	}

	/**
	 * Re-seed the Heartland notification of each selected, marked form with the current
	 * Settings → Forms values — but only the values (to / fromName / from) that still equal
	 * what the last run seeded: anything edited in Gravity Forms since is left alone.
	 * Records the new seeds. Guarded: nothing happens without Gravity Forms or a recorded run.
	 *
	 * @return string[] One line per form changed (for the run log / CLI); [] when nothing changed.
	 */
	public static function refresh_seeded_notifications(): array {
		if ( ! self::available() ) {
			return [];
		}
		$run    = self::last_run();
		$seeded = is_array( $run['seeded'] ?? null ) ? $run['seeded'] : [];
		if ( [] === $seeded ) {
			return [];
		}
		$lines = [];
		foreach ( array_keys( self::SETTING_KEYS ) as $role ) {
			$id  = self::stored_id( $role );
			$was = $seeded[ $role ] ?? null;
			if ( $id <= 0 || ! is_array( $was ) || (int) ( $run['forms'][ $role ] ?? 0 ) !== $id ) {
				continue;
			}
			$form   = \GFAPI::get_form( $id );
			$marker = is_array( $form ) ? self::marker( $form ) : null;
			if ( null === $marker || ! empty( $form['is_trash'] ) || ( $marker['role'] ?? '' ) !== $role ) {
				continue;
			}
			$notifications = is_array( $form['notifications'] ?? null ) ? $form['notifications'] : [];
			$n             = $notifications[ self::NOTIFICATION_ID ] ?? null;
			if ( ! is_array( $n ) ) {
				continue;
			}
			$now     = self::notification_seed( $role );
			$changed = [];
			foreach ( $now as $key => $value ) {
				$untouched = isset( $was[ $key ] ) && (string) ( $n[ $key ] ?? '' ) === (string) $was[ $key ];
				if ( $untouched && (string) $was[ $key ] !== $value ) {
					$n[ $key ]       = $value;
					$changed[ $key ] = $value;
				}
			}
			if ( [] === $changed ) {
				continue;
			}
			$notifications[ self::NOTIFICATION_ID ] = $n;
			if ( false === \GFFormsModel::update_form_meta( $id, $notifications, 'notifications' ) ) {
				continue;
			}
			self::flush_gf_cache( $id );
			$seeded[ $role ] = array_merge( $was, $changed );
			$parts           = [];
			foreach ( $changed as $key => $value ) {
				$parts[] = $key . ' = ' . $value;
			}
			/* translators: 1: form title, 2: form id, 3: changed values */
			$lines[] = sprintf( __( '"%1$s" (#%2$d): Admin notification re-seeded from the imported settings (%3$s).', 'heartland-k9s-core' ), (string) ( $form['title'] ?? '' ), $id, implode( '; ', $parts ) );
		}
		if ( [] !== $lines ) {
			$run['seeded'] = $seeded;
			update_option( self::OPTION, $run, false );
		}
		return $lines;
	}

	/** Whether an automatic run should happen: Gravity Forms active, both ids empty, never provisioned before. */
	public static function should_auto_provision(): bool {
		if ( ! self::available() ) {
			return false;
		}
		if ( self::stored_id( 'contact' ) > 0 || self::stored_id( 'application' ) > 0 ) {
			return false;
		}
		if ( null !== self::last_run() ) {
			return false; // Provisioned before; the ids were cleared by hand — a manual choice.
		}
		/**
		 * Filters whether the Heartland forms are created in Gravity Forms automatically
		 * (end of an import, first visit to Settings → Forms).
		 *
		 * @param bool $auto Default true.
		 */
		return (bool) apply_filters( 'hk9/forms/gravity_auto_provision', true );
	}

	/**
	 * First visit to Settings → Forms with Gravity Forms active: create the forms.
	 * Deliberately not the Setup & Import screen: it is opened before the import
	 * runs, when the recipient settings the notification is seeded from are not
	 * imported yet — the import's own finalize hook provisions at the right moment.
	 */
	public static function maybe_auto_provision(): void {
		global $pagenow;
		if ( 'admin.php' !== $pagenow || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable
		if ( 'hk9-settings' !== $page || 'forms' !== $tab ) {
			return;
		}
		if ( ! self::should_auto_provision() ) {
			return;
		}
		$result = self::provision( false, 'auto' );
		self::$auto_result = is_wp_error( $result )
			? [ 'kind' => 'error', 'message' => $result->get_error_message() ]
			: [ 'kind' => 'success', 'message' => self::summary( $result ) ];
	}

	/** One-line summary of a provision() result. */
	public static function summary( array $result ): string {
		$parts = [];
		foreach ( $result['forms'] as $r ) {
			$parts[] = $r['message'];
		}
		if ( ! empty( $result['provider_set'] ) ) {
			$parts[] = __( 'The site now shows the Gravity Forms forms (Default form provider: Gravity Forms).', 'heartland-k9s-core' );
		}
		return implode( ' ', $parts );
	}

	/** admin-post.php?action=hk9_gravity_provision (POST, manage_options + nonce). `force=1` updates / re-creates. */
	public static function handle_admin_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'heartland-k9s-core' ), 403 );
		}
		check_admin_referer( self::NONCE, self::NONCE_ARG );
		$force  = ! empty( $_REQUEST['force'] );
		$result = self::provision( $force, 'admin' );
		$notice = is_wp_error( $result )
			? [ 'kind' => 'error', 'message' => $result->get_error_message() ]
			: [ 'kind' => 'success', 'message' => self::summary( $result ) ];
		set_transient( self::notice_key(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( self::settings_url() );
		exit;
	}

	private static function notice_key(): string {
		return 'hk9_gravity_notice_' . get_current_user_id();
	}

	public static function settings_url(): string {
		return add_query_arg( [ 'page' => 'hk9-settings', 'tab' => 'forms' ], admin_url( 'admin.php' ) ) . '#hk9-field-gravity_provision';
	}

	/** Result notices (stored per user after the admin-post redirect, or from an automatic run this request). */
	public static function admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notices = [];
		if ( null !== self::$auto_result ) {
			$notices[] = self::$auto_result;
		}
		$stored = get_transient( self::notice_key() );
		if ( is_array( $stored ) ) {
			delete_transient( self::notice_key() );
			$notices[] = $stored;
		}
		foreach ( $notices as $n ) {
			$class = 'error' === ( $n['kind'] ?? '' ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>' . esc_html__( 'Gravity Forms:', 'heartland-k9s-core' ) . '</strong> ' . esc_html( (string) ( $n['message'] ?? '' ) ) . '</p></div>';
		}
	}

	/**
	 * Settings → Forms panel: status lines + the Create / Re-create button. The
	 * button is a submit with `formaction` so it posts to admin-post.php from
	 * inside the settings form (unsaved changes on the tab are not saved by it).
	 * The settings form carries a hidden `action=update` field (Settings API), so
	 * the button itself is named `action`: a submit button's value is appended
	 * after the hidden fields and wins in $_REQUEST.
	 */
	public static function render_settings_panel(): void {
		$status = self::status();
		$url    = wp_nonce_url( admin_url( 'admin-post.php' ), self::NONCE, self::NONCE_ARG );
		echo '<div class="hk9-gravity-panel" id="hk9-field-gravity_provision">';
		if ( ! $status['gravity_active'] ) {
			echo '<p>' . esc_html__( 'Gravity Forms is not active. Install and activate it (your licence) and come back here: the two Heartland forms are then created automatically, or with the button that appears here.', 'heartland-k9s-core' ) . '</p>';
			echo '</div>';
			return;
		}
		echo '<ul class="hk9-gravity-panel__status">';
		/* translators: %s: Gravity Forms version */
		echo '<li>' . esc_html( sprintf( __( 'Gravity Forms %s is active.', 'heartland-k9s-core' ), $status['gravity_version'] ) ) . '</li>';
		foreach ( $status['forms'] as $role => $f ) {
			$label = 'contact' === $role ? __( 'Contact form', 'heartland-k9s-core' ) : __( 'Application form', 'heartland-k9s-core' );
			if ( 'unset' === $f['state'] ) {
				$line = [] !== $f['candidates']
					/* translators: %s: form ids */
					? sprintf( __( 'not set — a Heartland form already exists (#%s); Create will use it.', 'heartland-k9s-core' ), implode( ', #', $f['candidates'] ) )
					: __( 'not created yet.', 'heartland-k9s-core' );
			} else {
				$edit = admin_url( 'admin.php?page=gf_edit_forms&id=' . $f['id'] );
				/* translators: 1: form title, 2: form id */
				$line = sprintf( __( '"%1$s" (#%2$d)', 'heartland-k9s-core' ), '' !== $f['title'] ? $f['title'] : __( 'Untitled', 'heartland-k9s-core' ), $f['id'] );
				$line = '<a href="' . esc_url( $edit ) . '">' . esc_html( $line ) . '</a>';
				$line .= 'ok' === $f['state'] ? ' — ' . esc_html__( 'OK', 'heartland-k9s-core' ) : '';
				if ( $f['provisioned'] ) {
					/* translators: %d: definition version */
					$line .= ' ' . esc_html( sprintf( __( '(Heartland definition v%d)', 'heartland-k9s-core' ), $f['version'] ) );
				}
				foreach ( $f['drift'] as $d ) {
					$line .= '<br><span class="hk9-gravity-panel__drift">' . esc_html( $d ) . '</span>';
				}
				if ( '' !== $f['note'] ) {
					$line .= '<br><span class="description">' . esc_html( $f['note'] ) . '</span>';
				}
				echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . $line . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				continue;
			}
			echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';

		$any_missing = false;
		$any_drift   = false;
		$force_acts  = false; // Whether --force would touch anything: a Heartland form of the right role (update in place) or a trashed/deleted one (re-create).
		foreach ( $status['forms'] as $role => $f ) {
			if ( 'ok' !== $f['state'] ) {
				$any_missing = true;
			}
			if ( [] !== $f['drift'] ) {
				$any_drift = true;
			}
			if ( in_array( $f['state'], [ 'trashed', 'missing' ], true ) || ( $f['provisioned'] && $f['marker_role'] === $role ) ) {
				$force_acts = true;
			}
		}
		echo '<p class="hk9-gravity-panel__actions">';
		if ( $any_missing ) {
			echo '<button type="submit" name="action" value="' . esc_attr( self::ACTION ) . '" class="button button-secondary" formaction="' . esc_url( $url ) . '" formmethod="post" formnovalidate>' . esc_html__( 'Create the Heartland forms in Gravity Forms', 'heartland-k9s-core' ) . '</button> ';
		}
		// The force button only appears when it can do something; a hand-picked form (no marker) is never rewritten by it.
		if ( $force_acts ) {
			echo '<button type="submit" name="action" value="' . esc_attr( self::ACTION ) . '" class="button' . ( $any_missing ? '' : ' button-secondary' ) . '" formaction="' . esc_url( add_query_arg( 'force', '1', $url ) ) . '" formmethod="post" formnovalidate onclick="return window.confirm(' . esc_attr( wp_json_encode( __( 'Re-create / update the Heartland forms? Forms created by Heartland are updated in place (fields, the Heartland notification and confirmation; entries and other notifications are kept); trashed or deleted forms are created again; a form picked by hand is left as is. Unsaved changes on this tab are not saved.', 'heartland-k9s-core' ) ) ) . ');">' . esc_html( $any_drift ? __( 'Re-create / update the Heartland forms', 'heartland-k9s-core' ) : __( 'Update the Heartland forms (force)', 'heartland-k9s-core' ) ) . '</button>';
		}
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Creates "Contact" and "Initial Application Inquiry" in Gravity Forms with the same fields as the built-in forms, a notification to the recipients above and a confirmation, then selects them here and switches the default provider to Gravity Forms (only when no form was selected yet). Edit fields, notifications and confirmations under Forms afterwards; the recipient settings above only seed the notification. A form you picked by hand is never changed. Never deletes a form.', 'heartland-k9s-core' ) . '</p>';
		echo '</div>';
	}
}
