<?php
/**
 * Settings tab.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_settings = bdsm_get_settings();
?>
<form method="post">
	<?php wp_nonce_field( 'bdsm_save_settings' ); ?>
	<input type="hidden" name="bdsm_action" value="save_settings">

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable plugin', 'bd-subscription-mailer' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="bdsm_enabled" value="1" <?php checked( 'yes', $bdsm_settings['enabled'] ); ?>>
					<?php esc_html_e( 'Master switch — when off, no emails are sent or scheduled by any feature.', 'bd-subscription-mailer' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Task Reminder', 'bd-subscription-mailer' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="bdsm_feature1_enabled" value="1" <?php checked( 'yes', $bdsm_settings['feature1_enabled'] ); ?>>
					<?php esc_html_e( 'Send a task reminder email after each successful subscription payment (per-site toggle).', 'bd-subscription-mailer' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Failed Payment and Card Expiry emails are always active while the plugin is enabled.', 'bd-subscription-mailer' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="bdsm_task_reminder_cc"><?php esc_html_e( 'Task Reminder CC', 'bd-subscription-mailer' ); ?></label>
			</th>
			<td>
				<input type="email" class="regular-text" id="bdsm_task_reminder_cc" name="bdsm_task_reminder_cc"
					value="<?php echo esc_attr( $bdsm_settings['task_reminder_cc'] ); ?>"
					placeholder="cc@example.com">
				<p class="description"><?php esc_html_e( 'Every Task Reminder email is CC\'d to this address. Leave empty for no CC.', 'bd-subscription-mailer' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="bdsm_failed_payment_cc"><?php esc_html_e( 'Failed Payment CC', 'bd-subscription-mailer' ); ?></label>
			</th>
			<td>
				<input type="email" class="regular-text" id="bdsm_failed_payment_cc" name="bdsm_failed_payment_cc"
					value="<?php echo esc_attr( $bdsm_settings['failed_payment_cc'] ); ?>"
					placeholder="cc@example.com">
				<p class="description"><?php esc_html_e( 'Every Failed Payment email (customer and internal) is CC\'d to this address. Leave empty for no CC.', 'bd-subscription-mailer' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="bdsm_card_expiry_cc"><?php esc_html_e( 'Card Expiry CC', 'bd-subscription-mailer' ); ?></label>
			</th>
			<td>
				<input type="email" class="regular-text" id="bdsm_card_expiry_cc" name="bdsm_card_expiry_cc"
					value="<?php echo esc_attr( $bdsm_settings['card_expiry_cc'] ); ?>"
					placeholder="cc@example.com">
				<p class="description"><?php esc_html_e( 'Every Card Expiry warning is CC\'d to this address. Leave empty for no CC.', 'bd-subscription-mailer' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="bdsm_support_link"><?php esc_html_e( 'Support link URL', 'bd-subscription-mailer' ); ?></label>
			</th>
			<td>
				<input type="url" class="regular-text" id="bdsm_support_link" name="bdsm_support_link"
					value="<?php echo esc_attr( $bdsm_settings['support_link'] ); ?>"
					placeholder="https://example.com/support">
				<p class="description"><?php esc_html_e( 'Used by the {support_link} template tag in Task Reminder emails.', 'bd-subscription-mailer' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
