<?php

/**
 * Enregistrement et chargement des assets CSS/JS.
 *
 * Principe : register partout, enqueue uniquement où c'est nécessaire.
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

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
            'givoly-frontend',
            GIVOLY_PLUGIN_URL . 'assets/css/givoly-frontend.css',
            [],
            GIVOLY_VERSION
        );

        wp_register_script(
            'givoly-frontend',
            GIVOLY_PLUGIN_URL . 'assets/js/givoly-frontend.js',
            [], // Vanilla JS — aucune dépendance
            GIVOLY_VERSION,
            true
        );

        wp_localize_script('givoly-frontend', 'givolyData', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('givoly_frontend_nonce'),
            'success'     => ! empty( $_GET['givoly_success'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect parameter only, no sensitive data
            'i18n'        => [
                'error'           => __('Une erreur est survenue. Veuillez réessayer.', 'givoly'),
                'invalid_amount'  => __('Veuillez sélectionner ou saisir un montant valide (min. 1 €).', 'givoly'),
                'invalid_email'   => __('Veuillez saisir une adresse email valide.', 'givoly'),
                'invalid_name'    => __('Veuillez saisir votre prénom et votre nom.', 'givoly'),
                'success_message' => __('Merci pour votre don ! Votre générosité fait la différence.', 'givoly'),
            ],
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (! str_contains($hook, 'givoly')) {
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
            ['jquery'],
            GIVOLY_VERSION,
            true
        );
    }
}
