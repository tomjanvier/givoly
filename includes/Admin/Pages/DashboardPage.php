<?php
/**
 * Page Dashboard Givoly.
 *
 * @package Givoly\Admin\Pages
 */

namespace Givoly\Admin\Pages;

use Givoly\Admin\DonationStats;
use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DashboardPage {

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'givoly' ) );
        }

        $stats            = DonationStats::summary();
        $monthly          = DonationStats::monthly_totals();
        $recent_donations = DonationStats::recent_donations();
        $support_donation_url = add_query_arg(
            [
                'utm_source'   => 'givoly',
                'utm_medium'   => 'plugin_admin',
                'utm_campaign' => 'dashboard_donation',
            ],
            'https://plaidact.org/don/'
        );
        ?>
        <div class="wrap givoly-dashboard">
            <header class="givoly-dashboard__header">
                <div>
                    <h1><?php esc_html_e( 'Givoly — Dashboard', 'givoly' ); ?></h1>
                    <p><?php esc_html_e( 'A clear view of donations received and the next useful actions.', 'givoly' ); ?></p>
                </div>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-donations' ) ); ?>">
                    <?php esc_html_e( 'View all donations', 'givoly' ); ?>
                </a>
            </header>

            <?php if ( ! Settings::is_configured() ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'Stripe is not configured yet.', 'givoly' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-settings' ) ); ?>">
                            <?php esc_html_e( 'Configure now →', 'givoly' ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <section class="givoly-stats" aria-label="<?php esc_attr_e( 'Donation metrics', 'givoly' ); ?>">
                <?php self::render_stat_card( '💰', __( 'Total collected', 'givoly' ), \Givoly\Core\Format::amounts_by_currency( $stats['by_currency'] ?? [] ) ); ?>
                <?php self::render_stat_card( '🎁', __( 'Completed donations', 'givoly' ), number_format_i18n( $stats['total_donations'] ) ); ?>
                <?php self::render_stat_card( '👥', __( 'Active donors', 'givoly' ), number_format_i18n( $stats['total_donors'] ) ); ?>
                <?php
                // Moyenne affichée uniquement si une seule devise : jamais de moyenne inter-devises.
                $by_currency = $stats['by_currency'] ?? [];
                $average_label = count( $by_currency ) === 1
                    ? \Givoly\Core\Format::amount_with_currency( (float) reset( $by_currency )['amount'] / max( 1, $stats['total_donations'] ), (string) key( $by_currency ) )
                    : __( 'See totals by currency', 'givoly' );
                ?>
                <?php self::render_stat_card( '↗', __( 'Average donation', 'givoly' ), $average_label ); ?>
            </section>

            <div class="givoly-dashboard-grid">
                <section class="givoly-panel givoly-panel--chart" aria-labelledby="givoly-chart-title">
                    <div class="givoly-panel__heading">
                        <div>
                            <h2 id="givoly-chart-title"><?php esc_html_e( 'Donation trends', 'givoly' ); ?></h2>
                            <p><?php esc_html_e( 'Completed amounts over the last six months.', 'givoly' ); ?></p>
                        </div>
                        <span class="givoly-panel__legend"><span aria-hidden="true"></span><?php esc_html_e( 'Completed donations', 'givoly' ); ?></span>
                    </div>
                    <?php self::render_monthly_chart( $monthly ); ?>
                </section>

                <section class="givoly-panel givoly-panel--actions" aria-labelledby="givoly-actions-title">
                    <h2 id="givoly-actions-title"><?php esc_html_e( 'Next steps', 'givoly' ); ?></h2>
                    <p><?php esc_html_e( 'Keep your donation space ready for your next campaign.', 'givoly' ); ?></p>
                    <ul class="givoly-quick-actions">
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-settings&tab=general' ) ); ?>"><span aria-hidden="true">⚙</span><?php esc_html_e( 'Check settings', 'givoly' ); ?><span aria-hidden="true">→</span></a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-settings&tab=email' ) ); ?>"><span aria-hidden="true">✉</span><?php esc_html_e( 'Customize emails', 'givoly' ); ?><span aria-hidden="true">→</span></a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-campaigns' ) ); ?>"><span aria-hidden="true">✦</span><?php esc_html_e( 'Create a campaign', 'givoly' ); ?><span aria-hidden="true">→</span></a></li>
                    </ul>
                </section>
            </div>

            <section class="givoly-panel givoly-install-panel" aria-labelledby="givoly-install-title">
                <div class="givoly-panel__heading">
                    <div>
                        <h2 id="givoly-install-title"><?php esc_html_e( 'Add a donation form', 'givoly' ); ?></h2>
                        <p><?php esc_html_e( 'Copy this shortcode into a WordPress page or post to display your form.', 'givoly' ); ?></p>
                    </div>
                    <span class="givoly-install-panel__badge" aria-hidden="true">Givoly</span>
                </div>
                <div class="givoly-shortcode-field">
                    <code id="givoly-dashboard-shortcode">[givoly_form]</code>
                    <button type="button" class="button givoly-copy-btn" data-target="givoly-dashboard-shortcode">
                        <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                        <?php esc_html_e( 'Copy shortcode', 'givoly' ); ?>
                    </button>
                </div>
                <div class="givoly-install-panel__footer">
                    <span class="description"><?php esc_html_e( 'The form automatically uses your payment and appearance settings.', 'givoly' ); ?></span>
                    <a class="button button-secondary" href="<?php echo esc_url( $support_donation_url ); ?>" target="_blank" rel="noopener noreferrer">
                        <span aria-hidden="true">♥</span>
                        <?php esc_html_e( 'Support PLAID·ACT', 'givoly' ); ?>
                    </a>
                </div>
            </section>

            <section class="givoly-panel givoly-panel--recent" aria-labelledby="givoly-recent-title">
                <div class="givoly-panel__heading">
                    <div>
                        <h2 id="givoly-recent-title"><?php esc_html_e( 'Recent donors', 'givoly' ); ?></h2>
                        <p><?php esc_html_e( 'The latest confirmed donations appear here automatically.', 'givoly' ); ?></p>
                    </div>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-donations' ) ); ?>">
                        <?php esc_html_e( 'Open the full list', 'givoly' ); ?>
                    </a>
                </div>

                <?php if ( empty( $recent_donations ) ) : ?>
                    <div class="givoly-empty-state">
                        <span class="givoly-empty-state__icon" aria-hidden="true">♡</span>
                        <strong><?php esc_html_e( 'No donations have been recorded yet.', 'givoly' ); ?></strong>
                        <span><?php esc_html_e( 'Confirmed donations will appear in this table.', 'givoly' ); ?></span>
                    </div>
                <?php else : ?>
                    <div class="givoly-table-scroll">
                        <table class="wp-list-table widefat fixed striped givoly-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Donor', 'givoly' ); ?></th>
                                    <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                                    <th><?php esc_html_e( 'Amount', 'givoly' ); ?></th>
                                    <th><?php esc_html_e( 'Campaign', 'givoly' ); ?></th>
                                    <th><?php esc_html_e( 'Date', 'givoly' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_donations as $donation ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( trim( $donation->first_name . ' ' . $donation->last_name ) ?: '—' ); ?></strong></td>
                                        <td><?php echo esc_html( $donation->email ?: '—' ); ?></td>
                                        <td><strong><?php echo esc_html( number_format_i18n( (float) $donation->amount, 2 ) . ' ' . $donation->currency ); ?></strong></td>
                                        <td><?php echo esc_html( $donation->campaign_title ?: $donation->donor_message ?: '—' ); ?></td>
                                        <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $donation->created_at ) ) ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    /**
     * Rendu compact du widget du tableau de bord WordPress.
     */
    public static function render_wordpress_widget(): void {
        $stats   = DonationStats::summary();
        $monthly = DonationStats::monthly_totals();
        $recent  = DonationStats::recent_donations( 5 );
        ?>
        <div class="givoly-wp-dashboard-widget">
            <div class="givoly-wp-dashboard-widget__summary">
                <div>
                    <span class="givoly-wp-dashboard-widget__label"><?php esc_html_e( 'Total collected', 'givoly' ); ?></span>
                    <strong><?php echo esc_html( \Givoly\Core\Format::amounts_by_currency( $stats['by_currency'] ?? [] ) ); ?></strong>
                </div>
                <div>
                    <span class="givoly-wp-dashboard-widget__label"><?php esc_html_e( 'Completed donations', 'givoly' ); ?></span>
                    <strong><?php echo esc_html( number_format_i18n( $stats['total_donations'] ) ); ?></strong>
                </div>
                <div>
                    <span class="givoly-wp-dashboard-widget__label"><?php esc_html_e( 'Donors', 'givoly' ); ?></span>
                    <strong><?php echo esc_html( number_format_i18n( $stats['total_donors'] ) ); ?></strong>
                </div>
            </div>

            <h3><?php esc_html_e( 'Six-month trend', 'givoly' ); ?></h3>
            <?php self::render_monthly_chart( $monthly, true ); ?>

            <h3><?php esc_html_e( 'Recent donors', 'givoly' ); ?></h3>
            <?php if ( empty( $recent ) ) : ?>
                <p><?php esc_html_e( 'No completed donations yet.', 'givoly' ); ?></p>
            <?php else : ?>
                <ul class="givoly-wp-dashboard-widget__donors">
                    <?php foreach ( $recent as $donation ) : ?>
                        <li>
                            <span>
                                <strong><?php echo esc_html( trim( $donation->first_name . ' ' . $donation->last_name ) ?: '—' ); ?></strong>
                                <small><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $donation->created_at ) ) ); ?></small>
                            </span>
                            <b><?php echo esc_html( number_format_i18n( (float) $donation->amount, 2 ) . ' ' . $donation->currency ); ?></b>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-dashboard' ) ); ?>">
                <?php esc_html_e( 'Open the Givoly dashboard', 'givoly' ); ?>
            </a>
        </div>
        <?php
    }

    private static function render_stat_card( string $icon, string $label, string $value ): void {
        ?>
        <div class="givoly-stat-card">
            <span class="givoly-stat-card__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
            <strong class="givoly-stat-card__value"><?php echo esc_html( $value ); ?></strong>
            <span class="givoly-stat-card__label"><?php echo esc_html( $label ); ?></span>
        </div>
        <?php
    }

    /**
     * Affiche un graphique CSS léger, sans bibliothèque externe.
     *
     * Les barres restent proportionnelles au total mensuel historique, mais le
     * détail par devise est toujours affiché (jamais de somme présentée comme
     * homogène en multidevise).
     *
     * @param array<int, array{key: string, label: string, total: float, count: int, by_currency?: array<string, float>}> $monthly
     */
    private static function render_monthly_chart( array $monthly, bool $compact = false ): void {
        $max_total = 0.0;
        foreach ( $monthly as $month ) {
            $max_total = max( $max_total, (float) $month['total'] );
        }
        ?>
        <div class="givoly-chart <?php echo $compact ? 'givoly-chart--compact' : ''; ?>" role="img" aria-label="<?php esc_attr_e( 'Chart of completed donation amounts over the last six months', 'givoly' ); ?>">
            <div class="givoly-chart__bars">
                <?php foreach ( $monthly as $month ) : ?>
                    <?php
                    $height = $max_total > 0 && $month['total'] > 0
                        ? max( 8, (int) round( ( $month['total'] / $max_total ) * 100 ) )
                        : 2;
                    $by_currency = $month['by_currency'] ?? [];
                    $detail = ! empty( $by_currency )
                        ? \Givoly\Core\Format::amounts_by_currency( $by_currency )
                        : number_format_i18n( $month['total'], 2 );
                    $title  = sprintf(
                        /* translators: 1: month label, 2: amounts by currency, 3: number of donations. */
                        _n( '%1$s: %2$s, %3$d donation', '%1$s: %2$s, %3$d donations', $month['count'], 'givoly' ),
                        $month['label'],
                        $detail,
                        $month['count']
                    );
                    ?>
                    <div class="givoly-chart__item">
                        <span class="givoly-chart__value"><?php echo $month['total'] > 0 ? esc_html( $detail ) : ''; ?></span>
                        <span class="givoly-chart__bar" style="height:<?php echo esc_attr( $height . '%' ); ?>" title="<?php echo esc_attr( $title ); ?>"></span>
                        <span class="givoly-chart__label"><?php echo esc_html( $month['label'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ( 0.0 === $max_total ) : ?>
                <p class="givoly-chart__empty"><?php esc_html_e( 'Amounts will appear after your first completed donations.', 'givoly' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
