<?php
/**
 * Subscription watchdog.
 *
 * Self-contained module that recovers subscriptions which were paid but
 * left stuck on-hold (e.g. a webhook race or a stale cache that prevented
 * the status update from firing). Runs on its own recurring 30-minute
 * Action Scheduler job, in the same group as the plugin's email jobs.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detects and auto-activates "stuck" on-hold subscriptions, then emails a
 * diagnostic report.
 */
class BD_Watchdog {

	const CRON_HOOK        = 'bd_watchdog_run';
	const INTERVAL_SECONDS = 30 * MINUTE_IN_SECONDS;
	const LOG_SOURCE       = 'bd-subscription-watchdog';

	/**
	 * How recent the subscription's last order may be before we treat a
	 * lingering on-hold status as a genuine problem (avoids false positives
	 * on orders still processing).
	 */
	const GRACE_SECONDS = 15 * MINUTE_IN_SECONDS;

	/**
	 * How far back a paid renewal order counts as "this subscription should
	 * already be active".
	 */
	const PAID_WINDOW_SECONDS = 6 * HOUR_IN_SECONDS;

	/**
	 * Register runtime hooks. Called after the plugin's other classes load.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		// Schedule on init (Action Scheduler is reliably loaded by then) and
		// re-create the job if it was ever lost.
		add_action( 'init', array( __CLASS__, 'maybe_reschedule' ) );
	}

	/**
	 * Schedule the recurring action. Hooked on plugin activation and run as
	 * the init safety net. Idempotent.
	 */
	public static function schedule() {
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		// Remove any legacy WP-Cron event from older versions (does not touch
		// Action Scheduler actions).
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		if ( false === as_next_scheduled_action( self::CRON_HOOK, null, BDSM_AS_GROUP ) ) {
			as_schedule_recurring_action( time() + self::GRACE_SECONDS, self::INTERVAL_SECONDS, self::CRON_HOOK, array(), BDSM_AS_GROUP );
		}
	}

	/**
	 * Cancel the recurring action. Hooked on plugin deactivation.
	 */
	public static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::CRON_HOOK, array(), BDSM_AS_GROUP );
		}
	}

	/**
	 * Re-register the schedule if it has gone missing.
	 */
	public static function maybe_reschedule() {
		self::schedule();
	}

	/**
	 * Cron callback — find and fix stuck on-hold subscriptions.
	 */
	public static function run() {
		if ( ! class_exists( 'WC_Subscriptions' ) || ! function_exists( 'wcs_get_subscriptions' ) ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'on-hold',
				'subscriptions_per_page' => 50,
			)
		);

		$fixed = array();

		foreach ( $subscriptions as $subscription ) {
			$renewal_order = self::find_paid_renewal( $subscription );
			if ( ! $renewal_order ) {
				continue;
			}

			$date_paid          = $renewal_order->get_date_paid();
			$date_paid_display  = $date_paid ? $date_paid->date_i18n( 'Y-m-d H:i:s' ) : '';
			$payment_method     = $renewal_order->get_payment_method_title();
			if ( '' === $payment_method ) {
				$payment_method = $subscription->get_payment_method_title();
			}

			$note = sprintf(
				/* translators: 1: timestamp, 2: renewal order ID, 3: date paid, 4: payment method title. */
				__( 'BD Watchdog auto-activated this subscription on %1$s. Renewal order #%2$d was paid %3$s via %4$s but the subscription remained on-hold.', 'bd-subscription-mailer' ),
				current_time( 'mysql' ),
				$renewal_order->get_id(),
				$date_paid_display,
				$payment_method
			);

			$subscription->update_status( 'active', $note );

			$fixed[] = array(
				'subscription'   => $subscription,
				'renewal_order'  => $renewal_order,
				'date_paid'      => $date_paid_display,
				'payment_method' => $payment_method,
			);
		}

		if ( empty( $fixed ) ) {
			return;
		}

		self::send_report( $fixed );

		$ids = array();
		foreach ( $fixed as $item ) {
			$ids[] = $item['subscription']->get_id();
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning(
				sprintf(
					/* translators: 1: count, 2: comma-separated subscription IDs. */
					__( 'Auto-activated %1$d stuck on-hold subscription(s): %2$s', 'bd-subscription-mailer' ),
					count( $ids ),
					implode( ', ', $ids )
				),
				array( 'source' => self::LOG_SOURCE )
			);
		}
	}

	/**
	 * Return the most recent qualifying paid renewal order for a stuck
	 * subscription, or false if the subscription should stay on-hold.
	 *
	 * @param WC_Subscription $subscription Subscription under inspection.
	 * @return WC_Order|false
	 */
	private static function find_paid_renewal( $subscription ) {
		// Skip subscriptions whose latest order is still within the grace
		// window — it may simply be mid-processing.
		$last_order = $subscription->get_last_order( 'all', array( 'parent', 'renewal' ) );
		if ( $last_order ) {
			$created = $last_order->get_date_created();
			if ( $created && ( time() - $created->getTimestamp() ) < self::GRACE_SECONDS ) {
				return false;
			}
		}

		$renewal_orders = $subscription->get_related_orders( 'all', 'renewal' );

		foreach ( $renewal_orders as $renewal_order ) {
			if ( ! is_a( $renewal_order, 'WC_Abstract_Order' ) ) {
				continue;
			}
			if ( ! $renewal_order->has_status( array( 'completed', 'processing' ) ) ) {
				continue;
			}

			$date_paid = $renewal_order->get_date_paid();
			if ( ! $date_paid ) {
				continue;
			}

			$paid_ts = $date_paid->getTimestamp();
			if ( $paid_ts <= time() && ( time() - $paid_ts ) <= self::PAID_WINDOW_SECONDS ) {
				return $renewal_order;
			}
		}

		return false;
	}

	/**
	 * Send the diagnostic alert email.
	 *
	 * @param array $fixed List of fixed subscriptions (see run()).
	 */
	private static function send_report( array $fixed ) {
		$site_name = get_bloginfo( 'name' );
		$count     = count( $fixed );

		$subject = sprintf(
			/* translators: 1: site name, 2: number of subscriptions. */
			_n(
				'[%1$s] ⚠️ Watchdog: %2$d stuck subscription auto-activated',
				'[%1$s] ⚠️ Watchdog: %2$d stuck subscriptions auto-activated',
				$count,
				'bd-subscription-mailer'
			),
			$site_name,
			$count
		);

		$lines   = array();
		$lines[] = sprintf(
			/* translators: %d: number of subscriptions. */
			_n(
				'The watchdog found and re-activated %d subscription that was paid but stuck on-hold:',
				'The watchdog found and re-activated %d subscriptions that were paid but stuck on-hold:',
				$count,
				'bd-subscription-mailer'
			),
			$count
		);
		$lines[] = '';

		foreach ( $fixed as $item ) {
			$subscription = $item['subscription'];
			$order        = $item['renewal_order'];
			$edit_link    = admin_url( 'post.php?post=' . $subscription->get_id() . '&action=edit' );
			$customer     = trim( $subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name() );

			$lines[] = '----------------------------------------';
			$lines[] = sprintf( 'Subscription:   #%d', $subscription->get_id() );
			$lines[] = sprintf( 'Customer:       %s <%s>', $customer, $subscription->get_billing_email() );
			$lines[] = sprintf( 'Total:          %s %s', $subscription->get_total(), $subscription->get_currency() );
			$lines[] = sprintf( 'Date paid:      %s', $item['date_paid'] );
			$lines[] = sprintf( 'Payment method: %s', $item['payment_method'] );
			$lines[] = sprintf( 'Renewal order:  #%d', $order->get_id() );
			$lines[] = sprintf( 'Edit:           %s', $edit_link );
		}

		$lines[] = '----------------------------------------';
		$lines[] = '';
		$lines[] = self::diagnostic_section();

		$to = apply_filters( 'bd_watchdog_alert_email', get_option( 'admin_email' ) );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Static diagnostic text appended to every report.
	 *
	 * @return string
	 */
	private static function diagnostic_section() {
		return <<<TEXT
========================================
LIKELY CAUSES & REMEDIATION
========================================

1. Stripe webhook race condition
   Check Stripe dashboard -> Webhooks for 5xx delivery failures.
   Verify the webhook signing secret matches in
   WooCommerce -> Payments -> Stripe.

2. Action Scheduler claim collision
   Check WooCommerce -> Status -> Scheduled Actions -> Failed for the
   woocommerce_scheduled_subscription_payment hook.

3. Redis object cache stale lock
   Redis is active on this site; a stale cached subscription status may
   prevent the status update from firing. Test by running
   `wp redis flush && wp cron event run --due-now` after the next
   occurrence.

4. Stale DNS / slow requests
   The nginx log shows repeated failed DNS lookups for
   old-bluedogdiywebsites-com.dev on image requests. Fix the broken
   attachment in the media library, as it may slow cron requests into
   timeouts.

========================================
IMMEDIATE DIAGNOSIS (WP-CLI)
========================================

  wp action-scheduler list --status=failed --format=table
  wp redis flush && wp cron event run --due-now
TEXT;
	}
}
