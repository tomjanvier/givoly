<?php
/**
 * Accès aux données des campagnes.
 *
 * Toutes les requêtes passent par $wpdb->prepare().
 * Ce repository est le seul endroit autorisé à lire/écrire givoly_campaigns.
 *
 * @package Givoly\Repository
 */

namespace Givoly\Repository;

use Givoly\Domain\Entities\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CampaignRepository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'givoly_campaigns';
    }

    public function find_by_id( int $id ): ?Campaign {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    public function find_by_slug( string $slug ): ?Campaign {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s LIMIT 1", $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    /**
     * Toutes les campagnes actives, triées par date de création décroissante.
     *
     * @return Campaign[]
     */
    public function find_active(): array {
        global $wpdb;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                Campaign::STATUS_ACTIVE
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return array_map( [ $this, 'hydrate' ], $rows ?: [] );
    }

    /**
     * Toutes les campagnes (pour la liste admin), triées par date décroissante.
     *
     * @return Campaign[]
     */
    public function find_all(): array {
        global $wpdb;

        // Pas de variable utilisateur ici — pas besoin de prepare().
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$this->table} ORDER BY created_at DESC",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return array_map( [ $this, 'hydrate' ], $rows ?: [] );
    }

    /**
     * Montant total collecté + nombre de donateurs uniques pour une campagne.
     * Une seule requête pour éviter le double aller-retour DB.
     *
     * @return array{amount: float, donors: int}
     */
    public function get_stats( int $campaign_id ): array {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COALESCE( SUM(amount), 0 ) AS amount,
                        COUNT( DISTINCT donor_id )  AS donors
                 FROM {$wpdb->prefix}givoly_donations
                 WHERE campaign_id = %d AND status = 'completed'",
                $campaign_id
            ),
            ARRAY_A
        );

        return [
            'amount' => (float) ( $row['amount'] ?? 0 ),
            'donors' => (int)   ( $row['donors'] ?? 0 ),
        ];
    }

    /**
     * Montant total collecté pour une campagne (dons complétés uniquement).
     * Préférer get_stats() quand on a aussi besoin du nombre de donateurs.
     */
    public function get_collected_amount( int $campaign_id ): float {
        return $this->get_stats( $campaign_id )['amount'];
    }

    /**
     * Nombre de donateurs uniques pour une campagne.
     * Préférer get_stats() quand on a aussi besoin du montant.
     */
    public function get_donor_count( int $campaign_id ): int {
        return $this->get_stats( $campaign_id )['donors'];
    }

    /**
     * Stats agrégées pour plusieurs campagnes en une seule requête.
     * Évite le N+1 dans les listes admin.
     *
     * @param int[] $campaign_ids
     * @return array<int, array{amount: float, donors: int}>  Indexé par campaign_id
     */
    public function get_stats_batch( array $campaign_ids ): array {
        global $wpdb;

        if ( empty( $campaign_ids ) ) {
            return [];
        }

        $ids          = array_map( 'intval', $campaign_ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $placeholders built by array_fill('%d'), table name from $wpdb->prefix (trusted)
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT campaign_id, COALESCE( SUM(amount), 0 ) AS amount, COUNT( DISTINCT donor_id ) AS donors FROM {$wpdb->prefix}givoly_donations WHERE campaign_id IN ($placeholders) AND status = 'completed' GROUP BY campaign_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter
                ...$ids
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter

        $result = [];
        foreach ( $rows as $row ) {
            $result[ (int) $row['campaign_id'] ] = [
                'amount' => (float) $row['amount'],
                'donors' => (int)   $row['donors'],
            ];
        }

        // Initialiser à zéro pour les campagnes sans dons
        foreach ( $ids as $id ) {
            $result[$id] ??= [ 'amount' => 0.0, 'donors' => 0 ];
        }

        return $result;
    }

    /**
     * Vérifie si un slug est déjà utilisé par une autre campagne.
     *
     * @param string $slug   Slug à vérifier.
     * @param int    $exclude_id  ID de la campagne à exclure (0 pour une création).
     */
    public function slug_exists( string $slug, int $exclude_id = 0 ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE slug = %s AND id != %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $slug,
                $exclude_id
            )
        );
    }

    /**
     * Archive une campagne (jamais de suppression physique).
     */
    public function archive( int $id ): void {
        global $wpdb;

        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            [ 'status' => Campaign::STATUS_ARCHIVED ],
            [ 'id'     => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Crée ou met à jour une campagne.
     * Si id === 0 → INSERT, sinon UPDATE.
     */
    public function save( Campaign $campaign ): Campaign {
        global $wpdb;

        $data = [
            'title'          => $campaign->get_title(),
            'slug'           => $campaign->get_slug(),
            'description'    => $campaign->get_description(),
            'goal_amount'    => $campaign->get_goal_amount(),
            'currency'       => $campaign->get_currency(),
            'start_date'     => $campaign->get_start_date()?->format( 'Y-m-d' ),
            'end_date'       => $campaign->get_end_date()?->format( 'Y-m-d' ),
            'status'         => $campaign->get_status(),
            'featured_image' => $campaign->get_featured_image(),
        ];

        $formats = [ '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d' ];

        if ( $campaign->get_id() === 0 ) {
            $wpdb->insert( $this->table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $id = (int) $wpdb->insert_id;
        } else {
            $wpdb->update( $this->table, $data, [ 'id' => $campaign->get_id() ], $formats, [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $id = $campaign->get_id();
        }

        // Retourne l'entité avec l'id définitif (utile après INSERT).
        return $this->find_by_id( $id ) ?? $campaign;
    }

    // ── Hydratation ────────────────────────────────────────────────────────

    private function hydrate( array $row ): Campaign {
        return new Campaign(
            id:             (int) $row['id'],
            title:          $row['title'],
            slug:           $row['slug'],
            status:         $row['status'],
            currency:       $row['currency'],
            description:    $row['description'] ?: null,
            goal_amount:    $row['goal_amount'] !== null ? (float) $row['goal_amount'] : null,
            start_date:     $row['start_date'] ? new \DateTimeImmutable( $row['start_date'] ) : null,
            end_date:       $row['end_date']   ? new \DateTimeImmutable( $row['end_date'] )   : null,
            featured_image: $row['featured_image'] ? (int) $row['featured_image'] : null,
        );
    }
}
