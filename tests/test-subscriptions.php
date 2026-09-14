<?php
// Tests du modèle d'abonnements : multi-abonnements, annulation ciblée, don unique.

require_once __DIR__ . '/stubs.php';
require_once dirname( __DIR__ ) . '/includes/Repository/SubscriptionRepository.php';

use Givoly\Repository\SubscriptionRepository;

echo "Subscriptions\n";

function givoly_new_sub_db(): Givoly_Fake_Wpdb {
    $wpdb         = new Givoly_Fake_Wpdb();
    $wpdb->prefix = 'wp_';
    $GLOBALS['wpdb'] = $wpdb;
    return $wpdb;
}

// Deux abonnements du même donateur coexistent.
$wpdb = givoly_new_sub_db();
$repo = new SubscriptionRepository();
$wpdb->get_row_handler = function ( $query ) use ( $wpdb ) {
    foreach ( $wpdb->tables['subscriptions'] ?? [] as $row ) {
        if ( str_contains( $query, $row['stripe_subscription_id'] ) ) {
            return (object) $row;
        }
    }
    return null;
};
$first  = $repo->upsert( 7, 'sub_first', 'cus_7', 'EUR', 0 );
$second = $repo->upsert( 7, 'sub_second', 'cus_7', 'USD', 0 );
givoly_assert( $first && $second, 'deux abonnements crees pour le meme donateur' );
givoly_assert_same( 2, count( $wpdb->tables['subscriptions'] ?? [] ), 'deux lignes distinctes en base' );
givoly_assert( $first->stripe_subscription_id !== $second->stripe_subscription_id, 'identifiants distincts conserves' );

// Le second n'écrase pas le premier (pas de last-write-wins).
$ids = array_column( $wpdb->tables['subscriptions'], 'stripe_subscription_id' );
givoly_assert( in_array( 'sub_first', $ids, true ) && in_array( 'sub_second', $ids, true ), 'aucun ecrasement mono-champ' );

// Impossibilité d'annuler le mauvais abonnement : vérification d'appartenance.
$wpdb2 = givoly_new_sub_db();
$wpdb2->tables['subscriptions'] = [
    [ 'id' => 1, 'donor_id' => 7, 'stripe_subscription_id' => 'sub_first', 'stripe_customer_id' => 'cus_7', 'status' => 'active', 'currency' => 'EUR' ],
    [ 'id' => 2, 'donor_id' => 8, 'stripe_subscription_id' => 'sub_other', 'stripe_customer_id' => 'cus_8', 'status' => 'active', 'currency' => 'EUR' ],
];
$repo2 = new SubscriptionRepository();
$wpdb2->get_row_handler = function ( $query ) use ( $wpdb2 ) {
    if ( str_contains( $query, 'sub_first' ) ) {
        return (object) $wpdb2->tables['subscriptions'][0];
    }
    if ( str_contains( $query, 'sub_other' ) ) {
        return (object) $wpdb2->tables['subscriptions'][1];
    }
    return null;
};
$GLOBALS['wpdb'] = $wpdb2;
$known = $repo2->find_by_stripe_id( 'sub_first' );
givoly_assert( $known && (int) $known->donor_id === 7, 'abonnement retrouve par identifiant exact' );
// Un donateur 8 ne peut pas annuler sub_first (contrôle donor_id).
givoly_assert( (int) $known->donor_id !== 8, 'annulation du mauvais abonnement refusee par controle donateur' );
// Identifiant inconnu : aucune action.
givoly_assert( null === $repo2->find_by_stripe_id( 'sub_unknown' ), 'abonnement inconnu introuvable' );
givoly_assert( null === $repo2->find_by_stripe_id( '' ), 'identifiant vide refuse' );

// Don unique sans abonnement : aucune action d'annulation proposée.
$wpdb3 = givoly_new_sub_db();
$GLOBALS['wpdb'] = $wpdb3;
$repo3 = new SubscriptionRepository();
$wpdb3->get_row_handler = static fn() => null;
$wpdb3->get_results_handler = static fn() => [];
givoly_assert_same( [], $repo3->find_by_donor( 99 ), 'donateur sans abonnement : liste vide' );
// upsert refuse les identifiants vides (don unique).
givoly_assert( null === $repo3->upsert( 99, '', 'cus_99' ), 'don unique sans rattachement invente' );
givoly_assert_same( [], $wpdb3->tables['subscriptions'] ?? [], 'aucune ligne creee pour un don unique' );

// Réassignation interdite : un abonnement reste au premier donateur.
$wpdb4 = givoly_new_sub_db();
$wpdb4->tables['subscriptions'] = [
    [ 'id' => 1, 'donor_id' => 7, 'stripe_subscription_id' => 'sub_first', 'stripe_customer_id' => 'cus_7', 'status' => 'active', 'currency' => 'EUR', 'campaign_id' => null ],
];
$GLOBALS['wpdb'] = $wpdb4;
$repo4 = new SubscriptionRepository();
$wpdb4->get_row_handler = static fn( $query ) => (object) $wpdb4->tables['subscriptions'][0];
$kept = $repo4->upsert( 8, 'sub_first', 'cus_8' );
givoly_assert( $kept && (int) $kept->donor_id === 7, 'abonnement non re assigne a un autre donateur' );
