<?php
/**
 * HTML email wrapper template.
 *
 * Available variables:
 *   $bdsm_email_body — the (already-formatted) inner HTML.
 *   $bdsm_site_name  — the site name.
 *
 * Copy this file's markup to restyle every email the plugin sends.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $bdsm_site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:24px 0;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:94%;background-color:#ffffff;border-radius:6px;overflow:hidden;">
					<tr>
						<td style="background-color:#1d2327;padding:20px 32px;">
							<span style="font-family:Helvetica,Arial,sans-serif;font-size:18px;font-weight:bold;color:#ffffff;">
								<?php echo esc_html( $bdsm_site_name ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#333333;">
							<?php echo wp_kses_post( $bdsm_email_body ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:16px 32px;border-top:1px solid #eeeeee;font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#888888;">
							<?php echo esc_html( $bdsm_site_name ); ?>
						</td>
					</tr>
					<?php if ( bdsm_promo_footer_enabled() ) : ?>
					<tr>
						<td style="padding:0 32px 16px;font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#aaaaaa;text-align:center;">
							<?php esc_html_e( 'Sent via Subscription Mailer, by Blue Dog Software.', 'bd-subscription-mailer' ); ?>
							<a href="https://bluedog.software" style="color:#aaaaaa;text-decoration:underline;">bluedog.software</a>
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
