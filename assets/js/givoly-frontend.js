/**
 * Givoly — Frontend JS
 *
 * Vanilla JS, pas de dépendance externe.
 * Supporte plusieurs formulaires sur la même page.
 *
 * Responsabilités :
 * - Sélection du montant (presets + montant libre)
 * - Validation côté client
 * - Soumission AJAX
 *
 * givolyData est injecté par wp_localize_script() (AssetsLoader.php).
 */

( function () {
    'use strict';

    // ── Classe principale ────────────────────────────────────────────────────

    class GivolyForm {

        /** @param {HTMLFormElement} formEl */
        constructor( formEl ) {
            this.form        = formEl;
            this.amountField = formEl.querySelector( '.givoly-final-amount' );
            this.customWrap  = formEl.querySelector( '.givoly-custom-amount' );
            this.customInput = formEl.querySelector( '.givoly-input--amount' );
            this.messages    = formEl.querySelector( '.givoly-form__messages' );
            this.submitBtn   = formEl.querySelector( '.givoly-form__submit' );
            this.currency    = formEl.dataset.currency || '€';

            this.init();
        }

        init() {
            // Sélection de montant preset
            this.form.addEventListener( 'change', ( e ) => {
                if ( e.target.classList.contains( 'givoly-amount-btn__input' ) ) {
                    this.on_amount_change( e.target );
                }
            } );

            // Montant libre saisi
            this.customInput?.addEventListener( 'input', () => {
                if ( this.amountField ) {
                    this.amountField.value = this.customInput.value;
                }
                this.update_btn_label();
            } );

            // Soumission
            this.form.addEventListener( 'submit', ( e ) => this.on_submit( e ) );

            // Pré-sélection du montant coché par défaut
            const checked = this.form.querySelector( '.givoly-amount-btn__input:checked' );
            if ( this.amountField && checked && checked.value !== 'custom' ) {
                this.amountField.value = checked.value;
            }

            this.update_btn_label();
        }

        // ── Gestionnaires d'événements ───────────────────────────────────────

        on_amount_change( radio ) {
            if ( radio.value === 'custom' ) {
                if ( this.customWrap ) {
                    this.customWrap.hidden = false;
                }
                this.customInput?.focus();
                if ( this.amountField ) {
                    this.amountField.value = '';
                }
            } else {
                if ( this.customWrap ) {
                    this.customWrap.hidden = true;
                }
                if ( this.amountField ) {
                    this.amountField.value = radio.value;
                }
            }

            this.update_btn_label();
        }

        on_submit( e ) {
            e.preventDefault();

            if ( ! this.validate() ) return;

            this.set_loading( true );

            const body = new FormData( this.form );

            fetch( givolyData.ajax_url, {
                method: 'POST',
                body,
            } )
            .then( ( r ) => r.json() )
            .then( ( response ) => {
                const checkout_url = response.data?.checkout_url ?? '';

                if ( response.success && checkout_url.startsWith( 'https://' ) ) {
                    // Redirection vers la page de paiement Stripe
                    window.location.href = checkout_url;
                } else {
                    this.show_message( 'error', response.data?.message || givolyData.i18n.error );
                    this.set_loading( false );
                }
            } )
            .catch( () => {
                this.show_message( 'error', givolyData.i18n.error );
                this.set_loading( false );
            } );
            // Pas de finally : si succès on redirige, le bouton reste en loading
        }

        // ── Label CTA dynamique ──────────────────────────────────────────────

        update_btn_label() {
            const amount     = this.parse_amount( this.amountField?.value || '' );
            const btn_text   = this.submitBtn?.querySelector( '.givoly-btn__text' );
            if ( ! btn_text ) return;

            const base_label   = this.submitBtn.dataset.label       || '';
            const amount_label = this.submitBtn.dataset.labelAmount || '';

            if ( amount >= 1 && amount_label ) {
                btn_text.textContent = `${ amount_label } ${ amount } ${ this.currency }`;
            } else {
                btn_text.textContent = base_label;
            }
        }

        // ── Validation ───────────────────────────────────────────────────────

        validate() {
            const amount = this.parse_amount( this.amountField?.value || '' );

            if ( ! amount || amount < 1 || amount > 100_000 ) {
                this.show_message( 'error', givolyData.i18n.invalid_amount );
                return false;
            }

            const email = this.form.querySelector( '[name="email"]' )?.value.trim();

            if ( ! email || ! this.is_valid_email( email ) ) {
                this.show_message( 'error', givolyData.i18n.invalid_email );
                return false;
            }

            // Validation prénom/nom (layout card uniquement)
            const first = this.form.querySelector( '[name="first_name"]' )?.value.trim();
            const last  = this.form.querySelector( '[name="last_name"]' )?.value.trim();

            if ( ( first !== undefined && ! first ) || ( last !== undefined && ! last ) ) {
                this.show_message( 'error', givolyData.i18n.invalid_name );
                return false;
            }

            this.hide_messages();
            return true;
        }

        // ── UI helpers ───────────────────────────────────────────────────────

        set_loading( loading ) {
            if ( ! this.submitBtn ) return;

            this.submitBtn.disabled = loading;

            const spinner = this.submitBtn.querySelector( '.givoly-btn__spinner' );
            const text    = this.submitBtn.querySelector( '.givoly-btn__text' );

            if ( spinner ) spinner.hidden = ! loading;
            if ( text ) text.hidden = loading;
        }

        show_message( type, text ) {
            if ( ! this.messages ) return;

            this.messages.hidden    = false;
            this.messages.className = `givoly-form__messages givoly-form__messages--${ type }`;
            this.messages.textContent = text;
            this.messages.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
        }

        hide_messages() {
            if ( this.messages ) {
                this.messages.hidden = true;
            }
        }

        // ── Utilitaires ──────────────────────────────────────────────────────

        parse_amount( value ) {
            return parseFloat( String( value ).replace( ',', '.' ) );
        }

        is_valid_email( email ) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
        }
    }

    // ── Message de remerciement au retour de paiement ────────────────────────

    function show_success_on_return() {
        if ( ! givolyData.success ) return;

        const form = document.querySelector( '.givoly-form' );
        if ( ! form ) return;

        const messages = form.querySelector( '.givoly-form__messages' );
        if ( ! messages ) return;

        messages.hidden    = false;
        messages.className = 'givoly-form__messages givoly-form__messages--success';
        messages.textContent = givolyData.i18n.success_message;
        messages.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );

        // Nettoyer le paramètre de l'URL sans recharger la page
        const url = new URL( window.location.href );
        url.searchParams.delete( 'givoly_success' );
        url.searchParams.delete( 'session_id' );
        history.replaceState( null, '', url.toString() );
    }

    function init_post_payment_form() {
        document.querySelectorAll( '.givoly-post-payment-form' ).forEach( ( form ) => {
            form.addEventListener( 'submit', ( e ) => {
                e.preventDefault();

                const messages = form.querySelector( '.givoly-form__messages' );
                const data = new FormData( form );
                data.append( 'action', 'givoly_save_post_payment_details' );
                data.append( 'givoly_nonce', form.closest( '.givoly-form' )?.querySelector( '[name="givoly_nonce"]' )?.value || '' );

                fetch( givolyData.ajax_url, { method: 'POST', body: data } )
                    .then( ( r ) => r.json() )
                    .then( ( response ) => {
                        messages.hidden = false;
                        if ( response.success ) {
                            messages.className = 'givoly-form__messages givoly-form__messages--success';
                            messages.textContent = response.data?.message || 'Informations enregistrées.';
                            form.reset();
                        } else {
                            messages.className = 'givoly-form__messages givoly-form__messages--error';
                            messages.textContent = response.data?.message || givolyData.i18n.error;
                        }
                    } )
                    .catch( () => {
                        messages.hidden = false;
                        messages.className = 'givoly-form__messages givoly-form__messages--error';
                        messages.textContent = givolyData.i18n.error;
                    } );
            } );
        } );
    }


    function protect_branding() {
        const requiredMarkup = givolyData?.branding || '';
        if ( ! requiredMarkup ) return;

        document.querySelectorAll( '.givoly-form' ).forEach( ( form ) => {
            let observer = null;

            const setAttributeIfChanged = ( element, name, value ) => {
                if ( element.getAttribute( name ) !== value ) {
                    element.setAttribute( name, value );
                }
            };

            const removeAttributeIfPresent = ( element, name ) => {
                if ( element.hasAttribute( name ) ) {
                    element.removeAttribute( name );
                }
            };

            const ensureBranding = () => {
                // Les mutations faites ici ne doivent pas relancer l'observer.
                // Sinon, une simple mise à jour du libellé du montant peut déclencher
                // une boucle MutationObserver qui bloque toute la page.
                observer?.disconnect();

                let branding = form.querySelector( '[data-givoly-branding="required"]' );
                const submitTrust = form.querySelector( '.givoly-form__trust' );

                if ( ! branding ) {
                    form.insertAdjacentHTML( 'beforeend', requiredMarkup );
                    branding = form.querySelector( '[data-givoly-branding="required"]' );
                }

                if ( ! branding ) {
                    observer?.observe( form, { childList: true, subtree: true } );
                    return;
                }

                removeAttributeIfPresent( branding, 'style' );
                if ( branding.className !== 'givoly-branding' ) {
                    branding.className = 'givoly-branding';
                }

                const link = branding.querySelector( 'a' );
                const logo = branding.querySelector( 'img' );

                if ( link ) {
                    if ( link.className !== 'givoly-branding__link' ) {
                        link.className = 'givoly-branding__link';
                    }
                    setAttributeIfChanged( link, 'target', '_blank' );
                    setAttributeIfChanged( link, 'rel', 'noopener noreferrer' );
                    removeAttributeIfPresent( link, 'style' );
                }

                if ( logo ) {
                    if ( logo.className !== 'givoly-branding__logo' ) {
                        logo.className = 'givoly-branding__logo';
                    }
                    setAttributeIfChanged( logo, 'alt', 'Givoly' );
                    removeAttributeIfPresent( logo, 'style' );
                }

                if ( link && link.href !== 'https://givoly.org/' ) {
                    link.href = 'https://givoly.org';
                }

                if ( logo && ! logo.src.includes( 'Black-and-Red-Foundation-Community-Non-Profit-Logo.png' ) ) {
                    logo.src = 'https://givoly.org/wp-content/uploads/2026/06/Black-and-Red-Foundation-Community-Non-Profit-Logo.png';
                }

                if ( submitTrust?.nextElementSibling !== branding ) {
                    submitTrust?.after( branding );
                }

                observer?.observe( form, { childList: true, subtree: true } );
            };

            observer = new MutationObserver( ensureBranding );
            ensureBranding();
        } );
    }

    // ── Initialisation ───────────────────────────────────────────────────────

    document.addEventListener( 'DOMContentLoaded', () => {
        document.querySelectorAll( '.givoly-form' ).forEach( ( form ) => new GivolyForm( form ) );
        document.querySelectorAll( '.givoly-gateway-submit' ).forEach( ( btn ) => {
            btn.addEventListener( 'click', () => {
                const form = btn.closest( '.givoly-form' );
                if ( ! form ) return;
                const gateway = form.querySelector( '[name="gateway"]' );
                if ( gateway ) {
                    gateway.value = btn.dataset.gateway || 'stripe';
                }
            } );
        } );
        show_success_on_return();
        init_post_payment_form();
        protect_branding();
    } );

} )();
