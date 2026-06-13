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
			'export-import'  => __( 'Export / Import', 'bd-subscription-mailer' ),
			'log'            => __( 'Log', 'bd-subscription-mailer' ),
			'queue'          => __( 'Queue', 'bd-subscription-mailer' ),
		);

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_bdsm_send_test', array( $this, 'ajax_send_test' ) );
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
		if ( in_array( $current, array( 'task-reminder', 'failed-payment', 'card-expiry' ), true ) ) {
			$this->print_test_script();
			$this->print_collapse_script();
		}
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

			case 'import_data':
				$this->import_data();
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
	 * Build the Base64 export string for the portable email content.
	 *
	 * Task Reminder is intentionally excluded — it is keyed by product ID,
	 * which differs between sites.
	 *
	 * @return string Base64-encoded JSON payload.
	 */
	public static function build_export_string() {
		$settings = bdsm_get_settings();

		$payload = array(
			'_bdsm_export' => 1,
			'version'      => BDSM_VERSION,
			'site'         => home_url(),
			'date'         => current_time( 'mysql' ),
			'data'         => array(
				'failed_payment' => bdsm_get_failed_payment_messages(),
				'card_expiry'    => bdsm_get_card_expiry_emails(),
				'settings'       => array(
					'support_link'      => $settings['support_link'],
					'task_reminder_cc'  => $settings['task_reminder_cc'],
					'failed_payment_cc' => $settings['failed_payment_cc'],
					'card_expiry_cc'    => $settings['card_expiry_cc'],
				),
			),
		);

		return base64_encode( (string) wp_json_encode( $payload ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- portable transport string, not obfuscation.
	}

	/**
	 * Decode an export string into its payload array.
	 *
	 * @param string $string Base64 export string.
	 * @return array|WP_Error
	 */
	public static function decode_export_string( $string ) {
		$json = base64_decode( trim( $string ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- portable transport string.
		if ( false === $json ) {
			return new WP_Error( 'bdsm_import', __( 'That is not a valid export string.', 'bd-subscription-mailer' ) );
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) || empty( $payload['_bdsm_export'] ) || empty( $payload['data'] ) ) {
			return new WP_Error( 'bdsm_import', __( 'That export string is not from BD Subscription Mailer.', 'bd-subscription-mailer' ) );
		}

		return $payload;
	}

	/**
	 * Apply a pasted export string to the selected sections.
	 */
	private function import_data() {
		check_admin_referer( 'bdsm_import_data' );

		$string  = isset( $_POST['bdsm_import_string'] ) ? wp_unslash( $_POST['bdsm_import_string'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and sanitised field-by-field below.
		$payload = self::decode_export_string( $string );

		if ( is_wp_error( $payload ) ) {
			$this->redirect_with( 'export-import', 'bdsm_import_error', rawurlencode( $payload->get_error_message() ) );
		}

		$sections = isset( $_POST['bdsm_import_sections'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['bdsm_import_sections'] ) ) : array();
		$data     = $payload['data'];
		$applied  = array();

		if ( in_array( 'failed_payment', $sections, true ) && ! empty( $data['failed_payment'] ) ) {
			update_option( 'bdsm_failed_payment', $this->sanitize_failed_payment_import( (array) $data['failed_payment'] ) );
			$applied[] = __( 'Failed Payment emails', 'bd-subscription-mailer' );
		}

		if ( in_array( 'card_expiry', $sections, true ) && ! empty( $data['card_expiry'] ) ) {
			update_option( 'bdsm_card_expiry', $this->sanitize_card_expiry_import( (array) $data['card_expiry'] ) );
			$applied[] = __( 'Card Expiry emails', 'bd-subscription-mailer' );
		}

		if ( in_array( 'settings', $sections, true ) && ! empty( $data['settings'] ) ) {
			$current = bdsm_get_settings();
			$in      = (array) $data['settings'];

			$current['support_link']      = isset( $in['support_link'] ) ? esc_url_raw( $in['support_link'] ) : $current['support_link'];
			$current['task_reminder_cc']  = isset( $in['task_reminder_cc'] ) ? sanitize_email( $in['task_reminder_cc'] ) : $current['task_reminder_cc'];
			$current['failed_payment_cc'] = isset( $in['failed_payment_cc'] ) ? sanitize_email( $in['failed_payment_cc'] ) : $current['failed_payment_cc'];
			$current['card_expiry_cc']    = isset( $in['card_expiry_cc'] ) ? sanitize_email( $in['card_expiry_cc'] ) : $current['card_expiry_cc'];

			update_option( 'bdsm_settings', $current );
			$applied[] = __( 'Settings (support link + CC addresses)', 'bd-subscription-mailer' );
		}

		if ( empty( $applied ) ) {
			$this->redirect_with( 'export-import', 'bdsm_import_error', rawurlencode( __( 'Nothing imported — select at least one section.', 'bd-subscription-mailer' ) ) );
		}

		$this->redirect_with( 'export-import', 'bdsm_imported', rawurlencode( implode( ', ', $applied ) ) );
	}

	/**
	 * Sanitise imported failed-payment messages, merged onto defaults.
	 *
	 * @param array $in Imported messages.
	 * @return array
	 */
	private function sanitize_failed_payment_import( array $in ) {
		$out = array();
		foreach ( bdsm_default_failed_messages() as $num => $defaults ) {
			$row            = isset( $in[ $num ] ) && is_array( $in[ $num ] ) ? $in[ $num ] : array();
			$out[ $num ]    = array(
				'subject'    => isset( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : $defaults['subject'],
				'body'       => isset( $row['body'] ) ? wp_kses_post( $row['body'] ) : $defaults['body'],
				'delay_days' => isset( $row['delay_days'] ) ? max( 0, (float) $row['delay_days'] ) : $defaults['delay_days'],
				'recipient'  => ( $num >= 5 && isset( $row['recipient'] ) && is_email( $row['recipient'] ) ) ? sanitize_email( $row['recipient'] ) : 'customer',
			);
		}
		return $out;
	}

	/**
	 * Sanitise imported card-expiry emails, merged onto defaults.
	 *
	 * @param array $in Imported emails.
	 * @return array
	 */
	private function sanitize_card_expiry_import( array $in ) {
		$out = array();
		foreach ( bdsm_default_expiry_emails() as $days => $defaults ) {
			$row           = isset( $in[ $days ] ) && is_array( $in[ $days ] ) ? $in[ $days ] : array();
			$out[ $days ]  = array(
				'subject' => isset( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : $defaults['subject'],
				'body'    => isset( $row['body'] ) ? wp_kses_post( $row['body'] ) : $defaults['body'],
			);
		}
		return $out;
	}

	/**
	 * Redirect to a tab with one query arg set.
	 *
	 * @param string $tab   Tab slug.
	 * @param string $key   Query arg key.
	 * @param string $value Query arg value (already URL-encoded if needed).
	 */
	private function redirect_with( $tab, $key, $value = '1' ) {
		wp_safe_redirect( add_query_arg( $key, $value, self::tab_url( $tab ) ) );
		exit;
	}

	/**
	 * Render the "send test email" controls under an email editor.
	 *
	 * @param string $subject_id DOM id of the subject input.
	 * @param string $editor_id  DOM id of the wp_editor textarea.
	 */
	public static function render_test_controls( $subject_id, $editor_id ) {
		?>
		<p class="bdsm-test-row" style="margin-top:8px;padding-top:8px;border-top:1px dashed #ccd0d4;">
			<input type="email" class="regular-text bdsm-test-email"
				placeholder="<?php esc_attr_e( 'you@example.com', 'bd-subscription-mailer' ); ?>">
			<button type="button" class="button bdsm-test-send"
				data-subject="<?php echo esc_attr( $subject_id ); ?>"
				data-editor="<?php echo esc_attr( $editor_id ); ?>">
				<?php esc_html_e( 'Send test email', 'bd-subscription-mailer' ); ?>
			</button>
			<span class="bdsm-test-result" style="margin-left:8px;"></span>
			<br><em class="description"><?php esc_html_e( 'Sends the content currently in this editor (saved or not) with sample data in place of template tags. No CC is added.', 'bd-subscription-mailer' ); ?></em>
		</p>
		<?php
	}

	/**
	 * Print the test-email JS once, after the tab views.
	 */
	private function print_test_script() {
		?>
		<script>
		( function () {
			function bdsmGetBody( id ) {
				if ( window.tinymce ) {
					var ed = window.tinymce.get( id );
					if ( ed && ! ed.isHidden() ) {
						return ed.getContent();
					}
				}
				var ta = document.getElementById( id );
				return ta ? ta.value : '';
			}

			document.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.bdsm-test-send' );
				if ( ! btn ) {
					return;
				}

				var row     = btn.closest( '.bdsm-test-row' );
				var email   = row.querySelector( '.bdsm-test-email' ).value.trim();
				var result  = row.querySelector( '.bdsm-test-result' );
				var subject = document.getElementById( btn.dataset.subject );

				if ( ! email || email.indexOf( '@' ) < 1 ) {
					result.style.color = '#d63638';
					result.textContent = <?php echo wp_json_encode( __( 'Enter a valid email address first.', 'bd-subscription-mailer' ) ); ?>;
					return;
				}

				btn.disabled       = true;
				result.style.color = '';
				result.textContent = <?php echo wp_json_encode( __( 'Sending…', 'bd-subscription-mailer' ) ); ?>;

				var fd = new FormData();
				fd.append( 'action', 'bdsm_send_test' );
				fd.append( '_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'bdsm_send_test' ) ); ?> );
				fd.append( 'test_email', email );
				fd.append( 'subject', subject ? subject.value : '' );
				fd.append( 'body', bdsmGetBody( btn.dataset.editor ) );

				fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						result.style.color = data.success ? '#00a32a' : '#d63638';
						result.textContent = ( data.data && data.data.message ) ? data.data.message : '';
					} )
					.catch( function () {
						result.style.color = '#d63638';
						result.textContent = <?php echo wp_json_encode( __( 'Request failed.', 'bd-subscription-mailer' ) ); ?>;
					} )
					.finally( function () {
						btn.disabled = false;
					} );
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * Print the collapse/expand JS once, after the tab views. Relies on the
	 * core .postbox.closed CSS to hide the body and rotate the chevron.
	 */
	private function print_collapse_script() {
		?>
		<script>
		( function () {
			function setState( box, closed ) {
				box.classList.toggle( 'closed', closed );
				var btn = box.querySelector( '.handlediv' );
				if ( btn ) {
					btn.setAttribute( 'aria-expanded', closed ? 'false' : 'true' );
				}
			}

			document.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.bdsm-collapse-all' ) ) {
					document.querySelectorAll( '.bdsm-collapsible' ).forEach( function ( b ) { setState( b, true ); } );
					return;
				}
				if ( e.target.closest( '.bdsm-expand-all' ) ) {
					document.querySelectorAll( '.bdsm-collapsible' ).forEach( function ( b ) { setState( b, false ); } );
					return;
				}

				var hdr = e.target.closest( '.bdsm-collapse-header' );
				if ( ! hdr ) {
					return;
				}
				// Don't toggle when interacting with the enable checkbox / its label / a link.
				if ( e.target.closest( 'input, a, label' ) ) {
					return;
				}
				var box = hdr.closest( '.bdsm-collapsible' );
				if ( box ) {
					setState( box, ! box.classList.contains( 'closed' ) );
				}
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * AJAX: send a test email with sample tag values.
	 */
	public function ajax_send_test() {
		check_ajax_referer( 'bdsm_send_test' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'bd-subscription-mailer' ) ) );
		}

		$to      = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'bd-subscription-mailer' ) ) );
		}
		if ( '' === trim( $body ) ) {
			wp_send_json_error( array( 'message' => __( 'The email body is empty.', 'bd-subscription-mailer' ) ) );
		}

		$tags = array(
			'customer_first_name' => 'Jane',
			'customer_email'      => 'customer@example.com',
			'subscription_id'     => '12345',
			'product_name'        => __( 'Example Subscription Product', 'bd-subscription-mailer' ),
			'support_link'        => bdsm_support_link() ? bdsm_support_link() : 'https://example.com/support',
			'site_name'           => get_bloginfo( 'name' ),
			'order_total'         => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( 49 ) ) : '$49.00',
			'payment_update_link' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
			'customer_domain'     => 'example.com',
			'days_overdue'        => '8',
			'card_expiry_month'   => '09',
			'card_expiry_year'    => '2027',
		);

		$sent = BDSM_Mailer::send( $to, '[TEST] ' . $subject, $body, $tags );

		if ( $sent ) {
			wp_send_json_success(
				array(
					/* translators: %s: recipient email address. */
					'message' => sprintf( __( 'Test sent to %s.', 'bd-subscription-mailer' ), $to ),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'wp_mail() failed — check your SMTP configuration.', 'bd-subscription-mailer' ) ) );
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
