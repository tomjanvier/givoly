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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminActions {

    public function register(): void {
        add_action( 'admin_post_givoly_refund_donation', [ $this, 'handle_refund_donation' ] );
        add_action( 'admin_post_givoly_export_donations', [ $this, 'handle_export_donations' ] );
        add_action( 'admin_post_givoly_queue_tax_receipts', [ $this, 'handle_queue_tax_receipts' ] );
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
        $table_d  = $wpdb->prefix . 'givoly_donations';
        $table_dn = $wpdb->prefix . 'givoly_donors';
        $sql      = "SELECT d.id, d.amount, d.currency, d.status, d.created_at, d.gateway, dn.first_name, dn.last_name, dn.email
                     FROM {$table_d} d
                     LEFT JOIN {$table_dn} dn ON d.donor_id = dn.id";

        if ( $status !== '' ) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare( $sql . ' WHERE d.status = %s ORDER BY d.created_at DESC', $status ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            );
        } else {
            $rows = $wpdb->get_results( $sql . ' ORDER BY d.created_at DESC' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( ! headers_sent() ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="givoly-dons-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
        }

        $output = fopen( 'php://output', 'w' );
        if ( ! $output ) {
            exit;
        }

        fputcsv( $output, [ 'id', 'date', 'donateur', 'email', 'montant', 'devise', 'statut', 'passerelle' ], ';' );
        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $this->sanitize_csv_value( $row->id ),
                $this->sanitize_csv_value( $row->created_at ),
                $this->sanitize_csv_value( trim( $row->first_name . ' ' . $row->last_name ) ),
                $this->sanitize_csv_value( $row->email ),
                $this->sanitize_csv_value( $row->amount ),
                $this->sanitize_csv_value( $row->currency ),
                $this->sanitize_csv_value( $row->status ),
                $this->sanitize_csv_value( $row->gateway ),
            ], ';' );
        }
        fclose( $output );
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

    private function queue_tax_receipts( bool $legacy_action = false ): void {
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
