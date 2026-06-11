<?php
/**
 * Uninstall — remove all plugin data.
 *
 * Drops both custom tables, deletes all options, removes the failure-flag
 * meta and deletes every Action Scheduler action in the plugin's group.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// 1. Custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bdSM_log`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bdSM_expiry_sent`" );

// 2. Options.
foreach ( array( 'bdsm_settings', 'bdsm_task_reminder', 'bdsm_failed_payment', 'bdsm_card_expiry', 'bdsm_db_version' ) as $bdsm_option ) {
	delete_option( $bdsm_option );
}

// 3. Failure-flag meta (both classic postmeta and HPOS orders meta).
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bdsm_payment_failed_at'" );

$bdsm_orders_meta = $wpdb->prefix . 'wc_orders_meta';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bdsm_orders_meta ) ) === $bdsm_orders_meta ) {
	$wpdb->query( "DELETE FROM `{$bdsm_orders_meta}` WHERE meta_key = '_bdsm_payment_failed_at'" );
}

// 4. Scheduled actions. WooCommerce (and therefore Action Scheduler) is not
// loaded during uninstall, so remove our group's rows directly.
$bdsm_as_groups  = $wpdb->prefix . 'actionscheduler_groups';
$bdsm_as_actions = $wpdb->prefix . 'actionscheduler_actions';
$bdsm_as_logs    = $wpdb->prefix . 'actionscheduler_logs';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bdsm_as_groups ) ) === $bdsm_as_groups ) {
	$bdsm_group_id = $wpdb->get_var(
		$wpdb->prepare( "SELECT group_id FROM `{$bdsm_as_groups}` WHERE slug = %s", 'bd-subscription-mailer' )
	);

	if ( $bdsm_group_id ) {
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bdsm_as_logs ) ) === $bdsm_as_logs ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE l FROM `{$bdsm_as_logs}` l INNER JOIN `{$bdsm_as_actions}` a ON a.action_id = l.action_id WHERE a.group_id = %d",
					$bdsm_group_id
				)
			);
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$bdsm_as_actions}` WHERE group_id = %d", $bdsm_group_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$bdsm_as_groups}` WHERE group_id = %d", $bdsm_group_id ) );
	}
}
// phpcs:enable
