<?php
/**
 * Plugin Name: Givoly
 * Plugin URI:       https://givoly.org
 * Description:      Givoly is a completely free donation form extension designed by and for nonprofit organizations, featuring payments via Stripe and HelloAsso, donor management, and campaign progress tracking.
 * Version:          1.0.0
 * Requires at least: 6.0
 * Requires PHP:     8.1
 * Author:           PLAID·ACT
 * Author URI:       https://plaidact.org
 * License:          GPL v2 or later
 * License URI:      https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:      givoly
 * Domain Path:      /languages
 *
 * @package Givoly
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GIVOLY_VERSION', '1.0.0' );
define( 'GIVOLY_PLUGIN_FILE', __FILE__ );
define( 'GIVOLY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIVOLY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GIVOLY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Lightweight PSR-4 autoloader for the plugin namespace.

spl_autoload_register( function ( string $class ): void {
    $prefix   = 'Givoly\\';
    $base_dir = GIVOLY_PLUGIN_DIR . 'includes/';

    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

register_activation_hook( __FILE__, [ 'Givoly\\Core\\Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Givoly\\Core\\Installer', 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
    \Givoly\Core\Plugin::get_instance()->boot();
} );
