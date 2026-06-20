<?php
/**
 * Plugin Name:       BD Subscription Mailer
 * Plugin URI:        https://github.com/bluedognz/bd-subscription-mailer
 * Description:       Lightweight automated emails for WooCommerce Subscriptions — payment task reminders, failed payment sequences and card expiry warnings. Replaces AutomateWoo.
 * Version:           1.6.0
 * Author:            Blue Dog Digital
 * Author URI:        https://www.bluedogdigitalmarketing.com/
 * Text Domain:       bd-subscription-mailer
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * License:           GPL-2.0+
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

define( 'BDSM_VERSION', '1.6.0' );
define( 'BDSM_PLUGIN_FILE', __FILE__ );
define( 'BDSM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDSM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BDSM_AS_GROUP', 'bd-subscription-mailer' );

require_once BDSM_PLUGIN_DIR . 'includes/bdsm-functions.php';
require_once BDSM_PLUGIN_DIR . 'includes/class-bdsm-install.php';

// ── GitHub auto-updates via Plugin Update Checker ────────────
require_once BDSM_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

$bdsm_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/bluedognz/bd-subscription-mailer',
	__FILE__,
	'bd-subscription-mailer'
);
$bdsm_updater->getVcsApi()->enableReleaseAssets();

// Optional: define in wp-config.php to avoid GitHub rate limits.
//   define( 'BDSM_GH_TOKEN', 'ghp_yourtoken' );
if ( defined( 'BDSM_GH_TOKEN' ) && BDSM_GH_TOKEN ) {
	$bdsm_updater->setAuthentication( BDSM_GH_TOKEN );
}

// Show the plugin icon on the Updates / Plugins screens and details modal.
add_filter(
	'puc_request_info_result-bd-subscription-mailer',
	function ( $info ) {
		if ( is_object( $info ) ) {
			$info->icons = array(
				'svg' => BDSM_PLUGIN_URL . 'assets/icon.svg',
				'1x'  => BDSM_PLUGIN_URL . 'assets/icon-128.png',
				'2x'  => BDSM_PLUGIN_URL . 'assets/icon-256.png',
			);
		}
		return $info;
	}
);

register_activation_hook( __FILE__, array( 'BDSM_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BDSM_Install', 'deactivate' ) );

/**
 * Declare HPOS (custom order tables) compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Bootstrap the plugin once all plugins are loaded.
 */
function bdsm_bootstrap() {

	if ( ! class_exists( 'WC_Subscriptions' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'BD Subscription Mailer requires WooCommerce and WooCommerce Subscriptions to be active.', 'bd-subscription-mailer' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once BDSM_PLUGIN_DIR . 'includes/class-bdsm-logger.php';
	require_once BDSM_PLUGIN_DIR . 'includes/class-bdsm-mailer.php';
	require_once BDSM_PLUGIN_DIR . 'includes/class-bdsm-task-reminder.php';
	require_once BDSM_PLUGIN_DIR . 'includes/class-bdsm-failed-payment.php';
	require_once BDSM_PLUGIN_DIR . 'includes/class-bdsm-card-expiry.php';

	new BDSM_Task_Reminder();
	new BDSM_Failed_Payment();
	new BDSM_Card_Expiry();

	if ( is_admin() ) {
		require_once BDSM_PLUGIN_DIR . 'admin/class-bdsm-admin.php';
		new BDSM_Admin();
	}

	// Upgrade-safe: make sure tables exist (covers updates pushed without re-activation).
	if ( get_option( 'bdsm_db_version' ) !== BDSM_VERSION ) {
		BDSM_Install::create_tables();
		update_option( 'bdsm_db_version', BDSM_VERSION );
	}
}
add_action( 'plugins_loaded', 'bdsm_bootstrap', 20 );
