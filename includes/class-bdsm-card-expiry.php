<?php
/**
 * Feature 3 — Card Expiry Warnings.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Daily 7:00am UTC Action Scheduler job that warns customers of active
 * subscriptions 45, 20 and 7 days before their card expires.
 */
class BDSM_Card_Expiry {

	const DAILY_HOOK = 'bdsm_daily_card_expiry_check';

	/**
	 * Warning tiers in ascending order (days before expiry).
	 *
	 * @var int[]
	 */
	private $thresholds = array( 7, 20, 45 );

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_schedule_daily_job' ) );
		add_action( self::DAILY_HOOK, array( $this, 'run_daily_check' ) );
	}

	/**
	 * Ensure the recurring 7:00am UTC job exists.
	 */
	public function maybe_schedule_daily_job() {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		if ( false === as_next_scheduled_action( self::DAILY_HOOK, null, BDSM_AS_GROUP ) ) {
			$next_run = strtotime( 'today 07:00 UTC' );
			if ( $next_run <= time() ) {
				$next_run = strtotime( 'tomorrow 07:00 UTC' );
			}
			as_schedule_recurring_action( $next_run, DAY_IN_SECONDS, self::DAILY_HOOK, array(), BDSM_AS_GROUP );
		}
	}

	/**
	 * Daily check across all active subscriptions.
	 */
	public function run_daily_check() {
		if ( ! bdsm_is_enabled() || ! function_exists( 'wcs_get_subscriptions' ) ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'active',
				'subscriptions_per_page' => -1,
			)
		);

		foreach ( $subscriptions as $subscription ) {
			$this->check_subscription( $subscription );
		}
	}

	/**
	 * Evaluate one subscription and send a warning if due.
	 *
	 * @param WC_Subscription $subscription Active subscription.
	 */
	private function check_subscription( $subscription ) {
		$expiry = $this->get_card_expiry( $subscription );
		if ( null === $expiry ) {
			return; // No card expiry data — skip silently.
		}

		list( $month, $year ) = $expiry;

		// Card is valid through the last day of its expiry month (UTC).
		$expiry_ts  = gmmktime( 23, 59, 59, $month + 1, 0, $year );
		$days_until = (int) floor( ( $expiry_ts - time() ) / DAY_IN_SECONDS );

		if ( $days_until < 0 || $days_until > max( $this->thresholds ) ) {
			return;
		}

		// The most urgent applicable tier: smallest threshold >= days remaining.
		$tier = null;
		foreach ( $this->thresholds as $threshold ) {
			if ( $days_until <= $threshold ) {
				$tier = $threshold;
				break;
			}
		}
		if ( null === $tier ) {
			return;
		}

		$card_key = sprintf( '%04d-%02d', $year, $month );

		if ( $this->already_sent( $subscription->get_id(), $tier, $card_key ) ) {
			return;
		}

		$emails = bdsm_get_card_expiry_emails();
		if ( ! isset( $emails[ $tier ] ) ) {
			return;
		}

		$email_address = $subscription->get_billing_email();
		$tags          = array(
			'customer_first_name' => $subscription->get_billing_first_name(),
			'customer_email'      => $email_address,
			'card_expiry_month'   => sprintf( '%02d', $month ),
			'card_expiry_year'    => $year,
			'payment_update_link' => bdsm_get_payment_update_link( $subscription ),
			'site_name'           => get_bloginfo( 'name' ),
		);

		$sent = BDSM_Mailer::send( $email_address, $emails[ $tier ]['subject'], $emails[ $tier ]['body'], $tags, bdsm_get_cc( 'card_expiry' ) );

		if ( $sent ) {
			$this->record_sent( $subscription->get_id(), $tier, $card_key );
		}

		BDSM_Logger::log(
			BDSM_Logger::FEATURE_CARD_EXPIRY,
			$email_address,
			$subscription->get_id(),
			null,
			$sent ? BDSM_Logger::STATUS_SENT : BDSM_Logger::STATUS_SKIPPED,
			sprintf(
				/* translators: 1: warning tier in days, 2: days until card expiry. */
				__( '%1$d-day warning (card expires in %2$d days).', 'bd-subscription-mailer' ),
				$tier,
				$days_until
			)
		);
	}

	/**
	 * Card expiry month/year from subscription or parent order meta.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return array{0:int,1:int}|null [month, year] or null when unknown.
	 */
	private function get_card_expiry( $subscription ) {
		// Primary source: the saved payment token, which is where WooCommerce
		// (and the Stripe gateway) actually stores card expiry.
		$from_token = $this->get_expiry_from_token( $subscription );
		if ( null !== $from_token ) {
			return $from_token;
		}

		$sources = array( $subscription );
		$parent  = $subscription->get_parent();
		if ( $parent ) {
			$sources[] = $parent;
		}

		foreach ( $sources as $source ) {
			$month = (int) $source->get_meta( '_stripe_card_expiry_month' );
			$year  = (int) $source->get_meta( '_stripe_card_expiry_year' );
			if ( $month >= 1 && $month <= 12 && $year > 0 ) {
				return array( $month, $this->normalize_year( $year ) );
			}
		}

		// Fallback: a combined expiry date string, e.g. "09/27", "09/2027" or "2027-09".
		foreach ( $sources as $source ) {
			$raw = (string) $source->get_meta( '_payment_method_expiry_date' );
			if ( '' === $raw ) {
				continue;
			}
			if ( preg_match( '#^(\d{1,2})\s*/\s*(\d{2,4})$#', trim( $raw ), $m ) ) {
				$month = (int) $m[1];
				$year  = $this->normalize_year( (int) $m[2] );
			} elseif ( preg_match( '#^(\d{4})-(\d{1,2})$#', trim( $raw ), $m ) ) {
				$month = (int) $m[2];
				$year  = (int) $m[1];
			} else {
				continue;
			}
			if ( $month >= 1 && $month <= 12 && $year > 2000 ) {
				return array( $month, $year );
			}
		}

		return null;
	}

	/**
	 * Card expiry from the customer's saved payment token.
	 *
	 * Prefers the token the subscription actually pays with (matched on the
	 * gateway's stored source / payment-method ID), then the customer's
	 * default card, then their most recent card. Non-card tokens are ignored.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return array{0:int,1:int}|null [month, year] or null when unknown.
	 */
	private function get_expiry_from_token( $subscription ) {
		if ( ! class_exists( 'WC_Payment_Tokens' ) ) {
			return null;
		}

		$user_id = $subscription->get_user_id();
		if ( ! $user_id ) {
			return null;
		}

		$tokens = WC_Payment_Tokens::get_customer_tokens( $user_id );
		if ( empty( $tokens ) ) {
			return null;
		}

		// The gateway's stored reference for this subscription's card.
		$wanted = '';
		foreach ( array( '_stripe_source_id', '_stripe_payment_method_id', '_payment_method_token' ) as $key ) {
			$value = (string) $subscription->get_meta( $key );
			if ( '' !== $value ) {
				$wanted = $value;
				break;
			}
		}

		$match   = null;
		$default = null;
		$newest  = null;

		foreach ( $tokens as $token ) {
			if ( ! is_a( $token, 'WC_Payment_Token_CC' ) ) {
				continue; // Ignore non-card tokens (no expiry data).
			}
			if ( '' !== $wanted && $token->get_token() === $wanted ) {
				$match = $token;
				break;
			}
			if ( $token->is_default() ) {
				$default = $token;
			}
			$newest = $token;
		}

		$token = $match ? $match : ( $default ? $default : $newest );
		if ( ! $token ) {
			return null;
		}

		$month = (int) $token->get_expiry_month();
		$year  = (int) $token->get_expiry_year();

		if ( $month >= 1 && $month <= 12 && $year > 0 ) {
			return array( $month, $this->normalize_year( $year ) );
		}

		return null;
	}

	/**
	 * Convert a two-digit year to four digits.
	 *
	 * @param int $year Year.
	 * @return int
	 */
	private function normalize_year( $year ) {
		return $year < 100 ? 2000 + $year : $year;
	}

	/**
	 * Has this tier already been sent for this subscription + card?
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param int    $days_before     Warning tier.
	 * @param string $card_key        YYYY-MM of the card expiry.
	 * @return bool
	 */
	private function already_sent( $subscription_id, $days_before, $card_key ) {
		global $wpdb;
		$table = bdsm_expiry_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE subscription_id = %d AND days_before = %d AND card_expiry = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$subscription_id,
				$days_before,
				$card_key
			)
		);

		return null !== $found;
	}

	/**
	 * Record a sent warning.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param int    $days_before     Warning tier.
	 * @param string $card_key        YYYY-MM of the card expiry.
	 */
	private function record_sent( $subscription_id, $days_before, $card_key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		$wpdb->insert(
			bdsm_expiry_table(),
			array(
				'subscription_id' => (int) $subscription_id,
				'days_before'     => (int) $days_before,
				'card_expiry'     => $card_key,
				'date_sent'       => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}
}
