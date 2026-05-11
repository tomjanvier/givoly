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
?>
<div class="givasso-wrap givasso-campaign <?php echo esc_attr( $config->get_wrap_classes() ); ?>"
     style="<?php echo esc_attr( $config->get_inline_css_vars() ); ?>"
     role="region"
     aria-label="<?php echo esc_attr( $campaign->get_title() ); ?>">

    <?php /* ── En-tête ──────────────────────────────────────────────────── */ ?>
    <h2 class="givasso-campaign__title">
        <?php echo esc_html( $campaign->get_title() ); ?>
    </h2>

    <?php if ( $show_description && $campaign->get_description() ) : ?>
        <div class="givasso-campaign__description">
            <?php echo wp_kses_post( $campaign->get_description() ); ?>
        </div>
    <?php endif; ?>

    <?php /* ── Jauge de progression ────────────────────────────────────── */ ?>
    <?php if ( $campaign->has_goal() ) : ?>
        <div class="givasso-campaign__progress"
             role="group"
             aria-label="<?php esc_attr_e( 'Progression de la collecte', 'givasso' ); ?>">

            <div class="givasso-campaign__bar-track">
                <div class="givasso-campaign__bar-fill"
                     role="progressbar"
                     aria-valuenow="<?php echo esc_attr( (int) $percentage ); ?>"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     style="width:<?php echo esc_attr( min( 100, $percentage ) ); ?>%">
                </div>
            </div>

            <div class="givasso-campaign__progress-row">
                <span class="givasso-campaign__collected">
                    <?php echo esc_html( $format_amount( $collected ) ); ?>
                    <span class="givasso-campaign__goal">
                        <?php
                        printf(
                            /* translators: %s: goal amount */
                            esc_html__( 'sur %s', 'givasso' ),
                            esc_html( $format_amount( $campaign->get_goal_amount() ) )
                        );
                        ?>
                    </span>
                </span>
                <span class="givasso-campaign__percentage">
                    <?php echo esc_html( number_format( $percentage, 1 ) ); ?>%
                </span>
            </div>
        </div>

    <?php else : ?>

        <div class="givasso-campaign__total">
            <?php echo esc_html( $format_amount( $collected ) ); ?>
            <?php esc_html_e( 'collectés', 'givasso' ); ?>
        </div>

    <?php endif; ?>

    <?php /* ── Statistiques ────────────────────────────────────────────── */ ?>
    <p class="givasso-campaign__meta">
        <?php
        printf(
            /* translators: %d: number of donors */
            esc_html( _n( '%d donateur', '%d donateurs', $donor_count, 'givasso' ) ),
            (int) $donor_count
        );
        ?>
        <?php if ( $campaign->get_end_date() && ! $is_ended ) : ?>
            &nbsp;·&nbsp;
            <?php
            printf(
                /* translators: %s: end date */
                esc_html__( 'Jusqu\'au %s', 'givasso' ),
                esc_html( $campaign->get_end_date()->format( 'd/m/Y' ) )
            );
            ?>
        <?php endif; ?>
    </p>

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
