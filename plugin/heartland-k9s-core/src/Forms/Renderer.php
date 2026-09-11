<?php
/**
 * Server-side markup for the site forms (theme provides the CSS; classes per ARCHITECTURE §11).
 *
 * Structure:
 *   .hk9-form-wrap#<id>
 *     [.hk9-form__heading] [.hk9-form__intro] [.hk9-form__notice]
 *     form.hk9-form.hk9-form--<form>
 *       .hk9-form__status (aria-live) · .hk9-form__summary (role=alert)
 *       hidden fields · .hk9-form__honeypot
 *       .hk9-form__grid > .hk9-form__field(--half|--checkbox) > .hk9-form__label + .hk9-form__input + .hk9-form__error
 *       .hk9-form__actions > button.hk9-btn.hk9-form__submit
 *     template.hk9-form__success-template (used by JS)
 *   — or — .hk9-form__success (role=status) when the submission was sent.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/**
	 * @param array<string,mixed> $args  Render options (see Handler::render()).
	 * @param array<string,mixed> $state {errors, values, sent, message, code, tokens}
	 */
	public static function render( AbstractForm $form, array $args, array $state ): string {
		$form_id = $form->id();
		$dom_id  = isset( $args['id'] ) && '' !== (string) $args['id'] ? sanitize_html_class( (string) $args['id'] ) : 'hk9-form-' . $form_id;
		$classes = 'hk9-form-wrap hk9-form-wrap--' . $form_id;
		if ( ! empty( $args['class'] ) ) {
			$classes .= ' ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $args['class'] ) ?: [] ) );
		}

		$success_heading = (string) ( $args['success_heading'] ?? $form->success_heading() );
		$success_text    = (string) ( $args['success_text'] ?? $form->success_text() );
		$success_html    = self::success_markup( $dom_id, $success_heading, $success_text );

		$out = '<div class="' . esc_attr( $classes ) . '" id="' . esc_attr( $dom_id ) . '" data-hk9-form-wrap="' . esc_attr( $form_id ) . '">';

		if ( ! empty( $state['sent'] ) ) {
			return $out . str_replace( '<div class="hk9-form__success"', '<div class="hk9-form__success" tabindex="-1" autofocus', $success_html ) . '</div>';
		}

		$heading = array_key_exists( 'heading', $args ) ? $args['heading'] : $form->heading();
		$notice  = array_key_exists( 'notice', $args ) ? $args['notice'] : $form->notice();
		$intro   = (string) ( $args['intro'] ?? '' );

		if ( is_string( $heading ) && '' !== $heading ) {
			$out .= '<h2 class="hk9-form__heading" id="' . esc_attr( $dom_id . '-heading' ) . '">' . esc_html( $heading ) . '</h2>';
		}
		if ( '' !== $intro ) {
			$out .= '<p class="hk9-form__intro">' . esc_html( $intro ) . '</p>';
		}
		if ( is_string( $notice ) && '' !== $notice ) {
			$out .= '<div class="hk9-form__notice" role="note">' . wp_kses( wpautop( $notice ), [ 'p' => [], 'strong' => [], 'em' => [], 'br' => [], 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ) . '</div>';
		}

		$success_url  = self::success_url( $form, $args );
		$mode         = '' !== $success_url ? 'redirect' : 'inline';
		$post_id      = isset( $args['post_id'] ) ? (int) $args['post_id'] : (int) get_the_ID();
		$tokens       = $state['tokens'] ?? Antispam::issue( $form_id );
		$errors       = is_array( $state['errors'] ?? null ) ? $state['errors'] : [];
		$values       = is_array( $state['values'] ?? null ) ? $state['values'] : [];
		$message      = (string) ( $state['message'] ?? '' );
		$fields       = $form->fields();
		$submit_label = (string) ( $args['submit_label'] ?? $form->submit_label() );

		$form_attrs = [
			'class'             => 'hk9-form hk9-form--' . $form_id,
			'method'            => 'post',
			'action'            => esc_url( admin_url( 'admin-post.php' ) ),
			'data-hk9-form'     => $form_id,
			'data-hk9-endpoint' => esc_url_raw( rest_url( 'hk9/v1/forms/' . $form_id ) ),
			'data-hk9-mode'     => $mode,
			'data-hk9-id'       => $dom_id,
		];
		if ( is_string( $heading ) && '' !== $heading ) {
			$form_attrs['aria-labelledby'] = $dom_id . '-heading';
		}
		$out .= '<form' . self::attrs( $form_attrs ) . '>';

		// Live region for JS status updates (visually hidden by the theme).
		$out .= '<div class="hk9-form__status hk9-visually-hidden" aria-live="polite" aria-atomic="true"></div>';

		// Error summary (role=alert). Rendered always so JS can populate it; hidden when empty.
		$has_summary = [] !== $errors || '' !== $message;
		$out        .= '<div class="hk9-form__summary" id="' . esc_attr( $dom_id . '-summary' ) . '" role="alert" tabindex="-1"' . ( $has_summary ? ' autofocus' : ' hidden' ) . '>';
		$out        .= '<p class="hk9-form__summary-title">' . esc_html( '' !== $message ? $message : __( 'Please correct the following:', 'heartland-k9s-core' ) ) . '</p>';
		$out        .= '<ul class="hk9-form__summary-list">';
		foreach ( $errors as $key => $error ) {
			if ( isset( $fields[ $key ] ) ) {
				$out .= '<li><a href="#' . esc_attr( self::field_id( $dom_id, (string) $key ) ) . '">' . esc_html( (string) $error ) . '</a></li>';
			}
		}
		$out .= '</ul></div>';

		// Hidden protocol fields.
		$hidden = [
			'action'     => 'hk9_form_submit',
			'hk9_form'   => $form_id,
			'hk9_nonce'  => wp_create_nonce( Antispam::nonce_action( $form_id ) ),
			'hk9_ts'     => (string) $tokens['ts'],
			'hk9_token'  => (string) $tokens['token'],
			'hk9_post'   => (string) $post_id,
			'hk9_anchor' => $dom_id,
		];
		if ( '' !== $success_url ) {
			$hidden['hk9_success'] = $success_url;
		}
		foreach ( $hidden as $name => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}

		// Honeypot: off-screen, not in the tab order, hidden from assistive tech.
		$hp_id = $dom_id . '-hk9_website';
		$out  .= '<div class="hk9-form__honeypot" aria-hidden="true" style="position:absolute!important;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">';
		$out  .= '<label for="' . esc_attr( $hp_id ) . '">' . esc_html__( 'Leave this field empty', 'heartland-k9s-core' ) . '</label>';
		$out  .= '<input type="text" id="' . esc_attr( $hp_id ) . '" name="hk9_website" value="" tabindex="-1" autocomplete="off">';
		$out  .= '</div>';

		$out .= '<div class="hk9-form__grid">';
		foreach ( $fields as $key => $field ) {
			$out .= self::field( $dom_id, (string) $key, $field, $values[ $key ] ?? null, $errors[ $key ] ?? '', $args );
		}
		$out .= '</div>';

		$out .= '<div class="hk9-form__actions">';
		$out .= '<button type="submit" class="btn hk9-btn hk9-btn--primary hk9-btn--lg hk9-btn--full hk9-form__submit" data-sending-label="' . esc_attr( $form->sending_label() ) . '">' . esc_html( $submit_label ) . '</button>';
		$out .= '</div>';

		$out .= '</form>';
		$out .= '<template class="hk9-form__success-template">' . $success_html . '</template>';
		$out .= '</div>';

		return $out;
	}

	public static function field_id( string $dom_id, string $key ): string {
		return $dom_id . '-' . sanitize_html_class( $key );
	}

	/** Resolve the success URL override (`$args['success_url']`, a link array or string) or the form default. */
	private static function success_url( AbstractForm $form, array $args ): string {
		if ( array_key_exists( 'success_url', $args ) ) {
			$url = Config::link_url( $args['success_url'] );
			if ( '' !== $url ) {
				return $url;
			}
		}
		return $form->success_url();
	}

	private static function success_markup( string $dom_id, string $heading, string $text ): string {
		$html  = '<div class="hk9-form__success" role="status" id="' . esc_attr( $dom_id . '-success' ) . '">';
		$html .= '<h2 class="hk9-form__success-title">' . esc_html( $heading ) . '</h2>';
		if ( '' !== $text ) {
			$html .= wp_kses( wpautop( $text ), [ 'p' => [], 'strong' => [], 'em' => [], 'br' => [], 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] );
		}
		$html .= '</div>';
		return str_replace( '<p>', '<p class="hk9-form__success-text">', $html );
	}

	/**
	 * @param array<string,mixed> $field Field definition.
	 */
	private static function field( string $dom_id, string $key, array $field, mixed $value, string $error, array $args ): string {
		$type     = (string) $field['type'];
		$id       = self::field_id( $dom_id, $key );
		$error_id = $id . '-error';
		$required = ! empty( $field['required'] );
		$invalid  = '' !== $error;
		$classes  = 'hk9-form__field hk9-form__field--' . sanitize_html_class( $type );
		if ( 'half' === ( $field['width'] ?? 'full' ) ) {
			$classes .= ' hk9-form__field--half';
		}
		if ( $invalid ) {
			$classes .= ' is-invalid';
		}

		$describedby = [];
		$help_html   = '';
		if ( ! empty( $field['help'] ) ) {
			$describedby[] = $id . '-help';
			$help_html     = '<p class="hk9-form__help" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( (string) $field['help'] ) . '</p>';
		}
		if ( $invalid ) {
			$describedby[] = $error_id;
		}

		$control_attrs = [
			'id'   => $id,
			'name' => $key,
		];
		if ( $required ) {
			$control_attrs['required']      = true;
			$control_attrs['aria-required'] = 'true';
		}
		if ( $invalid ) {
			$control_attrs['aria-invalid'] = 'true';
		}
		if ( [] !== $describedby ) {
			$control_attrs['aria-describedby'] = implode( ' ', $describedby );
		}
		if ( ! empty( $field['autocomplete'] ) ) {
			$control_attrs['autocomplete'] = (string) $field['autocomplete'];
		}

		$label_text = (string) $field['label'];
		$star       = $required ? ' <span class="hk9-form__required" aria-hidden="true">*</span>' : '';
		$error_html = '<p class="hk9-form__error" id="' . esc_attr( $error_id ) . '"' . ( $invalid ? '' : ' hidden' ) . '>' . esc_html( $error ) . '</p>';

		$out = '<div class="' . esc_attr( $classes ) . '" data-hk9-field="' . esc_attr( $key ) . '">';

		switch ( $type ) {
			case 'checkbox':
				$checked                 = (bool) $value;
				$control_attrs['class']  = 'hk9-form__checkbox form-check-input';
				$control_attrs['type']   = 'checkbox';
				$control_attrs['value']  = '1';
				$control_attrs['checked'] = $checked;
				// `show_five_questions_link` (application template option) toggles the linked label; default on.
				$show_link  = ! isset( $args['show_five_questions_link'] ) || ! empty( $args['show_five_questions_link'] );
				$label_html = $show_link && ! empty( $field['label_html'] )
					? wp_kses( (string) $field['label_html'], [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [], 'em' => [] ] )
					: esc_html( $label_text );
				$out .= '<div class="hk9-form__check form-check">';
				$out .= '<input' . self::attrs( $control_attrs ) . '>';
				$out .= '<label class="hk9-form__label hk9-form__label--checkbox form-check-label" for="' . esc_attr( $id ) . '">' . $label_html . $star . '</label>';
				$out .= '</div>';
				$out .= $help_html . $error_html;
				break;

			case 'select':
				$options                = (array) ( $field['options'] ?? [] );
				$current                = is_scalar( $value ) ? (string) $value : '';
				$control_attrs['class'] = 'hk9-form__input hk9-form__select form-select';
				$out                   .= '<label class="hk9-form__label" for="' . esc_attr( $id ) . '">' . esc_html( $label_text ) . $star . '</label>';
				$out                   .= '<select' . self::attrs( $control_attrs ) . '>';
				$placeholder            = (string) ( $field['placeholder_option'] ?? __( 'Select an option', 'heartland-k9s-core' ) );
				$out                   .= '<option value=""' . ( $required ? ' disabled hidden' : '' ) . ( '' === $current || ! isset( $options[ $current ] ) ? ' selected' : '' ) . '>' . esc_html( $placeholder ) . '</option>';
				foreach ( $options as $opt_value => $opt_label ) {
					$out .= '<option value="' . esc_attr( (string) $opt_value ) . '"' . selected( $current, (string) $opt_value, false ) . '>' . esc_html( (string) $opt_label ) . '</option>';
				}
				$out .= '</select>';
				$out .= $help_html . $error_html;
				break;

			case 'textarea':
				$control_attrs['class']       = 'hk9-form__input hk9-form__textarea form-control';
				$control_attrs['rows']        = (string) max( 2, (int) ( $field['rows'] ?? 5 ) );
				$control_attrs['maxlength']   = (string) (int) $field['maxlength'];
				$control_attrs['placeholder'] = (string) $field['placeholder'];
				$out                         .= '<label class="hk9-form__label" for="' . esc_attr( $id ) . '">' . esc_html( $label_text ) . $star . '</label>';
				$out                         .= '<textarea' . self::attrs( $control_attrs ) . '>' . esc_textarea( is_scalar( $value ) ? (string) $value : '' ) . '</textarea>';
				$out                         .= $help_html . $error_html;
				break;

			default:
				$control_attrs['class']       = 'hk9-form__input form-control';
				$control_attrs['type']        = in_array( $type, [ 'email', 'tel' ], true ) ? $type : 'text';
				$control_attrs['value']       = is_scalar( $value ) ? (string) $value : '';
				$control_attrs['maxlength']   = (string) (int) $field['maxlength'];
				$control_attrs['placeholder'] = (string) $field['placeholder'];
				if ( 'email' === $type ) {
					$control_attrs['inputmode']  = 'email';
					$control_attrs['spellcheck'] = 'false';
				} elseif ( 'tel' === $type ) {
					$control_attrs['inputmode'] = 'tel';
				}
				$out .= '<label class="hk9-form__label" for="' . esc_attr( $id ) . '">' . esc_html( $label_text ) . $star . '</label>';
				$out .= '<input' . self::attrs( $control_attrs ) . '>';
				$out .= $help_html . $error_html;
				break;
		}

		return $out . '</div>';
	}

	/** Build an escaped attribute string (bool true = boolean attribute, false/null = omitted). */
	private static function attrs( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $name => $value ) {
			if ( false === $value || null === $value ) {
				continue;
			}
			$name = preg_replace( '/[^a-z0-9\-_:]/i', '', (string) $name ) ?? '';
			if ( '' === $name ) {
				continue;
			}
			if ( true === $value ) {
				$out .= ' ' . $name;
				continue;
			}
			$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}
}
