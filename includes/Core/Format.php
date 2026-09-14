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
            'completed' => __( 'Completed', 'givoly' ),
            'pending'   => __( 'Pending', 'givoly' ),
            'failed'    => __( 'Failed', 'givoly' ),
            'refunded'  => __( 'Refunded', 'givoly' ),
            'cancelled' => __( 'Cancelled', 'givoly' ),
        ];

        return $labels[ $status ] ?? $status;
    }

    /**
     * Formate un montant dans sa devise (symbole + code ISO en repli).
     */
    public static function amount_with_currency( float $amount, string $currency ): string {
        $symbol = \Givoly\Form\FormConfig::currency_symbol( strtoupper( $currency ) );

        return number_format_i18n( $amount, 2 ) . ' ' . $symbol;
    }

    /**
     * Formate des totaux ventilés par devise : un total séparé par devise,
     * jamais une somme inter-devises.
     *
     * @param array<string, array{amount: float, count?: int}|float> $by_currency
     */
    public static function amounts_by_currency( array $by_currency ): string {
        if ( empty( $by_currency ) ) {
            return number_format_i18n( 0, 2 ) . ' ' . \Givoly\Form\FormConfig::currency_symbol( 'EUR' );
        }

        $parts = [];
        foreach ( $by_currency as $currency => $data ) {
            $amount = is_array( $data ) ? (float) ( $data['amount'] ?? 0 ) : (float) $data;
            $parts[] = self::amount_with_currency( $amount, (string) $currency );
        }

        return implode( ' + ', $parts );
    }

    /**
     * Masque les secrets configurés dans un message avant journalisation.
     *
     * L'API Stripe renvoie la clé utilisée dans son message d'erreur
     * (« Invalid API Key provided: sk_... ») : journaliser le message brut
     * d'une exception pourrait donc exposer la clé secrète.
     */
    public static function redact_secrets( string $message ): string {
        if ( $message === '' ) {
            return $message;
        }

        $secrets = [
            (string) get_option( \Givoly\Admin\Settings::OPT_STRIPE_SK_TEST, '' ),
            (string) get_option( \Givoly\Admin\Settings::OPT_STRIPE_SK_LIVE, '' ),
            (string) get_option( \Givoly\Admin\Settings::OPT_STRIPE_PK_TEST, '' ),
            (string) get_option( \Givoly\Admin\Settings::OPT_STRIPE_PK_LIVE, '' ),
            (string) get_option( \Givoly\Admin\Settings::OPT_WEBHOOK_SECRET, '' ),
            (string) \Givoly\Admin\Settings::get_helloasso_client_id(),
            (string) \Givoly\Admin\Settings::get_helloasso_client_secret(),
            (string) \Givoly\Admin\Settings::get_helloasso_signature_key(),
            (string) \Givoly\Admin\Settings::get_helloasso_org_slug(),
            (string) get_option( \Givoly\Gateway\HelloAssoGateway::OPT_ACCESS_TOKEN, '' ),
            (string) get_option( \Givoly\Gateway\HelloAssoGateway::OPT_REFRESH_TOKEN, '' ),
            (string) \Givoly\Admin\Settings::get_platform_api_key(),
        ];

        // Compatibilité Givasso : les secrets hérités ne doivent jamais fuir dans les logs.
        foreach ( $secrets as $secret ) {
            if ( $secret !== '' ) {
                // Clé Givoly exacte.
                if ( strlen( $secret ) >= 4 ) {
                    $message = str_replace( $secret, '[REDACTED]', $message );
                }
                continue;
            }
        }
        foreach ( [
            'givasso_stripe_sk_test', 'givasso_stripe_sk_live',
            'givasso_stripe_pk_test', 'givasso_stripe_pk_live',
            'givasso_stripe_webhook_secret', 'givasso_ha_client_id',
            'givasso_ha_client_secret', 'givasso_ha_signature_key',
            'givasso_ha_access_token', 'givasso_ha_refresh_token',
            'givasso_platform_api_key',
        ] as $legacy_option ) {
            $legacy = (string) get_option( $legacy_option, '' );
            if ( strlen( $legacy ) >= 4 ) {
                $message = str_replace( $legacy, '[REDACTED]', $message );
            }
        }

        // Filet générique : toute valeur ressemblant à une clé secrète est masquée,
        // même si l'option correspondante a été renommée ou purgée.
        $message = (string) preg_replace( '/sk_(live|test)_[A-Za-z0-9]+/', '[REDACTED]', $message );
        $message = (string) preg_replace( '/whsec_[A-Za-z0-9]+/', '[REDACTED]', $message );
        $message = (string) preg_replace( '/pk_(live|test)_[A-Za-z0-9]+/', '[REDACTED]', $message );

        return $message;
    }
}
