<?php
/**
 * Onglet « Email » de la page Réglages Givoly.
 *
 * Partiel inclus par SettingsPage::render() — les variables sont celles
 * définies dans render() (portée d'inclusion conservée).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

                <!-- ════════════════════════════════════════════════════════
                     Onglet : EMAIL
                ════════════════════════════════════════════════════════ -->
                <div class="givoly-tab-panel <?php echo esc_attr( $active === 'email' ? 'is-active' : '' ); ?>">

                    <div class="givoly-card givoly-card--email">
                        <h2 class="givoly-card__title">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e( 'Apparence des emails', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Personnalisez les notifications de dons, les messages aux donateurs et les reçus fiscaux avec leur pièce jointe PDF.', 'givoly' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givoly-email-sender"><?php esc_html_e( 'Nom expéditeur', 'givoly' ); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="givoly-email-sender"
                                           name="email_sender_name"
                                           value="<?php echo esc_attr( $email_sender_name ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( Settings::get_assoc_name() ?: get_bloginfo( 'name' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Affiché comme expéditeur dans la boîte email du donateur. Si vide, le nom de l\'association est utilisé.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="givoly-email-color"><?php esc_html_e( 'Couleur principale', 'givoly' ); ?></label>
                                </th>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <input type="color"
                                               id="givoly-email-color"
                                               name="email_primary_color"
                                               value="<?php echo esc_attr( $email_primary_color ); ?>">
                                        <span id="givoly-color-preview"
                                              style="display:inline-block;width:80px;height:32px;border-radius:4px;background:<?php echo esc_attr( $email_primary_color ); ?>;border:1px solid #ddd;"></span>
                                        <code id="givoly-color-hex"><?php echo esc_html( $email_primary_color ); ?></code>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e( 'Couleur de l\'en-tête et du montant dans l\'email.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-logo"><?php esc_html_e( 'URL du logo', 'givoly' ); ?></label>
                                    </th>
                                <td>
                                    <input type="url"
                                           id="givoly-email-logo"
                                           name="email_logo_url"
                                           value="<?php echo esc_attr( $email_logo_url ); ?>"
                                           class="regular-text"
                                           placeholder="https://votresite.fr/logo.png">
                                    <p class="description">
                                        <?php esc_html_e( 'Logo affiché en haut de l\'email (PNG ou JPG recommandé, max 300px de large). Si vide, le nom de l\'association est affiché.', 'givoly' ); ?>
                                    </p>
                                    <?php if ( $email_logo_url ) : ?>
                                        <div style="margin-top:8px;">
                                            <img src="<?php echo esc_url( $email_logo_url ); ?>"
                                                 alt="<?php esc_attr_e( 'Aperçu du logo', 'givoly' ); ?>"
                                                 style="max-height:60px;max-width:200px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;">
                                        </div>
                                    <?php endif; ?>
                                </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-thank-subject"><?php esc_html_e( 'Sujet email de remerciement', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               class="regular-text"
                                               id="givoly-email-thank-subject"
                                               name="email_thank_subject"
                                               value="<?php echo esc_attr( $email_thank_subject ); ?>"
                                               placeholder="<?php esc_attr_e( 'Merci pour votre don — {site_name}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Variables disponibles : {site_name}, {amount}, {first_name}, {last_name}, {campaign}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-thank-body"><?php esc_html_e( 'Texte email de remerciement', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-thank-body"
                                                  name="email_thank_body"
                                                  rows="6"
                                                  class="large-text"
                                                  placeholder="<?php esc_attr_e( 'Bonjour {first_name},', 'givoly' ); ?>"><?php echo esc_textarea( $email_thank_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Vous pouvez personnaliser le message librement. Les variables seront remplacées automatiquement.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-admin-donation-subject"><?php esc_html_e( 'Sujet email administrateur', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" class="regular-text" id="givoly-email-admin-donation-subject" name="email_admin_donation_subject" value="<?php echo esc_attr( $email_admin_donation_subject ); ?>" placeholder="<?php esc_attr_e( '[{site_name}] Nouveau don reçu — {amount}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Email envoyé à l’adresse administrateur après confirmation du paiement. Variables : {site_name}, {amount}, {first_name}, {last_name}, {email}, {campaign}, {donation_id}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-admin-donation-body"><?php esc_html_e( 'Texte email administrateur', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-admin-donation-body" name="email_admin_donation_body" rows="7" class="large-text" placeholder="<?php echo esc_attr( Settings::get_email_admin_donation_body() ); ?>"><?php echo esc_textarea( $email_admin_donation_body ); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-tax-receipt-subject"><?php esc_html_e( 'Sujet reçu fiscal annuel', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               class="regular-text"
                                               id="givoly-email-tax-receipt-subject"
                                               name="email_tax_receipt_subject"
                                               value="<?php echo esc_attr( $email_tax_receipt_subject ); ?>"
                                               placeholder="<?php esc_attr_e( 'Votre reçu fiscal {year} — {association}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Variables disponibles : {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-tax-receipt-body"><?php esc_html_e( 'Texte de l’email de reçu fiscal', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-tax-receipt-body"
                                                  name="email_tax_receipt_body"
                                                  rows="10"
                                                  class="large-text"
                                                  placeholder="<?php echo esc_attr( Settings::get_email_tax_receipt_body() ); ?>"><?php echo esc_textarea( $email_tax_receipt_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Modèle du message envoyé depuis Donateurs. Variables : {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}, {association_address}, {siret}, {rna}, {fiscal_id}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Pièce jointe PDF', 'givoly' ); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="tax_receipt_pdf_enabled" value="1" <?php checked( $tax_receipt_pdf_enabled ); ?>> <?php esc_html_e( 'Joindre un PDF personnalisé à chaque reçu fiscal', 'givoly' ); ?></label>
                                        <p class="description"><?php esc_html_e( 'Le PDF est généré sans service externe et envoyé avec le message. Les réglages ci-dessous permettent de modifier son contenu.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="givoly-tax-pdf-title"><?php esc_html_e( 'Titre du PDF', 'givoly' ); ?></label></th>
                                    <td>
                                        <input type="text" class="regular-text" id="givoly-tax-pdf-title" name="tax_receipt_pdf_title" value="<?php echo esc_attr( $tax_receipt_pdf_title ); ?>" placeholder="<?php echo esc_attr( Settings::get_tax_receipt_pdf_title() ); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="givoly-tax-pdf-body"><?php esc_html_e( 'Contenu du PDF', 'givoly' ); ?></label></th>
                                    <td>
                                        <textarea id="givoly-tax-pdf-body" name="tax_receipt_pdf_body" rows="12" class="large-text" placeholder="<?php echo esc_attr( Settings::get_tax_receipt_pdf_body() ); ?>"><?php echo esc_textarea( $tax_receipt_pdf_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Variables : {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}, {association_address}, {siret}, {rna}, {fiscal_id}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="givoly-tax-pdf-footer"><?php esc_html_e( 'Pied de page du PDF', 'givoly' ); ?></label></th>
                                    <td>
                                        <textarea id="givoly-tax-pdf-footer" name="tax_receipt_pdf_footer" rows="4" class="large-text" placeholder="<?php echo esc_attr( Settings::get_tax_receipt_pdf_footer() ); ?>"><?php echo esc_textarea( $tax_receipt_pdf_footer ); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                    </div>

                    <?php submit_button( __( 'Enregistrer', 'givoly' ) ); ?>
                </div>
