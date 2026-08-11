<?php
/**
 * Onglet « Email » de la page Settings Givoly.
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
                            <?php esc_html_e( 'Email appearance', 'givoly' ); ?>
                        </h2>
                        <p class="givoly-card__desc">
                            <?php esc_html_e( 'Customize donation notifications, donor messages, and tax receipts with their PDF attachment.', 'givoly' ); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="givoly-email-sender"><?php esc_html_e( 'Sender name', 'givoly' ); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="givoly-email-sender"
                                           name="email_sender_name"
                                           value="<?php echo esc_attr( $email_sender_name ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( \Givoly\Admin\Settings::get_assoc_name() ?: get_bloginfo( 'name' ) ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( "Shown as the sender in the donor's inbox. If empty, the organization's name is used.", 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="givoly-email-color"><?php esc_html_e( 'Primary color', 'givoly' ); ?></label>
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
                                        <?php esc_html_e( 'Color of the email header and amount.', 'givoly' ); ?>
                                    </p>
                                </td>
                            </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-logo"><?php esc_html_e( 'Logo URL', 'givoly' ); ?></label>
                                    </th>
                                <td>
                                    <input type="url"
                                           id="givoly-email-logo"
                                           name="email_logo_url"
                                           value="<?php echo esc_attr( $email_logo_url ); ?>"
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr( 'https://your-site.com/logo.png' ); ?>">
                                    <p class="description">
                                        <?php esc_html_e( 'Logo shown at the top of the email (PNG or JPG recommended, max. 300px wide). If empty, the organization\'s name is shown.', 'givoly' ); ?>
                                    </p>
                                    <?php if ( $email_logo_url ) : ?>
                                        <div style="margin-top:8px;">
                                            <img src="<?php echo esc_url( $email_logo_url ); ?>"
                                                 alt="<?php esc_attr_e( 'Logo preview', 'givoly' ); ?>"
                                                 style="max-height:60px;max-width:200px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;">
                                        </div>
                                    <?php endif; ?>
                                </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-thank-subject"><?php esc_html_e( 'Thank-you email subject', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               class="regular-text"
                                               id="givoly-email-thank-subject"
                                               name="email_thank_subject"
                                               value="<?php echo esc_attr( $email_thank_subject ); ?>"
                                               placeholder="<?php esc_attr_e( 'Thank you for your donation — {site_name}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Available variables: {site_name}, {amount}, {first_name}, {last_name}, {campaign}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-thank-body"><?php esc_html_e( 'Thank-you email text', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-thank-body"
                                                  name="email_thank_body"
                                                  rows="6"
                                                  class="large-text"
                                                  placeholder="<?php esc_attr_e( 'Hello {first_name},', 'givoly' ); ?>"><?php echo esc_textarea( $email_thank_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'You can customize this message freely. Variables will be replaced automatically.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-admin-donation-subject"><?php esc_html_e( 'Sujet email administrateur', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" class="regular-text" id="givoly-email-admin-donation-subject" name="email_admin_donation_subject" value="<?php echo esc_attr( $email_admin_donation_subject ); ?>" placeholder="<?php esc_attr_e( '[{site_name}] New donation received — {amount}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Email sent to the administrator after payment confirmation. Variables: {site_name}, {amount}, {first_name}, {last_name}, {email}, {campaign}, {donation_id}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-admin-donation-body"><?php esc_html_e( 'Texte email administrateur', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-admin-donation-body" name="email_admin_donation_body" rows="7" class="large-text" placeholder="<?php echo esc_attr( \Givoly\Admin\Settings::get_email_admin_donation_body() ); ?>"><?php echo esc_textarea( $email_admin_donation_body ); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-tax-receipt-subject"><?php esc_html_e( 'Annual tax receipt subject', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               class="regular-text"
                                               id="givoly-email-tax-receipt-subject"
                                               name="email_tax_receipt_subject"
                                               value="<?php echo esc_attr( $email_tax_receipt_subject ); ?>"
                                               placeholder="<?php esc_attr_e( 'Your tax receipt for {year} — {association}', 'givoly' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Available variables: {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="givoly-email-tax-receipt-body"><?php esc_html_e( 'Tax receipt email text', 'givoly' ); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="givoly-email-tax-receipt-body"
                                                  name="email_tax_receipt_body"
                                                  rows="10"
                                                  class="large-text"
                                                  placeholder="<?php echo esc_attr( \Givoly\Admin\Settings::get_email_tax_receipt_body() ); ?>"><?php echo esc_textarea( $email_tax_receipt_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Template for the message sent from Donors. Variables: {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}, {association_address}, {siret}, {rna}, {fiscal_id}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'PDF attachment', 'givoly' ); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="tax_receipt_pdf_enabled" value="1" <?php checked( $tax_receipt_pdf_enabled ); ?>> <?php esc_html_e( 'Attach a custom PDF to each tax receipt', 'givoly' ); ?></label>
                                        <p class="description"><?php esc_html_e( 'The PDF is generated without an external service and sent with the message. The settings below let you modify its content.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="givoly-tax-pdf-title"><?php esc_html_e( 'PDF title', 'givoly' ); ?></label></th>
                                    <td>
                                        <input type="text" class="regular-text" id="givoly-tax-pdf-title" name="tax_receipt_pdf_title" value="<?php echo esc_attr( $tax_receipt_pdf_title ); ?>" placeholder="<?php echo esc_attr( \Givoly\Admin\Settings::get_tax_receipt_pdf_title() ); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="givoly-tax-pdf-body"><?php esc_html_e( 'PDF content', 'givoly' ); ?></label></th>
                                    <td>
                                        <textarea id="givoly-tax-pdf-body" name="tax_receipt_pdf_body" rows="12" class="large-text" placeholder="<?php echo esc_attr( \Givoly\Admin\Settings::get_tax_receipt_pdf_body() ); ?>"><?php echo esc_textarea( $tax_receipt_pdf_body ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'Variables : {donor_name}, {first_name}, {last_name}, {year}, {amount}, {donation_count}, {association}, {association_address}, {siret}, {rna}, {fiscal_id}.', 'givoly' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="givoly-tax-pdf-footer"><?php esc_html_e( 'PDF footer', 'givoly' ); ?></label></th>
                                    <td>
                                        <textarea id="givoly-tax-pdf-footer" name="tax_receipt_pdf_footer" rows="4" class="large-text" placeholder="<?php echo esc_attr( \Givoly\Admin\Settings::get_tax_receipt_pdf_footer() ); ?>"><?php echo esc_textarea( $tax_receipt_pdf_footer ); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                    </div>

                    <?php submit_button( __( 'Save', 'givoly' ) ); ?>
                </div>
