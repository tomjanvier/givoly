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
    const DB_VERSION        = '2.3';

    public static function activate(): void {
        if ( ! self::create_tables() ) {
            return;
        }
        LegacyMigration::run();
        if ( ! self::run_migrations() ) {
            return;
        }
        if ( ! self::migrate_donor_references() ) {
            return;
        }
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
        add_option( \Givoly\Admin\Settings::OPT_PUBLIC_BRANDING_ENABLED, '0', '', false );
        \Givoly\Mail\MailQueue::schedule();
        \Givoly\Integration\StripeSync::schedule();
        \Givoly\Integration\HelloAssoSync::schedule();
        \Givoly\Integration\PlatformSync::schedule();
        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void {
        if ( self::needs_upgrade() ) {
            // Une migration en erreur ne doit jamais faire avancer la version :
            // chaque étape est contrôlée et la version n'est écrite qu'en cas de succès complet.
            $migrations_ok = self::run_migrations();
            $tables_ok     = $migrations_ok ? self::create_tables() : false;
            $second_ok     = $tables_ok ? self::run_migrations() : false;
            $refs_ok       = $second_ok ? self::migrate_donor_references() : false;
            if ( $migrations_ok && $tables_ok && $second_ok && $refs_ok ) {
                update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
            }
        }

        LegacyMigration::run();
    }

    public static function deactivate(): void {
        // On ne supprime pas les données ici.
        // La suppression se fait dans uninstall.php.
        flush_rewrite_rules();
        \Givoly\Mail\MailQueue::unschedule();
        \Givoly\Integration\StripeSync::unschedule();
        \Givoly\Integration\HelloAssoSync::unschedule();
        \Givoly\Integration\PlatformSync::unschedule();
    }

    public static function needs_upgrade(): bool {
        return get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION;
    }

    /**
     * Migrations SQL cumulatives exécutées à chaque upgrade.
     * Chaque bloc est idempotent (vérifie l'existence avant d'agir).
     *
     * Retourne false dès qu'une étape échoue : l'appelant ne doit alors pas
     * faire avancer givoly_db_version, pour permettre une nouvelle tentative.
     */
    private static function run_migrations(): bool {
        global $wpdb;

        // The former plugin-owned CSS setting is intentionally discarded. CSS
        // customization now belongs in WordPress's native CSS editor.
        delete_option( 'givoly_appearance_custom_css' );

        $table = esc_sql( $wpdb->prefix . 'givoly_donations' );

        if ( ! self::table_exists( $table ) ) {
            return self::migrate_donor_references();
        }

        if ( ! self::migrate_donor_references() ) {
            return false;
        }

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
                if ( self::has_db_error() ) {
                    return false;
                }
                $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `stripe_payment_intent_id`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( self::has_db_error() ) {
                    return false;
                }
            } else {
                // Renommage simple (install fraîche en 1.3)
                $wpdb->query( "ALTER TABLE `{$table}` CHANGE `stripe_payment_intent_id` `gateway_refund_ref` VARCHAR(255) DEFAULT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( self::has_db_error() ) {
                    return false;
                }
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
                // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migrations, table name from $wpdb->prefix (trusted)
                $wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `idx_payment_intent`" );
                // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( self::has_db_error() ) {
                    return false;
                }
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
                // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migrations, table name from $wpdb->prefix (trusted)
                $wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `idx_refund_ref` (`gateway_refund_ref`)" );
                // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( self::has_db_error() ) {
                    return false;
                }
            }
        }

        if ( ! self::deduplicate_gateway_transactions( $table ) ) {
            return false;
        }

        // v1.7 → v1.8 : persister le message saisi par le donor.
        // donor_message est historiquement réservé au slug de campagne (rétrocompat) :
        // le message réel doit donc vivre dans une colonne dédiée.
        $notes_col = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'donor_notes'",
                DB_NAME,
                $table
            )
        );

        if ( ! $notes_col ) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migration, table name escaped from the trusted WordPress prefix.
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `donor_notes` TEXT DEFAULT NULL AFTER `donor_message`" );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( self::has_db_error() ) {
                return false;
            }
        }

        // v2.2 : modèle d'abonnements multi-lignes. La colonne historique
        // donors.stripe_subscription_id est conservée telle quelle ; les dons
        // historiques ne sont pas rattachés a posteriori (ambiguïté).
        if ( ! self::migrate_subscriptions() ) {
            return false;
        }

        // v2.3 : file persistante vers la Plateforme (opt-in, aucun envoi passé rejoué).
        if ( ! self::migrate_platform_jobs() ) {
            return false;
        }

        return true;
    }

    /**
     * Supprime les doublons de transactions avant l'ajout d'une contrainte UNIQUE.
     *
     * Les doublons sont anormaux : une même paire (gateway, gateway_transaction_id)
     * représente le même paiement. On garde la ligne la plus ancienne, on lui
     * remonte le statut remboursé / la référence de remboursement si nécessaire,
     * puis on supprime les doublons restants.
     *
     * Retourne false en cas d'erreur SQL pour bloquer l'avancement de version.
     */
    private static function deduplicate_gateway_transactions( string $table ): bool {
        global $wpdb;

        $table = esc_sql( $table );

        // L'identifiant de table provient du préfixe WordPress et est échappé.
        // Il ne peut pas être remplacé par un placeholder SQL.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
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
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

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
                $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $table,
                    $update_data,
                    [ 'id' => $keep_id ],
                    $formats,
                    [ '%d' ]
                );
                if ( false === $updated && self::has_db_error() ) {
                    return false;
                }
            }

            $delete_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE id IN ({$delete_placeholders})",
                    ...$ids
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( false === $deleted && self::has_db_error() ) {
                return false;
            }
        }

        return ! self::has_db_error();
    }

    private static function create_tables(): bool {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // ── Donors ────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}givoly_donors (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            donor_reference VARCHAR(32)              DEFAULT NULL,
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
            stripe_customer_id     VARCHAR(255)        DEFAULT NULL,
            stripe_subscription_id VARCHAR(255)        DEFAULT NULL,
            magic_token_hash       CHAR(64)            DEFAULT NULL,
            magic_token_expires_at DATETIME            DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY     (id),
            UNIQUE KEY      uq_donor_reference (donor_reference),
            UNIQUE KEY      uq_email (email),
            KEY             idx_wp_user (wp_user_id),
            KEY             idx_stripe_customer (stripe_customer_id),
            KEY             idx_stripe_subscription (stripe_subscription_id),
            KEY             idx_magic_token (magic_token_hash)
        ) $charset;" );

        // ── Donations ─────────────────────────────────────────────────────────────
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
            stripe_subscription_id  VARCHAR(255)             DEFAULT NULL,
            post_payment_token      VARCHAR(64)              DEFAULT NULL,
            donor_message           TEXT                     DEFAULT NULL,
            donor_notes             TEXT                     DEFAULT NULL,
            created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY             (id),
            KEY                     idx_donor          (donor_id),
            KEY                     idx_status         (status),
            KEY                     idx_created        (created_at),
            KEY                     idx_refund_ref     (gateway_refund_ref),
            KEY                     idx_stripe_subscription (stripe_subscription_id),
            KEY                     idx_status_created (status, created_at),
            UNIQUE KEY              uq_gateway_transaction (gateway, gateway_transaction_id),
            UNIQUE KEY              uq_post_payment_token (post_payment_token)
        ) $charset;" );

        // ── Campaigns ─────────────────────────────────────────────────────────
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

        // ── File des emails ─────────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}givoly_email_jobs (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id     VARCHAR(80)              DEFAULT NULL,
            job_type     VARCHAR(50)     NOT NULL,
            recipient    VARCHAR(254)    NOT NULL DEFAULT '',
            payload      LONGTEXT        NOT NULL,
            status       VARCHAR(20)     NOT NULL DEFAULT 'pending',
            attempts     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME        NOT NULL,
            last_error   TEXT                     DEFAULT NULL,
            created_at   DATETIME        NOT NULL,
            updated_at   DATETIME        NOT NULL,
            sent_at      DATETIME                 DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY          idx_status_available (status, available_at),
            KEY          idx_batch_status (batch_id, status)
        ) $charset;" );

        if ( self::has_db_error() ) {
            return false;
        }

        // ── Abonnements Stripe (un donateur → N abonnements) ──────────────
        // La colonne historique donors.stripe_subscription_id est conservée ;
        // cette table devient la source de vérité pour l'annulation.
        dbDelta( "CREATE TABLE {$wpdb->prefix}givoly_subscriptions (
            id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            donor_id               BIGINT UNSIGNED NOT NULL,
            stripe_subscription_id VARCHAR(255)    NOT NULL,
            stripe_customer_id     VARCHAR(255)             DEFAULT NULL,
            status                 VARCHAR(30)     NOT NULL DEFAULT 'active',
            currency               CHAR(3)                  DEFAULT NULL,
            campaign_id            BIGINT UNSIGNED          DEFAULT NULL,
            created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY            (id),
            UNIQUE KEY             uq_stripe_subscription (stripe_subscription_id),
            KEY                    idx_sub_donor (donor_id),
            KEY                    idx_sub_customer (stripe_customer_id)
        ) $charset;" );

        if ( self::has_db_error() ) {
            return false;
        }

        // ── File vers la Plateforme (opt-in, un job par don, idempotent) ──
        dbDelta( "CREATE TABLE {$wpdb->prefix}givoly_platform_jobs (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            donation_id  BIGINT UNSIGNED NOT NULL,
            status       VARCHAR(20)     NOT NULL DEFAULT 'pending',
            attempts     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error   TEXT                     DEFAULT NULL,
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            sent_at      DATETIME                 DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY   uq_platform_donation (donation_id),
            KEY          idx_platform_status (status)
        ) $charset;" );

        return ! self::has_db_error();
    }

    private static function migrate_donor_references(): bool {
        global $wpdb;

        $table = esc_sql( $wpdb->prefix . 'givoly_donors' );
        if ( ! self::table_exists( $table ) ) {
            return true;
        }

        $column_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'donor_reference'",
                DB_NAME,
                $table
            )
        );

        if ( ! $column_exists ) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migration, table name escaped from the trusted WordPress prefix.
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `donor_reference` VARCHAR(32) DEFAULT NULL AFTER `id`" );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( self::has_db_error() ) {
                return false;
            }
        }

        $index_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'uq_donor_reference'",
                DB_NAME,
                $table
            )
        );

        if ( ! $index_exists ) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migration, table name escaped from the trusted WordPress prefix.
            $wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_donor_reference` (`donor_reference`)" );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( self::has_db_error() ) {
                return false;
            }
        }

        $missing = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT id, first_name, created_at FROM {$table} WHERE donor_reference IS NULL OR donor_reference = ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            ARRAY_A
        );
        if ( self::has_db_error() ) {
            return false;
        }

        foreach ( $missing as $donor ) {
            $reference = \Givoly\Donor\DonorReference::generate( (string) $donor['first_name'], (string) $donor['created_at'] );
            $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $table,
                [ 'donor_reference' => $reference ],
                [ 'id' => (int) $donor['id'] ],
                [ '%s' ],
                [ '%d' ]
            );
            if ( false === $updated && self::has_db_error() ) {
                return false;
            }
            // Un doublon concurrent sur donor_reference reste rejouable au prochain passage.
            $wpdb->last_error = '';
        }

        return true;
    }

    /**
     * Migration v2.2 : table d'abonnements + colonne de rattachement par don.
     *
     * Idempotente et non destructive :
     * - crée la table et la colonne uniquement si absentes ;
     * - recopie chaque stripe_subscription_id donateur connu en une ligne
     *   d'abonnement (statut active) sans écraser l'existant ;
     * - ne rattache jamais les dons historiques à un abonnement (données
     *   ambiguës : plusieurs échéances peuvent partager le même donateur).
     *
     * Retourne false en cas d'erreur SQL pour bloquer l'avancement de version.
     */
    private static function migrate_subscriptions(): bool {
        global $wpdb;

        $subs_table      = esc_sql( $wpdb->prefix . 'givoly_subscriptions' );
        $donors_table    = esc_sql( $wpdb->prefix . 'givoly_donors' );
        $donations_table = esc_sql( $wpdb->prefix . 'givoly_donations' );

        if ( ! self::table_exists( $donors_table ) ) {
            return true;
        }

        if ( ! self::table_exists( $subs_table ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migration, table names from $wpdb->prefix (trusted)
            $wpdb->query(
                "CREATE TABLE IF NOT EXISTS `{$subs_table}` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `donor_id` BIGINT UNSIGNED NOT NULL,
                    `stripe_subscription_id` VARCHAR(255) NOT NULL,
                    `stripe_customer_id` VARCHAR(255) DEFAULT NULL,
                    `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                    `currency` CHAR(3) DEFAULT NULL,
                    `campaign_id` BIGINT UNSIGNED DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_stripe_subscription` (`stripe_subscription_id`),
                    KEY `idx_sub_donor` (`donor_id`),
                    KEY `idx_sub_customer` (`stripe_customer_id`)
                ) {$charset}"
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( self::has_db_error() || ! self::table_exists( $subs_table ) ) {
                return false;
            }
        }

        // Colonne de rattachement exact par don (NULL pour l'historique ambigu).
        if ( self::table_exists( $donations_table ) && ! self::column_exists( $donations_table, 'stripe_subscription_id' ) ) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migration, table name escaped from the trusted WordPress prefix.
            $wpdb->query( "ALTER TABLE `{$donations_table}` ADD COLUMN `stripe_subscription_id` VARCHAR(255) DEFAULT NULL AFTER `gateway_refund_ref`" );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( self::has_db_error() ) {
                return false;
            }
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migration, table name escaped from the trusted WordPress prefix.
            $wpdb->query( "ALTER TABLE `{$donations_table}` ADD INDEX `idx_stripe_subscription` (`stripe_subscription_id`)" );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter
            // Un index manquant ne bloque pas la migration (dbDelta le créera au second passage).
            $wpdb->last_error = '';
        }

        // Recopie des identifiants donateurs connus → une ligne par valeur distincte.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $known = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT id AS donor_id, stripe_customer_id, stripe_subscription_id FROM {$donors_table} WHERE stripe_subscription_id IS NOT NULL AND stripe_subscription_id <> ''",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( self::has_db_error() ) {
            return false;
        }

        foreach ( (array) $known as $row ) {
            $sub_id = sanitize_text_field( (string) ( $row['stripe_subscription_id'] ?? '' ) );
            if ( $sub_id === '' ) {
                continue;
            }
            $exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT id FROM {$subs_table} WHERE stripe_subscription_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $sub_id
                )
            );
            if ( self::has_db_error() ) {
                return false;
            }
            if ( $exists ) {
                continue;
            }
            $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $subs_table,
                [
                    'donor_id'               => (int) $row['donor_id'],
                    'stripe_subscription_id' => $sub_id,
                    'stripe_customer_id'     => sanitize_text_field( (string) ( $row['stripe_customer_id'] ?? '' ) ) ?: null,
                    'status'                 => 'active',
                    'created_at'             => current_time( 'mysql', true ),
                    'updated_at'             => current_time( 'mysql', true ),
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%s' ]
            );
            if ( false === $inserted ) {
                if ( str_contains( strtolower( (string) $wpdb->last_error ), 'duplicate entry' ) ) {
                    $wpdb->last_error = '';
                    continue;
                }
                if ( self::has_db_error() ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Migration v2.3 : file persistante vers la Plateforme.
     *
     * Idempotente et non destructive : crée la table uniquement si absente,
     * ne rejoue aucun envoi passé (la file ne contient que les dons confirmés
     * après l'activation de la connexion).
     *
     * Retourne false en cas d'erreur SQL pour bloquer l'avancement de version.
     */
    private static function migrate_platform_jobs(): bool {
        global $wpdb;

        $jobs_table = esc_sql( $wpdb->prefix . 'givoly_platform_jobs' );

        if ( self::table_exists( $jobs_table ) ) {
            return true;
        }

        $charset = $wpdb->get_charset_collate();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL migration, table name from $wpdb->prefix (trusted)
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS `{$jobs_table}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `donation_id` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `last_error` TEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `sent_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_platform_donation` (`donation_id`),
                KEY `idx_platform_status` (`status`)
            ) {$charset}"
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return ! self::has_db_error() && self::table_exists( $jobs_table );
    }

    private static function column_exists( string $table, string $column ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table,
                $column
            )
        );
    }

    /**
     * Indique si la dernière requête a échoué (hors doublon géré par l'appelant).
     */
    private static function has_db_error(): bool {
        global $wpdb;

        return isset( $wpdb->last_error ) && $wpdb->last_error !== '';
    }

    private static function table_exists( string $table ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        );
    }
}
