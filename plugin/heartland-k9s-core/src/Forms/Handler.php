<?php
/**
 * Forms entry point: registers the admin-post handlers, the frontend script,
 * the REST route and the `hk9_render_form()` template function; runs the shared
 * submission pipeline (anti-spam → validation → store → mail → redirect/result).
 *
 * Result states after a non-JS POST: the "sent" state and the error states that
 * carry field errors / typed values (validation, duplicate, send_failed — all past
 * the nonce + token checks) are held in a short-lived transient
 * (`?hk9_form=<id>&status=sent|error&t=<key>`; "sent" keyed by the single-use
 * token, errors by a fresh random key, values capped in size, deleted at the end
 * of the request that rendered them). Every other failure (unknown form, nonce,
 * honeypot, token, too fast, expired, rate limited) is stateless: the redirect
 * carries `&code=<code>` and render() maps it to the canned message, so a request
 * that never proved a real form session writes nothing to the database. A per-IP-
 * hash failure counter (Antispam::record_failure) additionally stops persisting
 * any error state once a client has failed FAILURE_LIMIT times in an hour. No
 * submitted data ever appears in a URL. Pages rendering a form and result pages
 * are excluded from page caches (tokens are per visitor).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms {

	defined( 'ABSPATH' ) || exit;

	final class Handler {

		public const ACTION = 'hk9_form_submit';

		public const RESULT_PREFIX = 'hk9_form_res_';
		private const SENT_TTL     = 10 * MINUTE_IN_SECONDS;
		private const ERROR_TTL    = 5 * MINUTE_IN_SECONDS;
		/** Longest value kept in an error-state transient (typed values re-rendered after a no-JS failure). */
		private const STORED_VALUE_MAX = 2000;
		/** Outcomes for which typed values are never persisted (the request never proved a real form session). */
		private const NO_VALUE_CODES = [ 'unknown_form', 'nonce', 'honeypot', 'token', 'expired' ];
		/**
		 * The only failure outcomes that persist a result-state transient on the no-JS path
		 * (they carry field errors and/or the typed values needed to re-render the form).
		 * Everything else redirects with a stateless `code` query arg.
		 */
		private const STATEFUL_CODES = [ 'validation', 'duplicate', 'send_failed' ];

		/** Error-state transient keys consumed during this request (deleted on shutdown, after every render). */
		private static array $consumed = [];

		/** @var array<string,AbstractForm>|null */
		private static ?array $forms = null;

		public static function register(): void {
			add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle_admin_post' ] );
			add_action( 'admin_post_nopriv_' . self::ACTION, [ self::class, 'handle_admin_post' ] );
			add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
			add_action( 'template_redirect', [ self::class, 'no_cache_for_results' ] );
			add_action( 'shutdown', [ self::class, 'purge_consumed' ] );

			if ( class_exists( 'HK9\\Core\\Rest\\Forms' ) ) {
				\HK9\Core\Rest\Forms::register();
			}
		}

		/** All registered forms (id => instance), filterable via `hk9/forms/types` (id => class name). */
		public static function forms(): array {
			if ( null !== self::$forms ) {
				return self::$forms;
			}
			$types = [
				'contact'     => ContactForm::class,
				'application' => ApplicationForm::class,
			];
			/**
			 * Filters the available form types (id => class extending AbstractForm).
			 *
			 * @param array<string,string> $types
			 */
			$types       = (array) apply_filters( 'hk9/forms/types', $types );
			self::$forms = [];
			foreach ( $types as $id => $class ) {
				if ( is_string( $class ) && class_exists( $class ) && is_subclass_of( $class, AbstractForm::class ) ) {
					$instance                         = new $class();
					self::$forms[ $instance->id() ] = $instance;
				}
			}
			return self::$forms;
		}

		public static function form( string $id ): ?AbstractForm {
			$id = sanitize_key( $id );
			return '' !== $id ? ( self::forms()[ $id ] ?? null ) : null;
		}

		/* -----------------------------------------------------------------
		 * Assets
		 * -------------------------------------------------------------- */

		public static function register_assets(): void {
			wp_register_script(
				'hk9-forms',
				HK9_CORE_URL . 'assets/js/forms-frontend.js',
				[],
				HK9_CORE_VERSION,
				[
					'in_footer' => true,
					'strategy'  => 'defer',
				]
			);
			$config = [
				'restNonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'i18n'      => [
					'summaryTitle' => __( 'Please correct the following:', 'heartland-k9s-core' ),
					'required'     => __( '%s is required.', 'heartland-k9s-core' ),
					'select'       => __( 'Please select an option for %s.', 'heartland-k9s-core' ),
					'email'        => __( 'Please enter a valid email address.', 'heartland-k9s-core' ),
					'checkbox'     => __( 'Please confirm: %s', 'heartland-k9s-core' ),
					'generic'      => __( 'Something went wrong while sending. Please try again.', 'heartland-k9s-core' ),
					'sending'      => __( 'Sending your message…', 'heartland-k9s-core' ),
					'sent'         => __( 'Your message has been sent.', 'heartland-k9s-core' ),
					'errorCount'   => __( 'The form has %s error(s). Please review the highlighted fields.', 'heartland-k9s-core' ),
				],
			];
			wp_add_inline_script(
				'hk9-forms',
				'window.HK9 = window.HK9 || {}; window.HK9.forms = ' . wp_json_encode( $config ) . ';',
				'before'
			);
		}

		/**
		 * Result pages (state is per token) and pages that render a form (the
		 * nonce, timestamp and single-use token are baked into the HTML) must
		 * not be served from a full-page cache. Runs before output so the
		 * headers can still be sent; render() repeats the exclusion late.
		 */
		public static function no_cache_for_results(): void {
			if ( isset( $_GET['hk9_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state flag.
				self::do_not_cache();
				return;
			}
			if ( is_singular() && self::page_renders_form( (int) get_queried_object_id() ) ) {
				self::do_not_cache();
			}
		}

		/**
		 * Whether a page renders one of the forms: its template layout contains
		 * a form section (contact `form`, application `form`), or a theme/plugin
		 * says so via `hk9/forms/page_renders_form`.
		 */
		public static function page_renders_form( int $post_id ): bool {
			$renders = false;
			if ( $post_id > 0 && function_exists( 'hk9_sections_layout' ) && class_exists( 'HK9\\Core\\Sections\\Registry' ) ) {
				$template = class_exists( 'HK9\\Core\\Sections\\Accessor' ) ? \HK9\Core\Sections\Accessor::template_for_post( $post_id ) : '';
				foreach ( hk9_sections_layout( $post_id, $template ) as $section_id ) {
					$def = \HK9\Core\Sections\Registry::definition( $template, (string) $section_id );
					if ( $def && in_array( $def->type, [ 'form', 'application_form' ], true ) ) {
						$renders = true;
						break;
					}
				}
			}
			/**
			 * Filters whether a page renders a form (used to exclude it from page caches).
			 *
			 * @param bool $renders
			 * @param int  $post_id
			 */
			return (bool) apply_filters( 'hk9/forms/page_renders_form', $renders, $post_id );
		}

		/** Send no-cache headers (when still possible) and flag the page for caching plugins. */
		private static function do_not_cache(): void {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! headers_sent() ) {
				nocache_headers();
			}
		}

		/** shutdown: error states consumed by a render are deleted once the page has rendered (all copies of the form). */
		public static function purge_consumed(): void {
			foreach ( array_unique( self::$consumed ) as $key ) {
				delete_transient( self::RESULT_PREFIX . $key );
			}
			self::$consumed = [];
		}

		/* -----------------------------------------------------------------
		 * Rendering
		 * -------------------------------------------------------------- */

		/**
		 * Render a form.
		 *
		 * @param string              $id   'contact' | 'application'.
		 * @param array<string,mixed> $args Optional: id (DOM id), class, heading (null=default, ''=none), intro,
		 *                                  notice (null=default, ''=none), success_heading, success_text,
		 *                                  success_url (link array or URL; '' = inline success), post_id,
		 *                                  submit_label, show_five_questions_link (bool, application).
		 */
		public static function render( string $id, array $args = [] ): string {
			$form = self::form( $id );
			if ( null === $form ) {
				return '';
			}
			wp_enqueue_script( 'hk9-forms' );
			self::do_not_cache(); // No-JS path: the tokens in the markup are per visitor.

			$state = [
				'errors'  => [],
				'values'  => [],
				'sent'    => false,
				'message' => '',
				'code'    => '',
			];

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- state lookup keyed by a random single-use token / a canned code; no data is written.
			$q_form   = isset( $_GET['hk9_form'] ) ? sanitize_key( wp_unslash( $_GET['hk9_form'] ) ) : '';
			$q_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
			$q_token  = isset( $_GET['t'] ) && is_string( $_GET['t'] ) && preg_match( '/^[a-f0-9]{32}$/', $_GET['t'] ) ? $_GET['t'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by regex.
			$q_code   = isset( $_GET['code'] ) ? sanitize_key( wp_unslash( $_GET['code'] ) ) : '';
			// phpcs:enable
			if ( $q_form === $form->id() && 'error' === $q_status && '' === $q_token && '' !== $q_code ) {
				// Stateless failure: the code maps to a canned message, nothing was stored.
				$message = self::canned_message( $q_code );
				if ( '' !== $message ) {
					$state['message'] = $message;
					$state['code']    = $q_code;
				}
			} elseif ( $q_form === $form->id() && '' !== $q_token ) {
				$stored = get_transient( self::RESULT_PREFIX . $q_token );
				if ( is_array( $stored ) && ( $stored['form'] ?? '' ) === $form->id() ) {
					if ( 'sent' === $q_status && 'sent' === ( $stored['status'] ?? '' ) ) {
						$state['sent'] = true;
					} elseif ( 'error' === $q_status && 'error' === ( $stored['status'] ?? '' ) ) {
						$state['errors']  = is_array( $stored['errors'] ?? null ) ? $stored['errors'] : [];
						$state['values']  = is_array( $stored['values'] ?? null ) ? $stored['values'] : [];
						$state['message'] = (string) ( $stored['message'] ?? '' );
						$state['code']    = (string) ( $stored['code'] ?? '' );
						self::$consumed[] = $q_token; // consumed: deleted on shutdown so submitted values never linger (and a second copy of the form on the page still renders them).
					}
				}
			}

			$state['tokens'] = Antispam::issue( $form->id() );

			return Renderer::render( $form, $args, $state );
		}

		/* -----------------------------------------------------------------
		 * Processing pipeline (shared by admin-post and REST)
		 * -------------------------------------------------------------- */

		/**
		 * @param string              $form_id Form id.
		 * @param array<string,mixed> $input   Unslashed request data.
		 */
		public static function process( string $form_id, array $input ): Result {
			$result = self::run( $form_id, $input );
			if ( ! $result->ok ) {
				Antispam::record_failure( Antispam::client_ip() );
			}
			return $result;
		}

		/** The pipeline proper (process() wraps it to count failures). */
		private static function run( string $form_id, array $input ): Result {
			$form = self::form( $form_id );
			if ( null === $form ) {
				$o = Antispam::outcome( 'unknown_form' );
				return new Result( false, (int) $o['status'], 'unknown_form', (string) $o['message'] );
			}
			$form_id = $form->id();
			$ip      = Antispam::client_ip();
			$token   = is_string( $input['hk9_token'] ?? null ) ? $input['hk9_token'] : '';
			$key     = Antispam::token_key( $token );
			if ( '' === $key ) {
				$key = bin2hex( random_bytes( 16 ) );
			}
			$success_url = self::success_url( $form, $input );
			$mode        = '' !== $success_url ? 'redirect' : 'inline';

			$blocked = Antispam::check( $form_id, $input, $ip );
			if ( null !== $blocked ) {
				// Typed values are only carried (and later persisted for the no-JS re-render) once the
				// nonce and the token proved a real form session; cheap failures never store anything.
				$values = in_array( (string) $blocked['code'], self::NO_VALUE_CODES, true ) ? [] : $form->validate( $input )['values'];
				return new Result(
					false,
					(int) $blocked['status'],
					(string) $blocked['code'],
					(string) $blocked['message'],
					[],
					$values,
					'',
					$mode,
					$key,
					! empty( $blocked['refresh'] ) ? Antispam::issue( $form_id ) : null
				);
			}

			[ 'values' => $values, 'errors' => $errors ] = $form->validate( $input );
			if ( [] !== $errors ) {
				$o = Antispam::outcome( 'validation' );
				return new Result( false, (int) $o['status'], 'validation', (string) $o['message'], $errors, $values, '', $mode, $key );
			}

			// Claim the single-use token atomically only now (valid + validated); released below when
			// the submission is neither stored nor mailed, so a retry is not answered with "duplicate".
			if ( ! Antispam::claim( $token ) ) {
				$dup = Antispam::duplicate();
				return new Result( false, (int) $dup['status'], (string) $dup['code'], (string) $dup['message'], [], $values, '', $mode, $key );
			}

			$source_post   = self::source_post( $input );
			$submitted_gmt = current_time( 'mysql', true );
			$submission_id = 0;
			if ( Config::store_submissions() ) {
				$submission_id = Submissions::store(
					$form,
					$values,
					[
						'ip_hash'       => Antispam::ip_hash( $ip ),
						'source_post'   => $source_post,
						'submitted_gmt' => $submitted_gmt,
					]
				);
			}

			$sent = Mailer::send(
				$form,
				$values,
				[
					'submitted_at'  => get_date_from_gmt( $submitted_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
					'source_url'    => $source_post > 0 ? (string) get_permalink( $source_post ) : '',
					'submission_id' => $submission_id,
				]
			);
			Submissions::record_mail( $submission_id, $sent );

			if ( ! $sent && 0 === $submission_id ) {
				Antispam::release( $token );
				$o = Antispam::outcome( 'send_failed' );
				return new Result( false, (int) $o['status'], 'send_failed', (string) $o['message'], [], $values, '', $mode, $key, Antispam::issue( $form_id ) );
			}

			Antispam::record_send( $ip );

			/**
			 * Fires after a submission was accepted (stored and/or mailed).
			 *
			 * @param string $form_id
			 * @param array  $values        Validated values.
			 * @param int    $submission_id 0 when storage is disabled.
			 * @param bool   $sent          wp_mail() result.
			 */
			do_action( 'hk9/forms/submitted', $form_id, $values, $submission_id, $sent );

			set_transient(
				self::RESULT_PREFIX . $key,
				[
					'form'   => $form_id,
					'status' => 'sent',
				],
				self::SENT_TTL
			);

			$redirect = '' !== $success_url ? $success_url : self::result_url( $form_id, $source_post, $input, 'sent', $key );

			return new Result( true, 200, 'sent', $form->success_text(), [], [], $redirect, $mode, $key, null, $submission_id );
		}

		/** admin-post.php?action=hk9_form_submit (no-JS path): process, then redirect (303) to the result state. */
		public static function handle_admin_post(): void {
			if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
				wp_safe_redirect( home_url( '/' ), 303 );
				exit;
			}
			$input   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce verified + fields sanitized in process().
			$form_id = sanitize_key( (string) ( $input['hk9_form'] ?? '' ) );
			$result  = self::process( $form_id, is_array( $input ) ? $input : [] );

			if ( $result->ok ) {
				wp_safe_redirect( $result->redirect, 303 );
				exit;
			}

			$form = self::form( $form_id );
			if ( null === $form ) {
				wp_safe_redirect( home_url( '/' ), 303 );
				exit;
			}

			// Stateless outcomes (nothing proved a real form session, or nothing to re-render) and every
			// outcome from a client past the hourly failure limit: no transient, the code rides in the URL.
			if ( ! in_array( $result->code, self::STATEFUL_CODES, true ) || Antispam::too_many_failures( Antispam::client_ip() ) ) {
				wp_safe_redirect( self::result_url( $form->id(), self::source_post( $input ), $input, 'error', '', $result->code ), 303 );
				exit;
			}

			// Error states get their own random key so they never overwrite a stored "sent" state.
			// Values are only present for outcomes past the nonce/token checks (see process()) and are capped.
			$key = bin2hex( random_bytes( 16 ) );
			set_transient(
				self::RESULT_PREFIX . $key,
				[
					'form'    => $form->id(),
					'status'  => 'error',
					'code'    => $result->code,
					'message' => $result->message,
					'errors'  => $result->errors,
					'values'  => self::cap_values( $result->values ),
				],
				self::ERROR_TTL
			);

			wp_safe_redirect( self::result_url( $form->id(), self::source_post( $input ), $input, 'error', $key ), 303 );
			exit;
		}

		/**
		 * Canned message for a failure code carried in the URL ('' when unknown). Stateful codes
		 * can arrive here too (a client past the failure limit, or a reused URL): they get their
		 * generic line without field errors or values.
		 */
		public static function canned_message( string $code ): string {
			return Antispam::message( $code );
		}

		/* -----------------------------------------------------------------
		 * Helpers
		 * -------------------------------------------------------------- */

		/** Shorten stored values so a failed POST can never persist more than a bounded payload. */
		private static function cap_values( array $values ): array {
			foreach ( $values as $k => $v ) {
				if ( is_string( $v ) && mb_strlen( $v ) > self::STORED_VALUE_MAX ) {
					$values[ $k ] = mb_substr( $v, 0, self::STORED_VALUE_MAX );
				} elseif ( ! is_scalar( $v ) ) {
					unset( $values[ $k ] );
				}
			}
			return $values;
		}

		/** Validated source page id (must be a published post), else 0. */
		private static function source_post( array $input ): int {
			$id = (int) ( $input['hk9_post'] ?? 0 );
			return $id > 0 && 'publish' === get_post_status( $id ) ? $id : 0;
		}

		/** Success URL: per-render override (`hk9_success`, same-host only) or the form default. */
		private static function success_url( AbstractForm $form, array $input ): string {
			$override = is_string( $input['hk9_success'] ?? null ) ? trim( $input['hk9_success'] ) : '';
			if ( '' !== $override ) {
				$validated = wp_validate_redirect( esc_url_raw( $override ), '' );
				if ( '' !== $validated ) {
					return $validated;
				}
			}
			return $form->success_url();
		}

		/**
		 * URL of the originating page with the result state appended (no submitted data):
		 * `t=<key>` for a stored state, or `code=<code>` for a stateless canned outcome.
		 */
		private static function result_url( string $form_id, int $source_post, array $input, string $status, string $key, string $code = '' ): string {
			$base = $source_post > 0 ? (string) get_permalink( $source_post ) : '';
			if ( '' === $base ) {
				$referer = wp_get_referer();
				$base    = is_string( $referer ) && '' !== $referer ? $referer : home_url( '/' );
			}
			$base = remove_query_arg( [ 'hk9_form', 'status', 't', 'code' ], $base );
			$args = [
				'hk9_form' => $form_id,
				'status'   => $status,
			];
			if ( '' !== $key ) {
				$args['t'] = $key;
			} else {
				$args['code'] = sanitize_key( $code );
			}
			$url = add_query_arg( $args, $base );
			$anchor = is_string( $input['hk9_anchor'] ?? null ) ? sanitize_html_class( $input['hk9_anchor'] ) : '';
			if ( '' === $anchor ) {
				$anchor = 'hk9-form-' . $form_id;
			}
			return $url . '#' . $anchor;
		}
	}
}

namespace {

	if ( ! function_exists( 'hk9_render_form' ) ) {
		/**
		 * Render a site form ('contact' | 'application') and enqueue its progressive-enhancement script.
		 *
		 * @param string              $form Form id.
		 * @param array<string,mixed> $args See HK9\Core\Forms\Handler::render().
		 * @return string HTML ('' when the form id is unknown).
		 */
		function hk9_render_form( string $form, array $args = [] ): string {
			return HK9\Core\Forms\Handler::render( $form, $args );
		}
	}

	if ( ! function_exists( 'hk9_the_form' ) ) {
		/**
		 * Echo a site form.
		 *
		 * @param string              $form Form id.
		 * @param array<string,mixed> $args See HK9\Core\Forms\Handler::render().
		 */
		function hk9_the_form( string $form, array $args = [] ): void {
			echo hk9_render_form( $form, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped during rendering.
		}
	}
}
