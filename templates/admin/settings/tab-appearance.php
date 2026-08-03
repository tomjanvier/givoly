<?php
/**
 * Onglet « Apparence » de la page Réglages Givoly.
 *
 * Partiel inclus par SettingsPage::render() — les variables sont celles
 * définies dans render() (portée d'inclusion conservée).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : APPARENCE
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'appearance' ? 'is-active' : '' ); ?>">

                    <!-- Card Couleurs -->
                    <div class="givoly-card givoly-card--appearance">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php esc_html_e( 'Couleurs', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Cette couleur s\'applique à tous vos formulaires de don, quel que soit le thème shortcode. Laissez la valeur par défaut pour utiliser la couleur du thème.', 'givoly' ); ?>
                        </p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givoly-ap-primary">
                                        <?php esc_html_e( 'Couleur principale', 'givoly' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <input type="color"
                                               id="givoly-ap-primary"
                                               name="appearance_primary_color"
                                               value="<?php echo esc_attr( $ap_primary ?: '#2b1533' ); ?>"
                                               data-preview-id="givoly-ap-primary-preview"
                                               data-hex-id="givoly-ap-primary-hex"
                                               data-enabled-id="givoly-ap-primary-enabled">
                                        <span id="givoly-ap-primary-preview"
                                              style="display:inline-block;width:72px;height:32px;border-radius:4px;
                                                     background:<?php echo esc_attr( $ap_primary ?: '#2b1533' ); ?>;
                                                     border:1px solid #ddd;vertical-align:middle;"></span>
                                        <code id="givoly-ap-primary-hex"><?php echo esc_html( $ap_primary ?: '#2b1533' ); ?></code>
                                        <?php if ( $ap_primary !== '' ) : ?>
                                            <button type="button" class="button button-small givoly-ap-reset"
                                                    data-field="appearance_primary_color"
                                                    data-enabled="givoly-ap-primary-enabled"
                                                    data-preview-id="givoly-ap-primary-preview"
                                                    data-hex-id="givoly-ap-primary-hex"
                                                    data-default="#2b1533">
                                                <?php esc_html_e( 'Réinitialiser', 'givoly' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e( 'Boutons, bordures actives et accent du formulaire. Utilisé avec du texte blanc.', 'givoly' ); ?>
                                    </p>
                                    <input type="hidden"
                                           name="appearance_primary_color_enabled"
                                           id="givoly-ap-primary-enabled"
                                           value="<?php echo esc_attr( $ap_primary !== '' ? '1' : '0' ); ?>">
                                    <input type="hidden" name="appearance_accent_color_enabled" id="givoly-ap-accent-enabled" value="0">

                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Card Forme -->
                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-editor-expand"></span>
                            <?php esc_html_e( 'Forme', 'givoly' ); ?>
                        </h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Coins', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-shape-group">
                                        <?php
                                        $radius_opts = [
                                            'square'  => [ 'label' => 'Carré',       'preview_r' => '0px',  'desc' => '0 px'  ],
                                            'rounded' => [ 'label' => 'Arrondi',      'preview_r' => '8px',  'desc' => '12 px' ],
                                            'pill'    => [ 'label' => 'Très arrondi', 'preview_r' => '16px', 'desc' => '20 px' ],
                                        ];
                                        foreach ( $radius_opts as $val => $opt ) : ?>
                                            <label class="givoly-shape-card <?php echo esc_attr( $ap_radius === $val ? 'is-selected' : '' ); ?>">
                                                <input type="radio" name="appearance_radius"
                                                       value="<?php echo esc_attr( $val ); ?>"
                                                       <?php checked( $ap_radius, $val ); ?>>
                                                <span class="givoly-shape-card__preview"
                                                      style="border-radius:<?php echo esc_attr( $opt['preview_r'] ); ?>"></span>
                                                <span class="givoly-shape-card__label"><?php echo esc_html( $opt['label'] ); ?></span>
                                                <span class="givoly-shape-card__desc"><?php echo esc_html( $opt['desc'] ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Style du bouton', 'givoly' ); ?></th>
                                <td>
                                    <div class="givoly-shape-group">
                                        <label class="givoly-shape-card <?php echo esc_attr( $ap_btn_style === 'filled' ? 'is-selected' : '' ); ?>">
                                            <input type="radio" name="appearance_btn_style" value="filled"
                                                   <?php checked( $ap_btn_style, 'filled' ); ?>>
                                            <span class="givoly-shape-card__btn givoly-shape-card__btn--filled">
                                                <?php esc_html_e( 'Donner', 'givoly' ); ?>
                                            </span>
                                            <span class="givoly-shape-card__label"><?php esc_html_e( 'Plein', 'givoly' ); ?></span>
                                        </label>
                                        <label class="givoly-shape-card <?php echo esc_attr( $ap_btn_style === 'outline' ? 'is-selected' : '' ); ?>">
                                            <input type="radio" name="appearance_btn_style" value="outline"
                                                   <?php checked( $ap_btn_style, 'outline' ); ?>>
                                            <span class="givoly-shape-card__btn givoly-shape-card__btn--outline">
                                                <?php esc_html_e( 'Donner', 'givoly' ); ?>
                                            </span>
                                            <span class="givoly-shape-card__label"><?php esc_html_e( 'Contour', 'givoly' ); ?></span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>


                    <!-- Card CSS additionnel WordPress -->
                    <div class="givoly-card givoly-card--appearance">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php esc_html_e( 'CSS additionnel WordPress', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Pour personnaliser vos formulaires, utilisez l’éditeur CSS natif de WordPress. Givoly ne stocke ni n’exécute de CSS arbitraire.', 'givoly' ); ?>
                        </p>
                        <p>
                            <a class="button button-secondary" href="<?php echo esc_url( $wordpress_css_url ); ?>">
                                <?php
                                echo esc_html(
                                    wp_is_block_theme()
                                        ? __( 'Ouvrir l’éditeur du site', 'givoly' )
                                        : __( 'Ouvrir le CSS additionnel', 'givoly' )
                                );
                                ?>
                            </a>
                        </p>
                        <p class="description">
                            <?php esc_html_e( 'Ciblez .givoly-wrap, .givoly-form, .givoly-amount-btn ou .givoly-form__submit dans cet éditeur.', 'givoly' ); ?>
                        </p>
                    </div>

                    <!-- Card Aperçu -->
                    <div class="givoly-card">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Aperçu du formulaire', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Rendu du formulaire avec vos réglages actuels sauvegardés.', 'givoly' ); ?>
                        </p>
                        <?php
                        $preview_nonce = wp_create_nonce( 'givoly_form_preview' );
                        $preview_url   = admin_url( 'admin-ajax.php?action=givoly_form_preview&_wpnonce=' . $preview_nonce );
                        ?>
                        <iframe id="givoly-ap-preview"
                                src="<?php echo esc_url( $preview_url ); ?>"
                                style="width:100%;height:540px;border:1px solid #e0e0e0;border-radius:6px;display:block;"
                                title="<?php esc_attr_e( 'Aperçu formulaire', 'givoly' ); ?>">
                        </iframe>
                        <p class="description" style="margin-top:8px;">
                            <?php esc_html_e( 'Enregistrez les réglages pour mettre à jour l\'aperçu.', 'givoly' ); ?>
                        </p>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>
