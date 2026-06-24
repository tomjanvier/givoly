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

        $js = '
( function () {
    // ── Gateway card selection ───────────────────────────────────
    document.querySelectorAll( \'.givoly-gateway-card input\' ).forEach( function ( radio ) {
        radio.addEventListener( \'change\', function () {
            document.querySelectorAll( \'.givoly-gateway-card\' ).forEach( function ( card ) {
                card.classList.remove( \'is-selected\' );
            } );
            radio.closest( \'.givoly-gateway-card\' ).classList.add( \'is-selected\' );
        } );
    } );

    // ── Mode toggle highlight ────────────────────────────────────
    document.querySelectorAll( \'.givoly-mode-toggle input\' ).forEach( function ( radio ) {
        radio.addEventListener( \'change\', function () {
            radio.closest( \'.givoly-mode-toggle\' )
                 .querySelectorAll( \'.givoly-mode-toggle__option\' )
                 .forEach( function ( opt ) { opt.classList.remove( \'is-active\' ); } );
            radio.closest( \'.givoly-mode-toggle__option\' ).classList.add( \'is-active\' );
        } );
    } );

    // ── Copy webhook URL ─────────────────────────────────────────
    document.querySelectorAll( \'.givoly-copy-btn\' ).forEach( function ( btn ) {
        btn.addEventListener( \'click\', function () {
            const target = document.getElementById( btn.dataset.target );
            if ( ! target ) return;
            navigator.clipboard.writeText( target.textContent.trim() ).then( function () {
                btn.classList.add( \'givoly-copy-btn--copied\' );
                btn.querySelector( \'.dashicons\' ).className = \'dashicons dashicons-yes\';
                setTimeout( function () {
                    btn.classList.remove( \'givoly-copy-btn--copied\' );
                    btn.querySelector( \'.dashicons\' ).className = \'dashicons dashicons-clipboard\';
                }, 2000 );
            } );
        } );
    } );

    // ── Color picker live preview (email) ────────────────────────
    var colorInput   = document.getElementById( \'givoly-email-color\' );
    var colorPreview = document.getElementById( \'givoly-color-preview\' );
    var colorHex     = document.getElementById( \'givoly-color-hex\' );
    if ( colorInput && colorPreview ) {
        colorInput.addEventListener( \'input\', function () {
            colorPreview.style.background = colorInput.value;
            if ( colorHex ) colorHex.textContent = colorInput.value;
        } );
    }

    // ── Apparence — color pickers live preview ───────────────────
    document.querySelectorAll( \'input[type=color][data-preview-id]\' ).forEach( function ( picker ) {
        picker.addEventListener( \'input\', function () {
            var preview = document.getElementById( picker.dataset.previewId );
            var hex     = document.getElementById( picker.dataset.hexId );
            var enabled = picker.dataset.enabledId ? document.getElementById( picker.dataset.enabledId ) : null;
            if ( preview ) preview.style.background = picker.value;
            if ( hex )     hex.textContent           = picker.value;
            if ( enabled ) enabled.value             = \'1\';
        } );
    } );

    // ── Apparence — bouton Réinitialiser ─────────────────────────
    document.querySelectorAll( \'.givoly-ap-reset\' ).forEach( function ( btn ) {
        btn.addEventListener( \'click\', function () {
            var fieldInput   = document.querySelector( \'input[name="\' + btn.dataset.field + \'"]\' );
            var enabledInput = document.getElementById( btn.dataset.enabled );
            var preview      = document.getElementById( btn.dataset.previewId );
            var hex          = document.getElementById( btn.dataset.hexId );
            if ( fieldInput )   fieldInput.value           = btn.dataset.default;
            if ( enabledInput ) enabledInput.value         = \'0\';
            if ( preview )      preview.style.background   = btn.dataset.default;
            if ( hex )          hex.textContent            = btn.dataset.default;
            btn.style.display = \'none\';
        } );
    } );

    // ── Apparence — shape cards selection ────────────────────────
    document.querySelectorAll( \'.givoly-shape-group input[type=radio]\' ).forEach( function ( radio ) {
        radio.addEventListener( \'change\', function () {
            var group = radio.closest( \'.givoly-shape-group\' );
            if ( ! group ) return;
            group.querySelectorAll( \'.givoly-shape-card\' ).forEach( function ( c ) {
                c.classList.remove( \'is-selected\' );
            } );
            radio.closest( \'.givoly-shape-card\' ).classList.add( \'is-selected\' );
        } );
    } );
} )();
';
        wp_add_inline_script( 'givoly-admin', $js, 'after' );
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

        // Email
        $email_logo_url      = Settings::get_email_logo_url();
        $email_primary_color = Settings::get_email_primary_color();
        $email_sender_name   = (string) get_option( Settings::OPT_EMAIL_SENDER_NAME, '' );
        $email_thank_subject = (string) get_option( Settings::OPT_EMAIL_THANK_SUBJECT, '' );
        $email_thank_body    = (string) get_option( Settings::OPT_EMAIL_THANK_BODY, '' );
        $email_tax_receipt_subject = (string) get_option( Settings::OPT_EMAIL_TAX_RECEIPT_SUBJECT, '' );
        $email_tax_receipt_body    = (string) get_option( Settings::OPT_EMAIL_TAX_RECEIPT_BODY, '' );

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
        $ap_custom_css = Settings::get_appearance_custom_css();

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

                <!-- ════════════════════════════════════════════════════════
                     Onglet : GÉNÉRAL
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'general' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-randomize"></span>
                            <?php esc_html_e( 'Passerelle par défaut', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Passerelle utilisée par [givoly_form] sans attribut gateway=.', 'givoly' ); ?>
                        </p>

                        <p class="description">
                            <?php esc_html_e( 'Activez Stripe, HelloAsso ou les deux. Quand les deux sont actifs, le formulaire affiche les deux boutons de paiement.', 'givoly' ); ?>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="stripe_enabled" value="1" <?php checked( $stripe_enabled ); ?>>
                                <?php esc_html_e( 'Activer Stripe sur les formulaires', 'givoly' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="helloasso_enabled" value="1" <?php checked( $helloasso_enabled ); ?>>
                                <?php esc_html_e( 'Activer HelloAsso sur les formulaires', 'givoly' ); ?>
                            </label>
                        </p>

                        <div class="givoly-gateway-choice">
                            <label class="givoly-gateway-card <?php echo esc_attr( $default_gateway === 'stripe' ? 'is-selected' : '' ); ?>">
                                <input type="radio" name="default_gateway" value="stripe"
                                    <?php checked( $default_gateway, 'stripe' ); ?>>
                                <span class="givoly-gateway-card__icon givoly-gateway-card__icon--stripe">S</span>
                                <span class="givoly-gateway-card__name">Stripe</span>
                                <?php if ( $stripe_ok ) : ?>
                                    <span class="givoly-badge givoly-badge--ok">✓ Configuré</span>
                                <?php else : ?>
                                    <span class="givoly-badge givoly-badge--warn">Non configuré</span>
                                <?php endif; ?>
                            </label>

                            <label class="givoly-gateway-card <?php echo esc_attr( $default_gateway === 'helloasso' ? 'is-selected' : '' ); ?>">
                                <input type="radio" name="default_gateway" value="helloasso"
                                    <?php checked( $default_gateway, 'helloasso' ); ?>>
                                <span class="givoly-gateway-card__icon givoly-gateway-card__icon--ha">H</span>
                                <span class="givoly-gateway-card__name">HelloAsso</span>
                                <?php if ( $ha_ok ) : ?>
                                    <span class="givoly-badge givoly-badge--ok">✓ Configuré</span>
                                <?php else : ?>
                                    <span class="givoly-badge givoly-badge--warn">Non configuré</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php esc_html_e( 'Pages de redirection', 'givoly' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Page de succès', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="success_url"
                                           value="<?php echo esc_attr( $success_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/merci/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affichée après un don réussi. Si vide, un message par défaut est utilisé.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Page d\'annulation', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="cancel_url"
                                           value="<?php echo esc_attr( $cancel_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/don/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affichée si le donateur annule le paiement.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Formulaire post-paiement', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="post_payment_show_phone" value="1" <?php checked( $post_payment_show_phone ); ?>>
                                        <?php esc_html_e( 'Demander le numéro de téléphone (facultatif)', 'givoly' ); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="post_payment_show_address" value="1" <?php checked( $post_payment_show_address ); ?>>
                                        <?php esc_html_e( 'Demander l\'adresse postale complète (facultatif)', 'givoly' ); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'Affiché après retour de paiement réussi (paramètre givoly_success=1).', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : STRIPE
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'stripe' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card givoly-card--stripe">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-cart"></span>
                            Stripe
                            <?php if ( $stripe_ok ) : ?>
                                <span class="givoly-badge givoly-badge--ok givoly-badge--title">✓ Configuré</span>
                            <?php else : ?>
                                <span class="givoly-badge givoly-badge--warn givoly-badge--title">Non configuré</span>
                            <?php endif; ?>
                        </h2>

                        <table class="form-table" role="presentation">

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Mode', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-mode-toggle">
                                        <label class="givoly-mode-toggle__option <?php echo esc_attr( $stripe_mode === 'test' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="stripe_mode" value="test"
                                                <?php checked( $stripe_mode, 'test' ); ?>>
                                            <?php esc_html_e( 'Test', 'givoly' ); ?>
                                        </label>
                                        <label class="givoly-mode-toggle__option givoly-mode-toggle__option--live <?php echo esc_attr( $stripe_mode === 'live' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="stripe_mode" value="live"
                                                <?php checked( $stripe_mode, 'live' ); ?>>
                                            <?php esc_html_e( 'Live', 'givoly' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Clés Test', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé publique', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="stripe_pk_test"
                                           value="<?php echo esc_attr( $pk_test ); ?>"
                                           class="regular-text" placeholder="pk_test_…">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé secrète', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_sk_test', $has_sk_test, 'sk_test_…' ); ?>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Clés Live', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé publique', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="stripe_pk_live"
                                           value="<?php echo esc_attr( $pk_live ); ?>"
                                           class="regular-text" placeholder="pk_live_…">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé secrète', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_sk_live', $has_sk_live, 'sk_live_…' ); ?>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Webhook', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'URL Webhook', 'givoly' ); ?></th>
                                <td><?php $this->webhook_url_field( $webhook_url, 'checkout.session.completed', 'Stripe → Développeurs → Webhooks' ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Secret Webhook', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_webhook_secret', $has_webhook, 'whsec_…' ); ?>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : HELLOASSO
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'helloasso' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card givoly-card--ha">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-heart"></span>
                            HelloAsso
                            <?php if ( $ha_ok ) : ?>
                                <span class="givoly-badge givoly-badge--ok givoly-badge--title">✓ Configuré</span>
                            <?php else : ?>
                                <span class="givoly-badge givoly-badge--warn givoly-badge--title">Non configuré</span>
                            <?php endif; ?>
                        </h2>

                        <table class="form-table" role="presentation">

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Mode', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-mode-toggle">
                                        <label class="givoly-mode-toggle__option <?php echo esc_attr( $ha_mode === 'sandbox' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="ha_mode" value="sandbox"
                                                <?php checked( $ha_mode, 'sandbox' ); ?>>
                                            <?php esc_html_e( 'Sandbox', 'givoly' ); ?>
                                        </label>
                                        <label class="givoly-mode-toggle__option givoly-mode-toggle__option--live <?php echo esc_attr( $ha_mode === 'live' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="ha_mode" value="live"
                                                <?php checked( $ha_mode, 'live' ); ?>>
                                            <?php esc_html_e( 'Live', 'givoly' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Slug organisation', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="ha_org_slug"
                                           value="<?php echo esc_attr( $ha_org_slug ); ?>"
                                           class="regular-text" placeholder="mon-association">
                                    <p class="description">
                                        <?php esc_html_e( 'Identifiant de votre organisation dans l\'URL HelloAsso.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Identifiants API', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row">Client ID</th>
                                <td>
                                    <?php $this->secret_field( 'ha_client_id', $has_ha_client_id, '' ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Client Secret</th>
                                <td>
                                    <?php $this->secret_field( 'ha_client_secret', $has_ha_secret, '' ); ?>
                                </td>
                            </tr>


                            <tr>
                                <th scope="row"><?php esc_html_e( 'Lien autres modes de paiements', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="ha_other_payments_url"
                                           value="<?php echo esc_attr( $ha_other_payments_url ); ?>"
                                           class="regular-text" placeholder="https://...">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Forcer les dons uniques via lien externe', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ha_once_use_other_payments_url" value="1" <?php checked( $ha_once_use_other_payments_url ); ?>>
                                        <?php esc_html_e( 'Utiliser le lien “autres modes de paiements” au lieu de l\'API HelloAsso pour les dons uniques.', 'givoly' ); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Texte sous bouton HelloAsso', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="ha_button_notice"
                                           value="<?php echo esc_attr( $ha_button_notice ); ?>"
                                           class="regular-text" placeholder="* Exemple de mention">
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Webhook', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'URL Webhook', 'givoly' ); ?></th>
                                <td><?php $this->webhook_url_field( $ha_webhook_url, null, 'Espace partenaire HelloAsso' ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé de signature', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'ha_signature_key', $has_ha_sig_key, __( 'Optionnelle — si vide, vérification par IP', 'givoly' ) ); ?>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : ASSOCIATION
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'association' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-building"></span>
                            <?php esc_html_e( 'Votre association', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Ces informations identifient votre association dans les emails et les exports.', 'givoly' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Nom', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_name" value="<?php echo esc_attr( $assoc['name'] ); ?>" class="regular-text" placeholder="Association Exemple"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Adresse', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_address" value="<?php echo esc_attr( $assoc['address'] ); ?>" class="regular-text" placeholder="12 rue de la Paix"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Code postal', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_postal_code" value="<?php echo esc_attr( $assoc['postal_code'] ); ?>" class="small-text" placeholder="75001"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Ville', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_city" value="<?php echo esc_attr( $assoc['city'] ); ?>" class="regular-text" placeholder="Paris"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'SIRET', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="assoc_siret" value="<?php echo esc_attr( $assoc['siret'] ); ?>" class="regular-text" placeholder="123 456 789 00012">
                                    <p class="description"><?php esc_html_e( 'Ou numéro RNA si pas de SIRET.', 'givoly' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'RNA', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_rna" value="<?php echo esc_attr( $assoc['rna'] ); ?>" class="regular-text" placeholder="W751012345"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Agrément fiscal', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="assoc_fiscal_id" value="<?php echo esc_attr( $assoc['fiscal_id'] ); ?>" class="regular-text" placeholder="Optionnel">
                                    <p class="description"><?php esc_html_e( 'Délivré par la Direction des finances publiques.', 'givoly' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                                <td>
                                    <input type="email" name="assoc_email" value="<?php echo esc_attr( $assoc['email'] ); ?>" class="regular-text" placeholder="contact@association.fr">
                                    <p class="description"><?php esc_html_e( 'Expéditeur des reçus fiscaux.', 'givoly' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : EMAIL
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'email' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card givoly-card--email">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e( 'Apparence des emails', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Personnalisez les emails envoyés automatiquement aux donateurs après chaque don.', 'givoly' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givoly-email-sender"><?php esc_html_e( 'Nom expéditeur', 'givoly' ); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="givoly-email-sender"
                                           name="email_sender_name"
                                           value="<?php echo esc_attr( $email_sender_name ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( Settings::get_assoc_name() ?: get_bloginfo( 'name' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affiché comme expéditeur dans la boîte email du donateur. Si vide, le nom de l\'association est utilisé.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="givoly-email-color"><?php esc_html_e( 'Couleur principale', 'givoly' ); ?></label>
                                </th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <input type="color"
                                               id="givoly-email-color"
                                               name="email_primary_color"
                                               value="<?php echo esc_attr( $email_primary_color ); ?>">
                                        <span id="givoly-color-preview"
                                              style="display:inline-block;width:80px;height:32px;border-radius:4px;background:<?php echo esc_attr( $email_primary_color ); ?>;border:1px solid #ddd;"></span>
                                        <code id="givoly-color-hex"><?php echo esc_html( $email_primary_color ); ?></code>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e( 'Couleur de l\'en-tête et du montant dans l\'email.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-logo"><?php esc_html_e( 'URL du logo', 'givoly' ); ?></label>
                                    </th>
                                <td>
                                    <input type="url"
                                           id="givoly-email-logo"
                                           name="email_logo_url"
                                           value="<?php echo esc_attr( $email_logo_url ); ?>"
                                           class="regular-text"
                                           placeholder="https://votresite.fr/logo.png">
                                    <p class="description">
                                        <?php esc_html_e( 'Logo affiché en haut de l\'email (PNG ou JPG recommandé, max 300px de large). Si vide, le nom de l\'association est affiché.', 'givoly' ); ?>
                                    </p>
                                    <?php if ( $email_logo_url ) : ?>
                                        <div style="margin-top:8px;">
                                            <img src="<?php echo esc_url( $email_logo_url ); ?>"
                                                 alt="<?php esc_attr_e( 'Aperçu du logo', 'givoly' ); ?>"
                                                 style="max-height:60px;max-width:200px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;">
                                        </div>
                                    <?php endif; ?>
                                </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-thank-subject"><?php esc_html_e( 'Sujet email de remerciement', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               class="regular-text"
                                               id="givoly-email-thank-subject"
                                               name="email_thank_subject"
                                               value="<?php echo esc_attr( $email_thank_subject ); ?>"
                                               placeholder="<?php esc_attr_e( 'Merci pour votre don — {site_name}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Variables disponibles : {site_name}, {amount}, {first_name}, {last_name}, {campaign}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-thank-body"><?php esc_html_e( 'Texte email de remerciement', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-thank-body"
                                                  name="email_thank_body"
                                                  rows="6"
                                                  class="large-text"
                                                  placeholder="<?php esc_attr_e( 'Bonjour {first_name},', 'givoly' ); ?>"><?php echo esc_textarea( $email_thank_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Vous pouvez personnaliser le message librement. Les variables seront remplacées automatiquement.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-tax-receipt-subject"><?php esc_html_e( 'Sujet reçu fiscal annuel', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               class="regular-text"
                                               id="givoly-email-tax-receipt-subject"
                                               name="email_tax_receipt_subject"
                                               value="<?php echo esc_attr( $email_tax_receipt_subject ); ?>"
                                               placeholder="<?php esc_attr_e( 'Votre reçu fiscal {year} — {association}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Variables disponibles : {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-tax-receipt-body"><?php esc_html_e( 'Document / texte du reçu fiscal', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-tax-receipt-body"
                                                  name="email_tax_receipt_body"
                                                  rows="10"
                                                  class="large-text"
                                                  placeholder="<?php echo esc_attr( Settings::get_email_tax_receipt_body() ); ?>"><?php echo esc_textarea( $email_tax_receipt_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Modèle utilisé pour le document fiscal envoyé depuis Donateurs. Variables : {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}, {association_address}, {siret}, {rna}, {fiscal_id}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : APPARENCE
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'appearance' ? 'is-active' : '' ); ?>">

                    <!-- Card Couleurs -->
                    <div class="givoly-card givoly-card--appearance">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php esc_html_e( 'Couleurs', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Cette couleur s\'applique à tous vos formulaires de don, quel que soit le thème shortcode. Laissez la valeur par défaut pour utiliser la couleur du thème.', 'givoly' ); ?>
                        </p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givoly-ap-primary">
                                        <?php esc_html_e( 'Couleur principale', 'givoly' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <input type="color"
                                               id="givoly-ap-primary"
                                               name="appearance_primary_color"
                                               value="<?php echo esc_attr( $ap_primary ?: '#2b1533' ); ?>"
                                               data-preview-id="givoly-ap-primary-preview"
                                               data-hex-id="givoly-ap-primary-hex"
                                               data-enabled-id="givoly-ap-primary-enabled">
                                        <span id="givoly-ap-primary-preview"
                                              style="display:inline-block;width:72px;height:32px;border-radius:4px;
                                                     background:<?php echo esc_attr( $ap_primary ?: '#2b1533' ); ?>;
                                                     border:1px solid #ddd;vertical-align:middle;"></span>
                                        <code id="givoly-ap-primary-hex"><?php echo esc_html( $ap_primary ?: '#2b1533' ); ?></code>
                                        <?php if ( $ap_primary !== '' ) : ?>
                                            <button type="button" class="button button-small givoly-ap-reset"
                                                    data-field="appearance_primary_color"
                                                    data-enabled="givoly-ap-primary-enabled"
                                                    data-preview-id="givoly-ap-primary-preview"
                                                    data-hex-id="givoly-ap-primary-hex"
                                                    data-default="#2b1533">
                                                <?php esc_html_e( 'Réinitialiser', 'givoly' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e( 'Boutons, bordures actives et accent du formulaire. Utilisé avec du texte blanc.', 'givoly' ); ?>
                                    </p>
                                    <input type="hidden"
                                           name="appearance_primary_color_enabled"
                                           id="givoly-ap-primary-enabled"
                                           value="<?php echo esc_attr( $ap_primary !== '' ? '1' : '0' ); ?>">
                                    <input type="hidden" name="appearance_accent_color_enabled" id="givoly-ap-accent-enabled" value="0">

                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Card Forme -->
                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-editor-expand"></span>
                            <?php esc_html_e( 'Forme', 'givoly' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Coins', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-shape-group">
                                        <?php
                                        $radius_opts = [
                                            'square'  => [ 'label' => 'Carré',       'preview_r' => '0px',  'desc' => '0 px'  ],
                                            'rounded' => [ 'label' => 'Arrondi',      'preview_r' => '8px',  'desc' => '12 px' ],
                                            'pill'    => [ 'label' => 'Très arrondi', 'preview_r' => '16px', 'desc' => '20 px' ],
                                        ];
                                        foreach ( $radius_opts as $val => $opt ) : ?>
                                            <label class="givoly-shape-card <?php echo esc_attr( $ap_radius === $val ? 'is-selected' : '' ); ?>">
                                                <input type="radio" name="appearance_radius"
                                                       value="<?php echo esc_attr( $val ); ?>"
                                                       <?php checked( $ap_radius, $val ); ?>>
                                                <span class="givoly-shape-card__preview"
                                                      style="border-radius:<?php echo esc_attr( $opt['preview_r'] ); ?>"></span>
                                                <span class="givoly-shape-card__label"><?php echo esc_html( $opt['label'] ); ?></span>
                                                <span class="givoly-shape-card__desc"><?php echo esc_html( $opt['desc'] ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Style du bouton', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-shape-group">
                                        <label class="givoly-shape-card <?php echo esc_attr( $ap_btn_style === 'filled' ? 'is-selected' : '' ); ?>">
                                            <input type="radio" name="appearance_btn_style" value="filled"
                                                   <?php checked( $ap_btn_style, 'filled' ); ?>>
                                            <span class="givoly-shape-card__btn givoly-shape-card__btn--filled">
                                                <?php esc_html_e( 'Donner', 'givoly' ); ?>
                                            </span>
                                            <span class="givoly-shape-card__label"><?php esc_html_e( 'Plein', 'givoly' ); ?></span>
                                        </label>
                                        <label class="givoly-shape-card <?php echo esc_attr( $ap_btn_style === 'outline' ? 'is-selected' : '' ); ?>">
                                            <input type="radio" name="appearance_btn_style" value="outline"
                                                   <?php checked( $ap_btn_style, 'outline' ); ?>>
                                            <span class="givoly-shape-card__btn givoly-shape-card__btn--outline">
                                                <?php esc_html_e( 'Donner', 'givoly' ); ?>
                                            </span>
                                            <span class="givoly-shape-card__label"><?php esc_html_e( 'Contour', 'givoly' ); ?></span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>


                    <!-- Card CSS personnalisé -->
                    <div class="givoly-card givoly-card--appearance">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php esc_html_e( 'CSS personnalisé', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Ajoutez ici du CSS pour modifier le bloc du formulaire sans toucher aux fichiers du thème. Ce code est chargé après le CSS Givoly.', 'givoly' ); ?>
                        </p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givoly-ap-custom-css"><?php esc_html_e( 'Code CSS', 'givoly' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="givoly-ap-custom-css"
                                              name="appearance_custom_css"
                                              rows="12"
                                              class="large-text code"
                                              spellcheck="false"
                                              placeholder=".givoly-wrap { max-width: 720px; }
.givoly-form__submit { text-transform: uppercase; }"><?php echo esc_textarea( $ap_custom_css ); ?></textarea>
                                    <p class="description">
                                        <?php esc_html_e( 'Astuce : ciblez .givoly-wrap, .givoly-form, .givoly-amount-btn ou .givoly-form__submit pour personnaliser uniquement le formulaire.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Card Aperçu -->
                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Aperçu du formulaire', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Rendu du formulaire avec vos réglages actuels sauvegardés.', 'givoly' ); ?>
                        </p>
                        <?php
                        $preview_nonce = wp_create_nonce( 'givoly_form_preview' );
                        $preview_url   = admin_url( 'admin-ajax.php?action=givoly_form_preview&_wpnonce=' . $preview_nonce );
                        ?>
                        <iframe id="givoly-ap-preview"
                                src="<?php echo esc_url( $preview_url ); ?>"
                                style="width:100%;height:540px;border:1px solid #e0e0e0;border-radius:6px;display:block;"
                                title="<?php esc_attr_e( 'Aperçu formulaire', 'givoly' ); ?>">
                        </iframe>
                        <p class="description" style="margin-top:8px;">
                            <?php esc_html_e( 'Enregistrez les réglages pour mettre à jour l\'aperçu.', 'givoly' ); ?>
                        </p>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>

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
