<?php
/**
 * Export / Import tab.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

$bdsm_export_string = BDSM_Admin::build_export_string();
?>

<?php if ( isset( $_GET['bdsm_imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
	<div class="notice notice-success is-dismissible"><p>
		<?php
		printf(
			/* translators: %s: comma-separated list of imported sections. */
			esc_html__( 'Imported: %s.', 'bd-subscription-mailer' ),
			esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['bdsm_imported'] ) ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		?>
	</p></div>
<?php endif; ?>

<?php if ( isset( $_GET['bdsm_import_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
	<div class="notice notice-error is-dismissible"><p>
		<?php echo esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['bdsm_import_error'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	</p></div>
<?php endif; ?>

<p class="description" style="margin-top:12px;max-width:760px;">
	<?php esc_html_e( 'Copy the Failed Payment and Card Expiry email content (and optionally the support link + CC addresses) between sites. Task Reminder content is not included because it is tied to each site\'s own product IDs.', 'bd-subscription-mailer' ); ?>
</p>

<div class="postbox" style="margin-top:16px;max-width:760px;">
	<div class="postbox-header" style="padding:10px 12px;"><strong><?php esc_html_e( 'Export', 'bd-subscription-mailer' ); ?></strong></div>
	<div class="inside">
		<p><?php esc_html_e( 'Copy this string and paste it into the Import box on the other site.', 'bd-subscription-mailer' ); ?></p>
		<textarea id="bdsm-export-string" class="large-text code" rows="5" readonly onclick="this.select();"><?php echo esc_textarea( $bdsm_export_string ); ?></textarea>
		<p>
			<button type="button" class="button" onclick="var t=document.getElementById('bdsm-export-string');t.select();document.execCommand('copy');this.textContent=<?php echo esc_js( wp_json_encode( __( 'Copied!', 'bd-subscription-mailer' ) ) ); ?>;">
				<?php esc_html_e( 'Copy to clipboard', 'bd-subscription-mailer' ); ?>
			</button>
		</p>
	</div>
</div>

<div class="postbox" style="margin-top:16px;max-width:760px;">
	<div class="postbox-header" style="padding:10px 12px;"><strong><?php esc_html_e( 'Import', 'bd-subscription-mailer' ); ?></strong></div>
	<div class="inside">
		<form method="post">
			<?php wp_nonce_field( 'bdsm_import_data' ); ?>
			<input type="hidden" name="bdsm_action" value="import_data">

			<p><?php esc_html_e( 'Paste an export string from another site:', 'bd-subscription-mailer' ); ?></p>
			<textarea name="bdsm_import_string" class="large-text code" rows="5" required></textarea>

			<p style="margin-top:12px;"><strong><?php esc_html_e( 'Apply which sections?', 'bd-subscription-mailer' ); ?></strong></p>
			<p>
				<label style="display:block;margin-bottom:4px;">
					<input type="checkbox" name="bdsm_import_sections[]" value="failed_payment" checked>
					<?php esc_html_e( 'Failed Payment emails (overwrites all 6 messages)', 'bd-subscription-mailer' ); ?>
				</label>
				<label style="display:block;margin-bottom:4px;">
					<input type="checkbox" name="bdsm_import_sections[]" value="card_expiry" checked>
					<?php esc_html_e( 'Card Expiry emails (overwrites all 3 messages)', 'bd-subscription-mailer' ); ?>
				</label>
				<label style="display:block;margin-bottom:4px;">
					<input type="checkbox" name="bdsm_import_sections[]" value="settings">
					<?php esc_html_e( 'Settings: support link + CC addresses (leave off to keep this site\'s own CCs)', 'bd-subscription-mailer' ); ?>
				</label>
			</p>

			<p class="description"><?php esc_html_e( 'Importing overwrites the selected sections on this site. The plugin enable / Task Reminder toggles are never imported.', 'bd-subscription-mailer' ); ?></p>

			<?php
			submit_button(
				__( 'Import', 'bd-subscription-mailer' ),
				'primary',
				'submit',
				true,
				array( 'onclick' => "return confirm('" . esc_js( __( 'Overwrite the selected sections on this site?', 'bd-subscription-mailer' ) ) . "');" )
			);
			?>
		</form>
	</div>
</div>
