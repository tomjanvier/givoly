<?php
/**
 * Gestionnaire des réglages du plugin.
 *
 * Source unique pour lire/écrire toutes les options Givasso.
 * Toutes les autres classes passent par ici — jamais get_option() en direct.
 *
 * @package Givasso\Admin
 */

namespace Givasso\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Settings {

    // ── Noms des options WordPress ─────────────────────────────────────────

    // Stripe
    const OPT_STRIPE_MODE       = 'givasso_stripe_mode';
    const OPT_STRIPE_PK_TEST    = 'givasso_stripe_pk_test';
    const OPT_STRIPE_SK_TEST    = 'givasso_stripe_sk_test';
    const OPT_STRIPE_PK_LIVE    = 'givasso_stripe_pk_live';
    const OPT_STRIPE_SK_LIVE    = 'givasso_stripe_sk_live';
    const OPT_WEBHOOK_SECRET    = 'givasso_stripe_webhook_secret';
    const OPT_SUCCESS_URL       = 'givasso_success_url';
    const OPT_CANCEL_URL        = 'givasso_cancel_url';

    // HelloAsso
    const OPT_HA_CLIENT_ID     = 'givasso_ha_client_id';
    const OPT_HA_CLIENT_SECRET = 'givasso_ha_client_secret';
    const OPT_HA_ORG_SLUG      = 'givasso_ha_org_slug';
    const OPT_HA_MODE          = 'givasso_ha_mode';           // 'sandbox' | 'live'
    const OPT_HA_SIGNATURE_KEY = 'givasso_ha_signature_key';  // peut être vide
    const OPT_HA_MONTHLY_URL    = 'givasso_ha_monthly_url';
    const OPT_HA_BUTTON_NOTICE  = 'givasso_ha_button_notice';
    const OPT_HA_OTHER_PAYMENTS_URL = 'givasso_ha_other_payments_url';
    const OPT_HA_ONCE_USE_OTHER_PAYMENTS_URL = 'givasso_ha_once_use_other_payments_url';

    // Passerelle par défaut
    const OPT_DEFAULT_GATEWAY  = 'givasso_default_gateway';   // 'stripe' | 'helloasso'

    // Email — personnalisation des emails envoyés aux donateurs
    const OPT_EMAIL_LOGO_URL      = 'givasso_email_logo_url';
    const OPT_EMAIL_PRIMARY_COLOR = 'givasso_email_primary_color';  // hex, ex: #4f46e5
    const OPT_EMAIL_SENDER_NAME   = 'givasso_email_sender_name';    // défaut : nom association

    // Apparence — personnalisation visuelle du formulaire frontend
    const OPT_APPEARANCE_PRIMARY_COLOR = 'givasso_appearance_primary_color'; // hex, ex: #1B6B4A
    const OPT_APPEARANCE_ACCENT_COLOR  = 'givasso_appearance_accent_color';  // hex, ex: #2ECC71
    const OPT_APPEARANCE_RADIUS        = 'givasso_appearance_radius';        // 'square'|'rounded'|'pill'
    const OPT_APPEARANCE_BTN_STYLE     = 'givasso_appearance_btn_style';     // 'filled'|'outline'

    // Association — apparaissent sur les reçus CERFA
    const OPT_ASSOC_NAME        = 'givasso_assoc_name';
    const OPT_ASSOC_ADDRESS     = 'givasso_assoc_address';
    const OPT_ASSOC_POSTAL_CODE = 'givasso_assoc_postal_code';
    const OPT_ASSOC_CITY        = 'givasso_assoc_city';
    const OPT_ASSOC_SIRET       = 'givasso_assoc_siret';
    const OPT_ASSOC_RNA         = 'givasso_assoc_rna';
    const OPT_ASSOC_FISCAL_ID   = 'givasso_assoc_fiscal_id';
    const OPT_ASSOC_EMAIL       = 'givasso_assoc_email';

    // ── Lecture ────────────────────────────────────────────────────────────

    public static function get_stripe_mode(): string {
        return get_option( self::OPT_STRIPE_MODE, 'test' );
    }

    public static function is_test_mode(): bool {
        return self::get_stripe_mode() === 'test';
    }

    public static function get_stripe_public_key(): string {
        $opt = self::is_test_mode() ? self::OPT_STRIPE_PK_TEST : self::OPT_STRIPE_PK_LIVE;
        return (string) get_option( $opt, '' );
    }

    public static function get_stripe_secret_key(): string {
        $opt = self::is_test_mode() ? self::OPT_STRIPE_SK_TEST : self::OPT_STRIPE_SK_LIVE;
        return (string) get_option( $opt, '' );
    }

    public static function get_webhook_secret(): string {
        return (string) get_option( self::OPT_WEBHOOK_SECRET, '' );
    }

    public static function get_success_url(): string {
        $url = (string) get_option( self::OPT_SUCCESS_URL, '' );
        return $url ?: home_url( '/?givasso=success' );
    }

    public static function get_cancel_url(): string {
        $url = (string) get_option( self::OPT_CANCEL_URL, '' );
        return $url ?: home_url( '/?givasso=cancel' );
    }

    // ── Getters association ────────────────────────────────────────────────

    public static function get_assoc_name(): string        { return (string) get_option( self::OPT_ASSOC_NAME, '' ); }
    public static function get_assoc_address(): string     { return (string) get_option( self::OPT_ASSOC_ADDRESS, '' ); }
    public static function get_assoc_postal_code(): string { return (string) get_option( self::OPT_ASSOC_POSTAL_CODE, '' ); }
    public static function get_assoc_city(): string        { return (string) get_option( self::OPT_ASSOC_CITY, '' ); }
    public static function get_assoc_siret(): string       { return (string) get_option( self::OPT_ASSOC_SIRET, '' ); }
    public static function get_assoc_rna(): string         { return (string) get_option( self::OPT_ASSOC_RNA, '' ); }
    public static function get_assoc_fiscal_id(): string   { return (string) get_option( self::OPT_ASSOC_FISCAL_ID, '' ); }
    public static function get_assoc_email(): string       { return (string) get_option( self::OPT_ASSOC_EMAIL, get_option( 'admin_email', '' ) ); }

    // ── Getters email ──────────────────────────────────────────────────────

    /**
     * URL du logo de l'association à afficher dans les emails.
     * Vide si non configuré — le template affiche alors uniquement le nom textuel.
     */
    public static function get_email_logo_url(): string {
        return (string) get_option( self::OPT_EMAIL_LOGO_URL, '' );
    }

    /**
     * Couleur principale des emails (hex).
     * Utilisée pour l'en-tête et le montant.
     */
    public static function get_email_primary_color(): string {
        $color = (string) get_option( self::OPT_EMAIL_PRIMARY_COLOR, '' );
        // Valider le format hex strict : #rgb (3) ou #rrggbb (6) uniquement
        return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : '#1e293b';
    }

    /**
     * Nom affiché comme expéditeur des emails.
     * Défaut : nom de l'association (ou nom du blog si l'asso n'est pas configurée).
     */
    public static function get_email_sender_name(): string {
        $name = (string) get_option( self::OPT_EMAIL_SENDER_NAME, '' );
        return $name ?: ( self::get_assoc_name() ?: get_bloginfo( 'name' ) );
    }

    // ── Getters apparence ──────────────────────────────────────────────────

    /**
     * Couleur principale du formulaire (hex).
     * Retourne '' si non définie — FormConfig utilise alors la couleur du thème.
     */
    public static function get_appearance_primary_color(): string {
        $color = (string) get_option( self::OPT_APPEARANCE_PRIMARY_COLOR, '' );
        return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : '';
    }

    /**
     * Couleur d'accent du formulaire (hex).
     * Retourne '' si non définie.
     */
    public static function get_appearance_accent_color(): string {
        $color = (string) get_option( self::OPT_APPEARANCE_ACCENT_COLOR, '' );
        return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : '';
    }

    /**
     * Rayon des coins : 'square', 'rounded' (défaut), 'pill'.
     */
    public static function get_appearance_radius(): string {
        $val = (string) get_option( self::OPT_APPEARANCE_RADIUS, 'rounded' );
        return in_array( $val, [ 'square', 'rounded', 'pill' ], true ) ? $val : 'rounded';
    }

    /**
     * Style du bouton : 'filled' (défaut) ou 'outline'.
     */
    public static function get_appearance_btn_style(): string {
        $val = (string) get_option( self::OPT_APPEARANCE_BTN_STYLE, 'filled' );
        return in_array( $val, [ 'filled', 'outline' ], true ) ? $val : 'filled';
    }

    public static function is_assoc_configured(): bool {
        return self::get_assoc_name() !== '' && self::get_assoc_address() !== '';
    }

    // ── Stripe ─────────────────────────────────────────────────────────────

    public static function is_configured(): bool {
        return self::get_stripe_public_key() !== '' && self::get_stripe_secret_key() !== '';
    }

    // ── HelloAsso ──────────────────────────────────────────────────────────

    public static function get_helloasso_client_id(): string {
        return (string) get_option( self::OPT_HA_CLIENT_ID, '' );
    }

    public static function get_helloasso_client_secret(): string {
        return (string) get_option( self::OPT_HA_CLIENT_SECRET, '' );
    }

    public static function get_helloasso_org_slug(): string {
        return (string) get_option( self::OPT_HA_ORG_SLUG, '' );
    }

    public static function get_helloasso_mode(): string {
        return (string) get_option( self::OPT_HA_MODE, 'sandbox' );
    }

    public static function get_helloasso_signature_key(): string {
        return (string) get_option( self::OPT_HA_SIGNATURE_KEY, '' );
    }

    public static function get_helloasso_monthly_url(): string {
        return (string) get_option( self::OPT_HA_MONTHLY_URL, '' );
    }
    public static function get_helloasso_button_notice(): string {
        return (string) get_option( self::OPT_HA_BUTTON_NOTICE, '' );
    }

    public static function get_helloasso_other_payments_url(): string {
        return (string) get_option( self::OPT_HA_OTHER_PAYMENTS_URL, '' );
    }

    public static function should_use_helloasso_other_payments_for_once(): bool {
        return (string) get_option( self::OPT_HA_ONCE_USE_OTHER_PAYMENTS_URL, '0' ) === '1';
    }

    public static function is_helloasso_sandbox(): bool {
        return self::get_helloasso_mode() !== 'live';
    }

    public static function is_helloasso_configured(): bool {
        return self::get_helloasso_client_id() !== ''
            && self::get_helloasso_client_secret() !== ''
            && self::get_helloasso_org_slug() !== '';
    }

    // ── Passerelle par défaut ──────────────────────────────────────────────

    public static function get_default_gateway(): string {
        $gw = (string) get_option( self::OPT_DEFAULT_GATEWAY, 'stripe' );
        return in_array( $gw, [ 'stripe', 'helloasso' ], true ) ? $gw : 'stripe';
    }

    // ── Écriture ───────────────────────────────────────────────────────────

    /**
     * Sauvegarde les réglages depuis $_POST.
     * N'écrase pas une clé secrète si l'user soumet une valeur vide.
     */
    public static function save_from_post( array $post ): void {
        $mode = in_array( $post['stripe_mode'] ?? '', [ 'test', 'live' ], true )
            ? $post['stripe_mode']
            : 'test';

        update_option( self::OPT_STRIPE_MODE, $mode, false );

        // Clés publiques — toujours remplacées
        update_option( self::OPT_STRIPE_PK_TEST, sanitize_text_field( $post['stripe_pk_test'] ?? '' ), false );
        update_option( self::OPT_STRIPE_PK_LIVE, sanitize_text_field( $post['stripe_pk_live'] ?? '' ), false );

        // Clés secrètes — on ne remplace que si une nouvelle valeur est fournie
        self::update_secret( self::OPT_STRIPE_SK_TEST, $post['stripe_sk_test'] ?? '' );
        self::update_secret( self::OPT_STRIPE_SK_LIVE, $post['stripe_sk_live'] ?? '' );
        self::update_secret( self::OPT_WEBHOOK_SECRET, $post['stripe_webhook_secret'] ?? '' );

        // URLs
        update_option( self::OPT_SUCCESS_URL, esc_url_raw( $post['success_url'] ?? '' ), false );
        update_option( self::OPT_CANCEL_URL,  esc_url_raw( $post['cancel_url']  ?? '' ), false );

        // HelloAsso
        $ha_mode = in_array( $post['ha_mode'] ?? '', [ 'sandbox', 'live' ], true )
            ? $post['ha_mode']
            : 'sandbox';
        update_option( self::OPT_HA_MODE,     $ha_mode,                                          false );
        update_option( self::OPT_HA_ORG_SLUG, sanitize_text_field( $post['ha_org_slug'] ?? '' ), false );
        self::update_secret( self::OPT_HA_CLIENT_ID,     $post['ha_client_id']     ?? '' );
        self::update_secret( self::OPT_HA_CLIENT_SECRET, $post['ha_client_secret'] ?? '' );
        self::update_secret( self::OPT_HA_SIGNATURE_KEY, $post['ha_signature_key'] ?? '' );
        update_option( self::OPT_HA_MONTHLY_URL, esc_url_raw( $post['ha_monthly_url'] ?? '' ), false );
        update_option( self::OPT_HA_BUTTON_NOTICE, sanitize_text_field( $post['ha_button_notice'] ?? '' ), false );
        update_option( self::OPT_HA_OTHER_PAYMENTS_URL, esc_url_raw( $post['ha_other_payments_url'] ?? '' ), false );
        update_option( self::OPT_HA_ONCE_USE_OTHER_PAYMENTS_URL, isset( $post['ha_once_use_other_payments_url'] ) ? '1' : '0', false );

        // Passerelle par défaut
        $default_gw = in_array( $post['default_gateway'] ?? '', [ 'stripe', 'helloasso' ], true )
            ? $post['default_gateway']
            : 'stripe';
        update_option( self::OPT_DEFAULT_GATEWAY, $default_gw, false );

        // Email
        update_option( self::OPT_EMAIL_LOGO_URL,      esc_url_raw( $post['email_logo_url']           ?? '' ), false );
        update_option( self::OPT_EMAIL_SENDER_NAME,   sanitize_text_field( $post['email_sender_name'] ?? '' ), false );
        // Couleur : valider le format hex avant de sauvegarder
        $color = sanitize_text_field( $post['email_primary_color'] ?? '' );
        if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $color ) ) {
            update_option( self::OPT_EMAIL_PRIMARY_COLOR, $color, false );
        }

        // Apparence
        // Couleur principale : si enabled=0, effacer la valeur custom
        if ( ( $post['appearance_primary_color_enabled'] ?? '' ) === '0' ) {
            update_option( self::OPT_APPEARANCE_PRIMARY_COLOR, '', false );
        } else {
            $ap_primary = sanitize_text_field( $post['appearance_primary_color'] ?? '' );
            if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $ap_primary ) ) {
                update_option( self::OPT_APPEARANCE_PRIMARY_COLOR, $ap_primary, false );
            }
        }
        // Couleur accent : même logique
        if ( ( $post['appearance_accent_color_enabled'] ?? '' ) === '0' ) {
            update_option( self::OPT_APPEARANCE_ACCENT_COLOR, '', false );
        } else {
            $ap_accent = sanitize_text_field( $post['appearance_accent_color'] ?? '' );
            if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $ap_accent ) ) {
                update_option( self::OPT_APPEARANCE_ACCENT_COLOR, $ap_accent, false );
            }
        }
        $ap_radius = sanitize_text_field( $post['appearance_radius'] ?? '' );
        if ( in_array( $ap_radius, [ 'square', 'rounded', 'pill' ], true ) ) {
            update_option( self::OPT_APPEARANCE_RADIUS, $ap_radius, false );
        }
        $ap_btn = sanitize_text_field( $post['appearance_btn_style'] ?? '' );
        if ( in_array( $ap_btn, [ 'filled', 'outline' ], true ) ) {
            update_option( self::OPT_APPEARANCE_BTN_STYLE, $ap_btn, false );
        }

        // Association
        update_option( self::OPT_ASSOC_NAME,        sanitize_text_field( $post['assoc_name']        ?? '' ), false );
        update_option( self::OPT_ASSOC_ADDRESS,     sanitize_text_field( $post['assoc_address']     ?? '' ), false );
        update_option( self::OPT_ASSOC_POSTAL_CODE, sanitize_text_field( $post['assoc_postal_code'] ?? '' ), false );
        update_option( self::OPT_ASSOC_CITY,        sanitize_text_field( $post['assoc_city']        ?? '' ), false );
        update_option( self::OPT_ASSOC_SIRET,       sanitize_text_field( $post['assoc_siret']       ?? '' ), false );
        update_option( self::OPT_ASSOC_RNA,         sanitize_text_field( $post['assoc_rna']         ?? '' ), false );
        update_option( self::OPT_ASSOC_FISCAL_ID,   sanitize_text_field( $post['assoc_fiscal_id']   ?? '' ), false );
        update_option( self::OPT_ASSOC_EMAIL,       sanitize_email(      $post['assoc_email']       ?? '' ), false );
    }

    // ── Helpers privés ─────────────────────────────────────────────────────

    /**
     * Ne met à jour un secret que si l'user a saisi une vraie valeur.
     * Évite d'écraser la clé existante avec une chaîne vide.
     */
    private static function update_secret( string $option, string $value ): void {
        $value = sanitize_text_field( $value );

        if ( $value !== '' ) {
            update_option( $option, $value, false );
        }
    }
}
