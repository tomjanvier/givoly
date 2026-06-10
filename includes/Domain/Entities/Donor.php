<?php
/**
 * Entité Donateur.
 *
 * Classe pure : aucune dépendance WordPress ou base de données.
 * Si tu peux lire cette classe sans connaître WordPress, elle est bien écrite.
 *
 * @package Givoly\Domain\Entities
 */

namespace Givoly\Domain\Entities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Donor {

    public function __construct(
        private readonly int     $id,
        private readonly string  $email,
        private readonly string  $first_name,
        private readonly string  $last_name,
        private readonly string  $country       = 'FR',
        private readonly ?string $company       = null,
        private readonly ?string $address_line1 = null,
        private readonly ?string $postal_code   = null,
        private readonly ?string $city          = null,
        private readonly ?int    $wp_user_id    = null,
    ) {}

    public function get_id(): int              { return $this->id; }
    public function get_email(): string        { return $this->email; }
    public function get_first_name(): string   { return $this->first_name; }
    public function get_last_name(): string    { return $this->last_name; }
    public function get_company(): ?string     { return $this->company; }
    public function get_address_line1(): ?string { return $this->address_line1; }
    public function get_postal_code(): ?string { return $this->postal_code; }
    public function get_city(): ?string        { return $this->city; }
    public function get_country(): string      { return $this->country; }
    public function get_wp_user_id(): ?int     { return $this->wp_user_id; }

    /**
     * Nom affiché : société si personne morale, sinon prénom + nom.
     */
    public function get_display_name(): string {
        if ( ! empty( $this->company ) ) {
            return $this->company;
        }
        return trim( $this->first_name . ' ' . $this->last_name );
    }

    public function is_organization(): bool {
        return ! empty( $this->company );
    }
}
