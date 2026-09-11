<?php
/**
 * Contact form ("Send a Message") — fields per the visual reference.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class ContactForm extends AbstractForm {

	public function id(): string {
		return 'contact';
	}

	public function label(): string {
		return __( 'Contact', 'heartland-k9s-core' );
	}

	public function submit_label(): string {
		return __( 'Send Message', 'heartland-k9s-core' );
	}

	public function subject_prefix(): string {
		return '[HK9 Contact]';
	}

	public function success_text(): string {
		return Config::contact_success_text();
	}

	protected function define_fields(): array {
		return [
			[
				'key'          => 'first_name',
				'type'         => 'text',
				'label'        => __( 'First Name', 'heartland-k9s-core' ),
				'placeholder'  => 'John',
				'required'     => true,
				'maxlength'    => 100,
				'autocomplete' => 'given-name',
				'width'        => 'half',
			],
			[
				'key'          => 'last_name',
				'type'         => 'text',
				'label'        => __( 'Last Name', 'heartland-k9s-core' ),
				'placeholder'  => 'Doe',
				'required'     => true,
				'maxlength'    => 100,
				'autocomplete' => 'family-name',
				'width'        => 'half',
			],
			[
				'key'          => 'email',
				'type'         => 'email',
				'label'        => __( 'Email Address', 'heartland-k9s-core' ),
				'placeholder'  => 'john@example.com',
				'required'     => true,
				'maxlength'    => 254,
				'autocomplete' => 'email',
			],
			[
				'key'                => 'subject',
				'type'               => 'select',
				'label'              => __( 'Subject', 'heartland-k9s-core' ),
				'placeholder_option' => __( 'Select a subject', 'heartland-k9s-core' ),
				'required'           => true,
				'maxlength'          => 100,
				'options'            => Config::subjects(),
			],
			[
				'key'         => 'message',
				'type'        => 'textarea',
				'label'       => __( 'Message', 'heartland-k9s-core' ),
				'placeholder' => __( 'How can we help you?', 'heartland-k9s-core' ),
				'required'    => true,
				'maxlength'   => 5000,
				'rows'        => 5,
			],
		];
	}

	protected function subject_line( array $values ): string {
		$subject = $this->display_value( 'subject', $values['subject'] ?? '' );
		$name    = $this->submitter_name( $values );
		return sprintf(
			/* translators: 1: chosen subject, 2: sender name */
			__( '%1$s from %2$s', 'heartland-k9s-core' ),
			$subject,
			$name
		);
	}
}
