<?php
/**
 * Passwordless donor space with no WordPress account required.
 *
 * @package Givoly\Donor
 */

namespace Givoly\Donor;

use Givoly\Admin\Settings;
use Givoly\Gateway\StripeGateway;
use Givoly\Mail\TaxReceiptPdf;
use Givoly\Security\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DonorSpace {

    private const COOKIE       = 'givoly_donor_session';
    private const SESSION_TTL  = 2 * HOUR_IN_SECONDS;
    private const MAGIC_TTL    = 15 * MINUTE_IN_SECONDS;

    public function register(): void {
        add_shortcode( 'givoly_donor_space', [ $this, 'render' ] );
        // La consommation du magic link doit précéder tout envoi de contenu :
        // elle déclenche une redirection (cookie + nettoyage d'URL), impossible
        // depuis le rendu d'un shortcode quand les headers sont déjà partis.
        add_action( 'init', [ $this, 'maybe_consume_magic_link' ], 1 );
        add_action( 'wp_ajax_givoly_donor_request_access', [ $this, 'request_access' ] );
        add_action( 'wp_ajax_nopriv_givoly_donor_request_access', [ $this, 'request_access' ] );
        add_action( 'wp_ajax_givoly_donor_open_portal', [ $this, 'open_stripe_portal' ] );
        add_action( 'wp_ajax_nopriv_givoly_donor_open_portal', [ $this, 'open_stripe_portal' ] );
        add_action( 'wp_ajax_givoly_donor_cancel_subscription', [ $this, 'cancel_subscription' ] );
        add_action( 'wp_ajax_nopriv_givoly_donor_cancel_subscription', [ $this, 'cancel_subscription' ] );
        add_action( 'wp_ajax_givoly_donor_logout', [ $this, 'logout' ] );
        add_action( 'wp_ajax_nopriv_givoly_donor_logout', [ $this, 'logout' ] );
        add_action( 'admin_post_givoly_donor_download_receipt', [ $this, 'download_receipt' ] );
        add_action( 'admin_post_nopriv_givoly_donor_download_receipt', [ $this, 'download_receipt' ] );
    }

    public function render(): string {
        wp_enqueue_style( 'givoly-donor-space' );
        wp_enqueue_script( 'givoly-donor-space' );
        wp_localize_script(
            'givoly-donor-space',
            'givolyDonorSpaceData',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'givoly_donor_space' ),
                'i18n'    => [
                    'genericError' => __( 'An error occurred. Please try again.', 'givoly' ),
                    'sent'         => __( 'If a donor record exists for this address, a secure link has just been sent.', 'givoly' ),
                    'cancelled'    => __( 'Your cancellation is scheduled for the end of the current paid period.', 'givoly' ),
                ],
            ]
        );

        $session = $this->get_session();
        ob_start();
        if ( ! $session ) {
            $this->render_login();
        } else {
            $donor     = $this->get_donor( (int) $session['donor_id'] );
            $donations = $donor ? $this->get_donations( (int) $donor->id ) : [];
            if ( ! $donor ) {
                $this->clear_session();
                $this->render_login();
            } else {
                $this->render_dashboard( $donor, $donations );
            }
        }

        return (string) ob_get_clean();
    }

    public function request_access(): void {
        check_ajax_referer( 'givoly_donor_space', 'nonce' );

        if ( ! RateLimiter::is_allowed( 'donor_access' ) ) {
            wp_send_json_error( [ 'message' => __( 'Too many requests. Please wait before trying again.', 'givoly' ) ], 429 );
        }

        $email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $return_url = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ?? '' ) ), home_url( '/' ) );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid email address.', 'givoly' ) ], 422 );
        }

        $donor = $this->get_donor_by_email( $email );
        if ( $donor ) {
            $raw_token = bin2hex( random_bytes( 32 ) );
            global $wpdb;
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'givoly_donors',
                [
                    'magic_token_hash'       => hash( 'sha256', $raw_token ),
                    'magic_token_expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::MAGIC_TTL ),
                ],
                [ 'id' => (int) $donor->id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            $url = add_query_arg(
                [
                    'givoly_donor'       => (int) $donor->id,
                    'givoly_access_token' => $raw_token,
                ],
                $return_url
            );
            \Givoly\Mail\MailQueue::enqueue( 'donor_magic_login', [ 'email' => $email, 'url' => $url ], $email );
        }

        wp_send_json_success( [ 'message' => __( 'If a donor record exists for this address, a secure link has just been sent.', 'givoly' ) ] );
    }

    public function open_stripe_portal(): void {
        check_ajax_referer( 'givoly_donor_space', 'nonce' );
        $donor = $this->get_authenticated_donor();
        if ( ! $donor || ! $donor->stripe_customer_id ) {
            wp_send_json_error( [ 'message' => __( 'No manageable Stripe subscription is associated with your record.', 'givoly' ) ], 404 );
        }

        try {
            $return_url = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ?? '' ) ), home_url( '/' ) );
            $url = ( new StripeGateway( Settings::get_stripe_secret_key() ) )->create_billing_portal_session( (string) $donor->stripe_customer_id, $return_url );
            if ( ! wp_http_validate_url( $url ) ) {
                throw new \RuntimeException( 'Stripe portal unavailable.' );
            }
            wp_send_json_success( [ 'url' => $url ] );
        } catch ( \Throwable $exception ) {
            wp_send_json_error( [ 'message' => __( 'The Stripe portal is temporarily unavailable.', 'givoly' ) ], 503 );
        }
    }

    public function cancel_subscription(): void {
        check_ajax_referer( 'givoly_donor_space', 'nonce' );
        $donor = $this->get_authenticated_donor();
        if ( ! $donor ) {
            wp_send_json_error( [ 'message' => __( 'No active Stripe subscription is associated with your record.', 'givoly' ) ], 404 );
        }

        // Annulation ciblée : l'abonnement exact doit être transmis et appartenir au donateur.
        $subscription_id = sanitize_text_field( wp_unslash( $_POST['subscription_id'] ?? '' ) );
        if ( $subscription_id === '' ) {
            wp_send_json_error( [ 'message' => __( 'No active Stripe subscription is associated with your record.', 'givoly' ) ], 404 );
        }

        $known = ( new \Givoly\Repository\SubscriptionRepository() )->find_by_stripe_id( $subscription_id );
        if ( ! $known || (int) $known->donor_id !== (int) $donor->id ) {
            wp_send_json_error( [ 'message' => __( 'No active Stripe subscription is associated with your record.', 'givoly' ) ], 404 );
        }

        try {
            ( new StripeGateway( Settings::get_stripe_secret_key() ) )->cancel_subscription_at_period_end( (string) $known->stripe_subscription_id );
            ( new \Givoly\Repository\SubscriptionRepository() )->mark_cancel_at_period_end( (int) $known->id );
            wp_send_json_success( [ 'message' => __( 'Your cancellation is scheduled for the end of the current paid period.', 'givoly' ) ] );
        } catch ( \Throwable $exception ) {
            wp_send_json_error( [ 'message' => __( 'Cancellation is temporarily unavailable.', 'givoly' ) ], 503 );
        }
    }

    public function logout(): void {
        check_ajax_referer( 'givoly_donor_space', 'nonce' );
        $this->clear_session();
        wp_send_json_success();
    }

    public function download_receipt(): void {
        $donor = $this->get_authenticated_donor();
        $donation_id = absint( wp_unslash( $_GET['donation_id'] ?? 0 ) );
        if ( ! $donor || ! $donation_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'givoly_donor_receipt_' . $donation_id ) ) {
            wp_die( esc_html__( 'The receipt link is invalid or has expired.', 'givoly' ), '', [ 'response' => 403 ] );
        }

        global $wpdb;
        $table_d = esc_sql( $wpdb->prefix . 'givoly_donations' );
        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT d.id, d.amount, d.currency, d.created_at, dn.first_name, dn.last_name, dn.email
                 FROM {$table_d} d
                 INNER JOIN {$wpdb->prefix}givoly_donors dn ON dn.id = d.donor_id
                 WHERE d.id = %d AND d.donor_id = %d AND d.status = 'completed'
                 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $donation_id,
                (int) $donor->id
            ),
            ARRAY_A
        );
        if ( ! $row ) {
            wp_die( esc_html__( 'Receipt not found.', 'givoly' ), '', [ 'response' => 404 ] );
        }

        $name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
        $vars = [
            '{donor_name}' => $name ?: __( 'Donor', 'givoly' ),
            '{first_name}' => (string) $row['first_name'],
            '{last_name}'  => (string) $row['last_name'],
            '{year}'       => gmdate( 'Y', strtotime( (string) $row['created_at'] ) ),
            '{amount}'     => number_format_i18n( (float) $row['amount'], 2 ) . ' ' . (string) $row['currency'],
            '{donation_count}' => '1',
            '{association}' => Settings::get_assoc_name() ?: get_bloginfo( 'name' ),
            '{association_address}' => trim( implode( ' ', array_filter( [ Settings::get_assoc_address(), Settings::get_assoc_postal_code(), Settings::get_assoc_city() ] ) ) ),
            '{siret}' => Settings::get_assoc_siret(),
            '{rna}' => Settings::get_assoc_rna(),
            '{fiscal_id}' => Settings::get_assoc_fiscal_id(),
        ];
        $pdf = TaxReceiptPdf::generate(
            strtr( Settings::get_tax_receipt_pdf_title(), $vars ),
            strtr( Settings::get_tax_receipt_pdf_body(), $vars ),
            strtr( Settings::get_tax_receipt_pdf_footer(), $vars )
        );

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="givoly-recu-' . $donation_id . '.pdf"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF response.
        exit;
    }

    private function render_login(): void {
        ?>
        <section class="givoly-donor-space" aria-labelledby="givoly-donor-space-title">
            <h2 id="givoly-donor-space-title"><?php esc_html_e( 'Your donor space', 'givoly' ); ?></h2>
            <p><?php esc_html_e( 'Enter the email address used for your donations. We will send you a secure passwordless link.', 'givoly' ); ?></p>
            <form class="givoly-donor-space__login" data-givoly-donor-login>
                <label for="givoly-donor-email"><?php esc_html_e( 'Email address', 'givoly' ); ?></label>
                <input id="givoly-donor-email" name="email" type="email" autocomplete="email" required>
                <input type="hidden" name="return_url" value="<?php echo esc_attr( get_permalink() ?: home_url( '/' ) ); ?>">
                <button type="submit"><?php esc_html_e( 'Receive my access link', 'givoly' ); ?></button>
                <p class="givoly-donor-space__message" data-givoly-message role="status"></p>
            </form>
        </section>
        <?php
    }

    /** @param array<int,object> $donations */
    private function render_dashboard( object $donor, array $donations ): void {
        $subscriptions = ( new \Givoly\Repository\SubscriptionRepository() )->find_by_donor( (int) $donor->id );
        // Seuls les abonnements réellement connus et actifs proposent une action.
        $active_subscriptions = array_values(
            array_filter(
                $subscriptions,
                static fn( $subscription ): bool => isset( $subscription->status ) && $subscription->status === 'active'
            )
        );
        ?>
        <section class="givoly-donor-space" aria-labelledby="givoly-donor-space-title">
            <div class="givoly-donor-space__header">
                <div>
                    <p class="givoly-donor-space__eyebrow"><?php esc_html_e( 'Donor number', 'givoly' ); ?> <?php echo esc_html( $donor->donor_reference ?: '#' . (string) $donor->id ); ?></p>
                    <h2 id="givoly-donor-space-title"><?php echo esc_html( trim( $donor->first_name . ' ' . $donor->last_name ) ?: __( 'Your donor space', 'givoly' ) ); ?></h2>
                    <p><?php echo esc_html( $donor->email ); ?></p>
                </div>
                <button type="button" class="givoly-donor-space__logout" data-givoly-logout><?php esc_html_e( 'Log out', 'givoly' ); ?></button>
            </div>

            <?php if ( $donor->stripe_customer_id ) : ?>
                <div class="givoly-donor-space__subscription">
                    <h3><?php esc_html_e( 'My monthly donations', 'givoly' ); ?></h3>
                    <p><?php esc_html_e( 'You can change the amount or payment method in the secure Stripe portal.', 'givoly' ); ?></p>
                    <button type="button" data-givoly-portal><?php esc_html_e( 'Manage my subscriptions', 'givoly' ); ?></button>
                    <?php if ( ! empty( $active_subscriptions ) ) : ?>
                        <ul class="givoly-donor-space__subscriptions">
                        <?php foreach ( $active_subscriptions as $subscription ) : ?>
                            <li class="givoly-donor-space__subscription-item" data-subscription-id="<?php echo esc_attr( $subscription->stripe_subscription_id ); ?>">
                                <code><?php echo esc_html( $subscription->stripe_subscription_id ); ?></code>
                                <?php if ( ! empty( $subscription->currency ) ) : ?>
                                    <span>(<?php echo esc_html( $subscription->currency ); ?>)</span>
                                <?php endif; ?>
                                <button type="button" class="is-secondary" data-givoly-cancel-start data-subscription-id="<?php echo esc_attr( $subscription->stripe_subscription_id ); ?>"><?php esc_html_e( 'I want to cancel this subscription', 'givoly' ); ?></button>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'No active subscription with direct cancellation is known. Use the Stripe portal to manage your payments.', 'givoly' ); ?></p>
                    <?php endif; ?>
                    <div class="givoly-donor-space__retention" data-givoly-retention hidden>
                        <p><?php esc_html_e( 'Before you go, you can simply reduce the amount in the Stripe portal. Every contribution directly helps the organization continue its work.', 'givoly' ); ?></p>
                        <button type="button" data-givoly-portal><?php esc_html_e( 'Reduce instead of cancelling', 'givoly' ); ?></button>
                        <button type="button" class="is-danger" data-givoly-cancel-confirm><?php esc_html_e( 'Confirm cancellation', 'givoly' ); ?></button>
                    </div>
                    <p class="givoly-donor-space__message" data-givoly-message role="status"></p>
                </div>
            <?php endif; ?>

            <div class="givoly-donor-space__history">
                <h3><?php esc_html_e( 'My donation history', 'givoly' ); ?></h3>
                <?php if ( ! $donations ) : ?>
                    <p><?php esc_html_e( 'No completed donations found.', 'givoly' ); ?></p>
                <?php else : ?>
                    <div class="givoly-donor-space__table-wrap"><table>
                        <thead><tr><th><?php esc_html_e( 'Date', 'givoly' ); ?></th><th><?php esc_html_e( 'Amount', 'givoly' ); ?></th><th><?php esc_html_e( 'Method', 'givoly' ); ?></th><th><?php esc_html_e( 'Receipt', 'givoly' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $donations as $donation ) : ?>
                            <tr>
                                <td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $donation->created_at ) ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( (float) $donation->amount, 2 ) . ' ' . $donation->currency ); ?></td>
                                <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $donation->gateway ) ) ); ?></td>
                                <td><a href="<?php echo esc_url( $this->receipt_url( (int) $donation->id ) ); ?>"><?php esc_html_e( 'Download PDF', 'givoly' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    /**
     * Consomme le magic link présent dans l'URL, le cas échéant.
     *
     * Public : branché sur init (avant tout output). Ne fait rien sans les
     * paramètres givoly_donor + givoly_access_token.
     */
    public function maybe_consume_magic_link(): void {
        // Ne pas interférer avec les requêtes non frontend (cron, REST, AJAX).
        if ( wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
            return;
        }

        $this->consume_magic_link();
    }

    private function consume_magic_link(): void {
        $donor_id = absint( wp_unslash( $_GET['givoly_donor'] ?? 0 ) );
        $token    = sanitize_text_field( wp_unslash( $_GET['givoly_access_token'] ?? '' ) );
        if ( ! $donor_id || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
            return;
        }

        $donor = $this->get_donor( $donor_id );
        if ( ! $donor || empty( $donor->magic_token_hash ) || empty( $donor->magic_token_expires_at ) || strtotime( $donor->magic_token_expires_at ) < time() || ! hash_equals( (string) $donor->magic_token_hash, hash( 'sha256', $token ) ) ) {
            return;
        }

        $session = bin2hex( random_bytes( 32 ) );
        set_transient( $this->session_key( $session ), [ 'donor_id' => $donor_id ], self::SESSION_TTL );
        $this->set_cookie( $session );
        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'givoly_donors',
            [ 'magic_token_hash' => null, 'magic_token_expires_at' => null ],
            [ 'id' => $donor_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        wp_safe_redirect( remove_query_arg( [ 'givoly_donor', 'givoly_access_token' ] ) );
        exit;
    }

    private function get_authenticated_donor(): ?object {
        $session = $this->get_session();
        return $session ? $this->get_donor( (int) $session['donor_id'] ) : null;
    }

    private function get_session(): ?array {
        $value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ?? '' ) );
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $value ) ) {
            return null;
        }
        $session = get_transient( $this->session_key( $value ) );
        return is_array( $session ) && ! empty( $session['donor_id'] ) ? $session : null;
    }

    private function set_cookie( string $value ): void {
        setcookie( self::COOKIE, $value, [
            'expires'  => time() + self::SESSION_TTL,
            'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
    }

    private function clear_session(): void {
        $value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ?? '' ) );
        if ( preg_match( '/^[a-f0-9]{64}$/', $value ) ) {
            delete_transient( $this->session_key( $value ) );
        }
        setcookie( self::COOKIE, '', [ 'expires' => time() - HOUR_IN_SECONDS, 'path' => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ] );
    }

    private function session_key( string $value ): string {
        return 'givoly_donor_session_' . hash_hmac( 'sha256', $value, wp_salt( 'auth' ) );
    }

    private function get_donor( int $id ): ?object {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'givoly_donors' );
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            OBJECT
        );
    }

    private function get_donor_by_email( string $email ): ?object {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'givoly_donors' );
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            OBJECT
        );
    }

    /** @return array<int,object> */
    private function get_donations( int $donor_id ): array {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'givoly_donations' );
        return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT id, amount, currency, gateway, created_at FROM {$table} WHERE donor_id = %d AND status = 'completed' ORDER BY created_at DESC LIMIT 100", $donor_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            OBJECT
        );
    }

    private function receipt_url( int $donation_id ): string {
        return add_query_arg(
            [
                'action'       => 'givoly_donor_download_receipt',
                'donation_id'  => $donation_id,
                '_wpnonce'     => wp_create_nonce( 'givoly_donor_receipt_' . $donation_id ),
            ],
            admin_url( 'admin-post.php' )
        );
    }
}
