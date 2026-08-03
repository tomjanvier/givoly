<?php
/**
 * Onglet « Association » de la page Réglages Givoly.
 *
 * Partiel inclus par SettingsPage::render() — les variables sont celles
 * définies dans render() (portée d'inclusion conservée).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : ASSOCIATION
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'association' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-building"></span>
                            <?php esc_html_e( 'Votre association', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Ces informations identifient votre association dans les emails et les exports.', 'givoly' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Nom', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_name" value="<?php echo esc_attr( $assoc['name'] ); ?>" class="regular-text" placeholder="Association Exemple"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Adresse', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_address" value="<?php echo esc_attr( $assoc['address'] ); ?>" class="regular-text" placeholder="12 rue de la Paix"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Code postal', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_postal_code" value="<?php echo esc_attr( $assoc['postal_code'] ); ?>" class="small-text" placeholder="75001"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Ville', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_city" value="<?php echo esc_attr( $assoc['city'] ); ?>" class="regular-text" placeholder="Paris"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'SIRET', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="assoc_siret" value="<?php echo esc_attr( $assoc['siret'] ); ?>" class="regular-text" placeholder="123 456 789 00012">
                                    <p class="description"><?php esc_html_e( 'Ou numéro RNA si pas de SIRET.', 'givoly' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'RNA', 'givoly' ); ?></th>
                                <td><input type="text" name="assoc_rna" value="<?php echo esc_attr( $assoc['rna'] ); ?>" class="regular-text" placeholder="W751012345"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Agrément fiscal', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="assoc_fiscal_id" value="<?php echo esc_attr( $assoc['fiscal_id'] ); ?>" class="regular-text" placeholder="Optionnel">
                                    <p class="description"><?php esc_html_e( 'Délivré par la Direction des finances publiques.', 'givoly' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Email', 'givoly' ); ?></th>
                                <td>
                                    <input type="email" name="assoc_email" value="<?php echo esc_attr( $assoc['email'] ); ?>" class="regular-text" placeholder="contact@association.fr">
                                    <p class="description"><?php esc_html_e( 'Expéditeur des reçus fiscaux.', 'givoly' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>
