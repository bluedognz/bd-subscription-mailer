<?php
/**
 * Shared helper functions.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log table name (mixed-case prefix per spec).
 *
 * @return string
 */
function bdsm_log_table() {
	global $wpdb;
	return $wpdb->prefix . 'bdSM_log';
}

/**
 * Expiry-sent tracking table name.
 *
 * @return string
 */
function bdsm_expiry_table() {
	global $wpdb;
	return $wpdb->prefix . 'bdSM_expiry_sent';
}

/**
 * Global settings merged with defaults.
 *
 * @return array
 */
function bdsm_get_settings() {
	$defaults = array(
		'enabled'           => 'yes',
		'feature1_enabled'  => 'no',
		'support_link'      => '',
		'from_name'         => '',
		'from_email'        => '',
		'task_reminder_cc'  => '',
		'failed_payment_cc' => '',
		'card_expiry_cc'    => '',
	);
	return wp_parse_args( (array) get_option( 'bdsm_settings', array() ), $defaults );
}

/**
 * Is the whole plugin enabled?
 *
 * @return bool
 */
function bdsm_is_enabled() {
	$settings = bdsm_get_settings();
	return 'yes' === $settings['enabled'];
}

/**
 * Is Feature 1 (Task Reminder) enabled?
 *
 * @return bool
 */
function bdsm_feature1_enabled() {
	$settings = bdsm_get_settings();
	return 'yes' === $settings['feature1_enabled'];
}

/**
 * Support link URL from global settings.
 *
 * @return string
 */
function bdsm_support_link() {
	$settings = bdsm_get_settings();
	return $settings['support_link'];
}

/**
 * Build a "From: Name <email>" header from settings, or empty string when
 * no valid From email is configured (lets WordPress / WP Mail SMTP decide).
 *
 * @return string
 */
function bdsm_from_header() {
	$settings = bdsm_get_settings();
	$email    = $settings['from_email'];

	if ( ! is_email( $email ) ) {
		return '';
	}

	$name = trim( (string) $settings['from_name'] );
	if ( '' === $name ) {
		return 'From: ' . $email;
	}

	return sprintf( 'From: %s <%s>', $name, $email );
}

/**
 * Per-feature CC address from global settings.
 *
 * @param string $feature 'task_reminder', 'failed_payment' or 'card_expiry'.
 * @return string Valid email address or empty string.
 */
function bdsm_get_cc( $feature ) {
	$settings = bdsm_get_settings();
	$cc       = $settings[ $feature . '_cc' ] ?? '';
	return is_email( $cc ) ? $cc : '';
}

/**
 * Default content for the six failed-payment messages.
 *
 * @return array
 */
function bdsm_default_failed_messages() {
	return array(
		1 => array(
			'delay_days' => 1,
			'recipient'  => 'customer',
			'subject'    => 'Payment issue with your {site_name} subscription',
			'body'       => "Hi {customer_first_name},\n\nThe latest payment of {order_total} for your subscription #{subscription_id} didn't go through.\n\nThis is usually a card issue and only takes a minute to fix. You can update your payment details here:\n{payment_update_link}\n\nThanks,\n{site_name}",
		),
		2 => array(
			'delay_days' => 2,
			'recipient'  => 'customer',
			'subject'    => 'Reminder: your {site_name} payment is still outstanding',
			'body'       => "Hi {customer_first_name},\n\nJust a friendly reminder that the payment of {order_total} for subscription #{subscription_id} is still outstanding.\n\nPlease update your payment details here:\n{payment_update_link}\n\nThanks,\n{site_name}",
		),
		3 => array(
			'delay_days' => 4,
			'recipient'  => 'customer',
			'subject'    => 'Action needed: payment failed for subscription #{subscription_id}',
			'body'       => "Hi {customer_first_name},\n\nWe still haven't been able to process the payment of {order_total} for your subscription #{subscription_id}.\n\nTo avoid any interruption to your service, please update your payment details:\n{payment_update_link}\n\nThanks,\n{site_name}",
		),
		4 => array(
			'delay_days' => 7,
			'recipient'  => 'customer',
			'subject'    => 'Final notice: subscription #{subscription_id} payment overdue',
			'body'       => "Hi {customer_first_name},\n\nThis is a final reminder that the payment of {order_total} for your subscription #{subscription_id} remains unpaid.\n\nIf payment isn't received your service may be suspended. Please update your payment details now:\n{payment_update_link}\n\nThanks,\n{site_name}",
		),
		5 => array(
			'delay_days' => 8,
			'recipient'  => '',
			'subject'    => '[Internal] Subscription #{subscription_id} unpaid for {days_overdue} days',
			'body'       => "Internal alert — customer has not paid after the full reminder sequence.\n\nCustomer: {customer_first_name} ({customer_email})\nSubscription: #{subscription_id}\nAmount: {order_total}\nDomain: {customer_domain}\nDays overdue: {days_overdue}\n\nFollow up manually.",
		),
		6 => array(
			'delay_days' => 38,
			'recipient'  => '',
			'subject'    => '[Internal] Subscription #{subscription_id} still unpaid after {days_overdue} days',
			'body'       => "Internal alert — this subscription is still unpaid {days_overdue} days after the original failure.\n\nCustomer: {customer_first_name} ({customer_email})\nSubscription: #{subscription_id}\nAmount: {order_total}\nDomain: {customer_domain}\n\nConsider suspension or cancellation.",
		),
	);
}

/**
 * Failed-payment messages merged with defaults.
 *
 * @return array
 */
function bdsm_get_failed_payment_messages() {
	$saved    = (array) get_option( 'bdsm_failed_payment', array() );
	$messages = bdsm_default_failed_messages();

	foreach ( $messages as $num => $defaults ) {
		if ( isset( $saved[ $num ] ) && is_array( $saved[ $num ] ) ) {
			$messages[ $num ] = wp_parse_args( $saved[ $num ], $defaults );
		}
	}
	return $messages;
}

/**
 * Default content for the three card-expiry emails, keyed by days-before.
 *
 * @return array
 */
function bdsm_default_expiry_emails() {
	$body = "Hi {customer_first_name},\n\nThe card we have on file for your subscription expires {card_expiry_month}/{card_expiry_year}.\n\nTo keep your service running without interruption, please update your payment details here:\n{payment_update_link}\n\nThanks,\n{site_name}";

	return array(
		45 => array(
			'subject' => 'Your card on file with {site_name} expires soon',
			'body'    => $body,
		),
		20 => array(
			'subject' => 'Reminder: your card on file expires {card_expiry_month}/{card_expiry_year}',
			'body'    => $body,
		),
		7  => array(
			'subject' => 'Urgent: your card expires in days — update your payment details',
			'body'    => $body,
		),
	);
}

/**
 * Card-expiry emails merged with defaults.
 *
 * @return array
 */
function bdsm_get_card_expiry_emails() {
	$saved  = (array) get_option( 'bdsm_card_expiry', array() );
	$emails = bdsm_default_expiry_emails();

	foreach ( $emails as $days => $defaults ) {
		if ( isset( $saved[ $days ] ) && is_array( $saved[ $days ] ) ) {
			$emails[ $days ] = wp_parse_args( $saved[ $days ], $defaults );
		}
	}
	return $emails;
}

/**
 * Customer domain stored against a subscription, if any.
 *
 * @param WC_Subscription $subscription Subscription.
 * @return string
 */
function bdsm_get_customer_domain( $subscription ) {
	foreach ( array( 'customer_domain', '_customer_domain' ) as $key ) {
		$value = $subscription->get_meta( $key );
		if ( '' !== (string) $value ) {
			return (string) $value;
		}
	}
	return '';
}

/**
 * Best-available URL for the customer to update their payment method.
 *
 * @param WC_Subscription $subscription Subscription.
 * @return string
 */
function bdsm_get_payment_update_link( $subscription ) {
	if ( is_callable( array( $subscription, 'get_change_payment_method_url' ) ) ) {
		$url = $subscription->get_change_payment_method_url();
		if ( $url ) {
			return $url;
		}
	}
	return $subscription->get_view_order_url();
}
