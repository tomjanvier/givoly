<?php
/**
 * Template de la page de campagne.
 *
 * Variables injectées par CampaignWidget :
 *   @var \Givasso\Domain\Entities\Campaign $campaign
 *   @var float  $collected    Montant total collecté
 *   @var int    $donor_count  Nombre de donateurs uniques
 *   @var float  $percentage   Pourcentage de progression (0-100)
 *   @var bool   $is_ended     Campagne terminée ou archivée
 *   @var \Givasso\Form\DonationForm|null $donation_form  Formulaire de don (null si campagne terminée)
 *   @var bool   $show_description
 *
 * Pour personnaliser, copier ce fichier dans :
 *   {votre-theme}/givasso/campaign/campaign.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template file, variables are local-scope
$currency_symbol = match ( $campaign->get_currency() ) {
    'USD' => '$',
    'GBP' => '£',
    'CHF' => 'CHF',
    'MAD' => 'DH',
    default => '€',
};

$format_amount = static fn( float $amount ): string =>
    number_format( $amount, 0, ',', ' ' ) . ' ' . $currency_symbol;

$display_title = preg_replace( '/^\s*Don\s+[àa]\s+/iu', '', $campaign->get_title() );
if ( ! is_string( $display_title ) || '' === trim( $display_title ) ) {
    $display_title = $campaign->get_title();
}
?>
<div class="givasso-wrap givasso-campaign <?php echo esc_attr( $config->get_wrap_classes() ); ?>"
     style="<?php echo esc_attr( $config->get_inline_css_vars() ); ?>"
     role="region"
     aria-label="<?php echo esc_attr( $campaign->get_title() ); ?>">

    <?php /* ── En-tête ──────────────────────────────────────────────────── */ ?>
    <h2 class="givasso-campaign__title">
        <?php echo esc_html( $display_title ); ?>
    </h2>

    <?php /* ── Formulaire ou message de fin ─────────────────────────────── */ ?>
    <?php if ( $is_ended ) : ?>
        <div class="givasso-campaign__ended">
            <?php esc_html_e( 'Cette campagne est terminée. Merci pour votre générosité !', 'givasso' ); ?>
        </div>
    <?php elseif ( $donation_form ) : ?>
        <div class="givasso-campaign__form">
            <?php $donation_form->output(); ?>
        </div>
    <?php endif; ?>

</div>
