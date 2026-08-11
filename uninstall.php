<?php
/**
 * Désinstallation propre de Givoly.
 *
 * Appelé par WordPress quand l'utilisateur clique "Supprimer" dans la liste des plugins.
 * ATTENTION : supprime définitivement toutes les données Givoly.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Supprimer les tables (ordre important : d'abord celles avec dépendances)
foreach ( [
    $wpdb->prefix . 'givoly_donations',
    $wpdb->prefix . 'givoly_campaigns',
    $wpdb->prefix . 'givoly_donors',
    $wpdb->prefix . 'givoly_email_jobs',
    // Legacy Givasso tables are owned by the same plugin and are removed
    // only when the administrator explicitly uninstalls it.
    $wpdb->prefix . 'givasso_donations',
    $wpdb->prefix . 'givasso_campaigns',
    $wpdb->prefix . 'givasso_donors',
    $wpdb->prefix . 'givasso_email_jobs',
] as $givoly_table ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wpdb->query( "DROP TABLE IF EXISTS `{$givoly_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix
}

// Supprimer les options — liste à tenir à jour avec Settings::OPT_*
foreach ( [
    'givoly_db_version',
    'givoly_legacy_migration_version',
    'givoly_stripe_mode',
    'givoly_stripe_pk_test',
    'givoly_stripe_sk_test',
    'givoly_stripe_pk_live',
    'givoly_stripe_sk_live',
    'givoly_stripe_webhook_secret',
    'givoly_success_url',
    'givoly_cancel_url',
    'givoly_assoc_name',
    'givoly_assoc_address',
    'givoly_assoc_postal_code',
    'givoly_assoc_city',
    'givoly_assoc_siret',
    'givoly_assoc_rna',
    'givoly_assoc_fiscal_id',
    'givoly_assoc_email',
    'givoly_ha_client_id',
    'givoly_ha_client_secret',
    'givoly_ha_org_slug',
    'givoly_ha_mode',
    'givoly_ha_signature_key',
    'givoly_ha_button_notice',
    'givoly_ha_other_payments_url',
    'givoly_ha_once_use_other_payments_url',
    'givoly_stripe_enabled',
    'givoly_helloasso_enabled',
    'givoly_default_gateway',
    'givoly_email_logo_url',
    'givoly_email_primary_color',
    'givoly_email_sender_name',
    'givoly_email_thank_subject',
    'givoly_email_thank_body',
    'givoly_email_admin_donation_subject',
    'givoly_email_admin_donation_body',
    'givoly_email_tax_receipt_subject',
    'givoly_email_tax_receipt_body',
    'givoly_tax_receipt_pdf_enabled',
    'givoly_tax_receipt_pdf_title',
    'givoly_tax_receipt_pdf_body',
    'givoly_tax_receipt_pdf_footer',
    'givoly_appearance_primary_color',
    'givoly_appearance_accent_color',
    'givoly_appearance_radius',
    'givoly_appearance_btn_style',
    // Legacy option from versions before the WordPress-native CSS editor.
    'givoly_appearance_custom_css',
    'givoly_post_payment_show_phone',
    'givoly_post_payment_show_address',
    'givoly_public_branding_enabled',
    // HelloAsso OAuth — laisser des tokens actifs après "suppression propre"
    // laisserait une session API vivante ~30 jours.
    'givoly_ha_access_token',
    'givoly_ha_refresh_token',
    'givoly_ha_expires_at',
    'givoly_helloasso_last_sync_at',
    'givoly_stripe_last_invoice_sync_at',
] as $givoly_option ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    delete_option( $givoly_option );

    if ( str_starts_with( $givoly_option, 'givoly_' ) ) {
        delete_option( 'givasso_' . substr( $givoly_option, strlen( 'givoly_' ) ) );
    }
}

// Supprimer les transients liés au checkout (profils donateur en attente) et
// au rate limiter (givoly_rl_*), ainsi que leurs lignes de timeout.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local variables used only by the uninstall routine.
foreach ( [ 'givoly_checkout_profile_%', 'givoly_donor_session_%', 'givoly_rl_%' ] as $givoly_prefix ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $transient_names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( '_transient_' ) . $givoly_prefix
        )
    );
    foreach ( $transient_names as $transient_name ) {
        $key = substr( $transient_name, strlen( '_transient_' ) );
        delete_transient( $key );
    }
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Retirer tout WP-Cron planifié pour la file d'emails Givoly.
wp_clear_scheduled_hook( 'givoly_process_mail_queue' );
wp_clear_scheduled_hook( 'givoly_sync_helloasso_payments' );
wp_clear_scheduled_hook( 'givoly_sync_stripe_paid_invoices' );
