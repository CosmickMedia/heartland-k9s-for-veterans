<?php
/**
 * Base class for the site forms: field definitions + server validation.
 *
 * Field definition keys:
 *   key, type (text|email|tel|textarea|select|checkbox), label, placeholder, required (bool),
 *   maxlength (int), options (value => label, select), placeholder_option (select),
 *   autocomplete, width ('half'|'full'), rows (textarea), help, label_html (checkbox; kses'd),
 *   email_label (label used in the notification mail; defaults to label).
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

abstract class AbstractForm {

	/** Form id used in URLs, hooks and the submission record (`contact`, `application`). */
	abstract public function id(): string;

	/** Human label shown in admin (e.g. "Contact"). */
	abstract public function label(): string;

	/** @return array<int,array<string,mixed>> */
	abstract protected function define_fields(): array;

	abstract public function submit_label(): string;

	/** Subject prefix, e.g. `[HK9 Contact]`. */
	abstract public function subject_prefix(): string;

	/** Mail subject (without prefix) for validated values. */
	abstract protected function subject_line( array $values ): string;

	public function sending_label(): string {
		return __( 'Sending...', 'heartland-k9s-core' );
	}

	/** Default heading rendered above the form (null = none). */
	public function heading(): ?string {
		return null;
	}

	/** Default notice rendered above the fields (null = none). */
	public function notice(): ?string {
		return null;
	}

	public function success_heading(): string {
		return __( 'Message Sent', 'heartland-k9s-core' );
	}

	public function success_text(): string {
		return __( 'Thank you for reaching out. We will get back to you shortly.', 'heartland-k9s-core' );
	}

	/**
	 * URL to send the visitor to after success ('' = show the inline success state on the same page).
	 */
	public function success_url(): string {
		return '';
	}

	/**
	 * Notification recipients (settings `forms.{id}_recipients`, admin_email fallback).
	 *
	 * @return string[]
	 */
	public function recipients(): array {
		return Config::recipients( $this->id() );
	}

	/** Fields, filtered per form (`hk9/forms/{id}/fields`). */
	final public function fields(): array {
		$fields = [];
		foreach ( $this->define_fields() as $field ) {
			if ( empty( $field['key'] ) ) {
				continue;
			}
			$fields[ (string) $field['key'] ] = array_merge(
				[
					'type'         => 'text',
					'label'        => (string) $field['key'],
					'placeholder'  => '',
					'required'     => false,
					'maxlength'    => 200,
					'width'        => 'full',
					'autocomplete' => '',
					'options'      => [],
					'help'         => '',
				],
				$field
			);
		}
		/**
		 * Filters the field definitions of a form.
		 *
		 * @param array  $fields Keyed by field key.
		 * @param string $id     Form id.
		 */
		return (array) apply_filters( 'hk9/forms/' . $this->id() . '/fields', $fields, $this->id() );
	}

	final public function mail_subject( array $values ): string {
		$line = sanitize_text_field( $this->subject_line( $values ) );
		return trim( $this->subject_prefix() . ' ' . $line );
	}

	/**
	 * Sanitize + validate raw input. Unknown keys are dropped.
	 *
	 * @param array<string,mixed> $input Unslashed request data.
	 * @return array{values:array<string,mixed>,errors:array<string,string>}
	 */
	final public function validate( array $input ): array {
		$values = [];
		$errors = [];
		foreach ( $this->fields() as $key => $field ) {
			$raw   = $input[ $key ] ?? '';
			$label = (string) $field['label'];
			$type  = (string) $field['type'];
			$max   = max( 1, (int) $field['maxlength'] );

			if ( 'checkbox' === $type ) {
				$checked        = ! is_array( $raw ) && in_array( (string) $raw, [ '1', 'on', 'yes', 'true' ], true );
				$values[ $key ] = $checked;
				if ( ! empty( $field['required'] ) && ! $checked ) {
					$errors[ $key ] = (string) ( $field['required_message'] ?? sprintf(
						/* translators: %s: field label */
						__( 'Please confirm: %s', 'heartland-k9s-core' ),
						$label
					) );
				}
				continue;
			}

			if ( is_array( $raw ) ) {
				$raw = '';
			}
			$value = (string) $raw;
			$value = 'textarea' === $type ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			$value = trim( $value );

			if ( '' === $value ) {
				$values[ $key ] = '';
				if ( ! empty( $field['required'] ) ) {
					$errors[ $key ] = 'select' === $type
						/* translators: %s: field label */
						? sprintf( __( 'Please select an option for %s.', 'heartland-k9s-core' ), $label )
						/* translators: %s: field label */
						: sprintf( __( '%s is required.', 'heartland-k9s-core' ), $label );
				}
				continue;
			}

			if ( mb_strlen( $value ) > $max ) {
				$errors[ $key ] = sprintf(
					/* translators: 1: field label, 2: character limit */
					__( '%1$s must be %2$s characters or fewer.', 'heartland-k9s-core' ),
					$label,
					number_format_i18n( $max )
				);
				$values[ $key ] = mb_substr( $value, 0, $max );
				continue;
			}

			switch ( $type ) {
				case 'email':
					// Reject rather than "repair": sanitize_email() strips illegal characters, which
					// could turn a typo (or an injection attempt) into a different valid-looking address.
					$email = sanitize_email( $value );
					if ( '' === $email || $email !== $value || ! is_email( $email ) ) {
						// Keep the (text-sanitized) input so the visitor sees what they typed when the form re-renders.
						$errors[ $key ] = __( 'Please enter a valid email address.', 'heartland-k9s-core' );
						break;
					}
					$value = $email;
					break;
				case 'tel':
					$digits = preg_replace( '/\D/', '', $value ) ?? '';
					if ( ! preg_match( '/^[0-9 +().\-]+$/', $value ) || strlen( $digits ) < 7 || strlen( $digits ) > 15 ) {
						$errors[ $key ] = __( 'Please enter a valid phone number.', 'heartland-k9s-core' );
					}
					break;
				case 'select':
					$options = (array) ( $field['options'] ?? [] );
					if ( ! array_key_exists( $value, $options ) ) {
						$errors[ $key ] = __( 'Please choose one of the available options.', 'heartland-k9s-core' );
						$value          = '';
					}
					break;
			}
			$values[ $key ] = $value;
		}

		/**
		 * Filters validation errors (allows extra checks). Keys are field keys; values messages.
		 *
		 * @param array  $errors
		 * @param array  $values Sanitized values.
		 * @param string $id     Form id.
		 */
		$errors = (array) apply_filters( 'hk9/forms/' . $this->id() . '/errors', $errors, $values, $this->id() );

		return [
			'values' => $values,
			'errors' => $errors,
		];
	}

	/**
	 * Display value for a stored field (select value => label, checkbox => Yes/No).
	 */
	final public function display_value( string $key, mixed $value ): string {
		$fields = $this->fields();
		$field  = $fields[ $key ] ?? null;
		if ( null === $field ) {
			return is_scalar( $value ) ? (string) $value : '';
		}
		if ( 'checkbox' === $field['type'] ) {
			return $value ? __( 'Yes', 'heartland-k9s-core' ) : __( 'No', 'heartland-k9s-core' );
		}
		if ( 'select' === $field['type'] ) {
			$options = (array) $field['options'];
			return (string) ( $options[ (string) $value ] ?? $value );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Submitter name built from first/last name values. */
	final public function submitter_name( array $values ): string {
		return trim( (string) ( $values['first_name'] ?? '' ) . ' ' . (string) ( $values['last_name'] ?? '' ) );
	}

	/** Submitter email (validated) or ''. */
	final public function submitter_email( array $values ): string {
		$email = sanitize_email( (string) ( $values['email'] ?? '' ) );
		return is_email( $email ) ? $email : '';
	}

	/** Mail-safe rows: [ [label, value, multiline], ... ]. */
	final public function mail_rows( array $values ): array {
		$rows = [];
		foreach ( $this->fields() as $key => $field ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}
			$rows[] = [
				'label'     => (string) ( $field['email_label'] ?? $field['label'] ),
				'value'     => $this->display_value( $key, $values[ $key ] ),
				'multiline' => 'textarea' === $field['type'],
			];
		}
		return $rows;
	}
}
