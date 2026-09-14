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
        [ $total, $count, $campaign_obj, $by_currency ] = $this->fetch_data();

        if ( $this->display === 'bar' && $campaign_obj && $campaign_obj->has_goal() ) {
            wp_enqueue_style( 'givoly-frontend' );

            $pct = $campaign_obj->get_progress_percentage( $total );
            return sprintf(
                '<div class="givoly-total givoly-total--bar" role="img" aria-label="%2$s" style="background:var(--givoly-campaign-bar-bg,#e9ecef);border-radius:8px;height:12px;overflow:hidden;">'
                . '<div role="progressbar" aria-valuenow="%1$s" aria-valuemin="0" aria-valuemax="100" aria-valuetext="%1$s%%" '
                . 'style="background:var(--givoly-campaign-bar-fill,#28a745);height:100%%;width:%1$s%%;border-radius:8px;"></div>'
                . '</div>',
                esc_attr( (string) $pct ),
                esc_attr( sprintf( __( '%s collected', 'givoly' ), $this->format_total( $total, $campaign_obj->get_currency(), $by_currency ) ) )
            );
        }

        $effective_format = $this->display ?: $this->format;

        return match ( $effective_format ) {
            // translators: %d: number of donations.
            'count'  => '<span class="givoly-total givoly-total--count">'
                        . esc_html( sprintf( _n( '%d donation', '%d donations', $count, 'givoly' ), $count ) )
                        . '</span>',
            default  => '<span class="givoly-total givoly-total--amount">'
                        . esc_html( $this->format_total( $total, $campaign_obj?->get_currency() ?? '', $by_currency ) )
                        . '</span>',
        };
    }

    /**
     * Formate un total : devise de la campagne si connue, sinon un total séparé
     * par devise (jamais de somme inter-devises).
     *
     * @param array<string, float> $by_currency
     */
    private function format_total( float $total, string $currency, array $by_currency = [] ): string {
        if ( $currency !== '' ) {
            return number_format( $total, 2, ',', ' ' ) . ' ' . FormConfig::currency_symbol( strtoupper( $currency ) );
        }

        if ( ! empty( $by_currency ) ) {
            $parts = [];
            foreach ( $by_currency as $code => $amount ) {
                $parts[] = number_format( (float) $amount, 2, ',', ' ' ) . ' ' . FormConfig::currency_symbol( strtoupper( (string) $code ) );
            }

            return implode( ' + ', $parts );
        }

        return number_format( $total, 2, ',', ' ' ) . ' ' . FormConfig::currency_symbol( 'EUR' );
    }

    /**
     * Récupère total, count et l'objet campagne.
     *
     * Stratégie de requête :
     *  1. Si le slug correspond à une vraie campagne en DB → filtre par campaign_id (v0.7+)
     *  2. Sinon → fallback sur donor_message (rétrocompat campagnes pré-v0.7)
     *  3. Sans slug → toutes les donations complétées, ventilées par devise
     *
     * @return array{float, int, ?\Givoly\Domain\Entities\Campaign, array<string, float>}
     */
    private function fetch_data(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'givoly_donations';

        if ( $this->campaign !== '' ) {
            $repo         = new CampaignRepository();
            $campaign_obj = $repo->find_by_slug( $this->campaign );

            if ( $campaign_obj ) {
                // Statistiques dans la devise propre de la campagne.
                $stats = $repo->get_stats( $campaign_obj->get_id(), $campaign_obj->get_currency() );
                return [ $stats['amount'], $stats['donors'], $campaign_obj, [] ];
            }

            // Backward compatibility for legacy campaign values stored in donor_message.
            $campaign_obj = null;
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT currency, COALESCE( SUM(amount), 0 ) AS total, COUNT(*) AS cnt FROM {$table} WHERE donor_message = %s AND status = 'completed' GROUP BY currency", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $this->campaign
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

            $total = 0.0;
            $count = 0;
            $by_currency = [];
            foreach ( (array) $rows as $legacy_row ) {
                $code = strtoupper( (string) ( $legacy_row['currency'] ?? 'EUR' ) );
                $by_currency[ $code ] = (float) ( $legacy_row['total'] ?? 0 );
                $total += (float) ( $legacy_row['total'] ?? 0 );
                $count += (int) ( $legacy_row['cnt'] ?? 0 );
            }

            return [ $total, $count, null, $by_currency ];
        }

        // Total global : ventilation par devise, jamais de somme inter-devises.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix (trusted)
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT currency, COALESCE( SUM(amount), 0 ) AS total, COUNT(*) AS cnt FROM {$table} WHERE status = 'completed' GROUP BY currency", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        $total = 0.0;
        $count = 0;
        $by_currency = [];
        foreach ( (array) $rows as $global_row ) {
            $code = strtoupper( (string) ( $global_row['currency'] ?? 'EUR' ) );
            $by_currency[ $code ] = (float) ( $global_row['total'] ?? 0 );
            $total += (float) ( $global_row['total'] ?? 0 );
            $count += (int) ( $global_row['cnt'] ?? 0 );
        }

        return [ $total, $count, null, $by_currency ];
    }
}
