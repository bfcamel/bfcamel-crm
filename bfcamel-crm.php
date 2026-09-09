<?php
/**
 * Plugin Name: BfCamel CRM
 * Plugin URI: https://github.com/bfcamel/bfcamel-crm
 * Description: Native form builder and lightweight CRM for WordPress: forms, submissions, contacts, consent evidence and privacy tools.
 * Version: 0.3.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: BfCamel / People & Camels Charity Foundation
 * Author URI: https://bfcamel.ru/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bfcamel-crm
 * Domain Path: /languages
 * Update URI: https://github.com/bfcamel/bfcamel-crm
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * A GitHub source archive is extracted as bfcamel-crm-main and can therefore
 * be activated beside the real bfcamel-crm installation. Do not let two
 * copies register competing autoloaders, constants and hooks in one request.
 */
if ( defined( 'BFCAMEL_CRM_FILE' ) ) {
    $bfcamel_crm_duplicate_notice = static function () {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>BfCamel CRM:</strong> ' . esc_html( 'A second active copy was detected and safely stopped. Remove the duplicate plugin directory and install the official bfcamel-crm.zip release package.' ) . '</p></div>';
    };
    add_action( 'admin_notices', $bfcamel_crm_duplicate_notice );
    add_action( 'network_admin_notices', $bfcamel_crm_duplicate_notice );
    return;
}

define( 'BFCAMEL_CRM_VERSION', '0.3.2' );
define( 'BFCAMEL_CRM_FILE', __FILE__ );
define( 'BFCAMEL_CRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'BFCAMEL_CRM_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
    static function ( $class ) {
        $prefix = 'BfCamel\\CRM\\';
        if ( 0 !== strpos( $class, $prefix ) ) {
            return;
        }

        $relative = substr( $class, strlen( $prefix ) );
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
        $file     = BFCAMEL_CRM_DIR . 'src/' . $relative . '.php';

        if ( is_readable( $file ) ) {
            require_once $file;
        }
    }
);

register_activation_hook( __FILE__, array( 'BfCamel\\CRM\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BfCamel\\CRM\\Plugin', 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function () {
        BfCamel\CRM\Plugin::instance()->boot();
    }
);

add_action(
    'init',
    static function () {
        if ( is_callable( array( 'BfCamel\\CRM\\I18n', 'load' ) ) ) {
            BfCamel\CRM\I18n::load();
        }
    },
    0
);
