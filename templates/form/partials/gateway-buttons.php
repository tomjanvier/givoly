<?php
/**
 * Partial : boutons de paiement (Stripe / HelloAsso).
 *
 * Variables en portée d'inclusion :
 *   @var \Givoly\Form\FormConfig $config
 *   @var bool $show_stripe_gateway
 *   @var bool $show_helloasso_gateway
 *   @var bool $is_card   True pour le layout card (logos + variante bouton).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="givoly-gateway-actions">
<?php if ( $show_stripe_gateway ) : ?>
<button type="submit"
        class="givoly-btn givoly-btn--primary givoly-form__submit givoly-gateway-submit is-active<?php echo $is_card ? ' givoly-btn--card' : ''; ?>"
        data-gateway="stripe"
        data-label="<?php echo esc_attr( $config->button_text ); ?>"
        data-label-amount="<?php echo esc_attr( $is_card ? __( 'Payer', 'givoly' ) : __( 'Faire un don de', 'givoly' ) ); ?>">
    <span class="givoly-btn__text">
        <?php echo esc_html( $config->button_text ); ?>
    </span>
    <?php if ( $is_card ) : ?>
    <span class="givoly-payment-logos givoly-payment-logos--in-button" aria-hidden="true">
        <span class="givoly-payment-logos__item">Visa</span>
        <span class="givoly-payment-logos__item">Mastercard</span>
        <span class="givoly-payment-logos__item">Apple Pay</span>
        <span class="givoly-payment-logos__item">Google Pay</span>
        <span class="givoly-payment-logos__item">PayPal</span>
        <span class="givoly-payment-logos__item">SEPA</span>
    </span>
    <?php endif; ?>
    <span class="givoly-btn__spinner" hidden aria-hidden="true"></span>
</button>
<?php endif; ?>

<?php if ( $show_helloasso_gateway ) : ?>
<button type="submit"
        class="HaPayButton givoly-form__submit givoly-gateway-submit"
        data-gateway="helloasso">
    <span class="HaPayButtonLogoWrap"><img class="HaPayButtonLogo" src="<?php echo esc_url( GIVOLY_PLUGIN_URL . 'assets/logo-ha.svg' ); ?>" alt="" loading="lazy" decoding="async"></span>
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
