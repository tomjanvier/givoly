<?php
/**
 * Widget affichant le total collecté pour une campagne.
 *
 * Usage shortcode : [givoly_total campaign="ramadan-2025" format="amount" display="bar"]
 *
 * Stratégie de requête (v0.7+) :
 *  - Si slug correspond à une campagne en DB → filtre par campaign_id
 *  - Sinon → fallback sur donor_message (rétrocompat pré-v0.7)
 *
 * @package Givoly\Form
 */

namespace Givoly\Form;

use Givoly\Repository\CampaignRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CampaignTotalWidget {

    private string $campaign;
    private string $format;
    private string $display;

    public function __construct( array $atts ) {
        $this->campaign = sanitize_text_field( $atts['campaign'] ?? '' );
        $this->format   = in_array( $atts['format'] ?? '', [ 'amount', 'count' ], true )
            ? $atts['format']
            : 'amount';
        $this->display  = in_array( $atts['display'] ?? '', [ 'amount', 'count', 'bar' ], true )
            ? $atts['display']
            : '';
    }

    public function render(): string {
        [ $total, $count, $campaign_obj ] = $this->fetch_data();

        // display="bar" : jauge autonome (nécessite une campagne avec objectif)
        if ( $this->display === 'bar' && $campaign_obj && $campaign_obj->has_goal() ) {
            $pct = $campaign_obj->get_progress_percentage( $total );
            return sprintf(
                '<div class="givoly-total givoly-total--bar" style="background:var(--givoly-campaign-bar-bg,#e9ecef);border-radius:8px;height:12px;overflow:hidden;">'
                . '<div role="progressbar" aria-valuenow="%1$s" aria-valuemin="0" aria-valuemax="100" '
                . 'style="background:var(--givoly-campaign-bar-fill,#28a745);height:100%%;width:%1$s%%;border-radius:8px;"></div>'
                . '</div>',
                esc_attr( $pct )
            );
        }

        $effective_format = $this->display ?: $this->format;

        return match ( $effective_format ) {
            'count'  => '<span class="givoly-total givoly-total--count">'
                        . esc_html( $count . ' don' . ( $count > 1 ? 's' : '' ) )
                        . '</span>',
            default  => '<span class="givoly-total givoly-total--amount">'
                        . esc_html( number_format( $total, 2, ',', ' ' ) . ' €' )
                        . '</span>',
        };
    }

    /**
     * Récupère total, count et l'objet campagne.
     *
     * Stratégie de requête :
     *  1. Si le slug correspond à une vraie campagne en DB → filtre par campaign_id (v0.7+)
     *  2. Sinon → fallback sur donor_message (rétrocompat campagnes pré-v0.7)
     *  3. Sans slug → toutes les donations complétées
     *
     * @return array{float, int, ?\Givoly\Domain\Entities\Campaign}
     */
    private function fetch_data(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'givoly_donations';

        if ( $this->campaign !== '' ) {
            $repo         = new CampaignRepository();
            $campaign_obj = $repo->find_by_slug( $this->campaign );

            if ( $campaign_obj ) {
                // Campagne v0.7+ — une requête agrégée via repository
                $stats = $repo->get_stats( $campaign_obj->get_id() );
                return [ $stats['amount'], $stats['donors'], $campaign_obj ];
            }

            // Rétrocompat — campagne pré-v0.7 (donor_message), une seule requête
            $campaign_obj = null;
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
            $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT COALESCE( SUM(amount), 0 ) AS total, COUNT(*) AS cnt FROM {$table} WHERE donor_message = %s AND status = 'completed'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $this->campaign
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

            return [ (float) ( $row['total'] ?? 0 ), (int) ( $row['cnt'] ?? 0 ), null ];
        }

        // Toutes campagnes confondues — une seule requête
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COALESCE( SUM(amount), 0 ) AS total, COUNT(*) AS cnt FROM {$table} WHERE status = 'completed'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return [ (float) ( $row['total'] ?? 0 ), (int) ( $row['cnt'] ?? 0 ), null ];
    }
}
