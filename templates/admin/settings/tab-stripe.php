<?php
/**
 * Onglet « Stripe » de la page Réglages Givoly.
 *
 * Partiel inclus par SettingsPage::render() — les variables sont celles
 * définies dans render() (portée d'inclusion conservée).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : STRIPE
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'stripe' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card givoly-card--stripe">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-cart"></span>
                            Stripe
                            <?php if ( $stripe_ok ) : ?>
                                <span class="givoly-badge givoly-badge--ok givoly-badge--title">✓ Configuré</span>
                            <?php else : ?>
                                <span class="givoly-badge givoly-badge--warn givoly-badge--title">Non configuré</span>
                            <?php endif; ?>
                        </h2>
                        <p class="description">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: %s is the Stripe API documentation URL. */
                                    __( 'Documentation API Stripe : <a href="%s" target="_blank" rel="noopener noreferrer">docs.stripe.com/api</a>.', 'givoly' ),
                                    esc_url( 'https://docs.stripe.com/api' )
                                )
                            );
                            ?>
                        </p>

                        <table class="form-table" role="presentation">

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Mode', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-mode-toggle">
                                        <label class="givoly-mode-toggle__option <?php echo esc_attr( $stripe_mode === 'test' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="stripe_mode" value="test"
                                                <?php checked( $stripe_mode, 'test' ); ?>>
                                            <?php esc_html_e( 'Test', 'givoly' ); ?>
                                        </label>
                                        <label class="givoly-mode-toggle__option givoly-mode-toggle__option--live <?php echo esc_attr( $stripe_mode === 'live' ? 'is-active' : '' ); ?>">
                                            <input type="radio" name="stripe_mode" value="live"
                                                <?php checked( $stripe_mode, 'live' ); ?>>
                                            <?php esc_html_e( 'Live', 'givoly' ); ?>
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Clés Test', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé publique', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="stripe_pk_test"
                                           value="<?php echo esc_attr( $pk_test ); ?>"
                                           class="regular-text" placeholder="pk_test_…">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé secrète', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_sk_test', $has_sk_test, 'sk_test_…' ); ?>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Clés Live', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé publique', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="stripe_pk_live"
                                           value="<?php echo esc_attr( $pk_live ); ?>"
                                           class="regular-text" placeholder="pk_live_…">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Clé secrète', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_sk_live', $has_sk_live, 'sk_live_…' ); ?>
                                </td>
                            </tr>

                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Webhook', 'givoly' ); ?></div></th></tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'URL Webhook', 'givoly' ); ?></th>
                                <td><?php $this->webhook_url_field( $webhook_url, 'checkout.session.completed', 'Stripe → Développeurs → Webhooks' ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Secret Webhook', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'stripe_webhook_secret', $has_webhook, 'whsec_…' ); ?>
                                </td>
                            </tr>

                        </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>
