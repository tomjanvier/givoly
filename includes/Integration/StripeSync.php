<?php
/**
 * Réconciliation périodique des factures Stripe payées.
 *
 * Le webhook reste le chemin temps réel. Cette tâche récupère également les
 * factures récurrentes si Stripe n'a pas pu livrer un webhook.
 *
 * @package Givoly\Integration
 */

namespace Givoly\Integration;

use Givoly\Admin\Settings;
use Givoly\Ajax\AjaxHandler;
use Givoly\Gateway\StripeGateway;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class StripeSync {

    private const HOOK             = 'givoly_sync_stripe_paid_invoices';
    private const LAST_SYNC_OPTION = 'givoly_stripe_last_invoice_sync_at';
    private const PAGE_SIZE        = 100;
    private const MAX_PAGES        = 20;
    private const FIRST_LOOKBACK   = 30 * DAY_IN_SECONDS;

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
        $secret_key = Settings::get_stripe_secret_key();
        if ( $secret_key === '' ) {
            return;
        }

        $last_sync = (string) get_option( self::LAST_SYNC_OPTION, '' );
        $from      = '' === $last_sync
            ? time() - self::FIRST_LOOKBACK
            : max( 0, (int) strtotime( $last_sync ) - 10 * MINUTE_IN_SECONDS );

        try {
            $gateway        = new StripeGateway( $secret_key );
            $invoice_handler = new AjaxHandler();
            $starting_after = '';
            $page           = 0;
            $has_more       = false;

            do {
                $response = $gateway->get_paid_invoices( self::PAGE_SIZE, $starting_after, $from );
                $invoices = is_array( $response['data'] ?? null ) ? $response['data'] : [];
                $has_more = ! empty( $response['has_more'] );

                foreach ( $invoices as $invoice ) {
                    if ( is_array( $invoice ) ) {
                        $invoice_handler->process_stripe_invoice( $invoice );
                    }
                }

                $last_invoice = end( $invoices );
                $starting_after = is_array( $last_invoice ) ? sanitize_text_field( (string) ( $last_invoice['id'] ?? '' ) ) : '';
                $page++;
            } while ( $has_more && $starting_after !== '' && $page < self::MAX_PAGES );

            // Ne pas avancer le curseur si la limite de sécurité a interrompu
            // la pagination : le prochain passage reprendra la même fenêtre.
            if ( $has_more ) {
                return;
            }

            update_option( self::LAST_SYNC_OPTION, current_time( 'mysql', true ), false );
        } catch ( \Throwable $exception ) {
            error_log( '[Givoly] Stripe invoice sync error: ' . \Givoly\Core\Format::redact_secrets( $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
