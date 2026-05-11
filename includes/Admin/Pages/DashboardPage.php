<?php
/**
 * Page Tableau de bord Givasso.
 *
 * Affiche :
 * - 3 cartes de stats (total collecté, nb dons, nb donateurs)
 * - Tableau des 10 derniers dons
 *
 * @package Givasso\Admin\Pages
 */

namespace Givasso\Admin\Pages;

use Givasso\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DashboardPage {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givasso' ) );
        }

        $stats          = $this->get_stats();
        $recent_donations = $this->get_recent_donations();
        ?>
        <div class="wrap givasso-dashboard">

            <h1><?php esc_html_e( 'Givasso — Tableau de bord', 'givasso' ); ?></h1>

            <?php if ( ! Settings::is_configured() ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'Stripe n\'est pas encore configuré.', 'givasso' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=givasso-settings' ) ); ?>">
                            <?php esc_html_e( 'Configurer maintenant →', 'givasso' ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- ── Cartes de stats ─────────────────────────────────────── -->
            <div class="givasso-stats">

                <div class="givasso-stat-card">
                    <span class="givasso-stat-card__icon">💰</span>
                    <span class="givasso-stat-card__value">
                        <?php echo esc_html( number_format( $stats['total_amount'], 2, ',', ' ' ) . ' €' ); ?>
                    </span>
                    <span class="givasso-stat-card__label">
                        <?php esc_html_e( 'Total collecté', 'givasso' ); ?>
                    </span>
                </div>

                <div class="givasso-stat-card">
                    <span class="givasso-stat-card__icon">🎁</span>
                    <span class="givasso-stat-card__value">
                        <?php echo esc_html( number_format( $stats['total_donations'] ) ); ?>
                    </span>
                    <span class="givasso-stat-card__label">
                        <?php esc_html_e( 'Dons complétés', 'givasso' ); ?>
                    </span>
                </div>

                <div class="givasso-stat-card">
                    <span class="givasso-stat-card__icon">👥</span>
                    <span class="givasso-stat-card__value">
                        <?php echo esc_html( number_format( $stats['total_donors'] ) ); ?>
                    </span>
                    <span class="givasso-stat-card__label">
                        <?php esc_html_e( 'Donateurs', 'givasso' ); ?>
                    </span>
                </div>

            </div>

            <!-- ── Derniers dons ───────────────────────────────────────── -->
            <h2><?php esc_html_e( 'Derniers dons', 'givasso' ); ?></h2>

            <?php if ( empty( $recent_donations ) ) : ?>
                <p><?php esc_html_e( 'Aucun don enregistré pour l\'instant.', 'givasso' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped givasso-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Donateur', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Montant', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Campagne', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Statut', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'givasso' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent_donations as $donation ) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( $donation->first_name . ' ' . $donation->last_name ); ?>
                                </td>
                                <td><?php echo esc_html( $donation->email ); ?></td>
                                <td>
                                    <strong>
                                        <?php echo esc_html( number_format( $donation->amount, 2, ',', ' ' ) . ' ' . $donation->currency ); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo $donation->donor_message ? esc_html( $donation->donor_message ) : '—'; ?>
                                </td>
                                <td>
                                    <span class="givasso-badge givasso-badge--<?php echo esc_attr( $donation->status ); ?>">
                                        <?php echo esc_html( $this->format_status( $donation->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $donation->created_at ) ) ); ?>
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

    private function get_stats(): array {
        global $wpdb;

        $donations_table = $wpdb->prefix . 'givasso_donations';
        $donors_table    = $wpdb->prefix . 'givasso_donors';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix (trusted)
        $total_amount = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COALESCE( SUM(amount), 0 ) FROM {$donations_table} WHERE status = 'completed'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        );

        $total_donations = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$donations_table} WHERE status = 'completed'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        );

        $total_donors = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$donors_table}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return compact( 'total_amount', 'total_donations', 'total_donors' );
    }

    private function get_recent_donations( int $limit = 10 ): array {
        global $wpdb;

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT d.amount, d.currency, d.status, d.donor_message, d.created_at,
                        dn.first_name, dn.last_name, dn.email
                 FROM {$wpdb->prefix}givasso_donations d
                 JOIN {$wpdb->prefix}givasso_donors dn ON d.donor_id = dn.id
                 ORDER BY d.created_at DESC
                 LIMIT %d",
                $limit
            )
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function format_status( string $status ): string {
        $labels = [
            'completed' => __( 'Complété', 'givasso' ),
            'pending'   => __( 'En attente', 'givasso' ),
            'failed'    => __( 'Échoué', 'givasso' ),
            'refunded'  => __( 'Remboursé', 'givasso' ),
            'cancelled' => __( 'Annulé', 'givasso' ),
        ];

        return $labels[ $status ] ?? $status;
    }
}
