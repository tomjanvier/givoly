<?php
/**
 * Gestionnaire des requêtes AJAX et REST du plugin.
 *
 * Actions enregistrées :
 * - AJAX  : givoly_init_checkout          → Crée la session de paiement (Stripe ou HelloAsso)
 * - REST  : POST /givoly/v1/webhook        → Reçoit les événements Stripe
 * - REST  : POST /givoly/v1/helloasso-webhook → Reçoit les événements HelloAsso
 *
 * @package Givoly\Ajax
 */

namespace Givoly\Ajax;

use Givoly\Admin\Settings;
use Givoly\Gateway\StripeGateway;
use Givoly\Gateway\HelloAssoGateway;
use Givoly\Repository\CampaignRepository;
use Givoly\Security\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AjaxHandler {

    public function register(): void {
        add_action( 'wp_ajax_givoly_init_checkout',        [ $this, 'handle_checkout' ] );
        add_action( 'wp_ajax_nopriv_givoly_init_checkout', [ $this, 'handle_checkout' ] );
        add_action( 'wp_ajax_givoly_form_preview',         [ $this, 'handle_form_preview' ] );
        add_action( 'wp_ajax_givoly_save_post_payment_details',        [ $this, 'handle_post_payment_details' ] );
        add_action( 'wp_ajax_nopriv_givoly_save_post_payment_details', [ $this, 'handle_post_payment_details' ] );

        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    public function register_rest_routes(): void {
        register_rest_route( 'givoly/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_stripe_webhook' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'givoly/v1', '/helloasso-webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_helloasso_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ── Form preview (admin-only, nonce-protected) ─────────────────────────

    public function handle_form_preview(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '', '', 403 );
        }
        check_ajax_referer( 'givoly_form_preview' );

        $preview_config = new \Givoly\Form\FormConfig( [
            'theme'      => 'givoly',
            'layout'     => 'card',
            'show_title' => 'yes',
            'title'      => esc_html__( 'Soutenez-nous', 'givoly' ),
        ] );

        wp_enqueue_style( 'givoly-frontend', GIVOLY_PLUGIN_URL . 'assets/css/givoly-frontend.css', [], GIVOLY_VERSION );
        wp_add_inline_style( 'givoly-frontend', 'body{margin:0;padding:24px;background:#f0f0f1;font-family:system-ui,sans-serif;}' );

        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">';
        wp_print_styles( 'givoly-frontend' );
        echo '</head><body>';
        ( new \Givoly\Form\DonationForm( $preview_config ) )->output();
        echo '</body></html>';
        exit;
    }

    // ── Checkout ───────────────────────────────────────────────────────────

    public function handle_checkout(): void {
        if ( ! check_ajax_referer( 'givoly_submit_donation', 'givoly_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Requête invalide.', 'givoly' ) ], 403 );
        }

        if ( ! RateLimiter::is_allowed( 'checkout' ) ) {
            wp_send_json_error(
                [ 'message' => __( 'Trop de tentatives. Veuillez patienter une minute avant de réessayer.', 'givoly' ) ],
                429
            );
        }

        $amount_raw  = sanitize_text_field( wp_unslash( $_POST['amount'] ?? '' ) );
        $currency    = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'EUR' ) ) );
        $email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $first_name  = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name   = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $campaign    = sanitize_text_field( wp_unslash( $_POST['campaign'] ?? '' ) );
        $gateway_key = sanitize_text_field( wp_unslash( $_POST['gateway'] ?? Settings::get_default_gateway() ) );
        $gateway_key = in_array( $gateway_key, [ 'stripe', 'helloasso' ], true ) ? $gateway_key : Settings::get_default_gateway();
        $frequency   = sanitize_text_field( wp_unslash( $_POST['frequency'] ?? 'once' ) );
        $frequency   = in_array( $frequency, [ 'once', 'monthly' ], true ) ? $frequency : 'once';

        $amount_cents = $this->parse_amount_to_cents( $amount_raw );

        if ( $amount_cents < 100 || $amount_cents > 100_000 * 100 ) {
            wp_send_json_error( [ 'message' => __( 'Montant invalide.', 'givoly' ) ], 422 );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Email invalide.', 'givoly' ) ], 422 );
        }

        $post_payment_token = $this->generate_post_payment_token();

        try {
            if ( ! in_array( $gateway_key, Settings::get_enabled_gateways(), true ) ) {
                throw new \RuntimeException( 'Passerelle désactivée.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            $this->store_pending_donor_profile( $post_payment_token );

            if ( $gateway_key === 'helloasso' ) {
                $checkout_url = $this->checkout_helloasso(
                    $amount_cents, $currency, $email, $first_name, $last_name, $campaign, $frequency, $post_payment_token
                );
            } else {
                $checkout_url = $this->checkout_stripe(
                    $amount_cents, $currency, $email, $first_name, $last_name, $campaign, $frequency, $post_payment_token
                );
            }

            wp_send_json_success( [ 'checkout_url' => $checkout_url ] );

        } catch ( \RuntimeException $e ) {
            error_log( '[Givoly] Checkout error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            wp_send_json_error( [ 'message' => __( 'Erreur lors de la création du paiement. Veuillez réessayer.', 'givoly' ) ], 500 );
        }
    }

    public function handle_post_payment_details(): void {
        global $wpdb;

        if ( ! check_ajax_referer( 'givoly_submit_donation', 'givoly_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Requête invalide.', 'givoly' ) ], 403 );
        }

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $token = $this->sanitize_post_payment_token( wp_unslash( $_POST['post_payment_token'] ?? '' ) );

        if ( ! is_email( $email ) || $token === '' ) {
            wp_send_json_error( [ 'message' => __( 'Email invalide.', 'givoly' ) ], 422 );
        }

        $record = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT d.id AS donation_id, d.donor_id, dn.email
                 FROM {$wpdb->prefix}givoly_donations d
                 INNER JOIN {$wpdb->prefix}givoly_donors dn ON dn.id = d.donor_id
                 WHERE d.post_payment_token = %s AND d.status = 'completed'
                 LIMIT 1",
                $token
            ),
            ARRAY_A
        );

        if ( ! $record ) {
            wp_send_json_error( [ 'message' => __( 'Session post-paiement invalide ou expirée.', 'givoly' ) ], 403 );
        }

        if ( strtolower( $email ) !== strtolower( (string) $record['email'] ) ) {
            wp_send_json_error( [ 'message' => __( 'L’email saisi ne correspond pas au paiement.', 'givoly' ) ], 422 );
        }

        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prefix . 'givoly_donors',
            [
                'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) ?: null,
                'company'       => sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) ) ?: null,
                'address_line1' => sanitize_text_field( wp_unslash( $_POST['address_line1'] ?? '' ) ) ?: null,
                'postal_code'   => sanitize_text_field( wp_unslash( $_POST['postal_code'] ?? '' ) ) ?: null,
                'city'          => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ) ?: null,
            ],
            [ 'id' => (int) $record['donor_id'] ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $updated === false ) {
            wp_send_json_error( [ 'message' => __( 'Impossible d’enregistrer les informations.', 'givoly' ) ], 500 );
        }

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donations',
            [ 'post_payment_token' => null ],
            [ 'id' => (int) $record['donation_id'] ],
            [ '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => __( 'Merci, vos informations ont bien été enregistrées.', 'givoly' ) ] );
    }


    private function store_pending_donor_profile( string $post_payment_token ): void {
        $profile = array_filter(
            [
                'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
                'company'       => sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) ),
                'address_line1' => sanitize_text_field( wp_unslash( $_POST['address_line1'] ?? '' ) ),
                'postal_code'   => sanitize_text_field( wp_unslash( $_POST['postal_code'] ?? '' ) ),
                'city'          => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
            ],
            static fn( string $value ): bool => $value !== ''
        );

        if ( $profile ) {
            set_transient( 'givoly_checkout_profile_' . $post_payment_token, $profile, DAY_IN_SECONDS );
        }
    }

    private function checkout_stripe(
        int    $amount_cents,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign,
        string $frequency = 'once',
        string $post_payment_token = ''
    ): string {
        if ( ! Settings::is_configured() ) {
            throw new \RuntimeException( 'Stripe non configuré.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $gateway     = new StripeGateway( Settings::get_stripe_secret_key() );
        $success_url = add_query_arg(
            [
                'session_id'      => 'CHECKOUT_SESSION_ID',
                'givoly_success'  => '1',
                'givoly_token'    => $post_payment_token,
            ],
            Settings::get_success_url()
        );
        $success_url = str_replace( 'CHECKOUT_SESSION_ID', '{CHECKOUT_SESSION_ID}', $success_url );
        $cancel_url  = Settings::get_cancel_url();

        return $gateway->create_checkout_session(
            amount_cents:     $amount_cents,
            currency:         $currency,
            donor_email:      $email,
            donor_first_name: $first_name,
            donor_last_name:  $last_name,
            success_url:      $success_url,
            cancel_url:       $cancel_url,
            campaign:         $campaign,
            frequency:        $frequency,
            post_payment_token: $post_payment_token
        );
    }

    private function checkout_helloasso(
        int    $amount_cents,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign,
        string $frequency = 'once',
        string $post_payment_token = ''
    ): string {
        if ( ! Settings::is_helloasso_configured() ) {
            throw new \RuntimeException( 'HelloAsso non configuré.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $gateway = new HelloAssoGateway(
            Settings::get_helloasso_client_id(),
            Settings::get_helloasso_client_secret(),
            Settings::get_helloasso_org_slug(),
            Settings::is_helloasso_sandbox()
        );

        if ( Settings::should_use_helloasso_other_payments_for_once() ) {
            $other_payments_url = Settings::get_helloasso_other_payments_url();
            if ( $other_payments_url ) {
                return $other_payments_url;
            }
            throw new \RuntimeException( 'Lien HelloAsso pour dons uniques non configuré.' );
        }

        $item_name = $campaign ?: __( 'Don', 'givoly' );

        return $gateway->create_checkout_intent(
            amount_cents:     $amount_cents,
            item_name:        $item_name,
            donor_email:      $email,
            donor_first_name: $first_name,
            donor_last_name:  $last_name,
            return_url:       add_query_arg(
                [
                    'givoly_success' => '1',
                    'givoly_token'   => $post_payment_token,
                ],
                Settings::get_success_url()
            ),
            back_url:         Settings::get_cancel_url(),
            error_url:        Settings::get_cancel_url(),
            campaign:         $campaign,
            frequency:        $frequency,
            post_payment_token: $post_payment_token
        );
    }

    // ── Webhook Stripe ─────────────────────────────────────────────────────

    public function handle_stripe_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $payload   = $request->get_body();
        $signature = $request->get_header( 'stripe-signature' );
        $secret    = Settings::get_webhook_secret();

        if ( ! $signature || ! $secret ) {
            return new \WP_REST_Response( [ 'error' => 'Configuration manquante.' ], 400 );
        }

        try {
            $gateway = new StripeGateway( Settings::get_stripe_secret_key() );
            $event   = $gateway->verify_webhook( $payload, $signature, $secret );
        } catch ( \RuntimeException $e ) {
            return new \WP_REST_Response( [ 'error' => 'Signature invalide.' ], 400 );
        }

        $event_type = $event['type'] ?? '';

        if ( $event_type === 'checkout.session.completed' ) {
            $this->handle_checkout_session_completed( $event['data']['object'] ?? [] );
        } elseif ( $event_type === 'invoice.payment_succeeded' ) {
            $this->handle_invoice_payment_succeeded( $event['data']['object'] ?? [] );
        } elseif ( $event_type === 'charge.refunded' ) {
            $this->handle_charge_refunded( $event['data']['object'] ?? [] );
        }

        return new \WP_REST_Response( [ 'received' => true ], 200 );
    }

    private function handle_checkout_session_completed( array $session ): void {
        global $wpdb;

        $meta           = $session['metadata'] ?? [];
        $email          = sanitize_email( $meta['donor_email'] ?? '' );
        $first_name     = sanitize_text_field( $meta['donor_first_name'] ?? '' );
        $last_name      = sanitize_text_field( $meta['donor_last_name'] ?? '' );
        $campaign       = sanitize_text_field( $meta['campaign'] ?? '' );
        $currency       = strtoupper( sanitize_text_field( $meta['currency'] ?? 'EUR' ) );
        $amount_cents   = (int) ( $session['amount_total'] ?? 0 );
        $transaction_id = sanitize_text_field( $session['id'] ?? '' );
        $post_payment_token = $this->sanitize_post_payment_token( $meta['post_payment_token'] ?? '' );

        $campaign_id = $campaign
            ? ( ( new CampaignRepository() )->find_by_slug( $campaign )?->get_id() ?? 0 )
            : 0;

        ( new PaymentProcessor() )->process(
            gateway:        'stripe',
            transaction_id: $transaction_id,
            amount_cents:   $amount_cents,
            currency:       $currency,
            email:          $email,
            first_name:     $first_name,
            last_name:      $last_name,
            campaign:       $campaign,
            campaign_id:    $campaign_id,
            post_payment_token: $post_payment_token
        );

        // Stocker la référence de remboursement gateway (Stripe: payment_intent_id)
        $payment_intent_id = sanitize_text_field( $session['payment_intent'] ?? '' );
        if ( $payment_intent_id && $transaction_id ) {
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'givoly_donations',
                [ 'gateway_refund_ref' => $payment_intent_id ],
                [ 'gateway_transaction_id'   => $transaction_id ],
                [ '%s' ],
                [ '%s' ]
            );
        }

    }

    /**
     * Enregistre les paiements récurrents Stripe après le premier passage Checkout.
     *
     * Le premier paiement d'un abonnement est déjà traité par checkout.session.completed.
     * Les échéances suivantes arrivent via invoice.payment_succeeded avec billing_reason
     * subscription_cycle. On les stocke avec l'ID de facture pour conserver
     * l'idempotence de chaque échéance.
     */
    private function handle_invoice_payment_succeeded( array $invoice ): void {
        $billing_reason = (string) ( $invoice['billing_reason'] ?? '' );

        if ( $billing_reason !== 'subscription_cycle' ) {
            return;
        }

        $subscription_details = is_array( $invoice['subscription_details'] ?? null ) ? $invoice['subscription_details'] : [];
        $lines                = is_array( $invoice['lines']['data'] ?? null ) ? $invoice['lines']['data'] : [];
        $first_line           = is_array( $lines[0] ?? null ) ? $lines[0] : [];
        $meta                 = $invoice['metadata'] ?? [];

        if ( ! $meta && is_array( $subscription_details['metadata'] ?? null ) ) {
            $meta = $subscription_details['metadata'];
        }

        if ( ! $meta && is_array( $first_line['metadata'] ?? null ) ) {
            $meta = $first_line['metadata'];
        }

        $email          = sanitize_email( (string) ( $meta['donor_email'] ?? $invoice['customer_email'] ?? '' ) );
        $first_name     = sanitize_text_field( (string) ( $meta['donor_first_name'] ?? '' ) );
        $last_name      = sanitize_text_field( (string) ( $meta['donor_last_name'] ?? '' ) );
        $campaign       = sanitize_text_field( (string) ( $meta['campaign'] ?? '' ) );
        $currency       = strtoupper( sanitize_text_field( (string) ( $meta['currency'] ?? $invoice['currency'] ?? 'EUR' ) ) );
        $amount_cents   = (int) ( $invoice['amount_paid'] ?? 0 );
        $transaction_id = sanitize_text_field( (string) ( $invoice['id'] ?? '' ) );

        if ( $amount_cents <= 0 || ! $transaction_id || ! $email ) {
            return;
        }

        $campaign_id = $campaign
            ? ( ( new CampaignRepository() )->find_by_slug( $campaign )?->get_id() ?? 0 )
            : 0;

        ( new PaymentProcessor() )->process(
            gateway:        'stripe',
            transaction_id: $transaction_id,
            amount_cents:   $amount_cents,
            currency:       $currency,
            email:          $email,
            first_name:     $first_name,
            last_name:      $last_name,
            campaign:       $campaign,
            campaign_id:    $campaign_id
        );
    }

    /**
     * Gère l'événement Stripe charge.refunded.
     *
     * Retrouve le don via gateway_refund_ref et passe son statut à 'refunded'.
     * Idempotent : ignoré si le don est déjà remboursé ou introuvable.
     */
    private function handle_charge_refunded( array $charge ): void {
        global $wpdb;

        $payment_intent_id = sanitize_text_field( $charge['payment_intent'] ?? '' );

        if ( ! $payment_intent_id ) {
            return;
        }

        // Retrouver le don par payment_intent — ignorer si déjà remboursé
        $donation_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}givoly_donations
                 WHERE gateway_refund_ref = %s AND status = 'completed'
                 LIMIT 1",
                $payment_intent_id
            )
        );

        if ( ! $donation_id ) {
            return;
        }

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donations',
            [ 'status' => 'refunded' ],
            [ 'id'     => $donation_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    public function handle_helloasso_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        $payload   = $request->get_body();
        $signature = (string) ( $request->get_header( 'x-helloasso-signature' ) ?: $request->get_header( 'helloasso-signature' ) );
        $remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

        try {
            $gateway = new HelloAssoGateway(
                Settings::get_helloasso_client_id(),
                Settings::get_helloasso_client_secret(),
                Settings::get_helloasso_org_slug(),
                Settings::is_helloasso_sandbox()
            );
            $event = $gateway->verify_webhook( $payload, $signature, Settings::get_helloasso_signature_key(), $remote_ip );
        } catch ( \RuntimeException $e ) {
            return new \WP_REST_Response( [ 'error' => 'Notification HelloAsso invalide.' ], 400 );
        }

        $event_type = strtolower( (string) ( $event['eventType'] ?? $event['type'] ?? '' ) );
        $data       = $event['data'] ?? $event;

        if ( str_contains( $event_type, 'refund' ) ) {
            $this->handle_helloasso_refunded( sanitize_text_field( (string) ( $data['payment']['id'] ?? $data['id'] ?? '' ) ) );
            return new \WP_REST_Response( [ 'received' => true ], 200 );
        }

        if ( ! str_contains( $event_type, 'payment' ) && ! str_contains( $event_type, 'order' ) ) {
            return new \WP_REST_Response( [ 'received' => true ], 200 );
        }

        $order          = is_array( $data['order'] ?? null ) ? $data['order'] : [];
        $payments       = is_array( $data['payments'] ?? null ) ? $data['payments'] : ( is_array( $order['payments'] ?? null ) ? $order['payments'] : [] );
        $metadata = is_array( $data['metadata'] ?? null ) ? $data['metadata'] : ( is_array( $order['metadata'] ?? null ) ? $order['metadata'] : [] );
        $payer    = is_array( $data['payer'] ?? null ) ? $data['payer'] : ( is_array( $order['payer'] ?? null ) ? $order['payer'] : ( is_array( $data['user'] ?? null ) ? $data['user'] : [] ) );
        $payments = $payments ?: [ is_array( $data['payment'] ?? null ) ? $data['payment'] : $data ];

        foreach ( $payments as $payment ) {
            if ( is_array( $payment ) ) {
                $this->process_helloasso_payment( $payment, $data, $order, $metadata, $payer );
            }
        }

        return new \WP_REST_Response( [ 'received' => true ], 200 );
    }

    private function process_helloasso_payment( array $payment, array $data, array $order, array $metadata, array $payer ): void {
        $payment_payer  = is_array( $payment['payer'] ?? null ) ? $payment['payer'] : $payer;
        $amount_cents   = (int) ( $payment['amount'] ?? $payment['initialAmount'] ?? $data['amount'] ?? $data['totalAmount'] ?? $order['amount']['total'] ?? 0 );
        $transaction_id = sanitize_text_field( (string) ( $payment['id'] ?? $data['id'] ?? $order['id'] ?? '' ) );
        $campaign       = sanitize_text_field( (string) ( $metadata['campaign'] ?? '' ) );
        $currency       = strtoupper( sanitize_text_field( (string) ( $metadata['currency'] ?? 'EUR' ) ) );
        $email          = sanitize_email( (string) ( $payment_payer['email'] ?? $data['payerEmail'] ?? $order['payerEmail'] ?? '' ) );
        $first_name     = sanitize_text_field( (string) ( $payment_payer['firstName'] ?? $payment_payer['firstname'] ?? '' ) );
        $last_name      = sanitize_text_field( (string) ( $payment_payer['lastName'] ?? $payment_payer['lastname'] ?? '' ) );
        $post_payment_token = $this->sanitize_post_payment_token( (string) ( $metadata['post_payment_token'] ?? '' ) );

        if ( $amount_cents <= 0 || ! $transaction_id || ! $email ) {
            return;
        }

        $campaign_id = $campaign
            ? ( ( new CampaignRepository() )->find_by_slug( $campaign )?->get_id() ?? 0 )
            : 0;

        ( new PaymentProcessor() )->process(
            gateway:        'helloasso',
            transaction_id: $transaction_id,
            amount_cents:   $amount_cents,
            currency:       $currency,
            email:          $email,
            first_name:     $first_name,
            last_name:      $last_name,
            campaign:       $campaign,
            campaign_id:    $campaign_id,
            post_payment_token: $post_payment_token
        );
    }

    private function parse_amount_to_cents( string $raw ): int {
        $normalized = str_replace( [ ' ', ',' ], [ '', '.' ], trim( $raw ) );

        if ( $normalized === '' || ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $normalized ) ) {
            return 0;
        }

        $parts = explode( '.', $normalized, 2 );
        $euros = (int) $parts[0];
        $cents = isset( $parts[1] ) ? (int) str_pad( $parts[1], 2, '0' ) : 0;

        return ( $euros * 100 ) + $cents;
    }

    private function generate_post_payment_token(): string {
        return bin2hex( random_bytes( 16 ) );
    }

    private function sanitize_post_payment_token( string $token ): string {
        $token = trim( sanitize_text_field( $token ) );

        return preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : '';
    }

    private function handle_helloasso_refunded( string $transaction_id ): void {
        global $wpdb;

        if ( ! $transaction_id ) {
            return;
        }

        $donation_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}givoly_donations
                 WHERE gateway_transaction_id = %s AND gateway = 'helloasso' AND status = 'completed'
                 LIMIT 1",
                $transaction_id
            )
        );

        if ( ! $donation_id ) {
            return;
        }

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donations',
            [ 'status' => 'refunded' ],
            [ 'id'     => $donation_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }
}
