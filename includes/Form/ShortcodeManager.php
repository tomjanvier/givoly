<?php
/**
 * Enregistre tous les shortcodes du plugin.
 *
 * Usage :
 *   [givasso_form]
 *   [givasso_form campaign="ramadan-2025" amounts="10,25,50,100"]
 *   [givasso_total campaign="ramadan-2025"]
 *
 * @package Givasso\Form
 */

namespace Givasso\Form;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// CampaignWidget et CampaignTotalWidget sont dans le même namespace — pas de use nécessaire.

final class ShortcodeManager {

    public function register(): void {
        add_shortcode( 'givasso_form',     [ $this, 'render_form' ] );
        add_shortcode( 'givasso_total',    [ $this, 'render_total' ] );
        add_shortcode( 'givasso_campaign', [ $this, 'render_campaign' ] );
    }

    public function render_form( $atts ): string {
        $atts = shortcode_atts( [
            'campaign'    => '',
            'amounts'     => '10,25,50,100',
            'currency'    => 'EUR',
            'show_title'  => 'yes',
            'theme'       => 'givasso',
            'layout'      => 'card',   /* card | inline | flat */
            'title'       => '',
            'button_text' => '',
            'gateway'     => '',
            'frequency'   => '',
        ], $atts, 'givasso_form' );

        // Charger les assets uniquement quand le shortcode est présent
        wp_enqueue_style( 'givasso-frontend' );
        wp_enqueue_script( 'givasso-frontend' );

        $config = new FormConfig( $atts );

        return ( new DonationForm( $config ) )->render();
    }

    public function render_total( $atts ): string {
        $atts = shortcode_atts( [
            'campaign' => '',
            'format'   => 'amount',
            'display'  => '',
        ], $atts, 'givasso_total' );

        return ( new CampaignTotalWidget( $atts ) )->render();
    }

    public function render_campaign( $atts ): string {
        $atts = shortcode_atts( [
            'campaign'         => '',
            'show_description' => 'yes',
            'show_form'        => 'yes',
            'layout'           => 'card',
            'theme'            => 'classic',
        ], $atts, 'givasso_campaign' );

        return ( new CampaignWidget( $atts ) )->render();
    }
}
