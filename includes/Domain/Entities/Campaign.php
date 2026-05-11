<?php
/**
 * Entité Campagne.
 *
 * Classe pure : aucune dépendance WordPress ou base de données.
 *
 * @package Givasso\Domain\Entities
 */

namespace Givasso\Domain\Entities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Campaign {

    const STATUS_DRAFT    = 'draft';
    const STATUS_ACTIVE   = 'active';
    const STATUS_ENDED    = 'ended';
    const STATUS_ARCHIVED = 'archived';

    public function __construct(
        private readonly int                 $id,
        private readonly string              $title,
        private readonly string              $slug,
        private readonly string              $status,
        private readonly string              $currency      = 'EUR',
        private readonly ?string             $description   = null,
        private readonly ?float              $goal_amount   = null,
        private readonly ?\DateTimeImmutable $start_date    = null,
        private readonly ?\DateTimeImmutable $end_date      = null,
        private readonly ?int                $featured_image = null,
    ) {}

    public function get_id(): int                          { return $this->id; }
    public function get_title(): string                    { return $this->title; }
    public function get_slug(): string                     { return $this->slug; }
    public function get_status(): string                   { return $this->status; }
    public function get_currency(): string                 { return $this->currency; }
    public function get_description(): ?string             { return $this->description; }
    public function get_goal_amount(): ?float              { return $this->goal_amount; }
    public function get_start_date(): ?\DateTimeImmutable  { return $this->start_date; }
    public function get_end_date(): ?\DateTimeImmutable    { return $this->end_date; }
    public function get_featured_image(): ?int             { return $this->featured_image; }

    /**
     * Une campagne est active si son statut est 'active'
     * ET qu'elle n'est pas encore arrivée à échéance.
     *
     * @param \DateTimeImmutable|null $now Injecté pour la testabilité (défaut : maintenant).
     */
    public function is_active( ?\DateTimeImmutable $now = null ): bool {
        if ( $this->status !== self::STATUS_ACTIVE ) {
            return false;
        }

        $now ??= new \DateTimeImmutable();

        if ( $this->end_date !== null && $this->end_date < $now ) {
            return false;
        }

        return true;
    }

    /**
     * Une campagne est terminée si :
     *  - son statut est 'ended' ou 'archived', OU
     *  - sa date de fin est passée (même si le statut est encore 'active')
     *
     * @param \DateTimeImmutable|null $now Injecté pour la testabilité.
     */
    public function is_ended( ?\DateTimeImmutable $now = null ): bool {
        if ( $this->status === self::STATUS_ENDED
            || $this->status === self::STATUS_ARCHIVED ) {
            return true;
        }

        $now ??= new \DateTimeImmutable();

        return $this->end_date !== null && $this->end_date < $now;
    }

    public function has_goal(): bool {
        return $this->goal_amount !== null && $this->goal_amount > 0;
    }

    /**
     * Pourcentage de progression vers l'objectif, cappé à 100.
     * Retourne 0.0 si pas d'objectif défini.
     */
    public function get_progress_percentage( float $collected ): float {
        if ( ! $this->has_goal() ) {
            return 0.0;
        }

        $pct = ( $collected / $this->goal_amount ) * 100;

        return min( 100.0, max( 0.0, round( $pct, 1 ) ) );
    }
}
