<?php
/**
 * Passerelle Stripe.
 *
 * Responsabilité unique : communiquer avec l'API Stripe.
 * Aucune logique WordPress (hooks, options) ici — ça reste testable.
 *
 * On utilise wp_remote_post() plutôt que le SDK Stripe pour éviter
 * une dépendance Composer. Le SDK pourra être ajouté en v0.3.0 si besoin.
 *
 * @package Givoly\Gateway
 */

namespace Givoly\Gateway;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class StripeGateway {

    const API_BASE = 'https://api.stripe.com/v1';

    public function __construct( private readonly string $secret_key ) {}

    // ── API publique ───────────────────────────────────────────────────────

    /**
     * Crée une Stripe Checkout Session (don unique) et retourne l'URL de paiement.
     *
     * @throws \RuntimeException Si l'API retourne une erreur.
     */
    public function create_checkout_session(
        int    $amount_cents,
        string $currency,
        string $donor_email,
        string $donor_first_name,
        string $donor_last_name,
        string $success_url,
        string $cancel_url,
        string $campaign = '',
        string $frequency = 'once',
        string $post_payment_token = ''
    ): string {
        if ( $campaign ) {
            // translators: %s is the campaign name.
            $product_name = sprintf( __( 'Don — %s', 'givoly' ), $campaign );
        } else {
            $product_name = __( 'Don', 'givoly' );
        }

        $params = [
            'mode'                                             => $frequency === 'monthly' ? 'subscription' : 'payment',
            'payment_method_types[]'                          => 'card',
            'customer_email'                                  => $donor_email,
            'success_url'                                     => $success_url,
            'cancel_url'                                      => $cancel_url,
            'line_items[0][quantity]'                         => 1,
            'line_items[0][price_data][currency]'             => strtolower( $currency ),
            'line_items[0][price_data][unit_amount]'          => $amount_cents,
            'line_items[0][price_data][product_data][name]'   => $product_name,
            'metadata[donor_first_name]'                      => $donor_first_name,
            'metadata[donor_last_name]'                       => $donor_last_name,
            'metadata[donor_email]'                           => $donor_email,
            'metadata[campaign]'                              => $campaign,
            'metadata[currency]'                              => $currency,
            'metadata[frequency]'                             => $frequency,
            'metadata[post_payment_token]'                    => $post_payment_token,
        ];

        if ( $frequency === 'monthly' ) {
            $params['line_items[0][price_data][recurring][interval]'] = 'month';
            $params['subscription_data[metadata][donor_first_name]']  = $donor_first_name;
            $params['subscription_data[metadata][donor_last_name]']   = $donor_last_name;
            $params['subscription_data[metadata][donor_email]']       = $donor_email;
            $params['subscription_data[metadata][campaign]']          = $campaign;
            $params['subscription_data[metadata][currency]']          = $currency;
            $params['subscription_data[metadata][frequency]']         = $frequency;
        }

        $response = $this->post( '/checkout/sessions', $params );

        return $response['url'];
    }

    public function refund( string $payment_intent_id ): void {
        $this->post( '/refunds', [ 'payment_intent' => $payment_intent_id ] );
    }

    /**
     * Vérifie la signature d'un webhook Stripe.
     * Retourne l'événement décodé ou lève une exception si invalide.
     *
     * @throws \RuntimeException Si la signature est invalide ou expirée.
     */
    public function verify_webhook( string $payload, string $signature_header, string $webhook_secret ): array {
        // Stripe signe avec HMAC-SHA256 : "t=timestamp,v1=signature"
        $parts     = $this->parse_stripe_signature( $signature_header );
        $timestamp = $parts['t'] ?? 0;
        $received  = $parts['v1'] ?? '';

        // Rejeter les webhooks de plus de 5 minutes (protection replay)
        if ( abs( time() - (int) $timestamp ) > 300 ) {
            throw new \RuntimeException( 'Webhook expiré.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $webhook_secret );

        if ( ! hash_equals( $expected, $received ) ) {
            throw new \RuntimeException( 'Signature webhook invalide.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $event = json_decode( $payload, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new \RuntimeException( 'Payload webhook invalide.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $event;
    }

    // ── HTTP helpers privés ────────────────────────────────────────────────

    private function post( string $endpoint, array $params ): array {
        $response = wp_remote_post( self::API_BASE . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => http_build_query( $params ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Erreur réseau Stripe : ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code >= 400 ) {
            $message = $body['error']['message'] ?? 'Erreur Stripe inconnue.';
            throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $body;
    }

    private function parse_stripe_signature( string $header ): array {
        $parts = [];

        foreach ( explode( ',', $header ) as $pair ) {
            [ $key, $value ] = array_pad( explode( '=', $pair, 2 ), 2, '' );
            $parts[ trim( $key ) ] = trim( $value );
        }

        return $parts;
    }
}
