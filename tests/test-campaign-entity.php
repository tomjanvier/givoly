<?php
// Tests de l'entité Campaign : périodes, statuts, progression.

require_once __DIR__ . '/stubs.php';
require_once dirname( __DIR__ ) . '/includes/Domain/Entities/Campaign.php';

use Givoly\Domain\Entities\Campaign;

echo "Campaign entity\n";

$active = new Campaign( 1, 'Urgence', 'urgence', Campaign::STATUS_ACTIVE, 'EUR', null, 1000.0, null, null, null );
givoly_assert( $active->is_active( new DateTimeImmutable( '2026-01-15 12:00:00' ) ), 'campagne active sans dates accepte les dons' );
givoly_assert( $active->can_accept_donations( new DateTimeImmutable( '2026-01-15 12:00:00' ) ), 'can_accept_donations vrai pour campagne active' );
givoly_assert( ! $active->is_ended( new DateTimeImmutable( '2026-01-15 12:00:00' ) ), 'campagne active sans fin non terminee' );

// Fin de journée inclusive : end_date=today reste active toute la journée.
$today_end = new Campaign( 2, 'Fin', 'fin', Campaign::STATUS_ACTIVE, 'EUR', null, 100.0, null, new DateTimeImmutable( '2026-03-10' ), null );
givoly_assert( $today_end->is_active( new DateTimeImmutable( '2026-03-10 23:00:00' ) ), 'end_date inclusive jusquau soir' );
givoly_assert( ! $today_end->is_ended( new DateTimeImmutable( '2026-03-10 23:00:00' ) ), 'pas terminee le jour meme avant minuit' );
givoly_assert( $today_end->is_ended( new DateTimeImmutable( '2026-03-11 00:00:01' ) ), 'terminee le lendemain' );
givoly_assert( ! $today_end->can_accept_donations( new DateTimeImmutable( '2026-03-11 00:00:01' ) ), 'n accepte plus apres la fin' );

// Hors période de début : future non active mais non terminée.
$future = new Campaign( 3, 'Future', 'future', Campaign::STATUS_ACTIVE, 'EUR', null, null, new DateTimeImmutable( '2026-06-01' ), new DateTimeImmutable( '2026-06-30' ), null );
givoly_assert( ! $future->is_active( new DateTimeImmutable( '2026-05-15 12:00:00' ) ), 'campagne future hors periode' );
givoly_assert( ! $future->is_ended( new DateTimeImmutable( '2026-05-15 12:00:00' ) ), 'campagne future non terminee' );
givoly_assert( ! $future->can_accept_donations( new DateTimeImmutable( '2026-05-15 12:00:00' ) ), 'campagne future refuse les dons' );
givoly_assert( $future->is_active( new DateTimeImmutable( '2026-06-15 12:00:00' ) ), 'campagne ouverte pendant la periode' );

// Statuts fermés.
foreach ( [ Campaign::STATUS_ENDED, Campaign::STATUS_ARCHIVED ] as $status ) {
    $closed = new Campaign( 4, 'Fermee', 'fermee', $status, 'USD', null, 500.0, null, null, null );
    givoly_assert( $closed->is_ended(), "statut {$status} termine" );
    givoly_assert( ! $closed->can_accept_donations(), "statut {$status} refuse les dons" );
}

// Brouillon jamais actif.
$draft = new Campaign( 5, 'Brouillon', 'brouillon', Campaign::STATUS_DRAFT, 'EUR' );
givoly_assert( ! $draft->is_active(), 'brouillon inactif' );
givoly_assert( ! $draft->can_accept_donations(), 'brouillon refuse les dons' );

// Progression cappée.
givoly_assert_same( 50.0, $active->get_progress_percentage( 500.0 ), 'progression 50%' );
givoly_assert_same( 100.0, $active->get_progress_percentage( 5000.0 ), 'progression cappee a 100' );
givoly_assert_same( 0.0, ( new Campaign( 6, 'Sans objectif', 'sans', Campaign::STATUS_ACTIVE, 'EUR' ) )->get_progress_percentage( 500.0 ), 'sans objectif = 0' );
