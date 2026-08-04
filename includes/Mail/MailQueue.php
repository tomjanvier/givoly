<?php
/**
 * File persistante pour les emails Givoly.
 *
 * Les webhooks et les actions admin ne doivent pas attendre SMTP. Chaque
 * message est stocké, traité par WP-Cron et visible dans le back-office.
 *
 * @package Givoly\Mail
 */

namespace Givoly\Mail;

use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MailQueue {

    private const HOOK       = 'givoly_process_mail_queue';
    private const MAX_PER_RUN = 10;
    private const MAX_ATTEMPTS = 3;

    public function register(): void {
        add_action( self::HOOK, [ $this, 'process' ] );
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_single_event( time() + 5, self::HOOK );
        }
    }

    public static function unschedule(): void {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }

    public static function enqueue( string $type, array $payload, string $recipient = '', string $batch_id = '' ): int {
        global $wpdb;

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- this is the plugin-owned persistent queue table.
            self::table(),
            [
                'batch_id'    => $batch_id ?: null,
                'job_type'    => sanitize_key( $type ),
                'recipient'   => sanitize_email( $recipient ),
                'payload'     => wp_json_encode( $payload ),
                'status'      => 'pending',
                'attempts'    => 0,
                'available_at'=> current_time( 'mysql', true ),
                'created_at'  => current_time( 'mysql', true ),
                'updated_at'  => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            error_log( '[Givoly] Impossible de mettre un email en file : ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return 0;
        }

        self::schedule();
        return (int) $wpdb->insert_id;
    }

    public function process(): void {
        for ( $processed = 0; $processed < self::MAX_PER_RUN; $processed++ ) {
            $job = $this->claim_next_job();
            if ( ! $job ) {
                break;
            }

            try {
                $this->send_job( $job );
                $this->mark_sent( (int) $job['id'] );
            } catch ( \Throwable $exception ) {
                $this->mark_failed( $job, $exception->getMessage() );
            }
        }

        if ( $this->has_pending_jobs() ) {
            self::schedule();
        }
    }

    public static function get_batch_stats( string $batch_id ): array {
        global $wpdb;

        if ( '' === $batch_id ) {
            return [ 'total' => 0, 'pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0 ];
        }

        $table = esc_sql( self::table() );
        // Les placeholders ne peuvent pas représenter un identifiant de table.
        // Le nom vient du préfixe WordPress et est échappé avant interpolation.
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                'SELECT status, COUNT(*) AS total FROM ' . $table . ' WHERE batch_id = %s GROUP BY status',
                $batch_id
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $stats = [ 'total' => 0, 'pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0 ];
        foreach ( $rows ?: [] as $row ) {
            $status = (string) $row['status'];
            $count  = (int) $row['total'];
            if ( isset( $stats[ $status ] ) ) {
                $stats[ $status ] = $count;
            }
            $stats['total'] += $count;
        }

        return $stats;
    }

    /**
     * @return array<int,object>
     */
    public static function get_batch_jobs( string $batch_id, int $limit = 50 ): array {
        global $wpdb;

        if ( '' === $batch_id ) {
            return [];
        }

        $table = esc_sql( self::table() );

        // Les placeholders ne peuvent pas représenter un identifiant de table.
        // Le nom vient du préfixe WordPress et est échappé avant interpolation.
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                'SELECT id, recipient, status, attempts, last_error, created_at, sent_at FROM ' . $table . ' WHERE batch_id = %s ORDER BY id DESC LIMIT %d',
                $batch_id,
                max( 1, min( 100, $limit ) )
            )
        ) ?: [];
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'givoly_email_jobs';
    }

    private function claim_next_job(): ?array {
        global $wpdb;

        $table = esc_sql( self::table() );

        // Récupérer les jobs abandonnés après un crash PHP ou une coupure SMTP.
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "UPDATE {$table} SET status = 'pending', updated_at = UTC_TIMESTAMP() WHERE status = 'processing' AND updated_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table identifier is escaped from the trusted WordPress prefix.
        );

        $job = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$table} WHERE status = 'pending' AND available_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table identifier is escaped from the trusted WordPress prefix.
            ARRAY_A
        );

        if ( ! $job ) {
            return null;
        }

        $claimed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'processing', attempts = attempts + 1, updated_at = UTC_TIMESTAMP() WHERE id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table identifier is escaped from the trusted WordPress prefix.
                (int) $job['id']
            )
        );

        if ( ! $claimed ) {
            return null;
        }

        $job['attempts'] = (int) $job['attempts'] + 1;
        $job['status']   = 'processing';
        return $job;
    }

    private function send_job( array $job ): void {
        $payload = json_decode( (string) $job['payload'], true );
        if ( ! is_array( $payload ) ) {
            throw new \RuntimeException( 'Contenu de job email invalide.' );
        }

        if ( 'tax_receipt' === $job['job_type'] ) {
            $this->send_tax_receipt( $payload );
            return;
        }

        if ( in_array( $job['job_type'], [ 'donation_admin', 'donation_thank' ], true ) ) {
            $this->send_donation_email( (string) $job['job_type'], $payload );
            return;
        }

        if ( 'donor_magic_login' === $job['job_type'] ) {
            $this->send_magic_login_email( $payload );
            return;
        }

        throw new \RuntimeException( 'Type de job email inconnu.' );
    }

    private function send_donation_email( string $type, array $payload ): void {
        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $amount    = number_format_i18n( (float) ( $payload['amount'] ?? 0 ), 2 ) . ' ' . (string) ( $payload['currency'] ?? 'EUR' );
        $campaign  = (string) ( $payload['campaign'] ?? '' ) ?: __( 'Générale', 'givoly' );
        $variables = [
            '{site_name}'  => $site_name,
            '{amount}'     => $amount,
            '{first_name}' => (string) ( $payload['first_name'] ?? '' ) ?: __( 'donateur', 'givoly' ),
            '{last_name}'  => (string) ( $payload['last_name'] ?? '' ),
            '{campaign}'   => $campaign,
            '{donation_id}'=> (string) (int) ( $payload['donation_id'] ?? 0 ),
            '{email}'      => (string) ( $payload['email'] ?? '' ),
        ];

        if ( 'donation_admin' === $type ) {
            $recipient = get_option( 'admin_email' );
            $subject   = strtr( Settings::get_email_admin_donation_subject(), $variables );
            $body      = strtr( Settings::get_email_admin_donation_body(), $variables );
        } else {
            $recipient = sanitize_email( $payload['email'] ?? '' );
            $subject   = strtr( Settings::get_email_thank_subject(), $variables );
            $body      = strtr( Settings::get_email_thank_body(), $variables );
        }

        if ( ! is_email( $recipient ) || ! wp_mail( $recipient, $subject, EmailRenderer::render( $body, $subject ), EmailRenderer::headers() ) ) {
            throw new \RuntimeException( 'wp_mail() a refusé l’email de don.' );
        }
    }

    private function send_magic_login_email( array $payload ): void {
        $recipient = sanitize_email( $payload['email'] ?? '' );
        $url       = esc_url_raw( $payload['url'] ?? '' );
        if ( ! is_email( $recipient ) || ! wp_http_validate_url( $url ) ) {
            throw new \RuntimeException( 'Lien d’accès donateur invalide.' );
        }

        $subject = __( 'Votre accès à l’espace donateur', 'givoly' );
        $body    = sprintf(
            /* translators: %s: secure magic link. */
            __( "Bonjour,\n\nCliquez sur ce lien pour accéder à votre espace donateur :\n%s\n\nCe lien expire dans 15 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez cet email.", 'givoly' ),
            $url
        );

        if ( ! wp_mail( $recipient, $subject, EmailRenderer::render( $body, $subject ), EmailRenderer::headers() ) ) {
            throw new \RuntimeException( 'wp_mail() a refusé le lien d’accès donateur.' );
        }
    }

    private function send_tax_receipt( array $donor ): void {
        $email = sanitize_email( $donor['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            throw new \RuntimeException( 'Adresse email de donateur invalide.' );
        }

        $association = (string) ( $donor['association'] ?? get_bloginfo( 'name' ) );
        $name        = trim( (string) ( $donor['first_name'] ?? '' ) . ' ' . (string) ( $donor['last_name'] ?? '' ) );
        $amount      = number_format_i18n( (float) ( $donor['total_amount'] ?? 0 ), 2 ) . ' ' . (string) ( $donor['currency'] ?? 'EUR' );
        $variables   = [
            '{donor_name}'          => $name ?: __( 'cher donateur', 'givoly' ),
            '{first_name}'          => (string) ( $donor['first_name'] ?? '' ),
            '{last_name}'           => (string) ( $donor['last_name'] ?? '' ),
            '{year}'                => (string) (int) ( $donor['year'] ?? 0 ),
            '{amount}'              => $amount,
            '{donation_count}'      => (string) (int) ( $donor['donation_count'] ?? 0 ),
            '{association}'         => $association,
            '{association_address}' => (string) ( $donor['association_address'] ?? '' ) ?: __( 'non renseignée', 'givoly' ),
            '{siret}'               => (string) ( $donor['siret'] ?? '' ) ?: __( 'non renseigné', 'givoly' ),
            '{rna}'                 => (string) ( $donor['rna'] ?? '' ) ?: __( 'non renseigné', 'givoly' ),
            '{fiscal_id}'           => (string) ( $donor['fiscal_id'] ?? '' ) ?: __( 'non renseigné', 'givoly' ),
        ];

        $subject = strtr( Settings::get_email_tax_receipt_subject(), $variables );
        $body    = strtr( Settings::get_email_tax_receipt_body(), $variables );
        $headers = EmailRenderer::headers();

        $attachments = [];
        $temp_file   = '';

        if ( Settings::should_attach_tax_receipt_pdf() ) {
            $pdf = TaxReceiptPdf::generate(
                strtr( Settings::get_tax_receipt_pdf_title(), $variables ),
                strtr( Settings::get_tax_receipt_pdf_body(), $variables ),
                strtr( Settings::get_tax_receipt_pdf_footer(), $variables )
            );
            $filename = sanitize_file_name( 'recu-fiscal-' . (int) ( $donor['year'] ?? 0 ) . '-' . ( $name ?: 'donateur' ) . '.pdf' );
            $temp_file = wp_tempnam( $filename );

            if ( ! $temp_file || false === file_put_contents( $temp_file, $pdf ) ) {
                throw new \RuntimeException( 'Impossible de préparer le PDF du reçu fiscal.' );
            }

            $attachments[] = $temp_file;
        }

        try {
            if ( ! wp_mail( $email, $subject, EmailRenderer::render( $body, $subject ), $headers, $attachments ) ) {
                throw new \RuntimeException( 'wp_mail() a refusé le reçu fiscal.' );
            }
        } finally {
            if ( $temp_file && file_exists( $temp_file ) ) {
                wp_delete_file( $temp_file );
            }
        }
    }

    private function mark_sent( int $id ): void {
        global $wpdb;
        $wpdb->update( esc_sql( self::table() ), [ 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ], [ '%s', '%s', '%s' ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- this is the plugin-owned persistent queue table.
    }

    private function mark_failed( array $job, string $error ): void {
        global $wpdb;
        $table   = esc_sql( self::table() );
        $attempts = (int) $job['attempts'];
        $retry     = $attempts < self::MAX_ATTEMPTS;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- this is the plugin-owned persistent queue table.
            $table,
            [
                'status'       => $retry ? 'pending' : 'failed',
                'available_at' => $retry ? gmdate( 'Y-m-d H:i:s', time() + ( $attempts * 300 ) ) : current_time( 'mysql', true ),
                'last_error'   => sanitize_textarea_field( $error ),
                'updated_at'   => current_time( 'mysql', true ),
            ],
            [ 'id' => (int) $job['id'] ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    private function has_pending_jobs(): bool {
        global $wpdb;
        $table = esc_sql( self::table() );
        return (bool) $wpdb->get_var( "SELECT id FROM {$table} WHERE status = 'pending' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table identifier is escaped from the trusted WordPress prefix.
    }
}
