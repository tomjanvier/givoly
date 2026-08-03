<?php
/**
 * Helpers de formatage partagés entre les pages d'administration.
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Format {

    /**
     * Libellé lisible d'un statut de don.
     */
    public static function status( string $status ): string {
        $labels = [
            'completed' => __( 'Complété', 'givoly' ),
            'pending'   => __( 'En attente', 'givoly' ),
            'failed'    => __( 'Échoué', 'givoly' ),
            'refunded'  => __( 'Remboursé', 'givoly' ),
            'cancelled' => __( 'Annulé', 'givoly' ),
        ];

        return $labels[ $status ] ?? $status;
    }

    /**
     * Masque les secrets configurés dans un message avant journalisation.
     *
     * L'API Stripe renvoie la clé utilisée dans son message d'erreur
     * (« Invalid API Key provided: sk_... ») : journaliser le message brut
     * d'une exception pourrait donc exposer la clé secrète.
     */
    public static function redact_secrets( string $message ): string {
        $secrets = [
            (string) get_option( \Givoly\Admin\Settings::OPT_STRIPE_SK_TEST, '' ),
            (string) get_option( \Givoly\Admin\Settings::OPT_STRIPE_SK_LIVE, '' ),
            (string) get_option( \Givoly\Admin\Settings::OPT_WEBHOOK_SECRET, '' ),
            (string) \Givoly\Admin\Settings::get_helloasso_client_secret(),
            (string) \Givoly\Admin\Settings::get_helloasso_signature_key(),
            (string) get_option( \Givoly\Gateway\HelloAssoGateway::OPT_ACCESS_TOKEN, '' ),
            (string) get_option( \Givoly\Gateway\HelloAssoGateway::OPT_REFRESH_TOKEN, '' ),
        ];

        foreach ( $secrets as $secret ) {
            if ( strlen( $secret ) >= 4 ) {
                $message = str_replace( $secret, '[REDACTED]', $message );
            }
        }

        return $message;
    }
}
