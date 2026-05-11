<?php

/**
 * Enregistrement et chargement des assets CSS/JS.
 *
 * Principe : register partout, enqueue uniquement où c'est nécessaire.
 *
 * @package Givasso\Core
 */

namespace Givasso\Core;

if (! defined('ABSPATH')) {
    exit;
}

final class AssetsLoader
{

    public function register(): void
    {
        add_action('wp_enqueue_scripts',    [$this, 'register_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_frontend_assets(): void
    {
        wp_register_style(
            'givasso-frontend',
            GIVASSO_PLUGIN_URL . 'assets/css/givasso-frontend.css',
            [],
            GIVASSO_VERSION
        );

        wp_register_script(
            'givasso-frontend',
            GIVASSO_PLUGIN_URL . 'assets/js/givasso-frontend.js',
            [], // Vanilla JS — aucune dépendance
            GIVASSO_VERSION,
            true
        );

        wp_localize_script('givasso-frontend', 'givassoData', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('givasso_frontend_nonce'),
            'success'     => ! empty( $_GET['givasso_success'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect parameter only, no sensitive data
            'i18n'        => [
                'error'           => __('Une erreur est survenue. Veuillez réessayer.', 'givasso'),
                'invalid_amount'  => __('Veuillez sélectionner ou saisir un montant valide (min. 1 €).', 'givasso'),
                'invalid_email'   => __('Veuillez saisir une adresse email valide.', 'givasso'),
                'invalid_name'    => __('Veuillez saisir votre prénom et votre nom.', 'givasso'),
                'success_message' => __('Merci pour votre don ! Votre générosité fait la différence.', 'givasso'),
            ],
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (! str_contains($hook, 'givasso')) {
            return;
        }

        wp_enqueue_style(
            'givasso-admin',
            GIVASSO_PLUGIN_URL . 'assets/css/givasso-admin.css',
            [],
            GIVASSO_VERSION
        );

        wp_enqueue_script(
            'givasso-admin',
            GIVASSO_PLUGIN_URL . 'assets/js/givasso-admin.js',
            ['jquery'],
            GIVASSO_VERSION,
            true
        );
    }
}
