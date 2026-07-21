<?php
/**
 * Cards tab — card expiry overview for active subscriptions.
 *
 * Uses the same lookup as the daily job, so this table shows exactly what
 * the Card Expiry feature sees.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_rows     = array();
$bdsm_sent_map = BDSM_Card_Expiry::get_sent_warnings();
$bdsm_no_card  = 0;
$bdsm_expired  = 0;
$bdsm_due_soon = 0;
$bdsm_stale    = 0;
$bdsm_stripe   = class_exists( 'BDSM_Stripe' ) && BDSM_Stripe::is_available();

if ( function_exists( 'wcs_get_subscriptions' ) ) {
	$bdsm_subscriptions = wcs_get_subscriptions(
		array(
			'subscription_status'    => 'active',
			'subscriptions_per_page' => -1,
		)
	);

	foreach ( $bdsm_subscriptions as $bdsm_sub ) {
		$bdsm_card = BDSM_Card_Expiry::get_card_details( $bdsm_sub );

		if ( null === $bdsm_card ) {
			++$bdsm_no_card;
			$bdsm_rows[] = array(
				'sub'  => $bdsm_sub,
				'card' => null,
				'days' => PHP_INT_MAX, // Sort no-card rows last.
			);
			continue;
		}

		$bdsm_days = BDSM_Card_Expiry::days_until_expiry( $bdsm_card['month'], $bdsm_card['year'] );

		if ( ! empty( $bdsm_card['stale'] ) ) {
			++$bdsm_stale;
		} elseif ( $bdsm_days < 0 ) {
			++$bdsm_expired;
		} elseif ( $bdsm_days <= max( BDSM_Card_Expiry::THRESHOLDS ) ) {
			++$bdsm_due_soon;
		}

		$bdsm_rows[] = array(
			'sub'  => $bdsm_sub,
			'card' => $bdsm_card,
			'days' => $bdsm_days,
		);
	}

	usort(
		$bdsm_rows,
		function ( $a, $b ) {
			return $a['days'] <=> $b['days'];
		}
	);
}
?>

<p class="description" style="margin-top:12px;max-width:820px;">
	<?php esc_html_e( 'Every ACTIVE subscription and the card it will be charged against, read exactly the way the daily Card Expiry job reads it. If a row shows "No card data", that subscription can never receive a warning.', 'bd-subscription-mailer' ); ?>
</p>

<p style="margin:12px 0;">
	<strong><?php echo esc_html( count( $bdsm_rows ) ); ?></strong> <?php esc_html_e( 'active subscriptions', 'bd-subscription-mailer' ); ?> &nbsp;|&nbsp;
	<span style="color:#d63638;"><strong><?php echo esc_html( $bdsm_expired ); ?></strong> <?php esc_html_e( 'expired', 'bd-subscription-mailer' ); ?></span> &nbsp;|&nbsp;
	<span style="color:#dba617;"><strong><?php echo esc_html( $bdsm_due_soon ); ?></strong> <?php esc_html_e( 'expiring within 45 days', 'bd-subscription-mailer' ); ?></span> &nbsp;|&nbsp;
	<span style="color:#2271b1;"><strong><?php echo esc_html( $bdsm_stale ); ?></strong> <?php esc_html_e( 'stale (card replaced)', 'bd-subscription-mailer' ); ?></span> &nbsp;|&nbsp;
	<span style="color:#646970;"><strong><?php echo esc_html( $bdsm_no_card ); ?></strong> <?php esc_html_e( 'with no card data', 'bd-subscription-mailer' ); ?></span>
</p>

<?php if ( ! $bdsm_stripe ) : ?>
	<div class="notice notice-warning inline"><p>
		<?php esc_html_e( 'No Stripe secret key found in the WooCommerce Stripe settings, so expiry dates below come from WooCommerce\'s saved snapshot and may be out of date (Stripe silently updates reissued cards). Warnings still send, but accuracy is not guaranteed.', 'bd-subscription-mailer' ); ?>
	</p></div>
<?php endif; ?>

<?php if ( isset( $_GET['bdsm_refresh_queued'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
	<div class="notice notice-info is-dismissible"><p>
		<?php esc_html_e( 'Stripe refresh queued — it runs in the background. Reload this page in a minute to see updated data.', 'bd-subscription-mailer' ); ?>
	</p></div>
<?php endif; ?>

<?php if ( $bdsm_stripe ) : ?>
<form method="post" style="margin:12px 0 0;">
	<?php wp_nonce_field( 'bdsm_refresh_stripe_cards' ); ?>
	<input type="hidden" name="bdsm_action" value="refresh_stripe_cards">
	<?php submit_button( __( 'Refresh card data from Stripe', 'bd-subscription-mailer' ), 'primary', 'submit', false ); ?>
	<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Fetches live expiry dates for all active subscriptions. Read-only; sends no emails.', 'bd-subscription-mailer' ); ?></span>
</form>
<?php endif; ?>

<form method="post" style="margin:12px 0;">
	<?php wp_nonce_field( 'bdsm_run_card_expiry_check' ); ?>
	<input type="hidden" name="bdsm_action" value="run_card_expiry_check">
	<?php
	submit_button(
		__( 'Run expiry check now', 'bd-subscription-mailer' ),
		'secondary',
		'submit',
		false,
		array( 'onclick' => "return confirm('" . esc_js( __( 'This runs the daily check immediately and WILL send real warning emails to any customer currently due. Continue?', 'bd-subscription-mailer' ) ) . "');" )
	);
	?>
	<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Sends real emails to anyone due. Already-sent warnings are never repeated.', 'bd-subscription-mailer' ); ?></span>
</form>

<?php if ( empty( $bdsm_rows ) ) : ?>
	<p><?php esc_html_e( 'No active subscriptions found.', 'bd-subscription-mailer' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Subscription', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Card', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Days left', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bd-subscription-mailer' ); ?></th>
				<th><?php esc_html_e( 'Warnings sent', 'bd-subscription-mailer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $bdsm_rows as $bdsm_row ) :
				$bdsm_sub  = $bdsm_row['sub'];
				$bdsm_card = $bdsm_row['card'];
				$bdsm_id   = $bdsm_sub->get_id();
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $bdsm_id . '&action=edit' ) ); ?>">
							#<?php echo esc_html( $bdsm_id ); ?>
						</a>
					</td>
					<td>
						<?php echo esc_html( trim( $bdsm_sub->get_billing_first_name() . ' ' . $bdsm_sub->get_billing_last_name() ) ); ?><br>
						<span style="color:#646970;"><?php echo esc_html( $bdsm_sub->get_billing_email() ); ?></span>
					</td>

					<?php if ( null === $bdsm_card ) : ?>
						<td colspan="4"><em style="color:#646970;"><?php esc_html_e( 'No card data — this subscription is skipped by the expiry check.', 'bd-subscription-mailer' ); ?></em></td>
						<td>—</td>
					<?php else : ?>
						<?php
						$bdsm_days     = $bdsm_row['days'];
						$bdsm_card_key = sprintf( '%04d-%02d', $bdsm_card['year'], $bdsm_card['month'] );
						$bdsm_sent     = $bdsm_sent_map[ $bdsm_id ][ $bdsm_card_key ] ?? array();
						sort( $bdsm_sent );

						if ( ! empty( $bdsm_card['stale'] ) ) {
							$bdsm_label = __( 'Stale — card replaced', 'bd-subscription-mailer' );
							$bdsm_color = '#2271b1';
						} elseif ( $bdsm_days < 0 ) {
							$bdsm_label = __( 'EXPIRED', 'bd-subscription-mailer' );
							$bdsm_color = '#d63638';
						} elseif ( $bdsm_days <= 7 ) {
							$bdsm_label = __( 'Critical', 'bd-subscription-mailer' );
							$bdsm_color = '#d63638';
						} elseif ( $bdsm_days <= 20 ) {
							$bdsm_label = __( 'Warning', 'bd-subscription-mailer' );
							$bdsm_color = '#dba617';
						} elseif ( $bdsm_days <= 45 ) {
							$bdsm_label = __( 'Due soon', 'bd-subscription-mailer' );
							$bdsm_color = '#dba617';
						} else {
							$bdsm_label = __( 'OK', 'bd-subscription-mailer' );
							$bdsm_color = '#00a32a';
						}
						?>
						<td>
							<?php
							echo esc_html( $bdsm_card['brand'] ? ucfirst( $bdsm_card['brand'] ) : __( 'Card', 'bd-subscription-mailer' ) );
							echo $bdsm_card['last4'] ? ' ••••' . esc_html( $bdsm_card['last4'] ) : '';
							?>
							<br>
							<span style="color:#646970;font-size:11px;">
								<?php
								if ( 'stripe' === $bdsm_card['source'] ) {
									$bdsm_checked = ! empty( $bdsm_card['checked'] )
										? sprintf(
											/* translators: %s: human-readable time difference. */
											__( 'live from Stripe (%s ago)', 'bd-subscription-mailer' ),
											human_time_diff( (int) $bdsm_card['checked'] )
										)
										: __( 'live from Stripe', 'bd-subscription-mailer' );
									echo esc_html( $bdsm_checked );
								} elseif ( 'token' === $bdsm_card['source'] ) {
									esc_html_e( 'saved token (snapshot)', 'bd-subscription-mailer' );
								} else {
									esc_html_e( 'from order meta', 'bd-subscription-mailer' );
								}
								?>
							</span>
						</td>
						<td><?php echo esc_html( sprintf( '%02d/%04d', $bdsm_card['month'], $bdsm_card['year'] ) ); ?></td>
						<td><?php echo esc_html( $bdsm_days ); ?></td>
						<td><strong style="color:<?php echo esc_attr( $bdsm_color ); ?>;"><?php echo esc_html( $bdsm_label ); ?></strong></td>
						<td>
							<?php
							if ( empty( $bdsm_sent ) ) {
								echo '—';
							} else {
								$bdsm_out = array();
								foreach ( $bdsm_sent as $bdsm_tier ) {
									$bdsm_out[] = $bdsm_tier . 'd';
								}
								echo esc_html( implode( ', ', $bdsm_out ) );
							}
							?>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
