<?php
/**
 * Plain-text notification body for a form submission.
 *
 * Available: $data = [
 *   form_id, form_label, site_name, site_url, submitted_at, source_url, admin_url,
 *   rows => [ [label, value, multiline], ... ], reply_to, subject
 * ]
 *
 * @package HK9\Core
 */

defined( 'ABSPATH' ) || exit;

$hk9_lines = [];

$hk9_lines[] = sprintf(
	/* translators: 1: form label, 2: site name */
	__( 'New %1$s submission — %2$s', 'heartland-k9s-core' ),
	$data['form_label'],
	$data['site_name']
);
$hk9_lines[] = str_repeat( '=', 60 );
$hk9_lines[] = sprintf(
	/* translators: %s: date/time */
	__( 'Submitted: %s', 'heartland-k9s-core' ),
	$data['submitted_at']
);
if ( '' !== $data['source_url'] ) {
	$hk9_lines[] = sprintf(
		/* translators: %s: URL */
		__( 'Page: %s', 'heartland-k9s-core' ),
		$data['source_url']
	);
}
$hk9_lines[] = '';

foreach ( $data['rows'] as $hk9_row ) {
	if ( $hk9_row['multiline'] ) {
		$hk9_lines[] = $hk9_row['label'] . ':';
		$hk9_lines[] = '' !== $hk9_row['value'] ? $hk9_row['value'] : '—';
		$hk9_lines[] = '';
	} else {
		$hk9_lines[] = $hk9_row['label'] . ': ' . ( '' !== $hk9_row['value'] ? $hk9_row['value'] : '—' );
	}
}

$hk9_lines[] = '';
$hk9_lines[] = str_repeat( '-', 60 );
if ( '' !== $data['reply_to'] ) {
	$hk9_lines[] = __( 'Reply to this email to respond to the sender.', 'heartland-k9s-core' );
}
if ( '' !== $data['admin_url'] ) {
	$hk9_lines[] = sprintf(
		/* translators: %s: URL */
		__( 'View in the site admin: %s', 'heartland-k9s-core' ),
		$data['admin_url']
	);
}

echo wp_strip_all_tags( implode( "\n", $hk9_lines ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text mail body.
