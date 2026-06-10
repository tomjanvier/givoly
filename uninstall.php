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
] as $givoly_table ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wpdb->query( "DROP TABLE IF EXISTS `{$givoly_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix
}

// Supprimer les options — liste à tenir à jour avec Settings::OPT_*
foreach ( [
    'givoly_db_version',
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
    'givoly_default_gateway',
    'givoly_email_logo_url',
    'givoly_email_primary_color',
    'givoly_email_sender_name',
    'givoly_email_thank_subject',
    'givoly_email_thank_body',
    'givoly_appearance_primary_color',
    'givoly_appearance_accent_color',
    'givoly_appearance_radius',
    'givoly_appearance_btn_style',
    'givoly_post_payment_show_phone',
    'givoly_post_payment_show_address',
] as $givoly_option ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    delete_option( $givoly_option );
}
