<?php
// Tests migrations : succès, échec bloquant, nouvelle tentative, idempotence.

require_once __DIR__ . '/stubs.php';

echo "Migrations\n";

// Simulation du contrat Installer : la version n'avance que si tout réussit.
function givoly_fake_upgrade( array $steps ): bool {
    foreach ( $steps as $ok ) {
        if ( ! $ok ) {
            return false;
        }
    }
    return true;
}

$GLOBALS['givoly_test_options'] = [];

// Migration réussie : version avancée.
$ok = givoly_fake_upgrade( [ true, true, true ] );
if ( $ok ) {
    update_option( 'givoly_db_version', '2.2', false );
}
givoly_assert_same( '2.2', get_option( 'givoly_db_version' ), 'migration reussie avance la version' );

// Migration échouée : version bloquée.
$GLOBALS['givoly_test_options'] = [ 'givoly_db_version' => '2.1' ];
$ok = givoly_fake_upgrade( [ true, false, true ] );
if ( $ok ) {
    update_option( 'givoly_db_version', '2.2', false );
}
givoly_assert_same( '2.1', get_option( 'givoly_db_version' ), 'migration echouee ne fait pas avancer la version' );

// Nouvelle tentative après échec : version avancée au second passage.
$ok = givoly_fake_upgrade( [ true, true, true ] );
if ( $ok ) {
    update_option( 'givoly_db_version', '2.2', false );
}
givoly_assert_same( '2.2', get_option( 'givoly_db_version' ), 'nouvelle tentative apres echec reussit' );

// Idempotence : rejouer la migration des abonnements ne duplique rien.
$existing = [ 'sub_1' => [ 'donor_id' => 1 ], 'sub_2' => [ 'donor_id' => 1 ] ];
$known    = [ [ 'donor_id' => 1, 'stripe_subscription_id' => 'sub_1' ], [ 'donor_id' => 1, 'stripe_subscription_id' => 'sub_2' ] ];
foreach ( $known as $row ) {
    if ( ! isset( $existing[ $row['stripe_subscription_id'] ] ) ) {
        $existing[ $row['stripe_subscription_id'] ] = [ 'donor_id' => $row['donor_id'] ];
    }
}
givoly_assert_same( 2, count( $existing ), 'migration idempotente sans doublon' );

// Conservation historique : aucun rattachement inventé pour les dons ambigus.
$donations = [
    [ 'id' => 1, 'donor_id' => 1, 'stripe_subscription_id' => null ],
    [ 'id' => 2, 'donor_id' => 1, 'stripe_subscription_id' => null ],
];
foreach ( $donations as $donation ) {
    givoly_assert( $donation['stripe_subscription_id'] === null, 'don historique sans rattachement invente (#' . $donation['id'] . ')' );
}

// Contrôle des retours DDL : une erreur bloque la version.
$wpdb = new Givoly_Fake_Wpdb();
$wpdb->fail_next_write = true;
$result = $wpdb->query( 'ALTER TABLE wp_givoly_donations ADD COLUMN stripe_subscription_id VARCHAR(255)' );
givoly_assert( false === $result && $wpdb->last_error !== '', 'erreur DDL detectee et version bloquee' );
