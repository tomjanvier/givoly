<?php
/**
 * Onglet « Général » de la page Settings Givoly.
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
                            <?php esc_html_e( 'Free, nonprofit, no surprises.', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Givoly is maintained by PLAID·ACT, a nonprofit organization defending human rights. The goal is simple: give nonprofits a clear way to accept donations through WordPress, with no required subscription and no commission added by the plugin.', 'givoly' ); ?>
                        </p>
                    </div>

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-randomize"></span>
                            <?php esc_html_e( 'Default gateway', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Gateway used by [givoly_form] when no gateway= attribute is provided.', 'givoly' ); ?>
                        </p>

                        <p class="description">
                            <?php esc_html_e( 'Enable Stripe, HelloAsso, or both. When both are enabled, the form displays both payment buttons.', 'givoly' ); ?>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="stripe_enabled" value="1" <?php checked( $stripe_enabled ); ?>>
                                <?php esc_html_e( 'Enable Stripe on forms', 'givoly' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="helloasso_enabled" value="1" <?php checked( $helloasso_enabled ); ?>>
                                <?php esc_html_e( 'Enable HelloAsso on forms', 'givoly' ); ?>
                            </label>
                        </p>

                        <div class="givoly-gateway-choice">
                            <label class="givoly-gateway-card <?php echo esc_attr( $default_gateway === 'stripe' ? 'is-selected' : '' ); ?>">
                                <input type="radio" name="default_gateway" value="stripe"
                                    <?php checked( $default_gateway, 'stripe' ); ?>>
                                <span class="givoly-gateway-card__icon givoly-gateway-card__icon--stripe">S</span>
                                <span class="givoly-gateway-card__name">Stripe</span>
                                <?php if ( $stripe_ok ) : ?>
                                    <span class="givoly-badge givoly-badge--ok">✓ <?php esc_html_e( 'Configured', 'givoly' ); ?></span>
                                <?php else : ?>
                                    <span class="givoly-badge givoly-badge--warn"><?php esc_html_e( 'Not configured', 'givoly' ); ?></span>
                                <?php endif; ?>
                            </label>

                            <label class="givoly-gateway-card <?php echo esc_attr( $default_gateway === 'helloasso' ? 'is-selected' : '' ); ?>">
                                <input type="radio" name="default_gateway" value="helloasso"
                                    <?php checked( $default_gateway, 'helloasso' ); ?>>
                                <span class="givoly-gateway-card__icon givoly-gateway-card__icon--ha">H</span>
                                <span class="givoly-gateway-card__name">HelloAsso</span>
                                <?php if ( $ha_ok ) : ?>
                                    <span class="givoly-badge givoly-badge--ok">✓ <?php esc_html_e( 'Configured', 'givoly' ); ?></span>
                                <?php else : ?>
                                    <span class="givoly-badge givoly-badge--warn"><?php esc_html_e( 'Not configured', 'givoly' ); ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>

                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php esc_html_e( 'Redirect pages', 'givoly' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Success page', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="success_url"
                                           value="<?php echo esc_attr( $success_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/merci/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Shown after a successful donation. If empty, a default message is used.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Cancellation page', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="cancel_url"
                                           value="<?php echo esc_attr( $cancel_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( home_url( '/don/' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Shown if the donor cancels the payment.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Public Givoly branding', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="public_branding_enabled" value="1" <?php checked( $public_branding_enabled ); ?>>
                                        <?php esc_html_e( 'Show the Givoly logo and a link to givoly.org below donation forms.', 'givoly' ); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'This credit is entirely optional and disabled by default. Enabling it helps nonprofits discover Givoly: a free nonprofit plugin with no required subscription and no commission added by the plugin.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Post-payment form', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="post_payment_show_phone" value="1" <?php checked( $post_payment_show_phone ); ?>>
                                        <?php esc_html_e( 'Request phone number (optional)', 'givoly' ); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="post_payment_show_address" value="1" <?php checked( $post_payment_show_address ); ?>>
                                        <?php esc_html_e( 'Request full postal address (optional)', 'givoly' ); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'Shown after a successful payment return (givoly_success=1 parameter).', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Save', 'givoly' ) ); ?>
                </div>
