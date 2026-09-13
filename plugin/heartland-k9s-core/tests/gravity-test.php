<?php
/**
 * Gravity Forms provisioning test plan (Forms\GravityProvisioner, docs/ARCHITECTURE.md §10).
 *
 * Run inside the docker stack with Gravity Forms active:
 *   tools/wp.sh eval-file /var/www/html/wp-content/plugins/heartland-k9s-core/tests/gravity-test.php
 *
 * Prints one PASS/FAIL/BLOCKED line per check; exits non-zero on any FAIL.
 * Simulates a fresh site (ids empty, no marker known) so provisioning creates
 * forms, then deletes every form it created (the site's own provisioned forms
 * are never touched) and restores the settings (ids, provider, recipients,
 * sender) and the last-run option. Covers: creation, settings, idempotency,
 * adoption, --force (in place / re-create / hand-picked and other-role forms
 * skipped), the settings panel buttons, drift states, rendering, the import
 * hook (provision, seed refresh, hand-edited values kept) and the screens that
 * trigger an automatic run.
 * (No strict_types: eval-file wraps the code.)
 *
 * @package HK9\Core
 */

use HK9\Core\Forms\Config;
use HK9\Core\Forms\GravityProvisioner;
use HK9\Core\Settings\Store;
use HK9\Core\Support\FormProviders;

if ( ! defined( 'WP_CLI' ) && ! defined( 'ABSPATH' ) ) {
	exit( 'Run via WP-CLI eval-file.' );
}

$hk9_results = [];
$hk9_report  = static function ( string $name, string $status, string $evidence ) use ( &$hk9_results ): void {
	$hk9_results[] = [ $name, $status, $evidence ];
	echo str_pad( $status, 7 ), ' ', $name, ' — ', $evidence, "\n";
};
$hk9_pass    = static fn( string $n, string $e ) => $hk9_report( $n, 'PASS', $e );
$hk9_fail    = static fn( string $n, string $e ) => $hk9_report( $n, 'FAIL', $e );
$hk9_blocked = static fn( string $n, string $e ) => $hk9_report( $n, 'BLOCKED', $e );

$hk9_admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ] )[0] ?? 1;
wp_set_current_user( (int) $hk9_admin );

if ( ! GravityProvisioner::available() ) {
	$hk9_blocked( 'gravity_forms_active', 'Gravity Forms is not active on this site; nothing to test.' );
	echo "\n1 checks, 0 failed\n";
	return;
}

/* Snapshot of what the test changes; restored in the cleanup at the end. */
$hk9_saved_forms  = Store::raw()['forms'] ?? [];
$hk9_saved_option = get_option( GravityProvisioner::OPTION, null );
$hk9_created      = []; // Gravity form ids created by this test (deleted at the end).
$hk9_form_ids     = static fn(): array => array_map( static fn( $f ) => (int) $f['id'], (array) GFAPI::get_forms( null, null ) );
$hk9_set_forms    = static function ( array $values ): void {
	Store::update( [ 'forms' => $values ] );
	FormProviders::flush();
};
$hk9_gf_fields    = static function ( int $form_id ): array {
	$form = GFAPI::get_form( $form_id );
	$out  = [];
	foreach ( (array) ( $form['fields'] ?? [] ) as $f ) {
		$out[ (int) $f->id ] = [
			'type'     => (string) $f->type,
			'label'    => (string) $f->label,
			'required' => (bool) $f->isRequired,
			'css'      => (string) $f->cssClass,
			'choices'  => is_array( $f->choices ) ? array_map( static fn( $c ) => $c['value'] ?? $c['text'], $f->choices ) : [],
			'inputs'   => is_array( $f->inputs ) ? array_column( $f->inputs, 'customLabel', 'id' ) : [],
			'ph'       => (string) $f->placeholder,
			'ff'       => (string) ( $f->phoneFormat ?? '' ),
		];
	}
	return $out;
};
$hk9_ignore_marked = static fn( array $marked ): array => [];

/* 0. Fresh-site simulation: no ids, provider builtin, no earlier run, existing Heartland forms not recognised. */
$hk9_set_forms( [ 'provider' => 'builtin', 'gravity_contact_form' => 0, 'gravity_application_form' => 0 ] );
delete_option( GravityProvisioner::OPTION );
add_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
$before_ids = $hk9_form_ids();
$status0    = GravityProvisioner::status();
( $status0['gravity_active'] && $status0['needs_provisioning'] && 'unset' === $status0['forms']['contact']['state'] && GravityProvisioner::should_auto_provision() )
	? $hk9_pass( 'status_fresh_site', 'needs_provisioning=true, both roles unset, should_auto_provision()=true' )
	: $hk9_fail( 'status_fresh_site', wp_json_encode( [ $status0['needs_provisioning'], $status0['forms']['contact']['state'], GravityProvisioner::should_auto_provision() ] ) );

/* 1. provision() creates two forms with the expected fields / notifications / confirmations and stores ids + provider. */
$r1 = GravityProvisioner::provision( false, 'cli' );
if ( is_wp_error( $r1 ) ) {
	$hk9_fail( 'provision_creates_forms', $r1->get_error_message() );
} else {
	$after_ids  = $hk9_form_ids();
	$new_ids    = array_values( array_diff( $after_ids, $before_ids ) );
	$hk9_created = array_merge( $hk9_created, $new_ids );
	$c_id = (int) $r1['forms']['contact']['id'];
	$a_id = (int) $r1['forms']['application']['id'];
	$ok   = 2 === count( $new_ids ) && in_array( $c_id, $new_ids, true ) && in_array( $a_id, $new_ids, true )
		&& 'created' === $r1['forms']['contact']['action'] && 'created' === $r1['forms']['application']['action']
		&& $r1['provider_set'] && $r1['ids_changed'];
	$ok ? $hk9_pass( 'provision_creates_forms', sprintf( '2 new forms (#%d contact, #%d application), provider_set=true', $c_id, $a_id ) ) : $hk9_fail( 'provision_creates_forms', wp_json_encode( [ $new_ids, $r1 ] ) );

	$forms_settings = Store::raw()['forms'];
	( 'gravity' === ( $forms_settings['provider'] ?? '' ) && $c_id === (int) $forms_settings['gravity_contact_form'] && $a_id === (int) $forms_settings['gravity_application_form'] && 'gravity' === FormProviders::default_provider() )
		? $hk9_pass( 'settings_written', sprintf( 'forms.provider=gravity, gravity_contact_form=%d, gravity_application_form=%d', $c_id, $a_id ) )
		: $hk9_fail( 'settings_written', wp_json_encode( $forms_settings ) );

	$run = GravityProvisioner::last_run();
	( is_array( $run ) && 'cli' === $run['by'] && GravityProvisioner::VERSION === (int) $run['version'] && $c_id === (int) $run['forms']['contact'] )
		? $hk9_pass( 'last_run_recorded', 'option ' . GravityProvisioner::OPTION . ' = ' . wp_json_encode( $run['forms'] ) )
		: $hk9_fail( 'last_run_recorded', wp_json_encode( $run ) );

	// Contact form content.
	$cf     = GFAPI::get_form( $c_id );
	$fields = $hk9_gf_fields( $c_id );
	$marker = GravityProvisioner::marker( $cf );
	$ok     = str_starts_with( (string) $cf['title'], 'Contact' ) && 'orbital' === ( $cf['theme'] ?? '' ) && str_contains( (string) $cf['cssClass'], 'hk9-gf-form--contact' )
		&& 'top_label' === $cf['labelPlacement'] && 'below' === $cf['descriptionPlacement'] && 'Send Message' === $cf['button']['text']
		&& ! empty( $cf['enableHoneypot'] ) && empty( $cf['enableAnimation'] ) && empty( $cf['requireLogin'] ) && 'asterisk' === $cf['requiredIndicator']
		&& is_array( $marker ) && 'contact' === $marker['role'] && GravityProvisioner::VERSION === (int) $marker['version']
		&& 'First Name and Last Name are required.' === (string) ( $cf['fields'][0]->errorMessage ?? '' );
	$ok ? $hk9_pass( 'contact_form_settings', 'title/theme orbital/cssClass/top_label/description below/button "Send Message"/honeypot on/animation off/login off/asterisk + hk9_provisioned marker; Name validation message "First Name and Last Name are required."' ) : $hk9_fail( 'contact_form_settings', wp_json_encode( [ $cf['title'], $cf['theme'] ?? null, $cf['cssClass'], $cf['labelPlacement'], $cf['button'], $cf['enableHoneypot'] ?? null, $marker, (string) ( $cf['fields'][0]->errorMessage ?? '' ) ] ) );

	$ok = [ 1, 2, 3, 4 ] === array_keys( $fields )
		&& 'name' === $fields[1]['type'] && $fields[1]['required'] && [ '1.3' => 'First Name', '1.6' => 'Last Name' ] === array_filter( $fields[1]['inputs'] )
		&& 'email' === $fields[2]['type'] && $fields[2]['required'] && 'john@example.com' === $fields[2]['ph']
		&& 'select' === $fields[3]['type'] && $fields[3]['required'] && array_keys( Config::subjects() ) === $fields[3]['choices'] && 'Select a subject' === $fields[3]['ph']
		&& 'textarea' === $fields[4]['type'] && $fields[4]['required'] && 'How can we help you?' === $fields[4]['ph'];
	$ok ? $hk9_pass( 'contact_form_fields', 'Name(advanced: First Name/Last Name, John/Doe, required) · Email(required) · Subject(select, required, ' . count( $fields[3]['choices'] ) . ' subjects from settings) · Message(textarea, required)' ) : $hk9_fail( 'contact_form_fields', wp_json_encode( $fields ) );

	$n  = $cf['notifications'][ GravityProvisioner::NOTIFICATION_ID ] ?? null;
	$ok = is_array( $n ) && 'form_submission' === $n['event'] && ! empty( $n['isActive'] ) && 'email' === $n['toType']
		&& implode( ', ', Config::recipients( 'contact' ) ) === $n['to'] && Config::from()['name'] === $n['fromName']
		&& '{Email Address:2}' === $n['replyTo'] && '[HK9 Contact] {Subject:3} from {Name (First):1.3} {Name (Last):1.6}' === $n['subject'] && '{all_fields}' === $n['message'];
	$ok ? $hk9_pass( 'contact_notification', sprintf( 'Admin notification → %s, fromName "%s", replyTo {Email Address:2}, subject "[HK9 Contact] {Subject:3} from {Name (First):1.3} {Name (Last):1.6}"', $n['to'], $n['fromName'] ) ) : $hk9_fail( 'contact_notification', wp_json_encode( $cf['notifications'] ) );

	$c  = $cf['confirmations'][ GravityProvisioner::CONFIRMATION_ID ] ?? null;
	$ok = is_array( $c ) && 'message' === $c['type'] && ! empty( $c['isDefault'] ) && str_contains( $c['message'], 'Message Sent' ) && str_contains( $c['message'], Config::contact_success_text() ) && 1 === count( $cf['confirmations'] );
	$ok ? $hk9_pass( 'contact_confirmation', 'single default message confirmation "Message Sent — ' . Config::contact_success_text() . '"' ) : $hk9_fail( 'contact_confirmation', wp_json_encode( $cf['confirmations'] ) );

	// Application form content.
	$af     = GFAPI::get_form( $a_id );
	$fields = $hk9_gf_fields( $a_id );
	$states = HK9\Core\Forms\ApplicationForm::states();
	$heard  = HK9\Core\Forms\ApplicationForm::heard_from_options();
	$ok     = str_starts_with( (string) $af['title'], 'Initial Application Inquiry' ) && str_contains( (string) $af['cssClass'], 'hk9-gf-form--application' ) && 'Submit Inquiry' === $af['button']['text']
		&& [ 1, 2, 3, 4, 5, 6, 7, 8, 9 ] === array_keys( $fields )
		&& 'name' === $fields[1]['type'] && $fields[1]['required']
		&& 'email' === $fields[2]['type'] && $fields[2]['required'] && 'gf_left_half' === $fields[2]['css']
		&& 'phone' === $fields[3]['type'] && ! $fields[3]['required'] && 'international' === $fields[3]['ff'] && 'gf_right_half' === $fields[3]['css']
		&& 'text' === $fields[4]['type'] && ! $fields[4]['required'] && 'City' === $fields[4]['label']
		&& 'select' === $fields[5]['type'] && ! $fields[5]['required'] && array_keys( $states ) === $fields[5]['choices']
		&& 'select' === $fields[6]['type'] && $fields[6]['required'] && 'I am a' === $fields[6]['label'] && 'Select one' === $fields[6]['ph'] && 'gf_left_half' === $fields[6]['css']
		&& array_keys( HK9\Core\Forms\ApplicationForm::applicant_type_options() ) === $fields[6]['choices'] && [ 'veteran', 'family_member', 'other' ] === $fields[6]['choices']
		&& array_values( HK9\Core\Forms\ApplicationForm::applicant_type_options() ) === array_map( static fn( $c ) => $c['text'], $af['fields'][5]->choices )
		&& 'select' === $fields[7]['type'] && ! $fields[7]['required'] && array_keys( $heard ) === $fields[7]['choices']
		&& 'textarea' === $fields[8]['type'] && $fields[8]['required'] && 'Tell us about yourself' === $fields[8]['label']
		&& 'consent' === $fields[9]['type'] && $fields[9]['required'];
	$consent_label = (string) ( $af['fields'][8]->checkboxLabel ?? '' );
	$five_url      = Config::five_questions_url();
	$ok            = $ok && str_contains( $consent_label, '5 Questions to Ask Before Partnering With a Service Dog' ) && ( '' === $five_url || str_contains( $consent_label, esc_url( $five_url ) ) );
	$ok ? $hk9_pass( 'application_form_fields', sprintf( 'button "Submit Inquiry" · Name · Email(half) · Phone(intl, optional, half) · City · State(select, %d states, optional) · "I am a"(select, required, Veteran/Family member of a veteran/Other = ApplicationForm::applicant_type_options(), placeholder "Select one", half) · How did you hear(select, %d options) · "Tell us about yourself"(textarea) · consent checkbox (required, links to %s)', count( $fields[5]['choices'] ), count( $fields[7]['choices'] ), $five_url ) ) : $hk9_fail( 'application_form_fields', wp_json_encode( [ $af['title'], $af['button'], $fields, $consent_label ] ) );

	$n  = $af['notifications'][ GravityProvisioner::NOTIFICATION_ID ] ?? null;
	$ok = is_array( $n ) && implode( ', ', Config::recipients( 'application' ) ) === $n['to'] && '{Email Address:2}' === $n['replyTo'] && '[HK9 Application Inquiry] {Name (First):1.3} {Name (Last):1.6}' === $n['subject'];
	$ok ? $hk9_pass( 'application_notification', sprintf( 'notification → %s, replyTo {Email Address:2}, subject "[HK9 Application Inquiry] {Name (First):1.3} {Name (Last):1.6}"', $n['to'] ) ) : $hk9_fail( 'application_notification', wp_json_encode( $af['notifications'] ) );

	$c       = $af['confirmations'][ GravityProvisioner::CONFIRMATION_ID ] ?? null;
	$success = Config::application_success_url();
	$ok      = is_array( $c ) && ! empty( $c['isDefault'] ) && ( '' !== $success ? ( 'redirect' === $c['type'] && $success === $c['url'] ) : ( 'message' === $c['type'] && str_contains( $c['message'], 'Inquiry Received' ) ) );
	$ok ? $hk9_pass( 'application_confirmation', '' !== $success ? 'redirect → ' . $success : 'message fallback (no success page set)' ) : $hk9_fail( 'application_confirmation', wp_json_encode( [ $success, $af['confirmations'] ] ) );

	/* 2. Idempotent: a second call keeps both forms and creates nothing. */
	remove_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	$count_before = count( $hk9_form_ids() );
	$r2           = GravityProvisioner::provision( false, 'cli' );
	$ok           = ! is_wp_error( $r2 ) && 'kept' === $r2['forms']['contact']['action'] && 'kept' === $r2['forms']['application']['action']
		&& $c_id === (int) $r2['forms']['contact']['id'] && $a_id === (int) $r2['forms']['application']['id'] && ! $r2['ids_changed'] && ! $r2['provider_set'] && $count_before === count( $hk9_form_ids() );
	$ok ? $hk9_pass( 'provision_idempotent', sprintf( 'second call: both "kept" (#%d/#%d), no new form (%d forms before and after), no settings change', $c_id, $a_id, $count_before ) ) : $hk9_fail( 'provision_idempotent', wp_json_encode( [ $r2, $count_before, count( $hk9_form_ids() ) ] ) );

	/* 3. A manual provider choice is never overridden; ids are only written when they change. */
	$hk9_set_forms( [ 'provider' => 'shortcode' ] );
	$r3 = GravityProvisioner::provision( false, 'cli' );
	( ! is_wp_error( $r3 ) && ! $r3['provider_set'] && 'shortcode' === Store::raw()['forms']['provider'] )
		? $hk9_pass( 'manual_provider_kept', 'forms.provider=shortcode with ids set → provision() leaves it (provider_set=false)' )
		: $hk9_fail( 'manual_provider_kept', wp_json_encode( [ $r3, Store::raw()['forms']['provider'] ] ) );

	// Ids cleared by hand after an earlier run: no automatic run, but an explicit run re-adopts the marked forms (candidates) instead of creating new ones.
	$hk9_set_forms( [ 'provider' => 'builtin', 'gravity_contact_form' => 0, 'gravity_application_form' => 0 ] );
	$auto = GravityProvisioner::should_auto_provision();
	$st3  = GravityProvisioner::status();
	$cands = $st3['forms']['contact']['candidates'];
	( ! $auto && in_array( $c_id, $cands, true ) )
		? $hk9_pass( 'no_auto_after_manual_clear', 'ids cleared after a recorded run → should_auto_provision()=false; status lists marked candidates #' . implode( ', #', $cands ) )
		: $hk9_fail( 'no_auto_after_manual_clear', wp_json_encode( [ $auto, $cands ] ) );
	$count_before = count( $hk9_form_ids() );
	$r3b          = GravityProvisioner::provision( false, 'cli' );
	$adopted      = ! is_wp_error( $r3b ) ? (int) $r3b['forms']['contact']['id'] : 0;
	( ! is_wp_error( $r3b ) && 'kept' === $r3b['forms']['contact']['action'] && in_array( $adopted, $cands, true ) && $count_before === count( $hk9_form_ids() ) && $r3b['provider_set'] && 'gravity' === Store::raw()['forms']['provider'] )
		? $hk9_pass( 'explicit_run_adopts_marked_forms', sprintf( 'provision() with empty ids adopted marked form #%d (no new form) and, ids having been empty, set provider=gravity', $adopted ) )
		: $hk9_fail( 'explicit_run_adopts_marked_forms', wp_json_encode( [ $r3b, $count_before, count( $hk9_form_ids() ) ] ) );
	// Point the settings back at the forms created by this test for the remaining checks.
	$hk9_set_forms( [ 'provider' => 'gravity', 'gravity_contact_form' => $c_id, 'gravity_application_form' => $a_id ] );

	/* 4. --force updates in place: same ids, definition restored, entries and extra notifications kept. */
	$entry_id = GFAPI::add_entry( [ 'form_id' => $c_id, '1.3' => 'Test', '1.6' => 'Entry', '2' => 'tmp-test@hk9-local.test', '3' => 'other', '4' => 'temporary test entry' ] );
	$edited   = GFAPI::get_form( $c_id );
	$edited['button']['text']           = 'Changed by hand';
	$edited['notifications']['user_x']  = [ 'id' => 'user_x', 'name' => 'User copy', 'event' => 'form_submission', 'toType' => 'field', 'to' => '2', 'subject' => 'Copy', 'message' => '{all_fields}', 'isActive' => true ];
	$edited['fields'][3]->placeholder   = 'Edited placeholder';
	GFAPI::update_form( $edited, $c_id );
	$r4   = GravityProvisioner::provision( true, 'cli' );
	$cf4  = GFAPI::get_form( $c_id );
	$ok   = ! is_wp_error( $r4 ) && 'updated' === $r4['forms']['contact']['action'] && $c_id === (int) $r4['forms']['contact']['id']
		&& 'Send Message' === $cf4['button']['text'] && 'How can we help you?' === (string) $cf4['fields'][3]->placeholder
		&& isset( $cf4['notifications']['user_x'], $cf4['notifications'][ GravityProvisioner::NOTIFICATION_ID ] )
		&& ! is_wp_error( $entry_id ) && is_array( GFAPI::get_entry( (int) $entry_id ) ) && 'Test' === GFAPI::get_entry( (int) $entry_id )['1.3']
		&& ! empty( $cf4['is_active'] ) && count( $hk9_form_ids() ) === $count_before;
	$ok ? $hk9_pass( 'force_updates_in_place', sprintf( '--force: #%d updated (button/placeholder restored), hand-added notification "user_x" kept, entry #%d kept, still active, no new form', $c_id, (int) $entry_id ) ) : $hk9_fail( 'force_updates_in_place', wp_json_encode( [ $r4, $cf4['button'] ?? null, (string) ( $cf4['fields'][3]->placeholder ?? '' ), array_keys( (array) $cf4['notifications'] ), $entry_id ] ) );

	/* 4b. --force never rewrites a selected form that was not built by Heartland for that role (hand-picked, or the other role's form). */
	$tmp_hand = GFAPI::add_form(
		[
			'title'     => 'tmp-hand-picked contact form',
			'fields'    => [ [ 'id' => 1, 'type' => 'text', 'label' => 'Custom question', 'isRequired' => false ] ],
			'is_active' => '1',
			'button'    => [ 'type' => 'text', 'text' => 'Go' ],
		],
		true
	);
	if ( is_wp_error( $tmp_hand ) ) {
		$hk9_fail( 'force_leaves_unmarked_form_alone', $tmp_hand->get_error_message() );
	} else {
		$tmp_hand      = (int) $tmp_hand;
		$hk9_created[] = $tmp_hand;
		$hk9_set_forms( [ 'gravity_contact_form' => $tmp_hand ] );
		$st4b = GravityProvisioner::status();
		$r4b  = GravityProvisioner::provision( true, 'cli' );
		$hand = GFAPI::get_form( $tmp_hand );
		$ok   = ! is_wp_error( $r4b ) && 'skipped' === $r4b['forms']['contact']['action'] && $tmp_hand === (int) $r4b['forms']['contact']['id']
			&& str_contains( $r4b['forms']['contact']['message'], 'not created by Heartland' )
			&& 'updated' === $r4b['forms']['application']['action']
			&& 'tmp-hand-picked contact form' === (string) $hand['title'] && 1 === count( $hand['fields'] ) && 'Custom question' === (string) $hand['fields'][0]->label && 'Go' === (string) $hand['button']['text']
			&& null === GravityProvisioner::marker( $hand ) && empty( $hand['notifications'][ GravityProvisioner::NOTIFICATION_ID ] )
			&& $tmp_hand === (int) Store::raw()['forms']['gravity_contact_form']
			&& ! $st4b['forms']['contact']['provisioned'] && '' !== $st4b['forms']['contact']['note'] && '' === $st4b['forms']['contact']['marker_role']
			&& count( $hk9_form_ids() ) === $count_before + 1;
		$ok ? $hk9_pass( 'force_leaves_unmarked_form_alone', sprintf( 'hand-picked unmarked form #%d selected as contact → provision(true): action "skipped", title/fields/button/no marker untouched, id kept, no new form; status note "%s"', $tmp_hand, $st4b['forms']['contact']['note'] ) ) : $hk9_fail( 'force_leaves_unmarked_form_alone', wp_json_encode( [ $r4b, $hand['title'] ?? null, count( $hand['fields'] ?? [] ), $st4b['forms']['contact'] ] ) );

		// Settings panel: the force button is offered while a Heartland form is selected (application) …
		ob_start();
		GravityProvisioner::render_settings_panel();
		$panel_mixed = (string) ob_get_clean();
		// … and hidden when every selected form is hand-picked (nothing it could update or re-create).
		$hk9_set_forms( [ 'gravity_application_form' => $tmp_hand ] );
		ob_start();
		GravityProvisioner::render_settings_panel();
		$panel_hand = (string) ob_get_clean();
		( str_contains( $panel_mixed, 'force=1' ) && ! str_contains( $panel_hand, 'force=1' ) && ! str_contains( $panel_hand, 'Create the Heartland forms' ) && str_contains( $panel_hand, 'picked by hand' ) )
			? $hk9_pass( 'panel_hides_force_for_hand_picked_forms', 'force button present with a Heartland form selected; absent (and no Create button) when both selected forms are hand-picked, note "picked by hand" shown' )
			: $hk9_fail( 'panel_hides_force_for_hand_picked_forms', wp_json_encode( [ str_contains( $panel_mixed, 'force=1' ), str_contains( $panel_hand, 'force=1' ), str_contains( $panel_hand, 'Create the Heartland forms' ) ] ) );

		// The Heartland form of the other role selected for this role: skipped too (its fields would be replaced by the wrong definition).
		$hk9_set_forms( [ 'gravity_contact_form' => $a_id, 'gravity_application_form' => $a_id ] );
		$st4c = GravityProvisioner::status();
		$r4c  = GravityProvisioner::provision( true, 'cli' );
		$af4c = GFAPI::get_form( $a_id );
		$ok   = ! is_wp_error( $r4c ) && 'skipped' === $r4c['forms']['contact']['action'] && str_contains( $r4c['forms']['contact']['message'], 'is the Heartland "application" form' )
			&& 'updated' === $r4c['forms']['application']['action']
			&& 9 === count( $af4c['fields'] ) && 'application' === ( GravityProvisioner::marker( $af4c )['role'] ?? '' ) && str_starts_with( (string) $af4c['title'], 'Initial Application Inquiry' )
			&& [] !== $st4c['forms']['contact']['drift'] && 'application' === $st4c['forms']['contact']['marker_role']
			&& count( $hk9_form_ids() ) === $count_before + 1;
		$ok ? $hk9_pass( 'force_skips_other_role_form', sprintf( 'application form #%d selected as contact → provision(true): contact "skipped" (%s), form still the 9-field application form; status drift "%s"', $a_id, $r4c['forms']['contact']['message'], $st4c['forms']['contact']['drift'][0] ?? '' ) ) : $hk9_fail( 'force_skips_other_role_form', wp_json_encode( [ $r4c, count( $af4c['fields'] ?? [] ), $st4c['forms']['contact'] ] ) );
		$hk9_set_forms( [ 'gravity_contact_form' => $c_id, 'gravity_application_form' => $a_id ] );
		$count_before = count( $hk9_form_ids() );
	}

	/* 5. Drift: version marker, trashed form, deleted form (the site's own marked forms are hidden again so nothing is adopted). */
	add_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	$old_version = GFAPI::get_form( $a_id );
	$old_version[ GravityProvisioner::META_KEY ]['version'] = 0;
	GFAPI::update_form( $old_version, $a_id );
	$st5 = GravityProvisioner::status();
	( 'ok' === $st5['forms']['application']['state'] && 0 === $st5['forms']['application']['version'] && [] !== $st5['forms']['application']['drift'] && ! $st5['ok'] )
		? $hk9_pass( 'status_reports_version_drift', 'application form marked v0: drift "' . $st5['forms']['application']['drift'][0] . '"' )
		: $hk9_fail( 'status_reports_version_drift', wp_json_encode( $st5['forms']['application'] ) );

	GFAPI::update_form_property( $c_id, 'is_trash', '1' );
	FormProviders::flush();
	$st5b = GravityProvisioner::status();
	$r5   = GravityProvisioner::provision( false, 'cli' );
	$ok   = 'trashed' === $st5b['forms']['contact']['state'] && [] !== $st5b['forms']['contact']['drift']
		&& ! is_wp_error( $r5 ) && 'drift' === $r5['forms']['contact']['action'] && $c_id === (int) $r5['forms']['contact']['id']
		&& 'kept' === $r5['forms']['application']['action']
		&& $c_id === (int) Store::raw()['forms']['gravity_contact_form'];
	$ok ? $hk9_pass( 'status_reports_trashed_form', sprintf( 'contact #%d trashed → state=trashed, drift "%s"; provision() without --force reports drift and keeps the id (the outdated application form is kept too — only --force updates)', $c_id, $st5b['forms']['contact']['drift'][0] ) ) : $hk9_fail( 'status_reports_trashed_form', wp_json_encode( [ $st5b['forms']['contact'], $r5 ] ) );
	( ! FormProviders::resolve( [], 'contact' )['available'] )
		? $hk9_pass( 'trashed_form_falls_back', 'FormProviders::resolve() marks the trashed form unavailable (built-in form shown meanwhile)' )
		: $hk9_fail( 'trashed_form_falls_back', 'resolve() still available' );

	$count_before = count( $hk9_form_ids() );
	$ids_before   = $hk9_form_ids();
	$r5b          = GravityProvisioner::provision( true, 'cli' );
	$new_c        = ! is_wp_error( $r5b ) ? (int) $r5b['forms']['contact']['id'] : 0;
	// Only ids that did not exist before this call are ours to delete (never an adopted, pre-existing form).
	$hk9_created  = array_merge( $hk9_created, array_values( array_diff( $hk9_form_ids(), $ids_before ) ) );
	$trashed_still = GFAPI::get_form( $c_id );
	$ok = ! is_wp_error( $r5b ) && 'recreated' === $r5b['forms']['contact']['action'] && $new_c !== $c_id && $new_c > 0
		&& $new_c === (int) Store::raw()['forms']['gravity_contact_form'] && is_array( $trashed_still ) && ! empty( $trashed_still['is_trash'] )
		&& count( $hk9_form_ids() ) === $count_before + 1;
	$ok ? $hk9_pass( 'force_recreates_trashed_form', sprintf( '--force: new contact form #%d created and selected; trashed #%d left in the trash (never deleted)', $new_c, $c_id ) ) : $hk9_fail( 'force_recreates_trashed_form', wp_json_encode( [ $r5b, $trashed_still['is_trash'] ?? null ] ) );

	// Deleted form: status says so; without --force nothing is created.
	$hk9_set_forms( [ 'gravity_application_form' => 999999 ] );
	$st5c = GravityProvisioner::status();
	$r5c  = GravityProvisioner::provision( false, 'cli' );
	( 'missing' === $st5c['forms']['application']['state'] && ! is_wp_error( $r5c ) && 'drift' === $r5c['forms']['application']['action'] )
		? $hk9_pass( 'status_reports_missing_form', 'gravity_application_form=999999 → state=missing, provision() reports drift (no new form without --force)' )
		: $hk9_fail( 'status_reports_missing_form', wp_json_encode( [ $st5c['forms']['application'], $r5c ] ) );
	$hk9_set_forms( [ 'gravity_application_form' => $a_id ] );
	remove_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );

	/* 6. Rendering: the page parts print gform_wrapper inside .hk9-gf with the provisioned forms selected. */
	$hk9_set_forms( [ 'provider' => 'gravity', 'gravity_contact_form' => $new_c, 'gravity_application_form' => $a_id ] );
	$resolved = FormProviders::resolve( [ 'provider' => 'inherit' ], 'contact' );
	$html     = FormProviders::render( $resolved );
	$ok       = $resolved['available'] && 'gravity' === $resolved['provider'] && $new_c === $resolved['gravity_form_id']
		&& str_contains( $html, 'class="hk9-form-provider hk9-form-provider--gravity hk9-gf"' ) && str_contains( $html, 'gform_wrapper' ) && str_contains( $html, 'gform-theme--orbital' )
		&& str_contains( $html, 'hk9-gf-form--contact' ) && str_contains( $html, '</span> Required</p>' ) && ! str_contains( $html, 'indicates required fields' )
		&& str_contains( $html, 'gform_required_legend' );
	$ok ? $hk9_pass( 'render_gravity_in_hk9_gf', 'hk9_render_form_provider(): .hk9-form-provider.hk9-gf > .gform_wrapper.gform-theme--orbital (form ' . $new_c . '), legend "* Required"' ) : $hk9_fail( 'render_gravity_in_hk9_gf', substr( $html, 0, 400 ) );

	$contact_page = get_page_by_path( 'contact' );
	$app_page     = get_page_by_path( 'online-application' );
	if ( $contact_page instanceof WP_Post && function_exists( 'hk9_pages_section' ) ) {
		$GLOBALS['post'] = $contact_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test harness.
		setup_postdata( $contact_page );
		ob_start();
		get_template_part( 'template-parts/sections/form', null, [ 'data' => [], 'post_id' => $contact_page->ID, 'id' => 'form', 'template' => 'contact' ] );
		$part = (string) ob_get_clean();
		wp_reset_postdata();
		( str_contains( $part, 'hk9-gf' ) && str_contains( $part, 'gform_wrapper' ) && str_contains( $part, 'hk9-gf-form--contact' ) && ! str_contains( $part, 'class="hk9-form hk9-form--contact"' ) )
			? $hk9_pass( 'contact_part_renders_gravity', 'template-parts/sections/form.php on /contact/ prints .hk9-gf > .gform_wrapper (no built-in form)' )
			: $hk9_fail( 'contact_part_renders_gravity', substr( wp_strip_all_tags( $part ), 0, 200 ) );
	} else {
		$hk9_blocked( 'contact_part_renders_gravity', 'no /contact/ page or theme helpers unavailable' );
	}
	if ( $app_page instanceof WP_Post && function_exists( 'hk9_pages_section' ) ) {
		$GLOBALS['post'] = $app_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $app_page );
		ob_start();
		get_template_part( 'template-parts/sections/application_form', null, [ 'data' => hk9_pages_section( $app_page->ID, 'application', 'form' ), 'post_id' => $app_page->ID, 'id' => 'form' ] );
		$part = (string) ob_get_clean();
		wp_reset_postdata();
		( str_contains( $part, 'hk9-gf' ) && str_contains( $part, 'gform_wrapper' ) && str_contains( $part, 'hk9-gf-form--application' ) && str_contains( $part, 'hk9-form-wrap--provider' ) )
			? $hk9_pass( 'application_part_renders_gravity', 'template-parts/sections/application_form.php prints .hk9-form-wrap--provider > .hk9-gf > .gform_wrapper' )
			: $hk9_fail( 'application_part_renders_gravity', substr( wp_strip_all_tags( $part ), 0, 200 ) );
	} else {
		$hk9_blocked( 'application_part_renders_gravity', 'no /online-application/ page or theme helpers unavailable' );
	}

	/* 7. Import hook: hk9/import/finalized provisions on a fresh site only (ids empty + no run), logging through the context. */
	$hk9_set_forms( [ 'provider' => 'builtin', 'gravity_contact_form' => 0, 'gravity_application_form' => 0 ] );
	delete_option( GravityProvisioner::OPTION );
	add_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	$log = [];
	$ctx = new class( $log ) {
		public array $lines;
		public function __construct( array &$lines ) { $this->lines = &$lines; }
		public function info( string $key, string $message ): void { $this->lines[] = 'info: ' . $message; }
		public function warn( string $key, string $message ): void { $this->lines[] = 'warn: ' . $message; }
	};
	$count_before = count( $hk9_form_ids() );
	do_action( 'hk9/import/finalized', [ 'mode' => [ 'dry_run' => false ] ], $ctx );
	$after        = $hk9_form_ids();
	$import_new   = array_values( array_diff( $after, array_merge( $before_ids, $hk9_created ) ) );
	$hk9_created  = array_merge( $hk9_created, $import_new );
	$run7         = GravityProvisioner::last_run();
	( 2 === count( $import_new ) && 'import' === ( $run7['by'] ?? '' ) && 'gravity' === Store::raw()['forms']['provider'] && count( $log ) >= 2 && str_starts_with( $log[0], 'info: Gravity Forms:' ) )
		? $hk9_pass( 'import_finalized_provisions', sprintf( 'hk9/import/finalized → 2 forms created (#%s), by=import, provider=gravity; %d log lines via $ctx->info()', implode( ', #', $import_new ), count( $log ) ) )
		: $hk9_fail( 'import_finalized_provisions', wp_json_encode( [ $import_new, $run7, $log ] ) );
	remove_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	// A second finalize does nothing (ids set now).
	$count_before = count( $hk9_form_ids() );
	do_action( 'hk9/import/finalized', [ 'mode' => [ 'dry_run' => false ] ], $ctx );
	( $count_before === count( $hk9_form_ids() ) ) ? $hk9_pass( 'import_finalized_idempotent', 'second hk9/import/finalized created nothing' ) : $hk9_fail( 'import_finalized_idempotent', 'form count changed' );

	/* 8. Forms provisioned BEFORE the import (first Settings → Forms visit on a fresh site: recipients still empty → admin_email fallback):
	 *    hk9/import/finalized re-seeds the notification values that are still exactly as seeded; values edited in Gravity Forms stay. */
	$hk9_saved_recipients = array_intersect_key( Store::raw()['forms'] ?? [], array_flip( [ 'contact_recipients', 'application_recipients', 'from_name', 'from_email' ] ) ) + [ 'contact_recipients' => '', 'application_recipients' => '', 'from_name' => '', 'from_email' => '' ];
	$hk9_set_forms( [ 'provider' => 'builtin', 'gravity_contact_form' => 0, 'gravity_application_form' => 0, 'contact_recipients' => '', 'application_recipients' => '', 'from_name' => '', 'from_email' => '' ] );
	delete_option( GravityProvisioner::OPTION );
	add_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	$ids_before8  = $hk9_form_ids();
	$r8           = GravityProvisioner::provision( false, 'auto' ); // what maybe_auto_provision() runs on the first Settings → Forms visit
	$hk9_created  = array_merge( $hk9_created, array_values( array_diff( $hk9_form_ids(), $ids_before8 ) ) );
	remove_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	if ( is_wp_error( $r8 ) ) {
		$hk9_fail( 'pre_import_seed_uses_admin_email', $r8->get_error_message() );
	} else {
		$c8          = (int) $r8['forms']['contact']['id'];
		$a8          = (int) $r8['forms']['application']['id'];
		$admin_email = (string) get_option( 'admin_email' );
		$n8          = GFAPI::get_form( $c8 )['notifications'][ GravityProvisioner::NOTIFICATION_ID ] ?? [];
		$seed8       = GravityProvisioner::last_run()['seeded'] ?? [];
		( $admin_email === ( $n8['to'] ?? '' ) && $admin_email === ( $seed8['contact']['to'] ?? '' ) && $admin_email === ( $seed8['application']['to'] ?? '' ) && 'auto' === GravityProvisioner::last_run()['by'] )
			? $hk9_pass( 'pre_import_seed_uses_admin_email', sprintf( 'fresh site, recipients empty → auto run seeded both notifications with the admin e-mail (%s) and recorded the seeds in %s', $admin_email, GravityProvisioner::OPTION ) )
			: $hk9_fail( 'pre_import_seed_uses_admin_email', wp_json_encode( [ $n8['to'] ?? null, $seed8 ] ) );

		// The import's Options step writes the recipients / sender, then finalize fires.
		$hk9_set_forms( [ 'contact_recipients' => 'tmp-info@hk9-local.test', 'application_recipients' => 'tmp-director@hk9-local.test', 'from_name' => 'Tmp Sender' ] );
		$log          = [];
		$count_before = count( $hk9_form_ids() );
		do_action( 'hk9/import/finalized', [ 'mode' => [ 'dry_run' => false ] ], $ctx );
		$n8c   = GFAPI::get_form( $c8 )['notifications'][ GravityProvisioner::NOTIFICATION_ID ] ?? [];
		$n8a   = GFAPI::get_form( $a8 )['notifications'][ GravityProvisioner::NOTIFICATION_ID ] ?? [];
		$seed8 = GravityProvisioner::last_run()['seeded'] ?? [];
		$ok    = 'tmp-info@hk9-local.test' === ( $n8c['to'] ?? '' ) && 'tmp-director@hk9-local.test' === ( $n8a['to'] ?? '' )
			&& 'Tmp Sender' === ( $n8c['fromName'] ?? '' ) && 'Tmp Sender' === ( $n8a['fromName'] ?? '' ) && '{admin_email}' === ( $n8c['from'] ?? '' )
			&& '{Email Address:2}' === ( $n8c['replyTo'] ?? '' ) && ! empty( $n8c['isActive'] )
			&& 'tmp-info@hk9-local.test' === ( $seed8['contact']['to'] ?? '' ) && 'tmp-director@hk9-local.test' === ( $seed8['application']['to'] ?? '' )
			&& $count_before === count( $hk9_form_ids() ) && 'auto' === GravityProvisioner::last_run()['by']
			&& 2 === count( $log ) && str_contains( $log[0], 're-seeded' ) && str_contains( $log[0], 'tmp-info@hk9-local.test' );
		$ok ? $hk9_pass( 'import_finalized_refreshes_seeded_notifications', sprintf( 'after the import wrote the settings, finalize re-seeded contact → %s, application → %s, fromName "Tmp Sender" (no new form, no new run; %d log lines)', $n8c['to'], $n8a['to'], count( $log ) ) ) : $hk9_fail( 'import_finalized_refreshes_seeded_notifications', wp_json_encode( [ $n8c, $n8a, $seed8, $log, $count_before, count( $hk9_form_ids() ) ] ) );

		// A value edited in Gravity Forms since is not overwritten by a later import; a second finalize with unchanged settings is a no-op.
		$edited8 = GFAPI::get_form( $c8 );
		$edited8['notifications'][ GravityProvisioner::NOTIFICATION_ID ]['to'] = 'tmp-hand@hk9-local.test';
		GFAPI::update_form( $edited8, $c8 );
		$hk9_set_forms( [ 'contact_recipients' => 'tmp-info2@hk9-local.test' ] );
		$log = [];
		do_action( 'hk9/import/finalized', [ 'mode' => [ 'dry_run' => false ] ], $ctx );
		$n8d = GFAPI::get_form( $c8 )['notifications'][ GravityProvisioner::NOTIFICATION_ID ] ?? [];
		$n8e = GFAPI::get_form( $a8 )['notifications'][ GravityProvisioner::NOTIFICATION_ID ] ?? [];
		$log_a = $log;
		$log   = [];
		do_action( 'hk9/import/finalized', [ 'mode' => [ 'dry_run' => false ] ], $ctx );
		( 'tmp-hand@hk9-local.test' === ( $n8d['to'] ?? '' ) && 'Tmp Sender' === ( $n8d['fromName'] ?? '' ) && 'tmp-director@hk9-local.test' === ( $n8e['to'] ?? '' ) && [] === $log_a && [] === $log )
			? $hk9_pass( 'import_finalized_keeps_edited_notification', 'contact "to" edited in Gravity Forms (tmp-hand@…) survives a later finalize with changed settings; application untouched; no log lines when nothing changes' )
			: $hk9_fail( 'import_finalized_keeps_edited_notification', wp_json_encode( [ $n8d, $n8e, $log_a, $log ] ) );
	}

	/* 9. The Setup & Import screen no longer provisions (it is opened before the import writes the recipients); Settings → Forms does. */
	$hk9_set_forms( [ 'provider' => 'builtin', 'gravity_contact_form' => 0, 'gravity_application_form' => 0 ] );
	delete_option( GravityProvisioner::OPTION );
	add_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	$saved_pagenow = $GLOBALS['pagenow'] ?? null;
	$saved_get     = $_GET;
	$GLOBALS['pagenow'] = 'admin.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test harness.
	$_GET               = [ 'page' => 'hk9-import' ];
	$ids_before9        = $hk9_form_ids();
	GravityProvisioner::maybe_auto_provision();
	$import_screen_created = count( $hk9_form_ids() ) - count( $ids_before9 );
	$still_auto            = GravityProvisioner::should_auto_provision();
	$_GET                  = [ 'page' => 'hk9-settings', 'tab' => 'forms' ];
	GravityProvisioner::maybe_auto_provision();
	$settings_created = count( $hk9_form_ids() ) - count( $ids_before9 );
	$hk9_created      = array_merge( $hk9_created, array_values( array_diff( $hk9_form_ids(), $ids_before9 ) ) );
	$_GET             = $saved_get;
	$GLOBALS['pagenow'] = $saved_pagenow; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	remove_filter( 'hk9/forms/gravity_marked_forms', $hk9_ignore_marked );
	( 0 === $import_screen_created && $still_auto && 2 === $settings_created && 'auto' === ( GravityProvisioner::last_run()['by'] ?? '' ) )
		? $hk9_pass( 'import_screen_does_not_provision', 'admin_init on page=hk9-import created nothing (should_auto_provision() still true); page=hk9-settings&tab=forms created the 2 forms (by=auto)' )
		: $hk9_fail( 'import_screen_does_not_provision', wp_json_encode( [ $import_screen_created, $still_auto, $settings_created, GravityProvisioner::last_run() ] ) );
	$hk9_set_forms( $hk9_saved_recipients );
}

/* Cleanup: delete every form this test created (never the site's provisioned ones), restore settings + option. */
$hk9_created = array_values( array_diff( array_unique( $hk9_created ), $before_ids ) ); // belt and braces: a pre-existing id is never deleted
foreach ( $hk9_created as $fid ) {
	GFAPI::delete_form( (int) $fid );
}
FormProviders::flush();
Store::update( [ 'forms' => $hk9_saved_forms ] );
if ( null === $hk9_saved_option ) {
	delete_option( GravityProvisioner::OPTION );
} else {
	update_option( GravityProvisioner::OPTION, $hk9_saved_option, false );
}
$leftover = array_diff( $hk9_form_ids(), $before_ids );
$lost     = array_diff( $before_ids, $hk9_form_ids() );
( [] === $leftover && [] === $lost )
	? $hk9_pass( 'cleanup', sprintf( '%d test form(s) deleted, the %d pre-existing form(s) untouched; settings and last-run option restored (provider=%s, contact=%d, application=%d)', count( $hk9_created ), count( $before_ids ), (string) ( Store::raw()['forms']['provider'] ?? '' ), (int) ( Store::raw()['forms']['gravity_contact_form'] ?? 0 ), (int) ( Store::raw()['forms']['gravity_application_form'] ?? 0 ) ) )
	: $hk9_fail( 'cleanup', 'forms left behind: #' . implode( ', #', $leftover ) . '; pre-existing forms lost: #' . implode( ', #', $lost ) );

$fails = count( array_filter( $hk9_results, static fn( $r ) => 'FAIL' === $r[1] ) );
echo "\n", count( $hk9_results ), ' checks, ', $fails, " failed\n";
if ( $fails > 0 ) {
	exit( 1 );
}
