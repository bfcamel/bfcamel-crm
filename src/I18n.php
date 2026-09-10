<?php
namespace BfCamel\CRM;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class I18n {
    /**
     * Registers the bundled translation directory after WordPress is ready.
     */
    public static function load() {
        load_plugin_textdomain(
            'bfcamel-crm',
            false,
            dirname( plugin_basename( BFCAMEL_CRM_FILE ) ) . '/languages'
        );
    }
}
