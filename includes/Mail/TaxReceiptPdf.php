<?php
/**
 * Générateur PDF autonome pour les récapitulatifs fiscaux.
 *
 * Le plugin reste distribuable sans dépendance Composer. Le document est
 * volontairement textuel et personnalisable via les réglages Givoly.
 *
 * @package Givoly\Mail
 */

namespace Givoly\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TaxReceiptPdf {

    /**
     * Génère un PDF simple, lisible par les lecteurs PDF courants.
     *
     * @param string $title  Titre du document.
     * @param string $body   Corps du document.
     * @param string $footer Pied de page.
     */
    public static function generate( string $title, string $body, string $footer ): string {
        $lines = self::prepare_lines( $title, $body, $footer );
        $pages = array_chunk( $lines, 38 );

        if ( empty( $pages ) ) {
            $pages = [ [ '' ] ];
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        $page_references = [];
        $next_object     = 4;

        foreach ( $pages as $page_lines ) {
            $page_object    = $next_object++;
            $content_object = $next_object++;
            $page_references[] = $page_object . ' 0 R';

            $stream = self::build_page_stream( $page_lines );
            $objects[ $content_object ] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[ $page_object ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $content_object . ' 0 R >>';
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $page_references ) . '] /Count ' . count( $page_references ) . ' >>';

        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [ 0 ];

        for ( $index = 1; $index <= count( $objects ); $index++ ) {
            $offsets[ $index ] = strlen( $pdf );
            $pdf .= $index . " 0 obj\n" . $objects[ $index ] . "\nendobj\n";
        }

        $xref_offset = strlen( $pdf );
        $pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ( $index = 1; $index <= count( $objects ); $index++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $index ] );
        }

        $pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref_offset . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * @return string[]
     */
    private static function prepare_lines( string $title, string $body, string $footer ): array {
        $sections = [ trim( $title ), trim( $body ), trim( $footer ) ];
        $lines    = [];

        foreach ( $sections as $section_index => $section ) {
            if ( $section_index > 0 ) {
                $lines[] = '';
            }

            foreach ( preg_split( '/\r\n|\r|\n/', $section ) ?: [ '' ] as $line ) {
                $wrapped = wordwrap( $line, 92, "\n", false );
                foreach ( explode( "\n", $wrapped ) as $wrapped_line ) {
                    $lines[] = $wrapped_line;
                }
            }
        }

        return $lines;
    }

    /**
     * @param string[] $lines
     */
    private static function build_page_stream( array $lines ): string {
        $stream = "BT\n50 790 Td\n";

        foreach ( $lines as $index => $line ) {
            $stream .= ( 0 === $index ? "/F1 16 Tf\n" : "/F1 10 Tf\n" );
            $stream .= '(' . self::escape_pdf_text( $line ) . ") Tj\n0 -18 Td\n";
        }

        return $stream . "ET";
    }

    private static function escape_pdf_text( string $text ): string {
        if ( function_exists( 'iconv' ) ) {
            $converted = iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );
            if ( false !== $converted ) {
                $text = $converted;
            }
        } else {
            $text = preg_replace( '/[^\x20-\x7E]/', '?', $text ) ?? $text;
        }

        return str_replace( [ '\\', '(', ')' ], [ '\\\\', '\\(', '\\)' ], $text );
    }
}
