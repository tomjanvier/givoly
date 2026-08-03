<?php
/**
 * Helpers de formatage partagés entre les pages d'administration.
 *
 * @package Givoly\Core
 */

namespace Givoly\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Format {

    /**
     * Libellé lisible d'un statut de don.
     */
    public static function status( string $status ): string {
        $labels = [
            'completed' => __( 'Complété', 'givoly' ),
            'pending'   => __( 'En attente', 'givoly' ),
            'failed'    => __( 'Échoué', 'givoly' ),
            'refunded'  => __( 'Remboursé', 'givoly' ),
            'cancelled' => __( 'Annulé', 'givoly' ),
        ];

        return $labels[ $status ] ?? $status;
    }
}
