<?php
/**
 * Page admin de gestion des campagnes.
 *
 * Modes :
 *  - liste   : GET  /wp-admin/admin.php?page=givoly-campaigns
 *  - création : GET  ?page=givoly-campaigns&action=new
 *  - édition  : GET  ?page=givoly-campaigns&action=edit&id=X
 *  - archive  : GET  ?page=givoly-campaigns&action=archive&id=X&_wpnonce=Y
 *  - save     : POST ?page=givoly-campaigns
 *
 * @package Givoly\Admin\Pages
 */

namespace Givoly\Admin\Pages;

use Givoly\Domain\Entities\Campaign;
use Givoly\Repository\CampaignRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CampaignsPage {

    private CampaignRepository $repo;

    public function __construct() {
        $this->repo = new CampaignRepository();
    }

    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_page_assets' ] );
    }

    public function enqueue_page_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'givoly' ) ) {
            return;
        }
        $js = <<<'JS'
( function() {
    var titleInput = document.getElementById( 'givoly-title' );
    var slugInput  = document.getElementById( 'givoly-slug' );
    if ( ! titleInput || ! slugInput ) return;

    titleInput.addEventListener( 'input', function() {
        if ( slugInput.dataset.edited ) return;
        slugInput.value = titleInput.value
            .toLowerCase()
            .normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
            .replace( /[^a-z0-9]+/g, '-' )
            .replace( /^-+|-+$/g, '' );
    } );

    slugInput.addEventListener( 'input', function() {
        slugInput.dataset.edited = '1';
    } );
} )();
JS;
        wp_add_inline_script( 'givoly-admin', $js );
    }

    /**
     * Appelé par load-{hook} avant tout output WordPress.
     * Gère les actions POST (save) et GET (archive) qui font des redirections.
     */
    public function handle_early(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $action === 'archive' ) {
            $this->handle_archive();
            return;
        }

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['givoly_campaign_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified inside handle_save()
            $this->handle_save();
        }
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'givoly' ) );
        }

        $action = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $action === 'new' ) {
            $this->render_form( null );
        } elseif ( $action === 'edit' ) {
            $id       = absint( wp_unslash( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $campaign = $id ? $this->repo->find_by_id( $id ) : null;
            if ( ! $campaign ) {
                wp_die( esc_html__( 'Campagne introuvable.', 'givoly' ) );
            }
            $this->render_form( $campaign );
        } else {
            $this->render_list();
        }
    }

    // ── Handlers ───────────────────────────────────────────────────────────

    private function handle_archive(): void {
        $id = absint( wp_unslash( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by check_admin_referer below

        if ( ! $id || ! check_admin_referer( 'givoly_archive_campaign_' . $id ) ) {
            wp_die( esc_html__( 'Action invalide.', 'givoly' ) );
        }

        if ( ! $this->repo->find_by_id( $id ) ) {
            wp_die( esc_html__( 'Campagne introuvable.', 'givoly' ) );
        }

        $this->repo->archive( $id );

        wp_safe_redirect( add_query_arg( [ 'page' => 'givoly-campaigns', 'givoly_archived' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function handle_save(): void {
        if ( ! check_admin_referer( 'givoly_save_campaign', 'givoly_campaign_nonce' ) ) {
            wp_die( esc_html__( 'Requête invalide.', 'givoly' ) );
        }

        $id          = (int) wp_unslash( $_POST['campaign_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval cast is sufficient sanitization for an integer ID
        $title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $slug        = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
        $description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
        $goal_raw    = sanitize_text_field( wp_unslash( $_POST['goal_amount'] ?? '' ) );
        $goal_amount = $goal_raw !== '' ? abs( (float) $goal_raw ) : null;
        $currency    = sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'EUR' ) );
        $start_raw   = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
        $end_raw     = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
        $status      = sanitize_key( $_POST['status'] ?? Campaign::STATUS_DRAFT );

        if ( ! $title ) {
            $this->redirect_with_error( $id, 'title_required' );
            return;
        }

        if ( ! $slug ) {
            $slug = sanitize_title( $title );
        }

        // Vérifier l'unicité du slug avant d'insérer / mettre à jour
        if ( $this->repo->slug_exists( $slug, $id ) ) {
            $this->redirect_with_error( $id, 'slug_exists' );
            return;
        }

        $valid_statuses = [ Campaign::STATUS_DRAFT, Campaign::STATUS_ACTIVE, Campaign::STATUS_ENDED, Campaign::STATUS_ARCHIVED ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $status = Campaign::STATUS_DRAFT;
        }

        $valid_currencies = [ 'EUR', 'USD', 'GBP', 'CHF', 'MAD' ];
        if ( ! in_array( $currency, $valid_currencies, true ) ) {
            $currency = 'EUR';
        }

        try {
            $start_date = $start_raw ? new \DateTimeImmutable( $start_raw ) : null;
            $end_date   = $end_raw   ? new \DateTimeImmutable( $end_raw )   : null;
        } catch ( \Exception $e ) {
            $start_date = null;
            $end_date   = null;
        }

        $campaign = new Campaign(
            id:          $id,
            title:       $title,
            slug:        $slug,
            status:      $status,
            currency:    $currency,
            description: $description ?: null,
            goal_amount: $goal_amount,
            start_date:  $start_date,
            end_date:    $end_date,
        );

        $saved = $this->repo->save( $campaign );

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'givoly-campaigns', 'givoly_saved' => '1', 'id' => $saved->get_id() ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    // ── Vues ───────────────────────────────────────────────────────────────

    private function render_list(): void {
        $campaigns = $this->repo->find_all();

        // Une seule requête agrégée pour toutes les stats — pas de N+1
        $ids        = array_map( fn( $c ) => $c->get_id(), $campaigns );
        $stats_map  = $this->repo->get_stats_batch( $ids );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Campagnes', 'givoly' ); ?></h1>
            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'givoly-campaigns', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>"
               class="page-title-action"><?php esc_html_e( 'Ajouter', 'givoly' ); ?></a>
            <hr class="wp-header-end">

            <?php if ( isset( $_GET['givoly_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Campagne enregistrée.', 'givoly' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['givoly_archived'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Campagne archivée.', 'givoly' ); ?></p>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Titre', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Objectif', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Collecté', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Progression', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Statut', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Fin', 'givoly' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'givoly' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $campaigns ) ) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e( 'Aucune campagne.', 'givoly' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $campaigns as $c ) :
                            $stats     = $stats_map[ $c->get_id() ] ?? [ 'amount' => 0.0, 'donors' => 0 ];
                            $collected = $stats['amount'];
                            $pct       = $c->get_progress_percentage( $collected );
                            $edit_url  = add_query_arg( [ 'page' => 'givoly-campaigns', 'action' => 'edit', 'id' => $c->get_id() ], admin_url( 'admin.php' ) );
                            $archive_url = wp_nonce_url(
                                add_query_arg( [ 'page' => 'givoly-campaigns', 'action' => 'archive', 'id' => $c->get_id() ], admin_url( 'admin.php' ) ),
                                'givoly_archive_campaign_' . $c->get_id()
                            );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $c->get_title() ); ?></strong></td>
                            <td><code><?php echo esc_html( $c->get_slug() ); ?></code></td>
                            <td>
                                <?php echo $c->has_goal()
                                    ? esc_html( number_format( $c->get_goal_amount(), 0, ',', ' ' ) . ' ' . $c->get_currency() )
                                    : '—'; ?>
                            </td>
                            <td><?php echo esc_html( number_format( $collected, 0, ',', ' ' ) . ' ' . $c->get_currency() ); ?></td>
                            <td>
                                <?php if ( $c->has_goal() ) : ?>
                                    <div class="givoly-progress-track">
                                        <div class="givoly-progress-fill" style="width:<?php echo esc_attr( min( 100, $pct ) ); ?>%"></div>
                                    </div>
                                    <small><?php echo esc_html( number_format( $pct, 1 ) ); ?>%</small>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $now = new \DateTimeImmutable();
                                if ( $c->is_ended( $now ) && $c->get_status() === Campaign::STATUS_ACTIVE ) {
                                    // Date dépassée mais statut DB encore "active" — afficher l'état réel
                                    echo '<span class="givoly-status--overdue" title="' . esc_attr__( 'Date de fin dépassée', 'givoly' ) . '">'
                                        . esc_html__( 'Terminée', 'givoly' )
                                        . '</span>';
                                } else {
                                    $labels = [
                                        Campaign::STATUS_DRAFT    => __( 'Brouillon', 'givoly' ),
                                        Campaign::STATUS_ACTIVE   => __( 'Active', 'givoly' ),
                                        Campaign::STATUS_ENDED    => __( 'Terminée', 'givoly' ),
                                        Campaign::STATUS_ARCHIVED => __( 'Archivée', 'givoly' ),
                                    ];
                                    echo esc_html( $labels[ $c->get_status() ] ?? $c->get_status() );
                                }
                                ?>
                            </td>
                            <td><?php echo $c->get_end_date() ? esc_html( $c->get_end_date()->format( 'd/m/Y' ) ) : '—'; ?></td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Modifier', 'givoly' ); ?></a>
                                <?php if ( $c->get_status() !== Campaign::STATUS_ARCHIVED ) : ?>
                                    &nbsp;|&nbsp;
                                    <a href="<?php echo esc_url( $archive_url ); ?>"
                                       onclick="return confirm('<?php esc_attr_e( 'Archiver cette campagne ?', 'givoly' ); ?>')">
                                        <?php esc_html_e( 'Archiver', 'givoly' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_form( ?Campaign $campaign ): void {
        $is_edit = $campaign !== null;
        $title   = __( 'Nouvelle campagne', 'givoly' );
        if ( $is_edit ) {
            /* translators: %s: campaign title */
            $title = sprintf( __( 'Modifier : %s', 'givoly' ), $campaign->get_title() );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <a href="<?php echo esc_url( add_query_arg( 'page', 'givoly-campaigns', admin_url( 'admin.php' ) ) ); ?>">
                &larr; <?php esc_html_e( 'Retour à la liste', 'givoly' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ( isset( $_GET['givoly_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-error">
                    <p>
                    <?php
                    match ( sanitize_key( $_GET['givoly_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        'slug_exists'    => esc_html_e( 'Ce slug est déjà utilisé par une autre campagne.', 'givoly' ),
                        default          => esc_html_e( 'Le titre est obligatoire.', 'givoly' ),
                    };
                    ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( add_query_arg( 'page', 'givoly-campaigns', admin_url( 'admin.php' ) ) ); ?>">
                <?php wp_nonce_field( 'givoly_save_campaign', 'givoly_campaign_nonce' ); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $is_edit ? $campaign->get_id() : 0 ); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="givoly-title"><?php esc_html_e( 'Titre *', 'givoly' ); ?></label></th>
                        <td>
                            <input type="text" id="givoly-title" name="title" class="regular-text"
                                   value="<?php echo esc_attr( $is_edit ? $campaign->get_title() : '' ); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="givoly-slug"><?php esc_html_e( 'Slug', 'givoly' ); ?></label></th>
                        <td>
                            <input type="text" id="givoly-slug" name="slug" class="regular-text"
                                   value="<?php echo esc_attr( $is_edit ? $campaign->get_slug() : '' ); ?>"
                                   pattern="[a-z0-9\-]+" title="<?php esc_attr_e( 'Minuscules, chiffres et tirets uniquement.', 'givoly' ); ?>">
                            <p class="description"><?php esc_html_e( 'Identifiant utilisé dans le shortcode [givoly_campaign campaign="slug"]. Auto-généré depuis le titre si vide.', 'givoly' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="givoly-description"><?php esc_html_e( 'Description', 'givoly' ); ?></label></th>
                        <td>
                            <textarea id="givoly-description" name="description" rows="5" class="large-text"><?php
                                echo esc_textarea( $is_edit ? ( $campaign->get_description() ?? '' ) : '' );
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="givoly-goal"><?php esc_html_e( 'Objectif de collecte', 'givoly' ); ?></label></th>
                        <td>
                            <input type="number" id="givoly-goal" name="goal_amount" class="small-text"
                                   min="0" step="0.01"
                                   value="<?php echo esc_attr( $is_edit && $campaign->get_goal_amount() !== null ? $campaign->get_goal_amount() : '' ); ?>">
                            <p class="description"><?php esc_html_e( 'Laisser vide pour une collecte sans objectif.', 'givoly' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="givoly-currency"><?php esc_html_e( 'Devise', 'givoly' ); ?></label></th>
                        <td>
                            <select id="givoly-currency" name="currency">
                                <?php foreach ( [ 'EUR', 'USD', 'GBP', 'CHF', 'MAD' ] as $cur ) : ?>
                                    <option value="<?php echo esc_attr( $cur ); ?>"
                                        <?php selected( $is_edit ? $campaign->get_currency() : 'EUR', $cur ); ?>>
                                        <?php echo esc_html( $cur ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Dates', 'givoly' ); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e( 'Début :', 'givoly' ); ?>
                                <input type="date" name="start_date"
                                       value="<?php echo esc_attr( $is_edit && $campaign->get_start_date() ? $campaign->get_start_date()->format( 'Y-m-d' ) : '' ); ?>">
                            </label>
                            &nbsp;&nbsp;
                            <label>
                                <?php esc_html_e( 'Fin :', 'givoly' ); ?>
                                <input type="date" name="end_date"
                                       value="<?php echo esc_attr( $is_edit && $campaign->get_end_date() ? $campaign->get_end_date()->format( 'Y-m-d' ) : '' ); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="givoly-status"><?php esc_html_e( 'Statut', 'givoly' ); ?></label></th>
                        <td>
                            <select id="givoly-status" name="status">
                                <?php
                                $statuses = [
                                    Campaign::STATUS_DRAFT    => __( 'Brouillon', 'givoly' ),
                                    Campaign::STATUS_ACTIVE   => __( 'Active', 'givoly' ),
                                    Campaign::STATUS_ENDED    => __( 'Terminée', 'givoly' ),
                                    Campaign::STATUS_ARCHIVED => __( 'Archivée', 'givoly' ),
                                ];
                                foreach ( $statuses as $val => $label ) :
                                ?>
                                    <option value="<?php echo esc_attr( $val ); ?>"
                                        <?php selected( $is_edit ? $campaign->get_status() : Campaign::STATUS_DRAFT, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $is_edit ? esc_html__( 'Mettre à jour', 'givoly' ) : esc_html__( 'Créer la campagne', 'givoly' ); ?>
                    </button>
                </p>
            </form>

        </div>
        <?php
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function redirect_with_error( int $id, string $error ): void {
        $args = [ 'page' => 'givoly-campaigns', 'givoly_error' => $error ];
        if ( $id ) {
            $args['action'] = 'edit';
            $args['id']     = $id;
        } else {
            $args['action'] = 'new';
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
