<?php
/**
 * Gestionnaire des requêtes AJAX et REST du plugin.
 *
 * Actions enregistrées :
 * - AJAX  : givasso_init_checkout          → Crée la session de paiement (Stripe ou HelloAsso)
 * - REST  : POST /givasso/v1/webhook        → Reçoit les événements Stripe
 * - REST  : POST /givasso/v1/helloasso-webhook → Reçoit les événements HelloAsso
 *
 * @package Givasso\Ajax
 */

namespace Givasso\Ajax;

use Givasso\Admin\Settings;
use Givasso\Gateway\StripeGateway;
use Givasso\Gateway\HelloAssoGateway;
use Givasso\Repository\CampaignRepository;
use Givasso\Security\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AjaxHandler {

    public function register(): void {
        add_action( 'wp_ajax_givasso_init_checkout',        [ $this, 'handle_checkout' ] );
        add_action( 'wp_ajax_nopriv_givasso_init_checkout', [ $this, 'handle_checkout' ] );
        add_action( 'wp_ajax_givasso_form_preview',         [ $this, 'handle_form_preview' ] );

        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    public function register_rest_routes(): void {
        register_rest_route( 'givasso/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_stripe_webhook' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'givasso/v1', '/helloasso-webhook', [
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
        check_ajax_referer( 'givasso_form_preview' );

        $preview_config = new \Givasso\Form\FormConfig( [
            'theme'      => 'givasso',
            'layout'     => 'card',
            'show_title' => 'yes',
            'title'      => esc_html__( 'Soutenez-nous', 'givasso' ),
        ] );

        wp_enqueue_style( 'givasso-frontend', GIVASSO_PLUGIN_URL . 'assets/css/givasso-frontend.css', [], GIVASSO_VERSION );
        wp_add_inline_style( 'givasso-frontend', 'body{margin:0;padding:24px;background:#f0f0f1;font-family:system-ui,sans-serif;}' );

        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">';
        wp_print_styles( 'givasso-frontend' );
        echo '</head><body>';
        ( new \Givasso\Form\DonationForm( $preview_config ) )->output();
        echo '</body></html>';
        exit;
    }

    // ── Checkout ───────────────────────────────────────────────────────────

    public function handle_checkout(): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['givasso_nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, 'givasso_submit_donation' ) ) {
            wp_send_json_error( [ 'message' => __( 'Requête invalide.', 'givasso' ) ], 403 );
        }

        if ( ! RateLimiter::is_allowed( 'checkout' ) ) {
            wp_send_json_error(
                [ 'message' => __( 'Trop de tentatives. Veuillez patienter une minute avant de réessayer.', 'givasso' ) ],
                429
            );
        }

        $amount      = (int) round( (float) sanitize_text_field( wp_unslash( $_POST['amount'] ?? '0' ) ) );
        $currency    = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'EUR' ) ) );
        $email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $first_name  = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last_name   = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $campaign    = sanitize_text_field( wp_unslash( $_POST['campaign'] ?? '' ) );
        $gateway_key = sanitize_text_field( wp_unslash( $_POST['gateway'] ?? 'stripe' ) );
        $frequency   = 'once';

        if ( $amount < 1 || $amount > 100_000 ) {
            wp_send_json_error( [ 'message' => __( 'Montant invalide.', 'givasso' ) ], 422 );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Email invalide.', 'givasso' ) ], 422 );
        }

        try {
            if ( $gateway_key === 'helloasso' ) {
                $checkout_url = $this->checkout_helloasso(
                    $amount, $currency, $email, $first_name, $last_name, $campaign
                );
            } else {
                $checkout_url = $this->checkout_stripe(
                    $amount, $currency, $email, $first_name, $last_name, $campaign
                );
            }

            wp_send_json_success( [ 'checkout_url' => $checkout_url ] );

        } catch ( \RuntimeException $e ) {
            wp_send_json_error( [ 'message' => __( 'Erreur lors de la création du paiement. Veuillez réessayer.', 'givasso' ) ], 500 );
        }
    }

    private function checkout_stripe(
        int    $amount,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign
    ): string {
        if ( ! Settings::is_configured() ) {
            throw new \RuntimeException( 'Stripe non configuré.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $gateway     = new StripeGateway( Settings::get_stripe_secret_key() );
        $success_url = add_query_arg( 'session_id', '{CHECKOUT_SESSION_ID}', Settings::get_success_url() );
        $success_url = add_query_arg( 'givasso_success', '1', $success_url );
        $cancel_url  = Settings::get_cancel_url();

        return $gateway->create_checkout_session(
            amount_cents:     $amount * 100,
            currency:         $currency,
            donor_email:      $email,
            donor_first_name: $first_name,
            donor_last_name:  $last_name,
            success_url:      $success_url,
            cancel_url:       $cancel_url,
            campaign:         $campaign
        );
    }

    private function checkout_helloasso(
        int    $amount,
        string $currency,
        string $email,
        string $first_name,
        string $last_name,
        string $campaign
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

        $item_name = $campaign ?: __( 'Don', 'givasso' );

        return $gateway->create_checkout_intent(
            amount_cents:     $amount * 100,
            item_name:        $item_name,
            donor_email:      $email,
            donor_first_name: $first_name,
            donor_last_name:  $last_name,
            return_url:       add_query_arg( 'givasso_success', '1', Settings::get_success_url() ),
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
                $wpdb->prefix . 'givasso_donations',
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
                "SELECT id FROM {$wpdb->prefix}givasso_donations
                 WHERE gateway_refund_ref = %s AND status = 'completed'
                 LIMIT 1",
                $payment_intent_id
            )
        );

        if ( ! $donation_id ) {
            return;
        }

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givasso_donations',
            [ 'status' => 'refunded' ],
            [ 'id'     => $donation_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    // ── Webhook HelloAsso ──────────────────────────────────────────────────

    public function handle_helloasso_webhook( \WP_REST_Request $request ): \WP_REST_Response {
        if ( ! Settings::is_helloasso_configured() ) {
            return new \WP_REST_Response( [ 'error' => 'HelloAsso non configuré.' ], 400 );
        }

        $payload          = $request->get_body();
        $signature_header = (string) $request->get_header( 'x-helloasso-signature' );
        $signature_key    = Settings::get_helloasso_signature_key();
        $remote_ip        = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

        try {
            $gateway = new HelloAssoGateway(
                Settings::get_helloasso_client_id(),
                Settings::get_helloasso_client_secret(),
                Settings::get_helloasso_org_slug(),
                Settings::is_helloasso_sandbox()
            );
            $event = $gateway->verify_webhook( $payload, $signature_header, $signature_key, $remote_ip );
        } catch ( \RuntimeException $e ) {
            return new \WP_REST_Response( [ 'error' => 'Vérification échouée.' ], 400 );
        }

        // Filtrer : uniquement les paiements sur un checkout
        if ( ( $event['eventType'] ?? '' ) !== 'Payment'
            || ( $event['data']['order']['formType'] ?? '' ) !== 'Checkout' ) {
            return new \WP_REST_Response( [ 'received' => true ], 200 );
        }

        $data           = $event['data'] ?? [];
        $state          = sanitize_text_field( $data['state'] ?? 'Authorized' );
        $transaction_id = sanitize_text_field( (string) ( $data['id'] ?? '' ) );

        // Remboursement confirmé par HelloAsso → mettre à jour le statut en DB
        if ( in_array( $state, [ 'Refunded', 'Refunding' ], true ) ) {
            $this->handle_helloasso_refunded( $transaction_id );
            return new \WP_REST_Response( [ 'received' => true ], 200 );
        }

        $payer        = $data['payer'] ?? [];
        $meta         = $data['metadata'] ?? $data['meta'] ?? [];
        $amount_cents = (int) ( $data['amount'] ?? 0 );
        $email        = sanitize_email( $payer['email'] ?? '' );
        $first_name   = sanitize_text_field( $payer['firstName'] ?? '' );
        $last_name    = sanitize_text_field( $payer['lastName'] ?? '' );
        $campaign     = sanitize_text_field( $meta['campaign'] ?? '' );

        if ( ! $email || $amount_cents <= 0 ) {
            return new \WP_REST_Response( [ 'received' => true ], 200 );
        }

        $campaign_id = $campaign
            ? ( ( new CampaignRepository() )->find_by_slug( $campaign )?->get_id() ?? 0 )
            : 0;

        ( new PaymentProcessor() )->process(
            gateway:        'helloasso',
            transaction_id: $transaction_id,
            amount_cents:   $amount_cents,
            currency:       'EUR',
            email:          $email,
            first_name:     $first_name,
            last_name:      $last_name,
            campaign:       $campaign,
            campaign_id:    $campaign_id
        );

        // Stocker la référence de remboursement gateway (HelloAsso : payment ID = transaction ID)
        if ( $transaction_id ) {
            global $wpdb;
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'givasso_donations',
                [ 'gateway_refund_ref'    => $transaction_id ],
                [ 'gateway_transaction_id' => $transaction_id ],
                [ '%s' ],
                [ '%s' ]
            );
        }

        return new \WP_REST_Response( [ 'received' => true ], 200 );
    }

    /**
     * Gère la confirmation de remboursement HelloAsso.
     *
     * Retrouve le don via gateway_transaction_id et passe son statut à 'refunded'.
     * Idempotent : ignoré si le don est déjà remboursé ou introuvable.
     */
    private function handle_helloasso_refunded( string $transaction_id ): void {
        global $wpdb;

        if ( ! $transaction_id ) {
            return;
        }

        $donation_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}givasso_donations
                 WHERE gateway_transaction_id = %s AND gateway = 'helloasso' AND status = 'completed'
                 LIMIT 1",
                $transaction_id
            )
        );

        if ( ! $donation_id ) {
            return;
        }

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givasso_donations',
            [ 'status' => 'refunded' ],
            [ 'id'     => $donation_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }
}
