<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove optional plugin data and always revoke plugin capabilities.
 */
function bfcamel_crm_uninstall() {
    $settings    = get_option( 'bfcamel_crm_settings', array() );
    $delete_data = ! empty( $settings['delete_data_on_uninstall'] );

    if ( $delete_data ) {
        global $wpdb;
        $tables = array(
            'submission_tags',
            'contact_tags',
            'consent_events',
            'activity_log',
            'notes',
            'submissions',
            'contact_emails',
            'contact_phones',
            'contact_fields',
            'contacts',
            'form_revisions',
            'forms',
            'tags',
        );

        foreach ( $tables as $table ) {
            $name = $wpdb->prefix . 'bfcamel_crm_' . $table;
            $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit opt-in uninstall removes only allowlisted plugin tables and requires current database state.
        }

        delete_option( 'bfcamel_crm_db_version' );
        delete_option( 'bfcamel_crm_schema_error' );
        delete_option( 'bfcamel_crm_legal_documents' );
        delete_option( 'bfcamel_crm_settings' );
        delete_option( 'bfcamel_crm_role_permissions' );
        delete_option( 'bfcamel_crm_role_permissions_version' );
    }

    $capabilities = array(
        'bfcamel_crm_access',
        'bfcamel_crm_view_dashboard',
        'bfcamel_crm_manage_forms',
        'bfcamel_crm_view_submissions',
        'bfcamel_crm_edit_submissions',
        'bfcamel_crm_manage_contacts',
        'bfcamel_crm_manage_consents',
        'bfcamel_crm_export_data',
        'bfcamel_crm_manage_settings',
    );
    $roles = wp_roles();
    if ( $roles ) {
        foreach ( array_keys( $roles->roles ) as $role_slug ) {
            $role = get_role( $role_slug );
            if ( ! $role ) {
                continue;
            }
            foreach ( $capabilities as $capability ) {
                $role->remove_cap( $capability );
            }
        }
    }
}

bfcamel_crm_uninstall();
