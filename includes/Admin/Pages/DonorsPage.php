<?php
/**
 * Page liste des donateurs.
 *
 * Tableau : nom, email, total donné (dons complétés), nb de dons, dernier don.
 *
 * @package Givasso\Admin\Pages
 */

namespace Givasso\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonorsPage {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givasso' ) );
        }

        $donors = $this->get_donors();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Givasso — Donateurs', 'givasso' ); ?></h1>

            <?php if ( empty( $donors ) ) : ?>
                <p><?php esc_html_e( 'Aucun donateur enregistré pour l\'instant.', 'givasso' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped givasso-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Donateur', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Total donné', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Nb de dons', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Dernier don', 'givasso' ); ?></th>
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

        $table_dn = $wpdb->prefix . 'givasso_donors';
        $table_d  = $wpdb->prefix . 'givasso_donations';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix (trusted)
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT dn.id, dn.first_name, dn.last_name, dn.email, dn.company, COALESCE( SUM( CASE WHEN d.status = 'completed' THEN d.amount ELSE 0 END ), 0 ) AS total_donated, COUNT( CASE WHEN d.status = 'completed' THEN 1 END ) AS donation_count, MAX( CASE WHEN d.status = 'completed' THEN d.created_at END ) AS last_donation FROM {$table_dn} dn LEFT JOIN {$table_d} d ON d.donor_id = dn.id GROUP BY dn.id ORDER BY total_donated DESC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
}
