<?php
/**
 * Traitement d'un paiement complété — logique partagée entre passerelles.
 *
 * Crée le donateur, enregistre le don.
 * Utilisé par le webhook Stripe et le webhook HelloAsso.
 *
 * @package Givoly\Ajax
 */

namespace Givoly\Ajax;

use Givoly\Mail\MailQueue;

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
     *                          Le message réellement saisi par le donateur est lu depuis la
     *                          transient de profil (post_payment_token) et stocké dans donor_notes.
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
        int    $campaign_id = 0,
        string $post_payment_token = ''
    ): void {
        global $wpdb;

        if ( ! $email || $amount_cents <= 0 || $transaction_id === '' ) {
            return;
        }

        // Idempotence : ignorer si ce paiement est déjà enregistré
        $exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}givoly_donations WHERE gateway = %s AND gateway_transaction_id = %s",
                $gateway,
                $transaction_id
            )
        );

        if ( $exists ) {
            return;
        }

        // Créer ou retrouver le donateur
        $donor_id = $this->get_or_create_donor( $email, $first_name, $last_name );

        if ( ! $donor_id ) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de créer ou retrouver le donateur. Gateway : %s | Transaction : %s | Email : %s',
                    esc_html( $gateway ),
                    esc_html( $transaction_id ),
                    esc_html( $email )
                )
            ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        // Le message du donateur est transporté dans la transient de profil
        // (liée au post_payment_token) : on le lit avant que le profil ne soit consommé.
        $donor_notes = $this->get_pending_donor_message( $post_payment_token );

        $this->apply_pending_donor_profile( $donor_id, $post_payment_token );

        // Enregistrer le don
        $amount = $amount_cents / 100;

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prefix . 'givoly_donations',
            [
                'donor_id'               => $donor_id,
                'campaign_id'            => $campaign_id > 0 ? $campaign_id : null,
                'amount'                 => $amount,
                'currency'               => strtoupper( $currency ),
                'status'                 => 'completed',
                'gateway'                => $gateway,
                'gateway_transaction_id' => $transaction_id,
                'post_payment_token'     => $post_payment_token !== '' ? $post_payment_token : null,
                'donor_message'          => $campaign ?: null,
                'donor_notes'            => $donor_notes !== '' ? $donor_notes : null,
                'created_at'             => current_time( 'mysql', true ),
                'updated_at'             => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted && $this->is_duplicate_entry_error( $wpdb->last_error ) ) {
            return;
        }

        if ( false === $inserted ) {
            throw new \RuntimeException( 'Impossible d’enregistrer le don en base de données : ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $donation_id = (int) $wpdb->insert_id;

        if ( $donation_id > 0 ) {
            $payload = [
                'donation_id' => $donation_id,
                'email'       => $email,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'amount'      => $amount,
                'currency'    => strtoupper( $currency ),
                'campaign'    => $campaign,
            ];

            // Les webhooks restent rapides : SMTP est traité par WP-Cron.
            MailQueue::enqueue( 'donation_admin', $payload, (string) get_option( 'admin_email' ) );
            MailQueue::enqueue( 'donation_thank', $payload, $email );
        }
    }

// ── Helpers privés ─────────────────────────────────────────────────────

    private function get_or_create_donor( string $email, string $first_name, string $last_name ): int|false {
        global $wpdb;

        $table = $wpdb->prefix . 'givoly_donors';

        $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix
            $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if ( $existing ) {
            return (int) $existing;
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table,
            [
                'email'      => $email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'created_at' => current_time( 'mysql', true ),
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted && $this->is_duplicate_entry_error( $wpdb->last_error ) ) {
            $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix
                $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );

            if ( $existing ) {
                return (int) $existing;
            }

            throw new \RuntimeException( 'Entrée donateur dupliquée détectée, mais impossible de retrouver le donateur existant.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( false === $inserted ) {
            throw new \RuntimeException( 'Impossible d’enregistrer le donateur en base de données : ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $wpdb->insert_id ?: false;
    }

    private function is_duplicate_entry_error( string $error ): bool {
        return str_contains( strtolower( $error ), 'duplicate entry' );
    }

    /**
     * Lit le message saisi par le donateur depuis le profil en attente.
     * Ne supprime pas la transient (elle est consommée par apply_pending_donor_profile()).
     */
    private function get_pending_donor_message( string $post_payment_token ): string {
        if ( $post_payment_token === '' ) {
            return '';
        }

        $profile = get_transient( 'givoly_checkout_profile_' . $post_payment_token );
        if ( ! is_array( $profile ) ) {
            return '';
        }

        return sanitize_textarea_field( (string) ( $profile['message'] ?? '' ) );
    }

    private function apply_pending_donor_profile( int $donor_id, string $post_payment_token ): void {
        global $wpdb;

        if ( $post_payment_token === '' ) {
            return;
        }

        $profile = get_transient( 'givoly_checkout_profile_' . $post_payment_token );
        if ( ! is_array( $profile ) || ! $profile ) {
            return;
        }

        $allowed = array_filter(
            [
                'phone'         => isset( $profile['phone'] ) ? sanitize_text_field( (string) $profile['phone'] ) : '',
                'company'       => isset( $profile['company'] ) ? sanitize_text_field( (string) $profile['company'] ) : '',
                'address_line1' => isset( $profile['address_line1'] ) ? sanitize_text_field( (string) $profile['address_line1'] ) : '',
                'postal_code'   => isset( $profile['postal_code'] ) ? sanitize_text_field( (string) $profile['postal_code'] ) : '',
                'city'          => isset( $profile['city'] ) ? sanitize_text_field( (string) $profile['city'] ) : '',
            ],
            static fn( string $value ): bool => $value !== ''
        );

        if ( ! $allowed ) {
            delete_transient( 'givoly_checkout_profile_' . $post_payment_token );
            return;
        }

        $allowed['updated_at'] = current_time( 'mysql', true );

        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donors',
            $allowed,
            [ 'id' => $donor_id ]
        );

        if ( false === $updated ) {
            throw new \RuntimeException( 'Impossible de mettre à jour le profil donateur : ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        delete_transient( 'givoly_checkout_profile_' . $post_payment_token );
    }

}
