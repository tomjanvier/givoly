<?php
/**
 * Gestionnaire des actions admin POST.
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

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $year = absint( wp_unslash( $_POST['receipt_year'] ?? 0 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( $year < 2000 || $year > ( (int) gmdate( 'Y' ) + 1 ) ) {
            wp_safe_redirect( add_query_arg( 'givoly_tax_receipts_error', 'invalid_year', admin_url( 'admin.php?page=givoly-donors' ) ) );
            exit;
        }

        $donors = $this->get_yearly_receipt_donors( $year );
        if ( empty( $donors ) ) {
            wp_safe_redirect( add_query_arg( [ 'givoly_tax_receipts_sent' => 0, 'givoly_tax_receipts_year' => $year ], admin_url( 'admin.php?page=givoly-donors' ) ) );
            exit;
        }

        $sent = 0;
        foreach ( $donors as $donor ) {
            if ( $this->send_yearly_tax_receipt_email( $donor, $year ) ) {
                $sent++;
            }
        }

        wp_safe_redirect( add_query_arg( [ 'givoly_tax_receipts_sent' => $sent, 'givoly_tax_receipts_year' => $year ], admin_url( 'admin.php?page=givoly-donors' ) ) );
        exit;
    }

    private function get_yearly_receipt_donors( int $year ): array {
        global $wpdb;

        $start    = sprintf( '%d-01-01 00:00:00', $year );
        $end      = sprintf( '%d-01-01 00:00:00', $year + 1 );
        $table_dn = $wpdb->prefix . 'givoly_donors';
        $table_d  = $wpdb->prefix . 'givoly_donations';

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare(
                "SELECT dn.id, dn.first_name, dn.last_name, dn.email, dn.company, dn.address_line1, dn.address_line2, dn.postal_code, dn.city, dn.country, COALESCE(SUM(d.amount), 0) AS total_amount, d.currency, COUNT(d.id) AS donation_count
                 FROM {$table_dn} dn
                 INNER JOIN {$table_d} d ON d.donor_id = dn.id
                 WHERE d.status = 'completed' AND d.created_at >= %s AND d.created_at < %s AND dn.email <> ''
                 GROUP BY dn.id, dn.first_name, dn.last_name, dn.email, dn.company, dn.address_line1, dn.address_line2, dn.postal_code, dn.city, dn.country, d.currency
                 ORDER BY dn.last_name ASC, dn.first_name ASC, dn.email ASC",
                $start,
                $end
            )
        );
    }

    private function send_yearly_tax_receipt_email( object $donor, int $year ): bool {
        if ( ! is_email( $donor->email ) ) {
            return false;
        }

        $association = Settings::get_assoc_name() ?: get_bloginfo( 'name' );
        $sender_name = Settings::get_email_sender_name();
        $from_email  = Settings::get_assoc_email();
        $name        = trim( (string) $donor->first_name . ' ' . (string) $donor->last_name );
        $amount      = number_format_i18n( (float) $donor->total_amount, 2 ) . ' ' . ( $donor->currency ?: 'EUR' );
        $assoc_address = trim( implode( ' ', array_filter( [ Settings::get_assoc_address(), Settings::get_assoc_postal_code(), Settings::get_assoc_city() ] ) ) );
        $variables   = [
            '{donor_name}'          => $name ?: __( 'cher donateur', 'givoly' ),
            '{first_name}'          => (string) $donor->first_name,
            '{last_name}'           => (string) $donor->last_name,
            '{year}'                => (string) $year,
            '{amount}'              => $amount,
            '{donation_count}'      => (string) (int) $donor->donation_count,
            '{association}'         => $association,
            '{association_address}' => $assoc_address ?: __( 'non renseignée', 'givoly' ),
            '{siret}'               => Settings::get_assoc_siret() ?: __( 'non renseigné', 'givoly' ),
            '{rna}'                 => Settings::get_assoc_rna() ?: __( 'non renseigné', 'givoly' ),
            '{fiscal_id}'           => Settings::get_assoc_fiscal_id() ?: __( 'non renseigné', 'givoly' ),
        ];
        $subject     = strtr( Settings::get_email_tax_receipt_subject(), $variables );
        $body        = strtr( Settings::get_email_tax_receipt_body(), $variables );

        $headers = [];
        if ( is_email( $from_email ) ) {
            $headers[] = 'From: ' . $sender_name . ' <' . $from_email . '>';
        }

        return wp_mail( $donor->email, $subject, $body, $headers );
    }
}
