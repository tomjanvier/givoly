<?php
/**
 * Template : Formulaire de don — Layout "inline"
 *
 * Version compacte, idéale pour sidebar, widget ou footer.
 * Affiche uniquement montant + email + bouton.
 *
 * Variables disponibles (injectées par DonationForm::load_template) :
 *
 * @var \Givoly\Form\FormConfig $config
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Helpers locaux ──────────────────────────────────────────────────────────
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template file, variables are local-scope

$form_id = 'givoly-form-' . wp_unique_id();
$show_stripe_gateway = in_array( $config->gateway, [ 'stripe', 'both' ], true );
$show_helloasso_gateway = in_array( $config->gateway, [ 'helloasso', 'both' ], true );
$default_form_gateway = $show_stripe_gateway ? 'stripe' : 'helloasso';

$symbol = $config->get_currency_symbol();

$wrap_style = $config->get_inline_css_vars();

$render_extra_fields = static function ( string $form_id, array $extra_fields ): void {
    foreach ( $extra_fields as $field ) {
        if ( $field === 'phone' ) { ?>
            <div class="givoly-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-phone" class="givoly-label"><?php esc_html_e( 'Téléphone', 'givoly' ); ?></label>
                <input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone" class="givoly-input" maxlength="40" autocomplete="tel">
            </div>
        <?php } elseif ( $field === 'company' ) { ?>
            <div class="givoly-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-company" class="givoly-label"><?php esc_html_e( 'Organisation', 'givoly' ); ?></label>
                <input type="text" id="<?php echo esc_attr( $form_id ); ?>-company" name="company" class="givoly-input" maxlength="120" autocomplete="organization">
            </div>
        <?php } elseif ( $field === 'message' ) { ?>
            <div class="givoly-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-message" class="givoly-label"><?php esc_html_e( 'Message', 'givoly' ); ?></label>
                <textarea id="<?php echo esc_attr( $form_id ); ?>-message" name="message" class="givoly-input givoly-textarea" rows="3" maxlength="500"></textarea>
            </div>
        <?php }
    }
};
?>
<div class="givoly-wrap givoly-layout-inline <?php echo esc_attr( $config->get_wrap_classes() ); ?>"
     style="<?php echo esc_attr( $wrap_style ); ?>"
     role="region"
     aria-label="<?php esc_attr_e( 'Formulaire de don', 'givoly' ); ?>">

    <form id="<?php echo esc_attr( $form_id ); ?>"
          class="givoly-form"
          method="post"
          novalidate
          data-campaign="<?php echo esc_attr( $config->campaign ); ?>"
          data-currency="<?php echo esc_attr( $symbol ); ?>">

        <?php wp_nonce_field( 'givoly_submit_donation', 'givoly_nonce' ); ?>
        <input type="hidden" name="action"   value="givoly_init_checkout">
        <input type="hidden" name="currency" value="<?php echo esc_attr( $config->currency ); ?>">
        <input type="hidden" name="gateway"  value="<?php echo esc_attr( $default_form_gateway ); ?>">

        <?php if ( $config->campaign ) : ?>
            <input type="hidden" name="campaign" value="<?php echo esc_attr( $config->campaign ); ?>">
        <?php endif; ?>

        <?php if ( $config->show_title ) : ?>
            <h2 class="givoly-form__title">
                <?php echo esc_html( $config->title ); ?>
            </h2>
        <?php endif; ?>

        <div class="givoly-amount-grid givoly-frequency" role="group" aria-label="<?php esc_attr_e( 'Fréquence', 'givoly' ); ?>">
            <label class="givoly-amount-btn">
                <input type="radio" name="frequency" value="once" class="givoly-frequency__input" checked>
                <span class="givoly-amount-btn__label"><?php esc_html_e( 'Une fois', 'givoly' ); ?></span>
            </label>
            <?php if ( $show_stripe_gateway ) : ?>
            <label class="givoly-amount-btn">
                <input type="radio" name="frequency" value="monthly" class="givoly-frequency__input">
                <span class="givoly-amount-btn__label"><?php esc_html_e( 'Mensuel', 'givoly' ); ?></span>
            </label>
            <?php endif; ?>
        </div>

        <!-- ── Montants ───────────────────────────────────────────────── -->
        <div class="givoly-amount-grid"
             role="group"
             aria-label="<?php esc_attr_e( 'Choisissez un montant', 'givoly' ); ?>">

            <?php foreach ( $config->amounts as $amount ) : ?>
                <label class="givoly-amount-btn">
                    <input type="radio"
                           name="preset_amount"
                           value="<?php echo esc_attr( $amount ); ?>"
                           class="givoly-amount-btn__input"
                           <?php checked( $amount, $config->get_default_amount() ); ?>>
                    <span class="givoly-amount-btn__label">
                        <?php echo esc_html( $amount . ' ' . $symbol ); ?>
                    </span>
                </label>
            <?php endforeach; ?>

            <label class="givoly-amount-btn">
                <input type="radio"
                       name="preset_amount"
                       value="custom"
                       class="givoly-amount-btn__input">
                <span class="givoly-amount-btn__label">
                    <?php esc_html_e( 'Autre', 'givoly' ); ?>
                </span>
            </label>
        </div>

        <div class="givoly-custom-amount" hidden aria-live="polite">
            <div class="givoly-input-group">
                <span class="givoly-input-group__prefix" aria-hidden="true">
                    <?php echo esc_html( $symbol ); ?>
                </span>
                <input type="number"
                       inputmode="numeric"
                       name="custom_amount"
                       class="givoly-input givoly-input--amount"
                       min="1"
                       max="100000"
                       step="1"
                       placeholder="<?php esc_attr_e( 'Montant', 'givoly' ); ?>">
            </div>
        </div>

        <input type="hidden" name="amount" class="givoly-final-amount">

        <!-- ── Email ──────────────────────────────────────────────────── -->
        <div class="givoly-field">
            <input type="email"
                   name="email"
                   class="givoly-input"
                   required
                   autocomplete="email"
                   maxlength="254"
                   placeholder="<?php esc_attr_e( 'Votre email', 'givoly' ); ?>">
        </div>

        <?php $render_extra_fields( $form_id, $config->extra_fields ); ?>

        <!-- ── Messages retour ────────────────────────────────────────── -->
        <div class="givoly-form__messages"
             aria-live="polite"
             aria-atomic="true"
             hidden></div>

        <!-- ── Bouton ─────────────────────────────────────────────────── -->
        <div class="givoly-gateway-actions">
        <?php if ( $show_stripe_gateway ) : ?>
        <button type="submit"
                class="givoly-btn givoly-btn--primary givoly-form__submit givoly-gateway-submit is-active"
                data-gateway="stripe"
                data-label="<?php echo esc_attr( $config->button_text ); ?>"
                data-label-amount="<?php esc_attr_e( 'Faire un don de', 'givoly' ); ?>">
            <span class="givoly-btn__text">
                <?php echo esc_html( $config->button_text ); ?>
            </span>
            <span class="givoly-btn__spinner" hidden aria-hidden="true"></span>
        </button>
        <?php endif; ?>

        <?php if ( $show_helloasso_gateway ) : ?>
        <button type="submit"
                class="HaPayButton givoly-form__submit givoly-gateway-submit"
                data-gateway="helloasso">
            <span class="HaPayButtonLogo" aria-hidden="true">HA</span>
            <span class="HaPayButtonLabel"><?php esc_html_e( 'Payer avec HelloAsso*', 'givoly' ); ?></span>
        </button>

        <?php $ha_other_payments_url = \Givoly\Admin\Settings::get_helloasso_other_payments_url(); ?>
        <?php if ( $ha_other_payments_url ) : ?>
            <a href="<?php echo esc_url( $ha_other_payments_url ); ?>" class="givoly-ha-other-payments" target="_blank" rel="noopener"><?php esc_html_e( 'Autres modes de paiements', 'givoly' ); ?></a>
        <?php endif; ?>

        <?php $ha_notice = \Givoly\Admin\Settings::get_helloasso_button_notice(); ?>
        <?php if ( $ha_notice ) : ?>
            <p class="givoly-ha-note"><?php echo esc_html( $ha_notice ); ?></p>
        <?php endif; ?>
        <?php endif; ?>
        </div>


        <?php $gateway_label = $show_stripe_gateway && $show_helloasso_gateway ? 'Stripe / HelloAsso' : ( $show_helloasso_gateway ? 'HelloAsso' : 'Stripe' ); ?>
        <p class="givoly-form__trust">
            <svg class="givoly-form__trust-icon" width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <?php esc_html_e( 'Paiement 100% sécurisé par', 'givoly' ); ?>
            <span class="givoly-form__trust-badge givoly-form__trust-badge--stripe">
                <?php echo esc_html( $gateway_label ); ?>
            </span>
        </p>

        <?php \Givoly\Form\DonationForm::output_branding(); ?>

    </form>
</div>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
