<?php
/**
 * Initial Application Inquiry form.
 *
 * This is deliberately NOT the program application (the original
 * `[ccf_form id="2156"]` definition is unrecoverable): it collects contact
 * details and a short message so the director can follow up with the full
 * application. No medical/disability questions.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class ApplicationForm extends AbstractForm {

	public function id(): string {
		return 'application';
	}

	public function label(): string {
		return __( 'Application Inquiry', 'heartland-k9s-core' );
	}

	public function submit_label(): string {
		return __( 'Submit Inquiry', 'heartland-k9s-core' );
	}

	public function subject_prefix(): string {
		return '[HK9 Application Inquiry]';
	}

	public function heading(): ?string {
		return __( 'Initial Application Inquiry', 'heartland-k9s-core' );
	}

	public function notice(): ?string {
		return __( 'This is not the full application. It is a first step so we can get to know you. Our director will contact you to provide the full application to be considered for our program.', 'heartland-k9s-core' );
	}

	public function success_heading(): string {
		return __( 'Inquiry Received', 'heartland-k9s-core' );
	}

	public function success_text(): string {
		return __( 'Thank you. Our director will contact you about the next steps and the full application.', 'heartland-k9s-core' );
	}

	public function success_url(): string {
		return Config::application_success_url();
	}

	protected function define_fields(): array {
		$five_questions_url = Config::five_questions_url();
		$ack_label          = __( 'I have read the 5 Questions to Ask Before Partnering With a Service Dog', 'heartland-k9s-core' );
		$ack_html           = esc_html( $ack_label );
		if ( '' !== $five_questions_url ) {
			$ack_html = sprintf(
				/* translators: 1: link open tag, 2: link close tag */
				esc_html__( 'I have read the %1$s5 Questions to Ask Before Partnering With a Service Dog%2$s', 'heartland-k9s-core' ),
				'<a href="' . esc_url( $five_questions_url ) . '" target="_blank" rel="noopener">',
				'</a>'
			);
		}

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
				'width'        => 'half',
			],
			[
				'key'          => 'phone',
				'type'         => 'tel',
				'label'        => __( 'Phone', 'heartland-k9s-core' ),
				'placeholder'  => '(555) 555-5555',
				'required'     => false,
				'maxlength'    => 40,
				'autocomplete' => 'tel',
				'width'        => 'half',
			],
			[
				'key'          => 'city',
				'type'         => 'text',
				'label'        => __( 'City', 'heartland-k9s-core' ),
				'placeholder'  => '',
				'required'     => false,
				'maxlength'    => 100,
				'autocomplete' => 'address-level2',
				'width'        => 'half',
			],
			[
				'key'                => 'state',
				'type'               => 'select',
				'label'              => __( 'State', 'heartland-k9s-core' ),
				'placeholder_option' => __( 'Select a state', 'heartland-k9s-core' ),
				'required'           => false,
				'maxlength'          => 2,
				'autocomplete'       => 'address-level1',
				'options'            => self::states(),
				'width'              => 'half',
			],
			[
				'key'                => 'applicant_type',
				'type'               => 'select',
				'label'              => __( 'I am a', 'heartland-k9s-core' ),
				'email_label'        => __( 'Applicant type', 'heartland-k9s-core' ),
				'placeholder_option' => __( 'Select one', 'heartland-k9s-core' ),
				'required'           => true,
				'maxlength'          => 40,
				'options'            => [
					'veteran'       => __( 'Veteran', 'heartland-k9s-core' ),
					'family_member' => __( 'Family member of a veteran', 'heartland-k9s-core' ),
					'other'         => __( 'Other', 'heartland-k9s-core' ),
				],
				'width'              => 'half',
			],
			[
				'key'                => 'heard_from',
				'type'               => 'select',
				'label'              => __( 'How did you hear about us?', 'heartland-k9s-core' ),
				'placeholder_option' => __( 'Select one (optional)', 'heartland-k9s-core' ),
				'required'           => false,
				'maxlength'          => 40,
				'options'            => self::heard_from_options(),
				'width'              => 'half',
			],
			[
				'key'         => 'message',
				'type'        => 'textarea',
				'label'       => __( 'Tell us about yourself', 'heartland-k9s-core' ),
				'placeholder' => __( 'A little about you, your service, and why you are interested in partnering with a service dog.', 'heartland-k9s-core' ),
				'required'    => true,
				'maxlength'   => 5000,
				'rows'        => 6,
			],
			[
				'key'              => 'read_five_questions',
				'type'             => 'checkbox',
				'label'            => $ack_label,
				'label_html'       => $ack_html,
				'email_label'      => __( 'Read the 5 Questions', 'heartland-k9s-core' ),
				'required'         => true,
				'required_message' => __( 'Please confirm that you have read the 5 Questions before submitting.', 'heartland-k9s-core' ),
			],
		];
	}

	protected function subject_line( array $values ): string {
		$type = $this->display_value( 'applicant_type', $values['applicant_type'] ?? '' );
		$name = $this->submitter_name( $values );
		return '' !== $type ? sprintf( '%1$s (%2$s)', $name, $type ) : $name;
	}

	/** @return array<string,string> */
	public static function heard_from_options(): array {
		$options = [
			'va'       => __( 'VA or a medical provider', 'heartland-k9s-core' ),
			'veteran'  => __( 'Another veteran or a Heartland team', 'heartland-k9s-core' ),
			'friend'   => __( 'Friend or family', 'heartland-k9s-core' ),
			'event'    => __( 'Event or campaign', 'heartland-k9s-core' ),
			'social'   => __( 'Social media', 'heartland-k9s-core' ),
			'search'   => __( 'Internet search', 'heartland-k9s-core' ),
			'other'    => __( 'Other', 'heartland-k9s-core' ),
		];
		/**
		 * Filters the "How did you hear about us?" options (value => label).
		 *
		 * @param array $options
		 */
		return (array) apply_filters( 'hk9/forms/application/heard_from_options', $options );
	}

	/** US states + DC (code => name). */
	public static function states(): array {
		return [
			'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
			'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia',
			'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
			'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
			'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
			'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
			'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
			'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
			'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
			'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
			'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
		];
	}
}
