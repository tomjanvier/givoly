<?php
/**
 * Gestionnaire des réglages du plugin.
 *
 * Source unique pour lire/écrire toutes les options Givoly.
 * Toutes les autres classes passent par ici — jamais get_option() en direct.
 *
 * @package Givoly\Admin
 */

namespace Givoly\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Settings {

    // ── Noms des options WordPress ─────────────────────────────────────────

    // Stripe
    const OPT_STRIPE_MODE       = 'givoly_stripe_mode';
    const OPT_STRIPE_PK_TEST    = 'givoly_stripe_pk_test';
    const OPT_STRIPE_SK_TEST    = 'givoly_stripe_sk_test';
    const OPT_STRIPE_PK_LIVE    = 'givoly_stripe_pk_live';
    const OPT_STRIPE_SK_LIVE    = 'givoly_stripe_sk_live';
    const OPT_WEBHOOK_SECRET    = 'givoly_stripe_webhook_secret';
    const OPT_SUCCESS_URL       = 'givoly_success_url';
    const OPT_CANCEL_URL        = 'givoly_cancel_url';
    const OPT_POST_PAYMENT_SHOW_PHONE   = 'givoly_post_payment_show_phone';
    const OPT_POST_PAYMENT_SHOW_ADDRESS = 'givoly_post_payment_show_address';

    // HelloAsso
    const OPT_HA_CLIENT_ID     = 'givoly_ha_client_id';
    const OPT_HA_CLIENT_SECRET = 'givoly_ha_client_secret';
    const OPT_HA_ORG_SLUG      = 'givoly_ha_org_slug';
    const OPT_HA_MODE          = 'givoly_ha_mode';           // 'sandbox' | 'live'
    const OPT_HA_SIGNATURE_KEY = 'givoly_ha_signature_key';  // peut être vide
    const OPT_HA_BUTTON_NOTICE  = 'givoly_ha_button_notice';
    const OPT_HA_OTHER_PAYMENTS_URL = 'givoly_ha_other_payments_url';
    const OPT_HA_ONCE_USE_OTHER_PAYMENTS_URL = 'givoly_ha_once_use_other_payments_url';

    // Activation des passerelles sur le formulaire
    const OPT_STRIPE_ENABLED   = 'givoly_stripe_enabled';
    const OPT_HELLOASSO_ENABLED = 'givoly_helloasso_enabled';

    // Default gateway
    const OPT_DEFAULT_GATEWAY  = 'givoly_default_gateway';   // 'stripe' | 'helloasso'

    // Email — personnalisation des emails envoyés aux donateurs
    const OPT_EMAIL_LOGO_URL      = 'givoly_email_logo_url';
    const OPT_EMAIL_PRIMARY_COLOR = 'givoly_email_primary_color';  // hex, ex: #4f46e5
    const OPT_EMAIL_SENDER_NAME   = 'givoly_email_sender_name';    // défaut : nom association
    const OPT_EMAIL_THANK_SUBJECT = 'givoly_email_thank_subject';
    const OPT_EMAIL_THANK_BODY    = 'givoly_email_thank_body';
    const OPT_EMAIL_ADMIN_DONATION_SUBJECT = 'givoly_email_admin_donation_subject';
    const OPT_EMAIL_ADMIN_DONATION_BODY    = 'givoly_email_admin_donation_body';
    const OPT_EMAIL_TAX_RECEIPT_SUBJECT = 'givoly_email_tax_receipt_subject';
    const OPT_EMAIL_TAX_RECEIPT_BODY    = 'givoly_email_tax_receipt_body';

    // Receipt fiscal PDF
    const OPT_TAX_RECEIPT_PDF_ENABLED = 'givoly_tax_receipt_pdf_enabled';
    const OPT_TAX_RECEIPT_PDF_TITLE   = 'givoly_tax_receipt_pdf_title';
    const OPT_TAX_RECEIPT_PDF_BODY    = 'givoly_tax_receipt_pdf_body';
    const OPT_TAX_RECEIPT_PDF_FOOTER  = 'givoly_tax_receipt_pdf_footer';

    // Apparence — personnalisation visuelle du formulaire frontend
    const OPT_APPEARANCE_PRIMARY_COLOR = 'givoly_appearance_primary_color'; // hex, ex: #1B6B4A
    const OPT_APPEARANCE_ACCENT_COLOR  = 'givoly_appearance_accent_color';  // hex, ex: #2ECC71
    const OPT_APPEARANCE_RADIUS        = 'givoly_appearance_radius';        // 'square'|'rounded'|'pill'
    const OPT_APPEARANCE_BTN_STYLE     = 'givoly_appearance_btn_style';     // 'filled'|'outline'

    // Association
    const OPT_ASSOC_NAME        = 'givoly_assoc_name';
    const OPT_ASSOC_ADDRESS     = 'givoly_assoc_address';
    const OPT_ASSOC_POSTAL_CODE = 'givoly_assoc_postal_code';
    const OPT_ASSOC_CITY        = 'givoly_assoc_city';
    const OPT_ASSOC_SIRET       = 'givoly_assoc_siret';
    const OPT_ASSOC_RNA         = 'givoly_assoc_rna';
    const OPT_ASSOC_FISCAL_ID   = 'givoly_assoc_fiscal_id';
    const OPT_ASSOC_EMAIL       = 'givoly_assoc_email';

    // Crédits publics — optionnels, désactivés par défaut pour respecter les règles WordPress.org.
    const OPT_PUBLIC_BRANDING_ENABLED = 'givoly_public_branding_enabled';

    // ── Lecture ────────────────────────────────────────────────────────────

    /**
     * Lit une option Givoly et retombe sur sa clé Givasso si nécessaire.
     *
     * Le fallback couvre notamment un site où l'ancien plugin est encore
     * actif pendant la transition ou une migration interrompue.
     *
     * @param mixed $default
     * @return mixed
     */
    private static function get_compat_option( string $option, $default = null ) {
        $value = get_option( $option, null );
        if ( null !== $value ) {
            return $value;
        }

        if ( str_starts_with( $option, 'givoly_' ) ) {
            $legacy = 'givasso_' . substr( $option, strlen( 'givoly_' ) );
            $value  = get_option( $legacy, null );
            if ( null !== $value ) {
                return $value;
            }
        }

        return $default;
    }

    public static function get_stripe_mode(): string {
        return self::get_compat_option( self::OPT_STRIPE_MODE, 'test' );
    }

    public static function is_test_mode(): bool {
        return self::get_stripe_mode() === 'test';
    }

    public static function get_stripe_public_key(): string {
        $opt = self::is_test_mode() ? self::OPT_STRIPE_PK_TEST : self::OPT_STRIPE_PK_LIVE;
        return (string) self::get_compat_option( $opt, '' );
    }

    public static function get_stripe_secret_key(): string {
        $opt = self::is_test_mode() ? self::OPT_STRIPE_SK_TEST : self::OPT_STRIPE_SK_LIVE;
        return (string) self::get_compat_option( $opt, '' );
    }

    public static function get_webhook_secret(): string {
        return (string) self::get_compat_option( self::OPT_WEBHOOK_SECRET, '' );
    }

    public static function get_success_url(): string {
        $url = (string) self::get_compat_option( self::OPT_SUCCESS_URL, '' );
        return $url ?: home_url( '/?givoly=success' );
    }

    public static function get_cancel_url(): string {
        $url = (string) self::get_compat_option( self::OPT_CANCEL_URL, '' );
        return $url ?: home_url( '/?givoly=cancel' );
    }

    public static function should_show_post_payment_phone(): bool {
        return (string) self::get_compat_option( self::OPT_POST_PAYMENT_SHOW_PHONE, '1' ) === '1';
    }

    public static function should_show_post_payment_address(): bool {
        return (string) self::get_compat_option( self::OPT_POST_PAYMENT_SHOW_ADDRESS, '1' ) === '1';
    }

    public static function is_stripe_enabled(): bool {
        return (string) self::get_compat_option( self::OPT_STRIPE_ENABLED, '1' ) === '1';
    }

    public static function is_helloasso_enabled(): bool {
        return (string) self::get_compat_option( self::OPT_HELLOASSO_ENABLED, '1' ) === '1';
    }

    public static function get_enabled_gateways(): array {
        $gateways = [];
        if ( self::is_stripe_enabled() ) {
            $gateways[] = 'stripe';
        }
        if ( self::is_helloasso_enabled() ) {
            $gateways[] = 'helloasso';
        }

        return $gateways ?: [ 'stripe' ];
    }

    // ── Getters association ────────────────────────────────────────────────

    public static function get_assoc_name(): string        { return (string) self::get_compat_option( self::OPT_ASSOC_NAME, '' ); }
    public static function get_assoc_address(): string     { return (string) self::get_compat_option( self::OPT_ASSOC_ADDRESS, '' ); }
    public static function get_assoc_postal_code(): string { return (string) self::get_compat_option( self::OPT_ASSOC_POSTAL_CODE, '' ); }
    public static function get_assoc_city(): string        { return (string) self::get_compat_option( self::OPT_ASSOC_CITY, '' ); }
    public static function get_assoc_siret(): string       { return (string) self::get_compat_option( self::OPT_ASSOC_SIRET, '' ); }
    public static function get_assoc_rna(): string         { return (string) self::get_compat_option( self::OPT_ASSOC_RNA, '' ); }
    public static function get_assoc_fiscal_id(): string   { return (string) self::get_compat_option( self::OPT_ASSOC_FISCAL_ID, '' ); }
    public static function get_assoc_email(): string       { return (string) self::get_compat_option( self::OPT_ASSOC_EMAIL, get_option( 'admin_email', '' ) ); }

    // ── Getters email ──────────────────────────────────────────────────────

    /**
     * Logo URL de l'association à afficher dans les emails.
     * Vide si non configuré — le template affiche alors uniquement le nom textuel.
     */
    public static function get_email_logo_url(): string {
        return (string) self::get_compat_option( self::OPT_EMAIL_LOGO_URL, '' );
    }

    /**
     * Primary color des emails (hex).
     * Utilisée pour l'en-tête et le montant.
     */
    public static function get_email_primary_color(): string {
        $color = (string) self::get_compat_option( self::OPT_EMAIL_PRIMARY_COLOR, '' );
        // Valider le format hex strict : #rgb (3) ou #rrggbb (6) uniquement
        return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : '#1e293b';
    }

    /**
     * Name affiché comme expéditeur des emails.
     * Défaut : nom de l'association (ou nom du blog si l'asso n'est pas configurée).
     */
    public static function get_email_sender_name(): string {
        $name = (string) self::get_compat_option( self::OPT_EMAIL_SENDER_NAME, '' );
        return $name ?: ( self::get_assoc_name() ?: get_bloginfo( 'name' ) );
    }

    public static function get_email_thank_subject(): string {
        $subject = (string) self::get_compat_option( self::OPT_EMAIL_THANK_SUBJECT, '' );
        return $subject !== '' ? $subject : __( 'Thank you for your donation — {site_name}', 'givoly' );
    }

    public static function get_email_thank_body(): string {
        $body = (string) self::get_compat_option( self::OPT_EMAIL_THANK_BODY, '' );
        return $body !== '' ? $body : __( "Hello {first_name},\n\nThank you for your donation of {amount}. Your support matters.", 'givoly' );
    }

    public static function get_email_admin_donation_subject(): string {
        $subject = (string) self::get_compat_option( self::OPT_EMAIL_ADMIN_DONATION_SUBJECT, '' );
        return $subject !== '' ? $subject : __( '[{site_name}] New donation received — {amount}', 'givoly' );
    }

    public static function get_email_admin_donation_body(): string {
        $body = (string) self::get_compat_option( self::OPT_EMAIL_ADMIN_DONATION_BODY, '' );
        return $body !== '' ? $body : __( "A new donation was received.\n\nID: {donation_id}\nAmount: {amount}\nDonor: {first_name} {last_name}\nEmail: {email}\nCampaign: {campaign}", 'givoly' );
    }

    public static function get_email_tax_receipt_subject(): string {
        $subject = (string) self::get_compat_option( self::OPT_EMAIL_TAX_RECEIPT_SUBJECT, '' );
        return $subject !== '' ? $subject : __( 'Your tax receipt for {year} — {association}', 'givoly' );
    }

    public static function get_email_tax_receipt_body(): string {
        $body = (string) self::get_compat_option( self::OPT_EMAIL_TAX_RECEIPT_BODY, '' );
        return $body !== '' ? $body : __( "Hello {donor_name},\n\nBelow is a summary of your donations made in {year}.\n\nTotal donated: {amount}\nNumber of donations: {donation_count}\nOrganization: {association}\nAddress: {association_address}\nSIRET: {siret}\nRNA: {rna}\nTax approval / ruling: {fiscal_id}\n\nThis message helps with year-end reporting. Verify the organization's information and attach your official tax receipt if required before using it as supporting documentation.\n\nThank you for your support.", 'givoly' );
    }

    public static function should_attach_tax_receipt_pdf(): bool {
        return (string) self::get_compat_option( self::OPT_TAX_RECEIPT_PDF_ENABLED, '1' ) === '1';
    }

    public static function get_tax_receipt_pdf_title(): string {
        $title = (string) self::get_compat_option( self::OPT_TAX_RECEIPT_PDF_TITLE, '' );
        return $title !== '' ? $title : __( 'Donation summary — {year}', 'givoly' );
    }

    public static function get_tax_receipt_pdf_body(): string {
        $body = (string) self::get_compat_option( self::OPT_TAX_RECEIPT_PDF_BODY, '' );
        return $body !== '' ? $body : __( "Donor: {donor_name}\n\nTotal donations: {amount}\nNumber of donations: {donation_count}\n\nOrganization: {association}\nAddress: {association_address}\nSIRET: {siret}\nRNA: {rna}\nTax approval / ruling: {fiscal_id}", 'givoly' );
    }

    public static function get_tax_receipt_pdf_footer(): string {
        $footer = (string) self::get_compat_option( self::OPT_TAX_RECEIPT_PDF_FOOTER, '' );
        return $footer !== '' ? $footer : __( 'This summary accompanies the email and does not replace an official tax receipt when one is required.', 'givoly' );
    }

    // ── Getters apparence ──────────────────────────────────────────────────

    /**
     * Primary color du formulaire (hex).
     * Retourne '' si non définie — FormConfig utilise alors la couleur du thème.
     */
    public static function get_appearance_primary_color(): string {
        $color = (string) self::get_compat_option( self::OPT_APPEARANCE_PRIMARY_COLOR, '' );
        return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : '';
    }

    /**
     * Couleur d'accent du formulaire (hex).
     * Retourne '' si non définie.
     */
    public static function get_appearance_accent_color(): string {
        $color = (string) self::get_compat_option( self::OPT_APPEARANCE_ACCENT_COLOR, '' );
        return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ? $color : '';
    }

    /**
     * Rayon des coins : 'square', 'rounded' (défaut), 'pill'.
     */
    public static function get_appearance_radius(): string {
        $val = (string) self::get_compat_option( self::OPT_APPEARANCE_RADIUS, 'rounded' );
        return in_array( $val, [ 'square', 'rounded', 'pill' ], true ) ? $val : 'rounded';
    }

    /**
     * Button style : 'filled' (défaut) ou 'outline'.
     */
    public static function get_appearance_btn_style(): string {
        $val = (string) self::get_compat_option( self::OPT_APPEARANCE_BTN_STYLE, 'filled' );
        return in_array( $val, [ 'filled', 'outline' ], true ) ? $val : 'filled';
    }

    public static function is_assoc_configured(): bool {
        return self::get_assoc_name() !== '' && self::get_assoc_address() !== '';
    }

    public static function should_show_public_branding(): bool {
        return (string) self::get_compat_option( self::OPT_PUBLIC_BRANDING_ENABLED, '0' ) === '1';
    }

    // ── Stripe ─────────────────────────────────────────────────────────────

    public static function is_configured(): bool {
        return self::get_stripe_public_key() !== '' && self::get_stripe_secret_key() !== '';
    }

    // ── HelloAsso ──────────────────────────────────────────────────────────

    public static function get_helloasso_client_id(): string {
        return (string) self::get_compat_option( self::OPT_HA_CLIENT_ID, '' );
    }

    public static function get_helloasso_client_secret(): string {
        return (string) self::get_compat_option( self::OPT_HA_CLIENT_SECRET, '' );
    }

    public static function get_helloasso_org_slug(): string {
        return (string) self::get_compat_option( self::OPT_HA_ORG_SLUG, '' );
    }

    public static function get_helloasso_mode(): string {
        return (string) self::get_compat_option( self::OPT_HA_MODE, 'sandbox' );
    }

    public static function get_helloasso_signature_key(): string {
        return (string) self::get_compat_option( self::OPT_HA_SIGNATURE_KEY, '' );
    }


    public static function get_helloasso_button_notice(): string {
        return (string) self::get_compat_option( self::OPT_HA_BUTTON_NOTICE, '' );
    }

    public static function get_helloasso_other_payments_url(): string {
        return (string) self::get_compat_option( self::OPT_HA_OTHER_PAYMENTS_URL, '' );
    }

    public static function should_use_helloasso_other_payments_for_once(): bool {
        return (string) self::get_compat_option( self::OPT_HA_ONCE_USE_OTHER_PAYMENTS_URL, '0' ) === '1';
    }

    public static function is_helloasso_sandbox(): bool {
        return self::get_helloasso_mode() !== 'live';
    }

    public static function is_helloasso_configured(): bool {
        return self::get_helloasso_client_id() !== ''
            && self::get_helloasso_client_secret() !== ''
            && self::get_helloasso_org_slug() !== '';
    }

    // ── Plateforme Givoly ────────────────────────────────────────────────
    // Connexion opt-in vers la plateforme SaaS (tableau de bord centralisé).
    // Désactivée par défaut : aucun appel distant tant que l'administrateur
    // ne l'active pas explicitement avec une URL et une clé API valides.

    const OPT_PLATFORM_ENABLED         = 'givoly_platform_enabled';
    const OPT_PLATFORM_BASE_URL        = 'givoly_platform_base_url';
    const OPT_PLATFORM_API_KEY         = 'givoly_platform_api_key';
    const OPT_PLATFORM_ORGANIZATION_ID = 'givoly_platform_organization_id';
    const OPT_PLATFORM_SYNC_DONATIONS  = 'givoly_platform_sync_donations';
    const OPT_PLATFORM_SYNC_CAMPAIGNS  = 'givoly_platform_sync_campaigns';
    const OPT_PLATFORM_SITE_ID         = 'givoly_platform_site_id';
    const OPT_PLATFORM_LAST_CHECK_AT   = 'givoly_platform_last_check_at';
    const OPT_PLATFORM_LAST_STATUS     = 'givoly_platform_last_status';
    const OPT_PLATFORM_LAST_ERROR      = 'givoly_platform_last_error';

    // ── Default gateway ──────────────────────────────────────────────

    public static function get_default_gateway(): string {
        $gw = (string) self::get_compat_option( self::OPT_DEFAULT_GATEWAY, 'stripe' );
        if ( ! in_array( $gw, [ 'stripe', 'helloasso' ], true ) ) {
            $gw = 'stripe';
        }

        return in_array( $gw, self::get_enabled_gateways(), true ) ? $gw : self::get_enabled_gateways()[0];
    }

    // ── Plateforme Givoly : lecture ──────────────────────────────────────

    public static function is_platform_enabled(): bool {
        return (string) self::get_compat_option( self::OPT_PLATFORM_ENABLED, '0' ) === '1';
    }

    public static function get_platform_base_url(): string {
        return (string) self::get_compat_option( self::OPT_PLATFORM_BASE_URL, '' );
    }

    public static function get_platform_api_key(): string {
        return (string) self::get_compat_option( self::OPT_PLATFORM_API_KEY, '' );
    }

    public static function get_platform_organization_id(): string {
        return (string) self::get_compat_option( self::OPT_PLATFORM_ORGANIZATION_ID, '' );
    }

    public static function should_sync_platform_donations(): bool {
        return (string) self::get_compat_option( self::OPT_PLATFORM_SYNC_DONATIONS, '1' ) === '1';
    }

    public static function should_sync_platform_campaigns(): bool {
        return (string) self::get_compat_option( self::OPT_PLATFORM_SYNC_CAMPAIGNS, '1' ) === '1';
    }

    public static function get_platform_site_id(): string {
        return (string) self::get_compat_option( self::OPT_PLATFORM_SITE_ID, '' );
    }

    public static function get_platform_last_status(): string {
        return (string) self::get_compat_option( self::OPT_PLATFORM_LAST_STATUS, '' );
    }

    public static function get_platform_last_check_at(): string {
        return (string) self::get_compat_option( self::OPT_PLATFORM_LAST_CHECK_AT, '' );
    }

    public static function get_platform_last_error(): string {
        return (string) self::get_compat_option( self::OPT_PLATFORM_LAST_ERROR, '' );
    }

    /**
     * La connexion n'est utilisable que si l'administrateur l'a activée
     * avec une URL et une clé API renseignées.
     */
    public static function is_platform_configured(): bool {
        return self::is_platform_enabled()
            && self::get_platform_base_url() !== ''
            && self::get_platform_api_key() !== '';
    }

    /**
     * Fabrique le client HTTP Plateforme, ou null si non configuré.
     *
     * L'URL est normalisée (https imposé sauf localhost) ; une URL invalide
     * équivaut à une connexion non configurée, sans appel distant.
     */
    public static function get_platform_client(): ?\Givoly\Gateway\PlatformGateway {
        if ( ! self::is_platform_configured() ) {
            return null;
        }

        $base_url = \Givoly\Gateway\PlatformGateway::normalize_base_url( self::get_platform_base_url() );
        if ( $base_url === '' ) {
            return null;
        }

        return new \Givoly\Gateway\PlatformGateway( $base_url, self::get_platform_api_key() );
    }

    /**
     * Mémorise le résultat d'un contrôle de santé (statut + erreur expurgée).
     */
    public static function record_platform_check( string $status, string $error = '' ): void {
        // Troncature sans dépendance mbstring (non garantie sur tous les hébergeurs).
        $preview = sanitize_text_field( $error );
        $preview = function_exists( 'mb_substr' ) ? mb_substr( $preview, 0, 500 ) : substr( $preview, 0, 500 );
        update_option( self::OPT_PLATFORM_LAST_CHECK_AT, current_time( 'mysql', true ), false );
        update_option( self::OPT_PLATFORM_LAST_STATUS, sanitize_key( $status ), false );
        update_option( self::OPT_PLATFORM_LAST_ERROR, $preview, false );
    }

    /**
     * Invalide l'enregistrement local quand l'URL, la clé ou l'organisation change.
     */
    public static function reset_platform_registration(): void {
        delete_option( self::OPT_PLATFORM_SITE_ID );
        update_option( self::OPT_PLATFORM_LAST_STATUS, 'unknown', false );
        update_option( self::OPT_PLATFORM_LAST_ERROR, '', false );
    }

    // ── Écriture ───────────────────────────────────────────────────────────

    /**
     * Sauvegarde les réglages depuis $_POST.
     * N'écrase pas une clé secrète si l'user soumet une valeur vide, sauf
     * suppression explicite via une case `clear_<option>` protégée par nonce.
     */
    public static function save_from_post( array $post ): void {
        // Suppressions explicites de secrets (rotation/révocation).
        self::maybe_clear_secrets( $post );

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

        // Activation des passerelles — garder au moins une option active.
        $stripe_enabled   = isset( $post['stripe_enabled'] );
        $helloasso_enabled = isset( $post['helloasso_enabled'] );
        if ( ! $stripe_enabled && ! $helloasso_enabled ) {
            $stripe_enabled = true;
        }
        update_option( self::OPT_STRIPE_ENABLED, $stripe_enabled ? '1' : '0', false );
        update_option( self::OPT_HELLOASSO_ENABLED, $helloasso_enabled ? '1' : '0', false );

        // URLs
        update_option( self::OPT_SUCCESS_URL, esc_url_raw( $post['success_url'] ?? '' ), false );
        update_option( self::OPT_CANCEL_URL,  esc_url_raw( $post['cancel_url']  ?? '' ), false );
        update_option( self::OPT_POST_PAYMENT_SHOW_PHONE, isset( $post['post_payment_show_phone'] ) ? '1' : '0', false );
        update_option( self::OPT_POST_PAYMENT_SHOW_ADDRESS, isset( $post['post_payment_show_address'] ) ? '1' : '0', false );
        update_option( self::OPT_PUBLIC_BRANDING_ENABLED, isset( $post['public_branding_enabled'] ) ? '1' : '0', false );

        // HelloAsso : purger les jetons OAuth si le mode, le client ou l'organisation change.
        // Un jeton sandbox ne doit jamais être réutilisé en live (et inversement).
        $old_ha_mode   = (string) get_option( self::OPT_HA_MODE, 'sandbox' );
        $old_ha_org    = (string) get_option( self::OPT_HA_ORG_SLUG, '' );
        $old_ha_client = (string) get_option( self::OPT_HA_CLIENT_ID, '' );
        $old_ha_secret = (string) get_option( self::OPT_HA_CLIENT_SECRET, '' );

        // HelloAsso
        $ha_mode = in_array( $post['ha_mode'] ?? '', [ 'sandbox', 'live' ], true )
            ? $post['ha_mode']
            : 'sandbox';
        $new_ha_org = sanitize_text_field( $post['ha_org_slug'] ?? '' );
        update_option( self::OPT_HA_MODE,     $ha_mode,      false );
        update_option( self::OPT_HA_ORG_SLUG, $new_ha_org,   false );
        self::update_secret( self::OPT_HA_CLIENT_ID,     $post['ha_client_id']     ?? '' );
        self::update_secret( self::OPT_HA_CLIENT_SECRET, $post['ha_client_secret'] ?? '' );
        self::update_secret( self::OPT_HA_SIGNATURE_KEY, $post['ha_signature_key'] ?? '' );

        $new_ha_client = (string) get_option( self::OPT_HA_CLIENT_ID, '' );
        $new_ha_secret = (string) get_option( self::OPT_HA_CLIENT_SECRET, '' );
        if ( $old_ha_mode !== $ha_mode
            || $old_ha_org !== $new_ha_org
            || $old_ha_client !== $new_ha_client
            || $old_ha_secret !== $new_ha_secret
        ) {
            self::purge_helloasso_tokens();
        }
        update_option( self::OPT_HA_BUTTON_NOTICE, sanitize_text_field( $post['ha_button_notice'] ?? '' ), false );
        update_option( self::OPT_HA_OTHER_PAYMENTS_URL, esc_url_raw( $post['ha_other_payments_url'] ?? '' ), false );
        update_option( self::OPT_HA_ONCE_USE_OTHER_PAYMENTS_URL, isset( $post['ha_once_use_other_payments_url'] ) ? '1' : '0', false );

        // Plateforme Givoly (opt-in, désactivée par défaut).
        $old_platform_url = (string) get_option( self::OPT_PLATFORM_BASE_URL, '' );
        $old_platform_key = (string) get_option( self::OPT_PLATFORM_API_KEY, '' );
        $old_platform_org = (string) get_option( self::OPT_PLATFORM_ORGANIZATION_ID, '' );

        update_option( self::OPT_PLATFORM_ENABLED, isset( $post['platform_enabled'] ) ? '1' : '0', false );
        update_option( self::OPT_PLATFORM_BASE_URL, esc_url_raw( trim( (string) ( $post['platform_base_url'] ?? '' ) ) ), false );
        update_option( self::OPT_PLATFORM_ORGANIZATION_ID, sanitize_text_field( $post['platform_organization_id'] ?? '' ), false );
        self::update_secret( self::OPT_PLATFORM_API_KEY, $post['platform_api_key'] ?? '' );
        update_option( self::OPT_PLATFORM_SYNC_DONATIONS, isset( $post['platform_sync_donations'] ) ? '1' : '0', false );
        update_option( self::OPT_PLATFORM_SYNC_CAMPAIGNS, isset( $post['platform_sync_campaigns'] ) ? '1' : '0', false );

        // Tout changement d'URL, de clé ou d'organisation invalide
        // l'enregistrement local : l'administrateur doit ré-enregistrer le site.
        $new_platform_url = (string) get_option( self::OPT_PLATFORM_BASE_URL, '' );
        $new_platform_key = (string) get_option( self::OPT_PLATFORM_API_KEY, '' );
        $new_platform_org = (string) get_option( self::OPT_PLATFORM_ORGANIZATION_ID, '' );
        if ( $old_platform_url !== $new_platform_url
            || $old_platform_key !== $new_platform_key
            || $old_platform_org !== $new_platform_org
        ) {
            self::reset_platform_registration();
        }

        // Default gateway
        $default_gw = in_array( $post['default_gateway'] ?? '', [ 'stripe', 'helloasso' ], true )
            ? $post['default_gateway']
            : 'stripe';
        update_option( self::OPT_DEFAULT_GATEWAY, $default_gw, false );

        // Email
        update_option( self::OPT_EMAIL_LOGO_URL,      esc_url_raw( $post['email_logo_url']           ?? '' ), false );
        update_option( self::OPT_EMAIL_SENDER_NAME,   sanitize_text_field( $post['email_sender_name'] ?? '' ), false );
        update_option( self::OPT_EMAIL_THANK_SUBJECT, sanitize_text_field( $post['email_thank_subject'] ?? '' ), false );
        update_option( self::OPT_EMAIL_THANK_BODY,    sanitize_textarea_field( $post['email_thank_body'] ?? '' ), false );
        update_option( self::OPT_EMAIL_ADMIN_DONATION_SUBJECT, sanitize_text_field( $post['email_admin_donation_subject'] ?? '' ), false );
        update_option( self::OPT_EMAIL_ADMIN_DONATION_BODY,    sanitize_textarea_field( $post['email_admin_donation_body'] ?? '' ), false );
        update_option( self::OPT_EMAIL_TAX_RECEIPT_SUBJECT, sanitize_text_field( $post['email_tax_receipt_subject'] ?? '' ), false );
        update_option( self::OPT_EMAIL_TAX_RECEIPT_BODY,    sanitize_textarea_field( $post['email_tax_receipt_body'] ?? '' ), false );
        update_option( self::OPT_TAX_RECEIPT_PDF_ENABLED, isset( $post['tax_receipt_pdf_enabled'] ) ? '1' : '0', false );
        update_option( self::OPT_TAX_RECEIPT_PDF_TITLE,  sanitize_text_field( $post['tax_receipt_pdf_title'] ?? '' ), false );
        update_option( self::OPT_TAX_RECEIPT_PDF_BODY,   sanitize_textarea_field( $post['tax_receipt_pdf_body'] ?? '' ), false );
        update_option( self::OPT_TAX_RECEIPT_PDF_FOOTER, sanitize_textarea_field( $post['tax_receipt_pdf_footer'] ?? '' ), false );
        // Couleur : valider le format hex avant de sauvegarder (#rgb ou #rrggbb uniquement).
        $color = sanitize_text_field( $post['email_primary_color'] ?? '' );
        if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
            update_option( self::OPT_EMAIL_PRIMARY_COLOR, $color, false );
        }

        // Apparence
        // Primary color : si enabled=0, effacer la valeur custom
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
     * Suppression explicite et protégée des secrets configurés.
     *
     * Une case `clear_<option>` cochée (protégée par le nonce des réglages)
     * supprime définitivement la valeur, pour permettre rotation et révocation
     * depuis l'interface sans passer par la base de données.
     */
    private static function maybe_clear_secrets( array $post ): void {
        $clearable = [
            self::OPT_STRIPE_SK_TEST    => 'clear_stripe_sk_test',
            self::OPT_STRIPE_SK_LIVE    => 'clear_stripe_sk_live',
            self::OPT_WEBHOOK_SECRET    => 'clear_stripe_webhook_secret',
            self::OPT_HA_CLIENT_ID      => 'clear_ha_client_id',
            self::OPT_HA_CLIENT_SECRET  => 'clear_ha_client_secret',
            self::OPT_HA_SIGNATURE_KEY  => 'clear_ha_signature_key',
            self::OPT_PLATFORM_API_KEY  => 'clear_platform_api_key',
        ];

        foreach ( $clearable as $option => $field ) {
            if ( isset( $post[ $field ] ) && (string) $post[ $field ] === '1' ) {
                delete_option( $option );
            }
        }

        // Toute suppression de la clé Plateforme invalide l'enregistrement local.
        if ( isset( $post['clear_platform_api_key'] ) ) {
            self::reset_platform_registration();
        }

        // Toute suppression d'identifiant HelloAsso invalide les jetons en cache.
        if ( isset( $post['clear_ha_client_id'], $post['clear_ha_client_secret'] )
            || isset( $post['clear_ha_client_id'] )
            || isset( $post['clear_ha_client_secret'] ) ) {
            self::purge_helloasso_tokens();
        }
    }

    /**
     * Purge les jetons OAuth HelloAsso en cache.
     *
     * À appeler quand le mode, le client ou l'organisation change, ou quand un
     * secret est supprimé : évite de réutiliser un jeton sandbox en live.
     */
    public static function purge_helloasso_tokens(): void {
        delete_option( \Givoly\Gateway\HelloAssoGateway::OPT_ACCESS_TOKEN );
        delete_option( \Givoly\Gateway\HelloAssoGateway::OPT_REFRESH_TOKEN );
        delete_option( \Givoly\Gateway\HelloAssoGateway::OPT_EXPIRES_AT );
    }

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
