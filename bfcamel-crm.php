<?php
/**
 * Plugin Name: BfCamel CRM
 * Plugin URI: https://github.com/bfcamel/bfcamel-crm
 * Description: Native form builder and lightweight CRM for WordPress: forms, submissions, contacts, consent evidence and privacy tools.
 * Version: 0.2.0
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

define( 'BFCAMEL_CRM_VERSION', '0.2.0' );
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

/*
 * Register the UTF-8 PO fallback before any plugin UI strings are translated.
 * Russian intentionally uses this source catalog directly so a stale/corrupt
 * MO file in wp-content/languages/plugins cannot produce mojibake.
 */
BfCamel\CRM\I18n::register();

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
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

        // Russian is served by the bundled UTF-8 PO fallback registered above.
        // Other locales continue to use the normal WordPress gettext loader.
        if ( ! BfCamel\CRM\I18n::is_russian_locale( $locale ) ) {
            load_plugin_textdomain( 'bfcamel-crm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }
    },
    0
);

/**
 * Supply localized strings to the admin form builder and translate technical
 * status codes that are intentionally stored in the database in English.
 *
 * English remains the source language; bundled translations currently include
 * Russian. The active locale follows WordPress site/user language settings.
 */
add_action(
    'admin_enqueue_scripts',
    static function ( $hook ) {
        if ( false === strpos( (string) $hook, 'bfcamel-crm' ) || ! wp_script_is( 'bfcamel-crm-admin', 'enqueued' ) ) {
            return;
        }

        $payload = array(
            'labels' => array(
                'submissionOnly'      => __( 'Submission only', 'bfcamel-crm' ),
                'contactName'         => __( 'Contact: name', 'bfcamel-crm' ),
                'contactEmail'        => __( 'Contact: email', 'bfcamel-crm' ),
                'contactPhone'        => __( 'Contact: phone', 'bfcamel-crm' ),
                'contactOrganization' => __( 'Contact: organization', 'bfcamel-crm' ),
                'contactCustom'       => __( 'Contact: custom field', 'bfcamel-crm' ),
                'personalDataDefault' => __( 'I consent to {personal_data_consent} and confirm that I have read the {privacy_policy}.', 'bfcamel-crm' ),
                'marketingDefault'    => __( 'I consent to receive informational and marketing messages under the {marketing_consent}.', 'bfcamel-crm' ),
                'sectionTitle'        => __( 'Section title', 'bfcamel-crm' ),
                'sectionHelp'         => __( 'Add explanatory text here.', 'bfcamel-crm' ),
            ),
            'types' => array(
                'text'                  => __( 'Text', 'bfcamel-crm' ),
                'email'                 => __( 'Email', 'bfcamel-crm' ),
                'tel'                   => __( 'Phone', 'bfcamel-crm' ),
                'number'                => __( 'Number', 'bfcamel-crm' ),
                'date'                  => __( 'Date', 'bfcamel-crm' ),
                'textarea'              => __( 'Textarea', 'bfcamel-crm' ),
                'select'                => __( 'Select', 'bfcamel-crm' ),
                'radio'                 => __( 'Radio', 'bfcamel-crm' ),
                'checkbox'              => __( 'Checkboxes', 'bfcamel-crm' ),
                'hidden'                => __( 'Hidden', 'bfcamel-crm' ),
                'consent_personal_data' => __( 'Personal data consent', 'bfcamel-crm' ),
                'consent_marketing'     => __( 'Marketing consent', 'bfcamel-crm' ),
                'html'                  => __( 'Text / heading', 'bfcamel-crm' ),
            ),
            'runtime' => array(
                'stats' => array(
                    'Forms'       => __( 'Forms', 'bfcamel-crm' ),
                    'Submissions' => __( 'Submissions', 'bfcamel-crm' ),
                    'Contacts'    => __( 'Contacts', 'bfcamel-crm' ),
                    'Consents'    => __( 'Consents', 'bfcamel-crm' ),
                ),
                'statuses' => array(
                    'new'          => __( 'New', 'bfcamel-crm' ),
                    'in_progress'  => __( 'In progress', 'bfcamel-crm' ),
                    'waiting'      => __( 'Waiting', 'bfcamel-crm' ),
                    'completed'    => __( 'Completed', 'bfcamel-crm' ),
                    'needs_review' => __( 'Needs review', 'bfcamel-crm' ),
                ),
                'sync' => array(
                    'pending'  => __( 'Pending', 'bfcamel-crm' ),
                    'created'  => __( 'Contact created', 'bfcamel-crm' ),
                    'linked'   => __( 'Contact linked', 'bfcamel-crm' ),
                    'conflict' => __( 'Conflict — needs review', 'bfcamel-crm' ),
                    'error'    => __( 'Error', 'bfcamel-crm' ),
                ),
                'consentTypes' => array(
                    'personal_data' => __( 'Personal data', 'bfcamel-crm' ),
                    'marketing'     => __( 'Marketing', 'bfcamel-crm' ),
                ),
                'consentStatuses' => array(
                    'granted' => __( 'Granted', 'bfcamel-crm' ),
                    'revoked' => __( 'Revoked', 'bfcamel-crm' ),
                    'unknown' => __( 'Unknown', 'bfcamel-crm' ),
                ),
            ),
        );

        $json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! $json ) {
            return;
        }

        wp_add_inline_script(
            'bfcamel-crm-admin',
            'window.BfCamelCRMI18n=' . $json . ';if(window.BfCamelCRMBuilder){window.BfCamelCRMBuilder.labels=Object.assign({},window.BfCamelCRMBuilder.labels||{},window.BfCamelCRMI18n.labels||{});window.BfCamelCRMBuilder.types=Object.assign({},window.BfCamelCRMBuilder.types||{},window.BfCamelCRMI18n.types||{});}',
            'after'
        );
    },
    100
);
