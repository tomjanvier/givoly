<?php
/**
 * Désinstallation propre de Givasso.
 *
 * Appelé par WordPress quand l'utilisateur clique "Supprimer" dans la liste des plugins.
 * ATTENTION : supprime définitivement toutes les données Givasso.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Supprimer les tables (ordre important : d'abord celles avec dépendances)
foreach ( [
    $wpdb->prefix . 'givasso_receipts',
    $wpdb->prefix . 'givasso_donations',
    $wpdb->prefix . 'givasso_donors',
] as $givasso_table ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wpdb->query( "DROP TABLE IF EXISTS `{$givasso_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from $wpdb->prefix
}

// Supprimer les options — liste à tenir à jour avec Settings::OPT_*
foreach ( [
    'givasso_db_version',
    'givasso_stripe_mode',
    'givasso_stripe_pk_test',
    'givasso_stripe_sk_test',
    'givasso_stripe_pk_live',
    'givasso_stripe_sk_live',
    'givasso_stripe_webhook_secret',
    'givasso_success_url',
    'givasso_cancel_url',
    'givasso_assoc_name',
    'givasso_assoc_address',
    'givasso_assoc_postal_code',
    'givasso_assoc_city',
    'givasso_assoc_siret',
    'givasso_assoc_rna',
    'givasso_assoc_fiscal_id',
    'givasso_assoc_email',
] as $givasso_option ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    delete_option( $givasso_option );
}
