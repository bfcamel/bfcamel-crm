<?php
namespace BfCamel\CRM\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Schema {
    const VERSION = '1';
    const OPTION  = 'bfcamel_crm_db_version';

    public static function maybe_upgrade() {
        if ( self::VERSION !== (string) get_option( self::OPTION, '' ) ) {
            self::install();
        }
    }

    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $cc = $wpdb->get_charset_collate();

        $forms = self::table( 'forms' );
        dbDelta(
            "CREATE TABLE {$forms} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'publish',
                current_revision_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                settings_json LONGTEXT NULL,
                created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY status (status),
                KEY current_revision_id (current_revision_id)
            ) {$cc};"
        );

        $revisions = self::table( 'form_revisions' );
        dbDelta(
            "CREATE TABLE {$revisions} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                form_id BIGINT UNSIGNED NOT NULL,
                version BIGINT UNSIGNED NOT NULL,
                schema_json LONGTEXT NOT NULL,
                settings_json LONGTEXT NULL,
                created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY form_version (form_id,version),
                KEY form_id (form_id),
                KEY created_at (created_at)
            ) {$cc};"
        );

        $contacts = self::table( 'contacts' );
        dbDelta(
            "CREATE TABLE {$contacts} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                display_name VARCHAR(190) NOT NULL,
                organization VARCHAR(190) NOT NULL DEFAULT '',
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY display_name (display_name),
                KEY organization (organization),
                KEY status (status),
                KEY updated_at (updated_at)
            ) {$cc};"
        );

        $emails = self::table( 'contact_emails' );
        dbDelta(
            "CREATE TABLE {$emails} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contact_id BIGINT UNSIGNED NOT NULL,
                value VARCHAR(190) NOT NULL,
                normalized VARCHAR(190) NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY contact_id (contact_id),
                KEY normalized (normalized),
                KEY primary_contact (contact_id,is_primary)
            ) {$cc};"
        );

        $phones = self::table( 'contact_phones' );
        dbDelta(
            "CREATE TABLE {$phones} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contact_id BIGINT UNSIGNED NOT NULL,
                value VARCHAR(80) NOT NULL,
                normalized VARCHAR(40) NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY contact_id (contact_id),
                KEY normalized (normalized),
                KEY primary_contact (contact_id,is_primary)
            ) {$cc};"
        );

        $contact_fields = self::table( 'contact_fields' );
        dbDelta(
            "CREATE TABLE {$contact_fields} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contact_id BIGINT UNSIGNED NOT NULL,
                field_key VARCHAR(120) NOT NULL,
                field_value LONGTEXT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY contact_field (contact_id,field_key),
                KEY field_key (field_key),
                KEY contact_id (contact_id)
            ) {$cc};"
        );

        $submissions = self::table( 'submissions' );
        dbDelta(
            "CREATE TABLE {$submissions} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                form_id BIGINT UNSIGNED NOT NULL,
                revision_id BIGINT UNSIGNED NOT NULL,
                submission_uuid CHAR(36) NOT NULL,
                contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(40) NOT NULL DEFAULT 'new',
                contact_sync_status VARCHAR(40) NOT NULL DEFAULT '',
                payload_json LONGTEXT NOT NULL,
                source_url TEXT NULL,
                source_ip VARCHAR(100) NOT NULL DEFAULT '',
                user_agent TEXT NULL,
                submitted_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY submission_uuid (submission_uuid),
                KEY form_id (form_id),
                KEY revision_id (revision_id),
                KEY contact_id (contact_id),
                KEY status (status),
                KEY contact_sync_status (contact_sync_status),
                KEY submitted_at (submitted_at)
            ) {$cc};"
        );

        $consents = self::table( 'consent_events' );
        dbDelta(
            "CREATE TABLE {$consents} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                submission_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                consent_type VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL,
                form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                revision_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                documents_json LONGTEXT NULL,
                source_url TEXT NULL,
                source_ip VARCHAR(100) NOT NULL DEFAULT '',
                user_agent TEXT NULL,
                event_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY contact_id (contact_id),
                KEY submission_id (submission_id),
                KEY consent_type (consent_type),
                KEY status (status),
                KEY event_at (event_at)
            ) {$cc};"
        );

        $activity = self::table( 'activity_log' );
        dbDelta(
            "CREATE TABLE {$activity} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(30) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                meta_json LONGTEXT NULL,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY entity (entity_type,entity_id),
                KEY event_type (event_type),
                KEY created_at (created_at)
            ) {$cc};"
        );

        update_option( self::OPTION, self::VERSION, false );
    }

    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'bfcamel_crm_' . sanitize_key( $name );
    }

    public static function log( $entity_type, $entity_id, $event_type, $message, $meta = array(), $user_id = 0 ) {
        global $wpdb;

        $wpdb->insert(
            self::table( 'activity_log' ),
            array(
                'entity_type' => sanitize_key( $entity_type ),
                'entity_id'   => absint( $entity_id ),
                'event_type'  => sanitize_key( $event_type ),
                'message'     => sanitize_text_field( $message ),
                'meta_json'   => wp_json_encode( $meta ),
                'user_id'     => absint( $user_id ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
        );
    }
}
