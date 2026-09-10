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
            wp_die( esc_html__( 'Access denied.', 'givoly' ) );
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
            <h1><?php esc_html_e( 'Givoly — Donors', 'givoly' ); ?></h1>

            <?php if ( isset( $_GET['givoly_tax_receipts_queued'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <?php $queued = absint( wp_unslash( $_GET['givoly_tax_receipts_queued'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php /* translators: %d: number of fiscal receipts queued for delivery. */ ?>
                    <?php printf( esc_html( _n( '%d tax receipt has been queued.', '%d tax receipts have been queued.', $queued, 'givoly' ) ), esc_html( (string) $queued ) ); ?>
                </p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['givoly_tax_receipts_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Unable to queue tax receipts: invalid year or no recipient selected.', 'givoly' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['givoly_donor_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The donor record has been updated.', 'givoly' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['givoly_donor_update_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The record could not be updated. Check the email address and entered fields.', 'givoly' ); ?></p></div>
            <?php endif; ?>

            <?php if ( $edit_donor_id && ! $edit_donor ) : ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'Donor record not found.', 'givoly' ); ?></p></div>
            <?php elseif ( $edit_donor ) : ?>
                <?php $this->render_edit_form( $edit_donor ); ?>
            <?php endif; ?>

            <div class="card" style="max-width: 1100px;">
                <h2><?php esc_html_e( 'Annual tax receipt delivery', 'givoly' ); ?></h2>
                <p><?php esc_html_e( 'Preview recipients, send one receipt, or select multiple recipients. Emails and PDFs are processed in batches in the background.', 'givoly' ); ?></p>
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                    <input type="hidden" name="page" value="givoly-donors">
                    <label for="givoly-receipt-year"><strong><?php esc_html_e( 'Tax year', 'givoly' ); ?></strong></label>
                    <input type="number" id="givoly-receipt-year" name="receipt_year" min="2000" max="<?php echo esc_attr( (string) gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( (string) $receipt_year ); ?>" class="small-text">
                    <button type="submit" class="button"><?php esc_html_e( 'Show recipients', 'givoly' ); ?></button>
                </form>
                <p class="description"><?php esc_html_e( 'The displayed data will be used in the email and PDF. Configure their templates in Givoly > Settings > Email.', 'givoly' ); ?></p>

                <?php if ( empty( $receipt_donors ) ) : ?>
                    <p><?php esc_html_e( 'No donor has a completed donation for this year.', 'givoly' ); ?></p>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit='return confirm(<?php echo wp_json_encode( __( 'Queue the selected tax receipts for delivery?', 'givoly' ) ); ?>)'>
                        <?php wp_nonce_field( 'givoly_queue_tax_receipts' ); ?>
                        <input type="hidden" name="action" value="givoly_queue_tax_receipts">
                        <input type="hidden" name="receipt_year" value="<?php echo esc_attr( (string) $receipt_year ); ?>">
                        <p>
                            <button type="submit" name="mode" value="selected" class="button button-primary"><?php esc_html_e( 'Queue selected recipients', 'givoly' ); ?></button>
                            <button type="submit" name="mode" value="all" class="button" onclick='return confirm(<?php echo wp_json_encode( __( 'Queue all recipients for this year?', 'givoly' ) ); ?>)'><?php esc_html_e( 'Queue all recipients', 'givoly' ); ?></button>
                        </p>
                        <table class="wp-list-table widefat striped">
                            <thead><tr>
                                <th class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.givoly-receipt-check').forEach(function(c){c.checked=this.checked;}, this)"></th>
                                <th><?php esc_html_e( 'Donor', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Amount', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Donations', 'givoly' ); ?></th>
                                <th><?php esc_html_e( 'Individual action', 'givoly' ); ?></th>
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
                                    <td><button type="submit" name="single_donor_id" value="<?php echo esc_attr( (string) $recipient->id ); ?>" class="button button-small"><?php esc_html_e( 'Send this receipt', 'givoly' ); ?></button></td>
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
                    <h3><?php esc_html_e( 'Latest queue status', 'givoly' ); ?></h3>
                    <?php /* translators: 1: total jobs, 2: pending jobs, 3: processing jobs, 4: sent jobs, 5: failed jobs. */ ?>
                    <p><?php printf( esc_html__( '%1$d total: %2$d pending, %3$d processing, %4$d sent, %5$d failed.', 'givoly' ), esc_html( (string) $batch_stats['total'] ), esc_html( (string) $batch_stats['pending'] ), esc_html( (string) $batch_stats['processing'] ), esc_html( (string) $batch_stats['sent'] ), esc_html( (string) $batch_stats['failed'] ) ); ?></p>
                    <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Recipient', 'givoly' ); ?></th><th><?php esc_html_e( 'Status', 'givoly' ); ?></th><th><?php esc_html_e( 'Error', 'givoly' ); ?></th></tr></thead><tbody>
                    <?php foreach ( $batch_jobs as $job ) : ?><tr><td><?php echo esc_html( $job->recipient ); ?></td><td><?php echo esc_html( $job->status ); ?></td><td><?php echo esc_html( $job->last_error ?: '—' ); ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>

            <?php if ( empty( $donors ) ) : ?>
                <p><?php esc_html_e( 'No donors have been recorded yet.', 'givoly' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped givoly-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Donor number', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Donor', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Total donated', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Donation count', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Latest donation', 'givoly' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'givoly' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $donors as $donor ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $donor->donor_reference ?: '—' ); ?></code></td>
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
                                        <?php
                                        $totals = $donor->totals_by_currency ?? [];
                                        if ( ! empty( $totals ) ) {
                                            echo esc_html( \Givoly\Core\Format::amounts_by_currency( $totals ) );
                                        } else {
                                            echo esc_html( number_format( 0, 2, ',', ' ' ) . ' €' );
                                        }
                                        ?>
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
                                <td><a class="button button-small" href="<?php echo esc_url( add_query_arg( [ 'page' => 'givoly-donors', 'edit_donor' => (int) $donor->id ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'givoly' ); ?></a></td>
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

        // Page de donateurs d'abord (pagination stable), puis ventilation par devise.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix (trusted)
        $donors = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT dn.id, dn.donor_reference, dn.first_name, dn.last_name, dn.email, dn.company FROM {$table_dn} dn ORDER BY dn.id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                self::PER_PAGE,
                $offset
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( empty( $donors ) ) {
            return [];
        }

        $ids          = array_map( static fn( $row ): int => (int) $row['id'], $donors );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter -- placeholders built from integers, tables from $wpdb->prefix
        $totals = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT donor_id, currency, COALESCE( SUM( CASE WHEN status = 'completed' THEN amount ELSE 0 END ), 0 ) AS currency_total, COUNT( CASE WHEN status = 'completed' THEN 1 END ) AS currency_count, MAX( CASE WHEN status = 'completed' THEN created_at END ) AS last_donation FROM {$table_d} WHERE donor_id IN ({$placeholders}) GROUP BY donor_id, currency", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter
                ...$ids
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter

        $by_donor = [];
        foreach ( (array) $totals as $row ) {
            $donor_id = (int) ( $row['donor_id'] ?? 0 );
            $by_donor[ $donor_id ] ??= [ 'totals' => [], 'count' => 0, 'last' => null ];
            if ( ! empty( $row['currency'] ) && (float) ( $row['currency_total'] ?? 0 ) > 0 ) {
                $by_donor[ $donor_id ]['totals'][ strtoupper( (string) $row['currency'] ) ] = (float) $row['currency_total'];
            }
            $by_donor[ $donor_id ]['count'] += (int) ( $row['currency_count'] ?? 0 );
            if ( ! empty( $row['last_donation'] ) && ( $by_donor[ $donor_id ]['last'] === null || $row['last_donation'] > $by_donor[ $donor_id ]['last'] ) ) {
                $by_donor[ $donor_id ]['last'] = $row['last_donation'];
            }
        }

        $result = [];
        foreach ( $donors as $donor ) {
            $donor_id = (int) $donor['id'];
            $result[] = (object) [
                'id'                 => $donor['id'],
                'donor_reference'    => $donor['donor_reference'],
                'first_name'         => $donor['first_name'],
                'last_name'          => $donor['last_name'],
                'email'              => $donor['email'],
                'company'            => $donor['company'],
                'totals_by_currency' => $by_donor[ $donor_id ]['totals'] ?? [],
                'total_donated'      => array_sum( $by_donor[ $donor_id ]['totals'] ?? [] ),
                'donation_count'     => $by_donor[ $donor_id ]['count'] ?? 0,
                'last_donation'      => $by_donor[ $donor_id ]['last'] ?? null,
            ];
        }

        return $result;
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
            $wpdb->prepare( "SELECT id, donor_reference, email, first_name, last_name, company, address_line1, address_line2, postal_code, city, country, phone, stripe_customer_id, stripe_subscription_id FROM {$table} WHERE id = %d LIMIT 1", $donor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            OBJECT
        );
    }

    private function render_edit_form( object $donor ): void {
        $subscriptions = ( new \Givoly\Repository\SubscriptionRepository() )->find_by_donor( (int) $donor->id );
        ?>
        <div class="card" style="max-width: 900px;">
            <h2><?php esc_html_e( 'Edit donor record', 'givoly' ); ?> <span class="description"><?php echo esc_html( $donor->donor_reference ?: '#' . (string) $donor->id ); ?></span></h2>
            <p class="description"><?php esc_html_e( 'The donor number and Stripe identifiers are preserved. Editing the email address does not remove any donation history.', 'givoly' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'givoly_update_donor_' . (int) $donor->id, 'givoly_update_donor_nonce' ); ?>
                <input type="hidden" name="action" value="givoly_update_donor">
                <input type="hidden" name="donor_id" value="<?php echo esc_attr( (string) $donor->id ); ?>">
                <table class="form-table" role="presentation">
                    <tr><th><label for="givoly-donor-first-name"><?php esc_html_e( 'First name', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-first-name" name="first_name" type="text" value="<?php echo esc_attr( $donor->first_name ); ?>" required></td></tr>
                    <tr><th><label for="givoly-donor-last-name"><?php esc_html_e( 'Name', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-last-name" name="last_name" type="text" value="<?php echo esc_attr( $donor->last_name ); ?>" required></td></tr>
                    <tr><th><label for="givoly-donor-email"><?php esc_html_e( 'Email', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-email" name="email" type="email" value="<?php echo esc_attr( $donor->email ); ?>" required></td></tr>
                    <tr><th><label for="givoly-donor-company"><?php esc_html_e( 'Organization / company', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-company" name="company" type="text" value="<?php echo esc_attr( $donor->company ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-address1"><?php esc_html_e( 'Address', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-address1" name="address_line1" type="text" value="<?php echo esc_attr( $donor->address_line1 ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-address2"><?php esc_html_e( 'Address line 2', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-address2" name="address_line2" type="text" value="<?php echo esc_attr( $donor->address_line2 ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-postal-code"><?php esc_html_e( 'Postal code', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-postal-code" name="postal_code" type="text" value="<?php echo esc_attr( $donor->postal_code ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-city"><?php esc_html_e( 'City', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-city" name="city" type="text" value="<?php echo esc_attr( $donor->city ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-country"><?php esc_html_e( 'Country', 'givoly' ); ?></label></th><td><input class="small-text" id="givoly-donor-country" name="country" type="text" maxlength="2" value="<?php echo esc_attr( $donor->country ); ?>"></td></tr>
                    <tr><th><label for="givoly-donor-phone"><?php esc_html_e( 'Phone', 'givoly' ); ?></label></th><td><input class="regular-text" id="givoly-donor-phone" name="phone" type="tel" value="<?php echo esc_attr( $donor->phone ); ?>"></td></tr>
                </table>
                <?php submit_button( __( 'Save record', 'givoly' ), 'primary', 'submit', false ); ?>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=givoly-donors' ) ); ?>"><?php esc_html_e( 'Cancel', 'givoly' ); ?></a>
            </form>
            <?php /* ── Abonnements Stripe connus : un bloc par abonnement réel. */ ?>
            <h3><?php esc_html_e( 'Stripe subscriptions', 'givoly' ); ?></h3>
            <?php if ( empty( $subscriptions ) ) : ?>
                <p class="description"><?php esc_html_e( 'No known Stripe subscription for this donor.', 'givoly' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e( 'Subscription', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Currency', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'givoly' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $subscriptions as $subscription ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $subscription->stripe_subscription_id ); ?></code></td>
                            <td><?php echo esc_html( $subscription->status ); ?></td>
                            <td><?php echo esc_html( $subscription->currency ?: '—' ); ?></td>
                            <td>
                                <?php if ( $subscription->status === 'active' ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit='return confirm(<?php echo wp_json_encode( __( 'Confirm stopping future payments? The donor will retain access until the end of the current paid period.', 'givoly' ) ); ?>)'>
                                        <?php wp_nonce_field( 'givoly_cancel_subscription_' . $subscription->stripe_subscription_id ); ?>
                                        <input type="hidden" name="action" value="givoly_cancel_subscription">
                                        <input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->stripe_subscription_id ); ?>">
                                        <button type="submit" class="button button-small"><?php esc_html_e( 'Cancel recurring donation', 'givoly' ); ?></button>
                                    </form>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
