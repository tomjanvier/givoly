<?php
/**
 * Gestionnaire des actions admin POST (remboursement Stripe).
 *
 * @package Givoly\Admin
 */

namespace Givoly\Admin;

use Givoly\Gateway\StripeGateway;
use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminActions {

    public function register(): void {
        add_action( 'admin_post_givoly_refund_donation', [ $this, 'handle_refund_donation' ] );
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
}
