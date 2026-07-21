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

	const DAILY_HOOK   = 'bdsm_daily_card_expiry_check';
	const REFRESH_HOOK = 'bdsm_refresh_stripe_cards';

	/**
	 * Warning tiers in ascending order (days before expiry).
	 */
	const THRESHOLDS = array( 7, 20, 45 );

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_schedule_daily_job' ) );
		add_action( self::DAILY_HOOK, array( $this, 'run_daily_check' ) );
		add_action( self::REFRESH_HOOK, array( $this, 'refresh_all_from_stripe' ) );
	}

	/**
	 * Refresh every active subscription's card data from Stripe.
	 */
	public function refresh_all_from_stripe() {
		if ( ! class_exists( 'BDSM_Stripe' ) || ! BDSM_Stripe::is_available() || ! function_exists( 'wcs_get_subscriptions' ) ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'active',
				'subscriptions_per_page' => -1,
			)
		);

		foreach ( $subscriptions as $subscription ) {
			BDSM_Stripe::refresh( $subscription );
		}
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

		$use_stripe = class_exists( 'BDSM_Stripe' ) && BDSM_Stripe::is_available();

		foreach ( $subscriptions as $subscription ) {
			// Refresh from Stripe first so warnings use the live expiry, not
			// the (often stale) snapshot WooCommerce saved with the token.
			if ( $use_stripe ) {
				BDSM_Stripe::refresh( $subscription );
			}
			$this->check_subscription( $subscription );
		}
	}

	/**
	 * Evaluate one subscription and send a warning if due.
	 *
	 * @param WC_Subscription $subscription Active subscription.
	 */
	private function check_subscription( $subscription ) {
		$card = self::get_card_details( $subscription );
		if ( null === $card ) {
			return; // No card expiry data — skip silently.
		}

		// A payment succeeded after this card supposedly expired, so the
		// stored date is provably stale (card was replaced/auto-updated).
		if ( ! empty( $card['stale'] ) ) {
			return;
		}

		$month = $card['month'];
		$year  = $card['year'];

		$days_until = self::days_until_expiry( $month, $year );
		$tier       = self::tier_for_days( $days_until );

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
	public static function get_card_expiry( $subscription ) {
		// Primary source: the saved payment token, which is where WooCommerce
		// (and the Stripe gateway) actually stores card expiry.
		$from_token = self::get_expiry_from_token( $subscription );
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
				return array( $month, self::normalize_year( $year ) );
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
				$year  = self::normalize_year( (int) $m[2] );
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
	private static function get_expiry_from_token( $subscription ) {
		$token = self::find_token( $subscription );
		if ( ! $token ) {
			return null;
		}

		$month = (int) $token->get_expiry_month();
		$year  = (int) $token->get_expiry_year();

		if ( $month >= 1 && $month <= 12 && $year > 0 ) {
			return array( $month, self::normalize_year( $year ) );
		}

		return null;
	}

	/**
	 * Card details for display: month, year, last4, brand and which source
	 * the data came from. Mirrors exactly what the daily job sees.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return array|null
	 */
	public static function get_card_details( $subscription ) {
		// 1. Live data previously fetched from Stripe — the only source that
		// reflects account-updater changes.
		if ( class_exists( 'BDSM_Stripe' ) ) {
			$stored = BDSM_Stripe::get_stored( $subscription );
			if ( $stored ) {
				return self::with_stale_flag(
					$subscription,
					array(
						'month'   => $stored['month'],
						'year'    => $stored['year'],
						'last4'   => $stored['last4'],
						'brand'   => $stored['brand'],
						'source'  => 'stripe',
						'checked' => $stored['checked'],
					)
				);
			}
		}

		// 2. The saved payment token (a snapshot; may be out of date).
		$token = self::find_token( $subscription );

		if ( $token ) {
			$month = (int) $token->get_expiry_month();
			$year  = (int) $token->get_expiry_year();
			if ( $month >= 1 && $month <= 12 && $year > 0 ) {
				return self::with_stale_flag(
					$subscription,
					array(
						'month'   => $month,
						'year'    => self::normalize_year( $year ),
						'last4'   => $token->get_last4(),
						'brand'   => $token->get_card_type(),
						'source'  => 'token',
						'checked' => 0,
					)
				);
			}
		}

		// 3. Legacy order meta.
		$expiry = self::get_card_expiry( $subscription );
		if ( null === $expiry ) {
			return null;
		}

		return self::with_stale_flag(
			$subscription,
			array(
				'month'   => $expiry[0],
				'year'    => $expiry[1],
				'last4'   => '',
				'brand'   => '',
				'source'  => 'meta',
				'checked' => 0,
			)
		);
	}

	/**
	 * Mark card data as stale when a payment succeeded after the card's
	 * stated expiry — proof the card was replaced or auto-updated and the
	 * stored date is fiction.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @param array           $details      Card details.
	 * @return array
	 */
	private static function with_stale_flag( $subscription, array $details ) {
		$expiry_ts = gmmktime( 23, 59, 59, (int) $details['month'] + 1, 0, (int) $details['year'] );
		$last_paid = self::last_paid_timestamp( $subscription );

		$details['stale']     = ( $last_paid > 0 && $last_paid > $expiry_ts );
		$details['last_paid'] = $last_paid;

		return $details;
	}

	/**
	 * Timestamp of the most recent successful payment, or 0.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return int
	 */
	private static function last_paid_timestamp( $subscription ) {
		try {
			$date = $subscription->get_date( 'last_order_date_paid', 'gmt' );
			if ( ! empty( $date ) ) {
				$timestamp = strtotime( $date . ' UTC' );
				if ( $timestamp ) {
					return (int) $timestamp;
				}
			}
		} catch ( Exception $e ) {
			// Older WooCommerce Subscriptions — fall through to the order.
			unset( $e );
		}

		$last_order = $subscription->get_last_order( 'all', array( 'parent', 'renewal' ) );
		if ( $last_order ) {
			$paid = $last_order->get_date_paid();
			if ( $paid ) {
				return $paid->getTimestamp();
			}
		}

		return 0;
	}

	/**
	 * Locate the card token for a subscription: the one it actually pays
	 * with where identifiable, else the customer's default card, else their
	 * most recent card.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @return WC_Payment_Token_CC|null
	 */
	private static function find_token( $subscription ) {
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

		return $match ? $match : ( $default ? $default : $newest );
	}

	/**
	 * Convert a two-digit year to four digits.
	 *
	 * @param int $year Year.
	 * @return int
	 */
	private static function normalize_year( $year ) {
		return $year < 100 ? 2000 + $year : $year;
	}

	/**
	 * Whole days until a card expires. Cards are valid through the last day
	 * of their expiry month (UTC). Negative once expired.
	 *
	 * @param int $month Expiry month (1-12).
	 * @param int $year  Expiry year (4-digit).
	 * @return int
	 */
	public static function days_until_expiry( $month, $year ) {
		$expiry_ts = gmmktime( 23, 59, 59, (int) $month + 1, 0, (int) $year );
		return (int) floor( ( $expiry_ts - time() ) / DAY_IN_SECONDS );
	}

	/**
	 * The most urgent applicable warning tier for a number of days remaining,
	 * or null when no warning is due (expired, or too far out).
	 *
	 * @param int $days_until Days until expiry.
	 * @return int|null
	 */
	public static function tier_for_days( $days_until ) {
		if ( $days_until < 0 || $days_until > max( self::THRESHOLDS ) ) {
			return null;
		}

		foreach ( self::THRESHOLDS as $threshold ) {
			if ( $days_until <= $threshold ) {
				return $threshold;
			}
		}

		return null;
	}

	/**
	 * Every warning already sent, as [ subscription_id ][ card_key ] => days[].
	 *
	 * @return array
	 */
	public static function get_sent_warnings() {
		global $wpdb;
		$table = bdsm_expiry_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		$rows = (array) $wpdb->get_results( "SELECT subscription_id, days_before, card_expiry FROM {$table}" );

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->subscription_id ][ $row->card_expiry ][] = (int) $row->days_before;
		}

		return $map;
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
