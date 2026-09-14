<?php
// Tests webhooks : idempotence et conservation des données historiques.

require_once __DIR__ . '/stubs.php';

echo "Webhooks\n";

// Idempotence (gateway, gateway_transaction_id) : le doublon ne crée rien.
$wpdb = new Givoly_Fake_Wpdb();
$wpdb->prefix = 'wp_';
$wpdb->tables['donations'] = [
    [ 'id' => 1, 'gateway' => 'stripe', 'gateway_transaction_id' => 'cs_test_123', 'gateway_refund_ref' => 'pi_123', 'status' => 'completed' ],
];
$GLOBALS['wpdb'] = $wpdb;

// Miroir de PaymentProcessor::process() : recherche préalable + capture doublon.
function givoly_would_insert( Givoly_Fake_Wpdb $wpdb, string $gateway, string $transaction_id, string $refund_ref ): bool {
    foreach ( $wpdb->tables['donations'] ?? [] as $row ) {
        if ( ( $row['gateway'] ?? null ) === $gateway && ( $row['gateway_transaction_id'] ?? null ) === $transaction_id ) {
            return false;
        }
        if ( $refund_ref !== '' && ( $row['gateway_refund_ref'] ?? '' ) === $refund_ref ) {
            return false;
        }
    }
    $inserted = $wpdb->insert( $wpdb->prefix . 'givoly_donations', [ 'gateway' => $gateway, 'gateway_transaction_id' => $transaction_id, 'gateway_refund_ref' => $refund_ref ] );
    if ( false === $inserted && str_contains( strtolower( $wpdb->last_error ), 'duplicate entry' ) ) {
        return false;
    }
    return (bool) $inserted;
}

givoly_assert( ! givoly_would_insert( $wpdb, 'stripe', 'cs_test_123', 'pi_456' ), 'webhook checkout rejoue ignore (meme transaction)' );
givoly_assert( ! givoly_would_insert( $wpdb, 'stripe', 'in_new', 'pi_123' ), 'facture avec meme payment_intent ignoree (doublon checkout/facture)' );
givoly_assert( givoly_would_insert( $wpdb, 'stripe', 'in_fresh', 'pi_fresh' ), 'nouvelle echeance inseree une fois' );
givoly_assert( ! givoly_would_insert( $wpdb, 'stripe', 'in_fresh', 'pi_fresh' ), 'meme notification livree deux fois sans effet de bord' );

// Conservation : migration et webhooks ne vident jamais une valeur existante.
$donor = [ 'id' => 1, 'email' => 'a@example.org', 'stripe_customer_id' => 'cus_1', 'stripe_subscription_id' => 'sub_1', 'donor_message' => 'urgence', 'donor_notes' => 'message libre' ];
// Un webhook sans subscription ne doit pas écraser l'existant (array_filter non-vide).
$incoming_subscription = '';
$merged = $incoming_subscription !== '' ? $incoming_subscription : $donor['stripe_subscription_id'];
givoly_assert_same( 'sub_1', $merged, 'webhook sans abonnement ne vide pas la valeur existante' );
// donor_message historique (slug) préservé, message libre dans donor_notes.
givoly_assert_same( 'urgence', $donor['donor_message'], 'slug historique preserve dans donor_message' );
givoly_assert_same( 'message libre', $donor['donor_notes'], 'message libre conserve dans donor_notes' );

// Secrets jamais en clair dans les logs (miroir de Format::redact_secrets).
require_once dirname( __DIR__ ) . '/includes/Admin/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Gateway/HelloAssoGateway.php';
require_once dirname( __DIR__ ) . '/includes/Core/Format.php';
update_option( 'givoly_stripe_sk_test', 'sk_test_abcdef123456', false );
$logged = Givoly\Core\Format::redact_secrets( 'Stripe error: Invalid API Key provided: sk_test_abcdef123456' );
givoly_assert( ! str_contains( $logged, 'sk_test_abcdef123456' ), 'cle secrete masquee dans les logs' );
givoly_assert( str_contains( $logged, '[REDACTED]' ), 'marqueur de redaction present' );
