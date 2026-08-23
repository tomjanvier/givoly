/**
 * Givoly — Blocs Gutenberg (éditeur)
 *
 * Vanilla JS sans étape de build : s'appuie sur les globales wp.* fournies
 * par WordPress (wp.element = React, pas de JSX nécessaire).
 *
 * Les blocs sont rendus côté serveur (BlockRegistrar.php). L'éditeur affiche
 * un aperçu fidèle via <ServerSideRender> et expose les réglages dans la
 * barre latérale (InspectorControls).
 *
 * Dépendances déclarées en PHP : wp-blocks, wp-element, wp-block-editor,
 * wp-components, wp-i18n, wp-server-side-render.
 */
( function ( wp ) {
    'use strict';

    if ( ! wp || ! wp.blocks || ! wp.element ) {
        return;
    }

    var blocks       = wp.blocks;
    var element      = wp.element;
    var el           = element.createElement;
    var Fragment     = element.Fragment;
    var __           = ( wp.i18n && wp.i18n.__ ) ? wp.i18n.__ : function ( s ) { return s; };

    var InspectorControls = ( wp.blockEditor && wp.blockEditor.InspectorControls ) ? wp.blockEditor.InspectorControls : null;
    var PanelBody         = ( wp.components && wp.components.PanelBody ) ? wp.components.PanelBody : null;
    var TextControl       = ( wp.components && wp.components.TextControl ) ? wp.components.TextControl : null;
    var SelectControl     = ( wp.components && wp.components.SelectControl ) ? wp.components.SelectControl : null;
    var ToggleControl     = ( wp.components && wp.components.ToggleControl ) ? wp.components.ToggleControl : null;

    // Le paquet @wordpress/server-side-render exporte le composant par défaut ;
    // selon la version il est exposé sous .default ou directement.
    var ServerSideRender  = ( wp.serverSideRender && ( wp.serverSideRender.default || wp.serverSideRender ) ) || null;

    if ( ! ServerSideRender || ! InspectorControls || ! PanelBody ) {
        return;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    function textControl( label, value, onChange, help ) {
        if ( ! TextControl ) {
            return null;
        }
        return el( TextControl, {
            label: label,
            value: value,
            help: help,
            onChange: onChange,
        } );
    }

    function selectControl( label, value, options, onChange ) {
        if ( ! SelectControl ) {
            return null;
        }
        return el( SelectControl, {
            label: label,
            value: value,
            options: options,
            onChange: onChange,
        } );
    }

    function toggleControl( label, checked, onChange ) {
        if ( ! ToggleControl ) {
            return null;
        }
        return el( ToggleControl, {
            label: label,
            checked: checked,
            onChange: onChange,
        } );
    }

    function panel( title, children ) {
        return el(
            PanelBody,
            { title: title, initialOpen: true },
            children
        );
    }

    /**
     * Éditeur générique : réglages dans la barre latérale + aperçu serveur.
     *
     * @param {Object}   props         Props du bloc fournies par Gutenberg.
     * @param {Function} buildControls Retourne la liste des contrôles.
     * @return {Object} Élément React.
     */
    function editWithPreview( props, buildControls ) {
        return el(
            Fragment,
            {},
            el( InspectorControls, {}, panel( __( 'Settings', 'givoly' ), buildControls( props ) ) ),
            el(
                'div',
                { className: 'givoly-block-preview' },
                el( ServerSideRender, {
                    block: props.name,
                    attributes: props.attributes,
                } )
            )
        );
    }

    // ── Options partagées ──────────────────────────────────────────────────

    var themeOptions = [
        { label: __( 'Givoly', 'givoly' ), value: 'givoly' },
        { label: __( 'Classic', 'givoly' ), value: 'classic' },
        { label: __( 'Ocean', 'givoly' ), value: 'ocean' },
        { label: __( 'Sunset', 'givoly' ), value: 'sunset' },
        { label: __( 'Minimal', 'givoly' ), value: 'minimal' },
    ];

    var layoutOptions = [
        { label: __( 'Card', 'givoly' ), value: 'card' },
        { label: __( 'Inline', 'givoly' ), value: 'inline' },
        { label: __( 'Flat', 'givoly' ), value: 'flat' },
    ];

    var currencyOptions = [
        { label: 'EUR (€)', value: 'EUR' },
        { label: 'USD ($)', value: 'USD' },
        { label: 'GBP (£)', value: 'GBP' },
        { label: 'CHF', value: 'CHF' },
        { label: 'MAD (DH)', value: 'MAD' },
    ];

    var gatewayOptions = [
        { label: __( 'Default gateway', 'givoly' ), value: '' },
        { label: 'Stripe', value: 'stripe' },
        { label: 'HelloAsso', value: 'helloasso' },
        { label: __( 'Both gateways', 'givoly' ), value: 'both' },
    ];

    // ── Bloc : formulaire de don ───────────────────────────────────────────

    blocks.registerBlockType( 'givoly/form', {
        icon: 'heart',
        keywords: [ __( 'donation', 'givoly' ), __( 'donate', 'givoly' ), __( 'form', 'givoly' ) ],
        edit: function ( props ) {
            return editWithPreview( props, function ( p ) {
                var a = p.attributes;
                var set = function ( key ) {
                    return function ( value ) {
                        var update = {};
                        update[ key ] = value;
                        p.setAttributes( update );
                    };
                };
                return [
                    textControl( __( 'Campaign slug', 'givoly' ), a.campaign, set( 'campaign' ), __( 'Optional. Leave empty for a general donation form.', 'givoly' ) ),
                    textControl( __( 'Preset amounts', 'givoly' ), a.amounts, set( 'amounts' ), __( 'Comma-separated amounts, e.g. 10,25,50,100.', 'givoly' ) ),
                    selectControl( __( 'Currency', 'givoly' ), a.currency, currencyOptions, set( 'currency' ) ),
                    selectControl( __( 'Theme', 'givoly' ), a.theme, themeOptions, set( 'theme' ) ),
                    selectControl( __( 'Layout', 'givoly' ), a.layout, layoutOptions, set( 'layout' ) ),
                    selectControl( __( 'Payment gateway', 'givoly' ), a.gateway, gatewayOptions, set( 'gateway' ) ),
                    toggleControl( __( 'Show title', 'givoly' ), !! a.show_title, set( 'show_title' ) ),
                    textControl( __( 'Custom title', 'givoly' ), a.title, set( 'title' ) ),
                    textControl( __( 'Button text', 'givoly' ), a.button_text, set( 'button_text' ) ),
                ];
            } );
        },
        save: function () {
            return null;
        },
    } );

    // ── Bloc : campagne ────────────────────────────────────────────────────

    blocks.registerBlockType( 'givoly/campaign', {
        icon: 'megaphone',
        keywords: [ __( 'campaign', 'givoly' ), __( 'fundraising', 'givoly' ), __( 'goal', 'givoly' ) ],
        edit: function ( props ) {
            return editWithPreview( props, function ( p ) {
                var a = p.attributes;
                var set = function ( key ) {
                    return function ( value ) {
                        var update = {};
                        update[ key ] = value;
                        p.setAttributes( update );
                    };
                };
                return [
                    textControl( __( 'Campaign slug', 'givoly' ), a.campaign, set( 'campaign' ), __( 'Required. Slug of one of your Givoly campaigns.', 'givoly' ) ),
                    selectControl( __( 'Theme', 'givoly' ), a.theme, themeOptions, set( 'theme' ) ),
                    selectControl( __( 'Form layout', 'givoly' ), a.layout, layoutOptions, set( 'layout' ) ),
                    toggleControl( __( 'Show campaign title', 'givoly' ), !! a.show_title, set( 'show_title' ) ),
                    toggleControl( __( 'Show description', 'givoly' ), !! a.show_description, set( 'show_description' ) ),
                    toggleControl( __( 'Show donation form', 'givoly' ), !! a.show_form, set( 'show_form' ) ),
                    toggleControl( __( 'Show form title', 'givoly' ), !! a.show_form_title, set( 'show_form_title' ) ),
                ];
            } );
        },
        save: function () {
            return null;
        },
    } );

    // ── Bloc : total collecté ──────────────────────────────────────────────

    blocks.registerBlockType( 'givoly/total', {
        icon: 'chart-bar',
        keywords: [ __( 'total', 'givoly' ), __( 'progress', 'givoly' ), __( 'raised', 'givoly' ) ],
        edit: function ( props ) {
            return editWithPreview( props, function ( p ) {
                var a = p.attributes;
                var set = function ( key ) {
                    return function ( value ) {
                        var update = {};
                        update[ key ] = value;
                        p.setAttributes( update );
                    };
                };
                var displayOptions = [
                    { label: __( 'Amount', 'givoly' ), value: '' },
                    { label: __( 'Donation count', 'givoly' ), value: 'count' },
                    { label: __( 'Progress bar', 'givoly' ), value: 'bar' },
                ];
                return [
                    textControl( __( 'Campaign slug', 'givoly' ), a.campaign, set( 'campaign' ), __( 'Optional. Leave empty to show all donations.', 'givoly' ) ),
                    selectControl( __( 'Display', 'givoly' ), a.display, displayOptions, set( 'display' ) ),
                ];
            } );
        },
        save: function () {
            return null;
        },
    } );

} )( window.wp );
