<?php
/**
 * Gère l'installation et la désinstallation du plugin.
 *
 * Responsabilités :
 * - Créer les tables MySQL à l'activation
 * - Stocker la version DB pour les migrations futures
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Installer {

    const DB_VERSION_OPTION = 'givoly_db_version';
    const DB_VERSION        = '1.5';

    public static function activate(): void {
        self::create_tables();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
        add_option( \Givoly\Admin\Settings::OPT_PUBLIC_BRANDING_ENABLED, '0', '', false );
        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void {
        if ( self::needs_upgrade() ) {
            self::run_migrations(); // avant dbDelta pour que les renommages soient visibles
            self::create_tables();
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
        }
    }

    public static function deactivate(): void {
        // On ne supprime pas les données ici.
        // La suppression se fait dans uninstall.php.
        flush_rewrite_rules();
    }

    public static function needs_upgrade(): bool {
        return get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION;
    }

    /**
     * Migrations SQL cumulatives exécutées à chaque upgrade.
     * Chaque bloc est idempotent (vérifie l'existence avant d'agir).
     */
    private static function run_migrations(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'givoly_donations';

        // v1.3 → v1.4 : renommer stripe_payment_intent_id → gateway_refund_ref
        $old_col = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'stripe_payment_intent_id'",
                DB_NAME,
                $table
            )
        );

        if ( $old_col ) {
            $new_col = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'gateway_refund_ref'",
                    DB_NAME,
                    $table
                )
            );

            if ( $new_col ) {
                // Les deux colonnes coexistent (dbDelta a ajouté gateway_refund_ref avant la migration)
                // → copier les données puis supprimer l'ancienne colonne
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migrations, table name from $wpdb->prefix (trusted)
                $wpdb->query( "UPDATE `{$table}` SET `gateway_refund_ref` = `stripe_payment_intent_id` WHERE `gateway_refund_ref` IS NULL AND `stripe_payment_intent_id` IS NOT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `stripe_payment_intent_id`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            } else {
                // Renommage simple (install fraîche en 1.3)
                $wpdb->query( "ALTER TABLE `{$table}` CHANGE `stripe_payment_intent_id` `gateway_refund_ref` VARCHAR(255) DEFAULT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            }

            // Supprimer l'ancien index s'il existe encore
            $old_idx = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'idx_payment_intent'",
                    DB_NAME,
                    $table
                )
            );
            if ( $old_idx ) {
                $wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `idx_payment_intent`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            }

            // Créer le nouvel index s'il n'existe pas déjà
            $new_idx = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'idx_refund_ref'",
                    DB_NAME,
                    $table
                )
            );
            if ( ! $new_idx ) {
                $wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `idx_refund_ref` (`gateway_refund_ref`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            }
        }

        self::deduplicate_gateway_transactions( $table );
    }

    /**
     * Supprime les doublons de transactions avant l'ajout d'une contrainte UNIQUE.
     *
     * Les doublons sont anormaux : une même paire (gateway, gateway_transaction_id)
     * représente le même paiement. On garde la ligne la plus ancienne, on lui
     * remonte le statut remboursé / la référence de remboursement si nécessaire,
     * puis on supprime les doublons restants.
     */
    private static function deduplicate_gateway_transactions( string $table ): void {
        global $wpdb;

        $duplicates = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT gateway, gateway_transaction_id, GROUP_CONCAT(id ORDER BY id ASC) AS ids,
                    MAX(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS has_refunded,
                    MAX(NULLIF(gateway_refund_ref, '')) AS refund_ref
             FROM {$table}
             WHERE gateway_transaction_id IS NOT NULL AND gateway_transaction_id <> ''
             GROUP BY gateway, gateway_transaction_id
             HAVING COUNT(*) > 1",
            ARRAY_A
        );

        foreach ( $duplicates as $duplicate ) {
            $ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) $duplicate['ids'] ) ) ) );
            if ( count( $ids ) < 2 ) {
                continue;
            }

            $keep_id     = array_shift( $ids );
            $update_data = [];
            $formats     = [];

            if ( ! empty( $duplicate['refund_ref'] ) ) {
                $update_data['gateway_refund_ref'] = (string) $duplicate['refund_ref'];
                $formats[]                         = '%s';
            }

            if ( ! empty( $duplicate['has_refunded'] ) ) {
                $update_data['status'] = 'refunded';
                $formats[]             = '%s';
            }

            if ( $update_data ) {
                $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $table,
                    $update_data,
                    [ 'id' => $keep_id ],
                    $formats,
                    [ '%d' ]
                );
            }

            $delete_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE id IN ({$delete_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$ids
                )
            );
        }
    }

    private static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // ── Donateurs ────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}givoly_donors (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email           VARCHAR(254)    NOT NULL,
            first_name      VARCHAR(100)    NOT NULL DEFAULT '',
            last_name       VARCHAR(100)    NOT NULL DEFAULT '',
            company         VARCHAR(150)             DEFAULT NULL,
            address_line1   VARCHAR(255)             DEFAULT NULL,
            address_line2   VARCHAR(255)             DEFAULT NULL,
            postal_code     VARCHAR(10)              DEFAULT NULL,
            city            VARCHAR(100)             DEFAULT NULL,
            country         CHAR(2)         NOT NULL DEFAULT 'FR',
            phone           VARCHAR(30)              DEFAULT NULL,
            wp_user_id      BIGINT UNSIGNED          DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            UNIQUE KEY      uq_email (email),
            KEY             idx_wp_user (wp_user_id)
        ) $charset;" );

        // ── Dons ─────────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}givoly_donations (
            id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            donor_id                BIGINT UNSIGNED NOT NULL,
            campaign_id             BIGINT UNSIGNED          DEFAULT NULL,
            amount                  DECIMAL(10,2)   NOT NULL,
            currency                CHAR(3)         NOT NULL DEFAULT 'EUR',
            status                  ENUM('pending','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
            gateway                 VARCHAR(50)     NOT NULL DEFAULT 'stripe',
            gateway_transaction_id  VARCHAR(255)             DEFAULT NULL,
            gateway_refund_ref      VARCHAR(255)             DEFAULT NULL,
            post_payment_token      VARCHAR(64)              DEFAULT NULL,
            donor_message           TEXT                     DEFAULT NULL,
            created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY             (id),
            KEY                     idx_donor          (donor_id),
            KEY                     idx_status         (status),
            KEY                     idx_created        (created_at),
            KEY                     idx_refund_ref     (gateway_refund_ref),
            KEY                     idx_status_created (status, created_at),
            UNIQUE KEY              uq_gateway_transaction (gateway, gateway_transaction_id),
            UNIQUE KEY              uq_post_payment_token (post_payment_token)
        ) $charset;" );

        // ── Campagnes ─────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}givoly_campaigns (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title           VARCHAR(255)    NOT NULL,
            slug            VARCHAR(255)    NOT NULL,
            description     TEXT                     DEFAULT NULL,
            goal_amount     DECIMAL(10,2)            DEFAULT NULL,
            currency        CHAR(3)         NOT NULL DEFAULT 'EUR',
            start_date      DATE                     DEFAULT NULL,
            end_date        DATE                     DEFAULT NULL,
            status          ENUM('draft','active','ended','archived') NOT NULL DEFAULT 'draft',
            featured_image  BIGINT UNSIGNED          DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            UNIQUE KEY      uq_slug (slug),
            KEY             idx_status (status)
        ) $charset;" );

    }
}
