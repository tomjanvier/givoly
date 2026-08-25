<?php
/**
 * Conformité RGPD : export et effacement des données personnelles.
 *
 * S'intègre aux outils natifs de WordPress :
 *   Réglages > Confidentialité > Exporter / Effacer les données personnelles.
 *
 * L'exporteur restitue la fiche donateur et l'historique complet des dons.
 * L'effaceur anonymise la fiche donateur (référence, compte WordPress, noms,
 * coordonnées, identifiants Stripe, jetons), purge les sessions et la file
 * d'emails correspondante, puis retire le message libre associé à chaque don.
 * Les montants sont conservés : une association a l'obligation légale de
 * garder la trace de ses recettes (comptabilité, justificatifs fiscaux).
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Privacy {

    private const EXPORT_PAGE_SIZE = 100;

    public function register(): void {
        add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
    }

    public function register_exporter( array $exporters ): array {
        $exporters['givoly-donor'] = [
            'exporter_friendly_name' => __( 'Givoly — Donor profile and donations', 'givoly' ),
            'callback'               => [ $this, 'export_donor_data' ],
        ];

        return $exporters;
    }

    public function register_eraser( array $erasers ): array {
        $erasers['givoly-donor'] = [
            'eraser_friendly_name' => __( 'Givoly — Donor data eraser', 'givoly' ),
            'callback'             => [ $this, 'erase_donor_data' ],
        ];

        return $erasers;
    }

    // ── Export ─────────────────────────────────────────────────────────────

    /**
     * @param string $email_address
     * @param int    $page Numéro de page demandé par WordPress.
     * @return array{data: list<array{group_id:string, group_label:string, items:array<int,array<string,mixed>>}>, done:bool}
     */
    public function export_donor_data( string $email_address, int $page = 1 ): array {
        global $wpdb;

        $email = sanitize_email( $email_address );
        if ( ! is_email( $email ) ) {
            return [ 'data' => [], 'done' => true ];
        }

        $donors = $this->get_donors_by_email( $email );
        if ( ! $donors ) {
            return [ 'data' => [], 'done' => true ];
        }

        $page          = max( 1, $page );
        $offset        = ( $page - 1 ) * self::EXPORT_PAGE_SIZE;
        $profile_items = [];
        $donation_items = [];
        $donations_read = 0;

        foreach ( $donors as $donor ) {
            if ( 1 === $page ) {
                foreach ( $this->donor_export_fields( $donor ) as $key => $value ) {
                    if ( $value !== '' && $value !== null ) {
                        $profile_items[] = [
                            'name'  => $key,
                            'value' => $value,
                        ];
                    }
                }
            }

            $table_d = esc_sql( $wpdb->prefix . 'givoly_donations' );
            $rows    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT amount, currency, status, gateway, donor_message AS campaign_label, created_at
                     FROM {$table_d} WHERE donor_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                    (int) $donor->id,
                    self::EXPORT_PAGE_SIZE,
                    $offset
                ),
                ARRAY_A
            );

            $donations_read += count( $rows ?: [] );

            foreach ( $rows ?: [] as $index => $row ) {
                foreach ( $this->donation_export_fields( $row ) as $key => $value ) {
                    $donation_items[] = [
                        // translators: %d correspond à la position du don dans l'historique exporté.
                        'name'  => sprintf( __( 'Donation %1$d — %2$s', 'givoly' ), $offset + $index + 1, $key ),
                        'value' => $value,
                    ];
                }
            }
        }

        $data = [];

        if ( $profile_items ) {
            $data[] = [
                'group_id'    => 'givoly-donor-profile',
                'group_label' => __( 'Givoly — Donor profile', 'givoly' ),
                'items'       => $profile_items,
            ];
        }

        if ( $donation_items ) {
            $data[] = [
                'group_id'    => 'givoly-donations',
                'group_label' => __( 'Givoly — Donation history', 'givoly' ),
                'items'       => $donation_items,
            ];
        }

        return [ 'data' => $data, 'done' => $donations_read < self::EXPORT_PAGE_SIZE ];
    }

    /**
     * @return array<string,string>
     */
    private function donor_export_fields( object $donor ): array {
        return [
            __( 'Donor number', 'givoly' )   => (string) ( $donor->donor_reference ?: '#' . $donor->id ),
            __( 'Email address', 'givoly' )  => (string) $donor->email,
            __( 'First name', 'givoly' )     => (string) $donor->first_name,
            __( 'Last name', 'givoly' )      => (string) $donor->last_name,
            __( 'Company', 'givoly' )        => (string) ( $donor->company ?? '' ),
            __( 'Address', 'givoly' )        => trim( implode( ' ', array_filter( [
                (string) ( $donor->address_line1 ?? '' ),
                (string) ( $donor->address_line2 ?? '' ),
                (string) ( $donor->postal_code ?? '' ),
                (string) ( $donor->city ?? '' ),
                (string) ( $donor->country ?? '' ),
            ] ) ) ),
            __( 'Phone', 'givoly' )          => (string) ( $donor->phone ?? '' ),
            __( 'Registration date', 'givoly' ) => (string) $donor->created_at,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private function donation_export_fields( array $row ): array {
        return [
            __( 'Date', 'givoly' )         => (string) $row['created_at'],
            __( 'Amount', 'givoly' )       => number_format_i18n( (float) $row['amount'], 2 ) . ' ' . strtoupper( (string) $row['currency'] ),
            __( 'Status', 'givoly' )       => Format::status( (string) $row['status'] ),
            __( 'Payment method', 'givoly' ) => ucfirst( str_replace( '_', ' ', (string) $row['gateway'] ) ),
            __( 'Campaign', 'givoly' )     => (string) ( $row['campaign_label'] ?: '' ),
        ];
    }

    // ── Effacement ─────────────────────────────────────────────────────────

    /**
     * Anonymise la fiche donateur et purge les données libres.
     * Les lignes de dons sont conservées (obligations comptables) mais
     * détachées de toute donnée personnelle.
     *
     * @param string $email_address Adresse validée par WordPress.
     * @param int    $page Numéro de page demandé par WordPress.
     * @return array{items_removed:int, items_retained:int, messages:array<int,string>, done:bool}
     */
    public function erase_donor_data( string $email_address, int $page = 1 ): array {
        global $wpdb;

        $response = [
            'items_removed'  => 0,
            'items_retained' => 0,
            'messages'       => [],
            'done'           => true,
        ];

        $email = sanitize_email( $email_address );
        if ( ! is_email( $email ) ) {
            return $response;
        }

        // L'effacement est atomique ; les appels de pagination suivants n'ont
        // plus de fiche correspondant à l'adresse d'origine.
        if ( $page > 1 ) {
            return $response;
        }

        $donors = $this->get_donors_by_email( $email );
        if ( ! $donors ) {
            return $response;
        }

        $table_donors = esc_sql( $wpdb->prefix . 'givoly_donors' );
        $table_dons   = esc_sql( $wpdb->prefix . 'givoly_donations' );

        foreach ( $donors as $donor ) {
            $donor_id = (int) $donor->id;

            $this->delete_donor_sessions( $donor_id );

            // Message libre saisi par le donateur sur chaque don : purgé.
            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "UPDATE {$table_dons} SET donor_notes = NULL, post_payment_token = NULL WHERE donor_id = %d AND ( donor_notes IS NOT NULL OR post_payment_token IS NOT NULL )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $donor_id
                )
            );

            $anonymized = [
                'email'                  => $this->anonymous_email( (string) $donor->email ),
                'donor_reference'        => 'PA-ANON-' . $donor_id,
                'first_name'             => '',
                'last_name'              => '',
                'company'                => null,
                'address_line1'          => null,
                'address_line2'          => null,
                'postal_code'            => null,
                'city'                   => null,
                'phone'                  => null,
                'country'                => '',
                'wp_user_id'             => null,
                'stripe_customer_id'     => null,
                'stripe_subscription_id' => null,
                'magic_token_hash'       => null,
                'magic_token_expires_at' => null,
                'updated_at'             => current_time( 'mysql', true ),
            ];

            $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'givoly_donors',
                $anonymized,
                [ 'id' => $donor_id ],
                array_fill( 0, count( $anonymized ), '%s' ),
                [ '%d' ]
            );

            if ( false !== $updated ) {
                $response['items_removed']++;
            }
        }

        // La file persistante contient le destinataire et une copie JSON des
        // noms et de l'adresse. Ces données opérationnelles n'ont pas à être
        // conservées après une demande d'effacement validée.
        $table_mail = esc_sql( $wpdb->prefix . 'givoly_email_jobs' );
        $mail_rows  = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE FROM {$table_mail} WHERE LOWER( recipient ) = LOWER( %s ) OR payload LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $email,
                '%' . $wpdb->esc_like( $email ) . '%'
            )
        );

        if ( is_int( $mail_rows ) && $mail_rows > 0 ) {
            $response['items_removed'] += $mail_rows;
        }

        $this->delete_checkout_profiles( $email );

        $completed = 0;
        foreach ( $donors as $donor ) {
            $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_dons} WHERE donor_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                    (int) $donor->id
                )
            );
            $completed += $count;
        }

        if ( $completed > 0 ) {
            $response['items_retained'] = $completed;
            $response['messages'][]     = __(
                'Donation amounts were kept for accounting and tax obligations. All personal information attached to them has been removed.',
                'givoly'
            );
        }

        return $response;
    }

    /**
     * Invalide les sessions actives pour empêcher l'accès à la fiche anonymisée.
     */
    private function delete_donor_sessions( int $donor_id ): void {
        global $wpdb;

        $prefix = $wpdb->esc_like( '_transient_givoly_donor_session_' ) . '%';
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $prefix
            ),
            ARRAY_A
        );

        foreach ( $rows ?: [] as $row ) {
            $session = maybe_unserialize( $row['option_value'] ?? '' );
            if ( is_array( $session ) && (int) ( $session['donor_id'] ?? 0 ) === $donor_id ) {
                $key = substr( (string) $row['option_name'], strlen( '_transient_' ) );
                delete_transient( $key );
            }
        }
    }

    /**
     * Supprime les profils de paiement encore temporaires qui contiennent l'adresse.
     */
    private function delete_checkout_profiles( string $email ): void {
        global $wpdb;

        $rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->esc_like( '_transient_givoly_checkout_profile_' ) . '%',
                '%' . $wpdb->esc_like( $email ) . '%'
            )
        );

        foreach ( $rows ?: [] as $option_name ) {
            $key = substr( (string) $option_name, strlen( '_transient_' ) );
            delete_transient( $key );
        }
    }

    /**
     * Adresse e-mail de remplacement, déterministe et unique (contrainte UNIQUE).
     * Le hachage salé rend impossible la re-identification par l'e-mail d'origine.
     */
    private function anonymous_email( string $original_email ): string {
        return 'anonymized+' . substr( hash_hmac( 'sha256', strtolower( $original_email ), wp_salt( 'auth' ) ), 0, 24 ) . '@example.invalid';
    }

    /**
     * @return array<int,object>
     */
    private function get_donors_by_email( string $email ): array {
        global $wpdb;

        $table = esc_sql( $wpdb->prefix . 'givoly_donors' );

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id, email, first_name, last_name, company, address_line1, address_line2,
                        postal_code, city, country, phone, stripe_customer_id, stripe_subscription_id,
                        magic_token_hash, magic_token_expires_at, donor_reference, created_at
                 FROM {$table} WHERE LOWER( email ) = LOWER( %s )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $email
            )
        );

        return is_array( $rows ) ? $rows : [];
    }
}
