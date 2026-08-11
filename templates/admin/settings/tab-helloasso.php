<?php
/**
 * Onglet « HelloAsso » de la page Settings Givoly.
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
                                <span class="givoly-badge givoly-badge--ok givoly-badge--title">✓ <?php esc_html_e( 'Configured', 'givoly' ); ?></span>
                            <?php else : ?>
                                <span class="givoly-badge givoly-badge--warn givoly-badge--title"><?php esc_html_e( 'Not configured', 'givoly' ); ?></span>
                            <?php endif; ?>
                        </h2>
                        <p class="description">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: %s is the HelloAsso API documentation URL. */
                                    __( 'HelloAsso API documentation: <a href="%s" target="_blank" rel="noopener noreferrer">dev.helloasso.com/docs</a>.', 'givoly' ),
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
                                <th scope="row"><?php esc_html_e( 'Organization slug', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="ha_org_slug"
                                           value="<?php echo esc_attr( $ha_org_slug ); ?>"
                                           class="regular-text" placeholder="<?php esc_attr_e( 'my-organization', 'givoly' ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Your organization identifier in the HelloAsso URL.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'API credentials', 'givoly' ); ?></div></th></tr>

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
                                <th scope="row"><?php esc_html_e( 'Other payment methods link', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="ha_other_payments_url"
                                           value="<?php echo esc_attr( $ha_other_payments_url ); ?>"
                                           class="regular-text" placeholder="https://...">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Force one-time donations through an external link', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ha_once_use_other_payments_url" value="1" <?php checked( $ha_once_use_other_payments_url ); ?>>
                                        <?php esc_html_e( 'Use the “other payment methods” link instead of the HelloAsso API for one-time donations.', 'givoly' ); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Text below the HelloAsso button', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="ha_button_notice"
                                           value="<?php echo esc_attr( $ha_button_notice ); ?>"
                                           class="regular-text" placeholder="<?php esc_attr_e( '* Example notice', 'givoly' ); ?>">
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Webhook', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Webhook URL', 'givoly' ); ?></th>
                                <td><?php $this->webhook_url_field( $ha_webhook_url, null, __( 'HelloAsso partner area', 'givoly' ) ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Signing key', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'ha_signature_key', $has_ha_sig_key, __( 'Optional — if empty, verification uses the IP address', 'givoly' ) ); ?>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <?php submit_button( __( 'Save', 'givoly' ) ); ?>
                </div>
