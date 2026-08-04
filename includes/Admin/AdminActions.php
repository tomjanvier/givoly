<?php
/**
 * Gestionnaire des actions admin POST.
 *
 * @package Givoly\Admin
 */

namespace Givoly\Admin;

use Givoly\Gateway\StripeGateway;
use Givoly\Admin\Settings;
use Givoly\Mail\TaxReceiptService;
use Givoly\Ajax\PaymentProcessor;
use Givoly\Integration\StripeSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminActions {

    public function register(): void {
        add_action( 'admin_post_givoly_refund_donation', [ $this, 'handle_refund_donation' ] );
        add_action( 'admin_post_givoly_cancel_subscription', [ $this, 'handle_cancel_subscription' ] );
        add_action( 'admin_post_givoly_sync_stripe_now', [ $this, 'handle_sync_stripe_now' ] );
        add_action( 'admin_post_givoly_export_donations', [ $this, 'handle_export_donations' ] );
        add_action( 'admin_post_givoly_queue_tax_receipts', [ $this, 'handle_queue_tax_receipts' ] );
        add_action( 'admin_post_givoly_add_manual_donation', [ $this, 'handle_add_manual_donation' ] );
        add_action( 'admin_post_givoly_update_donor', [ $this, 'handle_update_donor' ] );
        // Compatibilité avec l'action utilisée par les versions précédentes.
        add_action( 'admin_post_givoly_send_yearly_tax_receipts', [ $this, 'handle_send_yearly_tax_receipts' ] );
    }

    public function handle_refund_donation(): void {
        $donation_id = (int) ( isset( $_POST['donation_id'] ) ? wp_unslash( $_POST['donation_id'] ) : 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        check_admin_referer( 'givoly_refund_donation_' . $donation_id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $redirect_base = admin_url( 'admin.php?page=givoly-donations' );

        if ( ! $donation_id ) {
            wp_safe_redirect( add_query_arg( 'givoly_refund_error', '1', $redirect_base ) );
            exit;
        }

        global $wpdb;

        $donation = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id, gateway, status, gateway_refund_ref
                 FROM {$wpdb->prefix}givoly_donations
                 WHERE id = %d",
                $donation_id
            ),
            ARRAY_A
        );

        if (
            ! $donation
            || $donation['status'] !== 'completed'
            || $donation['gateway'] !== 'stripe'
            || empty( $donation['gateway_refund_ref'] )
        ) {
            wp_safe_redirect( add_query_arg( 'givoly_refund_error', '1', $redirect_base ) );
            exit;
        }

        try {
            $gateway = new StripeGateway( Settings::get_stripe_secret_key() );
            $gateway->refund( $donation['gateway_refund_ref'] );

            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'givoly_donations',
                [ 'status' => 'refunded' ],
                [ 'id'     => $donation_id ],
                [ '%s' ],
                [ '%d' ]
            );

            wp_safe_redirect( add_query_arg( 'givoly_refunded', '1', $redirect_base ) );

        } catch ( \RuntimeException $e ) {
            error_log( '[Givoly] Erreur remboursement don #' . $donation_id . ' : ' . \Givoly\Core\Format::redact_secrets( $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            wp_safe_redirect( add_query_arg( 'givoly_refund_error', '1', $redirect_base ) );
        }

        exit;
    }

    public function handle_cancel_subscription(): void {
        $donation_id = absint( wp_unslash( $_POST['donation_id'] ?? 0 ) );

        check_admin_referer( 'givoly_cancel_subscription_' . $donation_id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $redirect_base = admin_url( 'admin.php?page=givoly-donations' );
        if ( ! $donation_id ) {
            wp_safe_redirect( add_query_arg( 'givoly_subscription_cancel_error', '1', $redirect_base ) );
            exit;
        }

        global $wpdb;

        $subscription_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT dn.stripe_subscription_id
                 FROM {$wpdb->prefix}givoly_donations d
                 INNER JOIN {$wpdb->prefix}givoly_donors dn ON dn.id = d.donor_id
                 WHERE d.id = %d AND d.gateway = 'stripe'
                 LIMIT 1",
                $donation_id
            )
        );

        if ( ! is_string( $subscription_id ) || $subscription_id === '' ) {
            wp_safe_redirect( add_query_arg( 'givoly_subscription_cancel_error', '1', $redirect_base ) );
            exit;
        }

        try {
            $cancelled = ( new StripeGateway( Settings::get_stripe_secret_key() ) )->cancel_subscription_at_period_end( $subscription_id );
            if ( ! $cancelled ) {
                throw new \RuntimeException( 'Stripe n’a pas confirmé la programmation de la résiliation.' );
            }

            wp_safe_redirect( add_query_arg( 'givoly_subscription_cancelled', '1', $redirect_base ) );
        } catch ( \Throwable $exception ) {
            error_log( '[Givoly] Erreur annulation abonnement #' . $donation_id . ' : ' . \Givoly\Core\Format::redact_secrets( $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            wp_safe_redirect( add_query_arg( 'givoly_subscription_cancel_error', '1', $redirect_base ) );
        }

        exit;
    }

    public function handle_sync_stripe_now(): void {
        check_admin_referer( 'givoly_sync_stripe_now' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $redirect_base = admin_url( 'admin.php?page=givoly-donations' );
        $success       = ( new StripeSync() )->run( true );
        $notice         = $success ? 'givoly_stripe_sync_done' : 'givoly_stripe_sync_error';

        wp_safe_redirect( add_query_arg( $notice, '1', $redirect_base ) );
        exit;
    }

    public function handle_export_donations(): void {
        check_admin_referer( 'givoly_export_donations' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $valid_statuses = [ '', 'completed', 'pending', 'failed', 'refunded', 'cancelled' ];
        $status         = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $status = '';
        }

        $this->export_donations_csv( $status );
        exit;
    }

    /**
     * Génère le CSV des dons. Appelé via admin-post.php, avant tout rendu HTML
     * de l'admin : les headers de téléchargement ne sont donc jamais précédés
     * par du HTML, contrairement à un appel depuis le callback d'une page.
     */
    private function export_donations_csv( string $status ): void {
        global $wpdb;
        $table_d  = esc_sql( $wpdb->prefix . 'givoly_donations' );
        $table_dn = esc_sql( $wpdb->prefix . 'givoly_donors' );

        if ( $status !== '' ) {
            // Les identifiants de tables ne peuvent pas être des placeholders.
            // Ils proviennent du préfixe WordPress et sont échappés avant interpolation.
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT d.id, d.amount, d.currency, d.status, d.created_at, d.gateway, dn.donor_reference, dn.first_name, dn.last_name, dn.email
                     FROM {$table_d} d
                     LEFT JOIN {$table_dn} dn ON d.donor_id = dn.id
                     WHERE d.status = %s ORDER BY d.created_at DESC",
                    $status
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        } else {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT d.id, d.amount, d.currency, d.status, d.created_at, d.gateway, dn.donor_reference, dn.first_name, dn.last_name, dn.email
                 FROM {$table_d} d
                 LEFT JOIN {$table_dn} dn ON d.donor_id = dn.id
                 ORDER BY d.created_at DESC"
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        if ( ! headers_sent() ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="givoly-dons-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
        }

        $output = fopen( 'php://output', 'w' );
        if ( ! $output ) {
            exit;
        }

        fputcsv( $output, [ 'id', 'date', 'numero_donateur', 'donateur', 'email', 'montant', 'devise', 'statut', 'passerelle' ], ';' );
        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $this->sanitize_csv_value( $row->id ),
                $this->sanitize_csv_value( $row->created_at ),
                $this->sanitize_csv_value( $row->donor_reference ),
                $this->sanitize_csv_value( trim( $row->first_name . ' ' . $row->last_name ) ),
                $this->sanitize_csv_value( $row->email ),
                $this->sanitize_csv_value( $row->amount ),
                $this->sanitize_csv_value( $row->currency ),
                $this->sanitize_csv_value( $row->status ),
                $this->sanitize_csv_value( $row->gateway ),
            ], ';' );
        }
        fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output is the HTTP response stream, not a file managed by WP_Filesystem.
    }

    /**
     * Évite l'injection de formules quand les administrateurs ouvrent l'export CSV.
     */
    private function sanitize_csv_value( mixed $value ): string {
        $value = (string) $value;

        if ( $value !== '' && preg_match( '/^[=+\-@]/', $value ) ) {
            return "'" . $value;
        }

        return $value;
    }

    public function handle_send_yearly_tax_receipts(): void {
        check_admin_referer( 'givoly_send_yearly_tax_receipts' );
        $this->queue_tax_receipts( true );
    }

    public function handle_queue_tax_receipts(): void {
        check_admin_referer( 'givoly_queue_tax_receipts' );
        $this->queue_tax_receipts();
    }

    public function handle_add_manual_donation(): void {
        check_admin_referer( 'givoly_add_manual_donation', 'givoly_manual_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $email          = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $first_name     = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name      = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $amount_raw     = str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ?? '' ) ) );
        $donation_date  = sanitize_text_field( wp_unslash( $_POST['donation_date'] ?? '' ) );
        $payment_method = sanitize_key( wp_unslash( $_POST['payment_method'] ?? '' ) );
        $send_receipt   = '1' === sanitize_text_field( wp_unslash( $_POST['send_receipt'] ?? '' ) );
        $methods        = [ 'virement', 'cheque', 'especes' ];
        $date           = \DateTimeImmutable::createFromFormat( '!Y-m-d', $donation_date );

        $amount_cents = is_numeric( $amount_raw ) ? (int) round( (float) $amount_raw * 100 ) : 0;
        if ( ! is_email( $email ) || '' === $first_name || '' === $last_name || $amount_cents < 100 || $amount_cents > 10_000_000 || ! in_array( $payment_method, $methods, true ) || ! $date || $date->format( 'Y-m-d' ) !== $donation_date ) {
            wp_safe_redirect( add_query_arg( 'givoly_manual_error', '1', admin_url( 'admin.php?page=givoly-manual-donation' ) ) );
            exit;
        }

        try {
            ( new PaymentProcessor() )->process_manual(
                $email,
                $first_name,
                $last_name,
                $amount_cents,
                $donation_date,
                $payment_method,
                $send_receipt
            );
            wp_safe_redirect( add_query_arg( 'givoly_manual_saved', '1', admin_url( 'admin.php?page=givoly-manual-donation' ) ) );
        } catch ( \Throwable $exception ) {
            error_log( '[Givoly] Manual donation error: ' . \Givoly\Core\Format::redact_secrets( $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            wp_safe_redirect( add_query_arg( 'givoly_manual_error', '1', admin_url( 'admin.php?page=givoly-manual-donation' ) ) );
        }
        exit;
    }

    public function handle_update_donor(): void {
        $donor_id = absint( wp_unslash( $_POST['donor_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        check_admin_referer( 'givoly_update_donor_' . $donor_id, 'givoly_update_donor_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $redirect = admin_url( 'admin.php?page=givoly-donors' );
        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $country  = strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ?? 'FR' ) ) );
        $data     = [
            'email'         => $email,
            'first_name'    => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name'     => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
            'company'       => sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) ),
            'address_line1' => sanitize_text_field( wp_unslash( $_POST['address_line1'] ?? '' ) ),
            'address_line2' => sanitize_text_field( wp_unslash( $_POST['address_line2'] ?? '' ) ),
            'postal_code'   => sanitize_text_field( wp_unslash( $_POST['postal_code'] ?? '' ) ),
            'city'          => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
            'country'       => preg_match( '/^[A-Z]{2}$/', $country ) ? $country : 'FR',
            'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'updated_at'    => current_time( 'mysql', true ),
        ];

        if ( ! $donor_id || ! is_email( $email ) || '' === $data['first_name'] || '' === $data['last_name'] ) {
            wp_safe_redirect( add_query_arg( [ 'edit_donor' => $donor_id, 'givoly_donor_update_error' => '1' ], $redirect ) );
            exit;
        }

        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'givoly_donors' );
        $duplicate_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s AND id <> %d LIMIT 1", $email, $donor_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        );

        if ( $duplicate_id ) {
            wp_safe_redirect( add_query_arg( [ 'edit_donor' => $donor_id, 'givoly_donor_update_error' => '1' ], $redirect ) );
            exit;
        }

        $updated = $wpdb->update(
            $table,
            $data,
            [ 'id' => $donor_id ],
            array_fill( 0, count( $data ), '%s' ),
            [ '%d' ]
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( false === $updated ) {
            wp_safe_redirect( add_query_arg( [ 'edit_donor' => $donor_id, 'givoly_donor_update_error' => '1' ], $redirect ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'givoly_donor_updated', '1', $redirect ) );
        exit;
    }

    private function queue_tax_receipts( bool $legacy_action = false ): void {
        check_admin_referer( $legacy_action ? 'givoly_send_yearly_tax_receipts' : 'givoly_queue_tax_receipts' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $year = absint( wp_unslash( $_POST['receipt_year'] ?? 0 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( $year < 2000 || $year > ( (int) gmdate( 'Y' ) + 1 ) ) {
            wp_safe_redirect( add_query_arg( 'givoly_tax_receipts_error', 'invalid_year', admin_url( 'admin.php?page=givoly-donors' ) ) );
            exit;
        }

        $single_donor_id = absint( wp_unslash( $_POST['single_donor_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $donor_ids       = array_values( array_filter( array_map( 'absint', (array) ( $_POST['donor_ids'] ?? [] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $mode            = sanitize_key( $_POST['mode'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( $single_donor_id ) {
            $donor_ids = [ $single_donor_id ];
        } elseif ( ! $legacy_action && 'all' !== $mode && empty( $donor_ids ) ) {
            wp_safe_redirect( add_query_arg( 'givoly_tax_receipts_error', 'empty_selection', admin_url( 'admin.php?page=givoly-donors' ) ) );
            exit;
        }

        $result = TaxReceiptService::enqueue( $year, $donor_ids );
        wp_safe_redirect( add_query_arg( [
            'givoly_tax_receipts_queued' => $result['queued'],
            'givoly_tax_receipts_year'   => $year,
            'givoly_tax_receipts_batch'  => $result['batch_id'],
        ], admin_url( 'admin.php?page=givoly-donors' ) ) );
        exit;
    }
}
