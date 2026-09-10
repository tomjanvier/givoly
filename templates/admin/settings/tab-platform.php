<?php
/**
 * Onglet « Plateforme » de la page Settings Givoly.
 *
 * Connexion opt-in vers la Plateforme Givoly (tableau de bord centralisé),
 * désactivée par défaut. Aucun appel distant sans activation explicite.
 *
 * Partiel inclus par SettingsPage::render() — les variables sont celles
 * définies dans render() (portée d'inclusion conservée).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : PLATEFORME
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'platform' ? 'is-active' : '' ); ?>">

                    <?php if ( isset( $_GET['givoly_platform_ok'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Platform connection is working.', 'givoly' ); ?></p></div>
                    <?php endif; ?>
                    <?php if ( isset( $_GET['givoly_platform_registered'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Site registered on the platform.', 'givoly' ); ?></p></div>
                    <?php endif; ?>
                    <?php if ( isset( $_GET['givoly_platform_synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                        <div class="notice notice-success is-dismissible"><p>
                            <?php
                            printf(
                                /* translators: %d: number of campaigns synced to the platform. */
                                esc_html( _n( '%d campaign synced to the platform.', '%d campaigns synced to the platform.', absint( wp_unslash( $_GET['givoly_platform_synced'] ) ), 'givoly' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                esc_html( (string) absint( wp_unslash( $_GET['givoly_platform_synced'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            );
                            ?>
                        </p></div>
                    <?php endif; ?>
                    <?php if ( isset( $_GET['givoly_platform_synced_now'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pending donations have been sent to the platform.', 'givoly' ); ?></p></div>
                    <?php endif; ?>
                    <?php if ( isset( $_GET['givoly_platform_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                        <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Platform action failed. Check the URL, the API key and the status below.', 'givoly' ); ?></p></div>
                    <?php endif; ?>

                    <div class="givoly-card givoly-card--platform">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-cloud"></span>
                            <?php esc_html_e( 'Givoly Platform', 'givoly' ); ?>
                            <?php if ( $platform_ok ) : ?>
                                <span class="givoly-badge givoly-badge--ok givoly-badge--title">✓ <?php esc_html_e( 'Connected', 'givoly' ); ?></span>
                            <?php else : ?>
                                <span class="givoly-badge givoly-badge--warn givoly-badge--title"><?php esc_html_e( 'Not connected', 'givoly' ); ?></span>
                            <?php endif; ?>
                        </h2>
                        <p class="description">
                            <?php esc_html_e( 'Optional central dashboard: register this site, check its health, forward confirmed donations and push a read-only campaign snapshot. Disabled by default — nothing is sent until you enable it.', 'givoly' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Enable the platform connection', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="platform_enabled" value="1" <?php checked( $platform_enabled ); ?>>
                                        <?php esc_html_e( 'Send data to the platform (requires a URL and an API key below).', 'givoly' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Platform URL', 'givoly' ); ?></th>
                                <td>
                                    <input type="url" name="platform_base_url"
                                           value="<?php echo esc_attr( $platform_base_url ); ?>"
                                           class="regular-text" placeholder="https://platform.example.org">
                                    <p class="description"><?php esc_html_e( 'Base URL of your Givoly Platform (https required, except localhost).', 'givoly' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'API key', 'givoly' ); ?></th>
                                <td>
                                    <?php $this->secret_field( 'platform_api_key', $has_platform_api_key, '' ); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Organization ID', 'givoly' ); ?></th>
                                <td>
                                    <input type="text" name="platform_organization_id"
                                           value="<?php echo esc_attr( $platform_org_id ); ?>"
                                           class="regular-text" placeholder="">
                                    <p class="description"><?php esc_html_e( 'Optional identifier of your organization on the platform.', 'givoly' ); ?></p>
                                </td>
                            </tr>
                            <tr><th colspan="2"><div class="givoly-section-sep"><?php esc_html_e( 'Synchronized data', 'givoly' ); ?></div></th></tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Donations', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="platform_sync_donations" value="1" <?php checked( $platform_sync_donations ); ?>>
                                        <?php esc_html_e( 'Forward confirmed donations (amount, currency, gateway, campaign, donor name and email).', 'givoly' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Campaigns', 'givoly' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="platform_sync_campaigns" value="1" <?php checked( $platform_sync_campaigns ); ?>>
                                        <?php esc_html_e( 'Allow pushing a read-only campaign snapshot on manual action.', 'givoly' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( __( 'Save', 'givoly' ) ); ?>
                </div>

                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'platform' ? 'is-active' : '' ); ?>">
                    <div class="givoly-card givoly-card--platform">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e( 'Connection status and actions', 'givoly' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Site ID', 'givoly' ); ?></th>
                                <td><code><?php echo esc_html( $platform_site_id ?: '—' ); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Last check', 'givoly' ); ?></th>
                                <td>
                                    <?php if ( $platform_last_check_at ) : ?>
                                        <?php echo esc_html( $platform_last_check_at ); ?>
                                        (<?php echo esc_html( $platform_last_status ?: 'unknown' ); ?>)
                                        <?php if ( $platform_last_error ) : ?>
                                            <br><code><?php echo esc_html( $platform_last_error ); ?></code>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <?php
                            // Liens admin-post avec nonce (pas de formulaires imbriqués :
                            // ce partiel est déjà inclus dans le formulaire des réglages).
                            $platform_actions = [
                                'givoly_platform_check'           => __( 'Check connection', 'givoly' ),
                                'givoly_platform_register'        => __( 'Register this site', 'givoly' ),
                                'givoly_platform_sync_now'        => __( 'Send pending donations', 'givoly' ),
                                'givoly_platform_sync_campaigns'  => __( 'Push campaigns snapshot', 'givoly' ),
                            ];
                            foreach ( $platform_actions as $action_name => $label ) :
                            ?>
                                <a class="button" style="margin-right:8px;"
                                   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $action_name ), $action_name ) ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </a>
                            <?php endforeach; ?>
                        </p>
                        <p class="description"><?php esc_html_e( 'Expected platform endpoints: GET /api/wordpress/health, POST /api/wordpress/sites, POST /api/wordpress/donations, POST /api/wordpress/campaigns/sync.', 'givoly' ); ?></p>
                    </div>
                </div>
