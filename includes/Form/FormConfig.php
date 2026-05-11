<?php
/**
 * Configuration et thèmes du formulaire de don.
 *
 * ─── Comment ajouter un thème ──────────────────────────────────────────────
 * 1. Ajouter une entrée dans THEMES avec les variables CSS souhaitées.
 * 2. C'est tout. Le CSS consomme ces variables automatiquement.
 * ──────────────────────────────────────────────────────────────────────────
 *
 * Usage shortcode :
 *   [givasso_form theme="ocean" layout="card" amounts="10,25,50,100"]
 *
 * @package Givasso\Form
 */

namespace Givasso\Form;

use Givasso\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FormConfig {

    // ── Thèmes disponibles ─────────────────────────────────────────────────
    // Chaque entrée définit un jeu de variables CSS custom.
    // Toutes les valeurs sont échappées en sortie via get_inline_css_vars().

    const THEMES = [

        'classic' => [
            '--givasso-color-primary'       => '#4f46e5',
            '--givasso-color-primary-hover'  => '#4338ca',
            '--givasso-color-primary-light'  => '#ede9fe',
            '--givasso-color-bg'             => '#ffffff',
            '--givasso-color-surface'        => '#f8fafc',
            '--givasso-color-border'         => '#e2e8f0',
            '--givasso-color-text'           => '#1e293b',
            '--givasso-color-text-muted'     => '#64748b',
            '--givasso-radius'               => '12px',
            '--givasso-radius-sm'            => '8px',
        ],

        'ocean' => [
            '--givasso-color-primary'       => '#0891b2',
            '--givasso-color-primary-hover'  => '#0e7490',
            '--givasso-color-primary-light'  => '#cffafe',
            '--givasso-color-bg'             => '#ffffff',
            '--givasso-color-surface'        => '#f0f9ff',
            '--givasso-color-border'         => '#bae6fd',
            '--givasso-color-text'           => '#0c4a6e',
            '--givasso-color-text-muted'     => '#0369a1',
            '--givasso-radius'               => '16px',
            '--givasso-radius-sm'            => '10px',
        ],

        'sunset' => [
            '--givasso-color-primary'       => '#ea580c',
            '--givasso-color-primary-hover'  => '#c2410c',
            '--givasso-color-primary-light'  => '#ffedd5',
            '--givasso-color-bg'             => '#ffffff',
            '--givasso-color-surface'        => '#fff7ed',
            '--givasso-color-border'         => '#fed7aa',
            '--givasso-color-text'           => '#431407',
            '--givasso-color-text-muted'     => '#9a3412',
            '--givasso-radius'               => '8px',
            '--givasso-radius-sm'            => '4px',
        ],

        'minimal' => [
            '--givasso-color-primary'       => '#18181b',
            '--givasso-color-primary-hover'  => '#3f3f46',
            '--givasso-color-primary-light'  => '#f4f4f5',
            '--givasso-color-bg'             => '#ffffff',
            '--givasso-color-surface'        => '#fafafa',
            '--givasso-color-border'         => '#e4e4e7',
            '--givasso-color-text'           => '#18181b',
            '--givasso-color-text-muted'     => '#71717a',
            '--givasso-radius'               => '4px',
            '--givasso-radius-sm'            => '2px',
        ],

        // Palette officielle Givasso — ONG / fundraising
        'givasso' => [
            '--givasso-color-primary'       => '#1B6B4A',
            '--givasso-color-primary-hover'  => '#155438',
            '--givasso-color-primary-light'  => '#E8F5EE',
            '--givasso-color-bg'             => '#FFFFFF',
            '--givasso-color-surface'        => '#F8FAF9',
            '--givasso-color-border'         => '#C6E0D6',
            '--givasso-color-text'           => '#1A2E24',
            '--givasso-color-text-muted'     => '#55665F',
            '--givasso-radius'               => '12px',
            '--givasso-radius-sm'            => '8px',
            '--givasso-color-accent'         => '#2ECC71',
        ],

    ];

    const LAYOUTS = [ 'card', 'inline', 'flat' ];

    const DEFAULT_THEME  = 'givasso';
    const DEFAULT_LAYOUT = 'card';

    // ── Propriétés publiques (readonly = immuables après construction) ─────

    public readonly string $campaign;
    public readonly array  $amounts;
    public readonly string $currency;
    public readonly bool   $show_title;
    public readonly string $theme;
    public readonly string $layout;
    public readonly string $title;
    public readonly string $button_text;
    public readonly string $gateway;
    public readonly string $frequency;

    public function __construct( array $raw_atts ) {
        $this->campaign    = sanitize_text_field( $raw_atts['campaign']    ?? '' );
        $this->currency    = $this->parse_currency( $raw_atts['currency']  ?? 'EUR' );
        $this->show_title  = $this->parse_bool( $raw_atts['show_title']    ?? 'yes' );
        $this->amounts     = $this->parse_amounts( $raw_atts['amounts']    ?? '10,25,50,100' );
        $this->theme       = $this->parse_enum( $raw_atts['theme']  ?? '', array_keys( self::THEMES ), self::DEFAULT_THEME );
        $this->layout      = $this->parse_enum( $raw_atts['layout'] ?? '', self::LAYOUTS,              self::DEFAULT_LAYOUT );
        $this->title       = sanitize_text_field( ! empty( $raw_atts['title'] )       ? $raw_atts['title']       : __( 'Faire un don', 'givasso' ) );
        $this->button_text = sanitize_text_field( ! empty( $raw_atts['button_text'] ) ? $raw_atts['button_text'] : __( 'Donner maintenant', 'givasso' ) );
        $this->gateway     = $this->parse_enum( $raw_atts['gateway'] ?? '', [ 'stripe', 'helloasso' ], Settings::get_default_gateway() );
        $this->frequency   = $this->parse_enum(
            $raw_atts['frequency'] ?? '',
            [ 'once' ],
            'once'
        );
    }

    /**
     * Retourne les variables CSS du thème sous forme d'attribut style inline.
     * Chaîne de priorité : thème (shortcode) → couleurs admin → radius admin → btn_style admin.
     *
     * Sécurité : toutes les clés/valeurs sont échappées via esc_attr().
     */
    public function get_inline_css_vars(): string {
        // 1. Base : variables du thème sélectionné via shortcode attr theme=
        $vars = self::THEMES[ $this->theme ];

        // 2. Override couleur principale depuis les réglages admin
        $custom_primary = Settings::get_appearance_primary_color();
        if ( $custom_primary !== '' ) {
            $vars['--givasso-color-primary']      = $custom_primary;
            $vars['--givasso-color-primary-hover'] = $this->darken_hex( $custom_primary );
            $vars['--givasso-color-primary-light'] = $this->lighten_hex( $custom_primary );
        }

        // 3. Override couleur accent depuis les réglages admin
        $custom_accent = Settings::get_appearance_accent_color();
        if ( $custom_accent !== '' ) {
            $vars['--givasso-color-accent'] = $custom_accent;
        }

        // 4. Rayon des coins depuis les réglages admin
        $radius_map = [
            'square'  => [ '--givasso-radius' => '0px',  '--givasso-radius-sm' => '0px'  ],
            'rounded' => [ '--givasso-radius' => '12px', '--givasso-radius-sm' => '8px'  ],
            'pill'    => [ '--givasso-radius' => '20px', '--givasso-radius-sm' => '14px' ],
        ];
        $radius_key = Settings::get_appearance_radius();
        if ( isset( $radius_map[ $radius_key ] ) ) {
            $vars = array_merge( $vars, $radius_map[ $radius_key ] );
        }

        // 5. Style bouton
        $vars['--givasso-btn-style'] = Settings::get_appearance_btn_style();

        // Sérialisation
        $pairs = [];
        foreach ( $vars as $property => $value ) {
            $pairs[] = esc_attr( $property ) . ':' . esc_attr( $value );
        }

        return implode( ';', $pairs );
    }

    /**
     * Retourne les classes CSS supplémentaires à ajouter sur .givasso-wrap.
     * Permet au CSS de cibler les variantes de style sans sélecteur [style*=].
     */
    public function get_wrap_classes(): string {
        $classes = [];

        if ( Settings::get_appearance_btn_style() === 'outline' ) {
            $classes[] = 'givasso-btn-style-outline';
        }

        return implode( ' ', $classes );
    }

    /**
     * Montant présélectionné par défaut (le 2e, ou le 1er s'il n'y en a qu'un).
     */
    public function get_default_amount(): int {
        return $this->amounts[1] ?? $this->amounts[0];
    }

    /**
     * Retourne le symbole de la devise courante.
     * Source unique — les templates ne doivent pas redéfinir ce tableau.
     */
    public function get_currency_symbol(): string {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'MAD' => 'DH',
            'CHF' => 'CHF',
        ];

        return $symbols[ $this->currency ] ?? $this->currency;
    }

    // ── Helpers couleur (purs PHP, sans dépendance WordPress) ─────────────

    /**
     * Assombrit une couleur hex de ~30 niveaux (RGB).
     * Usage : calcul de primary-hover depuis primary.
     */
    private function darken_hex( string $hex ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - 30 );
        $g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - 30 );
        $b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - 30 );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

    /**
     * Éclaircit une couleur hex en interpolant 85% vers le blanc.
     * Usage : calcul de primary-light depuis primary.
     */
    private function lighten_hex( string $hex ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = (int) round( hexdec( substr( $hex, 0, 2 ) ) + ( 255 - hexdec( substr( $hex, 0, 2 ) ) ) * 0.85 );
        $g = (int) round( hexdec( substr( $hex, 2, 2 ) ) + ( 255 - hexdec( substr( $hex, 2, 2 ) ) ) * 0.85 );
        $b = (int) round( hexdec( substr( $hex, 4, 2 ) ) + ( 255 - hexdec( substr( $hex, 4, 2 ) ) ) * 0.85 );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

    // ── Parsers privés ─────────────────────────────────────────────────────

    private function parse_amounts( string $raw ): array {
        $amounts = array_map( 'intval', explode( ',', $raw ) );
        $amounts = array_filter( $amounts, fn( $a ) => $a >= 1 && $a <= 100_000 );
        $amounts = array_unique( $amounts );
        sort( $amounts );

        return array_values( $amounts ) ?: [ 10, 25, 50, 100 ];
    }

    private function parse_currency( string $raw ): string {
        $allowed = [ 'EUR', 'USD', 'GBP', 'MAD', 'CHF' ];
        $upper   = strtoupper( sanitize_text_field( $raw ) );

        return in_array( $upper, $allowed, true ) ? $upper : 'EUR';
    }

    private function parse_enum( string $raw, array $allowed, string $default ): string {
        $value = sanitize_text_field( $raw );

        return in_array( $value, $allowed, true ) ? $value : $default;
    }

    private function parse_bool( string $raw ): bool {
        return filter_var( $raw, FILTER_VALIDATE_BOOLEAN );
    }
}
