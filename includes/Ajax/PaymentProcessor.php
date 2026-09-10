<?php
/**
 * Traitement d'un paiement complété — logique partagée entre passerelles.
 *
 * Crée le donor, enregistre le don.
 * Utilisé par le webhook Stripe et le webhook HelloAsso.
 *
 * @package Givoly\Ajax
 */

namespace Givoly\Ajax;

use Givoly\Donor\DonorReference;
use Givoly\Mail\MailQueue;
use Givoly\Repository\CampaignRepository;

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
     *                          Le message réellement saisi par le donor est lu depuis la
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
        string $post_payment_token = '',
        string $stripe_customer_id = '',
        string $stripe_subscription_id = '',
        string $gateway_refund_ref = ''
    ): void {
        global $wpdb;

        if ( ! $email || $amount_cents <= 0 || $transaction_id === '' ) {
            return;
        }

        // Idempotence : Stripe peut notifier le même paiement via Checkout puis
        // invoice.payment_succeeded. La référence du payment intent permet de
        // reconnaître ces deux notifications comme un seul don.
        $exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}givoly_donations
                 WHERE gateway = %s
                 AND (
                    gateway_transaction_id = %s
                    OR ( gateway_refund_ref <> '' AND %s <> '' AND gateway_refund_ref = %s )
                 )
                 LIMIT 1",
                $gateway,
                $transaction_id,
                $gateway_refund_ref,
                $gateway_refund_ref
            )
        );

        if ( $exists ) {
            return;
        }

        // Créer ou retrouver le donor
        $donor_id = $this->get_or_create_donor( $email, $first_name, $last_name );

        if ( ! $donor_id ) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de créer ou retrouver le donor. Gateway : %s | Transaction : %s',
                    esc_html( $gateway ),
                    esc_html( $transaction_id )
                )
            ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->update_stripe_identifiers( $donor_id, $stripe_customer_id, $stripe_subscription_id );

        // Rattachement exact de l'abonnement : un donateur peut en avoir plusieurs.
        // La colonne historique donors.stripe_subscription_id est conservée pour
        // compatibilité, mais l'annulation utilise désormais cette entité dédiée
        // et la colonne donations.stripe_subscription_id ci-dessous.
        $stripe_subscription_id = sanitize_text_field( $stripe_subscription_id );
        if ( $gateway === 'stripe' && $stripe_subscription_id !== '' ) {
            ( new \Givoly\Repository\SubscriptionRepository() )->upsert(
                $donor_id,
                $stripe_subscription_id,
                $stripe_customer_id,
                $currency,
                $campaign_id
            );
        } elseif ( $gateway === 'stripe' ) {
            $stripe_subscription_id = '';
        } else {
            // Seul Stripe porte des abonnements : aucun rattachement inventé.
            $stripe_subscription_id = '';
        }

        // Le message du donor est transporté dans la transient de profil
        // (liée au post_payment_token) : on le lit avant que le profil ne soit consommé.
        $donor_notes = $this->get_pending_donor_message( $post_payment_token );

        $this->apply_pending_donor_profile( $donor_id, $post_payment_token );

        // Save le don
        $amount = $amount_cents / 100;
        $currency = strtoupper( sanitize_text_field( $currency ) );
        if ( ! in_array( $currency, \Givoly\Form\FormConfig::SUPPORTED_CURRENCIES, true ) ) {
            $currency = 'EUR';
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prefix . 'givoly_donations',
            [
                'donor_id'               => $donor_id,
                'campaign_id'            => $campaign_id > 0 ? $campaign_id : null,
                'amount'                 => $amount,
                'currency'               => $currency,
                'status'                 => 'completed',
                'gateway'                => $gateway,
                'gateway_transaction_id' => $transaction_id,
                'gateway_refund_ref'     => $gateway_refund_ref !== '' ? $gateway_refund_ref : null,
                'stripe_subscription_id' => $stripe_subscription_id !== '' ? $stripe_subscription_id : null,
                'post_payment_token'     => $post_payment_token !== '' ? $post_payment_token : null,
                'donor_message'          => $campaign ?: null,
                'donor_notes'            => $donor_notes !== '' ? $donor_notes : null,
                'created_at'             => current_time( 'mysql', true ),
                'updated_at'             => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted && $this->is_duplicate_entry_error( $wpdb->last_error ) ) {
            return;
        }

        if ( false === $inserted && str_contains( strtolower( (string) $wpdb->last_error ), 'stripe_subscription_id' ) ) {
            // Compatibilité transitoire : la migration v2.2 n'a pas encore ajouté
            // la colonne de rattachement. On enregistre sans elle plutôt que de perdre le don.
            $wpdb->last_error = '';
            $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prefix . 'givoly_donations',
                [
                    'donor_id'               => $donor_id,
                    'campaign_id'            => $campaign_id > 0 ? $campaign_id : null,
                    'amount'                 => $amount,
                    'currency'               => $currency,
                    'status'                 => 'completed',
                    'gateway'                => $gateway,
                    'gateway_transaction_id' => $transaction_id,
                    'gateway_refund_ref'     => $gateway_refund_ref !== '' ? $gateway_refund_ref : null,
                    'post_payment_token'     => $post_payment_token !== '' ? $post_payment_token : null,
                    'donor_message'          => $campaign ?: null,
                    'donor_notes'            => $donor_notes !== '' ? $donor_notes : null,
                    'created_at'             => current_time( 'mysql', true ),
                    'updated_at'             => current_time( 'mysql', true ),
                ],
                [ '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
            );
            if ( false === $inserted && $this->is_duplicate_entry_error( $wpdb->last_error ) ) {
                return;
            }
        }

        if ( false === $inserted ) {
            // Ne jamais exposer de secret : last_error ne contient que du SQL.
            throw new \RuntimeException( 'Unable to save the donation in the database.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $donation_id = (int) $wpdb->insert_id;

        if ( $donation_id > 0 ) {
            CampaignRepository::flush_stats_cache();

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

            // Le placement après l'idempotence et les traitements internes
            // garantit un déclenchement unique sans interrompre les emails.
            $this->fire_donation_completed(
                donation_id:    $donation_id,
                gateway:        $gateway,
                transaction_id: $transaction_id,
                email:          $email,
                first_name:     $first_name,
                last_name:      $last_name,
                amount_cents:   $amount_cents,
                currency:       strtoupper( $currency ),
                campaign:       $campaign,
                occurred_at:    gmdate( 'Y-m-d\TH:i:s\Z' )
            );
        }
    }

    /**
     * Enregistre un don saisi manuellement par un administrateur.
     */
    public function process_manual(
        string $email,
        string $first_name,
        string $last_name,
        int $amount_cents,
        string $date,
        string $payment_method,
        bool $send_receipt = false
    ): int {
        global $wpdb;

        $donor_id = $this->get_or_create_donor( $email, $first_name, $last_name );
        if ( ! $donor_id ) {
            throw new \RuntimeException( 'Unable to create the manual donor.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->update_donor_name( (int) $donor_id, $first_name, $last_name );
        $created_at     = get_gmt_from_date( $date . ' 12:00:00' );
        $gateway        = 'manual_' . sanitize_key( $payment_method );
        $transaction_id = 'manual-' . wp_generate_uuid4();

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donations',
            [
                'donor_id'               => (int) $donor_id,
                'amount'                 => $amount_cents / 100,
                'currency'               => 'EUR',
                'status'                 => 'completed',
                'gateway'                => $gateway,
                'gateway_transaction_id' => $transaction_id,
                'created_at'             => $created_at,
                'updated_at'             => current_time( 'mysql', true ),
            ],
            [ '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            throw new \RuntimeException( 'Unable to save the manual donation.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $donation_id = (int) $wpdb->insert_id;

        CampaignRepository::flush_stats_cache();

        $payload = [
            'donation_id' => $donation_id,
            'email'       => $email,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'amount'      => $amount_cents / 100,
            'currency'    => 'EUR',
            'campaign'    => '',
        ];
        MailQueue::enqueue( 'donation_admin', $payload, (string) get_option( 'admin_email' ) );
        MailQueue::enqueue( 'donation_thank', $payload, $email );

        if ( $send_receipt ) {
            \Givoly\Mail\TaxReceiptService::enqueue( (int) gmdate( 'Y', strtotime( $date ) ), [ (int) $donor_id ] );
        }

        // La date exposée reflète le choix de l'administrateur, pas la saisie.
        $this->fire_donation_completed(
            donation_id:    $donation_id,
            gateway:        $gateway,
            transaction_id: $transaction_id,
            email:          $email,
            first_name:     $first_name,
            last_name:      $last_name,
            amount_cents:   $amount_cents,
            currency:       'EUR',
            campaign:       '',
            occurred_at:    gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $created_at ) )
        );

        return $donation_id;
    }

    /**
     * Publie une représentation stable du don pour les intégrations tierces.
     */
    private function fire_donation_completed(
        int $donation_id,
        string $gateway,
        string $transaction_id,
        string $email,
        string $first_name,
        string $last_name,
        int $amount_cents,
        string $currency,
        string $campaign,
        string $occurred_at
    ): void {
        $donation = [
            'donation_id'    => $donation_id,
            'gateway'        => $gateway,
            'transaction_id' => $transaction_id,
            'email'          => $email,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'amount_cents'   => $amount_cents,
            'currency'       => $currency,
            'campaign'       => $campaign,
            'occurred_at'    => $occurred_at,
        ];

        /**
         * Déclenchée après l'enregistrement d'un don confirmé et les contrôles
         * d'idempotence, quelle que soit la passerelle utilisée.
         *
         * @since 1.5.0
         *
         * @param array<string,int|string> $donation Don normalisé destiné aux intégrations.
         */
        do_action( 'givoly_donation_completed', $donation );
    }

    // ── Fonctions privées ──────────────────────────────────────────────────

    private function get_or_create_donor( string $email, string $first_name, string $last_name ): int|false {
        global $wpdb;

        $table = $wpdb->prefix . 'givoly_donors';

        $existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix
            $wpdb->prepare( "SELECT id, donor_reference, created_at FROM {$table} WHERE email = %s", $email ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        if ( $existing ) {
            $this->ensure_donor_reference(
                (int) $existing['id'],
                $first_name,
                (string) ( $existing['created_at'] ?? '' ),
                (string) ( $existing['donor_reference'] ?? '' )
            );
            return (int) $existing['id'];
        }

        $created_at = current_time( 'mysql', true );
        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table,
            [
                'email'           => $email,
                'donor_reference' => DonorReference::generate( $first_name, $created_at ),
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'created_at'      => $created_at,
                'updated_at'      => $created_at,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted && $this->is_duplicate_entry_error( $wpdb->last_error ) ) {
            $existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix
                $wpdb->prepare( "SELECT id, donor_reference, created_at FROM {$table} WHERE email = %s", $email ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                ARRAY_A
            );

            if ( $existing ) {
                $this->ensure_donor_reference(
                    (int) $existing['id'],
                    $first_name,
                    (string) ( $existing['created_at'] ?? '' ),
                    (string) ( $existing['donor_reference'] ?? '' )
                );
                return (int) $existing['id'];
            }

            throw new \RuntimeException( 'A duplicate donor entry was detected, but the existing donor could not be found.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( false === $inserted ) {
            throw new \RuntimeException( 'Unable to save the donor in the database: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $wpdb->insert_id ?: false;
    }

    private function ensure_donor_reference( int $donor_id, string $first_name, string $created_at, string $reference ): void {
        if ( $reference !== '' ) {
            return;
        }

        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donors',
            [ 'donor_reference' => DonorReference::generate( $first_name, $created_at ) ],
            [ 'id' => $donor_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    private function is_duplicate_entry_error( string $error ): bool {
        return str_contains( strtolower( $error ), 'duplicate entry' );
    }

    private function update_donor_name( int $donor_id, string $first_name, string $last_name ): void {
        global $wpdb;

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donors',
            [
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $donor_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    private function update_stripe_identifiers( int $donor_id, string $customer_id, string $subscription_id ): void {
        global $wpdb;

        $data = array_filter(
            [
                'stripe_customer_id'     => sanitize_text_field( $customer_id ),
                'stripe_subscription_id' => sanitize_text_field( $subscription_id ),
            ],
            static fn( string $value ): bool => $value !== ''
        );
        if ( ! $data ) {
            return;
        }

        $data['updated_at'] = current_time( 'mysql', true );
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donors',
            $data,
            [ 'id' => $donor_id ],
            array_fill( 0, count( $data ), '%s' ),
            [ '%d' ]
        );
    }

    /**
     * Lit le message saisi par le donor depuis le profil en attente.
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
            throw new \RuntimeException( 'Unable to update the donor profile: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        delete_transient( 'givoly_checkout_profile_' . $post_payment_token );
    }

}
