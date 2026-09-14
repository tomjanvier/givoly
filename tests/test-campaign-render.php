<?php
// Tests du rendu campagne : contenu complet, show_description, devise non EUR.

require_once __DIR__ . '/stubs.php';
require_once dirname( __DIR__ ) . '/includes/Admin/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Domain/Entities/Campaign.php';
require_once dirname( __DIR__ ) . '/includes/Form/FormConfig.php';

use Givoly\Domain\Entities\Campaign;
use Givoly\Form\FormConfig;

echo "Campaign rendering\n";

if ( ! function_exists( 'Givoly\\Form\\FormConfig_test_bootstrap' ) ) {
    // FormConfig nécessite Settings::get_enabled_gateways ; forcer Stripe+HelloAsso.
    $GLOBALS['givoly_test_options']['givoly_stripe_enabled']    = '1';
    $GLOBALS['givoly_test_options']['givoly_helloasso_enabled'] = '1';
}

/**
 * Rend le template campagne avec des variables injectées.
 */
function givoly_render_campaign_template( Campaign $campaign, float $collected, int $donors, bool $show_description, bool $show_title, $form ): string {
    $percentage   = $campaign->get_progress_percentage( $collected );
    $is_ended     = $campaign->is_ended( new DateTimeImmutable( '2026-04-15 12:00:00' ) );
    $collected    = $collected;
    $donor_count  = $donors;
    $donation_form = $form;
    $config        = new FormConfig( [ 'theme' => 'givoly', 'layout' => 'card' ] );
    $show_description = $show_description;
    $show_title       = $show_title;
    ob_start();
    include dirname( __DIR__ ) . '/templates/campaign/campaign.php';
    return (string) ob_get_clean();
}

$campaign = new Campaign(
    1, 'Urgence hiver', 'urgence-hiver', Campaign::STATUS_ACTIVE, 'EUR',
    'Aidez les familles cet hiver.', 1000.0,
    new DateTimeImmutable( '2026-01-01' ), new DateTimeImmutable( '2026-12-31' ), 42
);

// Rendu complet : titre, description, image, montants, objectif, donateurs, jauge accessible.
$full = givoly_render_campaign_template( $campaign, 250.0, 12, true, true, null );
givoly_assert( str_contains( $full, 'Urgence hiver' ), 'titre rendu' );
givoly_assert( str_contains( $full, 'Aidez les familles' ), 'description rendue si show_description=true' );
givoly_assert( str_contains( $full, 'attachment-42.jpg' ), 'image mise en avant rendue' );
givoly_assert( str_contains( $full, '250' ), 'montant collecte rendu' );
givoly_assert( str_contains( $full, '1 000' ) || str_contains( $full, '1000' ), 'objectif rendu' );
givoly_assert( str_contains( $full, '12' ), 'nombre de donateurs rendu' );
givoly_assert( str_contains( $full, 'role="progressbar"' ), 'jauge accessible avec progressbar' );
givoly_assert( str_contains( $full, 'aria-valuenow="25"' ), 'jauge avec valeur courante' );
givoly_assert( str_contains( $full, 'aria-valuetext' ), 'jauge avec texte de valeur' );

// show_description=false : description masquée, reste visible.
$hidden = givoly_render_campaign_template( $campaign, 250.0, 12, false, true, null );
givoly_assert( ! str_contains( $hidden, 'Aidez les familles' ), 'show_description=false masque la description' );
givoly_assert( str_contains( $hidden, 'Urgence hiver' ), 'titre conserve sans description' );
givoly_assert( str_contains( $hidden, 'role="progressbar"' ), 'jauge conservee sans description' );

// Campagne non EUR : devise héritée et affichée.
$usd = new Campaign( 2, 'US drive', 'us-drive', Campaign::STATUS_ACTIVE, 'USD', 'Help now.', 500.0, null, null, null );
$usd_html = givoly_render_campaign_template( $usd, 100.0, 3, true, true, null );
givoly_assert( str_contains( $usd_html, '$' ), 'campagne USD affiche le symbole $' );
givoly_assert( ! str_contains( $usd_html, '€' ), 'campagne USD sans symbole euro' );
// Le formulaire intégré doit hériter de la devise (vérifié via FormConfig).
$inherited = new FormConfig( [ 'campaign' => 'us-drive', 'currency' => $usd->get_currency() ] );
givoly_assert_same( 'USD', $inherited->currency, 'formulaire integre herite de la devise campagne' );
givoly_assert_same( '$', $inherited->get_currency_symbol(), 'symbole herite correct' );

// Campagne terminée : formulaire remplacé par message accessible.
$ended = new Campaign( 3, 'Close', 'close', Campaign::STATUS_ENDED, 'EUR', 'Desc.', 100.0, null, null, null );
$ended_html = givoly_render_campaign_template( $ended, 100.0, 5, true, true, null );
givoly_assert( str_contains( $ended_html, 'role="status"' ), 'message de fin avec role status' );
