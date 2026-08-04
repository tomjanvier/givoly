<?php
/**
 * Page liste des donateurs.
 *
 * Tableau : nom, email, total donné (dons complétés), nb de dons, dernier don.
 *
 * @package Givoly\Admin\Pages
 */

namespace Givoly\Admin\Pages;

use Givoly\Mail\MailQueue;
use Givoly\Mail\TaxReceiptService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonorsPage {

    private const PER_PAGE = 50;
    private const RECEIPTS_PER_PAGE = 50;

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $paged        = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $total        = $this->count_donors();
        $donors       = $this->get_donors( $paged );
        $total_pages  = (int) ceil( $total / self::PER_PAGE );
        $default_year = (int) gmdate( 'Y' ) - 1;
        $receipt_year = absint( wp_unslash( $_GET['receipt_year'] ?? $default_year ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( $receipt_year < 2000 || $receipt_year > ( (int) gmdate( 'Y' ) + 1 ) ) {
            $receipt_year = $default_year;
        }
        $receipt_paged  = max( 1, absint( wp_unslash( $_GET['receipt_paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $receipt_total  = TaxReceiptService::count_recipients( $receipt_year );
        $receipt_pages  = (int) ceil( $receipt_total / self::RECEIPTS_PER_PAGE );
        $receipt_donors = TaxReceiptService::get_recipients( $receipt_year, [], self::RECEIPTS_PER_PAGE, ( $receipt_paged - 1 ) * self::RECEIPTS_PER_PAGE );
        $batch_id       = sanitize_text_field( wp_unslash( $_GET['givoly_tax_receipts_batch'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $batch_stats    = MailQueue::get_batch_stats( $batch_id );
        $batch_jobs     = MailQueue::get_batch_jobs( $batch_id );
        $edit_donor_id  = absint( wp_unslash( $_GET['edit_donor'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $edit_donor     = $edit_donor_id ? $this->get_donor( $edit_donor_id ) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Givoly — Donateurs', 'givoly' ); ?></h1>

            <?php if ( isset( $_GET['givoly_tax_receipts_queued'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <?php $queued = absint( wp_unslash( $_GET['givoly_tax_receipts_queued'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php /* translators: %d: number of fiscal receipts queued for delivery. */ ?>
                    <?php printf( esc_html( _n( '%d reçu fiscal a été mis en file.', '%d reçus fiscaux ont été mis en file.', $queued, 'givoly' ) ), esc_html( (string) $queued ) ); ?>
                </p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['givoly_tax_receipts_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Impossible de mettre les reçus fiscaux en file : année invalide ou aucun destinataire sélectionné.', 'givoly' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['givoly_donor_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La fiche donateur a été mise à jour.', 'givoly' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['givoly_donor_update_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'La fiche n’a pas pu être mise à jour. Vérifiez l’email et les champs saisis.', 'givoly' ); ?></p></div>
            <?php endif; ?>

            <?php if ( $edit_donor_id && ! $edit_donor ) : ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'Fiche donateur introuvable.', 'givoly' ); ?></p></div>
            <?php elseif ( $edit_donor ) : ?>
                <?php $this->render_edit_form( $edit_donor ); ?>
            <?php endif; ?>

            <div class="card" style="max-width: 1100px;">
                <h2><?php esc_html_e( 'Envoi annuel des reçus fiscaux', 'givoly' ); ?></h2>
                <p><?php esc_html_e( 'Prévisualisez les bénéficiaires, envoyez un reçu seul ou sélectionnez plusieurs destinataires. Les emails et les PDF sont traités en arrière-plan par lots.', 'givoly' ); ?></p>
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                    <input type="hidden" name="page" value="givoly-donors">
                    <label for="givoly-receipt-year"><strong><?php esc_html_e( 'Année fiscale', 'givoly' ); ?></strong></label>
                    <input type="number" id="givoly-receipt-year" name="receipt_year" min="2000" max="<?php echo esc_attr( (string) gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( (string) $receipt_year ); ?>" class="small-text">
                    <button type="submit" class="button"><?php esc_html_e( 'Afficher les bénéficiaires', 'givoly' ); ?></button>
                </form>
                <p class="description"><?php esc_html_e( 'Les données affichées seront utilisées dans l’email et le PDF. Configurez leurs modèles dans Givoly > Réglages > Email.', 'givoly' ); ?></p>

                <?php if ( empty( $receipt_donors ) ) : ?>
                    <p><?php esc_html_e( 'Aucun donateur avec un don complété pour cette année.', 'givoly' ); ?></p>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit='return confirm(<?php echo wp_json_encode( __( 'Mettre les reçus sélectionnés en file d’envoi ?', 'givoly' ) ); ?>)'>
                        <?php wp_nonce_field( 'givoly_queue_tax_receipts' ); ?>
                        <input type="hidden" name="action" value="givoly_queue_tax_receipts">
                        <input type="hidden" name="receipt_year" value="<?php echo esc_attr( (string) $receipt_year ); ?>">
                        <p>
                            <button type="submit" name="mode" value="selected" class="button button-primary"><?php esc_html_e( 'Mettre les sélectionnés en file', 'givoly' ); ?></button>
                            <button type="submit" name="mode" value="all" class="button" onclick='return confirm(<?php echo wp_json_encode( __( 'Mettre tous les bénéficiaires de cette année en file ?', 'givoly' ) ); ?>)'><?php esc_html_e( 'Mettre tout en file', 'givoly' ); ?></button>
                        </p>
                        <table class="wp-list-table widefat striped">
                            <thead><tr>
                                <th class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.givoly-receipt-check').forEach(function(c){c.checked=this.checked;}, this)"></th>
                                <th><?php esc_html_e( 'Donateur', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Montant', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Dons', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Action individuelle', 'givoly' ); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ( $receipt_donors as $recipient ) : ?>
                                <?php $recipient_name = trim( (string) $recipient->first_name . ' ' . (string) $recipient->last_name ); ?>
                                <tr>
                                    <th class="check-column"><input class="givoly-receipt-check" type="checkbox" name="donor_ids[]" value="<?php echo esc_attr( (string) $recipient->id ); ?>"></th>
                                    <td><?php echo esc_html( $recipient_name ?: '—' ); ?></td>
                                    <td><?php echo esc_html( $recipient->email ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( (float) $recipient->total_amount, 2 ) . ' ' . $recipient->currency ); ?></td>
                                    <td><?php echo esc_html( (string) $recipient->donation_count ); ?></td>
                                    <td><button type="submit" name="single_donor_id" value="<?php echo esc_attr( (string) $recipient->id ); ?>" class="button button-small"><?php esc_html_e( 'Envoyer ce reçu', 'givoly' ); ?></button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                <?php endif; ?>

                <?php if ( $receipt_pages > 1 ) : ?>
                    <p class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links( [
                                'base'      => add_query_arg( [ 'page' => 'givoly-donors', 'receipt_year' => $receipt_year, 'receipt_paged' => '%#%' ], admin_url( 'admin.php' ) ),
                                'format'    => '',
                                'current'   => min( $receipt_paged, $receipt_pages ),
                                'total'     => $receipt_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ] )
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <?php if ( $batch_id && $batch_stats['total'] > 0 ) : ?>
                    <h3><?php esc_html_e( 'Suivi de la dernière file', 'givoly' ); ?></h3>
                    <?php /* translators: 1: total jobs, 2: pending jobs, 3: processing jobs, 4: sent jobs, 5: failed jobs. */ ?>
                    <p><?php printf( esc_html__( '%1$d total : %2$d en attente, %3$d en cours, %4$d envoyé(s), %5$d en échec.', 'givoly' ), esc_html( (string) $batch_stats['total'] ), esc_html( (string) $batch_stats['pending'] ), esc_html( (string) $batch_stats['processing'] ), esc_html( (string) $batch_stats['sent'] ), esc_html( (string) $batch_stats['failed'] ) ); ?></p>
                    <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Destinataire', 'givoly' ); ?></th><th><?php esc_html_e( 'Statut', 'givoly' ); ?></th><th><?php esc_html_e( 'Erreur', 'givoly' ); ?></th></tr></thead><tbody>
                    <?php foreach ( $batch_jobs as $job ) : ?><tr><td><?php echo esc_html( $job->recipient ); ?></td><td><?php echo esc_html( $job->status ); ?></td><td><?php echo esc_html( $job->last_error ?: '—' ); ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>

            <?php if ( empty( $donors ) ) : ?>
                <p><?php esc_html_e( 'Aucun donateur enregistré pour l\'instant.', 'givoly' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped givoly-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Donateur', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Total donné', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Nb de dons', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Dernier don', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'givoly' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $donors as $donor ) : ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php
                                        $name = trim( $donor->first_name . ' ' . $donor->last_name );
                                        echo esc_html( $name ?: '—' );
                                        ?>
                                    </strong>
                                    <?php if ( ! empty( $donor->company ) ) : ?>
                                        <br><small><?php echo esc_html( $donor->company ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $donor->email ); ?></td>
                                <td>
                                    <strong>
                                        <?php echo esc_html( number_format( (float) $donor->total_donated, 2, ',', ' ' ) . ' €' ); ?>
                                    </strong>
                                </td>
                                <td><?php echo esc_html( $donor->donation_count ); ?></td>
                                <td>
                                    <?php
                                    echo $donor->last_donation
                                        ? esc_html( date_i18n( 'd/m/Y', strtotime( $donor->last_donation ) ) )
                                        : '—';
                                    ?>
                                </td>
                                <td><a class="button button-small" href="<?php echo esc_url( add_query_arg( [ 'page' => 'givoly-donors', 'edit_donor' => (int) $donor->id ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Modifier', 'givoly' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php $this->render_pagination( $total_pages, $paged ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Requêtes DB ────────────────────────────────────────────────────────

    private function get_donors( int $page ): array {
        global $wpdb;

        $table_dn = $wpdb->prefix . 'givoly_donors';
        $table_d  = $wpdb->prefix . 'givoly_donations';
        $offset   = ( $page - 1 ) * self::PER_PAGE;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix (trusted)
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT dn.id, dn.first_name, dn.last_name, dn.email, dn.company, COALESCE( SUM( CASE WHEN d.status = 'completed' THEN d.amount ELSE 0 END ), 0 ) AS total_donated, COUNT( CASE WHEN d.status = 'completed' THEN 1 END ) AS donation_count, MAX( CASE WHEN d.status = 'completed' THEN d.created_at END ) AS last_donation FROM {$table_dn} dn LEFT JOIN {$table_d} d ON d.donor_id = dn.id GROUP BY dn.id ORDER BY total_donated DESC, dn.id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                self::PER_PAGE,
                $offset
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    private function count_donors(): int {
        global $wpdb;

        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$wpdb->prefix}givoly_donors" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        );
    }

    private function get_donor( int $donor_id ): ?object {
        global $wpdb;

        $table = esc_sql( $wpdb->prefix . 'givoly_donors' );
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT id, email, first_name, last_name, company, address_line1, address_line2, postal_code, city, country, phone, stripe_customer_id, stripe_subscription_id FROM {$table} WHERE id = %d LIMIT 1", $donor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            OBJECT
        );
    }

    private function render_edit_form( object $donor ): void {
        ?>
        <div class="card" style="max-width: 900px;">
            <h2><?php esc_html_e( 'Modifier la fiche donateur', 'givoly' ); ?> <span class="description">#<?php echo esc_html( (string) $donor->id ); ?></span></h2>
            <p class="description"><?php esc_html_e( 'Le numéro donateur et les identifiants Stripe sont conservés. Modifier l’email ne supprime aucun don historique.', 'givoly' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'givoly_update_donor_' . (int) $donor->id, 'givoly_update_donor_nonce' ); ?>
                <input type="hidden" name="action" value="givoly_update_donor">
                <input type="hidden" name="donor_id" value="<?php echo esc_attr( (string) $donor->id ); ?>">
                <table class="form-table" role="presentation">
                    <tr><th><label for="givoly-donor-first-name"><?php esc_html_e( 'Prénom', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-first-name" name="first_name" type="text" value="<?php echo esc_attr( $donor->first_name ); ?>" required></td></tr>
                    <tr><th><label for="givoly-donor-last-name"><?php esc_html_e( 'Nom', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-last-name" name="last_name" type="text" value="<?php echo esc_attr( $donor->last_name ); ?>" required></td></tr>
                    <tr><th><label for="givoly-donor-email"><?php esc_html_e( 'Email', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-email" name="email" type="email" value="<?php echo esc_attr( $donor->email ); ?>" required></td></tr>
                    <tr><th><label for="givoly-donor-company"><?php esc_html_e( 'Organisation / société', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-company" name="company" type="text" value="<?php echo esc_attr( $donor->company ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-address1"><?php esc_html_e( 'Adresse', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-address1" name="address_line1" type="text" value="<?php echo esc_attr( $donor->address_line1 ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-address2"><?php esc_html_e( 'Complément d’adresse', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-address2" name="address_line2" type="text" value="<?php echo esc_attr( $donor->address_line2 ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-postal-code"><?php esc_html_e( 'Code postal', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-postal-code" name="postal_code" type="text" value="<?php echo esc_attr( $donor->postal_code ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-city"><?php esc_html_e( 'Ville', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-city" name="city" type="text" value="<?php echo esc_attr( $donor->city ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-country"><?php esc_html_e( 'Pays', 'givoly' ); ?></label></th><td><input class="small-text" id="givoly-donor-country" name="country" type="text" maxlength="2" value="<?php echo esc_attr( $donor->country ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-phone"><?php esc_html_e( 'Téléphone', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-phone" name="phone" type="tel" value="<?php echo esc_attr( $donor->phone ); ?>"></td></tr>
                </table>
                <?php submit_button( __( 'Enregistrer la fiche', 'givoly' ), 'primary', 'submit', false ); ?>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-donors' ) ); ?>"><?php esc_html_e( 'Annuler', 'givoly' ); ?></a>
            </form>
        </div>
        <?php
    }

    private function render_pagination( int $total_pages, int $current_page ): void {
        if ( $total_pages <= 1 ) {
            return;
        }

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'base'      => admin_url( 'admin.php?page=givoly-donors&paged=%#%' ),
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ] );
        echo '</div></div>';
    }
}
