<?php
/**
 * Déclare les pages et menus du back-office WordPress.
 *
 * @package Givoly\Admin
 */

namespace Givoly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminMenu {

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menus' ] );
        add_action( 'admin_notices', [ $this, 'render_support_header' ] );
    }

    public function add_menus(): void {
        add_menu_page(
            __( 'Givoly', 'givoly' ),
            __( 'Givoly', 'givoly' ),
            'manage_options',
            'givoly-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-heart',
            30
        );

        add_submenu_page( 'givoly-dashboard',
            __( 'Tableau de bord', 'givoly' ), __( 'Tableau de bord', 'givoly' ),
            'manage_options', 'givoly-dashboard', [ $this, 'render_dashboard' ]
        );

        // load-{hook} se déclenche avant tout output — idéal pour POST + redirect
        $campaigns_hook = add_submenu_page( 'givoly-dashboard',
            __( 'Campagnes', 'givoly' ), __( 'Campagnes', 'givoly' ),
            'manage_options', 'givoly-campaigns', [ $this, 'render_campaigns' ]
        );
        add_action( 'load-' . $campaigns_hook, [ $this, 'handle_campaigns_early' ] );

        add_submenu_page( 'givoly-dashboard',
            __( 'Dons', 'givoly' ), __( 'Dons', 'givoly' ),
            'manage_options', 'givoly-donations', [ $this, 'render_donations' ]
        );

        add_submenu_page( 'givoly-dashboard',
            __( 'Donateurs', 'givoly' ), __( 'Donateurs', 'givoly' ),
            'manage_options', 'givoly-donors', [ $this, 'render_donors' ]
        );


        add_submenu_page( 'givoly-dashboard',
            __( 'Réglages', 'givoly' ), __( 'Réglages', 'givoly' ),
            'manage_options', 'givoly-settings', [ $this, 'render_settings' ]
        );
    }

    public function render_support_header(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! str_starts_with( $page, 'givoly-' ) ) {
            return;
        }
        ?>
        <div class="givoly-admin-support">
            <img class="givoly-admin-support__logo" src="<?php echo esc_url( GIVOLY_PLUGIN_URL . 'logo.png' ); ?>" alt="Givoly">
            <a class="button button-primary givoly-admin-support__button" href="<?php echo esc_url( 'https://givoly.org/don' ); ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e( 'Donner pour soutenir le plugin', 'givoly' ); ?>
            </a>
        </div>
        <?php
    }

    public function handle_campaigns_early(): void {
        ( new \Givoly\Admin\Pages\CampaignsPage() )->handle_early();
    }

    public function render_campaigns(): void {
        ( new \Givoly\Admin\Pages\CampaignsPage() )->render();
    }

    public function render_dashboard(): void {
        ( new \Givoly\Admin\Pages\DashboardPage() )->render();
    }

    public function render_donations(): void {
        ( new \Givoly\Admin\Pages\DonationsPage() )->render();
    }

    public function render_donors(): void {
        ( new \Givoly\Admin\Pages\DonorsPage() )->render();
    }

    public function render_settings(): void {
        ( new \Givoly\Admin\Pages\SettingsPage() )->render();
    }

}
