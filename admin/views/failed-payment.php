<?php
/**
 * Failed Payment tab (Feature 2).
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_messages = bdsm_get_failed_payment_messages();

$bdsm_customer_tags = '<code>{customer_first_name}</code> <code>{customer_email}</code> <code>{subscription_id}</code> <code>{order_total}</code> <code>{payment_update_link}</code> <code>{site_name}</code> <code>{customer_domain}</code>';
$bdsm_team_tags     = '<code>{customer_first_name}</code> <code>{customer_email}</code> <code>{subscription_id}</code> <code>{order_total}</code> <code>{customer_domain}</code> <code>{site_name}</code> <code>{days_overdue}</code>';
?>

<p class="description" style="margin-top:12px;">
	<?php esc_html_e( 'The full sequence is scheduled when a subscription payment fails. Before each send the subscription is re-checked: if payment has recovered or the subscription was cancelled, all remaining emails are cancelled silently.', 'bd-subscription-mailer' ); ?>
</p>

<form method="post">
	<?php wp_nonce_field( 'bdsm_save_failed_payment' ); ?>
	<input type="hidden" name="bdsm_action" value="save_failed_payment">

	<?php foreach ( $bdsm_messages as $bdsm_num => $bdsm_msg ) : ?>
		<?php $bdsm_is_team = $bdsm_num >= 5; ?>
		<div class="postbox" style="margin-top:16px;">
			<div class="postbox-header" style="padding:10px 12px;">
				<strong>
					<?php
					printf(
						/* translators: %d: message number. */
						esc_html__( 'Email #%d', 'bd-subscription-mailer' ),
						(int) $bdsm_num
					);
					?>
					—
					<?php echo $bdsm_is_team ? esc_html__( 'TEAM (internal)', 'bd-subscription-mailer' ) : esc_html__( 'Customer', 'bd-subscription-mailer' ); ?>
				</strong>
			</div>
			<div class="inside">
				<p>
					<label for="bdsm_fp_delay_<?php echo esc_attr( $bdsm_num ); ?>" style="font-weight:600;">
						<?php esc_html_e( 'Delay (days after failure)', 'bd-subscription-mailer' ); ?>
					</label><br>
					<input type="number" step="0.5" min="0" style="width:90px;"
						id="bdsm_fp_delay_<?php echo esc_attr( $bdsm_num ); ?>"
						name="bdsm_fp_delay[<?php echo esc_attr( $bdsm_num ); ?>]"
						value="<?php echo esc_attr( $bdsm_msg['delay_days'] ); ?>">
				</p>

				<?php if ( $bdsm_is_team ) : ?>
					<p>
						<label for="bdsm_fp_recipient_<?php echo esc_attr( $bdsm_num ); ?>" style="font-weight:600;">
							<?php esc_html_e( 'Send to', 'bd-subscription-mailer' ); ?>
						</label><br>
						<input type="email" class="regular-text"
							id="bdsm_fp_recipient_<?php echo esc_attr( $bdsm_num ); ?>"
							name="bdsm_fp_recipient[<?php echo esc_attr( $bdsm_num ); ?>]"
							value="<?php echo esc_attr( 'customer' === $bdsm_msg['recipient'] ? '' : $bdsm_msg['recipient'] ); ?>"
							placeholder="director@bluedogwebdesign.com">
						<br><em><?php esc_html_e( 'Internal only — customer will not be notified. Falls back to the site admin email if empty.', 'bd-subscription-mailer' ); ?></em>
					</p>
				<?php endif; ?>

				<p>
					<label for="bdsm_fp_subject_<?php echo esc_attr( $bdsm_num ); ?>" style="display:block;font-weight:600;margin-bottom:4px;">
						<?php esc_html_e( 'Subject', 'bd-subscription-mailer' ); ?>
					</label>
					<input type="text" class="large-text"
						id="bdsm_fp_subject_<?php echo esc_attr( $bdsm_num ); ?>"
						name="bdsm_fp_subject[<?php echo esc_attr( $bdsm_num ); ?>]"
						value="<?php echo esc_attr( $bdsm_msg['subject'] ); ?>">
				</p>

				<?php
				wp_editor(
					$bdsm_msg['body'],
					'bdsm_fp_body_' . $bdsm_num,
					array(
						'textarea_name' => 'bdsm_fp_body[' . $bdsm_num . ']',
						'textarea_rows' => 8,
						'media_buttons' => false,
					)
				);
				BDSM_Admin::render_test_controls( 'bdsm_fp_subject_' . $bdsm_num, 'bdsm_fp_body_' . $bdsm_num );
				?>

				<p class="description">
					<?php esc_html_e( 'Template tags:', 'bd-subscription-mailer' ); ?>
					<?php echo wp_kses_post( $bdsm_is_team ? $bdsm_team_tags : $bdsm_customer_tags ); ?>
				</p>
			</div>
		</div>
	<?php endforeach; ?>

	<?php submit_button(); ?>
</form>
