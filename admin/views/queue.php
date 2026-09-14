<?php
/**
 * Queue tab — pending Action Scheduler actions created by this plugin.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_items = BDSM_Admin::get_pending_actions();

/**
 * Resolve a subscription's customer email, cached per request so the six
 * queued items for one customer only trigger a single lookup.
 *
 * @param int $sub_id Subscription ID.
 * @return string
 */
function bdsm_queue_customer_email( $sub_id ) {
	static $cache = array();

	if ( ! $sub_id || ! function_exists( 'wcs_get_subscription' ) ) {
		return '';
	}
	if ( array_key_exists( $sub_id, $cache ) ) {
		return $cache[ $sub_id ];
	}

	$subscription      = wcs_get_subscription( $sub_id );
	$cache[ $sub_id ]  = $subscription ? $subscription->get_billing_email() : '';

	return $cache[ $sub_id ];
}

$bdsm_hook_labels = array(
	'bdsm_send_failed_payment_email' => __( 'Failed Payment email', 'bd-subscription-mailer' ),
	'bdsm_daily_card_expiry_check'   => __( 'Daily card expiry check', 'bd-subscription-mailer' ),
	'bd_watchdog_run'                => __( 'Subscription watchdog', 'bd-subscription-mailer' ),
	'bdsm_refresh_stripe_cards'      => __( 'Refresh card data from Stripe', 'bd-subscription-mailer' ),
);
?>

<form method="post" style="margin:12px 0;">
	<?php wp_nonce_field( 'bdsm_cancel_queue_subscription' ); ?>
	<input type="hidden" name="bdsm_action" value="cancel_queue_subscription">
	<label for="bdsm_subscription_id"><strong><?php esc_html_e( 'Cancel all queued emails for subscription ID:', 'bd-subscription-mailer' ); ?></strong></label>
	<input type="number" min="1" style="width:120px;" id="bdsm_subscription_id" name="bdsm_subscription_id" required>
	<?php submit_button( __( 'Cancel queued items', 'bd-subscription-mailer' ), 'secondary', 'submit', false ); ?>
</form>

<?php if ( empty( $bdsm_items ) ) : ?>
	<p><?php esc_html_e( 'No pending queued actions for this plugin.', 'bd-subscription-mailer' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Scheduled (UTC)', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Type', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Subscription', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Message #', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Action', 'bd-subscription-mailer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $bdsm_items as $bdsm_item ) : ?>
				<?php
				$bdsm_sub_id = (int) ( $bdsm_item['args']['subscription_id'] ?? 0 );
				$bdsm_msg_no = $bdsm_item['args']['message_number'] ?? null;
				$bdsm_email  = bdsm_queue_customer_email( $bdsm_sub_id );
				?>
				<tr>
					<td><?php echo esc_html( $bdsm_item['date'] ? $bdsm_item['date']->format( 'Y-m-d H:i:s' ) : '—' ); ?></td>
					<td><?php echo esc_html( $bdsm_hook_labels[ $bdsm_item['hook'] ] ?? $bdsm_item['hook'] ); ?></td>
					<td>
						<?php if ( $bdsm_sub_id ) : ?>
							<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $bdsm_sub_id . '&action=edit' ) ); ?>">
								#<?php echo esc_html( $bdsm_sub_id ); ?>
							</a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td>
						<?php if ( '' !== $bdsm_email ) : ?>
							<a href="<?php echo esc_url( 'mailto:' . $bdsm_email ); ?>"><?php echo esc_html( $bdsm_email ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><?php echo null === $bdsm_msg_no ? '—' : esc_html( $bdsm_msg_no ); ?></td>
					<td>
						<?php if ( 'bdsm_send_failed_payment_email' === $bdsm_item['hook'] ) : ?>
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'bdsm_cancel_queue_item' ); ?>
								<input type="hidden" name="bdsm_action" value="cancel_queue_item">
								<input type="hidden" name="bdsm_action_id" value="<?php echo esc_attr( $bdsm_item['id'] ); ?>">
								<button type="submit" class="button button-small"><?php esc_html_e( 'Cancel', 'bd-subscription-mailer' ); ?></button>
							</form>
						<?php else : ?>
							<em><?php esc_html_e( 'Recurring system job', 'bd-subscription-mailer' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
