<?php
/**
 * Runner des tests de régression Givoly.
 *
 * Léger et sans dépendance : `php tests/run.php`.
 * Ne prétend pas remplacer un test fonctionnel WordPress/MySQL réel.
 */

$files = [
    __DIR__ . '/test-campaign-entity.php',
    __DIR__ . '/test-currency.php',
    __DIR__ . '/test-subscriptions.php',
    __DIR__ . '/test-campaign-render.php',
    __DIR__ . '/test-migrations.php',
    __DIR__ . '/test-webhooks.php',
    __DIR__ . '/test-platform.php',
];

foreach ( $files as $file ) {
    require $file;
}

$total  = $GLOBALS['givoly_test_total'] ?? 0;
$failed = $GLOBALS['givoly_test_failed'] ?? 0;
$passed = $total - $failed;

echo "\n{$passed}/{$total} assertions passed";
if ( $failed > 0 ) {
    echo " ({$failed} failed)\n";
    exit( 1 );
}

echo "\n";
