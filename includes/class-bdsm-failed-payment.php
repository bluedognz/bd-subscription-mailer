<?php
/**
 * Feature 2 — Failed Payment Sequence.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedules a six-message dunning sequence via Action Scheduler when a
 * subscription payment fails, and cancels it silently on recovery.
 */
class BDSM_Failed_Payment {

	const SEND_HOOK   = 'bdsm_send_failed_payment_email';
	const FAILED_META = '_bdsm_payment_failed_at';

	/**
	 * Subscription statuses that end the sequence.
	 *
	 * @var string[]
	 */
	private $terminal_statuses = array( 'cancelled', 'pending-cancel', 'expired', 'switched', 'trash' );

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'woocommerce_subscription_payment_failed', array( $this, 'on_payment_failed' ), 10, 2 );
		// Priority 20 — runs after BDSM_Task_Reminder (10) has read the failure meta.
		add_action( 'woocommerce_subscription_payment_complete', array( $this, 'on_payment_complete' ), 20 );
		add_action( self::SEND_HOOK, array( $this, 'send_message' ), 10, 3 );
	}

	/**
	 * Payment failed — flag the subscription and queue the full sequence
	 * from the failure timestamp.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 * @param string          $new_status   Status the subscription changed to (unused).
	 */
	public function on_payment_failed( $subscription, $new_status = '' ) {
		if ( ! bdsm_is_enabled() || ! is_a( $subscription, 'WC_Subscription' ) ) {
			return;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$sub_id    = $subscription->get_id();
		$failed_at = time();

		// A repeat failure restarts the sequence from the new failure date.
		$this->cancel_pending_for( $sub_id );

		$subscription->update_meta_data( self::FAILED_META, $failed_at );
		$subscription->save();

		foreach ( bdsm_get_failed_payment_messages() as $num => $message ) {
			$delay_days = max( 0, (float) $message['delay_days'] );
			as_schedule_single_action(
				$failed_at + (int) round( $delay_days * DAY_IN_SECONDS ),
				self::SEND_HOOK,
				array(
					'subscription_id' => $sub_id,
					'message_number'  => (int) $num,
					'failed_at'       => $failed_at,
				),
				BDSM_AS_GROUP
			);
		}
	}

	/**
	 * Payment recovered — silently cancel the remaining queued messages
	 * and clear the failure flag.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 */
	public function on_payment_complete( $subscription ) {
		if ( ! is_a( $subscription, 'WC_Subscription' ) ) {
			return;
		}

		$had_failed = '' !== (string) $subscription->get_meta( self::FAILED_META );
		$cancelled  = $this->cancel_pending_for( $subscription->get_id() );

		if ( $had_failed ) {
			$subscription->delete_meta_data( self::FAILED_META );
			$subscription->save();
		}

		if ( $cancelled > 0 ) {
			BDSM_Logger::log(
				BDSM_Logger::FEATURE_FAILED_PAYMENT,
				$subscription->get_billing_email(),
				$subscription->get_id(),
				null,
				BDSM_Logger::STATUS_CANCELLED,
				sprintf(
					/* translators: %d: number of cancelled queued emails. */
					__( 'Payment recovered — %d queued email(s) cancelled.', 'bd-subscription-mailer' ),
					$cancelled
				)
			);
		}
	}

	/**
	 * Action Scheduler callback — send one message of the sequence after
	 * re-checking the subscription state.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @param int $message_number  1-6.
	 * @param int $failed_at       Original failure timestamp.
	 */
	public function send_message( $subscription_id, $message_number, $failed_at ) {
		if ( ! bdsm_is_enabled() ) {
			return;
		}

		$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : false;
		if ( ! $subscription ) {
			return;
		}

		$status = $subscription->get_status();
		$email  = $subscription->get_billing_email();

		// Recovered — the subscription is active again (or the failure flag is gone).
		if ( 'active' === $status || '' === (string) $subscription->get_meta( self::FAILED_META ) ) {
			$remaining = $this->cancel_pending_for( $subscription_id );
			BDSM_Logger::log(
				BDSM_Logger::FEATURE_FAILED_PAYMENT,
				$email,
				$subscription_id,
				(int) $message_number,
				BDSM_Logger::STATUS_CANCELLED,
				sprintf(
					/* translators: %d: number of cancelled queued emails. */
					__( 'Payment recovered — message and %d remaining email(s) cancelled.', 'bd-subscription-mailer' ),
					$remaining
				)
			);
			return;
		}

		// Subscription ended — stop the sequence.
		if ( in_array( $status, $this->terminal_statuses, true ) ) {
			$remaining = $this->cancel_pending_for( $subscription_id );
			BDSM_Logger::log(
				BDSM_Logger::FEATURE_FAILED_PAYMENT,
				$email,
				$subscription_id,
				(int) $message_number,
				BDSM_Logger::STATUS_CANCELLED,
				sprintf(
					/* translators: 1: subscription status, 2: number of cancelled queued emails. */
					__( 'Subscription %1$s — message and %2$d remaining email(s) cancelled.', 'bd-subscription-mailer' ),
					$status,
					$remaining
				)
			);
			return;
		}

		$messages = bdsm_get_failed_payment_messages();
		if ( ! isset( $messages[ $message_number ] ) ) {
			return;
		}
		$message = $messages[ $message_number ];
		$to_team = $message_number >= 5;

		if ( $to_team ) {
			$recipient = is_email( $message['recipient'] ) ? $message['recipient'] : get_option( 'admin_email' );
		} else {
			$recipient = $email;
		}

		$days_overdue = max( 0, (int) floor( ( time() - (int) $failed_at ) / DAY_IN_SECONDS ) );

		$tags = array(
			'customer_first_name' => $subscription->get_billing_first_name(),
			'customer_email'      => $email,
			'subscription_id'     => $subscription_id,
			'order_total'         => wp_strip_all_tags( wc_price( $subscription->get_total(), array( 'currency' => $subscription->get_currency() ) ) ),
			'customer_domain'     => bdsm_get_customer_domain( $subscription ),
			'site_name'           => get_bloginfo( 'name' ),
			'payment_update_link' => bdsm_get_payment_update_link( $subscription ),
			'days_overdue'        => $days_overdue,
		);

		if ( '' === trim( (string) $message['body'] ) ) {
			BDSM_Logger::log(
				BDSM_Logger::FEATURE_FAILED_PAYMENT,
				$email,
				$subscription_id,
				(int) $message_number,
				BDSM_Logger::STATUS_SKIPPED,
				__( 'No email content configured.', 'bd-subscription-mailer' )
			);
			return;
		}

		$sent = BDSM_Mailer::send( $recipient, $message['subject'], $message['body'], $tags, bdsm_get_cc( 'failed_payment' ) );

		BDSM_Logger::log(
			BDSM_Logger::FEATURE_FAILED_PAYMENT,
			$email,
			$subscription_id,
			(int) $message_number,
			$sent ? BDSM_Logger::STATUS_SENT : BDSM_Logger::STATUS_SKIPPED,
			$to_team
				? sprintf( /* translators: %s: recipient email. */ __( 'Internal email to %s', 'bd-subscription-mailer' ), $recipient )
				: ( $sent ? '' : __( 'wp_mail() returned false.', 'bd-subscription-mailer' ) )
		);
	}

	/**
	 * Cancel every pending sequence action for a subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return int Number of actions cancelled.
	 */
	private function cancel_pending_for( $subscription_id ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( 'ActionScheduler' ) ) {
			return 0;
		}

		$action_ids = as_get_scheduled_actions(
			array(
				'hook'     => self::SEND_HOOK,
				'group'    => BDSM_AS_GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1000,
			),
			'ids'
		);

		$count = 0;
		$store = ActionScheduler::store();

		foreach ( $action_ids as $action_id ) {
			$action = $store->fetch_action( $action_id );
			$args   = $action->get_args();
			if ( (int) ( $args['subscription_id'] ?? 0 ) === (int) $subscription_id ) {
				$store->cancel_action( $action_id );
				++$count;
			}
		}

		return $count;
	}
}
