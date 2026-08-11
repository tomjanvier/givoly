<?php
/**
 * Formulaire de saisie des dons manuels.
 *
 * @package Givoly\Admin\Pages
 */

namespace Givoly\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ManualDonationPage {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'givoly' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Add a manual donation', 'givoly' ); ?></h1>
            <?php if ( isset( $_GET['givoly_manual_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The manual donation was saved. Emails have been queued.', 'givoly' ); ?></p></div>
            <?php elseif ( isset( $_GET['givoly_manual_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The manual donation could not be saved. Check the fields.', 'givoly' ); ?></p></div>
            <?php endif; ?>
            <p><?php esc_html_e( 'Add a donation received by bank transfer, cheque, or cash. The donation will be recorded as completed.', 'givoly' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="givoly-manual-donation-form" style="max-width:720px;background:#fff;padding:24px;border:1px solid #dcdcde;">
                <?php wp_nonce_field( 'givoly_add_manual_donation', 'givoly_manual_nonce' ); ?>
                <input type="hidden" name="action" value="givoly_add_manual_donation">
                <table class="form-table" role="presentation">
                    <tr><th><label for="givoly-manual-first-name"><?php esc_html_e( 'First name', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-manual-first-name" name="first_name" type="text" required></td></tr>
                    <tr><th><label for="givoly-manual-last-name"><?php esc_html_e( 'Name', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-manual-last-name" name="last_name" type="text" required></td></tr>
                    <tr><th><label for="givoly-manual-email"><?php esc_html_e( 'Email', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-manual-email" name="email" type="email" required></td></tr>
                    <tr><th><label for="givoly-manual-amount"><?php esc_html_e( 'Amount (€)', 'givoly' ); ?></label></th><td><input class="small-text" id="givoly-manual-amount" name="amount" type="text" inputmode="decimal" required placeholder="50,00"></td></tr>
                    <tr><th><label for="givoly-manual-date"><?php esc_html_e( 'Donation date', 'givoly' ); ?></label></th><td><input id="givoly-manual-date" name="donation_date" type="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></td></tr>
                    <tr><th><label for="givoly-manual-method"><?php esc_html_e( 'Payment method', 'givoly' ); ?></label></th><td><select id="givoly-manual-method" name="payment_method"><option value="virement"><?php esc_html_e( 'Bank transfer', 'givoly' ); ?></option><option value="cheque"><?php esc_html_e( 'Cheque', 'givoly' ); ?></option><option value="especes"><?php esc_html_e( 'Cash', 'givoly' ); ?></option></select></td></tr>
                    <tr><th><?php esc_html_e( 'Tax receipt', 'givoly' ); ?></th><td><label><input name="send_receipt" type="checkbox" value="1"> <?php esc_html_e( 'Queue the tax receipt for delivery immediately', 'givoly' ); ?></label></td></tr>
                </table>
                <?php submit_button( __( 'Save manual donation', 'givoly' ) ); ?>
            </form>
        </div>
        <?php
    }
}
