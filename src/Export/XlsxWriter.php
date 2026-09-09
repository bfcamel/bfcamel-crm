<?php
namespace BfCamel\CRM\Export;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class XlsxWriter {
    public static function create( $headers, $rows ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new \WP_Error( 'bfcamel_crm_zip_missing', __( 'The PHP ZIP extension is required for XLSX export.', 'bfcamel-crm' ) );
        }

        $path = wp_tempnam( 'bfcamel-crm-export.xlsx' );
        if ( ! $path ) {
            return new \WP_Error( 'bfcamel_crm_export_temp', __( 'Could not create a temporary export file.', 'bfcamel-crm' ) );
        }

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            @unlink( $path );
            return new \WP_Error( 'bfcamel_crm_export_zip', __( 'Could not create the XLSX archive.', 'bfcamel-crm' ) );
        }

        $written = $zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
        $written = $zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' ) && $written;
        $written = $zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="CRM Export" sheetId="1" r:id="rId1"/></sheets></workbook>' ) && $written;
        $written = $zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' ) && $written;
        $written = $zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>' ) && $written;
        $written = $zip->addFromString( 'xl/worksheets/sheet1.xml', self::sheet( $headers, $rows ) ) && $written;
        $closed = $zip->close();
        if ( ! $written || ! $closed ) {
            @unlink( $path );
            return new \WP_Error( 'bfcamel_crm_export_zip_write', __( 'Could not create the XLSX archive.', 'bfcamel-crm' ) );
        }

        return $path;
    }

    private static function sheet( $headers, $rows ) {
        $all_rows = array_merge( array( array_values( $headers ) ), array_values( $rows ) );
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
        foreach ( $all_rows as $row_index => $row ) {
            $number = $row_index + 1;
            $xml .= '<row r="' . $number . '">';
            foreach ( array_values( (array) $row ) as $column_index => $value ) {
                $reference = self::column( $column_index + 1 ) . $number;
                $safe = self::safe_cell( $value );
                $xml .= '<c r="' . $reference . '" t="inlineStr" s="' . ( 0 === $row_index ? '1' : '0' ) . '"><is><t xml:space="preserve">' . self::xml( $safe ) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $last_column = self::column( max( 1, count( $headers ) ) );
        $last_row = max( 1, count( $all_rows ) );
        return $xml . '</sheetData><autoFilter ref="A1:' . $last_column . $last_row . '"/></worksheet>';
    }

    private static function safe_cell( $value ) {
        $value = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
        if ( preg_match( '/^[=+\-@\t\r]/u', $value ) ) {
            $value = "'" . $value;
        }
        return self::truncate( $value, 32767 );
    }

    private static function xml( $value ) {
        return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8' );
    }

    private static function truncate( $value, $length ) {
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, $length, 'UTF-8' );
        }
        if ( function_exists( 'iconv_substr' ) ) {
            $truncated = iconv_substr( $value, 0, $length, 'UTF-8' );
            if ( false !== $truncated ) {
                return $truncated;
            }
        }
        if ( preg_match_all( '/./us', $value, $characters ) ) {
            return implode( '', array_slice( $characters[0], 0, $length ) );
        }
        return substr( $value, 0, $length );
    }

    private static function column( $number ) {
        $result = '';
        while ( $number > 0 ) {
            $number--;
            $result = chr( 65 + ( $number % 26 ) ) . $result;
            $number = (int) floor( $number / 26 );
        }
        return $result;
    }
}
