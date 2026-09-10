<?php
/**
 * Accès aux abonnements Stripe.
 *
 * Un donateur peut posséder plusieurs abonnements : l'identifiant exact est
 * rattaché ici (entité dédiée) et sur chaque don récurrent via
 * givoly_donations.stripe_subscription_id. La colonne historique
 * givoly_donors.stripe_subscription_id est conservée pour compatibilité mais
 * n'est plus la source de vérité pour l'annulation.
 *
 * @package Givoly\Repository
 */

namespace Givoly\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SubscriptionRepository {

    const STATUS_ACTIVE              = 'active';
    const STATUS_CANCEL_AT_PERIOD_END = 'cancel_at_period_end';
    const STATUS_CANCELED            = 'canceled';

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'givoly_subscriptions';
    }

    /**
     * Abonnements d'un donateur, triés par création décroissante.
     *
     * @return array<int, object>
     */
    public function find_by_donor( int $donor_id ): array {
        global $wpdb;

        if ( $donor_id <= 0 ) {
            return [];
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
        return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE donor_id = %d ORDER BY created_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $donor_id
            ),
            OBJECT
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Retrouve un abonnement par son identifiant Stripe exact.
     */
    public function find_by_stripe_id( string $stripe_subscription_id ): ?object {
        global $wpdb;

        $stripe_subscription_id = sanitize_text_field( $stripe_subscription_id );
        if ( $stripe_subscription_id === '' ) {
            return null;
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE stripe_subscription_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $stripe_subscription_id
            ),
            OBJECT
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $row ?: null;
    }

    /**
     * Crée l'abonnement s'il n'existe pas, sinon met à jour le rattachement.
     *
     * Ne vide jamais une valeur existante à cause d'un champ vide : seuls les
     * identifiants non vides sont persistés.
     */
    public function upsert( int $donor_id, string $stripe_subscription_id, string $stripe_customer_id = '', string $currency = '', int $campaign_id = 0 ): ?object {
        global $wpdb;

        $stripe_subscription_id = sanitize_text_field( $stripe_subscription_id );
        if ( $donor_id <= 0 || $stripe_subscription_id === '' ) {
            return null;
        }

        $existing = $this->find_by_stripe_id( $stripe_subscription_id );
        if ( $existing ) {
            // Ne jamais réassigner un abonnement à un autre donateur : le
            // premier rattachement fait foi, les webhooks suivants sont ignorés.
            if ( (int) $existing->donor_id !== $donor_id ) {
                return $existing;
            }

            $data    = [];
            $formats = [];
            $customer_id = sanitize_text_field( $stripe_customer_id );
            if ( $customer_id !== '' && (string) ( $existing->stripe_customer_id ?? '' ) === '' ) {
                $data['stripe_customer_id'] = $customer_id;
                $formats[]                  = '%s';
            }
            $currency = strtoupper( sanitize_text_field( $currency ) );
            if ( $currency !== '' && (string) ( $existing->currency ?? '' ) === '' ) {
                $data['currency'] = $currency;
                $formats[]        = '%s';
            }
            if ( $campaign_id > 0 && empty( $existing->campaign_id ) ) {
                $data['campaign_id'] = $campaign_id;
                $formats[]           = '%d';
            }
            if ( $data ) {
                $data['updated_at'] = current_time( 'mysql', true );
                $formats[]          = '%s';
                $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $this->table,
                    $data,
                    [ 'id' => (int) $existing->id ],
                    $formats,
                    [ '%d' ]
                );
                return $this->find_by_stripe_id( $stripe_subscription_id );
            }

            return $existing;
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $this->table,
            [
                'donor_id'               => $donor_id,
                'stripe_subscription_id' => $stripe_subscription_id,
                'stripe_customer_id'     => sanitize_text_field( $stripe_customer_id ) ?: null,
                'status'                 => self::STATUS_ACTIVE,
                'currency'               => strtoupper( sanitize_text_field( $currency ) ) ?: null,
                'campaign_id'            => $campaign_id > 0 ? $campaign_id : null,
                'created_at'             => current_time( 'mysql', true ),
                'updated_at'             => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            // Course concurrente : un autre traitement a créé la ligne entre-temps.
            if ( str_contains( strtolower( (string) $wpdb->last_error ), 'duplicate entry' ) ) {
                return $this->find_by_stripe_id( $stripe_subscription_id );
            }
            return null;
        }

        return $this->find_by_stripe_id( $stripe_subscription_id );
    }

    /**
     * Marque un abonnement comme résilié à l'échéance après confirmation Stripe.
     */
    public function mark_cancel_at_period_end( int $id ): void {
        global $wpdb;

        if ( $id <= 0 ) {
            return;
        }

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            [
                'status'     => self::STATUS_CANCEL_AT_PERIOD_END,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }
}
