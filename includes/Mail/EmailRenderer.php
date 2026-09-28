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
        $color       = esc_attr( Settings::get_email_primary_color() );
        $content     = self::format_message( $body );
        $organization = Settings::get_assoc_name() ?: get_bloginfo( 'name' );
        $logo        = Settings::get_email_logo_url();
        $header      = $logo
            ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $organization ) . '" width="180" style="display:block;width:180px;max-width:100%;height:auto;max-height:64px;object-fit:contain;margin:0 auto;">'
            : '<div style="font-family:Arial,sans-serif;font-size:21px;line-height:1.2;font-weight:700;letter-spacing:-.4px;color:#173b35;">' . esc_html( $organization ) . '</div>';
        $brand_logo  = esc_url( GIVOLY_PLUGIN_URL . 'logo.png' );
        $brand       = '<img src="' . $brand_logo . '" alt="Givoly" width="66" style="display:inline-block;width:66px;height:auto;vertical-align:middle;border:0;">';

        return '<!doctype html><html lang="' . esc_attr( determine_locale() ) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f3f6f4;font-family:Arial,Helvetica,sans-serif;color:#20312d;line-height:1.65;-webkit-text-size-adjust:100%;">'
            . '<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">' . esc_html( wp_strip_all_tags( $subject ) ) . '</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f3f6f4;"><tr><td align="center" style="padding:34px 16px 24px;">'
            . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#fff;border:1px solid #e5ebe7;border-radius:18px;overflow:hidden;">'
            . '<tr><td style="height:6px;background:' . $color . ';font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td align="center" style="padding:32px 32px 26px;background:#fff;">' . $header
            . '<div style="width:42px;height:3px;margin:22px auto 16px;background:' . $color . ';border-radius:3px;font-size:0;line-height:0;">&nbsp;</div>'
            . '<div style="font-size:12px;line-height:1.4;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#70817b;">' . esc_html( $subject ) . '</div></td></tr>'
            . '<tr><td style="padding:6px 38px 36px;font-size:16px;line-height:1.8;color:#33443e;">' . $content . '</td></tr>'
            . '<tr><td style="padding:20px 28px;border-top:1px solid #edf1ee;background:#fbfcfb;text-align:center;">'
            . '<div style="font-size:12px;line-height:1.6;color:#78847f;">' . esc_html( $organization ) . '</div>'
            . '<div style="margin-top:10px;font-size:12px;line-height:1;color:#78847f;">' . $brand . '<span style="display:inline-block;margin-left:8px;vertical-align:middle;">' . esc_html__( 'Made for nonprofits by PLAID·ACT', 'givoly' ) . '</span></div>'
            . '</td></tr></table><div style="padding:16px 10px 0;font-size:11px;line-height:1.5;color:#8b9691;">' . esc_html__( 'This message was sent by your organization.', 'givoly' ) . '</div>'
            . '</td></tr></table></body></html>';
    }

    /**
     * Présente les liens sensibles comme de vrais boutons tout en conservant
     * les corps historiques saisis en texte brut dans les réglages.
     */
    private static function format_message( string $body ): string {
        $escaped = esc_html( $body );
        $escaped = preg_replace_callback(
            '~https?://[^\s<]+~i',
            static function ( array $matches ): string {
                $url       = rtrim( $matches[0], '.,;:!?)' );
                $tail      = substr( $matches[0], strlen( $url ) );
                $safe_url  = esc_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
                if ( '' === $safe_url ) {
                    return $matches[0];
                }

                return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;"><tr><td bgcolor="#176b55" style="border-radius:9px;text-align:center;"><a href="' . $safe_url . '" style="display:inline-block;padding:13px 22px;color:#fff;text-decoration:none;font-size:15px;font-weight:700;">' . esc_html__( 'Open your secure link', 'givoly' ) . '</a></td></tr></table>' . esc_html( $tail );
            },
            $escaped
        );

        $paragraphs = preg_split( '/\n\s*\n/', trim( $escaped ) );
        $html = '';
        foreach ( $paragraphs as $paragraph ) {
            if ( '' === trim( $paragraph ) ) {
                continue;
            }
            $html .= '<p style="margin:0 0 18px;">' . nl2br( trim( $paragraph ), false ) . '</p>';
        }
        return $html;
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
