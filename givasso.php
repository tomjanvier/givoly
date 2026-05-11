<?php
/**
 * Plugin Name: Givasso
 * Plugin URI:  https://www.givasso.fr
 * Description: Donation forms for French nonprofits. Customizable forms, HelloAsso & Stripe integration, automated CERFA 2041-RD tax receipts.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:      Givasso
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: givasso
 * Domain Path:       /languages
 *
 * @package Givasso
 */

// Sécurité : interdire l'accès direct au fichier.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────
// Constantes globales du plugin
// ─────────────────────────────────────────────

define( 'GIVASSO_VERSION',          '1.0.0' );
define( 'GIVASSO_PLUGIN_FILE',      __FILE__ );
define( 'GIVASSO_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'GIVASSO_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );
define( 'GIVASSO_PLUGIN_BASENAME',  plugin_basename( __FILE__ ) );

// ─────────────────────────────────────────────
// Autoloader PSR-4 minimal (sans Composer pour l'instant)
// ─────────────────────────────────────────────

spl_autoload_register( function ( string $class ): void {
    $prefix   = 'Givasso\\';
    $base_dir = GIVASSO_PLUGIN_DIR . 'includes/';

    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// ─────────────────────────────────────────────
// Hooks d'activation / désactivation
// ─────────────────────────────────────────────

register_activation_hook(   __FILE__, [ 'Givasso\\Core\\Installer', 'activate'   ] );
register_deactivation_hook( __FILE__, [ 'Givasso\\Core\\Installer', 'deactivate' ] );

// ─────────────────────────────────────────────
// Démarrage du plugin
// ─────────────────────────────────────────────

add_action( 'plugins_loaded', function (): void {
    \Givasso\Core\Plugin::get_instance()->boot();
} );
