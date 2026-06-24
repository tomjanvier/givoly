<?php
/**
 * Enregistre tous les shortcodes du plugin.
 *
 * Usage :
 *   [givoly_form]
 *   [givoly_form campaign="ramadan-2025" amounts="10,25,50,100"]
 *   [givoly_total campaign="ramadan-2025"]
 *
 * @package Givoly\Form
 */

namespace Givoly\Form;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// CampaignWidget et CampaignTotalWidget sont dans le même namespace — pas de use nécessaire.

final class ShortcodeManager {

    public function register(): void {
        add_shortcode( 'givoly_form',     [ $this, 'render_form' ] );
        add_shortcode( 'givoly_total',    [ $this, 'render_total' ] );
        add_shortcode( 'givoly_campaign', [ $this, 'render_campaign' ] );
    }

    public function render_form( $atts ): string {
        $atts = shortcode_atts( [
            'campaign'    => '',
            'amounts'     => '10,25,50,100',
            'currency'    => 'EUR',
            'show_title'  => 'yes',
            'theme'       => 'givoly',
            'layout'      => 'card',   /* card | inline | flat */
            'title'       => '',
            'button_text' => '',
            'gateway'     => '',
            'class'       => '',
            'css'         => '',
        ], $atts, 'givoly_form' );

        // Charger les assets uniquement quand le shortcode est présent
        wp_enqueue_style( 'givoly-frontend' );
        wp_enqueue_script( 'givoly-frontend' );

        $config = new FormConfig( $atts );

        return ( new DonationForm( $config ) )->render();
    }

    public function render_total( $atts ): string {
        $atts = shortcode_atts( [
            'campaign' => '',
            'format'   => 'amount',
            'display'  => '',
        ], $atts, 'givoly_total' );

        return ( new CampaignTotalWidget( $atts ) )->render();
    }

    public function render_campaign( $atts ): string {
        $atts = shortcode_atts( [
            'campaign'         => '',
            'show_description' => 'yes',
            'show_form'        => 'yes',
            'show_title'       => 'yes',
            'show_form_title'  => 'no',
            'layout'           => 'card',
            'theme'            => 'classic',
        ], $atts, 'givoly_campaign' );

        return ( new CampaignWidget( $atts ) )->render();
    }
}
