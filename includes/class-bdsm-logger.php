<?php
/**
 * Email log.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes and reads the bdSM_log table.
 */
class BDSM_Logger {

	const FEATURE_TASK_REMINDER  = 'task_reminder';
	const FEATURE_FAILED_PAYMENT = 'failed_payment';
	const FEATURE_CARD_EXPIRY    = 'card_expiry';

	const STATUS_SENT      = 'sent';
	const STATUS_SKIPPED   = 'skipped';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Maximum rows kept in the table (the UI shows the last 200).
	 */
	const MAX_ROWS = 1000;

	/**
	 * Add a log entry.
	 *
	 * @param string   $feature        Feature key.
	 * @param string   $customer_email Customer email (or recipient).
	 * @param int      $subscription_id Subscription ID.
	 * @param int|null $message_number Message number (Feature 2) or null.
	 * @param string   $status         sent|skipped|cancelled.
	 * @param string   $note           Optional extra detail.
	 */
	public static function log( $feature, $customer_email, $subscription_id, $message_number, $status, $note = '' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		$wpdb->insert(
			bdsm_log_table(),
			array(
				'log_time'        => current_time( 'mysql', true ),
				'feature'         => $feature,
				'customer_email'  => $customer_email,
				'subscription_id' => (int) $subscription_id,
				'message_number'  => null === $message_number ? null : (int) $message_number,
				'status'          => $status,
				'note'            => $note,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' ) // Null values are inserted as NULL regardless of format (WP 4.4+).
		);

		self::trim();
	}

	/**
	 * Most recent log rows.
	 *
	 * @param int $limit Row limit.
	 * @return array
	 */
	public static function get_logs( $limit = 200 ) {
		global $wpdb;
		$table = bdsm_log_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", (int) $limit ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Empty the log table.
	 */
	public static function clear() {
		global $wpdb;
		$table = bdsm_log_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- custom table name.
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Keep the table from growing unbounded.
	 */
	private static function trim() {
		global $wpdb;
		$table = bdsm_log_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
		if ( $max_id > self::MAX_ROWS ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id <= %d", $max_id - self::MAX_ROWS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
