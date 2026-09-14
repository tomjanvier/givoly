<?php
// Tests de la connexion Plateforme : URL, charge utile minimale, file idempotente.

require_once __DIR__ . '/stubs.php';
require_once dirname( __DIR__ ) . '/includes/Admin/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Gateway/HelloAssoGateway.php';
require_once dirname( __DIR__ ) . '/includes/Gateway/PlatformGateway.php';
require_once dirname( __DIR__ ) . '/includes/Core/Format.php';
require_once dirname( __DIR__ ) . '/includes/Integration/PlatformSync.php';

use Givoly\Admin\Settings;
use Givoly\Core\Format;
use Givoly\Gateway\PlatformGateway;
use Givoly\Integration\PlatformSync;

echo "Platform connection\n";

// Normalisation et validation de l'URL (https imposé sauf localhost).
givoly_assert_same( 'https://platform.example.org', PlatformGateway::normalize_base_url( 'platform.example.org' ), 'scheme https ajoute par defaut' );
givoly_assert_same( 'https://platform.example.org', PlatformGateway::normalize_base_url( 'https://platform.example.org/' ), 'slash final retire' );
givoly_assert_same( 'https://platform.example.org/base', PlatformGateway::normalize_base_url( 'https://platform.example.org/base/' ), 'chemin conserve sans slash final' );
givoly_assert_same( 'http://localhost:3000', PlatformGateway::normalize_base_url( 'http://localhost:3000' ), 'http local accepte pour le developpement' );
givoly_assert_same( '', PlatformGateway::normalize_base_url( 'http://platform.example.org' ), 'http distant refuse' );
givoly_assert_same( '', PlatformGateway::normalize_base_url( '' ), 'URL vide refusee' );
givoly_assert_same( '', PlatformGateway::normalize_base_url( 'not a url at all !!!' ), 'URL invalide refusee' );

// Client indisponible tant que la connexion n'est pas configurée.
$GLOBALS['givoly_test_options'] = [];
givoly_assert( null === Settings::get_platform_client(), 'pas de client sans configuration' );
givoly_assert( ! Settings::is_platform_configured(), 'connexion non configuree par defaut' );
givoly_assert( ! Settings::is_platform_enabled(), 'connexion desactivee par defaut (aucun appel distant)' );

// Payload don minimal : même périmètre que Stripe/HelloAsso, sans adresse ni téléphone.
$payload = PlatformSync::build_donation_payload( [
    'donation_id'    => 123,
    'gateway'        => 'stripe',
    'transaction_id' => 'cs_test_123',
    'email'          => ' donor@example.org ',
    'first_name'     => 'Ada',
    'last_name'      => 'Lovelace',
    'amount_cents'   => 2500,
    'currency'       => 'eur',
    'campaign'       => 'urgence',
    'occurred_at'    => '2026-01-15T12:00:00Z',
] );
givoly_assert_same( 'givoly-123', $payload['externalId'], 'identifiant externe stable par don' );
givoly_assert_same( 2500, $payload['amountCents'], 'montant en centimes conserve' );
givoly_assert_same( 'EUR', $payload['currency'], 'devise normalisee en majuscules' );
givoly_assert_same( 'donor@example.org', $payload['donor']['email'], 'email assaini' );
givoly_assert( ! isset( $payload['address'] ) && ! isset( $payload['phone'] ), 'minimisation : ni adresse ni telephone' );
givoly_assert_same( 'givoly-donation-123', PlatformSync::idempotency_key( 123 ), 'cle d idempotence unique par don' );

// Instantané campagne lecture seule.
$snapshot = PlatformSync::build_campaign_snapshot(
    [ 'slug' => 'Urgence Hiver', 'title' => 'Urgence hiver', 'goal_amount' => 1000.0, 'currency' => 'eur', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31' ],
    [ 'amount' => 250.0, 'donors' => 12 ]
);
givoly_assert_same( 'urgence-hiver', $snapshot['slug'], 'slug campagne assaini' );
givoly_assert_same( 100000, $snapshot['goalCents'], 'objectif en centimes' );
givoly_assert_same( 25000, $snapshot['collectedCents'], 'collecte en centimes' );
givoly_assert_same( 12, $snapshot['donors'], 'compteur donateurs conserve' );

// File idempotente : le même don confirmé deux fois ne crée qu'un job.
$GLOBALS['givoly_test_options'] = [
    'givoly_platform_enabled'         => '1',
    'givoly_platform_base_url'        => 'https://platform.example.org',
    'givoly_platform_api_key'         => 'test-key-abc123',
    'givoly_platform_sync_donations'  => '1',
];
$wpdb          = new Givoly_Fake_Wpdb();
$wpdb->prefix = 'wp_';
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_var_handler = static function ( $query ) use ( $wpdb ) {
    foreach ( $wpdb->tables['other'] ?? [] as $row ) {
        if ( (int) ( $row['donation_id'] ?? 0 ) === 123 ) {
            return $row['id'];
        }
    }
    return null;
};
// La fausse file utilise le compartiment générique : on l'initialise via inserts.
$sync = new PlatformSync();
$sync->enqueue_donation( [ 'donation_id' => 123 ] );
$first_count = count( $wpdb->inserts );
// Simuler la présence du job puis rejouer le même événement.
$wpdb->tables['other'][] = [ 'id' => 1, 'donation_id' => 123 ];
$sync->enqueue_donation( [ 'donation_id' => 123 ] );
givoly_assert_same( $first_count, count( $wpdb->inserts ), 'webhook rejoue : aucun doublon en file' );
// Connexion désactivée : aucun job créé.
$GLOBALS['givoly_test_options']['givoly_platform_enabled'] = '0';
$sync->enqueue_donation( [ 'donation_id' => 456 ] );
givoly_assert_same( $first_count, count( $wpdb->inserts ), 'connexion desactivee : aucun appel ni job' );

// Exécution sans configuration : no-op explicite.
$GLOBALS['givoly_test_options'] = [];
givoly_assert( false === ( new PlatformSync() )->run(), 'run sans configuration ne fait rien' );

// La clé API ne fuit jamais dans les logs.
$GLOBALS['givoly_test_options'] = [ 'givoly_platform_api_key' => 'secret-platform-key-xyz' ];
$logged = Format::redact_secrets( 'Platform push failed with key secret-platform-key-xyz at https://platform.example.org' );
givoly_assert( ! str_contains( $logged, 'secret-platform-key-xyz' ), 'cle plateforme masquee dans les logs' );
