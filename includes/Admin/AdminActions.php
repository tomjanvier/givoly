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
        add_action( 'admin_post_givoly_queue_tax_receipt', [ $this, 'handle_queue_tax_receipt' ] );
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
            error_log( '[Givoly] Erreur remboursement don #' . $donation_id . ' : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            wp_safe_redirect( add_query_arg( 'givoly_refund_error', '1', $redirect_base ) );
        }

        exit;
    }

    public function handle_send_yearly_tax_receipts(): void {
        check_admin_referer( 'givoly_send_yearly_tax_receipts' );
        $this->queue_tax_receipts( true );
    }

    public function handle_queue_tax_receipt(): void {
        $donation_year = absint( wp_unslash( $_POST['receipt_year'] ?? 0 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        check_admin_referer( 'givoly_queue_tax_receipt_' . $donation_year . '_' . absint( $_POST['donor_id'] ?? 0 ) );
        $this->queue_tax_receipts();
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
