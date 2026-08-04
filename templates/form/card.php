<?php
/**
 * Template : Formulaire de don — Layout "card"
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
$show_post_payment_phone   = \Givoly\Admin\Settings::should_show_post_payment_phone();
$show_post_payment_address = \Givoly\Admin\Settings::should_show_post_payment_address();

$form_id = 'givoly-form-' . wp_unique_id();
$show_stripe_gateway = in_array( $config->gateway, [ 'stripe', 'both' ], true );
$show_helloasso_gateway = in_array( $config->gateway, [ 'helloasso', 'both' ], true );
$default_form_gateway = $show_stripe_gateway ? 'stripe' : 'helloasso';

$symbol = $config->get_currency_symbol();

$wrap_style = $config->get_inline_css_vars();
?>
<div class="givoly-wrap givoly-layout-<?php echo esc_attr( $config->layout ); ?> <?php echo esc_attr( $config->get_wrap_classes() ); ?>"
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


        <!-- ── Fréquence du don ─────────────────────────────────────── -->
        <fieldset class="givoly-form__fieldset givoly-frequency">
            <legend class="givoly-form__legend">
                <?php esc_html_e( 'Fréquence', 'givoly' ); ?>
            </legend>
            <div class="givoly-frequency-toggle" role="group">
                <?php if ( $show_stripe_gateway ) : ?>
                <label class="givoly-frequency-option">
                    <input type="radio" name="frequency" value="monthly" class="givoly-frequency__input" checked>
                    <span class="givoly-frequency-option__label"><?php esc_html_e( 'Don récurrent', 'givoly' ); ?></span>
                </label>
                <?php endif; ?>
                <label class="givoly-frequency-option">
                    <input type="radio" name="frequency" value="once" class="givoly-frequency__input" <?php checked( ! $show_stripe_gateway ); ?>>
                    <span class="givoly-frequency-option__label"><?php esc_html_e( 'Don unique', 'givoly' ); ?></span>
                </label>
            </div>
        </fieldset>

        <!-- ── Sélection du montant ───────────────────────────────────── -->
        <fieldset class="givoly-form__fieldset">
            <legend class="givoly-form__legend">
                <?php esc_html_e( 'Choisissez votre montant', 'givoly' ); ?>
            </legend>

            <div class="givoly-amount-grid" role="group">
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

                <!-- Montant libre -->
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

            <!-- Champ montant libre (affiché uniquement si "Autre" sélectionné) -->
            <div class="givoly-custom-amount" hidden aria-live="polite">
                <label for="<?php echo esc_attr( $form_id ); ?>-custom" class="givoly-label">
                    <?php esc_html_e( 'Montant libre', 'givoly' ); ?>
                </label>
                <div class="givoly-input-group">
                    <span class="givoly-input-group__prefix" aria-hidden="true">
                        <?php echo esc_html( $symbol ); ?>
                    </span>
                    <input type="number"
                           inputmode="numeric"
                           id="<?php echo esc_attr( $form_id ); ?>-custom"
                           name="custom_amount"
                           class="givoly-input givoly-input--amount"
                           min="1"
                           max="100000"
                           step="1"
                           placeholder="<?php esc_attr_e( 'Ex : 75', 'givoly' ); ?>"
                           aria-describedby="<?php echo esc_attr( $form_id ); ?>-amount-hint">
                </div>
                <p id="<?php echo esc_attr( $form_id ); ?>-amount-hint" class="givoly-hint">
                    <?php esc_html_e( 'Minimum 1 €, maximum 100 000 €', 'givoly' ); ?>
                </p>
            </div>

            <!-- Champ caché qui portera le montant final validé -->
            <input type="hidden" name="amount" class="givoly-final-amount">
        </fieldset>

        <!-- ── Informations du donateur ───────────────────────────────── -->
        <fieldset class="givoly-form__fieldset">
            <legend class="givoly-form__legend">
                <?php esc_html_e( 'Vos informations', 'givoly' ); ?>
            </legend>

            <div class="givoly-row">
                <div class="givoly-field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-first-name" class="givoly-label">
                        <?php esc_html_e( 'Prénom', 'givoly' ); ?>
                        <span class="givoly-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="<?php echo esc_attr( $form_id ); ?>-first-name"
                           name="first_name"
                           class="givoly-input"
                           required
                           autocomplete="given-name"
                           maxlength="100">
                </div>

                <div class="givoly-field">
                    <label for="<?php echo esc_attr( $form_id ); ?>-last-name" class="givoly-label">
                        <?php esc_html_e( 'Nom', 'givoly' ); ?>
                        <span class="givoly-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="<?php echo esc_attr( $form_id ); ?>-last-name"
                           name="last_name"
                           class="givoly-input"
                           required
                           autocomplete="family-name"
                           maxlength="100">
                </div>
            </div>

            <div class="givoly-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-email" class="givoly-label">
                    <?php esc_html_e( 'Email', 'givoly' ); ?>
                    <span class="givoly-required" aria-hidden="true">*</span>
                </label>
                <input type="email"
                       id="<?php echo esc_attr( $form_id ); ?>-email"
                       name="email"
                       class="givoly-input"
                       required
                       autocomplete="email"
                       maxlength="254">
            </div>
        </fieldset>

        <?php $extra_fields = $config->extra_fields; include GIVOLY_PLUGIN_DIR . 'templates/form/partials/extra-fields.php'; ?>

        <!-- ── Messages retour (erreur / succès) ──────────────────────── -->
        <div class="givoly-form__messages"
             aria-live="polite"
             aria-atomic="true"
             hidden></div>

        <!-- ── Bouton de soumission ───────────────────────────────────── -->
        <?php $is_card = true; include GIVOLY_PLUGIN_DIR . 'templates/form/partials/gateway-buttons.php'; ?>


        <?php
        $gateway_label = $show_stripe_gateway && $show_helloasso_gateway ? 'Stripe / HelloAsso' : ( $show_helloasso_gateway ? 'HelloAsso' : 'Stripe' );
        ?>
        <div class="givoly-form__trust">
            <svg class="givoly-form__trust-icon" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <?php esc_html_e( 'Paiement 100% sécurisé par', 'givoly' ); ?>
            <span class="givoly-form__trust-badge givoly-form__trust-badge--stripe">
                <?php echo esc_html( $gateway_label ); ?>
            </span>
        </div>

        <?php \Givoly\Form\DonationForm::output_branding(); ?>

    </form>

<?php
$post_payment_token = isset( $_GET['givoly_token'] ) ? sanitize_text_field( wp_unslash( $_GET['givoly_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$givoly_success     = isset( $_GET['givoly_success'] ) ? sanitize_key( wp_unslash( $_GET['givoly_success'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<?php if ( '1' === $givoly_success && $post_payment_token !== '' ) : ?>
        <section class="givoly-post-payment" aria-live="polite">
            <h3 class="givoly-post-payment__title"><?php esc_html_e( 'Complétez votre profil donateur', 'givoly' ); ?></h3>
            <p class="givoly-hint"><?php esc_html_e( 'Merci ! Pour mieux vous accompagner, merci de compléter ces informations.', 'givoly' ); ?></p>
            <form class="givoly-post-payment-form" novalidate>
                <?php wp_nonce_field( 'givoly_submit_donation', 'givoly_nonce' ); ?>
                <input type="hidden" name="post_payment_token" value="<?php echo esc_attr( $post_payment_token ); ?>">
                <div class="givoly-row">
                    <div class="givoly-field">
                        <label class="givoly-label" for="<?php echo esc_attr( $form_id ); ?>-pp-email"><?php esc_html_e( 'Email utilisé pour le paiement', 'givoly' ); ?> *</label>
                        <input class="givoly-input" type="email" required name="email" id="<?php echo esc_attr( $form_id ); ?>-pp-email" maxlength="254">
                    </div>
                    <?php if ( $show_post_payment_phone ) : ?>
                        <div class="givoly-field">
                            <label class="givoly-label" for="<?php echo esc_attr( $form_id ); ?>-pp-phone"><?php esc_html_e( 'Téléphone', 'givoly' ); ?></label>
                            <input class="givoly-input" type="tel" name="phone" id="<?php echo esc_attr( $form_id ); ?>-pp-phone" maxlength="40">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="givoly-field">
                    <label class="givoly-label" for="<?php echo esc_attr( $form_id ); ?>-pp-company"><?php esc_html_e( 'Organisation', 'givoly' ); ?></label>
                    <input class="givoly-input" type="text" name="company" id="<?php echo esc_attr( $form_id ); ?>-pp-company" maxlength="150">
                </div>
                <?php if ( $show_post_payment_address ) : ?>
                    <div class="givoly-field">
                        <label class="givoly-label" for="<?php echo esc_attr( $form_id ); ?>-pp-address"><?php esc_html_e( 'Adresse', 'givoly' ); ?></label>
                        <input class="givoly-input" type="text" name="address_line1" id="<?php echo esc_attr( $form_id ); ?>-pp-address" maxlength="255">
                    </div>
                    <div class="givoly-row">
                        <div class="givoly-field">
                            <label class="givoly-label" for="<?php echo esc_attr( $form_id ); ?>-pp-postal"><?php esc_html_e( 'Code postal', 'givoly' ); ?></label>
                            <input class="givoly-input" type="text" name="postal_code" id="<?php echo esc_attr( $form_id ); ?>-pp-postal" maxlength="10">
                        </div>
                        <div class="givoly-field">
                            <label class="givoly-label" for="<?php echo esc_attr( $form_id ); ?>-pp-city"><?php esc_html_e( 'Ville', 'givoly' ); ?></label>
                            <input class="givoly-input" type="text" name="city" id="<?php echo esc_attr( $form_id ); ?>-pp-city" maxlength="100">
                        </div>
                    </div>
                <?php endif; ?>
                <button type="submit" class="givoly-btn givoly-btn--primary"><?php esc_html_e( 'Enregistrer mes informations', 'givoly' ); ?></button>
                <div class="givoly-form__messages" hidden></div>
            </form>
        </section>
    <?php endif; ?>
</div>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
