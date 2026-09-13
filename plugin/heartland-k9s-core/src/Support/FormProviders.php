<?php
/**
 * Form providers: the two form sections (contact `form`, application `form`)
 * can show the plugin's built-in form (default), a Gravity Forms form or any
 * form shortcode. The section value (`provider` = inherit|builtin|gravity|
 * shortcode, `gravity_form_id`, `shortcode`) overrides the site-wide default
 * in Heartland → Settings → Forms (`forms.provider`, `forms.gravity_contact_form`,
 * `forms.gravity_application_form`).
 *
 * Gravity Forms is never required or bundled: every call is guarded and the
 * theme falls back to the built-in form (with an editor-only note) when the
 * chosen provider cannot render.
 *
 * Global helpers (declared below, guarded): hk9_form_providers(),
 * hk9_form_provider(), hk9_render_form_provider(), hk9_form_provider_notice(),
 * hk9_form_provider_no_output(), hk9_gravity_forms_active(),
 * hk9_gravity_forms_list(), hk9_sanitize_form_shortcode().
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Support {

	defined( 'ABSPATH' ) || exit;

	final class FormProviders {

		public const INHERIT   = 'inherit';
		public const BUILTIN   = 'builtin';
		public const GRAVITY   = 'gravity';
		public const SHORTCODE = 'shortcode';

		/** Providers a site/section can select. */
		public const PROVIDERS = [ self::BUILTIN, self::GRAVITY, self::SHORTCODE ];

		/** Built-in form roles (which settings key supplies the default Gravity form). */
		public const ROLES = [ 'contact', 'application' ];

		/** Per-request cache of gravity_forms() (null = not fetched yet). */
		private static ?array $forms_cache = null;

		/** Per-request cache of gravity_form_exists(), form id => bool. */
		private static array $exists_cache = [];

		/** Whether render_gravity() is printing a form right now (Gravity Forms filters only apply inside .hk9-gf). */
		private static bool $rendering = false;

		/**
		 * Helpers are declared when this file loads (booted from Forms\Handler).
		 * Hooks (provisioned forms only — cssClass `hk9-gf-form` — or while
		 * render_gravity() runs): the required-field legend ("* Required"), the
		 * validation summary sentence and list (messages only) and the Name
		 * field's required message, all worded like the built-in form.
		 */
		public static function register(): void {
			add_filter( 'gform_required_legend', [ self::class, 'required_legend' ], 10, 2 );
			add_filter( 'gform_validation_message', [ self::class, 'validation_message' ], 10, 2 );
			add_filter( 'gform_field_validation', [ self::class, 'name_field_validation' ], 10, 4 );
			add_filter( 'gform_form_validation_errors', [ self::class, 'validation_errors_list' ], 10, 2 );
		}

		/**
		 * Validation summary list of a provisioned form: the messages only (the built-in
		 * summary lists "First Name is required."), not Gravity Forms' "Label: message"
		 * — the label repeats the message's own wording and, for the Name field, names
		 * a hidden legend.
		 *
		 * @param array $errors [{field_label, field_selector, message}].
		 * @param array $form   Form.
		 */
		public static function validation_errors_list( $errors, $form = [] ): array {
			if ( ! is_array( $errors ) || ! self::is_hk9_form( $form ) ) {
				return is_array( $errors ) ? $errors : [];
			}
			foreach ( $errors as &$error ) {
				if ( is_array( $error ) ) {
					$error['field_label'] = '';
				}
			}
			unset( $error );
			return $errors;
		}

		/**
		 * Required Name field of a provisioned form: the built-in wording ("First Name is
		 * required." / "Last Name is required." / both) instead of Gravity Forms' sentence,
		 * which always appends "Please complete the following fields: First Name, Last Name."
		 * to the field's own message (GF_Field::set_required_error()). Other fields, other
		 * forms and non-required failures are untouched.
		 *
		 * @param array{is_valid:bool, message:string} $result Validation result.
		 * @param mixed                                $value  Submitted value (input id => value).
		 * @param array                                $form   Form.
		 * @param object                               $field  GF_Field.
		 */
		public static function name_field_validation( $result, $value, $form = [], $field = null ): array {
			$result = is_array( $result ) ? $result : [ 'is_valid' => true, 'message' => '' ];
			if ( ! empty( $result['is_valid'] ) || ! is_object( $field ) || 'name' !== (string) ( $field->type ?? '' ) || empty( $field->isRequired ) || ! self::is_hk9_form( $form ) ) {
				return $result;
			}
			$missing = [];
			foreach ( is_array( $field->inputs ?? null ) ? $field->inputs : [] as $input ) {
				if ( ! is_array( $input ) || ! empty( $input['isHidden'] ) ) {
					continue;
				}
				$id = (string) ( $input['id'] ?? '' );
				$v  = is_array( $value ) ? (string) ( $value[ $id ] ?? '' ) : '';
				if ( '' === trim( $v ) ) {
					$missing[] = (string) ( ! empty( $input['customLabel'] ) ? $input['customLabel'] : ( $input['label'] ?? '' ) );
				}
			}
			$missing = array_values( array_filter( $missing ) );
			if ( [] === $missing ) {
				return $result; // Failed for another reason: keep Gravity Forms' message.
			}
			$result['message'] = 1 === count( $missing )
				/* translators: %s: field label */
				? sprintf( __( '%s is required.', 'heartland-k9s-core' ), $missing[0] )
				/* translators: 1: first field label, 2: second field label */
				: sprintf( __( '%1$s and %2$s are required.', 'heartland-k9s-core' ), $missing[0], implode( ', ', array_slice( $missing, 1 ) ) );
			return $result;
		}

		/** Whether a Gravity form is one of ours (provisioned by Forms\GravityProvisioner: cssClass carries `hk9-gf-form`). */
		public static function is_hk9_form( mixed $form ): bool {
			$css = is_array( $form ) && isset( $form['cssClass'] ) && is_string( $form['cssClass'] ) ? $form['cssClass'] : '';
			return str_contains( ' ' . $css . ' ', ' hk9-gf-form ' );
		}

		/**
		 * Validation summary heading of a provisioned form: the built-in form's
		 * sentence instead of Gravity Forms' default (the list of fields follows).
		 *
		 * @param string $markup Default `<h2 class="gform_submission_error">…</h2>`.
		 * @param array  $form   Form.
		 */
		public static function validation_message( $markup, $form = [] ): string {
			if ( ! self::is_hk9_form( $form ) ) {
				return (string) $markup;
			}
			return '<h2 class="gform_submission_error">' . esc_html__( 'Please correct the highlighted fields and try again.', 'heartland-k9s-core' ) . '</h2>';
		}

		/**
		 * Gravity Forms prints '"*" indicates required fields' above every form;
		 * inside .hk9-gf the note reads like the built-in form ("* Required").
		 *
		 * @param string $legend Default legend.
		 * @param array  $form   Form.
		 */
		public static function required_legend( $legend, $form = [] ): string {
			// The AJAX re-render (validation errors) runs outside render_gravity(): the provisioned
			// forms are recognised by their cssClass so the note stays the same after a failed submit.
			if ( ! self::$rendering && ! self::is_hk9_form( $form ) ) {
				return (string) $legend;
			}
			return '<span class="gfield_required" aria-hidden="true">*</span> ' . esc_html__( 'Required', 'heartland-k9s-core' );
		}

		/** Clears the per-request Gravity Forms caches (after adding/trashing forms in the same request, e.g. tests). */
		public static function flush(): void {
			self::$forms_cache  = null;
			self::$exists_cache = [];
		}

		/** Provider labels (value => label), optionally with the "inherit" option first. */
		public static function labels( bool $with_inherit = false ): array {
			$labels = [
				self::BUILTIN   => __( 'Built-in form (this plugin)', 'heartland-k9s-core' ),
				self::GRAVITY   => __( 'Gravity Forms', 'heartland-k9s-core' ),
				self::SHORTCODE => __( 'Form shortcode', 'heartland-k9s-core' ),
			];
			if ( $with_inherit ) {
				$labels = [ self::INHERIT => __( 'Site default (Settings → Forms)', 'heartland-k9s-core' ) ] + $labels;
			}
			return $labels;
		}

		/** Whether Gravity Forms is active (API + template function available). */
		public static function gravity_active(): bool {
			return class_exists( 'GFAPI' ) && function_exists( 'gravity_form' );
		}

		/**
		 * Active, non-trashed Gravity Forms forms: id => title (from GFAPI::get_forms()).
		 *
		 * Only fetched where a picker can be shown (admin, ajax, REST, CLI) and
		 * only on demand: GFAPI::get_forms() loads every form's meta, so nothing
		 * calls this while definitions load — the select's options callback runs
		 * when a picker is rendered, saved (sanitized) or formatted for a
		 * revision diff, and the result is cached per request. On the frontend
		 * the list is empty and the stored id is validated per form by
		 * gravity_form_exists() instead.
		 *
		 * @return array<int,string>
		 */
		public static function gravity_forms(): array {
			if ( null !== self::$forms_cache ) {
				return self::$forms_cache;
			}
			self::$forms_cache = [];
			if ( ! self::gravity_active() || ! self::can_list_forms() ) {
				return self::$forms_cache;
			}
			$forms = \GFAPI::get_forms( true, false );
			if ( ! is_array( $forms ) ) {
				return self::$forms_cache;
			}
			foreach ( $forms as $form ) {
				$id = (int) ( $form['id'] ?? 0 );
				if ( $id <= 0 ) {
					continue;
				}
				self::$forms_cache[ $id ] = (string) ( $form['title'] ?? '' );
			}
			return self::$forms_cache;
		}

		/**
		 * Select options for a Gravity form picker (ids as string keys; the ''
		 * placeholder is handled by the control). Two guarantees:
		 *
		 * - A stored id that is no longer listed (form trashed/deleted/inactive,
		 *   Gravity Forms deactivated, or a context where the list is not
		 *   fetched) is kept as a labelled "(unavailable)" option, so saving the
		 *   page never silently resets the field. The theme still falls back to
		 *   the built-in form while the id cannot render.
		 * - When no form can be picked because Gravity Forms is inactive or has
		 *   no forms, the '' option carries that status. The options callback
		 *   only runs when a picker is rendered, saved or formatted, so this is
		 *   the live status shown on the panel without any GFAPI call at
		 *   definition load (see gravity_status()).
		 *
		 * @param int $current Stored form id (0 = none).
		 * @return array<string,string>
		 */
		public static function gravity_form_options( int $current = 0 ): array {
			$out    = [];
			$status = self::gravity_status();
			if ( '' !== $status ) {
				$out[''] = $status;
			}
			foreach ( self::gravity_forms() as $id => $title ) {
				/* translators: 1: form title, 2: form id */
				$out[ (string) $id ] = sprintf( __( '%1$s (#%2$d)', 'heartland-k9s-core' ), '' !== $title ? $title : __( 'Untitled form', 'heartland-k9s-core' ), $id );
			}
			if ( $current > 0 && ! isset( $out[ (string) $current ] ) ) {
				/* translators: %d: form id */
				$out[ (string) $current ] = sprintf( __( 'Form #%d (unavailable)', 'heartland-k9s-core' ), $current );
			}
			return $out;
		}

		/**
		 * Live status of the Gravity Forms picker: '' when forms can be picked
		 * (or the list is not fetched in this context), otherwise a short
		 * sentence for the editor (Gravity Forms inactive / no forms yet).
		 */
		public static function gravity_status(): string {
			if ( ! self::gravity_active() ) {
				return __( 'Gravity Forms is not active — the built-in form is shown', 'heartland-k9s-core' );
			}
			if ( self::can_list_forms() && [] === self::gravity_forms() ) {
				return __( 'No Gravity Forms forms yet (Forms → New Form) — the built-in form is shown', 'heartland-k9s-core' );
			}
			return '';
		}

		/** Help text under a Gravity form picker (live: calls GFAPI in admin; use a static string in definitions). */
		public static function gravity_help(): string {
			if ( ! self::gravity_active() ) {
				return __( 'Gravity Forms is not active. Install and activate it to pick a form here; until then the built-in form is shown.', 'heartland-k9s-core' );
			}
			if ( [] === self::gravity_forms() ) {
				return __( 'Gravity Forms is active but has no forms yet (Forms → New Form).', 'heartland-k9s-core' );
			}
			return __( 'Shown when the provider is Gravity Forms. Title and description are hidden; the form submits with AJAX.', 'heartland-k9s-core' );
		}

		/**
		 * Keeps only `[shortcode …]` tags from a value: text outside brackets, HTML
		 * and nested brackets are dropped, so the stored string can only ever run
		 * through do_shortcode(). Idempotent.
		 */
		public static function sanitize_shortcode( mixed $value ): string {
			if ( ! is_scalar( $value ) ) {
				return '';
			}
			$value = sanitize_text_field( (string) $value );
			if ( '' === $value || ! str_contains( $value, '[' ) ) {
				return '';
			}
			if ( ! preg_match_all( '/\[\/?[a-zA-Z0-9_-]+(?:\s[^\[\]<>]*)?\]/', $value, $m ) ) {
				return '';
			}
			return implode( ' ', array_map( 'trim', $m[0] ) );
		}

		/** First shortcode tag name of a (sanitized) shortcode string, '' when none. */
		public static function shortcode_tag( string $shortcode ): string {
			return preg_match( '/^\[([a-zA-Z0-9_-]+)/', trim( $shortcode ), $m ) ? $m[1] : '';
		}

		/** Site-wide default provider from settings (validated). */
		public static function default_provider(): string {
			$provider = self::setting( 'forms.provider', self::BUILTIN );
			$provider = is_scalar( $provider ) ? sanitize_key( (string) $provider ) : self::BUILTIN;
			return in_array( $provider, self::PROVIDERS, true ) ? $provider : self::BUILTIN;
		}

		/** Site-wide default Gravity form id for a role ('contact' | 'application'), 0 = none. */
		public static function default_gravity_form( string $role ): int {
			$role = in_array( $role, self::ROLES, true ) ? $role : 'contact';
			return max( 0, (int) self::setting( 'forms.gravity_' . $role . '_form', 0 ) );
		}

		/**
		 * Resolves the effective provider of a form section.
		 *
		 * @param array  $section Section data (`provider`, `gravity_form_id`, `shortcode`).
		 * @param string $role    'contact' | 'application' (which built-in form / settings default applies).
		 * @return array{provider:string,role:string,gravity_form_id:int,shortcode:string,source:string,available:bool,notice:string}
		 */
		public static function resolve( array $section, string $role ): array {
			$role     = in_array( $role, self::ROLES, true ) ? $role : 'contact';
			$provider = isset( $section['provider'] ) && is_scalar( $section['provider'] ) ? sanitize_key( (string) $section['provider'] ) : self::INHERIT;
			$source   = 'section';
			if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
				$provider = self::default_provider();
				$source   = 'settings';
			}

			$form_id = isset( $section['gravity_form_id'] ) && is_scalar( $section['gravity_form_id'] ) ? (int) $section['gravity_form_id'] : 0;
			if ( $form_id <= 0 ) {
				$form_id = self::default_gravity_form( $role );
			}
			$shortcode = self::sanitize_shortcode( $section['shortcode'] ?? '' );

			$resolved = [
				'provider'        => $provider,
				'role'            => $role,
				'gravity_form_id' => $form_id,
				'shortcode'       => $shortcode,
				'source'          => $source,
				'available'       => true,
				'notice'          => '',
			];

			if ( self::GRAVITY === $provider ) {
				if ( ! self::gravity_active() ) {
					$resolved['available'] = false;
					$resolved['notice']    = __( 'This form is set to Gravity Forms, but Gravity Forms is not active. The built-in form is shown instead.', 'heartland-k9s-core' );
				} elseif ( $form_id <= 0 ) {
					$resolved['available'] = false;
					$resolved['notice']    = __( 'This form is set to Gravity Forms, but no form is selected (section field "Gravity Forms form" or Settings → Forms). The built-in form is shown instead.', 'heartland-k9s-core' );
				} elseif ( ! self::gravity_form_exists( $form_id ) ) {
					$resolved['available'] = false;
					/* translators: %d: form id */
					$resolved['notice'] = sprintf( __( 'This form is set to Gravity Forms form #%d, which does not exist or is inactive. The built-in form is shown instead.', 'heartland-k9s-core' ), $form_id );
				}
			} elseif ( self::SHORTCODE === $provider ) {
				$tag = self::shortcode_tag( $shortcode );
				if ( '' === $tag ) {
					$resolved['available'] = false;
					$resolved['notice']    = __( 'This form is set to a shortcode, but the shortcode field is empty. The built-in form is shown instead.', 'heartland-k9s-core' );
				} elseif ( ! shortcode_exists( $tag ) ) {
					$resolved['available'] = false;
					/* translators: %s: shortcode tag */
					$resolved['notice'] = sprintf( __( 'This form is set to the [%s] shortcode, but no active plugin provides it. The built-in form is shown instead.', 'heartland-k9s-core' ), $tag );
				}
			}

			/**
			 * Filters the resolved form provider of a section.
			 *
			 * @param array  $resolved {provider, role, gravity_form_id, shortcode, source, available, notice}.
			 * @param array  $section  Section data.
			 * @param string $role     Form role.
			 */
			return (array) apply_filters( 'hk9/forms/provider', $resolved, $section, $role );
		}

		/** Whether a Gravity form exists, is active and not trashed (single-form lookup, cached per request). */
		public static function gravity_form_exists( int $form_id ): bool {
			if ( $form_id <= 0 || ! self::gravity_active() ) {
				return false;
			}
			if ( ! isset( self::$exists_cache[ $form_id ] ) ) {
				$form                           = \GFAPI::get_form( $form_id );
				self::$exists_cache[ $form_id ] = is_array( $form ) && ! empty( $form['is_active'] ) && empty( $form['is_trash'] );
			}
			return self::$exists_cache[ $form_id ];
		}

		/** Contexts where a form picker can appear (the list is never fetched for visitors). */
		private static function can_list_forms(): bool {
			return is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI );
		}

		/**
		 * Renders an external provider (Gravity Forms / shortcode). '' for the
		 * built-in provider or when the provider is unavailable — callers render
		 * the built-in form in that case (see resolve()['available']).
		 *
		 * @param array $resolved Value from resolve().
		 * @param array $args     Optional: `id` (wrapper DOM id), `class` (extra wrapper classes).
		 */
		public static function render( array $resolved, array $args = [] ): string {
			if ( empty( $resolved['available'] ) ) {
				return '';
			}
			$provider = (string) ( $resolved['provider'] ?? self::BUILTIN );
			$inner    = '';
			$class    = 'hk9-form-provider hk9-form-provider--' . sanitize_html_class( $provider );
			if ( self::GRAVITY === $provider ) {
				$inner = self::render_gravity( (int) ( $resolved['gravity_form_id'] ?? 0 ) );
				$class .= ' hk9-gf';
			} elseif ( self::SHORTCODE === $provider ) {
				$inner  = self::render_shortcode( (string) ( $resolved['shortcode'] ?? '' ) );
				$class .= ' hk9-shortcode-form';
			}
			if ( '' === trim( $inner ) ) {
				return '';
			}
			if ( ! empty( $args['class'] ) ) {
				$class .= ' ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $args['class'] ) ?: [] ) );
			}
			$id = ! empty( $args['id'] ) ? ' id="' . esc_attr( sanitize_html_class( (string) $args['id'] ) ) . '"' : '';
			return '<div class="' . esc_attr( $class ) . '"' . $id . ' data-hk9-form-provider="' . esc_attr( $provider ) . '">' . $inner . '</div>';
		}

		/**
		 * Marks an external provider that was available but rendered nothing
		 * (render() returned '') as unavailable, with an editor note saying so —
		 * e.g. an enclosing shortcode whose inner content the sanitizer removed,
		 * or a Gravity form whose markup a filter suppressed. Callers then show
		 * the built-in form like for any other unavailable provider. No-op for
		 * the built-in provider or a provider already flagged unavailable.
		 *
		 * @param array $resolved Value from resolve().
		 * @return array Same shape as resolve().
		 */
		public static function no_output( array $resolved ): array {
			$provider = (string) ( $resolved['provider'] ?? self::BUILTIN );
			if ( empty( $resolved['available'] ) || ! in_array( $provider, [ self::GRAVITY, self::SHORTCODE ], true ) ) {
				return $resolved;
			}
			$resolved['available'] = false;
			if ( self::GRAVITY === $provider ) {
				/* translators: %d: form id */
				$resolved['notice'] = sprintf( __( 'Gravity Forms form #%d produced no output (another plugin or a customization may be suppressing it). The built-in form is shown instead.', 'heartland-k9s-core' ), (int) ( $resolved['gravity_form_id'] ?? 0 ) );
			} else {
				/* translators: %s: shortcode tag */
				$resolved['notice'] = sprintf( __( 'The [%s] shortcode produced no output. The built-in form is shown instead.', 'heartland-k9s-core' ), self::shortcode_tag( (string) ( $resolved['shortcode'] ?? '' ) ) );
			}
			return $resolved;
		}

		/**
		 * Gravity Forms markup (no title/description, AJAX on) or '' when it
		 * cannot render. Guarded: safe to call without Gravity Forms.
		 */
		public static function render_gravity( int $form_id ): string {
			if ( $form_id <= 0 || ! function_exists( 'gravity_form' ) ) {
				return '';
			}
			self::$rendering = true;
			try {
				$html = gravity_form( $form_id, false, false, false, null, true, 0, false );
			} finally {
				self::$rendering = false;
			}
			return is_string( $html ) ? $html : '';
		}

		/** Runs a sanitized shortcode string; anything that is not a shortcode was already stripped. */
		public static function render_shortcode( string $shortcode ): string {
			$shortcode = self::sanitize_shortcode( $shortcode );
			if ( '' === $shortcode ) {
				return '';
			}
			return (string) do_shortcode( $shortcode );
		}

		/**
		 * Editor-only note printed next to a fallback form (only users who can edit
		 * pages see it; visitors get nothing).
		 */
		public static function notice_markup( string $notice ): string {
			if ( '' === $notice || ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
				return '';
			}
			return '<p class="hk9-notice hk9-form-provider__notice" role="note">' . esc_html( $notice ) . ' <span class="hk9-form-provider__who">' . esc_html__( '(Only editors see this note.)', 'heartland-k9s-core' ) . '</span></p>';
		}

		/** Settings read that works in any boot state. */
		private static function setting( string $key, mixed $default ): mixed {
			if ( class_exists( 'HK9\\Core\\Forms\\Config' ) ) {
				return \HK9\Core\Forms\Config::get( $key, $default );
			}
			if ( function_exists( 'hk9_option' ) ) {
				return hk9_option( $key, $default );
			}
			return $default;
		}
	}
}

namespace {

	use HK9\Core\Support\FormProviders;

	if ( ! function_exists( 'hk9_form_providers' ) ) {
		/**
		 * Provider labels (value => label).
		 *
		 * @param bool $with_inherit Include the "Site default" option.
		 */
		function hk9_form_providers( bool $with_inherit = false ): array {
			return FormProviders::labels( $with_inherit );
		}
	}

	if ( ! function_exists( 'hk9_gravity_forms_active' ) ) {
		/** Whether Gravity Forms is active. */
		function hk9_gravity_forms_active(): bool {
			return FormProviders::gravity_active();
		}
	}

	if ( ! function_exists( 'hk9_gravity_forms_list' ) ) {
		/**
		 * Active Gravity Forms forms (id => title); [] without Gravity Forms.
		 *
		 * @return array<int,string>
		 */
		function hk9_gravity_forms_list(): array {
			return FormProviders::gravity_forms();
		}
	}

	if ( ! function_exists( 'hk9_sanitize_form_shortcode' ) ) {
		/** Keeps only [shortcode] tags from a string. */
		function hk9_sanitize_form_shortcode( mixed $value ): string {
			return FormProviders::sanitize_shortcode( $value );
		}
	}

	if ( ! function_exists( 'hk9_form_provider' ) ) {
		/**
		 * Effective provider of a form section (section value, else the site default).
		 *
		 * @param array  $section Section data.
		 * @param string $role    'contact' | 'application'.
		 * @return array {provider, role, gravity_form_id, shortcode, source, available, notice}
		 */
		function hk9_form_provider( array $section, string $role = 'contact' ): array {
			return FormProviders::resolve( $section, $role );
		}
	}

	if ( ! function_exists( 'hk9_render_form_provider' ) ) {
		/**
		 * Markup of an external provider (wrapped in .hk9-gf / .hk9-shortcode-form),
		 * '' for the built-in provider or when the provider is unavailable.
		 *
		 * @param array $resolved Value from hk9_form_provider().
		 * @param array $args     Optional `id`, `class`.
		 */
		function hk9_render_form_provider( array $resolved, array $args = [] ): string {
			return FormProviders::render( $resolved, $args );
		}
	}

	if ( ! function_exists( 'hk9_form_provider_notice' ) ) {
		/** Editor-only note for a provider that fell back to the built-in form ('' for visitors). */
		function hk9_form_provider_notice( array $resolved ): string {
			return FormProviders::notice_markup( (string) ( $resolved['notice'] ?? '' ) );
		}
	}

	if ( ! function_exists( 'hk9_form_provider_no_output' ) ) {
		/**
		 * Flags an available external provider whose markup came back empty as
		 * unavailable (editor note set); call when hk9_render_form_provider() returned ''.
		 *
		 * @param array $resolved Value from hk9_form_provider().
		 * @return array Same shape as hk9_form_provider().
		 */
		function hk9_form_provider_no_output( array $resolved ): array {
			return FormProviders::no_output( $resolved );
		}
	}
}
