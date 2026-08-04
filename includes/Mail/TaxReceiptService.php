<?php
/**
 * Sélection et mise en file des reçus fiscaux.
 *
 * @package Givoly\Mail
 */

namespace Givoly\Mail;

use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TaxReceiptService {

    /**
     * @param int[] $donor_ids Vide = tous les bénéficiaires de l'année.
     * @return array{batch_id:string,total:int,queued:int}
     */
    public static function enqueue( int $year, array $donor_ids = [] ): array {
        $recipients = self::get_recipients( $year, $donor_ids );
        $batch_id   = 'tax-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
        $queued     = 0;

        foreach ( $recipients as $recipient ) {
            $payload = [
                'year'                 => $year,
                'email'                => (string) $recipient->email,
                'first_name'           => (string) $recipient->first_name,
                'last_name'            => (string) $recipient->last_name,
                'total_amount'         => (float) $recipient->total_amount,
                'currency'             => (string) $recipient->currency,
                'donation_count'       => (int) $recipient->donation_count,
                'association'          => Settings::get_assoc_name() ?: get_bloginfo( 'name' ),
                'association_address'  => trim( implode( ' ', array_filter( [ Settings::get_assoc_address(), Settings::get_assoc_postal_code(), Settings::get_assoc_city() ] ) ) ),
                'siret'                => Settings::get_assoc_siret(),
                'rna'                  => Settings::get_assoc_rna(),
                'fiscal_id'            => Settings::get_assoc_fiscal_id(),
            ];

            if ( MailQueue::enqueue( 'tax_receipt', $payload, (string) $recipient->email, $batch_id ) ) {
                $queued++;
            }
        }

        return [ 'batch_id' => $batch_id, 'total' => count( $recipients ), 'queued' => $queued ];
    }

    /**
     * @param int[] $donor_ids
     * @return array<int,object>
     */
    public static function get_recipients( int $year, array $donor_ids = [], int $limit = 0, int $offset = 0 ): array {
        global $wpdb;

        $start    = sprintf( '%d-01-01 00:00:00', $year );
        $end      = sprintf( '%d-01-01 00:00:00', $year + 1 );
        $table_dn = esc_sql( $wpdb->prefix . 'givoly_donors' );
        $table_d  = esc_sql( $wpdb->prefix . 'givoly_donations' );
        $where    = '';
        $args     = [ $start, $end ];

        if ( $donor_ids ) {
            $ids          = array_values( array_filter( array_map( 'absint', $donor_ids ) ) );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $where        = " AND dn.id IN ({$placeholders})";
            $args         = array_merge( $args, $ids );
        }

        $pagination = '';
        if ( $limit > 0 ) {
            $pagination = ' LIMIT %d OFFSET %d';
            $args[]     = max( 1, min( 100, $limit ) );
            $args[]     = max( 0, $offset );
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names trusted, placeholders built from integers
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- the optional donor and pagination placeholders are built together with their matching integer arguments.
                "SELECT dn.id, dn.first_name, dn.last_name, dn.email, COALESCE(SUM(d.amount), 0) AS total_amount, d.currency, COUNT(d.id) AS donation_count
                 FROM {$table_dn} dn
                 INNER JOIN {$table_d} d ON d.donor_id = dn.id
                 WHERE d.status = 'completed' AND d.created_at >= %s AND d.created_at < %s AND dn.email <> '' {$where}
                 GROUP BY dn.id, dn.first_name, dn.last_name, dn.email, d.currency
                 ORDER BY dn.last_name ASC, dn.first_name ASC, dn.email ASC{$pagination}",
                ...$args
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return array_map( static fn( array $row ): object => (object) $row, $rows ?: [] );
    }

    public static function count_recipients( int $year ): int {
        global $wpdb;

        $start    = sprintf( '%d-01-01 00:00:00', $year );
        $end      = sprintf( '%d-01-01 00:00:00', $year + 1 );
        $table_dn = esc_sql( $wpdb->prefix . 'givoly_donors' );
        $table_d  = esc_sql( $wpdb->prefix . 'givoly_donations' );

        // Les identifiants de tables ne peuvent pas être des placeholders.
        // Ils proviennent du préfixe WordPress et sont échappés avant interpolation.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT dn.id, d.currency
                    FROM {$table_dn} dn
                    INNER JOIN {$table_d} d ON d.donor_id = dn.id
                    WHERE d.status = 'completed' AND d.created_at >= %s AND d.created_at < %s AND dn.email <> ''
                    GROUP BY dn.id, d.currency
                ) AS recipients",
                $start,
                $end
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
}
