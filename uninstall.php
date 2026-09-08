<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$settings = get_option( 'bfcamel_crm_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
    return;
}

global $wpdb;
$tables = array(
    'forms',
    'form_revisions',
    'submission_tags',
    'tags',
    'submissions',
    'contacts',
    'contact_emails',
    'contact_phones',
    'contact_fields',
    'consent_events',
    'activity_log',
);

foreach ( $tables as $table ) {
    $name = $wpdb->prefix . 'bfcamel_crm_' . $table;
    $wpdb->query( "DROP TABLE IF EXISTS {$name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'bfcamel_crm_db_version' );
delete_option( 'bfcamel_crm_legal_documents' );
delete_option( 'bfcamel_crm_settings' );

$administrator = get_role( 'administrator' );
if ( $administrator ) {
    foreach ( array(
        'bfcamel_crm_manage_forms',
        'bfcamel_crm_view_submissions',
        'bfcamel_crm_edit_submissions',
        'bfcamel_crm_manage_contacts',
        'bfcamel_crm_manage_consents',
        'bfcamel_crm_export_data',
        'bfcamel_crm_manage_settings',
    ) as $capability ) {
        $administrator->remove_cap( $capability );
    }
}
