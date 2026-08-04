<?php
/**
 * Requêtes de synthèse utilisées par les tableaux de bord Givoly.
 *
 * @package Givoly\Admin
 */

namespace Givoly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonationStats {

    /**
     * Retourne les indicateurs principaux des dons complétés.
     *
     * @return array{total_amount: float, total_donations: int, total_donors: int, average_amount: float}
     */
    public static function summary(): array {
        global $wpdb;

        $table = esc_sql( $wpdb->prefix . 'givoly_donations' );
        // Les identifiants de tables ne peuvent pas être des placeholders.
        // Ils proviennent du préfixe WordPress et sont échappés avant interpolation.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COALESCE( SUM(amount), 0 ) AS total_amount,
                    COUNT(*) AS total_donations,
                    COUNT(DISTINCT donor_id) AS total_donors
             FROM {$table}
             WHERE status = 'completed'"
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        $total_amount    = (float) ( $row->total_amount ?? 0 );
        $total_donations = (int) ( $row->total_donations ?? 0 );
        $total_donors    = (int) ( $row->total_donors ?? 0 );

        return [
            'total_amount'    => $total_amount,
            'total_donations' => $total_donations,
            'total_donors'    => $total_donors,
            'average_amount' => $total_donations > 0 ? $total_amount / $total_donations : 0.0,
        ];
    }

    /**
     * Retourne les six derniers mois, y compris ceux sans don.
     *
     * @return array<int, array{key: string, label: string, total: float, count: int}>
     */
    public static function monthly_totals(): array {
        global $wpdb;

        $timezone     = wp_timezone();
        $current_month = new \DateTimeImmutable( 'first day of this month 00:00:00', $timezone );
        $start_month   = $current_month->modify( '-5 months' );
        $start_date    = $start_month->format( 'Y-m-d H:i:s' );
        $table         = esc_sql( $wpdb->prefix . 'givoly_donations' );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month,
                        COALESCE( SUM(amount), 0 ) AS total,
                        COUNT(*) AS donation_count
                 FROM {$table}
                 WHERE status = 'completed' AND created_at >= %s
                 GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
                 ORDER BY month ASC",
                $start_date
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        $by_month = [];
        foreach ( (array) $rows as $row ) {
            $by_month[ (string) $row->month ] = [
                'total' => (float) $row->total,
                'count' => (int) $row->donation_count,
            ];
        }

        $months = [];
        for ( $offset = 0; $offset < 6; $offset++ ) {
            $month = $start_month->modify( '+' . $offset . ' months' );
            $key   = $month->format( 'Y-m' );
            $value = $by_month[ $key ] ?? [ 'total' => 0.0, 'count' => 0 ];

            $months[] = [
                'key'   => $key,
                'label' => wp_date( 'M', $month->getTimestamp() ),
                'total' => $value['total'],
                'count' => $value['count'],
            ];
        }

        return $months;
    }

    /**
     * Derniers dons complétés, avec le nom de campagne quand il existe.
     *
     * @return array<int, object>
     */
    public static function recent_donations( int $limit = 8 ): array {
        global $wpdb;

        $donations_table = esc_sql( $wpdb->prefix . 'givoly_donations' );
        $donors_table    = esc_sql( $wpdb->prefix . 'givoly_donors' );
        $campaigns_table = esc_sql( $wpdb->prefix . 'givoly_campaigns' );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT d.id, d.amount, d.currency, d.status, d.donor_message, d.donor_notes, d.created_at,
                        dn.first_name, dn.last_name, dn.email,
                        c.title AS campaign_title
                 FROM {$donations_table} d
                 INNER JOIN {$donors_table} dn ON d.donor_id = dn.id
                 LEFT JOIN {$campaigns_table} c ON d.campaign_id = c.id
                 WHERE d.status = 'completed'
                 ORDER BY d.created_at DESC
                 LIMIT %d",
                max( 1, min( 50, $limit ) )
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
}
