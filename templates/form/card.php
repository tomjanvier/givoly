<?php
/**
 * Template : Formulaire de don — Layout "card"
 *
 * Variables disponibles (injectées par DonationForm::load_template) :
 *
 * @var \Givasso\Form\FormConfig $config
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$show_post_payment_phone   = \Givasso\Admin\Settings::should_show_post_payment_phone();
$show_post_payment_address = \Givasso\Admin\Settings::should_show_post_payment_address();

// ── Helpers locaux ──────────────────────────────────────────────────────────
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template file, variables are local-scope

$form_id = 'givasso-form-' . wp_unique_id();

$symbol = $config->get_currency_symbol();

$wrap_style = $config->get_inline_css_vars();

$render_extra_fields = static function ( string $form_id, array $extra_fields ): void {
    foreach ( $extra_fields as $field ) {
        if ( $field === 'phone' ) { ?>
            <div class="givasso-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-phone" class="givasso-label"><?php esc_html_e( 'Téléphone', 'givasso' ); ?></label>
                <input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone" class="givasso-input" maxlength="40" autocomplete="tel">
            </div>
        <?php } elseif ( $field === 'company' ) { ?>
            <div class="givasso-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-company" class="givasso-label"><?php esc_html_e( 'Organisation', 'givasso' ); ?></label>
                <input type="text" id="<?php echo esc_attr( $form_id ); ?>-company" name="company" class="givasso-input" maxlength="120" autocomplete="organization">
            </div>
        <?php } elseif ( $field === 'message' ) { ?>
            <div class="givasso-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-message" class="givasso-label"><?php esc_html_e( 'Message', 'givasso' ); ?></label>
                <textarea id="<?php echo esc_attr( $form_id ); ?>-message" name="message" class="givasso-input givasso-textarea" rows="3" maxlength="500"></textarea>
            </div>
        <?php }
    }
};
?>
<div class="givasso-wrap givasso-layout-<?php echo esc_attr( $config->layout ); ?> <?php echo esc_attr( $config->get_wrap_classes() ); ?>"
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

        <input type="hidden" name="frequency" value="monthly">

        <?php if ( $config->campaign ) : ?>
            <input type="hidden" name="campaign" value="<?php echo esc_attr( $config->campaign ); ?>">
        <?php endif; ?>


        <div class="givasso-frequency-wrap" role="group" aria-label="<?php esc_attr_e( 'Fréquence du don', 'givasso' ); ?>">
            <button type="button" class="givasso-freq-btn active" data-freq="monthly">
                <?php esc_html_e( 'Don récurrent', 'givasso' ); ?>
            </button>
            <button type="button" class="givasso-freq-btn" data-freq="once">
                <?php esc_html_e( 'Don unique', 'givasso' ); ?>
            </button>
        </div>

        <!-- ── Sélection du montant ───────────────────────────────────── -->
        <fieldset class="givasso-form__fieldset">
            <legend class="givasso-form__legend">
                <?php esc_html_e( 'Choisissez votre montant', 'givasso' ); ?>
            </legend>

            <div class="givasso-amount-grid" role="group">
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

                <!-- Montant libre -->
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

            <!-- Champ montant libre (affiché uniquement si "Autre" sélectionné) -->
            <div class="givasso-custom-amount" hidden aria-live="polite">
                <label for="<?php echo esc_attr( $form_id ); ?>-custom" class="givasso-label">
                    <?php esc_html_e( 'Montant libre', 'givasso' ); ?>
                </label>
                <div class="givasso-input-group">
                    <span class="givasso-input-group__prefix" aria-hidden="true">
                        <?php echo esc_html( $symbol ); ?>
                    </span>
                    <input type="number"
                           inputmode="numeric"
                           id="<?php echo esc_attr( $form_id ); ?>-custom"
                           name="custom_amount"
                           class="givasso-input givasso-input--amount"
                           min="1"
                           max="100000"
                           step="1"
                           placeholder="<?php esc_attr_e( 'Ex : 75', 'givasso' ); ?>"
                           aria-describedby="<?php echo esc_attr( $form_id ); ?>-amount-hint">
                </div>
                <p id="<?php echo esc_attr( $form_id ); ?>-amount-hint" class="givasso-hint">
                    <?php esc_html_e( 'Minimum 1 €, maximum 100 000 €', 'givasso' ); ?>
                </p>
            </div>

            <!-- Champ caché qui portera le montant final validé -->
            <input type="hidden" name="amount" class="givasso-final-amount">
        </fieldset>

        <!-- ── Informations du donateur ───────────────────────────────── -->
        <fieldset class="givasso-form__fieldset">
            <legend class="givasso-form__legend">
                <?php esc_html_e( 'Vos informations', 'givasso' ); ?>
            </legend>

            <div class="givasso-row">
                <div class="givasso-field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-first-name" class="givasso-label">
                        <?php esc_html_e( 'Prénom', 'givasso' ); ?>
                        <span class="givasso-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="<?php echo esc_attr( $form_id ); ?>-first-name"
                           name="first_name"
                           class="givasso-input"
                           required
                           autocomplete="given-name"
                           maxlength="100">
                </div>

                <div class="givasso-field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-last-name" class="givasso-label">
                        <?php esc_html_e( 'Nom', 'givasso' ); ?>
                        <span class="givasso-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="<?php echo esc_attr( $form_id ); ?>-last-name"
                           name="last_name"
                           class="givasso-input"
                           required
                           autocomplete="family-name"
                           maxlength="100">
                </div>
            </div>

            <div class="givasso-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-email" class="givasso-label">
                    <?php esc_html_e( 'Email', 'givasso' ); ?>
                    <span class="givasso-required" aria-hidden="true">*</span>
                </label>
                <input type="email"
                       id="<?php echo esc_attr( $form_id ); ?>-email"
                       name="email"
                       class="givasso-input"
                       required
                       autocomplete="email"
                       maxlength="254">
            </div>
        </fieldset>

        <?php $render_extra_fields( $form_id, $config->extra_fields ); ?>

        <!-- ── Messages retour (erreur / succès) ──────────────────────── -->
        <div class="givasso-form__messages"
             aria-live="polite"
             aria-atomic="true"
             hidden></div>

        <!-- ── Bouton de soumission ───────────────────────────────────── -->
        <div class="givasso-gateway-actions">
        <button type="submit"
                class="givasso-btn givasso-btn--primary givasso-form__submit givasso-gateway-submit is-active givasso-btn--card"
                data-gateway="stripe"
                data-label="<?php echo esc_attr( $config->button_text ); ?>"
                data-label-amount="<?php esc_attr_e( 'Payer', 'givasso' ); ?>">
            <span class="givasso-btn__text">
                <?php echo esc_html( $config->button_text ); ?>
            </span>
            <span class="givasso-payment-logos givasso-payment-logos--in-button" aria-hidden="true">
                <span class="givasso-payment-logos__item">Visa</span>
                <span class="givasso-payment-logos__item">Mastercard</span>
                <span class="givasso-payment-logos__item">Apple Pay</span>
                <span class="givasso-payment-logos__item">Google Pay</span>
                <span class="givasso-payment-logos__item">PayPal</span>
                <span class="givasso-payment-logos__item">SEPA</span>
            </span>
            <span class="givasso-btn__spinner" hidden aria-hidden="true"></span>
        </button>



        <button type="submit"
                class="HaPayButton givasso-gateway-submit"
                data-gateway="helloasso">
            <span class="HaPayButtonLogoWrap"><img src="https://api.helloasso.com/v5/img/logo-ha.svg" alt="" class="HaPayButtonLogo" /></span>
            <span class="HaPayButtonLabel"><?php esc_html_e( 'Payer avec HelloAsso*', 'givasso' ); ?></span>
        </button>

        <?php $ha_other_payments_url = \Givasso\Admin\Settings::get_helloasso_other_payments_url(); ?>
        <?php if ( $ha_other_payments_url ) : ?>
            <a href="<?php echo esc_url( $ha_other_payments_url ); ?>" class="givasso-ha-other-payments" target="_blank" rel="noopener"><?php esc_html_e( 'Autres modes de paiements', 'givasso' ); ?></a>
        <?php endif; ?>

        <?php $ha_notice = \Givasso\Admin\Settings::get_helloasso_button_notice(); ?>
        <?php if ( $ha_notice ) : ?>
            <p class="givasso-ha-note"><?php echo esc_html( $ha_notice ); ?></p>
        <?php endif; ?>
        </div>

        <?php if ( $config->gateway === 'helloasso' ) : ?>
            <?php $ha_monthly_url = \Givasso\Admin\Settings::get_helloasso_monthly_url(); ?>
            <div class="givasso-ha-monthly" hidden>
                <?php if ( $ha_monthly_url ) : ?>
                    <a class="HaPayButton" href="<?php echo esc_url( $ha_monthly_url ); ?>" target="_blank" rel="noopener">
                        <span class="HaPayButtonLogoWrap"><img src="https://api.helloasso.com/v5/img/logo-ha.svg" alt="" class="HaPayButtonLogo" /></span>
                        <span class="HaPayButtonLabel"><?php esc_html_e( 'Payer avec HelloAsso', 'givasso' ); ?></span>
                    </a>
                <?php else : ?>
                    <p class="givasso-hint"><?php esc_html_e( 'Le lien mensuel HelloAsso n\'est pas configuré.', 'givasso' ); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <?php
        $gateway_label = 'Stripe / HelloAsso';
        ?>
        <div class="givasso-form__trust">
            <svg class="givasso-form__trust-icon" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <?php esc_html_e( 'Paiement 100% sécurisé par', 'givasso' ); ?>
            <span class="givasso-form__trust-badge givasso-form__trust-badge--stripe">
                <?php echo esc_html( $gateway_label ); ?>
            </span>
        </div>

    </form>

    <?php if ( isset( $_GET['givasso_success'] ) && '1' === wp_unslash( $_GET['givasso_success'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <section class="givasso-post-payment" aria-live="polite">
            <h3 class="givasso-post-payment__title"><?php esc_html_e( 'Complétez votre profil donateur', 'givasso' ); ?></h3>
            <p class="givasso-hint"><?php esc_html_e( 'Merci ! Pour mieux vous accompagner, merci de compléter ces informations.', 'givasso' ); ?></p>
            <form class="givasso-post-payment-form" novalidate>
                <div class="givasso-row">
                    <div class="givasso-field">
                        <label class="givasso-label" for="<?php echo esc_attr( $form_id ); ?>-pp-email"><?php esc_html_e( 'Email utilisé pour le paiement', 'givasso' ); ?> *</label>
                        <input class="givasso-input" type="email" required name="email" id="<?php echo esc_attr( $form_id ); ?>-pp-email" maxlength="254">
                    </div>
                    <?php if ( $show_post_payment_phone ) : ?>
                        <div class="givasso-field">
                            <label class="givasso-label" for="<?php echo esc_attr( $form_id ); ?>-pp-phone"><?php esc_html_e( 'Téléphone', 'givasso' ); ?></label>
                            <input class="givasso-input" type="tel" name="phone" id="<?php echo esc_attr( $form_id ); ?>-pp-phone" maxlength="40">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="givasso-field">
                    <label class="givasso-label" for="<?php echo esc_attr( $form_id ); ?>-pp-company"><?php esc_html_e( 'Organisation', 'givasso' ); ?></label>
                    <input class="givasso-input" type="text" name="company" id="<?php echo esc_attr( $form_id ); ?>-pp-company" maxlength="150">
                </div>
                <?php if ( $show_post_payment_address ) : ?>
                    <div class="givasso-field">
                        <label class="givasso-label" for="<?php echo esc_attr( $form_id ); ?>-pp-address"><?php esc_html_e( 'Adresse', 'givasso' ); ?></label>
                        <input class="givasso-input" type="text" name="address_line1" id="<?php echo esc_attr( $form_id ); ?>-pp-address" maxlength="255">
                    </div>
                    <div class="givasso-row">
                        <div class="givasso-field">
                            <label class="givasso-label" for="<?php echo esc_attr( $form_id ); ?>-pp-postal"><?php esc_html_e( 'Code postal', 'givasso' ); ?></label>
                            <input class="givasso-input" type="text" name="postal_code" id="<?php echo esc_attr( $form_id ); ?>-pp-postal" maxlength="10">
                        </div>
                        <div class="givasso-field">
                            <label class="givasso-label" for="<?php echo esc_attr( $form_id ); ?>-pp-city"><?php esc_html_e( 'Ville', 'givasso' ); ?></label>
                            <input class="givasso-input" type="text" name="city" id="<?php echo esc_attr( $form_id ); ?>-pp-city" maxlength="100">
                        </div>
                    </div>
                <?php endif; ?>
                <button type="submit" class="givasso-btn givasso-btn--primary"><?php esc_html_e( 'Enregistrer mes informations', 'givasso' ); ?></button>
                <div class="givasso-form__messages" hidden></div>
            </form>
        </section>
    <?php endif; ?>
</div>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
