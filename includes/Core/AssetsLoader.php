<?php
/**
 * Registers and loads plugin assets.
 *
 * Assets are registered globally and enqueued only where they are needed.
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

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

        wp_register_script(
            'givoly-frontend',
            GIVOLY_PLUGIN_URL . 'assets/js/givoly-frontend.js',
            [],
            GIVOLY_VERSION,
            true
        );

        wp_register_style(
            'givoly-donor-space',
            GIVOLY_PLUGIN_URL . 'assets/css/givoly-donor-space.css',
            [],
            GIVOLY_VERSION
        );

        wp_register_script(
            'givoly-donor-space',
            GIVOLY_PLUGIN_URL . 'assets/js/givoly-donor-space.js',
            [],
            GIVOLY_VERSION,
            true
        );
    }

    /**
     * Localizes the frontend script with the data a rendered form needs.
     *
     * Must be called only when a form is actually rendered (shortcode or
     * campaign widget), not on every frontend page.
     */
    public static function localize_frontend(): void {
        static $localized = false;
        if ( $localized ) {
            return;
        }
        $localized = true;

        $is_success_return = filter_input( INPUT_GET, 'givoly_success', FILTER_VALIDATE_BOOLEAN );

        wp_localize_script(
            'givoly-frontend',
            'givolyData',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
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
        $page             = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_givoly_page   = str_contains( $hook, 'givoly' );
        $is_dashboard     = 'index.php' === $hook;

        if ( ! $is_givoly_page && ! $is_dashboard ) {
            return;
        }

        wp_enqueue_style(
            'givoly-admin',
            GIVOLY_PLUGIN_URL . 'assets/css/givoly-admin.css',
            [],
            GIVOLY_VERSION
        );

        if ( in_array( $page, [ 'givoly-campaigns', 'givoly-settings', 'givoly-dashboard' ], true ) ) {
            wp_enqueue_script(
                'givoly-admin',
                GIVOLY_PLUGIN_URL . 'assets/js/givoly-admin.js',
                [],
                GIVOLY_VERSION,
                true
            );
        }
    }
}
