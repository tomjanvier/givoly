<?php
/**
 * Rend le contenu HTML commun des emails Givoly.
 *
 * @package Givoly\Mail
 */

namespace Givoly\Mail;

use Givoly\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EmailRenderer {

    public static function render( string $body, string $subject ): string {
        $color   = esc_attr( Settings::get_email_primary_color() );
        $content = nl2br( esc_html( $body ) );
        $logo    = Settings::get_email_logo_url();
        $header  = $logo
            ? '<img src="' . esc_url( $logo ) . '" alt="" style="display:block;max-width:220px;max-height:70px;margin:0 auto 16px;">'
            : '<strong style="font-size:20px;">' . esc_html( Settings::get_assoc_name() ?: get_bloginfo( 'name' ) ) . '</strong>';

        return '<!doctype html><html><body style="margin:0;padding:24px;background:#f6f7f7;font-family:Arial,sans-serif;color:#1f2937;line-height:1.6;">'
            . '<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">'
            . '<div style="padding:24px;text-align:center;background:' . $color . ';color:#fff;">' . $header . '<div style="font-size:16px;">' . esc_html( $subject ) . '</div></div>'
            . '<div style="padding:28px 30px;">' . $content . '</div>'
            . '</div></body></html>';
    }

    /**
     * @return string[]
     */
    public static function headers(): array {
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $email   = Settings::get_assoc_email();

        if ( is_email( $email ) ) {
            $headers[] = 'From: ' . Settings::get_email_sender_name() . ' <' . $email . '>';
        }

        return $headers;
    }
}
