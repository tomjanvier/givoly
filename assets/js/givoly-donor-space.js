( function () {
    'use strict';

    function request( action, values ) {
        var data = new FormData();
        data.append( 'action', action );
        data.append( 'nonce', window.givolyDonorSpaceData.nonce );
        Object.keys( values || {} ).forEach( function ( key ) {
            data.append( key, values[ key ] );
        } );

        return fetch( window.givolyDonorSpaceData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        } ).then( function ( response ) {
            return response.json();
        } );
    }

    function showMessage( element, message, isError ) {
        if ( ! element ) {
            return;
        }
        element.textContent = message || '';
        element.classList.toggle( 'is-error', Boolean( isError ) );
    }

    function openPortal( messageElement ) {
        request( 'givoly_donor_open_portal', { return_url: window.location.href } ).then( function ( result ) {
            if ( result.success && result.data.url ) {
                window.location.href = result.data.url;
                return;
            }
            showMessage( messageElement, result.data && result.data.message ? result.data.message : window.givolyDonorSpaceData.i18n.genericError, true );
        } ).catch( function () {
            showMessage( messageElement, window.givolyDonorSpaceData.i18n.genericError, true );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        var login = document.querySelector( '[data-givoly-donor-login]' );
        if ( login ) {
            login.addEventListener( 'submit', function ( event ) {
                event.preventDefault();
                var message = login.querySelector( '[data-givoly-message]' );
                var button = login.querySelector( 'button[type="submit"]' );
                button.disabled = true;
                request( 'givoly_donor_request_access', {
                    email: login.querySelector( '[name="email"]' ).value,
                    return_url: login.querySelector( '[name="return_url"]' ).value
                } ).then( function ( result ) {
                    showMessage( message, result.data && result.data.message ? result.data.message : window.givolyDonorSpaceData.i18n.genericError, ! result.success );
                } ).catch( function () {
                    showMessage( message, window.givolyDonorSpaceData.i18n.genericError, true );
                } ).finally( function () {
                    button.disabled = false;
                } );
            } );
        }

        document.querySelectorAll( '[data-givoly-portal]' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                openPortal( button.closest( '.givoly-donor-space__subscription' ).querySelector( '[data-givoly-message]' ) );
            } );
        } );

        document.querySelectorAll( '[data-givoly-cancel-start]' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                var retention = button.closest( '.givoly-donor-space__subscription' ).querySelector( '[data-givoly-retention]' );
                retention.hidden = false;
                button.hidden = true;
            } );
        } );

        document.querySelectorAll( '[data-givoly-cancel-confirm]' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                var wrapper = button.closest( '.givoly-donor-space__subscription' );
                var message = wrapper.querySelector( '[data-givoly-message]' );
                button.disabled = true;
                request( 'givoly_donor_cancel_subscription' ).then( function ( result ) {
                    showMessage( message, result.data && result.data.message ? result.data.message : window.givolyDonorSpaceData.i18n.genericError, ! result.success );
                } ).catch( function () {
                    showMessage( message, window.givolyDonorSpaceData.i18n.genericError, true );
                } ).finally( function () {
                    button.disabled = false;
                } );
            } );
        } );

        document.querySelectorAll( '[data-givoly-logout]' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                request( 'givoly_donor_logout' ).finally( function () {
                    window.location.reload();
                } );
            } );
        } );
    } );
}() );
