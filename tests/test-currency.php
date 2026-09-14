<?php
// Tests multidevise : whitelist, passerelles, symboles, agrégats séparés.

require_once __DIR__ . '/stubs.php';
require_once dirname( __DIR__ ) . '/includes/Admin/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Form/FormConfig.php';
require_once dirname( __DIR__ ) . '/includes/Core/Format.php';

use Givoly\Form\FormConfig;
use Givoly\Core\Format;

echo "Currency rules\n";

// Stripe : 5 devises.
foreach ( [ 'EUR', 'USD', 'GBP', 'CHF', 'MAD' ] as $code ) {
    givoly_assert( FormConfig::is_supported_currency( $code ), "devise supportee {$code}" );
    givoly_assert( FormConfig::is_supported_for_gateway( $code, 'stripe' ), "Stripe accepte {$code}" );
}
givoly_assert( ! FormConfig::is_supported_currency( 'JPY' ), 'JPY refusee' );
givoly_assert( ! FormConfig::is_supported_currency( '' ), 'devise vide refusee' );

// HelloAsso : EUR uniquement, interface comme serveur.
givoly_assert( FormConfig::is_supported_for_gateway( 'EUR', 'helloasso' ), 'HelloAsso accepte EUR' );
foreach ( [ 'USD', 'GBP', 'CHF', 'MAD', 'JPY' ] as $code ) {
    givoly_assert( ! FormConfig::is_supported_for_gateway( $code, 'helloasso' ), "HelloAsso refuse {$code}" );
}

// Symboles corrects partout (source unique).
givoly_assert_same( '€', FormConfig::currency_symbol( 'EUR' ), 'symbole EUR' );
givoly_assert_same( '$', FormConfig::currency_symbol( 'USD' ), 'symbole USD' );
givoly_assert_same( '£', FormConfig::currency_symbol( 'GBP' ), 'symbole GBP' );
givoly_assert_same( 'DH', FormConfig::currency_symbol( 'MAD' ), 'symbole MAD' );
givoly_assert_same( 'CHF', FormConfig::currency_symbol( 'CHF' ), 'symbole CHF' );
givoly_assert_same( 'JPY', FormConfig::currency_symbol( 'JPY' ), 'repli code ISO inconnu' );

// Agrégats séparés par devise : jamais de somme inter-devises.
$by_currency = [ 'EUR' => [ 'amount' => 100.0, 'count' => 2 ], 'USD' => [ 'amount' => 50.0, 'count' => 1 ] ];
$formatted   = Format::amounts_by_currency( $by_currency );
givoly_assert( str_contains( $formatted, '€' ) && str_contains( $formatted, '$' ), 'total global multidevise separe par devise' );
givoly_assert( ! str_contains( $formatted, '150' ), 'aucune somme inter-devises affichee' );

// Une seule devise reste lisible.
givoly_assert( str_contains( Format::amounts_by_currency( [ 'EUR' => [ 'amount' => 42.5 ] ] ), '€' ), 'total mono-devise' );

// Regroupement pur (miroir de DonationStats::totals_by_currency).
$donations = [
    [ 'currency' => 'EUR', 'amount' => 10.0 ],
    [ 'currency' => 'EUR', 'amount' => 20.0 ],
    [ 'currency' => 'USD', 'amount' => 5.0 ],
    [ 'currency' => 'GBP', 'amount' => 7.5 ],
];
$grouped = [];
foreach ( $donations as $donation ) {
    $grouped[ $donation['currency'] ] = ( $grouped[ $donation['currency'] ] ?? 0.0 ) + $donation['amount'];
}
givoly_assert_same( 30.0, $grouped['EUR'], 'agregat EUR separe' );
givoly_assert_same( 5.0, $grouped['USD'], 'agregat USD separe' );
givoly_assert_same( 7.5, $grouped['GBP'], 'agregat GBP separe' );
