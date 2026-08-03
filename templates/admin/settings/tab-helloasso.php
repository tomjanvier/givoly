<?php
/**
 * Onglet « HelloAsso » de la page Réglages Givoly.
 *
 * Partiel inclus par SettingsPage::render() — les variables sont celles
 * définies dans render() (portée d'inclusion conservée).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : HELLOASSO
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'helloasso' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card givoly-card--ha">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-heart"></span>
                            HelloAsso
                            <?php if ( $ha_ok ) : ?>
                                <span class="givoly-badge givoly-badge--ok givoly-badge--title">✓ Configuré</span>
                            <?php else : ?>
                                <span class="givoly-badge givoly-badge--warn givoly-badge--title">Non configuré</span>
                            <?php endif; ?>
                        </h2>
                        <p class="description">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: %s is the HelloAsso API documentation URL. */
                                    __( 'Documentation API HelloAsso : <a href="%s" target="_blank" rel="noopener noreferrer">dev.helloasso.com/docs</a>.', 'givoly' ),
                                    esc_url( 'https://dev.helloasso.com/docs' )
                                )
                            );
                            ?>
                        </p>

                        <table class="form-table" role="presentation">

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Mode', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-mode-toggle">
                                        <label class="givoly-mode-toggle__option <?php echo esc_attr( $ha_mode === 'sandbox' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="ha_mode" value="sandbox"
                                                <?php checked( $ha_mode, 'sandbox' ); ?>>
                                            <?php esc_html_e( 'Sandbox', 'givoly' ); ?>
                                        </label>
                                        <label class="givoly-mode-toggle__option givoly-mode-toggle__option--live <?php echo esc_attr( $ha_mode === 'live' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="ha_mode" value="live"
                                                <?php checked( $ha_mode, 'live' ); ?>>
                                            <?php esc_html_e( 'Live', 'givoly' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Slug organisation', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="ha_org_slug"
                                           value="<?php echo esc_attr( $ha_org_slug ); ?>"
                                           class="regular-text" placeholder="mon-association">
                                    <p class="description">
                                        <?php esc_html_e( 'Identifiant de votre organisation dans l\'URL HelloAsso.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Identifiants API', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row">Client ID</th>
                                <td>
                                    <?php $this->secret_field( 'ha_client_id', $has_ha_client_id, '' ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Client Secret</th>
                                <td>
                                    <?php $this->secret_field( 'ha_client_secret', $has_ha_secret, '' ); ?>
                                </td>
                            </tr>


                            <tr>
                                <th scope="row"><?php esc_html_e( 'Lien autres modes de paiements', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="ha_other_payments_url"
                                           value="<?php echo esc_attr( $ha_other_payments_url ); ?>"
                                           class="regular-text" placeholder="https://...">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Forcer les dons uniques via lien externe', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ha_once_use_other_payments_url" value="1" <?php checked( $ha_once_use_other_payments_url ); ?>>
                                        <?php esc_html_e( 'Utiliser le lien “autres modes de paiements” au lieu de l\'API HelloAsso pour les dons uniques.', 'givoly' ); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Texte sous bouton HelloAsso', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="ha_button_notice"
                                           value="<?php echo esc_attr( $ha_button_notice ); ?>"
                                           class="regular-text" placeholder="* Exemple de mention">
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Webhook', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'URL Webhook', 'givoly' ); ?></th>
                                <td><?php $this->webhook_url_field( $ha_webhook_url, null, 'Espace partenaire HelloAsso' ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé de signature', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'ha_signature_key', $has_ha_sig_key, __( 'Optionnelle — si vide, vérification par IP', 'givoly' ) ); ?>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>
