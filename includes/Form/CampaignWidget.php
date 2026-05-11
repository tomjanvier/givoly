<?php
/**
 * Widget de campagne — jauge de progression + formulaire de don intégré.
 *
 * Utilisé par le shortcode [givasso_campaign campaign="slug"].
 *
 * Le template est overridable dans le thème actif :
 *   {theme}/givasso/campaign/campaign.php
 *
 * @package Givasso\Form
 */

namespace Givasso\Form;

use Givasso\Domain\Entities\Campaign;
use Givasso\Repository\CampaignRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CampaignWidget {

    private string  $campaign_slug;
    private bool    $show_description;
    private bool    $show_form;
    private string  $layout;
    private string  $theme;

    public function __construct( array $atts ) {
        $this->campaign_slug    = sanitize_text_field( $atts['campaign'] ?? '' );
        $this->show_description = ( $atts['show_description'] ?? 'yes' ) !== 'no';
        $this->show_form        = ( $atts['show_form'] ?? 'yes' ) !== 'no';
        $this->layout           = in_array( $atts['layout'] ?? 'card', FormConfig::LAYOUTS, true )
            ? $atts['layout']
            : 'card';
        $this->theme            = sanitize_key( $atts['theme'] ?? FormConfig::DEFAULT_THEME );
    }

    public function render(): string {
        if ( ! $this->campaign_slug ) {
            return '';
        }

        $repo     = new CampaignRepository();
        $campaign = $repo->find_by_slug( $this->campaign_slug );

        if ( ! $campaign ) {
            return '';
        }

        $stats       = $repo->get_stats( $campaign->get_id() );
        $collected   = $stats['amount'];
        $donor_count = $stats['donors'];
        $percentage  = $campaign->get_progress_percentage( $collected );
        $is_ended    = $campaign->is_ended();

        // FormConfig pour le wrapper campagne — injecte les CSS vars (couleurs admin + thème)
        $wrapper_config = new FormConfig( [
            'theme'  => $this->theme,
            'layout' => $this->layout,
        ] );

        // Préparer le formulaire de don si la campagne est ouverte
        $donation_form = null;
        if ( $this->show_form && ! $is_ended ) {
            wp_enqueue_style( 'givasso-frontend' );
            wp_enqueue_script( 'givasso-frontend' );

            $form_config   = new FormConfig( [
                'campaign'   => $this->campaign_slug,
                'layout'     => 'flat',
                'theme'      => $this->theme,
                'show_title' => 'no',
            ] );
            $donation_form = new DonationForm( $form_config );
        }

        ob_start();
        $this->load_template( $campaign, $collected, $donor_count, $percentage, $is_ended, $donation_form, $wrapper_config, $this->show_description );
        return ob_get_clean();
    }

    private function load_template(
        Campaign      $campaign,
        float         $collected,
        int           $donor_count,
        float         $percentage,
        bool          $is_ended,
        ?DonationForm $donation_form,
        FormConfig    $config,
        bool          $show_description = true
    ): void {
        // Cherche d'abord dans le thème actif (overridable).
        $theme_path  = get_stylesheet_directory() . '/givasso/campaign/campaign.php';
        $plugin_path = GIVASSO_PLUGIN_DIR . 'templates/campaign/campaign.php';

        $path = file_exists( $theme_path ) ? $theme_path : $plugin_path;

        include $path;
    }
}
