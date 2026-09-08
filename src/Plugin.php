<?php
namespace BfCamel\CRM;

use BfCamel\CRM\Admin\Bootstrap;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Renderer;
use BfCamel\CRM\Forms\SubmissionHandler;
use BfCamel\CRM\Privacy\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {
    private static $instance = null;
    private $booted = false;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    public function boot() {
        if ( $this->booted ) {
            return;
        }

        $this->booted = true;

        Schema::maybe_upgrade();

        Renderer::instance()->register();
        SubmissionHandler::instance()->register();
        Privacy::instance()->register();

        if ( is_admin() ) {
            Bootstrap::instance()->register();
        }
    }

    public static function activate() {
        Schema::install();
        self::grant_capabilities();
        self::seed_defaults();
    }

    public static function deactivate() {
        // No scheduled jobs in 0.1.0.
    }

    private static function grant_capabilities() {
        $administrator = get_role( 'administrator' );
        if ( ! $administrator ) {
            return;
        }

        foreach ( self::capabilities() as $capability ) {
            $administrator->add_cap( $capability );
        }
    }

    public static function capabilities() {
        return array(
            'bfcamel_crm_manage_forms',
            'bfcamel_crm_view_submissions',
            'bfcamel_crm_edit_submissions',
            'bfcamel_crm_manage_contacts',
            'bfcamel_crm_manage_consents',
            'bfcamel_crm_export_data',
            'bfcamel_crm_manage_settings',
        );
    }

    private static function seed_defaults() {
        if ( false === get_option( 'bfcamel_crm_legal_documents', false ) ) {
            add_option(
                'bfcamel_crm_legal_documents',
                array(
                    'personal_data_consent' => array(
                        'url'     => '',
                        'version' => '',
                    ),
                    'privacy_policy' => array(
                        'url'     => '',
                        'version' => '',
                    ),
                    'marketing_consent' => array(
                        'url'     => '',
                        'version' => '',
                    ),
                ),
                '',
                false
            );
        }

        if ( false === get_option( 'bfcamel_crm_settings', false ) ) {
            add_option(
                'bfcamel_crm_settings',
                array(
                    'store_ip'                 => false,
                    'delete_data_on_uninstall' => false,
                ),
                '',
                false
            );
        }
    }
}
