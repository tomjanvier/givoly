<?php
/**
 * Page liste des donateurs.
 *
 * Tableau : nom, email, total donné (dons complétés), nb de dons, dernier don.
 *
 * @package Givoly\Admin\Pages
 */

namespace Givoly\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonorsPage {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $donors       = $this->get_donors();
        $default_year = (int) gmdate( 'Y' ) - 1;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Givoly — Donateurs', 'givoly' ); ?></h1>

            <?php if ( isset( $_GET['givoly_tax_receipts_sent'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <?php
                $sent = absint( wp_unslash( $_GET['givoly_tax_receipts_sent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $year = absint( wp_unslash( $_GET['givoly_tax_receipts_year'] ?? $default_year ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php
                    printf(
                        esc_html( _n( '%1$d email de reçu fiscal envoyé pour %2$d.', '%1$d emails de reçus fiscaux envoyés pour %2$d.', $sent, 'givoly' ) ),
                        esc_html( (string) $sent ),
                        esc_html( (string) $year )
                    );
                    ?>
                </p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['givoly_tax_receipts_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Impossible d’envoyer les reçus fiscaux : année invalide.', 'givoly' ); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width: 760px;">
                <h2><?php esc_html_e( 'Envoi annuel des reçus fiscaux', 'givoly' ); ?></h2>
                <p><?php esc_html_e( 'Envoyez facilement un récapitulatif fiscal par email à tous les donateurs ayant au moins un don complété sur l’année choisie.', 'givoly' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Confirmer l’envoi des emails de reçus fiscaux pour cette année ?', 'givoly' ); ?>')">
                    <?php wp_nonce_field( 'givoly_send_yearly_tax_receipts' ); ?>
                    <input type="hidden" name="action" value="givoly_send_yearly_tax_receipts">
                    <label for="givoly-receipt-year"><strong><?php esc_html_e( 'Année fiscale', 'givoly' ); ?></strong></label>
                    <input type="number" id="givoly-receipt-year" name="receipt_year" min="2000" max="<?php echo esc_attr( (string) gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( (string) $default_year ); ?>" class="small-text">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Envoyer les reçus par email', 'givoly' ); ?></button>
                    <p class="description"><?php esc_html_e( 'Astuce : renseignez les informations de l’association dans Givoly > Réglages > Association avant l’envoi.', 'givoly' ); ?></p>
                </form>
            </div>

            <?php if ( empty( $donors ) ) : ?>
                <p><?php esc_html_e( 'Aucun donateur enregistré pour l\'instant.', 'givoly' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped givoly-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Donateur', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Total donné', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Nb de dons', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Dernier don', 'givoly' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $donors as $donor ) : ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php
                                        $name = trim( $donor->first_name . ' ' . $donor->last_name );
                                        echo esc_html( $name ?: '—' );
                                        ?>
                                    </strong>
                                    <?php if ( ! empty( $donor->company ) ) : ?>
                                        <br><small><?php echo esc_html( $donor->company ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $donor->email ); ?></td>
                                <td>
                                    <strong>
                                        <?php echo esc_html( number_format( (float) $donor->total_donated, 2, ',', ' ' ) . ' €' ); ?>
                                    </strong>
                                </td>
                                <td><?php echo esc_html( $donor->donation_count ); ?></td>
                                <td>
                                    <?php
                                    echo $donor->last_donation
                                        ? esc_html( date_i18n( 'd/m/Y', strtotime( $donor->last_donation ) ) )
                                        : '—';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Requêtes DB ────────────────────────────────────────────────────────

    private function get_donors(): array {
        global $wpdb;

        $table_dn = $wpdb->prefix . 'givoly_donors';
        $table_d  = $wpdb->prefix . 'givoly_donations';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix (trusted)
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT dn.id, dn.first_name, dn.last_name, dn.email, dn.company, COALESCE( SUM( CASE WHEN d.status = 'completed' THEN d.amount ELSE 0 END ), 0 ) AS total_donated, COUNT( CASE WHEN d.status = 'completed' THEN 1 END ) AS donation_count, MAX( CASE WHEN d.status = 'completed' THEN d.created_at END ) AS last_donation FROM {$table_dn} dn LEFT JOIN {$table_d} d ON d.donor_id = dn.id GROUP BY dn.id ORDER BY total_donated DESC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
}
