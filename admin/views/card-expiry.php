<?php
/**
 * Card Expiry tab (Feature 3).
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_emails = bdsm_get_card_expiry_emails();
?>

<p class="description" style="margin-top:12px;">
	<?php esc_html_e( 'A daily job runs at 7:00am UTC and checks every active subscription for a stored card expiry date. Warnings are sent 45, 20 and 7 days before expiry — never twice for the same card.', 'bd-subscription-mailer' ); ?>
	<br>
	<?php esc_html_e( 'Template tags:', 'bd-subscription-mailer' ); ?>
	<code>{customer_first_name}</code> <code>{customer_email}</code> <code>{card_expiry_month}</code> <code>{card_expiry_year}</code> <code>{payment_update_link}</code> <code>{site_name}</code>
</p>

<p class="bdsm-collapse-tools" style="margin:12px 0 0;">
	<button type="button" class="button-link bdsm-expand-all"><?php esc_html_e( 'Expand all', 'bd-subscription-mailer' ); ?></button> |
	<button type="button" class="button-link bdsm-collapse-all"><?php esc_html_e( 'Collapse all', 'bd-subscription-mailer' ); ?></button>
</p>

<form method="post">
	<?php wp_nonce_field( 'bdsm_save_card_expiry' ); ?>
	<input type="hidden" name="bdsm_action" value="save_card_expiry">

	<?php foreach ( $bdsm_emails as $bdsm_days => $bdsm_email ) : ?>
		<div class="postbox bdsm-collapsible" style="margin-top:16px;">
			<div class="postbox-header bdsm-collapse-header" style="padding:10px 12px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;">
				<strong>
					<?php
					printf(
						/* translators: %d: days before card expiry. */
						esc_html__( '%d days before expiry', 'bd-subscription-mailer' ),
						(int) $bdsm_days
					);
					?>
				</strong>
				<button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text"><?php esc_html_e( 'Toggle panel', 'bd-subscription-mailer' ); ?></span><span class="toggle-indicator" aria-hidden="true"></span></button>
			</div>
			<div class="inside">
				<p>
					<label for="bdsm_ce_subject_<?php echo esc_attr( $bdsm_days ); ?>" style="display:block;font-weight:600;margin-bottom:4px;">
						<?php esc_html_e( 'Subject', 'bd-subscription-mailer' ); ?>
					</label>
					<input type="text" class="large-text"
						id="bdsm_ce_subject_<?php echo esc_attr( $bdsm_days ); ?>"
						name="bdsm_ce_subject[<?php echo esc_attr( $bdsm_days ); ?>]"
						value="<?php echo esc_attr( $bdsm_email['subject'] ); ?>">
				</p>
				<?php
				wp_editor(
					$bdsm_email['body'],
					'bdsm_ce_body_' . $bdsm_days,
					array(
						'textarea_name' => 'bdsm_ce_body[' . $bdsm_days . ']',
						'textarea_rows' => 8,
						'media_buttons' => false,
					)
				);
				BDSM_Admin::render_test_controls( 'bdsm_ce_subject_' . $bdsm_days, 'bdsm_ce_body_' . $bdsm_days );
				?>
			</div>
		</div>
	<?php endforeach; ?>

	<?php submit_button(); ?>
</form>
