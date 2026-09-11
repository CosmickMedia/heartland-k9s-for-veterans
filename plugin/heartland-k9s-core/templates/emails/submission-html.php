<?php
/**
 * Simple HTML notification body for a form submission (table layout, inline styles only).
 *
 * Available: $data = [
 *   form_id, form_label, site_name, site_url, submitted_at, source_url, admin_url,
 *   rows => [ [label, value, multiline], ... ], reply_to, subject
 * ]
 *
 * @package HK9\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $data['subject'] ); ?></title>
</head>
<body style="margin:0;padding:24px;background:#f3f0eb;font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;color:#15191f;">
	<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e0dc;border-radius:4px;">
		<tr>
			<td style="padding:20px 24px;background:#1c2f4a;color:#ffffff;border-radius:4px 4px 0 0;">
				<p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.85;"><?php echo esc_html( $data['site_name'] ); ?></p>
				<h1 style="margin:4px 0 0;font-size:20px;font-weight:600;color:#ffffff;">
					<?php
					printf(
						/* translators: %s: form label */
						esc_html__( 'New %s submission', 'heartland-k9s-core' ),
						esc_html( $data['form_label'] )
					);
					?>
				</h1>
			</td>
		</tr>
		<tr>
			<td style="padding:8px 24px 0;color:#52637a;font-size:13px;">
				<?php
				printf(
					/* translators: %s: date/time */
					esc_html__( 'Submitted: %s', 'heartland-k9s-core' ),
					esc_html( $data['submitted_at'] )
				);
				if ( '' !== $data['source_url'] ) {
					echo ' &middot; <a href="' . esc_url( $data['source_url'] ) . '" style="color:#b82e45;">' . esc_html__( 'Source page', 'heartland-k9s-core' ) . '</a>';
				}
				?>
			</td>
		</tr>
		<tr>
			<td style="padding:16px 24px 8px;">
				<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
					<?php foreach ( $data['rows'] as $hk9_row ) : ?>
						<tr>
							<th scope="row" align="left" valign="top" style="padding:8px 12px 8px 0;border-top:1px solid #e5e0dc;width:38%;font-weight:600;color:#1c2f4a;">
								<?php echo esc_html( $hk9_row['label'] ); ?>
							</th>
							<td valign="top" style="padding:8px 0;border-top:1px solid #e5e0dc;<?php echo $hk9_row['multiline'] ? 'white-space:pre-wrap;' : ''; ?>">
								<?php echo '' !== $hk9_row['value'] ? esc_html( $hk9_row['value'] ) : '&mdash;'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</td>
		</tr>
		<tr>
			<td style="padding:8px 24px 20px;color:#52637a;font-size:13px;border-top:1px solid #e5e0dc;">
				<?php if ( '' !== $data['reply_to'] ) : ?>
					<p style="margin:12px 0 0;"><?php esc_html_e( 'Reply to this email to respond to the sender.', 'heartland-k9s-core' ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $data['admin_url'] ) : ?>
					<p style="margin:8px 0 0;"><a href="<?php echo esc_url( $data['admin_url'] ); ?>" style="color:#b82e45;"><?php esc_html_e( 'View this submission in the site admin', 'heartland-k9s-core' ); ?></a></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</body>
</html>
