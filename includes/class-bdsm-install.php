<?php
/**
 * Activation / deactivation and table creation.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Install routines.
 */
class BDSM_Install {

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		update_option( 'bdsm_db_version', BDSM_VERSION );
	}

	/**
	 * Plugin deactivation — remove the recurring daily job.
	 * Pending failed-payment singles are left in place so the sequence
	 * resumes if the plugin is re-activated; uninstall removes everything.
	 */
	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'bdsm_daily_card_expiry_check', array(), BDSM_AS_GROUP );
		}
	}

	/**
	 * Create custom tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$log_table       = bdsm_log_table();
		$expiry_table    = bdsm_expiry_table();

		dbDelta(
			"CREATE TABLE {$log_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				log_time datetime NOT NULL,
				feature varchar(32) NOT NULL DEFAULT '',
				customer_email varchar(190) NOT NULL DEFAULT '',
				subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
				message_number tinyint(3) unsigned DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT '',
				note text DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY subscription_id (subscription_id),
				KEY log_time (log_time)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$expiry_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
				days_before smallint(5) unsigned NOT NULL DEFAULT 0,
				card_expiry varchar(7) NOT NULL DEFAULT '',
				date_sent datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY sub_days_card (subscription_id,days_before,card_expiry)
			) {$charset_collate};"
		);
	}
}
