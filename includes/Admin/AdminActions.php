<?php
/**
 * Gestionnaire des actions admin POST (remboursement Stripe).
 *
 * @package Givasso\Admin
 */

namespace Givasso\Admin;

use Givasso\Gateway\StripeGateway;
use Givasso\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminActions {

    public function register(): void {
        add_action( 'admin_post_givasso_refund_donation', [ $this, 'handle_refund_donation' ] );
    }

    public function handle_refund_donation(): void {
        $donation_id = (int) ( isset( $_POST['donation_id'] ) ? wp_unslash( $_POST['donation_id'] ) : 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        check_admin_referer( 'givasso_refund_donation_' . $donation_id );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givasso' ) );
        }

        $redirect_base = admin_url( 'admin.php?page=givasso-donations' );

        if ( ! $donation_id ) {
            wp_safe_redirect( add_query_arg( 'givasso_refund_error', '1', $redirect_base ) );
            exit;
        }

        global $wpdb;

        $donation = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id, gateway, status, gateway_refund_ref
                 FROM {$wpdb->prefix}givasso_donations
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
            wp_safe_redirect( add_query_arg( 'givasso_refund_error', '1', $redirect_base ) );
            exit;
        }

        try {
            $gateway = new StripeGateway( Settings::get_stripe_secret_key() );
            $gateway->refund( $donation['gateway_refund_ref'] );

            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'givasso_donations',
                [ 'status' => 'refunded' ],
                [ 'id'     => $donation_id ],
                [ '%s' ],
                [ '%d' ]
            );

            wp_safe_redirect( add_query_arg( 'givasso_refunded', '1', $redirect_base ) );

        } catch ( \RuntimeException $e ) {
            error_log( '[Givasso] Erreur remboursement don #' . $donation_id . ' : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            wp_safe_redirect( add_query_arg( 'givasso_refund_error', '1', $redirect_base ) );
        }

        exit;
    }
}
