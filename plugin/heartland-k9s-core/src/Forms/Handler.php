<?php
/**
 * Forms entry point: registers the admin-post handlers, the frontend script,
 * the REST route and the `hk9_render_form()` template function; runs the shared
 * submission pipeline (anti-spam → validation → store → mail → redirect/result).
 *
 * Result states after a non-JS POST are carried by a short-lived transient
 * (`?hk9_form=<id>&status=sent|error&t=<key>`): the "sent" state is keyed by the
 * single-use token, error states by a fresh random key. No submitted data ever
 * appears in a URL; error states (which hold the typed values for re-rendering)
 * are deleted as soon as they are rendered once.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms {

	defined( 'ABSPATH' ) || exit;

	final class Handler {

		public const ACTION = 'hk9_form_submit';

		private const RESULT_PREFIX = 'hk9_form_res_';
		private const SENT_TTL      = 10 * MINUTE_IN_SECONDS;
		private const ERROR_TTL     = 5 * MINUTE_IN_SECONDS;

		/** @var array<string,AbstractForm>|null */
		private static ?array $forms = null;

		public static function register(): void {
			add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle_admin_post' ] );
			add_action( 'admin_post_nopriv_' . self::ACTION, [ self::class, 'handle_admin_post' ] );
			add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
			add_action( 'template_redirect', [ self::class, 'no_cache_for_results' ] );

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

		/** Result pages must not be cached (state is per token). */
		public static function no_cache_for_results(): void {
			if ( isset( $_GET['hk9_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state flag.
				nocache_headers();
			}
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

			$state = [
				'errors'  => [],
				'values'  => [],
				'sent'    => false,
				'message' => '',
				'code'    => '',
			];

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- state lookup keyed by a random single-use token; no data is written.
			$q_form   = isset( $_GET['hk9_form'] ) ? sanitize_key( wp_unslash( $_GET['hk9_form'] ) ) : '';
			$q_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
			$q_token  = isset( $_GET['t'] ) && is_string( $_GET['t'] ) && preg_match( '/^[a-f0-9]{32}$/', $_GET['t'] ) ? $_GET['t'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by regex.
			// phpcs:enable
			if ( $q_form === $form->id() && '' !== $q_token ) {
				$stored = get_transient( self::RESULT_PREFIX . $q_token );
				if ( is_array( $stored ) && ( $stored['form'] ?? '' ) === $form->id() ) {
					if ( 'sent' === $q_status && 'sent' === ( $stored['status'] ?? '' ) ) {
						$state['sent'] = true;
					} elseif ( 'error' === $q_status && 'error' === ( $stored['status'] ?? '' ) ) {
						$state['errors']  = is_array( $stored['errors'] ?? null ) ? $stored['errors'] : [];
						$state['values']  = is_array( $stored['values'] ?? null ) ? $stored['values'] : [];
						$state['message'] = (string) ( $stored['message'] ?? '' );
						$state['code']    = (string) ( $stored['code'] ?? '' );
						delete_transient( self::RESULT_PREFIX . $q_token ); // consumed: submitted values never linger.
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
			$form = self::form( $form_id );
			if ( null === $form ) {
				return new Result( false, 404, 'unknown_form', __( 'This form is not available.', 'heartland-k9s-core' ) );
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
				$values = 'honeypot' === $blocked['code'] ? [] : $form->validate( $input )['values'];
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
				return new Result( false, 422, 'validation', __( 'Please correct the highlighted fields and try again.', 'heartland-k9s-core' ), $errors, $values, '', $mode, $key );
			}

			Antispam::mark_used( $token );

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
				return new Result( false, 500, 'send_failed', __( 'We could not send your message right now. Please try again in a few minutes.', 'heartland-k9s-core' ), [], $values, '', $mode, $key );
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

			// Error states get their own random key so they never overwrite a stored "sent" state.
			$key = bin2hex( random_bytes( 16 ) );
			set_transient(
				self::RESULT_PREFIX . $key,
				[
					'form'    => $form->id(),
					'status'  => 'error',
					'code'    => $result->code,
					'message' => $result->message,
					'errors'  => $result->errors,
					'values'  => $result->values,
				],
				self::ERROR_TTL
			);

			wp_safe_redirect( self::result_url( $form->id(), self::source_post( $input ), $input, 'error', $key ), 303 );
			exit;
		}

		/* -----------------------------------------------------------------
		 * Helpers
		 * -------------------------------------------------------------- */

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

		/** URL of the originating page with the result state appended (no submitted data). */
		private static function result_url( string $form_id, int $source_post, array $input, string $status, string $key ): string {
			$base = $source_post > 0 ? (string) get_permalink( $source_post ) : '';
			if ( '' === $base ) {
				$referer = wp_get_referer();
				$base    = is_string( $referer ) && '' !== $referer ? $referer : home_url( '/' );
			}
			$base = remove_query_arg( [ 'hk9_form', 'status', 't' ], $base );
			$url  = add_query_arg(
				[
					'hk9_form' => $form_id,
					'status'   => $status,
					't'        => $key,
				],
				$base
			);
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
