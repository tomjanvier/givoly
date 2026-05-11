<?php
/**
 * Page liste des dons.
 *
 * Tableau paginé (20/page) avec filtre par statut, bouton de remboursement Stripe
 * et lien vers le dashboard HelloAsso pour les dons HelloAsso.
 *
 * @package Givasso\Admin\Pages
 */

namespace Givasso\Admin\Pages;

use Givasso\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonationsPage {

    private const PER_PAGE = 20;

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givasso' ) );
        }

        // Notices
        if ( isset( $_GET['givasso_refunded'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__( 'Don remboursé avec succès.', 'givasso' )
                . '</p></div>';
        }
        if ( isset( $_GET['givasso_refund_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__( 'Erreur lors du remboursement. Vérifiez vos clés Stripe ou effectuez le remboursement depuis le dashboard Stripe.', 'givasso' )
                . '</p></div>';
        }

        $valid_statuses = [ '', 'completed', 'pending', 'failed', 'refunded', 'cancelled' ];
        $status         = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $status = '';
        }

        $paged       = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $total       = $this->count_donations( $status );
        $donations   = $this->get_donations( $status, $paged );
        $total_pages = (int) ceil( $total / self::PER_PAGE );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Givasso — Dons', 'givasso' ); ?></h1>

            <!-- ── Filtre par statut ───────────────────────────────────── -->
            <ul class="subsubsub">
                <?php
                $filters = [
                    ''          => __( 'Tous', 'givasso' ),
                    'completed' => __( 'Complétés', 'givasso' ),
                    'pending'   => __( 'En attente', 'givasso' ),
                    'failed'    => __( 'Échoués', 'givasso' ),
                    'refunded'  => __( 'Remboursés', 'givasso' ),
                    'cancelled' => __( 'Annulés', 'givasso' ),
                ];
                $links = [];
                foreach ( $filters as $s => $label ) {
                    $url     = admin_url( 'admin.php?page=givasso-donations' . ( $s !== '' ? '&status=' . rawurlencode( $s ) : '' ) );
                    $current = $status === $s ? ' class="current"' : '';
                    $links[] = '<li><a href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $label ) . '</a></li>';
                }
                echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- links built with esc_url/esc_html
                ?>
            </ul>

            <?php if ( empty( $donations ) ) : ?>
                <p><?php esc_html_e( 'Aucun don enregistré pour l\'instant.', 'givasso' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped givasso-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Donateur', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Montant', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Statut', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'givasso' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'givasso' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $donations as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( trim( $row->first_name . ' ' . $row->last_name ) ?: '—' ); ?></td>
                                <td><?php echo esc_html( $row->email ?: '—' ); ?></td>
                                <td>
                                    <strong>
                                        <?php echo esc_html( number_format( (float) $row->amount, 2, ',', ' ' ) . ' ' . $row->currency ); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="givasso-badge givasso-badge--<?php echo esc_attr( $row->status ); ?>">
                                        <?php echo esc_html( $this->format_status( $row->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $row->created_at ) ) ); ?></td>
                                <td>
                                    <?php if (
                                        $row->status === 'completed'
                                        && $row->gateway === 'stripe'
                                        && ! empty( $row->gateway_refund_ref )
                                    ) : ?>
                                        <form method="post"
                                              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                              style="display:inline;"
                                              onsubmit="return confirm('<?php esc_attr_e( 'Confirmer le remboursement de ce don ? Cette action est irréversible.', 'givasso' ); ?>')">
                                            <?php wp_nonce_field( 'givasso_refund_donation_' . $row->id ); ?>
                                            <input type="hidden" name="action"      value="givasso_refund_donation">
                                            <input type="hidden" name="donation_id" value="<?php echo esc_attr( $row->id ); ?>">
                                            <button type="submit" class="button button-small button-link-delete">
                                                <?php esc_html_e( 'Rembourser', 'givasso' ); ?>
                                            </button>
                                        </form>
                                    <?php elseif (
                                        $row->status === 'completed'
                                        && $row->gateway === 'helloasso'
                                    ) : ?>
                                        <?php
                                        $ha_slug = Settings::get_helloasso_org_slug();
                                        $ha_base = Settings::is_helloasso_sandbox()
                                            ? 'https://www.helloasso-sandbox.com'
                                            : 'https://www.helloasso.com';
                                        $ha_url  = $ha_base . '/associations/' . rawurlencode( $ha_slug ) . '/gestion';
                                        ?>
                                        <a href="<?php echo esc_url( $ha_url ); ?>"
                                           target="_blank"
                                           rel="noopener"
                                           class="button button-small"
                                           title="<?php esc_attr_e( 'Rembourser depuis le dashboard HelloAsso', 'givasso' ); ?>">
                                            <?php esc_html_e( 'Rembourser ↗', 'givasso' ); ?>
                                        </a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php $this->render_pagination( $total_pages, $paged, $status ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Requêtes DB ────────────────────────────────────────────────────────

    private function get_donations( string $status, int $page ): array {
        global $wpdb;

        $offset   = ( $page - 1 ) * self::PER_PAGE;
        $table_d  = $wpdb->prefix . 'givasso_donations';
        $table_dn = $wpdb->prefix . 'givasso_donors';

        $select = "SELECT d.id, d.amount, d.currency, d.status, d.created_at,
                          d.gateway, d.gateway_refund_ref,
                          dn.first_name, dn.last_name, dn.email
                   FROM {$table_d} d
                   LEFT JOIN {$table_dn} dn ON d.donor_id = dn.id";

        if ( $status !== '' ) {
            return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $select uses $wpdb->prefix table names only
                $wpdb->prepare(
                    $select . " WHERE d.status = %s ORDER BY d.created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $status,
                    self::PER_PAGE,
                    $offset
                )
            );
        }

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $select uses $wpdb->prefix table names only
            $wpdb->prepare(
                $select . " ORDER BY d.created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                self::PER_PAGE,
                $offset
            )
        );
    }

    private function count_donations( string $status ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'givasso_donations';

        if ( $status !== '' ) {
            return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function render_pagination( int $total_pages, int $current_page, string $status ): void {
        if ( $total_pages <= 1 ) {
            return;
        }

        $base_url = admin_url( 'admin.php?page=givasso-donations' . ( $status !== '' ? '&status=' . rawurlencode( $status ) : '' ) );

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'base'      => $base_url . '&paged=%#%',
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ] );
        echo '</div></div>';
    }

    private function format_status( string $status ): string {
        $labels = [
            'completed' => __( 'Complété', 'givasso' ),
            'pending'   => __( 'En attente', 'givasso' ),
            'failed'    => __( 'Échoué', 'givasso' ),
            'refunded'  => __( 'Remboursé', 'givasso' ),
            'cancelled' => __( 'Annulé', 'givasso' ),
        ];

        return $labels[ $status ] ?? $status;
    }
}
