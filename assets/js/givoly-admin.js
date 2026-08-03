/**
 * Givoly — Admin JS
 *
 * Shared behavior for the Givoly settings and campaign screens.
 */

( function () {
    'use strict';

    function init_campaign_slug() {
        const titleInput = document.getElementById( 'givoly-title' );
        const slugInput  = document.getElementById( 'givoly-slug' );

        if ( ! titleInput || ! slugInput ) {
            return;
        }

        titleInput.addEventListener( 'input', function () {
            if ( slugInput.dataset.edited ) {
                return;
            }

            slugInput.value = titleInput.value
                .toLowerCase()
                .normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
                .replace( /[^a-z0-9]+/g, '-' )
                .replace( /^-+|-+$/g, '' );
        } );

        slugInput.addEventListener( 'input', function () {
            slugInput.dataset.edited = '1';
        } );
    }

    function init_settings() {
        document.querySelectorAll( '.givoly-gateway-card input' ).forEach( function ( radio ) {
            radio.addEventListener( 'change', function () {
                document.querySelectorAll( '.givoly-gateway-card' ).forEach( function ( card ) {
                    card.classList.remove( 'is-selected' );
                } );

                const card = radio.closest( '.givoly-gateway-card' );
                if ( card ) {
                    card.classList.add( 'is-selected' );
                }
            } );
        } );

        document.querySelectorAll( '.givoly-mode-toggle input' ).forEach( function ( radio ) {
            radio.addEventListener( 'change', function () {
                const toggle = radio.closest( '.givoly-mode-toggle' );
                if ( ! toggle ) {
                    return;
                }

                toggle.querySelectorAll( '.givoly-mode-toggle__option' ).forEach( function ( option ) {
                    option.classList.remove( 'is-active' );
                } );

                const option = radio.closest( '.givoly-mode-toggle__option' );
                if ( option ) {
                    option.classList.add( 'is-active' );
                }
            } );
        } );

        document.querySelectorAll( '.givoly-copy-btn' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                const target = document.getElementById( button.dataset.target );
                if ( ! target || ! navigator.clipboard ) {
                    return;
                }

                navigator.clipboard.writeText( target.textContent.trim() ).then( function () {
                    button.classList.add( 'givoly-copy-btn--copied' );
                    const icon = button.querySelector( '.dashicons' );
                    if ( icon ) {
                        icon.className = 'dashicons dashicons-yes';
                    }

                    window.setTimeout( function () {
                        button.classList.remove( 'givoly-copy-btn--copied' );
                        if ( icon ) {
                            icon.className = 'dashicons dashicons-clipboard';
                        }
                    }, 2000 );
                } ).catch( function () {
                    // Clipboard access may be unavailable on non-secure admin URLs.
                } );
            } );
        } );

        document.querySelectorAll( 'input[type="color"][data-preview-id]' ).forEach( function ( picker ) {
            picker.addEventListener( 'input', function () {
                const preview = document.getElementById( picker.dataset.previewId );
                const hex     = document.getElementById( picker.dataset.hexId );
                const enabled = picker.dataset.enabledId
                    ? document.getElementById( picker.dataset.enabledId )
                    : null;

                if ( preview ) {
                    preview.style.background = picker.value;
                }
                if ( hex ) {
                    hex.textContent = picker.value;
                }
                if ( enabled ) {
                    enabled.value = '1';
                }
            } );
        } );

        document.querySelectorAll( '.givoly-ap-reset' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                const fieldInput   = document.querySelector( 'input[name="' + button.dataset.field + '"]' );
                const enabledInput = document.getElementById( button.dataset.enabled );
                const preview      = document.getElementById( button.dataset.previewId );
                const hex           = document.getElementById( button.dataset.hexId );

                if ( fieldInput ) {
                    fieldInput.value = button.dataset.default;
                }
                if ( enabledInput ) {
                    enabledInput.value = '0';
                }
                if ( preview ) {
                    preview.style.background = button.dataset.default;
                }
                if ( hex ) {
                    hex.textContent = button.dataset.default;
                }
                button.style.display = 'none';
            } );
        } );

        document.querySelectorAll( '.givoly-shape-group input[type="radio"]' ).forEach( function ( radio ) {
            radio.addEventListener( 'change', function () {
                const group = radio.closest( '.givoly-shape-group' );
                if ( ! group ) {
                    return;
                }

                group.querySelectorAll( '.givoly-shape-card' ).forEach( function ( card ) {
                    card.classList.remove( 'is-selected' );
                } );

                const card = radio.closest( '.givoly-shape-card' );
                if ( card ) {
                    card.classList.add( 'is-selected' );
                }
            } );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        init_campaign_slug();
        init_settings();
    } );
} )();
