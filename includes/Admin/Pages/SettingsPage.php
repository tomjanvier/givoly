<?php
/**
 * Page de réglages Givoly — layout avec onglets.
 *
 * Onglets : Général | Stripe | HelloAsso | Association
 *
 * @package Givoly\Admin\Pages
 */

namespace Givoly\Admin\Pages;

use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SettingsPage {

    const NONCE_ACTION = 'givoly_save_settings';
    const NONCE_FIELD  = 'givoly_settings_nonce';

    private const TABS = [
        'general'     => [ 'label' => 'Général',     'icon' => 'dashicons-admin-settings'    ],
        'stripe'      => [ 'label' => 'Stripe',       'icon' => 'dashicons-cart'              ],
        'helloasso'   => [ 'label' => 'HelloAsso',    'icon' => 'dashicons-heart'             ],
        'association' => [ 'label' => 'Association',  'icon' => 'dashicons-building'          ],
        'email'       => [ 'label' => 'Email',        'icon' => 'dashicons-email-alt'         ],
        'appearance'  => [ 'label' => 'Apparence',    'icon' => 'dashicons-admin-appearance'  ],
    ];

    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_page_assets' ] );
    }

    public function enqueue_page_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'givoly' ) ) {
            return;
        }
        $css = '
/* ── Header ──────────────────────────────────────────────────── */
.givoly-header { display: flex; align-items: center; margin: 16px 0 8px; }
.givoly-header__title { font-size: 22px; font-weight: 700; margin: 0; line-height: 1.3; display: flex; align-items: center; gap: 8px; }
.givoly-header__logo { font-size: 24px; }
.givoly-header__sub  { font-weight: 400; color: #666; }
/* ── Onglets ─────────────────────────────────────────────────── */
.givoly-tabs { margin-bottom: 0 !important; border-bottom: 1px solid #c3c4c7; }
.givoly-tab { display: inline-flex !important; align-items: center; gap: 6px; padding: 8px 16px !important; font-size: 13px !important; }
.givoly-tab__icon { font-size: 16px !important; width: 16px !important; height: 16px !important; line-height: 1 !important; opacity: .7; }
.nav-tab-active .givoly-tab__icon { opacity: 1; }
.givoly-tab__dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-left: 2px; }
.givoly-tab__dot--ok   { background: #00a32a; }
.givoly-tab__dot--warn { background: #dba617; }
/* ── Panels ──────────────────────────────────────────────────── */
.givoly-tab-panel         { display: none; padding-top: 20px; }
.givoly-tab-panel.is-active { display: block; }
/* ── Cards ───────────────────────────────────────────────────── */
.givoly-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px 24px 4px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.givoly-card--stripe { border-top: 3px solid #635bff; }
.givoly-card--ha     { border-top: 3px solid #ff6b35; }
.givoly-card--email  { border-top: 3px solid #0ea5e9; }
.givoly-card__title { font-size: 15px; font-weight: 600; margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
.givoly-card__title .dashicons { font-size: 18px; width: 18px; height: 18px; line-height: 1; color: #666; }
.givoly-card--stripe .givoly-card__title .dashicons { color: #635bff; }
.givoly-card--ha     .givoly-card__title .dashicons { color: #ff6b35; }
.givoly-card__desc { color: #646970; font-size: 13px; margin: 0 0 12px; }
/* ── Section sep ─────────────────────────────────────────────── */
.givoly-section-sep { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #888; padding: 4px 0 0; border-top: 1px solid #f0f0f0; margin-top: 4px; }
tr:has(.givoly-section-sep) th, tr:has(.givoly-section-sep) td { padding-bottom: 0; }
/* ── Mode toggle ─────────────────────────────────────────────── */
.givoly-mode-toggle { display: inline-flex; border: 1px solid #c3c4c7; border-radius: 6px; overflow: hidden; }
.givoly-mode-toggle__option { padding: 5px 16px; font-size: 13px; cursor: pointer; background: #f6f7f7; display: flex; align-items: center; gap: 5px; transition: background .15s; }
.givoly-mode-toggle__option input { display: none; }
.givoly-mode-toggle__option.is-active { background: #2271b1; color: #fff; font-weight: 600; }
.givoly-mode-toggle__option--live.is-active { background: #00a32a; }
/* ── Gateway cards (Général) ─────────────────────────────────── */
.givoly-gateway-choice { display: flex; gap: 16px; margin: 16px 0; flex-wrap: wrap; }
.givoly-gateway-card { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; background: #fafafa; transition: border-color .15s, background .15s; min-width: 220px; }
.givoly-gateway-card input { display: none; }
.givoly-gateway-card:hover { border-color: #999; }
.givoly-gateway-card.is-selected { border-color: #2271b1; background: #f0f6fc; }
.givoly-gateway-card__icon { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; color: #fff; flex-shrink: 0; }
.givoly-gateway-card__icon--stripe { background: #635bff; }
.givoly-gateway-card__icon--ha     { background: #ff6b35; }
.givoly-gateway-card__name { font-weight: 600; font-size: 14px; }
/* ── Badges ──────────────────────────────────────────────────── */
.givoly-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.givoly-badge--ok   { background: #d1fae5; color: #065f46; }
.givoly-badge--warn { background: #fef3c7; color: #92400e; }
.givoly-badge--title { margin-left: 8px; }
/* ── Secret field ────────────────────────────────────────────── */
.givoly-secret-wrap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
/* ── Webhook URL field ───────────────────────────────────────── */
.givoly-webhook-field { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
.givoly-webhook-field__url { background: #f6f7f7; border: 1px solid #ddd; padding: 5px 10px; border-radius: 4px; font-size: 12px; word-break: break-all; }
.givoly-copy-btn { display: inline-flex !important; align-items: center; gap: 4px; font-size: 12px !important; height: 28px !important; padding: 0 10px !important; }
.givoly-copy-btn .dashicons { font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 1 !important; }
.givoly-copy-btn--copied { background: #d1fae5 !important; border-color: #6ee7b7 !important; color: #065f46 !important; }
/* ── Onglet Apparence ─────────────────────────────────── */
.givoly-card--appearance { border-top: 3px solid #1B6B4A; }
.givoly-card--appearance .givoly-card__title .dashicons { color: #1B6B4A; }
.givoly-shape-group { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 4px; }
.givoly-shape-card { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 18px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; background: #fafafa; transition: border-color .15s, background .15s; min-width: 100px; text-align: center; }
.givoly-shape-card input[type=radio] { display: none; }
.givoly-shape-card:hover { border-color: #aaa; }
.givoly-shape-card.is-selected { border-color: #1B6B4A; background: #f0f7f4; }
.givoly-shape-card__preview { display: block; width: 48px; height: 28px; background: #1B6B4A; }
.givoly-shape-card__label { font-weight: 600; font-size: 13px; color: #1a2e24; }
.givoly-shape-card__desc { color: #888; font-size: 11px; }
.givoly-shape-card__btn { display: inline-block; padding: 6px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; }
.givoly-shape-card__btn--filled { background: #1B6B4A; color: #fff; border: 2px solid #1B6B4A; }
.givoly-shape-card__btn--outline { background: transparent; color: #1B6B4A; border: 2px solid #1B6B4A; }
';
        wp_add_inline_style( 'givoly-admin', $css );

        // Interactive admin behavior is loaded from assets/js/givoly-admin.js.

    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $this->maybe_save();

        // ── Onglet actif ──────────────────────────────────────────────────
        $active = sanitize_key( $_GET['tab'] ?? 'general' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( self::TABS[ $active ] ) ) {
            $active = 'general';
        }

        // ── Données ───────────────────────────────────────────────────────

        // Stripe
        $stripe_mode  = Settings::get_stripe_mode();
        $pk_test      = (string) get_option( Settings::OPT_STRIPE_PK_TEST, '' );
        $pk_live      = (string) get_option( Settings::OPT_STRIPE_PK_LIVE, '' );
        $has_sk_test  = get_option( Settings::OPT_STRIPE_SK_TEST, '' ) !== '';
        $has_sk_live  = get_option( Settings::OPT_STRIPE_SK_LIVE, '' ) !== '';
        $has_webhook  = get_option( Settings::OPT_WEBHOOK_SECRET, '' ) !== '';
        $webhook_url  = rest_url( 'givoly/v1/webhook' );
        $stripe_ok    = Settings::is_configured();

        // HelloAsso
        $ha_mode          = Settings::get_helloasso_mode();
        $ha_org_slug      = Settings::get_helloasso_org_slug();
        $has_ha_client_id = Settings::get_helloasso_client_id() !== '';
        $has_ha_secret    = Settings::get_helloasso_client_secret() !== '';
        $has_ha_sig_key   = Settings::get_helloasso_signature_key() !== '';
        $ha_webhook_url   = rest_url( 'givoly/v1/helloasso-webhook' );
        $ha_ok            = Settings::is_helloasso_configured();
        $ha_button_notice = Settings::get_helloasso_button_notice();
        $ha_other_payments_url = Settings::get_helloasso_other_payments_url();
        $ha_once_use_other_payments_url = Settings::should_use_helloasso_other_payments_for_once();

        // Général
        $default_gateway = Settings::get_default_gateway();
        $stripe_enabled = Settings::is_stripe_enabled();
        $helloasso_enabled = Settings::is_helloasso_enabled();
        $success_url     = (string) get_option( Settings::OPT_SUCCESS_URL, '' );
        $cancel_url      = (string) get_option( Settings::OPT_CANCEL_URL, '' );
        $post_payment_show_phone = Settings::should_show_post_payment_phone();
        $post_payment_show_address = Settings::should_show_post_payment_address();
        $public_branding_enabled = Settings::should_show_public_branding();

        // Email
        $email_logo_url      = Settings::get_email_logo_url();
        $email_primary_color = Settings::get_email_primary_color();
        $email_sender_name   = (string) get_option( Settings::OPT_EMAIL_SENDER_NAME, '' );
        $email_thank_subject = (string) get_option( Settings::OPT_EMAIL_THANK_SUBJECT, '' );
        $email_thank_body    = (string) get_option( Settings::OPT_EMAIL_THANK_BODY, '' );
        $email_admin_donation_subject = (string) get_option( Settings::OPT_EMAIL_ADMIN_DONATION_SUBJECT, '' );
        $email_admin_donation_body    = (string) get_option( Settings::OPT_EMAIL_ADMIN_DONATION_BODY, '' );
        $email_tax_receipt_subject = (string) get_option( Settings::OPT_EMAIL_TAX_RECEIPT_SUBJECT, '' );
        $email_tax_receipt_body    = (string) get_option( Settings::OPT_EMAIL_TAX_RECEIPT_BODY, '' );
        $tax_receipt_pdf_enabled   = Settings::should_attach_tax_receipt_pdf();
        $tax_receipt_pdf_title     = (string) get_option( Settings::OPT_TAX_RECEIPT_PDF_TITLE, '' );
        $tax_receipt_pdf_body      = (string) get_option( Settings::OPT_TAX_RECEIPT_PDF_BODY, '' );
        $tax_receipt_pdf_footer    = (string) get_option( Settings::OPT_TAX_RECEIPT_PDF_FOOTER, '' );

        // Association
        $assoc = [
            'name'        => Settings::get_assoc_name(),
            'address'     => Settings::get_assoc_address(),
            'postal_code' => Settings::get_assoc_postal_code(),
            'city'        => Settings::get_assoc_city(),
            'siret'       => Settings::get_assoc_siret(),
            'rna'         => Settings::get_assoc_rna(),
            'fiscal_id'   => Settings::get_assoc_fiscal_id(),
            'email'       => Settings::get_assoc_email(),
        ];

        // Apparence
        $ap_primary    = Settings::get_appearance_primary_color();
        $ap_accent     = Settings::get_appearance_accent_color();
        $ap_radius     = Settings::get_appearance_radius();
        $ap_btn_style  = Settings::get_appearance_btn_style();

        // WordPress owns custom CSS. Point administrators to its native editor
        // instead of storing executable CSS in a Givoly option.
        $wordpress_css_url = wp_is_block_theme()
            ? admin_url( 'site-editor.php?path=%2Fstyles' )
            : add_query_arg( [ 'autofocus[section]' => 'custom_css' ], admin_url( 'customize.php' ) );

        $base_url = admin_url( 'admin.php?page=givoly-settings' );

        ?>
        <div class="wrap givoly-settings">

            <div class="givoly-header">
                <h1 class="givoly-header__title">
                    <span class="givoly-header__logo">💜</span>
                    Givoly <span class="givoly-header__sub">Réglages</span>
                </h1>
            </div>

            <?php settings_errors( 'givoly_settings' ); ?>

            <!-- ── Navigation onglets ─────────────────────────────────── -->
            <nav class="nav-tab-wrapper givoly-tabs">
                <?php foreach ( self::TABS as $slug => $tab ) :
                    $is_active = ( $slug === $active );
                    $status    = match ( $slug ) {
                        'stripe'    => $stripe_ok,
                        'helloasso' => $ha_ok,
                        default     => null,
                    };
                    ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
                       class="nav-tab givoly-tab <?php echo esc_attr( $is_active ? 'nav-tab-active' : '' ); ?>">
                        <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?> givoly-tab__icon"></span>
                        <?php echo esc_html( $tab['label'] ); ?>
                        <?php if ( $status === true ) : ?>
                            <span class="givoly-tab__dot givoly-tab__dot--ok" title="Configuré"></span>
                        <?php elseif ( $status === false ) : ?>
                            <span class="givoly-tab__dot givoly-tab__dot--warn" title="Non configuré"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post"
                  action="<?php echo esc_url( add_query_arg( 'tab', $active, $base_url ) ); ?>">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                <input type="hidden" name="givoly_active_tab" value="<?php echo esc_attr( $active ); ?>">

<?php include GIVOLY_PLUGIN_DIR . 'templates/admin/settings/tab-general.php'; ?>

<?php include GIVOLY_PLUGIN_DIR . 'templates/admin/settings/tab-stripe.php'; ?>

<?php include GIVOLY_PLUGIN_DIR . 'templates/admin/settings/tab-helloasso.php'; ?>

<?php include GIVOLY_PLUGIN_DIR . 'templates/admin/settings/tab-association.php'; ?>

<?php include GIVOLY_PLUGIN_DIR . 'templates/admin/settings/tab-email.php'; ?>

<?php include GIVOLY_PLUGIN_DIR . 'templates/admin/settings/tab-appearance.php'; ?>

            </form>
        </div>
        <?php // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    // ── Helpers d'affichage ────────────────────────────────────────────────

    private function secret_field( string $name, bool $has_value, string $empty_placeholder ): void {
        $placeholder = $has_value
            ? __( '(déjà configuré — laisser vide pour conserver)', 'givoly' )
            : $empty_placeholder;
        ?>
        <div class="givoly-secret-wrap">
            <input type="password"
                   name="<?php echo esc_attr( $name ); ?>"
                   value=""
                   class="regular-text"
                   placeholder="<?php echo esc_attr( $placeholder ); ?>"
                   autocomplete="new-password">
            <?php if ( $has_value ) : ?>
                <span class="givoly-badge givoly-badge--ok">✓ <?php esc_html_e( 'Configuré', 'givoly' ); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    private function webhook_url_field( string $url, ?string $event, string $destination ): void {
        ?>
        <div class="givoly-webhook-field">
            <code class="givoly-webhook-field__url" id="<?php echo esc_attr( 'dwurl-' . md5( $url ) ); ?>">
                <?php echo esc_html( $url ); ?>
            </code>
            <button type="button"
                    class="button givoly-copy-btn"
                    data-target="<?php echo esc_attr( 'dwurl-' . md5( $url ) ); ?>">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'Copier', 'givoly' ); ?>
            </button>
        </div>
        <p class="description">
            <?php
            printf(
                // translators: %s is the name of the destination service (e.g. "Stripe" or "HelloAsso").
                esc_html__( 'À renseigner dans : %s.', 'givoly' ),
                '<strong>' . esc_html( $destination ) . '</strong>'
            ); ?>
            <?php if ( $event ) : ?>
                <?php esc_html_e( 'Événement à activer :', 'givoly' ); ?>
                <code><?php echo esc_html( $event ); ?></code>
            <?php endif; ?>
        </p>
        <?php
    }

    // ── Sauvegarde ─────────────────────────────────────────────────────────

    private function maybe_save(): void {
        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
            return;
        }

        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        Settings::save_from_post( wp_unslash( $_POST ) );

        add_settings_error(
            'givoly_settings',
            'givoly_saved',
            __( 'Réglages enregistrés.', 'givoly' ),
            'updated'
        );
    }

}
