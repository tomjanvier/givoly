<?php
/**
 * Synchronisation opt-in vers la Plateforme Givoly.
 *
 * - Les dons confirmés sont mis en file via le hook `givoly_donation_completed`
 *   (un job par don, idempotent) puis poussés par WP-Cron, avec réessais bornés.
 * - Les campagnes sont poussées en instantané lecture seule sur action admin
 *   explicite (aucun import : la plateforme ne peut pas écraser les données locales).
 * - Aucun appel distant tant que la connexion n'est pas activée et configurée.
 *
 * @package Givoly\Integration
 */

namespace Givoly\Integration;

use Givoly\Admin\Settings;
use Givoly\Repository\CampaignRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PlatformSync {

    private const HOOK          = 'givoly_sync_platform';
    private const BATCH_SIZE    = 20;
    private const MAX_ATTEMPTS  = 25;
    private const ERROR_PREVIEW = 500;

    public function register(): void {
        add_action( 'givoly_donation_completed', [ $this, 'enqueue_donation' ] );
        add_action( self::HOOK, [ $this, 'run' ] );
        self::schedule();
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK );
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::HOOK );
    }

    /**
     * Met en file un don confirmé pour envoi à la plateforme (idempotent).
     *
     * @param array<string,int|string> $donation Payload du hook givoly_donation_completed.
     */
    public function enqueue_donation( array $donation ): void {
        if ( ! Settings::is_platform_enabled()
            || ! Settings::is_platform_configured()
            || ! Settings::should_sync_platform_donations()
        ) {
            return;
        }

        $donation_id = absint( $donation['donation_id'] ?? 0 );
        if ( $donation_id <= 0 ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'givoly_platform_jobs';

        // Idempotence : un seul job par don, les doublons sont ignorés.
        $exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE donation_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $donation_id
            )
        );
        if ( $exists ) {
            return;
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table,
            [
                'donation_id' => $donation_id,
                'status'      => 'pending',
                'created_at'  => current_time( 'mysql', true ),
                'updated_at'  => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        if ( false === $inserted && ! str_contains( strtolower( (string) $wpdb->last_error ), 'duplicate entry' ) ) {
            error_log( '[Givoly] Platform enqueue failed: ' . \Givoly\Core\Format::redact_secrets( (string) $wpdb->last_error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        $wpdb->last_error = '';
    }

    /**
     * Pousse les jobs en attente (WP-Cron horaire ou action admin).
     */
    public function run(): bool {
        $gateway = Settings::get_platform_client();
        if ( ! $gateway || ! Settings::should_sync_platform_donations() ) {
            return false;
        }

        global $wpdb;
        $jobs_table      = $wpdb->prefix . 'givoly_platform_jobs';
        $donations_table = $wpdb->prefix . 'givoly_donations';
        $donors_table    = $wpdb->prefix . 'givoly_donors';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix (trusted)
        $jobs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id, donation_id, attempts FROM {$jobs_table}
                 WHERE status IN ('pending', 'failed') AND attempts < %d
                 ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                self::MAX_ATTEMPTS,
                self::BATCH_SIZE
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( empty( $jobs ) ) {
            return true;
        }

        $all_ok = true;

        foreach ( (array) $jobs as $job ) {
            $job_id      = (int) ( $job['id'] ?? 0 );
            $donation_id = (int) ( $job['donation_id'] ?? 0 );
            if ( $job_id <= 0 || $donation_id <= 0 ) {
                continue;
            }

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix (trusted)
            $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT d.id, d.amount, d.currency, d.gateway, d.gateway_transaction_id,
                            d.donor_message AS campaign, d.created_at,
                            dn.first_name, dn.last_name, dn.email
                     FROM {$donations_table} d
                     INNER JOIN {$donors_table} dn ON dn.id = d.donor_id
                     WHERE d.id = %d AND d.status = 'completed' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $donation_id
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

            if ( ! $row ) {
                // Don introuvable ou non confirmé : job soldé en échec définitif, sans réessai.
                $this->mark_job( $job_id, false, 'Donation not found or not completed.', true );
                $all_ok = false;
                continue;
            }

            try {
                $gateway->push_donation(
                    self::build_donation_payload( [
                        'donation_id'    => (int) $row['id'],
                        'gateway'        => (string) $row['gateway'],
                        'transaction_id' => (string) $row['gateway_transaction_id'],
                        'email'          => (string) $row['email'],
                        'first_name'     => (string) $row['first_name'],
                        'last_name'      => (string) $row['last_name'],
                        'amount_cents'   => (int) round( (float) $row['amount'] * 100 ),
                        'currency'       => (string) $row['currency'],
                        'campaign'       => (string) ( $row['campaign'] ?? '' ),
                        'occurred_at'    => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( (string) $row['created_at'] ) ),
                    ] ),
                    self::idempotency_key( $donation_id )
                );
                $this->mark_job( $job_id, true, '' );
            } catch ( \Throwable $exception ) {
                $all_ok = false;
                error_log( '[Givoly] Platform push failed: ' . \Givoly\Core\Format::redact_secrets( $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                $this->mark_job( $job_id, false, $exception->getMessage() );
            }
        }

        return $all_ok;
    }

    /**
     * Contrôle de santé manuel (action admin) : mémorise statut + erreur expurgée.
     */
    public function check_now(): bool {
        $gateway = Settings::get_platform_client();
        if ( ! $gateway ) {
            Settings::record_platform_check( 'error', __( 'Platform connection is not configured.', 'givoly' ) );
            return false;
        }

        try {
            $gateway->health();
            Settings::record_platform_check( 'ok' );
            return true;
        } catch ( \Throwable $exception ) {
            error_log( '[Givoly] Platform health check failed: ' . \Givoly\Core\Format::redact_secrets( $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            Settings::record_platform_check( 'error', $exception->getMessage() );
            return false;
        }
    }

    /**
     * Enregistre le site auprès de la plateforme (action admin explicite).
     *
     * @return string Identifiant site retourné (peut être vide selon la plateforme).
     * @throws \RuntimeException si non configuré ou refusé.
     */
    public function register_site_now(): string {
        $gateway = Settings::get_platform_client();
        if ( ! $gateway ) {
            throw new \RuntimeException( 'Platform connection is not configured.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $site_id = $gateway->register_site( [
            'siteUrl'       => home_url( '/' ),
            'siteName'      => Settings::get_assoc_name() ?: get_bloginfo( 'name' ),
            'contactEmail'  => Settings::get_assoc_email(),
            'pluginVersion' => defined( 'GIVOLY_VERSION' ) ? GIVOLY_VERSION : 'unknown',
        ] );

        if ( $site_id !== '' ) {
            update_option( Settings::OPT_PLATFORM_SITE_ID, $site_id, false );
        }
        Settings::record_platform_check( 'ok' );

        return $site_id;
    }

    /**
     * Pousse un instantané des campagnes (lecture seule, action admin explicite).
     *
     * @return int Nombre de campagnes confirmées par la plateforme.
     * @throws \RuntimeException si non configuré ou refusé.
     */
    public function sync_campaigns_now(): int {
        $gateway = Settings::get_platform_client();
        if ( ! $gateway || ! Settings::should_sync_platform_campaigns() ) {
            throw new \RuntimeException( 'Platform campaign sync is not enabled.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $repo      = new CampaignRepository();
        $campaigns = $repo->find_all();
        $ids       = array_map( static fn( $campaign ): int => $campaign->get_id(), $campaigns );
        $stats_map = $repo->get_stats_batch( $ids );

        $snapshot = [];
        foreach ( $campaigns as $campaign ) {
            $stats      = $stats_map[ $campaign->get_id() ] ?? [ 'amount' => 0.0, 'donors' => 0 ];
            $snapshot[] = self::build_campaign_snapshot(
                [
                    'slug'        => $campaign->get_slug(),
                    'title'       => $campaign->get_title(),
                    'description' => $campaign->get_description(),
                    'goal_amount' => $campaign->get_goal_amount(),
                    'currency'    => $campaign->get_currency(),
                    'status'      => $campaign->get_status(),
                    'start_date'  => $campaign->get_start_date()?->format( 'Y-m-d' ),
                    'end_date'    => $campaign->get_end_date()?->format( 'Y-m-d' ),
                ],
                $stats
            );
        }

        return $gateway->push_campaigns( $snapshot );
    }

    /**
     * Construit le payload minimal d'un don (même périmètre que Stripe/HelloAsso).
     *
     * Volontairement sans adresse ni téléphone : minimisation des données.
     *
     * @param array<string,int|string> $donation Don normalisé du hook givoly_donation_completed.
     * @return array<string,mixed>
     */
    public static function build_donation_payload( array $donation ): array {
        $donation_id = absint( $donation['donation_id'] ?? 0 );

        return [
            'externalId'    => 'givoly-' . $donation_id,
            'amountCents'   => max( 0, (int) ( $donation['amount_cents'] ?? 0 ) ),
            'currency'      => strtoupper( sanitize_text_field( (string) ( $donation['currency'] ?? 'EUR' ) ) ),
            'gateway'       => sanitize_key( (string) ( $donation['gateway'] ?? '' ) ),
            'transactionId' => sanitize_text_field( (string) ( $donation['transaction_id'] ?? '' ) ),
            'campaign'      => sanitize_text_field( (string) ( $donation['campaign'] ?? '' ) ),
            'occurredAt'    => sanitize_text_field( (string) ( $donation['occurred_at'] ?? '' ) ),
            'donor'         => [
                'firstName' => sanitize_text_field( (string) ( $donation['first_name'] ?? '' ) ),
                'lastName'  => sanitize_text_field( (string) ( $donation['last_name'] ?? '' ) ),
                'email'     => sanitize_email( (string) ( $donation['email'] ?? '' ) ),
            ],
        ];
    }

    /**
     * Construit l'instantané lecture seule d'une campagne.
     *
     * @param array<string,mixed> $campaign Champs de l'entité Campaign.
     * @param array{amount: float, donors: int} $stats
     * @return array<string,mixed>
     */
    public static function build_campaign_snapshot( array $campaign, array $stats ): array {
        $goal = $campaign['goal_amount'] ?? null;

        return [
            'slug'           => sanitize_title( (string) ( $campaign['slug'] ?? '' ) ),
            'title'          => sanitize_text_field( (string) ( $campaign['title'] ?? '' ) ),
            'currency'       => strtoupper( sanitize_text_field( (string) ( $campaign['currency'] ?? 'EUR' ) ) ),
            'status'         => sanitize_key( (string) ( $campaign['status'] ?? '' ) ),
            'goalCents'      => $goal !== null ? (int) round( (float) $goal * 100 ) : null,
            'startDate'      => sanitize_text_field( (string) ( $campaign['start_date'] ?? '' ) ),
            'endDate'        => sanitize_text_field( (string) ( $campaign['end_date'] ?? '' ) ),
            'collectedCents' => (int) round( (float) ( $stats['amount'] ?? 0 ) * 100 ),
            'donors'         => (int) ( $stats['donors'] ?? 0 ),
        ];
    }

    /**
     * Clé d'idempotence unique par don.
     */
    public static function idempotency_key( int $donation_id ): string {
        return 'givoly-donation-' . max( 0, $donation_id );
    }

    private function mark_job( int $job_id, bool $sent, string $error, bool $final = false ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'givoly_platform_jobs';

        if ( $sent ) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $table,
                [
                    'status'     => 'sent',
                    'sent_at'    => current_time( 'mysql', true ),
                    'updated_at' => current_time( 'mysql', true ),
                    'last_error' => null,
                ],
                [ 'id' => $job_id ],
                [ '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
            return;
        }

        // Erreur expurgée et tronquée : jamais de secret en base ni en log.
        // Sans dépendance mbstring (non garantie sur tous les hébergeurs).
        $clean   = \Givoly\Core\Format::redact_secrets( $error );
        $clean   = sanitize_text_field( $clean );
        $preview = function_exists( 'mb_substr' ) ? mb_substr( $clean, 0, self::ERROR_PREVIEW ) : substr( $clean, 0, self::ERROR_PREVIEW );
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, attempts = attempts + 1, last_error = %s, updated_at = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $final ? 'failed_final' : 'failed',
                $preview !== '' ? $preview : null,
                current_time( 'mysql', true ),
                $job_id
            )
        );
    }
}
