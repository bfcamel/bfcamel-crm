<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\Access\RoleManager;
use BfCamel\CRM\Export\Exporter;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Bootstrap {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct() {}

    public function register() {
        $admin = Admin::instance(); $submissions = SubmissionsPage::instance(); $contacts = ContactsPage::instance(); $workflow = WorkflowPage::instance();
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $admin, 'assets' ) );
        add_action( 'admin_notices', array( $workflow, 'legal_notice' ) );
        add_action( 'admin_post_bfcamel_crm_save_form', array( $admin, 'save_form' ) );
        add_action( 'admin_post_bfcamel_crm_save_settings', array( $admin, 'save_settings' ) );
        add_action( 'admin_post_bfcamel_crm_save_workflow', array( $workflow, 'save_workflow' ) );
        add_action( 'admin_post_bfcamel_crm_save_rules', array( $workflow, 'save_rules' ) );
        add_action( 'admin_post_bfcamel_crm_save_tag_catalog', array( $workflow, 'save_tags' ) );
        add_action( 'admin_post_bfcamel_crm_delete_tag', array( $workflow, 'delete_tag' ) );
        add_action( 'admin_post_bfcamel_crm_archive_form', array( $workflow, 'archive_form' ) );
        add_action( 'admin_post_bfcamel_crm_restore_form', array( $workflow, 'restore_form' ) );
        add_action( 'admin_post_bfcamel_crm_update_submission', array( $submissions, 'update_submission' ) );
        add_action( 'admin_post_bfcamel_crm_bulk_submissions', array( $submissions, 'bulk_submissions' ) );
        add_action( 'admin_post_bfcamel_crm_add_submission_note', array( $submissions, 'add_note' ) );
        add_action( 'admin_post_bfcamel_crm_create_contact', array( $contacts, 'create_contact' ) );
        add_action( 'admin_post_bfcamel_crm_update_contact', array( $contacts, 'update_contact' ) );
        add_action( 'admin_post_bfcamel_crm_bulk_contacts', array( $contacts, 'bulk_contacts' ) );
        add_action( 'admin_post_bfcamel_crm_add_contact_note', array( $contacts, 'add_note' ) );
        add_action( 'admin_post_bfcamel_crm_update_contact_tags', array( $contacts, 'update_tags' ) );
        add_action( 'admin_post_bfcamel_crm_update_contact_consents', array( $contacts, 'update_consents' ) );
        add_action( 'admin_post_bfcamel_crm_export', array( Exporter::class, 'handle' ) );
    }

    public function menu() {
        $admin = Admin::instance(); $submissions = SubmissionsPage::instance(); $contacts = ContactsPage::instance(); $workflow = WorkflowPage::instance();
        add_menu_page( __( 'BfCamel CRM', 'bfcamel-crm' ), __( 'BfCamel CRM', 'bfcamel-crm' ), RoleManager::ACCESS_CAPABILITY, 'bfcamel-crm', array( $this, 'landing' ), 'dashicons-feedback', 26 );
        add_submenu_page( 'bfcamel-crm', __( 'Dashboard', 'bfcamel-crm' ), __( 'Dashboard', 'bfcamel-crm' ), 'bfcamel_crm_view_dashboard', 'bfcamel-crm', '' );
        add_submenu_page( 'bfcamel-crm', __( 'Submissions', 'bfcamel-crm' ), __( 'Submissions', 'bfcamel-crm' ), 'bfcamel_crm_view_submissions', 'bfcamel-crm-submissions', array( $submissions, 'page' ) );
        add_submenu_page( 'bfcamel-crm', __( 'Contacts', 'bfcamel-crm' ), __( 'Contacts', 'bfcamel-crm' ), 'bfcamel_crm_manage_contacts', 'bfcamel-crm-contacts', array( $contacts, 'page' ) );
        add_submenu_page( 'bfcamel-crm', __( 'Forms', 'bfcamel-crm' ), __( 'Forms', 'bfcamel-crm' ), 'bfcamel_crm_manage_forms', 'bfcamel-crm-forms', array( $admin, 'forms_page' ) );
        add_submenu_page( 'bfcamel-crm', __( 'CRM configuration', 'bfcamel-crm' ), __( 'Workflow', 'bfcamel-crm' ), 'bfcamel_crm_manage_settings', 'bfcamel-crm-workflow', array( $workflow, 'page' ) );
        add_submenu_page( 'bfcamel-crm', __( 'Settings', 'bfcamel-crm' ), __( 'Settings', 'bfcamel-crm' ), 'bfcamel_crm_manage_settings', 'bfcamel-crm-settings', array( $admin, 'settings_page' ) );
    }

    public function landing() {
        if ( current_user_can( 'bfcamel_crm_view_dashboard' ) ) { DashboardPage::render(); return; }
        if ( current_user_can( 'bfcamel_crm_manage_forms' ) ) { Admin::instance()->forms_page(); return; }
        if ( current_user_can( 'bfcamel_crm_view_submissions' ) ) { SubmissionsPage::instance()->page(); return; }
        if ( current_user_can( 'bfcamel_crm_manage_contacts' ) ) { ContactsPage::instance()->page(); return; }
        wp_die( esc_html__( 'You do not have permission to access this page.', 'bfcamel-crm' ) );
    }
}
