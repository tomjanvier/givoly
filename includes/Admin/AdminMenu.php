<?php
/**
 * Déclare les pages et menus du back-office WordPress.
 *
 * @package Givasso\Admin
 */

namespace Givasso\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminMenu {

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menus' ] );
    }

    public function add_menus(): void {
        add_menu_page(
            __( 'Givasso', 'givasso' ),
            __( 'Givasso', 'givasso' ),
            'manage_options',
            'givasso-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-heart',
            30
        );

        add_submenu_page( 'givasso-dashboard',
            __( 'Tableau de bord', 'givasso' ), __( 'Tableau de bord', 'givasso' ),
            'manage_options', 'givasso-dashboard', [ $this, 'render_dashboard' ]
        );

        // load-{hook} se déclenche avant tout output — idéal pour POST + redirect
        $campaigns_hook = add_submenu_page( 'givasso-dashboard',
            __( 'Campagnes', 'givasso' ), __( 'Campagnes', 'givasso' ),
            'manage_options', 'givasso-campaigns', [ $this, 'render_campaigns' ]
        );
        add_action( 'load-' . $campaigns_hook, [ $this, 'handle_campaigns_early' ] );

        add_submenu_page( 'givasso-dashboard',
            __( 'Dons', 'givasso' ), __( 'Dons', 'givasso' ),
            'manage_options', 'givasso-donations', [ $this, 'render_donations' ]
        );

        add_submenu_page( 'givasso-dashboard',
            __( 'Donateurs', 'givasso' ), __( 'Donateurs', 'givasso' ),
            'manage_options', 'givasso-donors', [ $this, 'render_donors' ]
        );

        add_submenu_page( 'givasso-dashboard',
            __( 'Reçus fiscaux', 'givasso' ), __( 'Reçus fiscaux ✦', 'givasso' ),
            'manage_options', 'givasso-receipts', [ $this, 'render_pro_page' ]
        );

        add_submenu_page( 'givasso-dashboard',
            __( 'Abonnements', 'givasso' ), __( 'Abonnements ✦', 'givasso' ),
            'manage_options', 'givasso-subscriptions', [ $this, 'render_pro_page' ]
        );

        add_submenu_page( 'givasso-dashboard',
            __( 'Export', 'givasso' ), __( 'Export ✦', 'givasso' ),
            'manage_options', 'givasso-export', [ $this, 'render_pro_page' ]
        );

        add_submenu_page( 'givasso-dashboard',
            __( 'Réglages', 'givasso' ), __( 'Réglages', 'givasso' ),
            'manage_options', 'givasso-settings', [ $this, 'render_settings' ]
        );
    }

    public function handle_campaigns_early(): void {
        ( new \Givasso\Admin\Pages\CampaignsPage() )->handle_early();
    }

    public function render_campaigns(): void {
        ( new \Givasso\Admin\Pages\CampaignsPage() )->render();
    }

    public function render_dashboard(): void {
        ( new \Givasso\Admin\Pages\DashboardPage() )->render();
    }

    public function render_donations(): void {
        ( new \Givasso\Admin\Pages\DonationsPage() )->render();
    }

    public function render_donors(): void {
        ( new \Givasso\Admin\Pages\DonorsPage() )->render();
    }

    public function render_settings(): void {
        ( new \Givasso\Admin\Pages\SettingsPage() )->render();
    }

    public function render_pro_page(): void {
        ?>
        <div class="wrap">
            <h1>Givasso <span style="font-weight:400;color:#888;">Pro</span></h1>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:32px 40px;max-width:600px;margin-top:20px;">
                <p style="font-size:32px;margin:0 0 12px;">✦</p>
                <h2 style="margin:0 0 12px;font-size:20px;"><?php esc_html_e( 'Fonctionnalité Pro', 'givasso' ); ?></h2>
                <p style="color:#555;margin:0 0 20px;">
                    <?php esc_html_e( 'Cette fonctionnalité est disponible dans Givasso Pro : reçus fiscaux CERFA 2041-RD, dons récurrents, exports CSV/FEC DGFiP.', 'givasso' ); ?>
                </p>
                <a href="https://givasso.fr/pro" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    <?php esc_html_e( 'Découvrir Givasso Pro →', 'givasso' ); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
