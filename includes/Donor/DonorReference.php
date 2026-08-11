<?php
/**
 * Génération des références publiques des donateurs.
 *
 * @package Givoly\Donor
 */

namespace Givoly\Donor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonorReference {

    /**
     * Génère une référence persistante et unique, par exemple PA2026-16-4837.
     *
     * Le deuxième bloc correspond à la position alphabétique de la première
     * lettre du prénom et le dernier bloc est aléatoire.
     */
    public static function generate( string $first_name, string $created_at = '' ): string {
        global $wpdb;

        $year = (int) gmdate( 'Y', strtotime( $created_at ?: 'now' ) );
        if ( $year < 2000 || $year > 2100 ) {
            $year = (int) gmdate( 'Y' );
        }

        $initial = strtoupper( substr( remove_accents( trim( $first_name ) ), 0, 1 ) );
        if ( ! preg_match( '/^[A-Z]$/', $initial ) ) {
            $initial = 'X';
        }
        $alphabet_position = ord( $initial ) - ord( 'A' ) + 1;
        $table             = esc_sql( $wpdb->prefix . 'givoly_donors' );

        for ( $attempt = 0; $attempt < 20; $attempt++ ) {
            $reference = sprintf( 'PA%d-%02d-%04d', $year, $alphabet_position, wp_rand( 1000, 9999 ) );
            $exists    = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE donor_reference = %s LIMIT 1",
                    $reference
                )
            );

            if ( ! $exists ) {
                return $reference;
            }
        }

        return sprintf( 'PA%d-%02d-%s', $year, $alphabet_position, strtoupper( wp_generate_password( 6, false, false ) ) );
    }
}
