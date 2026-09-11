<?php
/**
 * Sends the notification mail for a validated submission via wp_mail().
 *
 * - Recipients from settings (`forms.{id}_recipients`, admin_email fallback)
 * - From name/email from settings or site defaults
 * - Reply-To = validated submitter email only (no other user input reaches a header)
 * - HTML body + plain-text alternative rendered from templates/emails/*.php
 * - Logs only wp_mail failures (error code/message with addresses redacted; never the content)
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	private static string $alt_body = '';

	private static ?\WP_Error $last_error = null;

	/**
	 * @param AbstractForm        $form    Form definition.
	 * @param array<string,mixed> $values  Validated values.
	 * @param array<string,mixed> $context {submitted_at, source_url, submission_id}
	 * @return bool Whether wp_mail() reported success.
	 */
	public static function send( AbstractForm $form, array $values, array $context = [] ): bool {
		$recipients = $form->recipients();
		if ( [] === $recipients ) {
			return false;
		}

		$from     = Config::from();
		$reply_to = $form->submitter_email( $values );
		$headers  = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %1$s <%2$s>', self::header_name( $from['name'] ), $from['email'] ),
		];
		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: <' . $reply_to . '>';
		}

		/**
		 * Filters the mail headers (array of "Header: value" strings).
		 *
		 * @param string[] $headers
		 * @param string   $form_id
		 */
		$headers = (array) apply_filters( 'hk9/forms/mail_headers', $headers, $form->id() );

		$data = [
			'form_id'      => $form->id(),
			'form_label'   => $form->label(),
			'site_name'    => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'     => home_url( '/' ),
			'submitted_at' => (string) ( $context['submitted_at'] ?? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			'source_url'   => (string) ( $context['source_url'] ?? '' ),
			'admin_url'    => ! empty( $context['submission_id'] ) ? admin_url( 'post.php?post=' . (int) $context['submission_id'] . '&action=edit' ) : '',
			'rows'         => $form->mail_rows( $values ),
			'reply_to'     => $reply_to,
			'subject'      => $form->mail_subject( $values ),
		];

		$html = self::render_template( $form->id(), 'html', $data );
		$text = self::render_template( $form->id(), 'text', $data );
		if ( '' === $html ) {
			$html = '<pre>' . esc_html( $text ) . '</pre>';
		}

		self::$alt_body   = $text;
		self::$last_error = null;

		add_action( 'phpmailer_init', [ self::class, 'attach_alt_body' ], 20 );
		add_action( 'wp_mail_failed', [ self::class, 'capture_failure' ] );

		$sent = (bool) wp_mail( $recipients, $data['subject'], $html, $headers );

		remove_action( 'phpmailer_init', [ self::class, 'attach_alt_body' ], 20 );
		remove_action( 'wp_mail_failed', [ self::class, 'capture_failure' ] );
		self::$alt_body = '';

		if ( ! $sent ) {
			self::log_failure( $form->id(), self::$last_error );
		}

		return $sent;
	}

	/** Adds the plain-text alternative to the outgoing PHPMailer message. */
	public static function attach_alt_body( \PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
		if ( '' !== self::$alt_body ) {
			$phpmailer->AltBody = self::$alt_body;
		}
	}

	public static function capture_failure( \WP_Error $error ): void {
		self::$last_error = $error;
	}

	/**
	 * Locate + render `templates/emails/{form}-{format}.php`, falling back to `submission-{format}.php`.
	 *
	 * @param array<string,mixed> $data Template data (available as `$data`).
	 */
	private static function render_template( string $form_id, string $format, array $data ): string {
		$base       = trailingslashit( HK9_CORE_DIR ) . 'templates/emails/';
		$candidates = [
			$base . sanitize_key( $form_id ) . '-' . $format . '.php',
			$base . 'submission-' . $format . '.php',
		];
		/**
		 * Filters the email template candidates (first existing file wins).
		 *
		 * @param string[] $candidates Absolute paths.
		 * @param string   $form_id
		 * @param string   $format     'html' | 'text'.
		 */
		$candidates = (array) apply_filters( 'hk9/forms/mail_templates', $candidates, $form_id, $format );
		foreach ( $candidates as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				ob_start();
				include $file;
				return (string) ob_get_clean();
			}
		}
		return '';
	}

	/** Make a display name safe for a From header. */
	private static function header_name( string $name ): string {
		$name = preg_replace( '/[\r\n<>"]+/', ' ', $name ) ?? '';
		return trim( preg_replace( '/\s+/', ' ', $name ) ?? '' );
	}

	/** Log a delivery failure without any message content. */
	private static function log_failure( string $form_id, ?\WP_Error $error ): void {
		$detail = 'wp_mail returned false';
		if ( $error instanceof \WP_Error ) {
			$detail = $error->get_error_code() . ': ' . $error->get_error_message();
		}
		// Redact any email address that a transport error may echo back.
		$detail = preg_replace( '/[^\s<>@"]+@[^\s<>@"]+/', '[redacted]', $detail ) ?? $detail;
		error_log( sprintf( '[hk9] Form notification failed (form=%s): %s', sanitize_key( $form_id ), $detail ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
