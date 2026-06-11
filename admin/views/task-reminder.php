<?php
/**
 * Task Reminder tab (Feature 1).
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_config   = (array) get_option( 'bdsm_task_reminder', array() );
$bdsm_products = wc_get_products(
	array(
		'type'    => array( 'subscription', 'variable-subscription' ),
		'limit'   => -1,
		'status'  => 'publish',
		'orderby' => 'title',
		'order'   => 'ASC',
	)
);
?>

<?php if ( ! bdsm_feature1_enabled() ) : ?>
	<div class="notice notice-info inline"><p>
		<?php esc_html_e( 'The Task Reminder feature is currently switched off in Settings. You can still edit content below — nothing will send until it is enabled.', 'bd-subscription-mailer' ); ?>
	</p></div>
<?php endif; ?>

<p class="description" style="margin-top:12px;">
	<?php esc_html_e( 'Available template tags:', 'bd-subscription-mailer' ); ?>
	<code>{customer_first_name}</code> <code>{subscription_id}</code> <code>{product_name}</code> <code>{support_link}</code> <code>{site_name}</code>
</p>

<?php if ( empty( $bdsm_products ) ) : ?>
	<p><?php esc_html_e( 'No published subscription products found.', 'bd-subscription-mailer' ); ?></p>
<?php else : ?>

<style>
	.bdsm-tr-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
		align-items: start;
	}
	.bdsm-tr-grid .postbox {
		margin-top: 0;
		min-width: 0;
	}
	@media ( max-width: 1280px ) {
		.bdsm-tr-grid {
			grid-template-columns: 1fr;
		}
	}
</style>

<form method="post">
	<?php wp_nonce_field( 'bdsm_save_task_reminder' ); ?>
	<input type="hidden" name="bdsm_action" value="save_task_reminder">

	<div class="bdsm-tr-grid" style="margin-top:16px;">
	<?php
	foreach ( $bdsm_products as $bdsm_product ) :
		$bdsm_pid   = $bdsm_product->get_id();
		$bdsm_entry = wp_parse_args(
			$bdsm_config[ $bdsm_pid ] ?? array(),
			array(
				'enabled' => 0,
				'subject' => '',
				'body'    => '',
			)
		);
		?>
		<div class="postbox">
			<div class="postbox-header" style="padding:10px 12px;">
				<label style="font-weight:600;">
					<input type="checkbox" name="bdsm_tr_enabled[]" value="<?php echo esc_attr( $bdsm_pid ); ?>" <?php checked( 1, (int) $bdsm_entry['enabled'] ); ?>>
					<?php echo esc_html( $bdsm_product->get_name() ); ?>
					<span style="color:#888;font-weight:400;">(#<?php echo esc_html( $bdsm_pid ); ?>)</span>
				</label>
			</div>
			<div class="inside">
				<input type="hidden" name="bdsm_tr_product_ids[]" value="<?php echo esc_attr( $bdsm_pid ); ?>">
				<p>
					<label for="bdsm_tr_subject_<?php echo esc_attr( $bdsm_pid ); ?>" style="display:block;font-weight:600;margin-bottom:4px;">
						<?php esc_html_e( 'Subject', 'bd-subscription-mailer' ); ?>
					</label>
					<input type="text" class="large-text" id="bdsm_tr_subject_<?php echo esc_attr( $bdsm_pid ); ?>"
						name="bdsm_tr_subject[<?php echo esc_attr( $bdsm_pid ); ?>]"
						value="<?php echo esc_attr( $bdsm_entry['subject'] ); ?>">
				</p>
				<?php
				wp_editor(
					$bdsm_entry['body'],
					'bdsm_tr_body_' . $bdsm_pid,
					array(
						'textarea_name' => 'bdsm_tr_body[' . $bdsm_pid . ']',
						'textarea_rows' => 8,
						'media_buttons' => false,
					)
				);
				BDSM_Admin::render_test_controls( 'bdsm_tr_subject_' . $bdsm_pid, 'bdsm_tr_body_' . $bdsm_pid );
				?>
			</div>
		</div>
	<?php endforeach; ?>
	</div>

	<?php submit_button(); ?>
</form>

<?php endif; ?>
