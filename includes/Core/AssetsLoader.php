<?php
/**
 * Registers and loads plugin assets.
 *
 * Assets are registered globally and enqueued only where they are needed.
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AssetsLoader {

    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function register_frontend_assets(): void {
        wp_register_style(
            'givoly-frontend',
            GIVOLY_PLUGIN_URL . 'assets/css/givoly-frontend.css',
            [],
            GIVOLY_VERSION
        );

        $custom_css = Settings::get_appearance_custom_css();
        if ( $custom_css !== '' ) {
            wp_add_inline_style( 'givoly-frontend', $custom_css );
        }

        wp_register_script(
            'givoly-frontend',
            GIVOLY_PLUGIN_URL . 'assets/js/givoly-frontend.js',
            [],
            GIVOLY_VERSION,
            true
        );

        $is_success_return = filter_input( INPUT_GET, 'givoly_success', FILTER_VALIDATE_BOOLEAN );

        wp_localize_script(
            'givoly-frontend',
            'givolyData',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'givoly_frontend_nonce' ),
                'success'  => (bool) $is_success_return,
                'branding' => \Givoly\Form\DonationForm::get_branding_html(),
                'i18n'     => [
                    'error'           => __( 'Une erreur est survenue. Veuillez réessayer.', 'givoly' ),
                    'invalid_amount'  => __( 'Veuillez sélectionner ou saisir un montant valide (min. 1 €).', 'givoly' ),
                    'invalid_email'   => __( 'Veuillez saisir une adresse email valide.', 'givoly' ),
                    'invalid_name'    => __( 'Veuillez saisir votre prénom et votre nom.', 'givoly' ),
                    'success_message' => __( 'Merci pour votre don ! Votre générosité fait la différence.', 'givoly' ),
                ],
            ]
        );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'givoly' ) ) {
            return;
        }

        wp_enqueue_style(
            'givoly-admin',
            GIVOLY_PLUGIN_URL . 'assets/css/givoly-admin.css',
            [],
            GIVOLY_VERSION
        );

        wp_enqueue_script(
            'givoly-admin',
            GIVOLY_PLUGIN_URL . 'assets/js/givoly-admin.js',
            [ 'jquery' ],
            GIVOLY_VERSION,
            true
        );
    }
}
