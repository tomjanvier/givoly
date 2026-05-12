/**
 * Givasso — Frontend JS
 *
 * Vanilla JS, pas de dépendance externe.
 * Supporte plusieurs formulaires sur la même page.
 *
 * Responsabilités :
 * - Sélection du montant (presets + montant libre)
 * - Validation côté client
 * - Soumission AJAX
 *
 * givassoData est injecté par wp_localize_script() (AssetsLoader.php).
 */

( function () {
    'use strict';

    // ── Classe principale ────────────────────────────────────────────────────

    class GivassoForm {

        /** @param {HTMLFormElement} formEl */
        constructor( formEl ) {
            this.form        = formEl;
            this.amountField = formEl.querySelector( '.givasso-final-amount' );
            this.customWrap  = formEl.querySelector( '.givasso-custom-amount' );
            this.customInput = formEl.querySelector( '.givasso-input--amount' );
            this.messages    = formEl.querySelector( '.givasso-form__messages' );
            this.submitBtn   = formEl.querySelector( '.givasso-form__submit' );
            this.currency    = formEl.dataset.currency || '€';

            this.init();
        }

        init() {
            // Sélection de montant preset
            this.form.addEventListener( 'change', ( e ) => {
                if ( e.target.classList.contains( 'givasso-amount-btn__input' ) ) {
                    this.on_amount_change( e.target );
                }
            } );

            // Montant libre saisi
            this.customInput?.addEventListener( 'input', () => {
                this.amountField.value = this.customInput.value;
                this.update_btn_label();
            } );

            // Soumission
            this.form.addEventListener( 'submit', ( e ) => this.on_submit( e ) );

            // Pré-sélection du montant coché par défaut
            const checked = this.form.querySelector( '.givasso-amount-btn__input:checked' );
            if ( checked && checked.value !== 'custom' ) {
                this.amountField.value = checked.value;
            }

            this.update_btn_label();
        }

        // ── Gestionnaires d'événements ───────────────────────────────────────

        on_amount_change( radio ) {
            if ( radio.value === 'custom' ) {
                this.customWrap.hidden = false;
                this.customInput?.focus();
                this.amountField.value = '';
            } else {
                this.customWrap.hidden = true;
                this.amountField.value = radio.value;
            }

            this.update_btn_label();
        }

        on_submit( e ) {
            e.preventDefault();

            if ( ! this.validate() ) return;

            this.set_loading( true );

            const body = new FormData( this.form );

            fetch( givassoData.ajax_url, {
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
                    this.show_message( 'error', response.data?.message || givassoData.i18n.error );
                    this.set_loading( false );
                }
            } )
            .catch( () => {
                this.show_message( 'error', givassoData.i18n.error );
                this.set_loading( false );
            } );
            // Pas de finally : si succès on redirige, le bouton reste en loading
        }

        // ── Label CTA dynamique ──────────────────────────────────────────────

        update_btn_label() {
            const amount     = parseFloat( this.amountField.value );
            const btn_text   = this.submitBtn?.querySelector( '.givasso-btn__text' );
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
            const amount = parseFloat( this.amountField.value );

            if ( ! amount || amount < 1 || amount > 100_000 ) {
                this.show_message( 'error', givassoData.i18n.invalid_amount );
                return false;
            }

            const email = this.form.querySelector( '[name="email"]' )?.value.trim();

            if ( ! email || ! this.is_valid_email( email ) ) {
                this.show_message( 'error', givassoData.i18n.invalid_email );
                return false;
            }

            // Validation prénom/nom (layout card uniquement)
            const first = this.form.querySelector( '[name="first_name"]' )?.value.trim();
            const last  = this.form.querySelector( '[name="last_name"]' )?.value.trim();

            if ( ( first !== undefined && ! first ) || ( last !== undefined && ! last ) ) {
                this.show_message( 'error', givassoData.i18n.invalid_name );
                return false;
            }

            this.hide_messages();
            return true;
        }

        // ── UI helpers ───────────────────────────────────────────────────────

        set_loading( loading ) {
            this.submitBtn.disabled = loading;
            this.submitBtn.querySelector( '.givasso-btn__spinner' ).hidden = ! loading;
            this.submitBtn.querySelector( '.givasso-btn__text' ).hidden    = loading;
        }

        show_message( type, text ) {
            this.messages.hidden    = false;
            this.messages.className = `givasso-form__messages givasso-form__messages--${ type }`;
            this.messages.textContent = text;
            this.messages.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
        }

        hide_messages() {
            this.messages.hidden = true;
        }

        // ── Utilitaires ──────────────────────────────────────────────────────

        is_valid_email( email ) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
        }
    }

    // ── Sélection de fréquence (Pro gate) ────────────────────────────────────

    function init_frequency_buttons() {
        document.querySelectorAll( '.givasso-frequency-wrap' ).forEach( ( wrap ) => {
            const form        = wrap.closest( '.givasso-form' );
            const freq_input  = form ? form.querySelector( '[name="frequency"]' ) : null;

            const initial = freq_input ? freq_input.value : 'once';
            const monthlyBlock = form ? form.querySelector( '.givasso-ha-monthly' ) : null;
            const submitBtn = form ? form.querySelector( '.givasso-form__submit' ) : null;
            if ( monthlyBlock && submitBtn ) {
                const isMonthly = initial === 'monthly';
                monthlyBlock.hidden = ! isMonthly;
                submitBtn.hidden = isMonthly;
            }

            wrap.querySelectorAll( '.givasso-freq-btn' ).forEach( ( btn ) => {
                btn.addEventListener( 'click', () => {
                    const freq = btn.dataset.freq;

                    if ( freq_input ) {
                        freq_input.value = freq;
                    }

                    const monthlyBlock = form ? form.querySelector( '.givasso-ha-monthly' ) : null;
                    const submitBtn = form ? form.querySelector( '.givasso-form__submit' ) : null;
                    if ( monthlyBlock && submitBtn ) {
                        const isMonthly = freq === 'monthly';
                        monthlyBlock.hidden = ! isMonthly;
                        submitBtn.hidden = isMonthly;
                    }

                    wrap.querySelectorAll( '.givasso-freq-btn' ).forEach( ( b ) => b.classList.remove( 'active' ) );
                    btn.classList.add( 'active' );
                } );
            } );
        } );
    }

    // ── Message de remerciement au retour de paiement ────────────────────────

    function show_success_on_return() {
        if ( ! givassoData.success ) return;

        const form = document.querySelector( '.givasso-form' );
        if ( ! form ) return;

        const messages = form.querySelector( '.givasso-form__messages' );
        if ( ! messages ) return;

        messages.hidden    = false;
        messages.className = 'givasso-form__messages givasso-form__messages--success';
        messages.textContent = givassoData.i18n.success_message;
        messages.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );

        // Nettoyer le paramètre de l'URL sans recharger la page
        const url = new URL( window.location.href );
        url.searchParams.delete( 'givasso_success' );
        url.searchParams.delete( 'session_id' );
        history.replaceState( null, '', url.toString() );
    }

    // ── Initialisation ───────────────────────────────────────────────────────

    document.addEventListener( 'DOMContentLoaded', () => {
        document.querySelectorAll( '.givasso-form' ).forEach( ( form ) => new GivassoForm( form ) );
        init_frequency_buttons();
        document.querySelectorAll( '.givasso-gateway-submit' ).forEach( ( btn ) => {
            btn.addEventListener( 'click', () => {
                const form = btn.closest( '.givasso-form' );
                if ( ! form ) return;
                const gateway = form.querySelector( '[name="gateway"]' );
                if ( gateway ) {
                    gateway.value = btn.dataset.gateway || 'stripe';
                }
            } );
        } );
        show_success_on_return();
    } );

} )();
