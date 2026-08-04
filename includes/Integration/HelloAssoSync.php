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
        }

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
                $response = $gateway->get_payments( $page, self::PAGE_SIZE, $from );
                $payments = is_array( $response['data'] ?? null ) ? $response['data'] : [];

                foreach ( $payments as $payment ) {
                    if ( is_array( $payment ) ) {
                        $this->process_payment( $processor, $payment );
                    }
                }

                $page++;
            } while ( count( $payments ) >= self::PAGE_SIZE && $page <= 5 );

            update_option( self::LAST_SYNC_OPTION, current_time( 'mysql', true ), false );
        } catch ( \Throwable $exception ) {
            error_log( '[Givoly] HelloAsso sync error: ' . \Givoly\Core\Format::redact_secrets( $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    private function process_payment( PaymentProcessor $processor, array $payment ): void {
        $order   = is_array( $payment['order'] ?? null ) ? $payment['order'] : [];
        $payer   = is_array( $payment['payer'] ?? null ) ? $payment['payer'] : ( is_array( $order['payer'] ?? null ) ? $order['payer'] : [] );
        $metadata = is_array( $payment['metadata'] ?? null ) ? $payment['metadata'] : ( is_array( $order['metadata'] ?? null ) ? $order['metadata'] : [] );
        $status  = strtolower( (string) ( $payment['status'] ?? $payment['state'] ?? '' ) );

        if ( in_array( $status, [ 'cancelled', 'canceled', 'refunded', 'failed', 'rejected' ], true ) ) {
            return;
        }

        $amount = $payment['amount'] ?? $payment['initialAmount'] ?? $payment['totalAmount'] ?? $order['amount']['total'] ?? 0;
        if ( is_array( $amount ) ) {
            $amount = $amount['total'] ?? $amount['value'] ?? 0;
        }

        $transaction_id = sanitize_text_field( (string) ( $payment['id'] ?? $order['id'] ?? '' ) );
        $email          = sanitize_email( (string) ( $payer['email'] ?? $payment['payerEmail'] ?? $order['payerEmail'] ?? '' ) );
        $first_name     = sanitize_text_field( (string) ( $payer['firstName'] ?? $payer['firstname'] ?? '' ) );
        $last_name      = sanitize_text_field( (string) ( $payer['lastName'] ?? $payer['lastname'] ?? '' ) );
        $currency       = strtoupper( sanitize_text_field( (string) ( $metadata['currency'] ?? $payment['currency'] ?? 'EUR' ) ) );
        $campaign       = sanitize_text_field( (string) ( $metadata['campaign'] ?? '' ) );
        $token          = sanitize_text_field( (string) ( $metadata['post_payment_token'] ?? '' ) );

        if ( ! $transaction_id || ! is_email( $email ) || (int) $amount <= 0 ) {
            return;
        }

        $campaign_id = $campaign
            ? ( ( new \Givoly\Repository\CampaignRepository() )->find_by_slug( $campaign )?->get_id() ?? 0 )
            : 0;

        $processor->process(
            gateway: 'helloasso',
            transaction_id: $transaction_id,
            amount_cents: (int) $amount,
            currency: $currency,
            email: $email,
            first_name: $first_name,
            last_name: $last_name,
            campaign: $campaign,
            campaign_id: $campaign_id,
            post_payment_token: preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : ''
        );
    }
}
