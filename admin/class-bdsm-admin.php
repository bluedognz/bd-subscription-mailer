<?php
/**
 * Admin UI — WooCommerce → BD Subscription Mailer.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin page, renders the tabs and handles saves.
 */
class BDSM_Admin {

	const PAGE_SLUG  = 'bd-subscription-mailer';
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Tab slug => label.
	 *
	 * @var array
	 */
	private $tabs = array();

	/**
	 * Hook registration.
	 */
	public function __construct() {
		$this->tabs = array(
			'settings'       => __( 'Settings', 'bd-subscription-mailer' ),
			'task-reminder'  => __( 'Task Reminder', 'bd-subscription-mailer' ),
			'failed-payment' => __( 'Failed Payment', 'bd-subscription-mailer' ),
			'card-expiry'    => __( 'Card Expiry', 'bd-subscription-mailer' ),
			'log'            => __( 'Log', 'bd-subscription-mailer' ),
			'queue'          => __( 'Queue', 'bd-subscription-mailer' ),
		);

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Add the submenu under WooCommerce.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'BD Subscription Mailer', 'bd-subscription-mailer' ),
			__( 'BD Subscription Mailer', 'bd-subscription-mailer' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Current tab from the query string.
	 *
	 * @return string
	 */
	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return isset( $this->tabs[ $tab ] ) ? $tab : 'settings';
	}

	/**
	 * URL for a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render the page chrome and current tab view.
	 */
	public function render_page() {
		$current = $this->current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BD Subscription Mailer', 'bd-subscription-mailer' ); ?></h1>

			<?php if ( ! bdsm_is_enabled() ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'The plugin is currently disabled in Settings — no emails will be sent or scheduled.', 'bd-subscription-mailer' ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['bdsm_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php esc_html_e( 'Saved.', 'bd-subscription-mailer' ); ?>
				</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $this->tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( self::tab_url( $slug ) ); ?>"
						class="nav-tab <?php echo $slug === $current ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php include BDSM_PLUGIN_DIR . 'admin/views/' . $current . '.php'; ?>
		</div>
		<?php
	}

	/**
	 * Route POST actions (saves, log clear, queue cancel).
	 */
	public function handle_actions() {
		if ( empty( $_POST['bdsm_action'] ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['bdsm_action'] ) );
		check_admin_referer( 'bdsm_' . $action );

		switch ( $action ) {
			case 'save_settings':
				$this->save_settings();
				$this->redirect( 'settings' );
				break;

			case 'save_task_reminder':
				$this->save_task_reminder();
				$this->redirect( 'task-reminder' );
				break;

			case 'save_failed_payment':
				$this->save_failed_payment();
				$this->redirect( 'failed-payment' );
				break;

			case 'save_card_expiry':
				$this->save_card_expiry();
				$this->redirect( 'card-expiry' );
				break;

			case 'clear_log':
				BDSM_Logger::clear();
				$this->redirect( 'log' );
				break;

			case 'cancel_queue_item':
				$this->cancel_queue_item();
				$this->redirect( 'queue' );
				break;

			case 'cancel_queue_subscription':
				$this->cancel_queue_subscription();
				$this->redirect( 'queue' );
				break;
		}
	}

	/**
	 * Redirect back to a tab with a saved notice.
	 *
	 * @param string $tab Tab slug.
	 */
	private function redirect( $tab ) {
		wp_safe_redirect( add_query_arg( 'bdsm_saved', '1', self::tab_url( $tab ) ) );
		exit;
	}

	/**
	 * Save global settings.
	 */
	private function save_settings() {
		update_option(
			'bdsm_settings',
			array(
				'enabled'           => empty( $_POST['bdsm_enabled'] ) ? 'no' : 'yes',
				'feature1_enabled'  => empty( $_POST['bdsm_feature1_enabled'] ) ? 'no' : 'yes',
				'support_link'      => isset( $_POST['bdsm_support_link'] ) ? esc_url_raw( wp_unslash( $_POST['bdsm_support_link'] ) ) : '',
				'task_reminder_cc'  => isset( $_POST['bdsm_task_reminder_cc'] ) ? sanitize_email( wp_unslash( $_POST['bdsm_task_reminder_cc'] ) ) : '',
				'failed_payment_cc' => isset( $_POST['bdsm_failed_payment_cc'] ) ? sanitize_email( wp_unslash( $_POST['bdsm_failed_payment_cc'] ) ) : '',
				'card_expiry_cc'    => isset( $_POST['bdsm_card_expiry_cc'] ) ? sanitize_email( wp_unslash( $_POST['bdsm_card_expiry_cc'] ) ) : '',
			)
		);
	}

	/**
	 * Save Feature 1 per-product config.
	 */
	private function save_task_reminder() {
		$config      = array();
		$product_ids = isset( $_POST['bdsm_tr_product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['bdsm_tr_product_ids'] ) ) : array();
		$enabled     = isset( $_POST['bdsm_tr_enabled'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['bdsm_tr_enabled'] ) ) : array();
		$subjects    = isset( $_POST['bdsm_tr_subject'] ) ? (array) wp_unslash( $_POST['bdsm_tr_subject'] ) : array();
		$bodies      = isset( $_POST['bdsm_tr_body'] ) ? (array) wp_unslash( $_POST['bdsm_tr_body'] ) : array();

		foreach ( $product_ids as $product_id ) {
			$config[ $product_id ] = array(
				'enabled' => in_array( $product_id, $enabled, true ) ? 1 : 0,
				'subject' => isset( $subjects[ $product_id ] ) ? sanitize_text_field( $subjects[ $product_id ] ) : '',
				'body'    => isset( $bodies[ $product_id ] ) ? wp_kses_post( $bodies[ $product_id ] ) : '',
			);
		}

		update_option( 'bdsm_task_reminder', $config );
	}

	/**
	 * Save Feature 2 message config.
	 */
	private function save_failed_payment() {
		$config   = array();
		$subjects = isset( $_POST['bdsm_fp_subject'] ) ? (array) wp_unslash( $_POST['bdsm_fp_subject'] ) : array();
		$bodies   = isset( $_POST['bdsm_fp_body'] ) ? (array) wp_unslash( $_POST['bdsm_fp_body'] ) : array();
		$delays   = isset( $_POST['bdsm_fp_delay'] ) ? (array) wp_unslash( $_POST['bdsm_fp_delay'] ) : array();
		$to       = isset( $_POST['bdsm_fp_recipient'] ) ? (array) wp_unslash( $_POST['bdsm_fp_recipient'] ) : array();

		foreach ( array_keys( bdsm_default_failed_messages() ) as $num ) {
			$config[ $num ] = array(
				'subject'    => isset( $subjects[ $num ] ) ? sanitize_text_field( $subjects[ $num ] ) : '',
				'body'       => isset( $bodies[ $num ] ) ? wp_kses_post( $bodies[ $num ] ) : '',
				'delay_days' => isset( $delays[ $num ] ) ? max( 0, (float) $delays[ $num ] ) : 0,
				'recipient'  => ( $num >= 5 && isset( $to[ $num ] ) ) ? sanitize_email( $to[ $num ] ) : 'customer',
			);
		}

		update_option( 'bdsm_failed_payment', $config );
	}

	/**
	 * Save Feature 3 email config.
	 */
	private function save_card_expiry() {
		$config   = array();
		$subjects = isset( $_POST['bdsm_ce_subject'] ) ? (array) wp_unslash( $_POST['bdsm_ce_subject'] ) : array();
		$bodies   = isset( $_POST['bdsm_ce_body'] ) ? (array) wp_unslash( $_POST['bdsm_ce_body'] ) : array();

		foreach ( array_keys( bdsm_default_expiry_emails() ) as $days ) {
			$config[ $days ] = array(
				'subject' => isset( $subjects[ $days ] ) ? sanitize_text_field( $subjects[ $days ] ) : '',
				'body'    => isset( $bodies[ $days ] ) ? wp_kses_post( $bodies[ $days ] ) : '',
			);
		}

		update_option( 'bdsm_card_expiry', $config );
	}

	/**
	 * Cancel one queued action (must belong to this plugin's group).
	 */
	private function cancel_queue_item() {
		$action_id = isset( $_POST['bdsm_action_id'] ) ? absint( wp_unslash( $_POST['bdsm_action_id'] ) ) : 0;
		if ( ! $action_id || ! class_exists( 'ActionScheduler' ) ) {
			return;
		}

		$store  = ActionScheduler::store();
		$action = $store->fetch_action( $action_id );

		if ( BDSM_AS_GROUP !== $action->get_group() ) {
			return;
		}

		$args = $action->get_args();
		$store->cancel_action( $action_id );

		BDSM_Logger::log(
			BDSM_Logger::FEATURE_FAILED_PAYMENT,
			'',
			(int) ( $args['subscription_id'] ?? 0 ),
			isset( $args['message_number'] ) ? (int) $args['message_number'] : null,
			BDSM_Logger::STATUS_CANCELLED,
			__( 'Cancelled manually from the Queue page.', 'bd-subscription-mailer' )
		);
	}

	/**
	 * Cancel every queued action for a subscription ID.
	 */
	private function cancel_queue_subscription() {
		$sub_id = isset( $_POST['bdsm_subscription_id'] ) ? absint( wp_unslash( $_POST['bdsm_subscription_id'] ) ) : 0;
		if ( ! $sub_id ) {
			return;
		}

		$count = 0;
		foreach ( self::get_pending_actions() as $item ) {
			if ( (int) ( $item['args']['subscription_id'] ?? 0 ) === $sub_id ) {
				ActionScheduler::store()->cancel_action( $item['id'] );
				++$count;
			}
		}

		if ( $count > 0 ) {
			BDSM_Logger::log(
				BDSM_Logger::FEATURE_FAILED_PAYMENT,
				'',
				$sub_id,
				null,
				BDSM_Logger::STATUS_CANCELLED,
				sprintf(
					/* translators: %d: number of cancelled queued emails. */
					__( '%d queued item(s) cancelled manually from the Queue page.', 'bd-subscription-mailer' ),
					$count
				)
			);
		}
	}

	/**
	 * Pending Action Scheduler actions created by this plugin.
	 *
	 * @return array[] Each: id, hook, args, date (DateTime|null).
	 */
	public static function get_pending_actions() {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( 'ActionScheduler' ) ) {
			return array();
		}

		$action_ids = as_get_scheduled_actions(
			array(
				'group'    => BDSM_AS_GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 200,
				'orderby'  => 'date',
				'order'    => 'ASC',
			),
			'ids'
		);

		$store = ActionScheduler::store();
		$items = array();

		foreach ( $action_ids as $action_id ) {
			$action  = $store->fetch_action( $action_id );
			$items[] = array(
				'id'   => (int) $action_id,
				'hook' => $action->get_hook(),
				'args' => (array) $action->get_args(),
				'date' => $store->get_date( $action_id ),
			);
		}

		return $items;
	}
}
