<?php
namespace BfCamel\CRM;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lightweight translation fallback for Russian.
 *
 * The plugin historically shipped a stale/corrupt MO catalog. WordPress can
 * also prefer a global catalog from wp-content/languages/plugins over the
 * plugin-local file. To make the interface deterministic, Russian strings are
 * read directly from the bundled UTF-8 PO source and applied through gettext
 * filters. Other locales continue to use WordPress' normal gettext pipeline.
 */
final class I18n {
    private static $registered = false;
    private static $ru = null;

    public static function register() {
        if ( self::$registered ) {
            return;
        }

        self::$registered = true;

        add_filter( 'gettext', array( __CLASS__, 'translate' ), 999, 3 );
        add_filter( 'gettext_with_context', array( __CLASS__, 'translate_with_context' ), 999, 4 );
    }

    public static function is_russian_locale( $locale ) {
        $locale = strtolower( str_replace( '-', '_', (string) $locale ) );
        return 'ru' === $locale || 0 === strpos( $locale, 'ru_' );
    }

    public static function translate( $translation, $text, $domain ) {
        if ( 'bfcamel-crm' !== $domain || ! self::is_current_locale_russian() ) {
            return $translation;
        }

        $translations = self::russian_translations();

        if ( array_key_exists( $text, $translations ) ) {
            return $translations[ $text ];
        }

        // Never expose mojibake from a stale global MO catalog. If a source
        // string is missing from the PO file, fall back to clean English.
        return $text;
    }

    public static function translate_with_context( $translation, $text, $context, $domain ) {
        return self::translate( $translation, $text, $domain );
    }

    private static function is_current_locale_russian() {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        return self::is_russian_locale( $locale );
    }

    private static function russian_translations() {
        if ( null !== self::$ru ) {
            return self::$ru;
        }

        self::$ru = self::parse_po( BFCAMEL_CRM_DIR . 'languages/bfcamel-crm-ru_RU.po' );
        return self::$ru;
    }

    private static function parse_po( $path ) {
        if ( ! is_readable( $path ) ) {
            return array();
        }

        $lines = file( $path, FILE_IGNORE_NEW_LINES );
        if ( false === $lines ) {
            return array();
        }

        $translations = array();
        $msgid        = null;
        $msgstr       = null;
        $state        = '';

        $commit = static function () use ( &$translations, &$msgid, &$msgstr, &$state ) {
            if ( null !== $msgid && '' !== $msgid && null !== $msgstr && '' !== $msgstr ) {
                $translations[ $msgid ] = $msgstr;
            }

            $msgid  = null;
            $msgstr = null;
            $state  = '';
        };

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( '' === $line ) {
                $commit();
                continue;
            }

            if ( '#' === $line[0] ) {
                continue;
            }

            if ( 0 === strpos( $line, 'msgid ' ) ) {
                $commit();
                $msgid  = self::decode_po_string( substr( $line, 6 ) );
                $msgstr = '';
                $state  = 'msgid';
                continue;
            }

            if ( 0 === strpos( $line, 'msgstr ' ) ) {
                $msgstr = self::decode_po_string( substr( $line, 7 ) );
                $state  = 'msgstr';
                continue;
            }

            if ( '"' === $line[0] ) {
                $part = self::decode_po_string( $line );
                if ( 'msgid' === $state && null !== $msgid ) {
                    $msgid .= $part;
                } elseif ( 'msgstr' === $state && null !== $msgstr ) {
                    $msgstr .= $part;
                }
            }
        }

        $commit();

        return $translations;
    }

    private static function decode_po_string( $quoted ) {
        $quoted = trim( (string) $quoted );
        if ( '' === $quoted ) {
            return '';
        }

        $decoded = json_decode( $quoted, true );
        return is_string( $decoded ) ? $decoded : '';
    }
}
