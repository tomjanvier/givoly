<?php
/**
 * Template : Formulaire de don — Layout "inline"
 *
 * Version compacte, idéale pour sidebar, widget ou footer.
 * Affiche uniquement montant + email + bouton.
 *
 * Variables disponibles (injectées par DonationForm::load_template) :
 *
 * @var \Givasso\Form\FormConfig $config
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Helpers locaux ──────────────────────────────────────────────────────────
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template file, variables are local-scope

$form_id = 'givasso-form-' . wp_unique_id();

$symbol = $config->get_currency_symbol();

$wrap_style = $config->get_inline_css_vars();
?>
<div class="givasso-wrap givasso-layout-inline <?php echo esc_attr( $config->get_wrap_classes() ); ?>"
     style="<?php echo esc_attr( $wrap_style ); ?>"
     role="region"
     aria-label="<?php esc_attr_e( 'Formulaire de don', 'givasso' ); ?>">

    <form id="<?php echo esc_attr( $form_id ); ?>"
          class="givasso-form"
          method="post"
          novalidate
          data-campaign="<?php echo esc_attr( $config->campaign ); ?>"
          data-currency="<?php echo esc_attr( $symbol ); ?>">

        <?php wp_nonce_field( 'givasso_submit_donation', 'givasso_nonce' ); ?>
        <input type="hidden" name="action"   value="givasso_init_checkout">
        <input type="hidden" name="currency" value="<?php echo esc_attr( $config->currency ); ?>">
        <input type="hidden" name="gateway"  value="<?php echo esc_attr( $config->gateway ); ?>">

        <input type="hidden" name="frequency" value="once">

        <?php if ( $config->campaign ) : ?>
            <input type="hidden" name="campaign" value="<?php echo esc_attr( $config->campaign ); ?>">
        <?php endif; ?>

        <?php if ( $config->show_title ) : ?>
            <h2 class="givasso-form__title">
                <?php echo esc_html( $config->title ); ?>
            </h2>
        <?php endif; ?>

        <!-- ── Montants ───────────────────────────────────────────────── -->
        <div class="givasso-amount-grid"
             role="group"
             aria-label="<?php esc_attr_e( 'Choisissez un montant', 'givasso' ); ?>">

            <?php foreach ( $config->amounts as $amount ) : ?>
                <label class="givasso-amount-btn">
                    <input type="radio"
                           name="preset_amount"
                           value="<?php echo esc_attr( $amount ); ?>"
                           class="givasso-amount-btn__input"
                           <?php checked( $amount, $config->get_default_amount() ); ?>>
                    <span class="givasso-amount-btn__label">
                        <?php echo esc_html( $amount . ' ' . $symbol ); ?>
                    </span>
                </label>
            <?php endforeach; ?>

            <label class="givasso-amount-btn">
                <input type="radio"
                       name="preset_amount"
                       value="custom"
                       class="givasso-amount-btn__input">
                <span class="givasso-amount-btn__label">
                    <?php esc_html_e( 'Autre', 'givasso' ); ?>
                </span>
            </label>
        </div>

        <div class="givasso-custom-amount" hidden aria-live="polite">
            <div class="givasso-input-group">
                <span class="givasso-input-group__prefix" aria-hidden="true">
                    <?php echo esc_html( $symbol ); ?>
                </span>
                <input type="number"
                       inputmode="numeric"
                       name="custom_amount"
                       class="givasso-input givasso-input--amount"
                       min="1"
                       max="100000"
                       step="1"
                       placeholder="<?php esc_attr_e( 'Montant', 'givasso' ); ?>">
            </div>
        </div>

        <input type="hidden" name="amount" class="givasso-final-amount">

        <!-- ── Email ──────────────────────────────────────────────────── -->
        <div class="givasso-field">
            <input type="email"
                   name="email"
                   class="givasso-input"
                   required
                   autocomplete="email"
                   maxlength="254"
                   placeholder="<?php esc_attr_e( 'Votre email', 'givasso' ); ?>">
        </div>

        <!-- ── Messages retour ────────────────────────────────────────── -->
        <div class="givasso-form__messages"
             aria-live="polite"
             aria-atomic="true"
             hidden></div>

        <!-- ── Bouton ─────────────────────────────────────────────────── -->
        <button type="submit"
                class="givasso-btn givasso-btn--primary givasso-form__submit"
                data-label="<?php echo esc_attr( $config->button_text ); ?>"
                data-label-amount="<?php esc_attr_e( 'Faire un don de', 'givasso' ); ?>">
            <span class="givasso-btn__text">
                <?php echo esc_html( $config->button_text ); ?>
            </span>
            <span class="givasso-btn__spinner" hidden aria-hidden="true"></span>
        </button>

        <?php $gateway_label = ( $config->gateway === 'helloasso' ) ? 'HelloAsso' : 'Stripe'; ?>
        <p class="givasso-form__trust">
            <svg class="givasso-form__trust-icon" width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <?php esc_html_e( 'Paiement 100% sécurisé par', 'givasso' ); ?>
            <span class="givasso-form__trust-badge givasso-form__trust-badge--<?php echo esc_attr( $config->gateway ); ?>">
                <?php echo esc_html( $gateway_label ); ?>
            </span>
        </p>

    </form>
</div>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
