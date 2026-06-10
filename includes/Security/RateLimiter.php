<?php
/**
 * Limiteur de débit pour les actions publiques (checkout).
 *
 * Stratégie : fenêtre fixe par IP (Fixed Window Counter).
 * Backend   : object cache persistant (Redis / Memcached) si disponible — atomique,
 *             sinon transients WordPress — universel, léger risque de race condition
 *             intentionnellement accepté pour du rate limiting.
 * Fail-open : en cas d'erreur de cache la requête est autorisée — on ne bloque
 *             jamais un donateur légitime à cause d'un problème d'infra.
 *
 * @package Givoly\Security
 */

namespace Givoly\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RateLimiter {

    /** Nombre maximum de tentatives autorisées par fenêtre. */
    private const MAX_ATTEMPTS   = 5;

    /** Durée de la fenêtre en secondes. */
    private const WINDOW_SECONDS = 60;

    /** Groupe de cache (utilisé par l'object cache, ignoré par les transients). */
    private const CACHE_GROUP    = 'givoly_rl';

    /**
     * Vérifie si l'IP cliente peut effectuer l'action demandée.
     *
     * Incrémente le compteur de la fenêtre courante et retourne false
     * si le quota est dépassé.
     *
     * @param string $action Identifiant de l'action (ex: 'checkout').
     * @return bool true = autorisé, false = quota dépassé (HTTP 429).
     */
    public static function is_allowed( string $action ): bool {
        $ip = self::get_client_ip();

        if ( ! $ip ) {
            return true; // IP indéterminée → fail-open
        }

        $window = (int) floor( time() / self::WINDOW_SECONDS );
        $key    = 'givoly_rl_' . substr( hash( 'sha256', $action . $ip ), 0, 16 ) . '_' . $window;

        $count = wp_using_ext_object_cache()
            ? self::increment_object_cache( $key )
            : self::increment_transient( $key );

        if ( $count === null ) {
            return true; // Cache indisponible → fail-open
        }

        return $count <= self::MAX_ATTEMPTS;
    }

    // ── Backends ───────────────────────────────────────────────────────────

    /**
     * Incrément atomique via object cache (Redis / Memcached).
     *
     * wp_cache_add() est no-op si la clé existe déjà.
     * wp_cache_incr() est atomique dans tous les backends qui le supportent.
     *
     * @return int|null Compteur courant, null en cas d'échec.
     */
    private static function increment_object_cache( string $key ): ?int {
        wp_cache_add( $key, 0, self::CACHE_GROUP, self::WINDOW_SECONDS + 5 );
        $count = wp_cache_incr( $key, 1, self::CACHE_GROUP );

        return ( $count === false ) ? null : (int) $count;
    }

    /**
     * Incrément via transients WordPress.
     *
     * Stocké en DB si aucun object cache persistant n'est actif.
     * Léger risque de race condition (deux requêtes simultanées lisent le même
     * compteur) intentionnellement accepté : l'impact est d'autoriser au plus
     * MAX_ATTEMPTS + N requêtes concurrentes, ce qui est négligeable.
     *
     * @return int|null Compteur courant, null en cas d'échec.
     */
    private static function increment_transient( string $key ): ?int {
        $count = get_transient( $key );

        if ( $count === false ) {
            set_transient( $key, 1, self::WINDOW_SECONDS );
            return 1;
        }

        $new_count = (int) $count + 1;
        set_transient( $key, $new_count, self::WINDOW_SECONDS );

        return $new_count;
    }

    // ── IP cliente ─────────────────────────────────────────────────────────

    /**
     * Retourne l'IP cliente validée.
     *
     * Source par défaut : REMOTE_ADDR (fiable, non falsifiable).
     *
     * Un filtre `givoly_client_ip` permet aux administrateurs dont le site
     * est derrière un reverse proxy de confiance (Cloudflare, nginx, AWS ELB)
     * de passer leur propre logique d'extraction d'IP réelle :
     *
     *   add_filter( 'givoly_client_ip', function( $ip ) {
     *       $cf_ip = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '' );
     *       return filter_var( $cf_ip, FILTER_VALIDATE_IP ) ? $cf_ip : $ip;
     *   } );
     *
     * @return string IP validée (IPv4 ou IPv6), chaîne vide si indéterminée.
     */
    private static function get_client_ip(): string {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

        /** @var string $ip */
        $ip = (string) apply_filters( 'givoly_client_ip', $ip );

        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    }
}
