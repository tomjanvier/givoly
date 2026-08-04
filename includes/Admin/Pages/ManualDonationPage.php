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
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ajouter un don manuel', 'givoly' ); ?></h1>
            <?php if ( isset( $_GET['givoly_manual_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Le don manuel a été enregistré. Les emails ont été mis en file.', 'givoly' ); ?></p></div>
            <?php elseif ( isset( $_GET['givoly_manual_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Le don manuel n’a pas pu être enregistré. Vérifiez les champs.', 'givoly' ); ?></p></div>
            <?php endif; ?>
            <p><?php esc_html_e( 'Ajoutez un don reçu par virement, chèque ou espèces. Le don sera comptabilisé comme complété.', 'givoly' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="givoly-manual-donation-form" style="max-width:720px;background:#fff;padding:24px;border:1px solid #dcdcde;">
                <?php wp_nonce_field( 'givoly_add_manual_donation', 'givoly_manual_nonce' ); ?>
                <input type="hidden" name="action" value="givoly_add_manual_donation">
                <table class="form-table" role="presentation">
                    <tr><th><label for="givoly-manual-first-name"><?php esc_html_e( 'Prénom', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-manual-first-name" name="first_name" type="text" required></td></tr>
                    <tr><th><label for="givoly-manual-last-name"><?php esc_html_e( 'Nom', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-manual-last-name" name="last_name" type="text" required></td></tr>
                    <tr><th><label for="givoly-manual-email"><?php esc_html_e( 'Email', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-manual-email" name="email" type="email" required></td></tr>
                    <tr><th><label for="givoly-manual-amount"><?php esc_html_e( 'Montant (€)', 'givoly' ); ?></label></th><td><input class="small-text" id="givoly-manual-amount" name="amount" type="text" inputmode="decimal" required placeholder="50,00"></td></tr>
                    <tr><th><label for="givoly-manual-date"><?php esc_html_e( 'Date du don', 'givoly' ); ?></label></th><td><input id="givoly-manual-date" name="donation_date" type="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></td></tr>
                    <tr><th><label for="givoly-manual-method"><?php esc_html_e( 'Moyen de paiement', 'givoly' ); ?></label></th><td><select id="givoly-manual-method" name="payment_method"><option value="virement"><?php esc_html_e( 'Virement', 'givoly' ); ?></option><option value="cheque"><?php esc_html_e( 'Chèque', 'givoly' ); ?></option><option value="especes"><?php esc_html_e( 'Espèces', 'givoly' ); ?></option></select></td></tr>
                    <tr><th><?php esc_html_e( 'Reçu fiscal', 'givoly' ); ?></th><td><label><input name="send_receipt" type="checkbox" value="1"> <?php esc_html_e( 'Mettre immédiatement le reçu fiscal en file d’envoi', 'givoly' ); ?></label></td></tr>
                </table>
                <?php submit_button( __( 'Enregistrer le don manuel', 'givoly' ) ); ?>
            </form>
        </div>
        <?php
    }
}
