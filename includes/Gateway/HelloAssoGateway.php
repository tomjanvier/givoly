<?php
/**
 * Passerelle HelloAsso v5.
 *
 * Gère :
 * - Authentification OAuth2 client_credentials + refresh_token
 * - Création d'un checkout intent
 * - Vérification des webhooks (signature HMAC ou IP whitelist)
 *
 * Les tokens sont stockés dans wp_options (pas des transients) car le
 * refresh_token est valide 30 jours et les transients peuvent être purgés.
 *
 * Note : l'API HelloAsso (Cloudflare/Azure) bloque le user-agent WordPress
 * par défaut. Tous les appels HTTP utilisent un user-agent neutre.
 *
 * @package Givoly\Gateway
 */

namespace Givoly\Gateway;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HelloAssoGateway {

    const API_LIVE    = 'https://api.helloasso.com';
    const API_SANDBOX = 'https://api.helloasso-sandbox.com';

    const OPT_ACCESS_TOKEN  = 'givoly_ha_access_token';
    const OPT_REFRESH_TOKEN = 'givoly_ha_refresh_token';
    const OPT_EXPIRES_AT    = 'givoly_ha_expires_at';

    /** User-agent neutre — évite le blocage Cloudflare/Azure sur le user-agent WordPress. */
    const USER_AGENT = 'Givoly/1.0';

    public function __construct(
        private readonly string $client_id,
        private readonly string $client_secret,
        private readonly string $org_slug,
        private readonly bool   $sandbox = false
    ) {}

    // ── API publique ───────────────────────────────────────────────────────

    /**
     * Crée un checkout intent HelloAsso et retourne l'URL de redirection.
     *
     * @throws \RuntimeException si l'API renvoie une erreur.
     */
    public function create_checkout_intent(
        int    $amount_cents,
        string $item_name,
        string $donor_email,
        string $donor_first_name,
        string $donor_last_name,
        string $return_url,
        string $back_url,
        string $error_url,
        string $campaign = '',
        string $frequency = 'once',
        string $post_payment_token = ''
    ): string {
        $token = $this->get_valid_token();

        $terms = [];
        if ( $frequency === 'monthly' ) {
            for ( $i = 1; $i <= 11; $i++ ) {
                $terms[] = [
                    'amount' => $amount_cents,
                    'date'   => gmdate( 'Y-m-d', strtotime( '+' . $i . ' month' ) ),
                ];
            }
        }

        $body = [
            'totalAmount'      => $frequency === 'monthly' ? $amount_cents * 12 : $amount_cents,
            'initialAmount'    => $amount_cents,
            'itemName'         => $item_name,
            'backUrl'          => $back_url,
            'errorUrl'         => $error_url,
            'returnUrl'        => $return_url,
            'containsDonation' => true,
            'payer'            => [
                'email'     => $donor_email,
                'firstName' => $donor_first_name,
                'lastName'  => $donor_last_name,
            ],
            'metadata'         => array_filter( [
                'campaign'           => $campaign,
                'currency'           => 'EUR',
                'frequency'          => $frequency,
                'post_payment_token' => $post_payment_token,
            ] ),
        ];

        if ( $terms ) {
            $body['terms'] = $terms;
            $body['paymentOptions'] = [ 'enableSepa' => true ];
        }

        $base = $this->sandbox ? self::API_SANDBOX : self::API_LIVE;
        $url  = $base . '/v5/organizations/' . rawurlencode( $this->org_slug ) . '/checkout-intents';

        $response = wp_remote_post( $url, $this->http_args( [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ], wp_json_encode( $body ) ) );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'HelloAsso API error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! in_array( $code, [ 200, 201 ], true ) || empty( $data['redirectUrl'] ) ) {
            $message = $this->extract_error_message( $data, $code );
            error_log( '[Givoly] HelloAsso checkout intent failed: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            throw new \RuntimeException( 'HelloAsso checkout intent failed: ' . $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $data['redirectUrl'];
    }

    /**
     * Vérifie l'authenticité d'un webhook HelloAsso.
     *
     * Si $signature_key est renseigné (partenaire) : HMAC-SHA256.
     * Sinon : vérification par IP (whitelist HelloAsso).
     *
     * @throws \RuntimeException si la vérification échoue.
     */
    public function verify_webhook(
        string $payload,
        string $signature_header,
        string $signature_key,
        string $remote_ip
    ): array {
        if ( $signature_key !== '' ) {
            $expected = hash_hmac( 'sha256', $payload, $signature_key );

            if ( ! hash_equals( $expected, $signature_header ) ) {
                throw new \RuntimeException( 'HelloAsso webhook: signature invalide.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        } else {
            $allowed_ips = $this->sandbox
                ? [ '4.233.135.234' ]
                : [ '51.138.206.200' ];

            if ( ! in_array( $remote_ip, $allowed_ips, true ) ) {
                throw new \RuntimeException( 'HelloAsso webhook: IP non autorisée : ' . $remote_ip ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        $data = json_decode( $payload, true );

        if ( ! is_array( $data ) ) {
            throw new \RuntimeException( 'HelloAsso webhook: payload JSON invalide.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $data;
    }

    // ── Gestion des tokens OAuth2 ──────────────────────────────────────────

    private function get_valid_token(): string {
        $access_token = (string) get_option( self::OPT_ACCESS_TOKEN, '' );
        $expires_at   = (int) get_option( self::OPT_EXPIRES_AT, 0 );

        // Hot path : token encore valide
        if ( $access_token !== '' && $expires_at > time() + 60 ) {
            return $access_token;
        }

        $refresh_token = (string) get_option( self::OPT_REFRESH_TOKEN, '' );

        if ( $refresh_token !== '' ) {
            return $this->do_refresh_token( $refresh_token );
        }

        return $this->authenticate();
    }

    private function authenticate(): string {
        $base = $this->sandbox ? self::API_SANDBOX : self::API_LIVE;

        $response = wp_remote_post( $base . '/oauth2/token', $this->http_args(
            [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            http_build_query( [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ] )
        ) );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'HelloAsso auth error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['access_token'] ) ) {
            $api_error = $this->extract_error_message( $data, $code );
            error_log( '[Givoly] HelloAsso auth failed: ' . $api_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            throw new \RuntimeException( 'HelloAsso auth failed: ' . $api_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->store_tokens( $data );

        return $data['access_token'];
    }

    private function do_refresh_token( string $refresh_token ): string {
        $base = $this->sandbox ? self::API_SANDBOX : self::API_LIVE;

        $response = wp_remote_post( $base . '/oauth2/token', $this->http_args(
            [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            http_build_query( [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token,
            ] )
        ) );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'HelloAsso token refresh error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        // Si le refresh échoue, on retente avec client_credentials
        if ( empty( $data['access_token'] ) ) {
            return $this->authenticate();
        }

        $this->store_tokens( $data );

        return $data['access_token'];
    }

    private function store_tokens( array $r ): void {
        update_option( self::OPT_ACCESS_TOKEN,  $r['access_token'],           false );
        update_option( self::OPT_REFRESH_TOKEN, $r['refresh_token'] ?? '',    false );
        update_option( self::OPT_EXPIRES_AT,    time() + (int) ( $r['expires_in'] ?? 1800 ), false );
    }

    private function extract_error_message( mixed $data, int $code ): string {
        if ( ! is_array( $data ) ) {
            return 'HTTP ' . $code;
        }

        $message = $data['message'] ?? $data['error_description'] ?? $data['error'] ?? $data['title'] ?? '';
        $errors  = $data['errors'] ?? $data['validationErrors'] ?? null;

        if ( is_array( $errors ) ) {
            $parts = [];
            foreach ( $errors as $field => $error ) {
                if ( is_array( $error ) ) {
                    $error = implode( ', ', array_filter( array_map( 'strval', $error ) ) );
                }
                $parts[] = is_string( $field ) ? $field . ': ' . (string) $error : (string) $error;
            }
            if ( $parts ) {
                $message .= ( $message ? ' — ' : '' ) . implode( ' | ', $parts );
            }
        }

        return $message !== '' ? $message : 'HTTP ' . $code;
    }

    // ── HTTP helper ────────────────────────────────────────────────────────

    /**
     * Construit les options pour wp_remote_post avec un user-agent neutre.
     *
     * Sans ce override, WordPress envoie "WordPress/X.X; http://..." qui est
     * bloqué par Cloudflare/Azure devant l'API HelloAsso (retourne 404 HTML).
     */
    private function http_args( array $headers, string $body ): array {
        return [
            'headers'     => $headers,
            'body'        => $body,
            'timeout'     => 15,
            'redirection' => 0,
            'user-agent'  => self::USER_AGENT,
        ];
    }
}
