<?php
/**
 * Log tab.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_logs = BDSM_Logger::get_logs( 200 );

$bdsm_feature_labels = array(
	BDSM_Logger::FEATURE_TASK_REMINDER  => __( 'Task Reminder', 'bd-subscription-mailer' ),
	BDSM_Logger::FEATURE_FAILED_PAYMENT => __( 'Failed Payment', 'bd-subscription-mailer' ),
	BDSM_Logger::FEATURE_CARD_EXPIRY    => __( 'Card Expiry', 'bd-subscription-mailer' ),
);
?>

<form method="post" style="margin:12px 0;">
	<?php wp_nonce_field( 'bdsm_clear_log' ); ?>
	<input type="hidden" name="bdsm_action" value="clear_log">
	<?php
	submit_button(
		__( 'Clear log', 'bd-subscription-mailer' ),
		'delete',
		'submit',
		false,
		array( 'onclick' => "return confirm('" . esc_js( __( 'Delete all log entries?', 'bd-subscription-mailer' ) ) . "');" )
	);
	?>
</form>

<?php if ( empty( $bdsm_logs ) ) : ?>
	<p><?php esc_html_e( 'No log entries yet.', 'bd-subscription-mailer' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date/time (UTC)', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Feature', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Customer email', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Subscription', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Message #', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Note', 'bd-subscription-mailer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $bdsm_logs as $bdsm_row ) : ?>
				<tr>
					<td><?php echo esc_html( $bdsm_row->log_time ); ?></td>
					<td><?php echo esc_html( $bdsm_feature_labels[ $bdsm_row->feature ] ?? $bdsm_row->feature ); ?></td>
					<td><?php echo esc_html( $bdsm_row->customer_email ); ?></td>
					<td>
						<?php if ( $bdsm_row->subscription_id ) : ?>
							<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $bdsm_row->subscription_id . '&action=edit' ) ); ?>">
								#<?php echo esc_html( $bdsm_row->subscription_id ); ?>
							</a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><?php echo null === $bdsm_row->message_number ? '—' : esc_html( $bdsm_row->message_number ); ?></td>
					<td>
						<?php
						$bdsm_colors = array(
							'sent'      => '#00a32a',
							'skipped'   => '#dba617',
							'cancelled' => '#d63638',
						);
						$bdsm_color  = $bdsm_colors[ $bdsm_row->status ] ?? '#646970';
						?>
						<strong style="color:<?php echo esc_attr( $bdsm_color ); ?>;"><?php echo esc_html( $bdsm_row->status ); ?></strong>
					</td>
					<td><?php echo esc_html( $bdsm_row->note ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
