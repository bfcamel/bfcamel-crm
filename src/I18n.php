<?php
namespace BfCamel\CRM;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Loads translations through WordPress' standard text-domain pipeline.
 *
 * Keeping localization in PO/MO catalogs allows WordPress language packs,
 * user locales and translation tools to work without runtime gettext shims.
 */
final class I18n {
    public static function load() {
        load_plugin_textdomain(
            'bfcamel-crm',
            false,
            dirname( plugin_basename( BFCAMEL_CRM_FILE ) ) . '/languages'
        );
    }
}
