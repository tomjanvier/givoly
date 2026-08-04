<?php
/**
 * Partial : champs supplémentaires (téléphone, organisation, message).
 *
 * Variables en portée d'inclusion :
 *   @var string $form_id
 *   @var array  $extra_fields
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local variables in an included template.
foreach ( $extra_fields as $field ) {
    if ( $field === 'phone' ) { ?>
        <div class="givoly-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-phone" class="givoly-label"><?php esc_html_e( 'Téléphone', 'givoly' ); ?></label>
            <input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone" class="givoly-input" maxlength="40" autocomplete="tel">
        </div>
    <?php } elseif ( $field === 'company' ) { ?>
        <div class="givoly-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-company" class="givoly-label"><?php esc_html_e( 'Organisation', 'givoly' ); ?></label>
            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-company" name="company" class="givoly-input" maxlength="120" autocomplete="organization">
        </div>
    <?php } elseif ( $field === 'message' ) { ?>
        <div class="givoly-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-message" class="givoly-label"><?php esc_html_e( 'Message', 'givoly' ); ?></label>
            <textarea id="<?php echo esc_attr( $form_id ); ?>-message" name="message" class="givoly-input givoly-textarea" rows="3" maxlength="500"></textarea>
        </div>
    <?php }
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
