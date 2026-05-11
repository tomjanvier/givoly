<?php
/**
 * Rendu du formulaire de don.
 *
 * Cette classe orchestre uniquement le rendu.
 * Toute la configuration est dans FormConfig.
 * Tout le HTML est dans les templates (templates/form/).
 *
 * Pour ajouter un nouveau layout :
 * 1. Créer templates/form/{nom}.php
 * 2. Ajouter le nom dans FormConfig::LAYOUTS
 *
 * @package Givasso\Form
 */

namespace Givasso\Form;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonationForm {

    public function __construct( private readonly FormConfig $config ) {}

    public function render(): string {
        ob_start();
        $this->load_template( $this->config->layout );
        return ob_get_clean();
    }

    /**
     * Outputs the form directly (no ob_start, no raw echo of a string blob).
     * Use this in templates instead of echo render().
     */
    public function output(): void {
        $this->load_template( $this->config->layout );
    }

    private function load_template( string $layout ): void {
        // flat utilise la même structure que card — seule la classe CSS diffère.
        $template = ( $layout === 'flat' ) ? 'card' : $layout;
        $path     = GIVASSO_PLUGIN_DIR . "templates/form/{$template}.php";

        // Fallback sur le layout card si le fichier n'existe pas.
        if ( ! file_exists( $path ) ) {
            $path = GIVASSO_PLUGIN_DIR . 'templates/form/card.php';
        }

        // $config est accessible dans le template.
        $config = $this->config;
        include $path;
    }
}
