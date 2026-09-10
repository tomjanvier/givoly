<?php
/**
 * Client HTTP de la Plateforme Givoly.
 *
 * Responsabilité unique : parler à l'API de la Plateforme.
 * Aucune logique WordPress métier ici (ni options, ni base) — testable.
 *
 * Contrat attendu côté Plateforme (à exposer sous /api/wordpress) :
 * - GET  /api/wordpress/health           → { ok: true }
 * - POST /api/wordpress/sites            → { siteId: string }
 * - POST /api/wordpress/donations        → { received: true }
 * - POST /api/wordpress/campaigns/sync   → { synced: number }
 *
 * Authentification par clé API (Bearer). La plateforme n'expose aujourd'hui
 * aucune route /api/wordpress : le contrôle de santé le signale explicitement
 * au lieu de prétendre une connexion établie.
 *
 * On utilise wp_remote_*() comme les autres passerelles, sans SDK ni Composer.
 *
 * @package Givoly\Gateway
 */

namespace Givoly\Gateway;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PlatformGateway {

    /** Agent HTTP neutre identifiant l'extension appelante. */
    const USER_AGENT = 'Givoly-Platform/1.0';

    const PATH_HEALTH           = '/api/wordpress/health';
    const PATH_REGISTER_SITE    = '/api/wordpress/sites';
    const PATH_PUSH_DONATION    = '/api/wordpress/donations';
    const PATH_SYNC_CAMPAIGNS   = '/api/wordpress/campaigns/sync';

    public function __construct(
        private readonly string $base_url,
        private readonly string $api_key,
        private readonly int $timeout = 15
    ) {}

    /**
     * URL de base normalisée (sans slash final).
     *
     * Seules les URL http(s) sont acceptées ; http est réservé au développement
     * local (localhost). Retourne '' si l'URL est invalide.
     */
    public static function normalize_base_url( string $raw_url ): string {
        $url = trim( $raw_url );
        if ( $url === '' ) {
            return '';
        }

        if ( ! preg_match( '#^https?://#i', $url ) ) {
            $url = 'https://' . $url;
        }

        $parts = parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return '';
        }

        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            return '';
        }

        $host = strtolower( (string) $parts['host'] );
        if ( ! preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host ) ) {
            return '';
        }
        if ( $scheme === 'http' && ! in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
            return '';
        }

        $normalized = $scheme . '://' . $host;
        if ( ! empty( $parts['port'] ) ) {
            $normalized .= ':' . (int) $parts['port'];
        }
        if ( ! empty( $parts['path'] ) && trim( (string) $parts['path'], '/' ) !== '' ) {
            $normalized .= '/' . trim( (string) $parts['path'], '/' );
        }

        return $normalized;
    }

    /**
     * Contrôle de santé : vérifie URL joignable + clé acceptée.
     *
     * @return array{ok: bool, organizationId?: string}
     * @throws \RuntimeException si la plateforme est injoignable ou refuse la clé.
     */
    public function health(): array {
        $data = $this->get( self::PATH_HEALTH );

        if ( empty( $data['ok'] ) ) {
            throw new \RuntimeException( 'Givoly Platform health check failed.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $data;
    }

    /**
     * Enregistre le site WordPress auprès de la plateforme (idempotent sur siteUrl).
     *
     * @param array{siteUrl: string, siteName: string, contactEmail: string, pluginVersion: string} $site
     * @return string Identifiant site retourné par la plateforme (peut être vide si non fourni).
     * @throws \RuntimeException si l'enregistrement échoue.
     */
    public function register_site( array $site ): string {
        $data = $this->post( self::PATH_REGISTER_SITE, [
            'siteUrl'       => (string) ( $site['siteUrl'] ?? '' ),
            'siteName'      => (string) ( $site['siteName'] ?? '' ),
            'contactEmail'  => (string) ( $site['contactEmail'] ?? '' ),
            'pluginVersion' => (string) ( $site['pluginVersion'] ?? '' ),
        ] );

        return (string) ( $data['siteId'] ?? $data['site_id'] ?? '' );
    }

    /**
     * Pousse un don confirmé (données minimales, même périmètre que Stripe/HelloAsso).
     *
     * @param array<string,mixed> $donation Payload construit par PlatformSync::build_donation_payload().
     * @param string $idempotency_key Clé unique par don (ex. givoly-donation-123).
     * @throws \RuntimeException si la plateforme refuse ou est injoignable.
     */
    public function push_donation( array $donation, string $idempotency_key ): void {
        $this->post( self::PATH_PUSH_DONATION, $donation, $idempotency_key );
    }

    /**
     * Pousse un instantané des campagnes (lecture seule, aucun import).
     *
     * @param array<int, array<string,mixed>> $campaigns
     * @return int Nombre de campagnes confirmées par la plateforme.
     * @throws \RuntimeException si la synchronisation échoue.
     */
    public function push_campaigns( array $campaigns ): int {
        $data = $this->post( self::PATH_SYNC_CAMPAIGNS, [ 'campaigns' => array_values( $campaigns ) ] );

        return (int) ( $data['synced'] ?? count( $campaigns ) );
    }

    // ── HTTP privé ───────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    private function get( string $path ): array {
        $response = wp_remote_get(
            $this->url( $path ),
            [
                'headers'     => $this->auth_headers(),
                'timeout'     => $this->timeout,
                'redirection' => 0,
                'user-agent'  => self::USER_AGENT,
            ]
        );

        return $this->decode_response( $response, 'GET ' . $path );
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post( string $path, array $body, string $idempotency_key = '' ): array {
        $headers = $this->auth_headers();
        $headers['Content-Type'] = 'application/json';
        if ( $idempotency_key !== '' ) {
            $headers['Idempotency-Key'] = $idempotency_key;
        }

        $response = wp_remote_post(
            $this->url( $path ),
            [
                'headers'     => $headers,
                'body'        => wp_json_encode( $body ),
                'timeout'     => $this->timeout,
                'redirection' => 0,
                'user-agent'  => self::USER_AGENT,
            ]
        );

        return $this->decode_response( $response, 'POST ' . $path );
    }

    private function url( string $path ): string {
        return rtrim( $this->base_url, '/' ) . $path;
    }

    /**
     * @return array<string,string>
     */
    private function auth_headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Décode une réponse HTTP en vérifiant code + JSON, sans jamais exposer la clé.
     *
     * @param mixed $response Réponse wp_remote_*().
     * @return array<string,mixed>
     * @throws \RuntimeException
     */
    private function decode_response( $response, string $action ): array {
        if ( is_wp_error( $response ) ) {
            // Le message WP_Error ne contient ni URL complète ni clé (headers séparés).
            throw new \RuntimeException( 'Givoly Platform unreachable: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code === 401 || $code === 403 ) {
            throw new \RuntimeException( 'Givoly Platform refused the API key.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( $code === 404 ) {
            throw new \RuntimeException( 'Givoly Platform endpoint not found. The platform does not expose the WordPress bridge yet.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            $detail = is_array( $data ) ? (string) ( $data['error'] ?? $data['message'] ?? '' ) : '';
            // Message volontairement générique : le détail brut peut contenir l'URL appelée.
            throw new \RuntimeException( trim( 'Givoly Platform request failed (' . $action . '). ' . $detail ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $data;
    }
}
