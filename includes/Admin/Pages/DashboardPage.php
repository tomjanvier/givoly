<?php
/**
 * Page Tableau de bord Givoly.
 *
 * Affiche :
 * - 3 cartes de stats (total collecté, nb dons, nb donateurs)
 * - Tableau des 10 derniers dons
 *
 * @package Givoly\Admin\Pages
 */

namespace Givoly\Admin\Pages;

use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DashboardPage {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $stats          = $this->get_stats();
        $recent_donations = $this->get_recent_donations();
        ?>
        <div class="wrap givoly-dashboard">

            <h1><?php esc_html_e( 'Givoly — Tableau de bord', 'givoly' ); ?></h1>

            <?php if ( ! Settings::is_configured() ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'Stripe n\'est pas encore configuré.', 'givoly' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-settings' ) ); ?>">
                            <?php esc_html_e( 'Configurer maintenant →', 'givoly' ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- ── Cartes de stats ─────────────────────────────────────── -->
            <div class="givoly-stats">

                <div class="givoly-stat-card">
                    <span class="givoly-stat-card__icon">💰</span>
                    <span class="givoly-stat-card__value">
                        <?php echo esc_html( number_format( $stats['total_amount'], 2, ',', ' ' ) . ' €' ); ?>
                    </span>
                    <span class="givoly-stat-card__label">
                        <?php esc_html_e( 'Total collecté', 'givoly' ); ?>
                    </span>
                </div>

                <div class="givoly-stat-card">
                    <span class="givoly-stat-card__icon">🎁</span>
                    <span class="givoly-stat-card__value">
                        <?php echo esc_html( number_format( $stats['total_donations'] ) ); ?>
                    </span>
                    <span class="givoly-stat-card__label">
                        <?php esc_html_e( 'Dons complétés', 'givoly' ); ?>
                    </span>
                </div>

                <div class="givoly-stat-card">
                    <span class="givoly-stat-card__icon">👥</span>
                    <span class="givoly-stat-card__value">
                        <?php echo esc_html( number_format( $stats['total_donors'] ) ); ?>
                    </span>
                    <span class="givoly-stat-card__label">
                        <?php esc_html_e( 'Donateurs', 'givoly' ); ?>
                    </span>
                </div>

            </div>

            <!-- ── Derniers dons ───────────────────────────────────────── -->
            <h2><?php esc_html_e( 'Derniers dons', 'givoly' ); ?></h2>

            <?php if ( empty( $recent_donations ) ) : ?>
                <p><?php esc_html_e( 'Aucun don enregistré pour l\'instant.', 'givoly' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped givoly-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Donateur', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Montant', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Campagne', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Statut', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'givoly' ); ?></th>
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
                                    <span class="givoly-badge givoly-badge--<?php echo esc_attr( $donation->status ); ?>">
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

        $donations_table = $wpdb->prefix . 'givoly_donations';
        $donors_table    = $wpdb->prefix . 'givoly_donors';

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
                 FROM {$wpdb->prefix}givoly_donations d
                 JOIN {$wpdb->prefix}givoly_donors dn ON d.donor_id = dn.id
                 ORDER BY d.created_at DESC
                 LIMIT %d",
                $limit
            )
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function format_status( string $status ): string {
        $labels = [
            'completed' => __( 'Complété', 'givoly' ),
            'pending'   => __( 'En attente', 'givoly' ),
            'failed'    => __( 'Échoué', 'givoly' ),
            'refunded'  => __( 'Remboursé', 'givoly' ),
            'cancelled' => __( 'Annulé', 'givoly' ),
        ];

        return $labels[ $status ] ?? $status;
    }
}
