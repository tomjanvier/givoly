<?php
/**
 * Migration non destructive des installations historiques Givasso.
 *
 * Givoly lit les anciennes options en secours, copie les réglages dans les
 * nouvelles clés et rattache les anciens donateurs, campagnes et dons aux
 * tables actuelles. Les tables Givasso ne sont jamais supprimées.
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LegacyMigration {

    private const OPTION = 'givoly_legacy_migration_version';
    private const VERSION = '1';
    private static bool $failed = false;

    public static function run(): void {
        if ( self::VERSION === (string) get_option( self::OPTION, '' ) ) {
            return;
        }

        self::$failed = false;
        self::migrate_options();
        self::migrate_tables();

        if ( self::$failed ) {
            return;
        }

        update_option( self::OPTION, self::VERSION, false );
    }

    private static function migrate_options(): void {
        $options = [
            'givoly_stripe_mode', 'givoly_stripe_pk_test', 'givoly_stripe_sk_test',
            'givoly_stripe_pk_live', 'givoly_stripe_sk_live', 'givoly_stripe_webhook_secret',
            'givoly_success_url', 'givoly_cancel_url', 'givoly_post_payment_show_phone',
            'givoly_post_payment_show_address', 'givoly_assoc_name', 'givoly_assoc_address',
            'givoly_assoc_postal_code', 'givoly_assoc_city', 'givoly_assoc_siret',
            'givoly_assoc_rna', 'givoly_assoc_fiscal_id', 'givoly_assoc_email',
            'givoly_ha_client_id', 'givoly_ha_client_secret', 'givoly_ha_org_slug',
            'givoly_ha_mode', 'givoly_ha_signature_key', 'givoly_ha_button_notice',
            'givoly_ha_other_payments_url', 'givoly_ha_once_use_other_payments_url',
            'givoly_stripe_enabled', 'givoly_helloasso_enabled', 'givoly_default_gateway',
            'givoly_email_logo_url', 'givoly_email_primary_color', 'givoly_email_sender_name',
            'givoly_email_thank_subject', 'givoly_email_thank_body',
            'givoly_email_admin_donation_subject', 'givoly_email_admin_donation_body',
            'givoly_email_tax_receipt_subject', 'givoly_email_tax_receipt_body',
            'givoly_tax_receipt_pdf_enabled', 'givoly_tax_receipt_pdf_title',
            'givoly_tax_receipt_pdf_body', 'givoly_tax_receipt_pdf_footer',
            'givoly_appearance_primary_color', 'givoly_appearance_accent_color',
            'givoly_appearance_radius', 'givoly_appearance_btn_style',
            'givoly_public_branding_enabled', 'givoly_ha_access_token',
            'givoly_ha_refresh_token', 'givoly_ha_expires_at',
        ];

        foreach ( $options as $current ) {
            $legacy = 'givasso_' . substr( $current, strlen( 'givoly_' ) );

            if ( null === get_option( $current, null ) ) {
                $value = get_option( $legacy, null );
                if ( null !== $value ) {
                    update_option( $current, $value, false );
                }
            }
        }
    }

    private static function migrate_tables(): void {
        global $wpdb;

        $legacy_donors    = $wpdb->prefix . 'givasso_donors';
        $legacy_campaigns = $wpdb->prefix . 'givasso_campaigns';
        $legacy_donations = $wpdb->prefix . 'givasso_donations';

        if ( ! self::table_exists( $legacy_donors ) && ! self::table_exists( $legacy_campaigns ) && ! self::table_exists( $legacy_donations ) ) {
            return;
        }

        $donor_map    = self::migrate_donors( $legacy_donors );
        $campaign_map = self::migrate_campaigns( $legacy_campaigns );
        self::migrate_donations( $legacy_donations, $donor_map, $campaign_map );
    }

    /** @return array<int, int> */
    private static function migrate_donors( string $legacy_table ): array {
        global $wpdb;

        $map = [];
        if ( ! self::table_exists( $legacy_table ) ) {
            return $map;
        }

        $current_table = $wpdb->prefix . 'givoly_donors';
        $columns       = self::columns( $current_table );
        $rows          = $wpdb->get_results( "SELECT * FROM `{$legacy_table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- trusted table name from $wpdb->prefix

        foreach ( $rows as $row ) {
            $legacy_id = absint( $row['id'] ?? 0 );
            $email     = sanitize_email( $row['email'] ?? $row['email_address'] ?? '' );
            if ( '' === $email ) {
                continue;
            }

            $current_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$current_table}` WHERE email = %s LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! $current_id ) {
                $data = self::common_data( $row, $columns, [ 'id', 'email' ] );
                $data['email'] = $email;
                $inserted = $wpdb->insert( $current_table, $data, array_fill( 0, count( $data ), '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if ( false === $inserted ) {
                    self::$failed = true;
                }
                $current_id = false === $inserted ? 0 : (int) $wpdb->insert_id;
            }

            if ( $legacy_id && $current_id ) {
                $map[ $legacy_id ] = $current_id;
            }
        }

        return $map;
    }

    /** @return array<int, int> */
    private static function migrate_campaigns( string $legacy_table ): array {
        global $wpdb;

        $map = [];
        if ( ! self::table_exists( $legacy_table ) ) {
            return $map;
        }

        $current_table = $wpdb->prefix . 'givoly_campaigns';
        $columns       = self::columns( $current_table );
        $rows          = $wpdb->get_results( "SELECT * FROM `{$legacy_table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- trusted table name from $wpdb->prefix

        foreach ( $rows as $row ) {
            $legacy_id = absint( $row['id'] ?? 0 );
            $slug      = sanitize_title( $row['slug'] ?? $row['campaign_slug'] ?? '' );
            if ( '' === $slug ) {
                continue;
            }

            $current_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$current_table}` WHERE slug = %s LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! $current_id ) {
                $data = self::common_data( $row, $columns, [ 'id', 'slug' ] );
                $data['slug'] = $slug;
                $inserted = $wpdb->insert( $current_table, $data, array_fill( 0, count( $data ), '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if ( false === $inserted ) {
                    self::$failed = true;
                }
                $current_id = false === $inserted ? 0 : (int) $wpdb->insert_id;
            }

            if ( $legacy_id && $current_id ) {
                $map[ $legacy_id ] = $current_id;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $donor_map
     * @param array<int, int> $campaign_map
     */
    private static function migrate_donations( string $legacy_table, array $donor_map, array $campaign_map ): void {
        global $wpdb;

        if ( ! self::table_exists( $legacy_table ) ) {
            return;
        }

        $current_table = $wpdb->prefix . 'givoly_donations';
        $columns       = self::columns( $current_table );
        $rows          = $wpdb->get_results( "SELECT * FROM `{$legacy_table}` ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- trusted table name from $wpdb->prefix

        foreach ( $rows as $row ) {
            $legacy_id = absint( $row['id'] ?? 0 );
            $donor_id  = $donor_map[ absint( $row['donor_id'] ?? 0 ) ] ?? 0;
            if ( ! $donor_id ) {
                continue;
            }

            $gateway    = sanitize_key( $row['gateway'] ?? 'stripe' ) ?: 'stripe';
            $gateway_id = sanitize_text_field( $row['gateway_transaction_id'] ?? '' );
            if ( '' !== $gateway_id ) {
                $already_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$current_table}` WHERE gateway = %s AND gateway_transaction_id = %s LIMIT 1", $gateway, $gateway_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( $already_exists ) {
                    continue;
                }
            } elseif ( $legacy_id ) {
                // Keeping the old primary key makes a retry idempotent for
                // donations without a gateway transaction reference.
                $already_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$current_table}` WHERE id = %d LIMIT 1", $legacy_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( $already_exists ) {
                    continue;
                }
            }

            $data = self::common_data( $row, $columns, [ 'id', 'donor_id', 'campaign_id', 'gateway' ] );
            $data['donor_id'] = $donor_id;
            $data['campaign_id'] = ! empty( $row['campaign_id'] ) ? ( $campaign_map[ absint( $row['campaign_id'] ) ] ?? null ) : null;
            $data['gateway'] = $gateway;
            if ( '' !== $gateway_id ) {
                $data['gateway_transaction_id'] = $gateway_id;
            }

            if ( $legacy_id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$current_table}` WHERE id = %d LIMIT 1", $legacy_id ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $data['id'] = $legacy_id;
            }

            $formats = array_map( static function ( $value ): string {
                return is_int( $value ) ? '%d' : '%s';
            }, $data );
            if ( false === $wpdb->insert( $current_table, $data, $formats ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                self::$failed = true;
            }
        }
    }

    /** @return array<int, string> */
    private static function columns( string $table ): array {
        global $wpdb;

        return array_map( 'strval', $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- trusted table name from $wpdb->prefix
    }

    /** @param array<string, mixed> $row @param array<int, string> $columns @param array<int, string> $skip */
    private static function common_data( array $row, array $columns, array $skip = [] ): array {
        $data = [];
        foreach ( $row as $column => $value ) {
            if ( in_array( $column, $skip, true ) || ! in_array( $column, $columns, true ) ) {
                continue;
            }
            $data[ $column ] = $value;
        }
        return $data;
    }

    private static function table_exists( string $table ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
