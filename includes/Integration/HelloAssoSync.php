<?php
/**
 * Synchronisation périodique des paiements HelloAsso.
 *
 * @package Givoly\Integration
 */

namespace Givoly\Integration;

use Givoly\Admin\Settings;
use Givoly\Ajax\PaymentProcessor;
use Givoly\Gateway\HelloAssoGateway;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HelloAssoSync {

    private const HOOK             = 'givoly_sync_helloasso_payments';
    private const LAST_SYNC_OPTION = 'givoly_helloasso_last_sync_at';
    private const PAGE_SIZE        = 100;
    private const MAX_PAGES        = 20;

    public function register(): void {
        add_action( self::HOOK, [ $this, 'run' ] );
        self::schedule();
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK );
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::HOOK );
    }

    public function run(): void {
        if ( ! Settings::is_helloasso_configured() ) {
            return;
        }

        $from = (string) get_option( self::LAST_SYNC_OPTION, '' );
        if ( '' === $from ) {
            $from = gmdate( 'c', time() - 2 * DAY_IN_SECONDS );
        } else {
            // Chevauchement de cinq minutes : un paiement créé à la limite
            // entre deux exécutions ne doit pas être perdu.
            $from_timestamp = strtotime( $from );
            $from            = gmdate( 'c', max( 0, (int) $from_timestamp - 5 * MINUTE_IN_SECONDS ) );
        }

        $to = gmdate( 'c' );

        $gateway = new HelloAssoGateway(
            Settings::get_helloasso_client_id(),
            Settings::get_helloasso_client_secret(),
            Settings::get_helloasso_org_slug(),
            Settings::is_helloasso_sandbox()
        );

        try {
            $processor = new PaymentProcessor();
            $page      = 1;

            do {
                $response = $gateway->get_payments( $page, self::PAGE_SIZE, $from, $to );
                $payments = is_array( $response['data'] ?? null ) ? $response['data'] : [];

                foreach ( $payments as $payment ) {
                    if ( is_array( $payment ) ) {
                        $this->process_payment( $processor, $gateway, $payment );
                    }
                }

                $page++;
            } while ( count( $payments ) >= self::PAGE_SIZE && $page <= self::MAX_PAGES );

            // Si la fenêtre dépasse la limite de sécurité, on ne déplace pas le
            // curseur : le prochain cron reprendra avec la même fenêtre et
            // l'idempotence ignorera les lignes déjà importées.
            if ( count( $payments ) >= self::PAGE_SIZE ) {
                return;
            }

            update_option( self::LAST_SYNC_OPTION, current_time( 'mysql', true ), false );
        } catch ( \Throwable $exception ) {
            error_log( '[Givoly] HelloAsso sync error: ' . \Givoly\Core\Format::redact_secrets( $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    private function process_payment( PaymentProcessor $processor, HelloAssoGateway $gateway, array $payment ): void {
        $order   = is_array( $payment['order'] ?? null ) ? $payment['order'] : [];
        $payer   = is_array( $payment['payer'] ?? null ) ? $payment['payer'] : ( is_array( $order['payer'] ?? null ) ? $order['payer'] : [] );
        $metadata = is_array( $payment['metadata'] ?? null ) ? $payment['metadata'] : ( is_array( $order['metadata'] ?? null ) ? $order['metadata'] : [] );
        $status  = strtolower( (string) ( $payment['state'] ?? $payment['status'] ?? '' ) );

        if ( in_array( $status, [ 'cancelled', 'canceled', 'refunded', 'failed', 'rejected', 'refused', 'contested' ], true ) ) {
            return;
        }

        $amount = $this->normalize_amount( $payment['amount'] ?? $payment['initialAmount'] ?? $payment['totalAmount'] ?? $order['amount']['total'] ?? 0 );
        $payment_id = (string) ( $payment['id'] ?? $order['id'] ?? '' );
        $email = sanitize_email( (string) ( $payer['email'] ?? $payment['payerEmail'] ?? $order['payerEmail'] ?? '' ) );

        // La liste peut être synthétique. On ne demande le détail que si les
        // données nécessaires à l'enregistrement du don manquent.
        if ( ( ! $email || $amount <= 0 ) && $payment_id !== '' && ctype_digit( $payment_id ) ) {
            $details = $gateway->get_payment( (int) $payment_id );
            $payment = array_merge( $payment, $details );
            $order   = is_array( $payment['order'] ?? null ) ? $payment['order'] : $order;
            $payer   = is_array( $payment['payer'] ?? null ) ? $payment['payer'] : $payer;
            $metadata = is_array( $payment['metadata'] ?? null ) ? $payment['metadata'] : $metadata;
            $amount  = $this->normalize_amount( $payment['amount'] ?? $payment['initialAmount'] ?? $payment['totalAmount'] ?? $order['amount']['total'] ?? 0 );
            $email   = sanitize_email( (string) ( $payer['email'] ?? $payment['payerEmail'] ?? $order['payerEmail'] ?? '' ) );
        }

        $status = strtolower( (string) ( $payment['state'] ?? $payment['status'] ?? $status ) );
        if ( $status !== '' && ! in_array( $status, [ 'authorized', 'registered', 'processed', 'paid', 'cashedout', 'cashout' ], true ) ) {
            return;
        }

        $transaction_id = sanitize_text_field( (string) ( $payment['id'] ?? $order['id'] ?? '' ) );
        $first_name     = sanitize_text_field( (string) ( $payer['firstName'] ?? $payer['firstname'] ?? '' ) );
        $last_name      = sanitize_text_field( (string) ( $payer['lastName'] ?? $payer['lastname'] ?? '' ) );
        $currency       = strtoupper( sanitize_text_field( (string) ( $metadata['currency'] ?? $payment['currency'] ?? 'EUR' ) ) );
        $campaign       = sanitize_text_field( (string) ( $metadata['campaign'] ?? '' ) );
        $token          = sanitize_text_field( (string) ( $metadata['post_payment_token'] ?? '' ) );

        if ( ! $transaction_id || ! is_email( $email ) || $amount <= 0 ) {
            return;
        }

        $campaign_id = $campaign
            ? ( ( new \Givoly\Repository\CampaignRepository() )->find_by_slug( $campaign )?->get_id() ?? 0 )
            : 0;

        $processor->process(
            gateway: 'helloasso',
            transaction_id: $transaction_id,
            amount_cents: $amount,
            currency: $currency,
            email: $email,
            first_name: $first_name,
            last_name: $last_name,
            campaign: $campaign,
            campaign_id: $campaign_id,
            post_payment_token: preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : ''
        );
    }

    private function normalize_amount( mixed $amount ): int {
        if ( is_array( $amount ) ) {
            $amount = $amount['total'] ?? $amount['value'] ?? $amount['amount'] ?? $amount['totalAmount'] ?? 0;
        }

        return max( 0, (int) $amount );
    }
}
