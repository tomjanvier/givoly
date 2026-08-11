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
            __( 'Dashboard', 'givoly' ), __( 'Dashboard', 'givoly' ),
            'manage_options', 'givoly-dashboard', [ $this, 'render_dashboard' ]
        );

        // load-{hook} se déclenche avant tout output — idéal pour POST + redirect
        $campaigns_hook = add_submenu_page( 'givoly-dashboard',
            __( 'Campaigns', 'givoly' ), __( 'Campaigns', 'givoly' ),
            'manage_options', 'givoly-campaigns', [ $this, 'render_campaigns' ]
        );
        add_action( 'load-' . $campaigns_hook, [ $this, 'handle_campaigns_early' ] );

        add_submenu_page( 'givoly-dashboard',
            __( 'Donations', 'givoly' ), __( 'Donations', 'givoly' ),
            'manage_options', 'givoly-donations', [ $this, 'render_donations' ]
        );

        add_submenu_page( 'givoly-dashboard',
            __( 'Donors', 'givoly' ), __( 'Donors', 'givoly' ),
            'manage_options', 'givoly-donors', [ $this, 'render_donors' ]
        );

        add_submenu_page( 'givoly-dashboard',
            __( 'Add a manual donation', 'givoly' ), __( 'Add a manual donation', 'givoly' ),
            'manage_options', 'givoly-manual-donation', [ $this, 'render_manual_donation' ]
        );

        add_submenu_page( 'givoly-dashboard',
            __( 'Settings', 'givoly' ), __( 'Settings', 'givoly' ),
            'manage_options', 'givoly-settings', [ $this, 'render_settings' ]
        );
    }

    public function render_support_header(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! str_starts_with( $page, 'givoly-' ) ) {
            return;
        }

        // Direct links only: WordPress.org does not allow referral tracking
        // through plugin-admin promotion.
        $givoly_url   = 'https://givoly.org';
        $plaidact_url = 'https://plaidact.org';
        $donate_url   = 'https://plaidact.org/don/';
        ?>
        <section class="givoly-admin-support" aria-labelledby="givoly-support-title">
            <div class="givoly-admin-support__copy">
                <p class="givoly-admin-support__title" id="givoly-support-title">
                    <span class="givoly-admin-support__heart" aria-hidden="true">♥</span>
                    <?php esc_html_e( 'Free, nonprofit, no surprises.', 'givoly' ); ?>
                </p>
                <p class="givoly-admin-support__text">
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: 1: PLAID·ACT link, 2: Givoly link. */
                            __( '%2$s is maintained by %1$s, a nonprofit organization defending human rights. The goal is simple: give nonprofits a clear way to accept donations through WordPress, with no required subscription and no commission added by the plugin.', 'givoly' ),
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
                    <?php esc_html_e( 'Discover Givoly', 'givoly' ); ?>
                </a>
                <a class="givoly-admin-support__link" href="<?php echo esc_url( $plaidact_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'PLAID·ACT', 'givoly' ); ?>
                </a>
                <a class="button button-primary givoly-admin-support__button" href="<?php echo esc_url( $donate_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <span aria-hidden="true">♥</span>
                    <?php esc_html_e( 'Make a donation to help', 'givoly' ); ?>
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
            __( 'Givoly — Donations received', 'givoly' ),
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

    public function render_manual_donation(): void {
        ( new \Givoly\Admin\Pages\ManualDonationPage() )->render();
    }

    public function render_settings(): void {
        ( new \Givoly\Admin\Pages\SettingsPage() )->render();
    }

}
