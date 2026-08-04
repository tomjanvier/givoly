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
        add_action( 'wp_dashboard_setup', [ $this, 'register_wordpress_dashboard_widget' ] );
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

        $support_utm = [
            'utm_source'   => 'givoly',
            'utm_medium'   => 'plugin_admin',
            'utm_campaign' => 'support',
        ];
        $givoly_url   = add_query_arg( $support_utm, 'https://givoly.org' );
        $plaidact_url = add_query_arg( $support_utm, 'https://plaidact.org' );
        $donate_url   = add_query_arg( [ 'utm_campaign' => 'support_donation' ] + $support_utm, 'https://plaidact.org/don/' );
        ?>
        <section class="givoly-admin-support" aria-labelledby="givoly-support-title">
            <div class="givoly-admin-support__copy">
                <p class="givoly-admin-support__title" id="givoly-support-title">
                    <span class="givoly-admin-support__heart" aria-hidden="true">♥</span>
                    <?php esc_html_e( 'Gratuit, associatif, sans mauvaise surprise.', 'givoly' ); ?>
                </p>
                <p class="givoly-admin-support__text">
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: 1: PLAID·ACT link, 2: Givoly link. */
                            __( '%2$s est maintenu par %1$s, une association à but non lucratif de défense des Droits humains. L’objectif est simple : proposer aux associations un outil clair pour recevoir des dons en ligne depuis WordPress, sans abonnement imposé et sans commission ajoutée par le plugin.', 'givoly' ),
                            '<a href="' . esc_url( $plaidact_url ) . '" target="_blank" rel="noopener noreferrer">PLAID·ACT</a>',
                            '<a href="' . esc_url( $givoly_url ) . '" target="_blank" rel="noopener noreferrer">Givoly</a>'
                        ),
                        [ 'a' => [ 'href' => true, 'target' => true, 'rel' => true ] ]
                    );
                    ?>
                </p>
            </div>
            <div class="givoly-admin-support__actions">
                <a class="givoly-admin-support__link" href="<?php echo esc_url( $givoly_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Découvrir Givoly', 'givoly' ); ?>
                </a>
                <a class="givoly-admin-support__link" href="<?php echo esc_url( $plaidact_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'PLAID·ACT', 'givoly' ); ?>
                </a>
                <a class="button button-primary givoly-admin-support__button" href="<?php echo esc_url( $donate_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <span aria-hidden="true">♥</span>
                    <?php esc_html_e( 'Faire un don pour aider', 'givoly' ); ?>
                </a>
            </div>
        </section>
        <?php
    }

    /**
     * Ajoute le résumé Givoly au tableau de bord natif de WordPress.
     */
    public function register_wordpress_dashboard_widget(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'givoly_dashboard_widget',
            __( 'Givoly — Dons reçus', 'givoly' ),
            [ \Givoly\Admin\Pages\DashboardPage::class, 'render_wordpress_widget' ]
        );
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
