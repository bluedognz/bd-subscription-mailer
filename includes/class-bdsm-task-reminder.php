<?php
/**
 * Feature 1 — Success Payment Task Reminder.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends a per-product reminder email immediately after a successful
 * subscription payment, unless the payment is a recovery from a failure.
 */
class BDSM_Task_Reminder {

	/**
	 * Meta key set by BDSM_Failed_Payment when a payment fails. If present
	 * on payment-complete, the payment is a recovery and no reminder sends.
	 */
	const FAILED_META = '_bdsm_payment_failed_at';

	/**
	 * Hook registration.
	 */
	public function __construct() {
		// Priority 10 — must run before BDSM_Failed_Payment clears the failure meta at 20.
		add_action( 'woocommerce_subscription_payment_complete', array( $this, 'on_payment_complete' ), 10 );
	}

	/**
	 * Handle a completed subscription payment.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 */
	public function on_payment_complete( $subscription ) {
		if ( ! bdsm_is_enabled() || ! bdsm_feature1_enabled() ) {
			return;
		}
		if ( ! is_a( $subscription, 'WC_Subscription' ) ) {
			return;
		}

		$sub_id = $subscription->get_id();
		$email  = $subscription->get_billing_email();

		// Recovery payment — previous attempt failed, do not send the task reminder.
		if ( '' !== (string) $subscription->get_meta( self::FAILED_META ) ) {
			BDSM_Logger::log(
				BDSM_Logger::FEATURE_TASK_REMINDER,
				$email,
				$sub_id,
				null,
				BDSM_Logger::STATUS_SKIPPED,
				__( 'Payment recovery — reminder not sent.', 'bd-subscription-mailer' )
			);
			return;
		}

		$config = (array) get_option( 'bdsm_task_reminder', array() );
		if ( empty( $config ) ) {
			return;
		}

		foreach ( $subscription->get_items() as $item ) {
			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$entry      = $this->enabled_entry_for( $config, $product_id, $item->get_product_id() );

			if ( null === $entry ) {
				continue;
			}

			if ( '' === trim( (string) $entry['body'] ) ) {
				BDSM_Logger::log(
					BDSM_Logger::FEATURE_TASK_REMINDER,
					$email,
					$sub_id,
					null,
					BDSM_Logger::STATUS_SKIPPED,
					__( 'No email content configured for product.', 'bd-subscription-mailer' ) . ' #' . $product_id
				);
				continue;
			}

			$tags = array(
				'customer_first_name' => $subscription->get_billing_first_name(),
				'subscription_id'     => $sub_id,
				'product_name'        => $item->get_name(),
				'support_link'        => bdsm_support_link(),
				'site_name'           => get_bloginfo( 'name' ),
			);

			$sent = BDSM_Mailer::send( $email, $entry['subject'], $entry['body'], $tags, bdsm_get_cc( 'task_reminder' ) );

			BDSM_Logger::log(
				BDSM_Logger::FEATURE_TASK_REMINDER,
				$email,
				$sub_id,
				null,
				$sent ? BDSM_Logger::STATUS_SENT : BDSM_Logger::STATUS_SKIPPED,
				$sent ? $item->get_name() : __( 'wp_mail() returned false.', 'bd-subscription-mailer' )
			);
		}
	}

	/**
	 * Find an enabled config entry for a line item, checking the variation
	 * ID first and falling back to the parent product ID.
	 *
	 * @param array $config       Saved task-reminder config.
	 * @param int   $product_id   Variation ID or product ID.
	 * @param int   $parent_id    Parent product ID.
	 * @return array|null
	 */
	private function enabled_entry_for( array $config, $product_id, $parent_id ) {
		foreach ( array_unique( array( (int) $product_id, (int) $parent_id ) ) as $id ) {
			if ( isset( $config[ $id ] ) && ! empty( $config[ $id ]['enabled'] ) ) {
				return wp_parse_args(
					$config[ $id ],
					array(
						'subject' => '',
						'body'    => '',
					)
				);
			}
		}
		return null;
	}
}
