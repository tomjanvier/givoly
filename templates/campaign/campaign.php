<?php
/**
 * Template de la page de campagne.
 *
 * Variables injectées par CampaignWidget :
 *   @var \Givoly\Domain\Entities\Campaign $campaign
 *   @var float  $collected    Amount total collecté (devise de la campagne)
 *   @var int    $donor_count  Nombre de donateurs uniques
 *   @var float  $percentage   Pourcentage de progression (0-100)
 *   @var bool   $is_ended     Campaign terminée ou archivée
 *   @var \Givoly\Form\DonationForm|null $donation_form  Donation form (null si campagne fermée)
 *   @var bool   $show_description
 *   @var bool   $show_title
 *
 * Pour personnaliser, copier ce fichier dans :
 *   {votre-theme}/givoly/campaign/campaign.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template file, variables are local-scope
$currency_symbol = \Givoly\Form\FormConfig::currency_symbol( $campaign->get_currency() );

$format_amount = static fn( float $amount ): string =>
    number_format( $amount, 0, ',', ' ' ) . ' ' . $currency_symbol;

$display_title = $campaign->get_title();
$description = $campaign->get_description();
$goal = $campaign->get_goal_amount();
$featured_image_id = $campaign->get_featured_image();
?>
<div class="givoly-wrap givoly-campaign <?php echo esc_attr( $config->get_wrap_classes() ); ?>"
     style="<?php echo esc_attr( $config->get_inline_css_vars() ); ?>"
     role="region"
     aria-label="<?php echo esc_attr( $display_title ); ?>">

    <?php /* ── En-tête ──────────────────────────────────────────────────── */ ?>
    <?php if ( $show_title ) : ?>
        <h2 class="givoly-campaign__title">
            <?php echo esc_html( $display_title ); ?>
        </h2>
    <?php endif; ?>

    <?php /* ── Image mise en avant (si configurée) ──────────────────────── */ ?>
    <?php if ( $featured_image_id ) : ?>
        <div class="givoly-campaign__image">
            <?php echo wp_get_attachment_image( $featured_image_id, 'large', false, [ 'class' => 'givoly-campaign__img', 'alt' => $display_title ] ); ?>
        </div>
    <?php endif; ?>

    <?php /* ── Description (si demandée) ────────────────────────────────── */ ?>
    <?php if ( $show_description && $description !== null && trim( $description ) !== '' ) : ?>
        <div class="givoly-campaign__description">
            <?php echo wp_kses_post( $description ); ?>
        </div>
    <?php endif; ?>

    <?php /* ── Jauge accessible ─────────────────────────────────────────── */ ?>
    <div class="givoly-campaign__stats" aria-live="polite">
        <p class="givoly-campaign__collected">
            <strong><?php echo esc_html( $format_amount( $collected ) ); ?></strong>
            <?php if ( $goal !== null && $goal > 0 ) : ?>
                <span class="givoly-campaign__goal">
                    <?php
                    /* translators: %s: campaign goal amount with currency. */
                    printf( esc_html__( 'collected of %s goal', 'givoly' ), esc_html( $format_amount( $goal ) ) );
                    ?>
                </span>
            <?php else : ?>
                <span class="givoly-campaign__goal"><?php esc_html_e( 'collected', 'givoly' ); ?></span>
            <?php endif; ?>
        </p>
        <p class="givoly-campaign__donors">
            <?php
            /* translators: %d: number of donors. */
            printf( esc_html( _n( '%d donor', '%d donors', $donor_count, 'givoly' ) ), esc_html( (string) $donor_count ) );
            ?>
        </p>
        <?php if ( $goal !== null && $goal > 0 ) : ?>
            <div class="givoly-campaign__bar" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $percentage ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuetext="<?php echo esc_attr( $percentage . '%' ); ?>" aria-label="<?php esc_attr_e( 'Campaign progress', 'givoly' ); ?>">
                <div class="givoly-campaign__bar-fill" style="width:<?php echo esc_attr( (string) min( 100, $percentage ) ); ?>%"></div>
            </div>
        <?php endif; ?>
    </div>

    <?php /* ── Formulaire ou message de fin ─────────────────────────────── */ ?>
    <?php if ( $is_ended ) : ?>
        <div class="givoly-campaign__ended" role="status">
            <?php esc_html_e( 'This campaign has ended. Thank you for your generosity!', 'givoly' ); ?>
        </div>
    <?php elseif ( $donation_form ) : ?>
        <div class="givoly-campaign__form">
            <?php $donation_form->output(); ?>
        </div>
    <?php else : ?>
        <div class="givoly-campaign__ended" role="status">
            <?php esc_html_e( 'This campaign is not accepting donations at the moment.', 'givoly' ); ?>
        </div>
    <?php endif; ?>

</div>
