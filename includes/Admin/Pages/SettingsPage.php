<?php
/**
 * Page de réglages Givasso — layout avec onglets.
 *
 * Onglets : Général | Stripe | HelloAsso | Association
 *
 * @package Givasso\Admin\Pages
 */

namespace Givasso\Admin\Pages;

use Givasso\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SettingsPage {

    const NONCE_ACTION = 'givasso_save_settings';
    const NONCE_FIELD  = 'givasso_settings_nonce';

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
        if ( ! str_contains( $hook, 'givasso' ) ) {
            return;
        }
        $css = '
/* ── Header ──────────────────────────────────────────────────── */
.givasso-header { display: flex; align-items: center; margin: 16px 0 8px; }
.givasso-header__title { font-size: 22px; font-weight: 700; margin: 0; line-height: 1.3; display: flex; align-items: center; gap: 8px; }
.givasso-header__logo { font-size: 24px; }
.givasso-header__sub  { font-weight: 400; color: #666; }
/* ── Onglets ─────────────────────────────────────────────────── */
.givasso-tabs { margin-bottom: 0 !important; border-bottom: 1px solid #c3c4c7; }
.givasso-tab { display: inline-flex !important; align-items: center; gap: 6px; padding: 8px 16px !important; font-size: 13px !important; }
.givasso-tab__icon { font-size: 16px !important; width: 16px !important; height: 16px !important; line-height: 1 !important; opacity: .7; }
.nav-tab-active .givasso-tab__icon { opacity: 1; }
.givasso-tab__dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-left: 2px; }
.givasso-tab__dot--ok   { background: #00a32a; }
.givasso-tab__dot--warn { background: #dba617; }
/* ── Panels ──────────────────────────────────────────────────── */
.givasso-tab-panel         { display: none; padding-top: 20px; }
.givasso-tab-panel.is-active { display: block; }
/* ── Cards ───────────────────────────────────────────────────── */
.givasso-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px 24px 4px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.givasso-card--stripe { border-top: 3px solid #635bff; }
.givasso-card--ha     { border-top: 3px solid #ff6b35; }
.givasso-card--email  { border-top: 3px solid #0ea5e9; }
.givasso-card__title { font-size: 15px; font-weight: 600; margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
.givasso-card__title .dashicons { font-size: 18px; width: 18px; height: 18px; line-height: 1; color: #666; }
.givasso-card--stripe .givasso-card__title .dashicons { color: #635bff; }
.givasso-card--ha     .givasso-card__title .dashicons { color: #ff6b35; }
.givasso-card__desc { color: #646970; font-size: 13px; margin: 0 0 12px; }
/* ── Section sep ─────────────────────────────────────────────── */
.givasso-section-sep { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #888; padding: 4px 0 0; border-top: 1px solid #f0f0f0; margin-top: 4px; }
tr:has(.givasso-section-sep) th, tr:has(.givasso-section-sep) td { padding-bottom: 0; }
/* ── Mode toggle ─────────────────────────────────────────────── */
.givasso-mode-toggle { display: inline-flex; border: 1px solid #c3c4c7; border-radius: 6px; overflow: hidden; }
.givasso-mode-toggle__option { padding: 5px 16px; font-size: 13px; cursor: pointer; background: #f6f7f7; display: flex; align-items: center; gap: 5px; transition: background .15s; }
.givasso-mode-toggle__option input { display: none; }
.givasso-mode-toggle__option.is-active { background: #2271b1; color: #fff; font-weight: 600; }
.givasso-mode-toggle__option--live.is-active { background: #00a32a; }
/* ── Gateway cards (Général) ─────────────────────────────────── */
.givasso-gateway-choice { display: flex; gap: 16px; margin: 16px 0; flex-wrap: wrap; }
.givasso-gateway-card { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; background: #fafafa; transition: border-color .15s, background .15s; min-width: 220px; }
.givasso-gateway-card input { display: none; }
.givasso-gateway-card:hover { border-color: #999; }
.givasso-gateway-card.is-selected { border-color: #2271b1; background: #f0f6fc; }
.givasso-gateway-card__icon { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; color: #fff; flex-shrink: 0; }
.givasso-gateway-card__icon--stripe { background: #635bff; }
.givasso-gateway-card__icon--ha     { background: #ff6b35; }
.givasso-gateway-card__name { font-weight: 600; font-size: 14px; }
/* ── Badges ──────────────────────────────────────────────────── */
.givasso-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.givasso-badge--ok   { background: #d1fae5; color: #065f46; }
.givasso-badge--warn { background: #fef3c7; color: #92400e; }
.givasso-badge--title { margin-left: 8px; }
/* ── Secret field ────────────────────────────────────────────── */
.givasso-secret-wrap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
/* ── Webhook URL field ───────────────────────────────────────── */
.givasso-webhook-field { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
.givasso-webhook-field__url { background: #f6f7f7; border: 1px solid #ddd; padding: 5px 10px; border-radius: 4px; font-size: 12px; word-break: break-all; }
.givasso-copy-btn { display: inline-flex !important; align-items: center; gap: 4px; font-size: 12px !important; height: 28px !important; padding: 0 10px !important; }
.givasso-copy-btn .dashicons { font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 1 !important; }
.givasso-copy-btn--copied { background: #d1fae5 !important; border-color: #6ee7b7 !important; color: #065f46 !important; }
/* ── Onglet Apparence ─────────────────────────────────── */
.givasso-card--appearance { border-top: 3px solid #1B6B4A; }
.givasso-card--appearance .givasso-card__title .dashicons { color: #1B6B4A; }
.givasso-shape-group { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 4px; }
.givasso-shape-card { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 18px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; background: #fafafa; transition: border-color .15s, background .15s; min-width: 100px; text-align: center; }
.givasso-shape-card input[type=radio] { display: none; }
.givasso-shape-card:hover { border-color: #aaa; }
.givasso-shape-card.is-selected { border-color: #1B6B4A; background: #f0f7f4; }
.givasso-shape-card__preview { display: block; width: 48px; height: 28px; background: #1B6B4A; }
.givasso-shape-card__label { font-weight: 600; font-size: 13px; color: #1a2e24; }
.givasso-shape-card__desc { color: #888; font-size: 11px; }
.givasso-shape-card__btn { display: inline-block; padding: 6px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; }
.givasso-shape-card__btn--filled { background: #1B6B4A; color: #fff; border: 2px solid #1B6B4A; }
.givasso-shape-card__btn--outline { background: transparent; color: #1B6B4A; border: 2px solid #1B6B4A; }
';
        wp_add_inline_style( 'givasso-admin', $css );

        $js = '
( function () {
    // ── Gateway card selection ───────────────────────────────────
    document.querySelectorAll( \'.givasso-gateway-card input\' ).forEach( function ( radio ) {
        radio.addEventListener( \'change\', function () {
            document.querySelectorAll( \'.givasso-gateway-card\' ).forEach( function ( card ) {
                card.classList.remove( \'is-selected\' );
            } );
            radio.closest( \'.givasso-gateway-card\' ).classList.add( \'is-selected\' );
        } );
    } );

    // ── Mode toggle highlight ────────────────────────────────────
    document.querySelectorAll( \'.givasso-mode-toggle input\' ).forEach( function ( radio ) {
        radio.addEventListener( \'change\', function () {
            radio.closest( \'.givasso-mode-toggle\' )
                 .querySelectorAll( \'.givasso-mode-toggle__option\' )
                 .forEach( function ( opt ) { opt.classList.remove( \'is-active\' ); } );
            radio.closest( \'.givasso-mode-toggle__option\' ).classList.add( \'is-active\' );
        } );
    } );

    // ── Copy webhook URL ─────────────────────────────────────────
    document.querySelectorAll( \'.givasso-copy-btn\' ).forEach( function ( btn ) {
        btn.addEventListener( \'click\', function () {
            const target = document.getElementById( btn.dataset.target );
            if ( ! target ) return;
            navigator.clipboard.writeText( target.textContent.trim() ).then( function () {
                btn.classList.add( \'givasso-copy-btn--copied\' );
                btn.querySelector( \'.dashicons\' ).className = \'dashicons dashicons-yes\';
                setTimeout( function () {
                    btn.classList.remove( \'givasso-copy-btn--copied\' );
                    btn.querySelector( \'.dashicons\' ).className = \'dashicons dashicons-clipboard\';
                }, 2000 );
            } );
        } );
    } );

    // ── Color picker live preview (email) ────────────────────────
    var colorInput   = document.getElementById( \'givasso-email-color\' );
    var colorPreview = document.getElementById( \'givasso-color-preview\' );
    var colorHex     = document.getElementById( \'givasso-color-hex\' );
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
            if ( preview ) preview.style.background = picker.value;
            if ( hex )     hex.textContent           = picker.value;
        } );
    } );

    // ── Apparence — bouton Réinitialiser ─────────────────────────
    document.querySelectorAll( \'.givasso-ap-reset\' ).forEach( function ( btn ) {
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
    document.querySelectorAll( \'.givasso-shape-group input[type=radio]\' ).forEach( function ( radio ) {
        radio.addEventListener( \'change\', function () {
            var group = radio.closest( \'.givasso-shape-group\' );
            if ( ! group ) return;
            group.querySelectorAll( \'.givasso-shape-card\' ).forEach( function ( c ) {
                c.classList.remove( \'is-selected\' );
            } );
            radio.closest( \'.givasso-shape-card\' ).classList.add( \'is-selected\' );
        } );
    } );
} )();
';
        wp_add_inline_script( 'givasso-admin', $js, 'after' );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givasso' ) );
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
        $webhook_url  = rest_url( 'givasso/v1/webhook' );
        $stripe_ok    = Settings::is_configured();

        // HelloAsso
        $ha_mode          = Settings::get_helloasso_mode();
        $ha_org_slug      = Settings::get_helloasso_org_slug();
        $has_ha_client_id = Settings::get_helloasso_client_id() !== '';
        $has_ha_secret    = Settings::get_helloasso_client_secret() !== '';
        $has_ha_sig_key   = Settings::get_helloasso_signature_key() !== '';
        $ha_webhook_url   = rest_url( 'givasso/v1/helloasso-webhook' );
        $ha_ok            = Settings::is_helloasso_configured();

        // Général
        $default_gateway = Settings::get_default_gateway();
        $success_url     = (string) get_option( Settings::OPT_SUCCESS_URL, '' );
        $cancel_url      = (string) get_option( Settings::OPT_CANCEL_URL, '' );

        // Email
        $email_logo_url      = Settings::get_email_logo_url();
        $email_primary_color = Settings::get_email_primary_color();
        $email_sender_name   = (string) get_option( Settings::OPT_EMAIL_SENDER_NAME, '' );

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
        $ap_primary   = Settings::get_appearance_primary_color();
        $ap_accent    = Settings::get_appearance_accent_color();
        $ap_radius    = Settings::get_appearance_radius();
        $ap_btn_style = Settings::get_appearance_btn_style();

        $base_url = admin_url( 'admin.php?page=givasso-settings' );

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- all dynamic values in this block are boolean-derived CSS class toggles (hardcoded string literals, no user data)
        ?>
        <div class="wrap givasso-settings">

            <div class="givasso-header">
                <h1 class="givasso-header__title">
                    <span class="givasso-header__logo">💜</span>
                    Givasso <span class="givasso-header__sub">Réglages</span>
                </h1>
            </div>

            <?php settings_errors( 'givasso_settings' ); ?>

            <!-- ── Navigation onglets ─────────────────────────────────── -->
            <nav class="nav-tab-wrapper givasso-tabs">
                <?php foreach ( self::TABS as $slug => $tab ) :
                    $is_active = ( $slug === $active );
                    $status    = match ( $slug ) {
                        'stripe'    => $stripe_ok,
                        'helloasso' => $ha_ok,
                        default     => null,
                    };
                    ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
                       class="nav-tab givasso-tab <?php echo esc_attr( $is_active ? 'nav-tab-active' : '' ); ?>">
                        <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?> givasso-tab__icon"></span>
                        <?php echo esc_html( $tab['label'] ); ?>
                        <?php if ( $status === true ) : ?>
                            <span class="givasso-tab__dot givasso-tab__dot--ok" title="Configuré"></span>
                        <?php elseif ( $status === false ) : ?>
                            <span class="givasso-tab__dot givasso-tab__dot--warn" title="Non configuré"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post"
                  action="<?php echo esc_url( add_query_arg( 'tab', $active, $base_url ) ); ?>">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                <input type="hidden" name="givasso_active_tab" value="<?php echo esc_attr( $active ); ?>">

                <!-- ════════════════════════════════════════════════════════
                     Onglet : GÉNÉRAL
                ════════════════════════════════════════════════════════ -->
                <div class="givasso-tab-panel <?php echo $active === 'general' ? 'is-active' : ''; ?>">

                    <div class="givasso-card">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-randomize"></span>
                            <?php esc_html_e( 'Passerelle par défaut', 'givasso' ); ?>
                        </h2>
                        <p class="givasso-card__desc">
                            <?php esc_html_e( 'Passerelle utilisée par [givasso_form] sans attribut gateway=.', 'givasso' ); ?>
                        </p>

                        <div class="givasso-gateway-choice">
                            <label class="givasso-gateway-card <?php echo $default_gateway === 'stripe' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="default_gateway" value="stripe"
                                    <?php checked( $default_gateway, 'stripe' ); ?>>
                                <span class="givasso-gateway-card__icon givasso-gateway-card__icon--stripe">S</span>
                                <span class="givasso-gateway-card__name">Stripe</span>
                                <?php if ( $stripe_ok ) : ?>
                                    <span class="givasso-badge givasso-badge--ok">✓ Configuré</span>
                                <?php else : ?>
                                    <span class="givasso-badge givasso-badge--warn">Non configuré</span>
                                <?php endif; ?>
                            </label>

                            <label class="givasso-gateway-card <?php echo $default_gateway === 'helloasso' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="default_gateway" value="helloasso"
                                    <?php checked( $default_gateway, 'helloasso' ); ?>>
                                <span class="givasso-gateway-card__icon givasso-gateway-card__icon--ha">H</span>
                                <span class="givasso-gateway-card__name">HelloAsso</span>
                                <?php if ( $ha_ok ) : ?>
                                    <span class="givasso-badge givasso-badge--ok">✓ Configuré</span>
                                <?php else : ?>
                                    <span class="givasso-badge givasso-badge--warn">Non configuré</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>

                    <div class="givasso-card">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php esc_html_e( 'Pages de redirection', 'givasso' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Page de succès', 'givasso' ); ?></th>
                                <td>
                                    <input type="url" name="success_url"
                                           value="<?php echo esc_attr( $success_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/merci/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affichée après un don réussi. Si vide, un message par défaut est utilisé.', 'givasso' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Page d\'annulation', 'givasso' ); ?></th>
                                <td>
                                    <input type="url" name="cancel_url"
                                           value="<?php echo esc_attr( $cancel_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/don/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affichée si le donateur annule le paiement.', 'givasso' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givasso' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : STRIPE
                ════════════════════════════════════════════════════════ -->
                <div class="givasso-tab-panel <?php echo $active === 'stripe' ? 'is-active' : ''; ?>">

                    <div class="givasso-card givasso-card--stripe">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-cart"></span>
                            Stripe
                            <?php if ( $stripe_ok ) : ?>
                                <span class="givasso-badge givasso-badge--ok givasso-badge--title">✓ Configuré</span>
                            <?php else : ?>
                                <span class="givasso-badge givasso-badge--warn givasso-badge--title">Non configuré</span>
                            <?php endif; ?>
                        </h2>

                        <table class="form-table" role="presentation">

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Mode', 'givasso' ); ?></th>
                                <td>
                                    <div class="givasso-mode-toggle">
                                        <label class="givasso-mode-toggle__option <?php echo $stripe_mode === 'test' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="stripe_mode" value="test"
                                                <?php checked( $stripe_mode, 'test' ); ?>>
                                            <?php esc_html_e( 'Test', 'givasso' ); ?>
                                        </label>
                                        <label class="givasso-mode-toggle__option givasso-mode-toggle__option--live <?php echo $stripe_mode === 'live' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="stripe_mode" value="live"
                                                <?php checked( $stripe_mode, 'live' ); ?>>
                                            <?php esc_html_e( 'Live', 'givasso' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givasso-section-sep"><?php esc_html_e( 'Clés Test', 'givasso' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé publique', 'givasso' ); ?></th>
                                <td>
                                    <input type="text" name="stripe_pk_test"
                                           value="<?php echo esc_attr( $pk_test ); ?>"
                                           class="regular-text" placeholder="pk_test_…">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé secrète', 'givasso' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_sk_test', $has_sk_test, 'sk_test_…' ); ?>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givasso-section-sep"><?php esc_html_e( 'Clés Live', 'givasso' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé publique', 'givasso' ); ?></th>
                                <td>
                                    <input type="text" name="stripe_pk_live"
                                           value="<?php echo esc_attr( $pk_live ); ?>"
                                           class="regular-text" placeholder="pk_live_…">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé secrète', 'givasso' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_sk_live', $has_sk_live, 'sk_live_…' ); ?>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givasso-section-sep"><?php esc_html_e( 'Webhook', 'givasso' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'URL Webhook', 'givasso' ); ?></th>
                                <td><?php $this->webhook_url_field( $webhook_url, 'checkout.session.completed', 'Stripe → Développeurs → Webhooks' ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Secret Webhook', 'givasso' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_webhook_secret', $has_webhook, 'whsec_…' ); ?>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givasso' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : HELLOASSO
                ════════════════════════════════════════════════════════ -->
                <div class="givasso-tab-panel <?php echo $active === 'helloasso' ? 'is-active' : ''; ?>">

                    <div class="givasso-card givasso-card--ha">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-heart"></span>
                            HelloAsso
                            <?php if ( $ha_ok ) : ?>
                                <span class="givasso-badge givasso-badge--ok givasso-badge--title">✓ Configuré</span>
                            <?php else : ?>
                                <span class="givasso-badge givasso-badge--warn givasso-badge--title">Non configuré</span>
                            <?php endif; ?>
                        </h2>

                        <table class="form-table" role="presentation">

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Mode', 'givasso' ); ?></th>
                                <td>
                                    <div class="givasso-mode-toggle">
                                        <label class="givasso-mode-toggle__option <?php echo $ha_mode === 'sandbox' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="ha_mode" value="sandbox"
                                                <?php checked( $ha_mode, 'sandbox' ); ?>>
                                            <?php esc_html_e( 'Sandbox', 'givasso' ); ?>
                                        </label>
                                        <label class="givasso-mode-toggle__option givasso-mode-toggle__option--live <?php echo $ha_mode === 'live' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="ha_mode" value="live"
                                                <?php checked( $ha_mode, 'live' ); ?>>
                                            <?php esc_html_e( 'Live', 'givasso' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Slug organisation', 'givasso' ); ?></th>
                                <td>
                                    <input type="text" name="ha_org_slug"
                                           value="<?php echo esc_attr( $ha_org_slug ); ?>"
                                           class="regular-text" placeholder="mon-association">
                                    <p class="description">
                                        <?php esc_html_e( 'Identifiant de votre organisation dans l\'URL HelloAsso.', 'givasso' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givasso-section-sep"><?php esc_html_e( 'Identifiants API', 'givasso' ); ?></div></th></tr>

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

                            <tr><th colspan="2"><div class="givasso-section-sep"><?php esc_html_e( 'Webhook', 'givasso' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'URL Webhook', 'givasso' ); ?></th>
                                <td><?php $this->webhook_url_field( $ha_webhook_url, null, 'Espace partenaire HelloAsso' ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé de signature', 'givasso' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'ha_signature_key', $has_ha_sig_key, __( 'Optionnelle — si vide, vérification par IP', 'givasso' ) ); ?>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givasso' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : ASSOCIATION
                ════════════════════════════════════════════════════════ -->
                <div class="givasso-tab-panel <?php echo $active === 'association' ? 'is-active' : ''; ?>">

                    <div class="givasso-card">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-building"></span>
                            <?php esc_html_e( 'Votre association', 'givasso' ); ?>
                        </h2>
                        <p class="givasso-card__desc">
                            <?php esc_html_e( 'Ces informations apparaissent sur les reçus fiscaux CERFA envoyés aux donateurs.', 'givasso' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Nom', 'givasso' ); ?></th>
                                <td><input type="text" name="assoc_name" value="<?php echo esc_attr( $assoc['name'] ); ?>" class="regular-text" placeholder="Association Exemple"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Adresse', 'givasso' ); ?></th>
                                <td><input type="text" name="assoc_address" value="<?php echo esc_attr( $assoc['address'] ); ?>" class="regular-text" placeholder="12 rue de la Paix"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Code postal', 'givasso' ); ?></th>
                                <td><input type="text" name="assoc_postal_code" value="<?php echo esc_attr( $assoc['postal_code'] ); ?>" class="small-text" placeholder="75001"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Ville', 'givasso' ); ?></th>
                                <td><input type="text" name="assoc_city" value="<?php echo esc_attr( $assoc['city'] ); ?>" class="regular-text" placeholder="Paris"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'SIRET', 'givasso' ); ?></th>
                                <td>
                                    <input type="text" name="assoc_siret" value="<?php echo esc_attr( $assoc['siret'] ); ?>" class="regular-text" placeholder="123 456 789 00012">
                                    <p class="description"><?php esc_html_e( 'Ou numéro RNA si pas de SIRET.', 'givasso' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'RNA', 'givasso' ); ?></th>
                                <td><input type="text" name="assoc_rna" value="<?php echo esc_attr( $assoc['rna'] ); ?>" class="regular-text" placeholder="W751012345"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Agrément fiscal', 'givasso' ); ?></th>
                                <td>
                                    <input type="text" name="assoc_fiscal_id" value="<?php echo esc_attr( $assoc['fiscal_id'] ); ?>" class="regular-text" placeholder="Optionnel">
                                    <p class="description"><?php esc_html_e( 'Délivré par la Direction des finances publiques.', 'givasso' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Email', 'givasso' ); ?></th>
                                <td>
                                    <input type="email" name="assoc_email" value="<?php echo esc_attr( $assoc['email'] ); ?>" class="regular-text" placeholder="contact@association.fr">
                                    <p class="description"><?php esc_html_e( 'Expéditeur des reçus fiscaux.', 'givasso' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givasso' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : EMAIL
                ════════════════════════════════════════════════════════ -->
                <div class="givasso-tab-panel <?php echo $active === 'email' ? 'is-active' : ''; ?>">

                    <div class="givasso-card givasso-card--email">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e( 'Apparence des emails', 'givasso' ); ?>
                        </h2>
                        <p class="givasso-card__desc">
                            <?php esc_html_e( 'Personnalisez les emails envoyés automatiquement aux donateurs après chaque don.', 'givasso' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givasso-email-sender"><?php esc_html_e( 'Nom expéditeur', 'givasso' ); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="givasso-email-sender"
                                           name="email_sender_name"
                                           value="<?php echo esc_attr( $email_sender_name ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( Settings::get_assoc_name() ?: get_bloginfo( 'name' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affiché comme expéditeur dans la boîte email du donateur. Si vide, le nom de l\'association est utilisé.', 'givasso' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="givasso-email-color"><?php esc_html_e( 'Couleur principale', 'givasso' ); ?></label>
                                </th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <input type="color"
                                               id="givasso-email-color"
                                               name="email_primary_color"
                                               value="<?php echo esc_attr( $email_primary_color ); ?>">
                                        <span id="givasso-color-preview"
                                              style="display:inline-block;width:80px;height:32px;border-radius:4px;background:<?php echo esc_attr( $email_primary_color ); ?>;border:1px solid #ddd;"></span>
                                        <code id="givasso-color-hex"><?php echo esc_html( $email_primary_color ); ?></code>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e( 'Couleur de l\'en-tête et du montant dans l\'email.', 'givasso' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="givasso-email-logo"><?php esc_html_e( 'URL du logo', 'givasso' ); ?></label>
                                </th>
                                <td>
                                    <input type="url"
                                           id="givasso-email-logo"
                                           name="email_logo_url"
                                           value="<?php echo esc_attr( $email_logo_url ); ?>"
                                           class="regular-text"
                                           placeholder="https://votresite.fr/logo.png">
                                    <p class="description">
                                        <?php esc_html_e( 'Logo affiché en haut de l\'email (PNG ou JPG recommandé, max 300px de large). Si vide, le nom de l\'association est affiché.', 'givasso' ); ?>
                                    </p>
                                    <?php if ( $email_logo_url ) : ?>
                                        <div style="margin-top:8px;">
                                            <img src="<?php echo esc_url( $email_logo_url ); ?>"
                                                 alt="<?php esc_attr_e( 'Aperçu du logo', 'givasso' ); ?>"
                                                 style="max-height:60px;max-width:200px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;">
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Aperçu de l'email — disponible dans Givasso Pro -->
                    <div class="givasso-card">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Aperçu email', 'givasso' ); ?>
                        </h2>
                        <p class="givasso-card__desc">
                            <?php esc_html_e( 'L\'aperçu de l\'email de reçu fiscal est disponible dans Givasso Pro.', 'givasso' ); ?>
                        </p>
                        <a href="https://givasso.fr/pro" target="_blank" rel="noopener noreferrer" class="button">
                            <?php esc_html_e( 'Découvrir Givasso Pro →', 'givasso' ); ?>
                        </a>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givasso' ) ); ?>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : APPARENCE
                ════════════════════════════════════════════════════════ -->
                <div class="givasso-tab-panel <?php echo $active === 'appearance' ? 'is-active' : ''; ?>">

                    <!-- Card Couleurs -->
                    <div class="givasso-card givasso-card--appearance">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php esc_html_e( 'Couleurs', 'givasso' ); ?>
                        </h2>
                        <p class="givasso-card__desc">
                            <?php esc_html_e( 'Ces couleurs s\'appliquent à tous vos formulaires de don, quel que soit le thème shortcode. Laissez vide pour utiliser les couleurs du thème.', 'givasso' ); ?>
                        </p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givasso-ap-primary">
                                        <?php esc_html_e( 'Couleur principale', 'givasso' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <input type="color"
                                               id="givasso-ap-primary"
                                               name="appearance_primary_color"
                                               value="<?php echo esc_attr( $ap_primary ?: '#1B6B4A' ); ?>"
                                               data-preview-id="givasso-ap-primary-preview"
                                               data-hex-id="givasso-ap-primary-hex">
                                        <span id="givasso-ap-primary-preview"
                                              style="display:inline-block;width:72px;height:32px;border-radius:4px;
                                                     background:<?php echo esc_attr( $ap_primary ?: '#1B6B4A' ); ?>;
                                                     border:1px solid #ddd;vertical-align:middle;"></span>
                                        <code id="givasso-ap-primary-hex"><?php echo esc_html( $ap_primary ?: '#1B6B4A' ); ?></code>
                                        <?php if ( $ap_primary !== '' ) : ?>
                                            <button type="button" class="button button-small givasso-ap-reset"
                                                    data-field="appearance_primary_color"
                                                    data-enabled="appearance_primary_color_enabled"
                                                    data-preview-id="givasso-ap-primary-preview"
                                                    data-hex-id="givasso-ap-primary-hex"
                                                    data-default="#1B6B4A">
                                                <?php esc_html_e( 'Réinitialiser', 'givasso' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e( 'Boutons, bordures actives, checkboxes. Utilisé avec du texte blanc.', 'givasso' ); ?>
                                    </p>
                                    <input type="hidden"
                                           name="appearance_primary_color_enabled"
                                           id="givasso-ap-primary-enabled"
                                           value="<?php echo esc_attr( $ap_primary !== '' ? '1' : '0' ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="givasso-ap-accent">
                                        <?php esc_html_e( 'Couleur d\'accent', 'givasso' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <input type="color"
                                               id="givasso-ap-accent"
                                               name="appearance_accent_color"
                                               value="<?php echo esc_attr( $ap_accent ?: '#2ECC71' ); ?>"
                                               data-preview-id="givasso-ap-accent-preview"
                                               data-hex-id="givasso-ap-accent-hex">
                                        <span id="givasso-ap-accent-preview"
                                              style="display:inline-block;width:72px;height:32px;border-radius:4px;
                                                     background:<?php echo esc_attr( $ap_accent ?: '#2ECC71' ); ?>;
                                                     border:1px solid #ddd;vertical-align:middle;"></span>
                                        <code id="givasso-ap-accent-hex"><?php echo esc_html( $ap_accent ?: '#2ECC71' ); ?></code>
                                        <?php if ( $ap_accent !== '' ) : ?>
                                            <button type="button" class="button button-small givasso-ap-reset"
                                                    data-field="appearance_accent_color"
                                                    data-enabled="appearance_accent_color_enabled"
                                                    data-preview-id="givasso-ap-accent-preview"
                                                    data-hex-id="givasso-ap-accent-hex"
                                                    data-default="#2ECC71">
                                                <?php esc_html_e( 'Réinitialiser', 'givasso' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description" style="color:#d64545;">
                                        <?php esc_html_e( 'Badges Pro. Attention : ne jamais utiliser du texte blanc sur cette couleur.', 'givasso' ); ?>
                                    </p>
                                    <input type="hidden"
                                           name="appearance_accent_color_enabled"
                                           id="givasso-ap-accent-enabled"
                                           value="<?php echo esc_attr( $ap_accent !== '' ? '1' : '0' ); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Card Forme -->
                    <div class="givasso-card">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-editor-expand"></span>
                            <?php esc_html_e( 'Forme', 'givasso' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Coins', 'givasso' ); ?></th>
                                <td>
                                    <div class="givasso-shape-group">
                                        <?php
                                        $radius_opts = [
                                            'square'  => [ 'label' => 'Carré',       'preview_r' => '0px',  'desc' => '0 px'  ],
                                            'rounded' => [ 'label' => 'Arrondi',      'preview_r' => '8px',  'desc' => '12 px' ],
                                            'pill'    => [ 'label' => 'Très arrondi', 'preview_r' => '16px', 'desc' => '20 px' ],
                                        ];
                                        foreach ( $radius_opts as $val => $opt ) : ?>
                                            <label class="givasso-shape-card <?php echo $ap_radius === $val ? 'is-selected' : ''; ?>">
                                                <input type="radio" name="appearance_radius"
                                                       value="<?php echo esc_attr( $val ); ?>"
                                                       <?php checked( $ap_radius, $val ); ?>>
                                                <span class="givasso-shape-card__preview"
                                                      style="border-radius:<?php echo esc_attr( $opt['preview_r'] ); ?>"></span>
                                                <span class="givasso-shape-card__label"><?php echo esc_html( $opt['label'] ); ?></span>
                                                <span class="givasso-shape-card__desc"><?php echo esc_html( $opt['desc'] ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Style du bouton', 'givasso' ); ?></th>
                                <td>
                                    <div class="givasso-shape-group">
                                        <label class="givasso-shape-card <?php echo $ap_btn_style === 'filled' ? 'is-selected' : ''; ?>">
                                            <input type="radio" name="appearance_btn_style" value="filled"
                                                   <?php checked( $ap_btn_style, 'filled' ); ?>>
                                            <span class="givasso-shape-card__btn givasso-shape-card__btn--filled">
                                                <?php esc_html_e( 'Donner', 'givasso' ); ?>
                                            </span>
                                            <span class="givasso-shape-card__label"><?php esc_html_e( 'Plein', 'givasso' ); ?></span>
                                        </label>
                                        <label class="givasso-shape-card <?php echo $ap_btn_style === 'outline' ? 'is-selected' : ''; ?>">
                                            <input type="radio" name="appearance_btn_style" value="outline"
                                                   <?php checked( $ap_btn_style, 'outline' ); ?>>
                                            <span class="givasso-shape-card__btn givasso-shape-card__btn--outline">
                                                <?php esc_html_e( 'Donner', 'givasso' ); ?>
                                            </span>
                                            <span class="givasso-shape-card__label"><?php esc_html_e( 'Contour', 'givasso' ); ?></span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Card Aperçu -->
                    <div class="givasso-card">
                        <h2 class="givasso-card__title">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Aperçu du formulaire', 'givasso' ); ?>
                        </h2>
                        <p class="givasso-card__desc">
                            <?php esc_html_e( 'Rendu du formulaire avec vos réglages actuels sauvegardés.', 'givasso' ); ?>
                        </p>
                        <?php
                        $preview_nonce = wp_create_nonce( 'givasso_form_preview' );
                        $preview_url   = admin_url( 'admin-ajax.php?action=givasso_form_preview&_wpnonce=' . $preview_nonce );
                        ?>
                        <iframe id="givasso-ap-preview"
                                src="<?php echo esc_url( $preview_url ); ?>"
                                style="width:100%;height:540px;border:1px solid #e0e0e0;border-radius:6px;display:block;"
                                title="<?php esc_attr_e( 'Aperçu formulaire', 'givasso' ); ?>">
                        </iframe>
                        <p class="description" style="margin-top:8px;">
                            <?php esc_html_e( 'Enregistrez les réglages pour mettre à jour l\'aperçu.', 'givasso' ); ?>
                        </p>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givasso' ) ); ?>
                </div>

            </form>
        </div>
        <?php // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    // ── Helpers d'affichage ────────────────────────────────────────────────

    private function secret_field( string $name, bool $has_value, string $empty_placeholder ): void {
        $placeholder = $has_value
            ? __( '(déjà configuré — laisser vide pour conserver)', 'givasso' )
            : $empty_placeholder;
        ?>
        <div class="givasso-secret-wrap">
            <input type="password"
                   name="<?php echo esc_attr( $name ); ?>"
                   value=""
                   class="regular-text"
                   placeholder="<?php echo esc_attr( $placeholder ); ?>"
                   autocomplete="new-password">
            <?php if ( $has_value ) : ?>
                <span class="givasso-badge givasso-badge--ok">✓ <?php esc_html_e( 'Configuré', 'givasso' ); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    private function webhook_url_field( string $url, ?string $event, string $destination ): void {
        ?>
        <div class="givasso-webhook-field">
            <code class="givasso-webhook-field__url" id="<?php echo esc_attr( 'dwurl-' . md5( $url ) ); ?>">
                <?php echo esc_html( $url ); ?>
            </code>
            <button type="button"
                    class="button givasso-copy-btn"
                    data-target="<?php echo esc_attr( 'dwurl-' . md5( $url ) ); ?>">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'Copier', 'givasso' ); ?>
            </button>
        </div>
        <p class="description">
            <?php
            printf(
                // translators: %s is the name of the destination service (e.g. "Stripe" or "HelloAsso").
                esc_html__( 'À renseigner dans : %s.', 'givasso' ),
                '<strong>' . esc_html( $destination ) . '</strong>'
            ); ?>
            <?php if ( $event ) : ?>
                <?php esc_html_e( 'Événement à activer :', 'givasso' ); ?>
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

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
            wp_die( esc_html__( 'Nonce invalide.', 'givasso' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givasso' ) );
        }

        Settings::save_from_post( wp_unslash( $_POST ) );

        add_settings_error(
            'givasso_settings',
            'givasso_saved',
            __( 'Réglages enregistrés.', 'givasso' ),
            'updated'
        );
    }

}
