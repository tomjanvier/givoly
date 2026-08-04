<?php
/**
 * Page Tableau de bord Givoly.
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
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $stats            = DonationStats::summary();
        $monthly          = DonationStats::monthly_totals();
        $recent_donations = DonationStats::recent_donations();
        ?>
        <div class="wrap givoly-dashboard">
            <header class="givoly-dashboard__header">
                <div>
                    <h1><?php esc_html_e( 'Givoly — Tableau de bord', 'givoly' ); ?></h1>
                    <p><?php esc_html_e( 'Une vue claire des dons reçus et des prochaines actions utiles.', 'givoly' ); ?></p>
                </div>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-donations' ) ); ?>">
                    <?php esc_html_e( 'Voir tous les dons', 'givoly' ); ?>
                </a>
            </header>

            <?php if ( ! Settings::is_configured() ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'Stripe n\'est pas encore configuré.', 'givoly' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-settings' ) ); ?>">
                            <?php esc_html_e( 'Configurer maintenant →', 'givoly' ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <section class="givoly-stats" aria-label="<?php esc_attr_e( 'Indicateurs des dons', 'givoly' ); ?>">
                <?php self::render_stat_card( '💰', __( 'Total collecté', 'givoly' ), number_format_i18n( $stats['total_amount'], 2 ) . ' €' ); ?>
                <?php self::render_stat_card( '🎁', __( 'Dons complétés', 'givoly' ), number_format_i18n( $stats['total_donations'] ) ); ?>
                <?php self::render_stat_card( '👥', __( 'Donateurs actifs', 'givoly' ), number_format_i18n( $stats['total_donors'] ) ); ?>
                <?php self::render_stat_card( '↗', __( 'Don moyen', 'givoly' ), number_format_i18n( $stats['average_amount'], 2 ) . ' €' ); ?>
            </section>

            <div class="givoly-dashboard-grid">
                <section class="givoly-panel givoly-panel--chart" aria-labelledby="givoly-chart-title">
                    <div class="givoly-panel__heading">
                        <div>
                            <h2 id="givoly-chart-title"><?php esc_html_e( 'Évolution des dons', 'givoly' ); ?></h2>
                            <p><?php esc_html_e( 'Montants complétés sur les six derniers mois.', 'givoly' ); ?></p>
                        </div>
                        <span class="givoly-panel__legend"><span aria-hidden="true"></span><?php esc_html_e( 'Dons complétés', 'givoly' ); ?></span>
                    </div>
                    <?php self::render_monthly_chart( $monthly ); ?>
                </section>

                <section class="givoly-panel givoly-panel--actions" aria-labelledby="givoly-actions-title">
                    <h2 id="givoly-actions-title"><?php esc_html_e( 'À faire ensuite', 'givoly' ); ?></h2>
                    <p><?php esc_html_e( 'Gardez votre espace de dons prêt pour votre prochaine campagne.', 'givoly' ); ?></p>
                    <ul class="givoly-quick-actions">
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-settings&tab=general' ) ); ?>"><span aria-hidden="true">⚙</span><?php esc_html_e( 'Vérifier les réglages', 'givoly' ); ?><span aria-hidden="true">→</span></a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-settings&tab=email' ) ); ?>"><span aria-hidden="true">✉</span><?php esc_html_e( 'Personnaliser les emails', 'givoly' ); ?><span aria-hidden="true">→</span></a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-campaigns' ) ); ?>"><span aria-hidden="true">✦</span><?php esc_html_e( 'Créer une campagne', 'givoly' ); ?><span aria-hidden="true">→</span></a></li>
                    </ul>
                </section>
            </div>

            <section class="givoly-panel givoly-panel--recent" aria-labelledby="givoly-recent-title">
                <div class="givoly-panel__heading">
                    <div>
                        <h2 id="givoly-recent-title"><?php esc_html_e( 'Derniers donateurs', 'givoly' ); ?></h2>
                        <p><?php esc_html_e( 'Les derniers dons confirmés apparaissent ici automatiquement.', 'givoly' ); ?></p>
                    </div>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-donations' ) ); ?>">
                        <?php esc_html_e( 'Ouvrir la liste complète', 'givoly' ); ?>
                    </a>
                </div>

                <?php if ( empty( $recent_donations ) ) : ?>
                    <div class="givoly-empty-state">
                        <span class="givoly-empty-state__icon" aria-hidden="true">♡</span>
                        <strong><?php esc_html_e( 'Aucun don enregistré pour l’instant.', 'givoly' ); ?></strong>
                        <span><?php esc_html_e( 'Les dons confirmés apparaîtront dans ce tableau.', 'givoly' ); ?></span>
                    </div>
                <?php else : ?>
                    <div class="givoly-table-scroll">
                        <table class="wp-list-table widefat fixed striped givoly-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Donateur', 'givoly' ); ?></th>
                                    <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                                    <th><?php esc_html_e( 'Montant', 'givoly' ); ?></th>
                                    <th><?php esc_html_e( 'Campagne', 'givoly' ); ?></th>
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
                    <span class="givoly-wp-dashboard-widget__label"><?php esc_html_e( 'Total collecté', 'givoly' ); ?></span>
                    <strong><?php echo esc_html( number_format_i18n( $stats['total_amount'], 2 ) . ' €' ); ?></strong>
                </div>
                <div>
                    <span class="givoly-wp-dashboard-widget__label"><?php esc_html_e( 'Dons complétés', 'givoly' ); ?></span>
                    <strong><?php echo esc_html( number_format_i18n( $stats['total_donations'] ) ); ?></strong>
                </div>
                <div>
                    <span class="givoly-wp-dashboard-widget__label"><?php esc_html_e( 'Donateurs', 'givoly' ); ?></span>
                    <strong><?php echo esc_html( number_format_i18n( $stats['total_donors'] ) ); ?></strong>
                </div>
            </div>

            <h3><?php esc_html_e( 'Évolution sur six mois', 'givoly' ); ?></h3>
            <?php self::render_monthly_chart( $monthly, true ); ?>

            <h3><?php esc_html_e( 'Derniers donateurs', 'givoly' ); ?></h3>
            <?php if ( empty( $recent ) ) : ?>
                <p><?php esc_html_e( 'Aucun don complété pour l’instant.', 'givoly' ); ?></p>
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
                <?php esc_html_e( 'Ouvrir le tableau de bord Givoly', 'givoly' ); ?>
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
     * @param array<int, array{key: string, label: string, total: float, count: int}> $monthly
     */
    private static function render_monthly_chart( array $monthly, bool $compact = false ): void {
        $max_total = 0.0;
        foreach ( $monthly as $month ) {
            $max_total = max( $max_total, (float) $month['total'] );
        }
        ?>
        <div class="givoly-chart <?php echo $compact ? 'givoly-chart--compact' : ''; ?>" role="img" aria-label="<?php esc_attr_e( 'Graphique des montants de dons complétés sur les six derniers mois', 'givoly' ); ?>">
            <div class="givoly-chart__bars">
                <?php foreach ( $monthly as $month ) : ?>
                    <?php
                    $height = $max_total > 0 && $month['total'] > 0
                        ? max( 8, (int) round( ( $month['total'] / $max_total ) * 100 ) )
                        : 2;
                    $title  = sprintf(
                        /* translators: 1: month label, 2: amount, 3: number of donations. */
                        _n( '%1$s : %2$s €, %3$d don', '%1$s : %2$s €, %3$d dons', $month['count'], 'givoly' ),
                        $month['label'],
                        number_format_i18n( $month['total'], 2 ),
                        $month['count']
                    );
                    ?>
                    <div class="givoly-chart__item">
                        <span class="givoly-chart__value"><?php echo $month['total'] > 0 ? esc_html( number_format_i18n( $month['total'], 0 ) . ' €' ) : ''; ?></span>
                        <span class="givoly-chart__bar" style="height:<?php echo esc_attr( $height . '%' ); ?>" title="<?php echo esc_attr( $title ); ?>"></span>
                        <span class="givoly-chart__label"><?php echo esc_html( $month['label'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ( 0.0 === $max_total ) : ?>
                <p class="givoly-chart__empty"><?php esc_html_e( 'Les montants apparaîtront après vos premiers dons complétés.', 'givoly' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
