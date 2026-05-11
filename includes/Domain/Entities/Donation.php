<?php
/**
 * Entité Don.
 *
 * Classe pure : aucune dépendance WordPress ou base de données.
 *
 * @package Givasso\Domain\Entities
 */

namespace Givasso\Domain\Entities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Donation {

    const STATUS_PENDING   = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED    = 'failed';
    const STATUS_REFUNDED  = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly int                $id,
        private readonly int                $donor_id,
        private readonly float              $amount,
        private readonly string             $currency,
        private readonly string             $status,
        private readonly string             $gateway,
        private readonly \DateTimeImmutable $created_at,
        private readonly ?int               $campaign_id            = null,
        private readonly ?string            $gateway_transaction_id = null,
        private readonly bool               $is_recurring           = false,
        private readonly ?int               $receipt_id             = null,
    ) {}

    public function get_id(): int                         { return $this->id; }
    public function get_donor_id(): int                   { return $this->donor_id; }
    public function get_amount(): float                   { return $this->amount; }
    public function get_currency(): string                { return $this->currency; }
    public function get_status(): string                  { return $this->status; }
    public function get_gateway(): string                 { return $this->gateway; }
    public function get_campaign_id(): ?int               { return $this->campaign_id; }
    public function get_gateway_transaction_id(): ?string { return $this->gateway_transaction_id; }
    public function get_receipt_id(): ?int                { return $this->receipt_id; }
    public function get_created_at(): \DateTimeImmutable  { return $this->created_at; }

    public function is_completed(): bool {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function is_recurring(): bool {
        return $this->is_recurring;
    }

    public function has_receipt(): bool {
        return $this->receipt_id !== null;
    }

    /**
     * Un don est éligible au CERFA s'il est complété et sans reçu existant.
     */
    public function is_eligible_for_receipt(): bool {
        return $this->is_completed() && ! $this->has_receipt();
    }

    /**
     * Montant formaté pour affichage : "25,00 €"
     */
    public function get_formatted_amount(): string {
        $symbols = [ 'EUR' => '€', 'USD' => '$', 'GBP' => '£', 'MAD' => 'DH' ];
        $symbol  = $symbols[ $this->currency ] ?? $this->currency;
        return number_format( $this->amount, 2, ',', ' ' ) . ' ' . $symbol;
    }
}
