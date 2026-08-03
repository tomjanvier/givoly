<?php
/**
 * Onglet « Général » de la page Réglages Givoly.
 *
 * Partiel inclus par SettingsPage::render() — les variables sont celles
 * définies dans render() (portée d'inclusion conservée).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : GÉNÉRAL
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'general' ? 'is-active' : '' ); ?>">


                    <div class="givoly-card givoly-card--mission">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-heart"></span>
                            <?php esc_html_e( 'Gratuit, associatif, sans mauvaise surprise.', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Givoly est maintenu par PLAID·ACT, une association à but non lucratif de défense des Droits humains. L’objectif est simple : proposer aux associations un outil clair pour recevoir des dons en ligne depuis WordPress, sans abonnement imposé et sans commission ajoutée par le plugin.', 'givoly' ); ?>
                        </p>
                    </div>

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-randomize"></span>
                            <?php esc_html_e( 'Passerelle par défaut', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Passerelle utilisée par [givoly_form] sans attribut gateway=.', 'givoly' ); ?>
                        </p>

                        <p class="description">
                            <?php esc_html_e( 'Activez Stripe, HelloAsso ou les deux. Quand les deux sont actifs, le formulaire affiche les deux boutons de paiement.', 'givoly' ); ?>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="stripe_enabled" value="1" <?php checked( $stripe_enabled ); ?>>
                                <?php esc_html_e( 'Activer Stripe sur les formulaires', 'givoly' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="helloasso_enabled" value="1" <?php checked( $helloasso_enabled ); ?>>
                                <?php esc_html_e( 'Activer HelloAsso sur les formulaires', 'givoly' ); ?>
                            </label>
                        </p>

                        <div class="givoly-gateway-choice">
                            <label class="givoly-gateway-card <?php echo esc_attr( $default_gateway === 'stripe' ? 'is-selected' : '' ); ?>">
                                <input type="radio" name="default_gateway" value="stripe"
                                    <?php checked( $default_gateway, 'stripe' ); ?>>
                                <span class="givoly-gateway-card__icon givoly-gateway-card__icon--stripe">S</span>
                                <span class="givoly-gateway-card__name">Stripe</span>
                                <?php if ( $stripe_ok ) : ?>
                                    <span class="givoly-badge givoly-badge--ok">✓ Configuré</span>
                                <?php else : ?>
                                    <span class="givoly-badge givoly-badge--warn">Non configuré</span>
                                <?php endif; ?>
                            </label>

                            <label class="givoly-gateway-card <?php echo esc_attr( $default_gateway === 'helloasso' ? 'is-selected' : '' ); ?>">
                                <input type="radio" name="default_gateway" value="helloasso"
                                    <?php checked( $default_gateway, 'helloasso' ); ?>>
                                <span class="givoly-gateway-card__icon givoly-gateway-card__icon--ha">H</span>
                                <span class="givoly-gateway-card__name">HelloAsso</span>
                                <?php if ( $ha_ok ) : ?>
                                    <span class="givoly-badge givoly-badge--ok">✓ Configuré</span>
                                <?php else : ?>
                                    <span class="givoly-badge givoly-badge--warn">Non configuré</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php esc_html_e( 'Pages de redirection', 'givoly' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Page de succès', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="success_url"
                                           value="<?php echo esc_attr( $success_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/merci/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affichée après un don réussi. Si vide, un message par défaut est utilisé.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Page d\'annulation', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="cancel_url"
                                           value="<?php echo esc_attr( $cancel_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/don/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affichée si le donateur annule le paiement.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Logo Givoly public', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="public_branding_enabled" value="1" <?php checked( $public_branding_enabled ); ?>>
                                        <?php esc_html_e( 'Afficher volontairement le logo Givoly et un lien vers givoly.org sous les formulaires de don.', 'givoly' ); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'Ce crédit est entièrement optionnel et désactivé par défaut. L’activer aide les associations à découvrir Givoly : un plugin gratuit, associatif, sans abonnement imposé et sans commission ajoutée par le plugin.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Formulaire post-paiement', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="post_payment_show_phone" value="1" <?php checked( $post_payment_show_phone ); ?>>
                                        <?php esc_html_e( 'Demander le numéro de téléphone (facultatif)', 'givoly' ); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="post_payment_show_address" value="1" <?php checked( $post_payment_show_address ); ?>>
                                        <?php esc_html_e( 'Demander l\'adresse postale complète (facultatif)', 'givoly' ); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'Affiché après retour de paiement réussi (paramètre givoly_success=1).', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>
