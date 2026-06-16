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
 * @package Givoly\Form
 */

namespace Givoly\Form;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonationForm {

    private const BRANDING_URL = 'https://givoly.org';
    private const BRANDING_LOGO_URL = 'https://givoly.org/wp-content/uploads/2026/06/Black-and-Red-Foundation-Community-Non-Profit-Logo.png';

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

    public static function output_branding(): void {
        ?>
        <div class="givoly-branding" data-givoly-branding="required" aria-label="<?php esc_attr_e( 'Propulsé par Givoly', 'givoly' ); ?>">
            <a class="givoly-branding__link" href="<?php echo esc_url( self::BRANDING_URL ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Découvrir Givoly', 'givoly' ); ?>">
                <img class="givoly-branding__logo" src="<?php echo esc_url( self::BRANDING_LOGO_URL ); ?>" alt="Givoly" loading="lazy" decoding="async">
            </a>
        </div>
        <?php
    }

    public static function get_branding_html(): string {
        ob_start();
        self::output_branding();
        return ob_get_clean();
    }

    private function load_template( string $layout ): void {
        // flat utilise la même structure que card — seule la classe CSS diffère.
        $template = ( $layout === 'flat' ) ? 'card' : $layout;
        $path     = GIVOLY_PLUGIN_DIR . "templates/form/{$template}.php";

        // Fallback sur le layout card si le fichier n'existe pas.
        if ( ! file_exists( $path ) ) {
            $path = GIVOLY_PLUGIN_DIR . 'templates/form/card.php';
        }

        // $config est accessible dans le template.
        $config = $this->config;
        include $path;
    }
}
