<?php
/**
 * Blocs Gutenberg dynamiques — rendu côté serveur via les widgets existants.
 *
 * Trois blocs sans étape de build :
 *   givoly/form      → DonationForm
 *   givoly/campaign  → CampaignWidget
 *   givoly/total     → CampaignTotalWidget
 *
 * Le rendu éditeur est assuré par <ServerSideRender> (assets/js/givoly-blocks.js,
 * vanilla JS sans dépendance npm) : l'aperçu dans Gutenberg est identique au rendu public.
 *
 * @package Givoly\Form
 */

namespace Givoly\Form;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BlockRegistrar {

    public function register(): void {
        add_action( 'init', [ $this, 'register_blocks' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
        add_filter( 'block_categories_all', [ $this, 'add_block_category' ], 10, 2 );
    }

    /**
     * Catégorie dédiée « Givoly » dans l'inserteur de blocs.
     */
    public function add_block_category( array $categories ): array {
        foreach ( $categories as $category ) {
            if ( ( $category['slug'] ?? '' ) === 'givoly' ) {
                return $categories;
            }
        }

        $categories[] = [
            'slug'  => 'givoly',
            'title' => __( 'Givoly', 'givoly' ),
            'icon'  => 'heart',
        ];

        return $categories;
    }

    public function register_blocks(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        wp_register_script(
            'givoly-blocks',
            GIVOLY_PLUGIN_URL . 'assets/js/givoly-blocks.js',
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ],
            GIVOLY_VERSION,
            true
        );
        wp_set_script_translations( 'givoly-blocks', 'givoly' );

        register_block_type(
            'givoly/form',
            [
                'api_version'     => 2,
                'title'           => __( 'Givoly donation form', 'givoly' ),
                'category'        => 'givoly',
                'editor_script'   => 'givoly-blocks',
                'render_callback' => [ $this, 'render_form_block' ],
                'attributes'      => [
                    'campaign'    => [ 'type' => 'string', 'default' => '' ],
                    'amounts'     => [ 'type' => 'string', 'default' => '10,25,50,100' ],
                    'currency'    => [ 'type' => 'string', 'default' => 'EUR' ],
                    'show_title'  => [ 'type' => 'boolean', 'default' => true ],
                    'theme'       => [ 'type' => 'string', 'default' => 'givoly' ],
                    'layout'      => [ 'type' => 'string', 'default' => 'card' ],
                    'title'       => [ 'type' => 'string', 'default' => '' ],
                    'button_text' => [ 'type' => 'string', 'default' => '' ],
                    'gateway'     => [ 'type' => 'string', 'default' => '' ],
                ],
                'supports'        => [ 'html' => false ],
            ]
        );

        register_block_type(
            'givoly/campaign',
            [
                'api_version'     => 2,
                'title'           => __( 'Givoly campaign', 'givoly' ),
                'category'        => 'givoly',
                'editor_script'   => 'givoly-blocks',
                'render_callback' => [ $this, 'render_campaign_block' ],
                'attributes'      => [
                    'campaign'         => [ 'type' => 'string', 'default' => '' ],
                    'show_description' => [ 'type' => 'boolean', 'default' => true ],
                    'show_form'        => [ 'type' => 'boolean', 'default' => true ],
                    'show_title'       => [ 'type' => 'boolean', 'default' => true ],
                    'show_form_title'  => [ 'type' => 'boolean', 'default' => false ],
                    'layout'           => [ 'type' => 'string', 'default' => 'card' ],
                    'theme'            => [ 'type' => 'string', 'default' => 'classic' ],
                ],
                'supports'        => [ 'html' => false ],
            ]
        );

        register_block_type(
            'givoly/total',
            [
                'api_version'     => 2,
                'title'           => __( 'Givoly campaign total', 'givoly' ),
                'category'        => 'givoly',
                'editor_script'   => 'givoly-blocks',
                'render_callback' => [ $this, 'render_total_block' ],
                'attributes'      => [
                    'campaign' => [ 'type' => 'string', 'default' => '' ],
                    'display'  => [ 'type' => 'string', 'default' => '' ],
                ],
                'supports'        => [ 'html' => false ],
            ]
        );
    }

    // ── Rendu serveur ──────────────────────────────────────────────────────

    public function render_form_block( array $attributes ): string {
        return ( new ShortcodeManager() )->render_form(
            $this->bool_to_string( $attributes, [ 'show_title' ] )
        );
    }

    public function render_campaign_block( array $attributes ): string {
        return ( new ShortcodeManager() )->render_campaign(
            $this->bool_to_string(
                $attributes,
                [ 'show_description', 'show_form', 'show_title', 'show_form_title' ]
            )
        );
    }

    public function render_total_block( array $attributes ): string {
        return ( new ShortcodeManager() )->render_total( $attributes );
    }

    /**
     * Les shortcodes attendent 'yes'/'no' ; les attributs de bloc sont booléens.
     */
    private function bool_to_string( array $attributes, array $keys ): array {
        foreach ( $keys as $key ) {
            if ( isset( $attributes[ $key ] ) ) {
                $attributes[ $key ] = $attributes[ $key ] ? 'yes' : 'no';
            }
        }

        return $attributes;
    }

    // ── Assets éditeur ─────────────────────────────────────────────────────

    public function enqueue_editor_assets(): void {
        // Le CSS public garantit un aperçu fidèle des formulaires dans l'éditeur.
        wp_enqueue_style(
            'givoly-frontend-editor-preview',
            GIVOLY_PLUGIN_URL . 'assets/css/givoly-frontend.css',
            [],
            GIVOLY_VERSION
        );
        wp_add_inline_style(
            'givoly-frontend-editor-preview',
            '.givoly-block-preview{position:relative}.givoly-block-preview::after{content:"";position:absolute;inset:0;z-index:1;}'
        );
    }
}
