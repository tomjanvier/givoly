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

        try {
            if ( ! in_array( $gateway_key, Settings::get_enabled_gateways(), true ) ) {
                throw new \RuntimeException( 'Passerelle désactivée.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            if ( $gateway_key === 'helloasso' ) {
                $checkout_url = $this->checkout_helloasso(
                    $amount_cents, $currency, $email, $first_name, $last_name, $campaign, $frequency
                );
            } else {
                $checkout_url = $this->checkout_stripe(
                    $amount_cents, $currency, $email, $first_name, $last_name, $campaign, $frequency
                );
            }

            wp_send_json_success( [ 'checkout_url' => $checkout_url ] );

        } catch ( \RuntimeException $e ) {
            wp_send_json_error( [ 'message' => __( 'Erreur lors de la création du paiement. Veuillez réessayer.', 'givoly' ) ], 500 );
        }
    }

    public function handle_post_payment_details(): void {
        global $wpdb;

        if ( ! check_ajax_referer( 'givoly_submit_donation', 'givoly_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Requête invalide.', 'givoly' ) ], 403 );
        }

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Email invalide.', 'givoly' ) ], 422 );
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
            [ 'email' => $email ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%s' ]
        );

        if ( $updated === false ) {
            wp_send_json_error( [ 'message' => __( 'Impossible d’enregistrer les informations.', 'givoly' ) ], 500 );
        }

        wp_send_json_success( [ 'message' => __( 'Merci, vos informations ont bien été enregistrées.', 'givoly' ) ] );
    }

    private function checkout_stripe(
        int    $amount_cents,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign,
        string $frequency = 'once'
    ): string {
        if ( ! Settings::is_configured() ) {
            throw new \RuntimeException( 'Stripe non configuré.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $gateway     = new StripeGateway( Settings::get_stripe_secret_key() );
        $success_url = add_query_arg( 'session_id', 'CHECKOUT_SESSION_ID', Settings::get_success_url() );
        $success_url = str_replace( 'CHECKOUT_SESSION_ID', '{CHECKOUT_SESSION_ID}', $success_url );
        $success_url = add_query_arg( 'givoly_success', '1', $success_url );
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
            frequency:        $frequency
        );
    }

    private function checkout_helloasso(
        int    $amount_cents,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign,
        string $frequency = 'once'
    ): string {
        if ( $frequency === 'monthly' ) {
            throw new \RuntimeException( 'Le don mensuel est disponible avec Stripe.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

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
            return_url:       add_query_arg( 'givoly_success', '1', Settings::get_success_url() ),
            back_url:         Settings::get_cancel_url(),
            error_url:        Settings::get_cancel_url(),
            campaign:         $campaign
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

        $metadata       = is_array( $data['metadata'] ?? null ) ? $data['metadata'] : [];
        $payer          = is_array( $data['payer'] ?? null ) ? $data['payer'] : ( is_array( $data['user'] ?? null ) ? $data['user'] : [] );
        $payment        = is_array( $data['payment'] ?? null ) ? $data['payment'] : $data;
        $amount_cents   = (int) ( $payment['amount'] ?? $data['amount'] ?? $data['totalAmount'] ?? 0 );
        $transaction_id = sanitize_text_field( (string) ( $payment['id'] ?? $data['id'] ?? $data['order']['id'] ?? '' ) );
        $campaign       = sanitize_text_field( (string) ( $metadata['campaign'] ?? '' ) );
        $currency       = strtoupper( sanitize_text_field( (string) ( $metadata['currency'] ?? 'EUR' ) ) );
        $email          = sanitize_email( (string) ( $payer['email'] ?? $data['payerEmail'] ?? '' ) );
        $first_name     = sanitize_text_field( (string) ( $payer['firstName'] ?? '' ) );
        $last_name      = sanitize_text_field( (string) ( $payer['lastName'] ?? '' ) );

        if ( $amount_cents > 0 && $transaction_id && $email ) {
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
                campaign_id:    $campaign_id
            );
        }

        return new \WP_REST_Response( [ 'received' => true ], 200 );
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
