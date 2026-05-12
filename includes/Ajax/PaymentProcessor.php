<?php
/**
 * Traitement d'un paiement complété — logique partagée entre passerelles.
 *
 * Crée le donateur, enregistre le don et émet le reçu fiscal.
 * Utilisé par le webhook Stripe et le webhook HelloAsso.
 *
 * @package Givasso\Ajax
 */

namespace Givasso\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PaymentProcessor {

    /**
     * Enregistre un paiement complété en base et émet le reçu si demandé.
     *
     * Idempotent : un même transaction_id ne peut pas créer deux dons.
     *
     * @param int $campaign_id  ID de la campagne en DB (0 si aucune / ancienne campagne sans table).
     * @param string $campaign  Slug de campagne — conservé dans donor_message pour rétrocompat.
     */
    public function process(
        string $gateway,
        string $transaction_id,
        int    $amount_cents,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign    = '',
        int    $campaign_id = 0
    ): void {
        global $wpdb;

        if ( ! $email || $amount_cents <= 0 ) {
            return;
        }

        // Idempotence : ignorer si ce paiement est déjà enregistré
        $exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}givasso_donations WHERE gateway_transaction_id = %s",
                $transaction_id
            )
        );

        if ( $exists ) {
            return;
        }

        // Créer ou retrouver le donateur
        $donor_id = $this->get_or_create_donor( $email, $first_name, $last_name );

        if ( ! $donor_id ) {
            error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                '[Givasso] WEBHOOK ERREUR — Impossible de créer/retrouver le donateur. '
                . 'Gateway : %s | Transaction : %s | Email : %s',
                $gateway,
                $transaction_id,
                $email
            ) );
            return;
        }

        // Enregistrer le don
        $amount = $amount_cents / 100;

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prefix . 'givasso_donations',
            [
                'donor_id'               => $donor_id,
                'campaign_id'            => $campaign_id > 0 ? $campaign_id : null,
                'amount'                 => $amount,
                'currency'               => strtoupper( $currency ),
                'status'                 => 'completed',
                'gateway'                => $gateway,
                'gateway_transaction_id' => $transaction_id,
                'donor_message'          => $campaign ?: null,
            ],
            [ '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' ]
        );

        $donation_id = (int) $wpdb->insert_id; // phpcs:ignore -- used in future Pro hook
        $this->send_admin_notification( $gateway, $amount, $currency, $email, $first_name, $last_name, $campaign );
    }

    // ── Helpers privés ─────────────────────────────────────────────────────

    private function get_or_create_donor( string $email, string $first_name, string $last_name ): int|false {
        global $wpdb;

        $table = $wpdb->prefix . 'givasso_donors';

        $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix
            $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if ( $existing ) {
            return (int) $existing;
        }

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table,
            [
                'email'      => $email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
            ],
            [ '%s', '%s', '%s' ]
        );

        return $wpdb->insert_id ?: false;
    }

    private function send_admin_notification(
        string $gateway,
        float $amount,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign
    ): void {
        $to = get_option( 'admin_email', '' );
        if ( ! is_email( $to ) ) {
            return;
        }

        $subject = sprintf( __( '[Givasso] Nouveau don : %s %s', 'givasso' ), number_format_i18n( $amount, 2 ), strtoupper( $currency ) );
        $lines   = [
            __( 'Un nouveau don a été reçu sur votre site.', 'givasso' ),
            '',
            sprintf( __( 'Montant : %s %s', 'givasso' ), number_format_i18n( $amount, 2 ), strtoupper( $currency ) ),
            sprintf( __( 'Donateur : %1$s %2$s', 'givasso' ), $first_name, $last_name ),
            sprintf( __( 'Email : %s', 'givasso' ), $email ),
            sprintf( __( 'Passerelle : %s', 'givasso' ), ucfirst( $gateway ) ),
        ];

        if ( $campaign !== '' ) {
            $lines[] = sprintf( __( 'Campagne : %s', 'givasso' ), $campaign );
        }

        wp_mail( $to, $subject, implode( "\n", $lines ) );
    }

}
