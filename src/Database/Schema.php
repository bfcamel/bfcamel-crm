<?php
namespace BfCamel\CRM\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Schema {
    const VERSION = '6';
    const OPTION  = 'bfcamel_crm_db_version';
    const ERROR_OPTION = 'bfcamel_crm_schema_error';

    private static $install_errors = array();

    public static function maybe_upgrade() {
        if ( self::VERSION === (string) get_option( self::OPTION, '' ) ) {
            return true;
        }
        if ( get_transient( 'bfcamel_crm_schema_lock' ) ) {
            return new \WP_Error( 'bfcamel_crm_schema_locked', __( 'Another BfCamel CRM database update is already running.', 'bfcamel-crm' ) );
        }
        set_transient( 'bfcamel_crm_schema_lock', 1, MINUTE_IN_SECONDS );
        try {
            return self::install();
        } finally {
            delete_transient( 'bfcamel_crm_schema_lock' );
        }
    }

    public static function is_current() {
        return self::VERSION === (string) get_option( self::OPTION, '' );
    }

    public static function readiness_message() {
        $details = get_option( self::ERROR_OPTION, array() );
        if ( current_user_can( 'manage_options' ) && is_array( $details ) && $details ) {
            return sprintf(
                /* translators: %s: database error details. */
                __( 'BfCamel CRM database upgrade failed: %s', 'bfcamel-crm' ),
                implode( '; ', array_map( 'sanitize_text_field', $details ) )
            );
        }
        if ( current_user_can( 'manage_options' ) && is_string( $details ) && '' !== $details ) {
            return $details;
        }
        return __( 'The BfCamel CRM database update is not complete.', 'bfcamel-crm' );
    }

    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        self::$install_errors = array();
        $cc = 'ENGINE=InnoDB ' . $wpdb->get_charset_collate();

        $forms = self::table( 'forms' );
        self::run_delta(
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
        self::run_delta(
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
        self::run_delta(
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
        self::run_delta(
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
        self::run_delta(
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
        self::run_delta(
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
        self::run_delta(
            "CREATE TABLE {$submissions} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                form_id BIGINT UNSIGNED NOT NULL,
                revision_id BIGINT UNSIGNED NOT NULL,
                submission_uuid CHAR(36) NOT NULL,
                contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(40) NOT NULL DEFAULT 'new',
                contact_sync_status VARCHAR(40) NOT NULL DEFAULT '',
                assigned_to BIGINT UNSIGNED NOT NULL DEFAULT 0,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
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
                KEY assigned_to (assigned_to),
                KEY priority (priority),
                KEY submitted_at (submitted_at)
            ) {$cc};"
        );

        $consents = self::table( 'consent_events' );
        self::run_delta(
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
                source_type VARCHAR(20) NOT NULL DEFAULT 'form',
                recorded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
                event_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY contact_id (contact_id),
                KEY submission_id (submission_id),
                KEY consent_type (consent_type),
                KEY status (status),
                KEY source_type (source_type),
                KEY recorded_by (recorded_by),
                KEY event_at (event_at)
            ) {$cc};"
        );

        $tags = self::table( 'tags' );
        self::run_delta(
            "CREATE TABLE {$tags} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY name (name)
            ) {$cc};"
        );

        $submission_tags = self::table( 'submission_tags' );
        self::run_delta(
            "CREATE TABLE {$submission_tags} (
                submission_id BIGINT UNSIGNED NOT NULL,
                tag_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (submission_id,tag_id),
                KEY tag_id (tag_id)
            ) {$cc};"
        );

        $contact_tags = self::table( 'contact_tags' );
        self::run_delta(
            "CREATE TABLE {$contact_tags} (
                contact_id BIGINT UNSIGNED NOT NULL,
                tag_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (contact_id,tag_id),
                KEY tag_id (tag_id)
            ) {$cc};"
        );

        $notes = self::table( 'notes' );
        self::run_delta(
            "CREATE TABLE {$notes} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(30) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                note_text LONGTEXT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY entity (entity_type,entity_id),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) {$cc};"
        );

        $activity = self::table( 'activity_log' );
        self::run_delta(
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

        self::ensure_transactional_tables();

        $verification = self::verify();
        if ( is_wp_error( $verification ) ) {
            self::$install_errors[] = $verification->get_error_message();
        }

        $errors = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', self::$install_errors ) ) ) );
        if ( $errors ) {
            $error = new \WP_Error(
                'bfcamel_crm_schema_upgrade_failed',
                sprintf(
                    /* translators: %s: database error details. */
                    __( 'BfCamel CRM database upgrade failed: %s', 'bfcamel-crm' ),
                    implode( '; ', $errors )
                )
            );
            update_option( self::ERROR_OPTION, $errors, false );
            return $error;
        }

        update_option( self::OPTION, self::VERSION, false );
        delete_option( self::ERROR_OPTION );
        return true;
    }

    private static function run_delta( $sql ) {
        global $wpdb;
        $wpdb->last_error = '';
        dbDelta( $sql );
        if ( $wpdb->last_error ) {
            self::$install_errors[] = $wpdb->last_error;
        }
    }

    private static function ensure_transactional_tables() {
        global $wpdb;

        foreach ( self::required_tables() as $suffix ) {
            $table = self::table( $suffix );
            $engine = self::table_engine( $table );
            if ( ! $engine || 0 === strcasecmp( 'InnoDB', $engine ) ) {
                continue;
            }

            $wpdb->last_error = '';
            $changed = $wpdb->query( "ALTER TABLE `{$table}` ENGINE=InnoDB" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( false === $changed || $wpdb->last_error ) {
                self::$install_errors[] = $wpdb->last_error ?: sprintf( 'Could not enable transactions for %s.', $table );
            }
        }
    }

    public static function verify() {
        global $wpdb;

        $required_tables = self::required_tables();
        $missing = array();

        foreach ( $required_tables as $suffix ) {
            $table = self::table( $suffix );
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
            if ( $table !== $found ) {
                $missing[] = $table;
            }
        }

        if ( ! $missing ) {
            $required_columns = array(
                'submissions'     => array( 'assigned_to', 'priority' ),
                'tags'            => array( 'id', 'name', 'slug' ),
                'submission_tags' => array( 'submission_id', 'tag_id' ),
                'contact_tags'    => array( 'contact_id', 'tag_id' ),
                'consent_events'  => array( 'source_type', 'recorded_by' ),
                'notes'           => array( 'entity_type', 'entity_id', 'note_text', 'user_id' ),
                'activity_log'    => array( 'meta_json', 'user_id' ),
            );
            foreach ( $required_columns as $suffix => $expected ) {
                $table = self::table( $suffix );
                $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                foreach ( $expected as $column ) {
                    if ( ! in_array( $column, (array) $columns, true ) ) {
                        $missing[] = $table . '.' . $column;
                    }
                }
            }
        }

        if ( ! $missing ) {
            $required_indexes = array(
                array( 'tags', 'slug', array( 'slug' ) ),
                array( 'submission_tags', 'PRIMARY', array( 'submission_id', 'tag_id' ) ),
                array( 'contact_tags', 'PRIMARY', array( 'contact_id', 'tag_id' ) ),
            );
            foreach ( $required_indexes as $index ) {
                $table = self::table( $index[0] );
                if ( $index[2] !== self::index_columns( $table, $index[1] ) || ! self::index_is_unique( $table, $index[1] ) ) {
                    $missing[] = $table . '.' . $index[1];
                }
            }
        }

        if ( ! $missing ) {
            foreach ( $required_tables as $suffix ) {
                $table = self::table( $suffix );
                $engine = self::table_engine( $table );
                if ( 0 !== strcasecmp( 'InnoDB', (string) $engine ) ) {
                    $missing[] = $table . ' (InnoDB)';
                }
            }
        }

        if ( $missing ) {
            return new \WP_Error(
                'bfcamel_crm_schema_incomplete',
                sprintf(
                    /* translators: %s: comma-separated database tables or columns. */
                    __( 'Required database objects are missing: %s', 'bfcamel-crm' ),
                    implode( ', ', $missing )
                )
            );
        }

        return true;
    }

    private static function required_tables() {
        return array(
            'forms',
            'form_revisions',
            'contacts',
            'contact_emails',
            'contact_phones',
            'contact_fields',
            'submissions',
            'consent_events',
            'tags',
            'submission_tags',
            'contact_tags',
            'notes',
            'activity_log',
        );
    }

    private static function table_engine( $table ) {
        global $wpdb;
        $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ) );
        return $status && isset( $status->Engine ) ? (string) $status->Engine : '';
    }

    private static function index_columns( $table, $index_name ) {
        global $wpdb;
        $rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $columns = array();
        foreach ( (array) $rows as $row ) {
            if ( isset( $row['Key_name'], $row['Column_name'] ) && (string) $row['Key_name'] === (string) $index_name ) {
                $sequence = isset( $row['Seq_in_index'] ) ? absint( $row['Seq_in_index'] ) : count( $columns ) + 1;
                $columns[ $sequence ] = (string) $row['Column_name'];
            }
        }
        ksort( $columns );
        return array_values( $columns );
    }

    private static function index_is_unique( $table, $index_name ) {
        global $wpdb;
        $rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ( (array) $rows as $row ) {
            if ( isset( $row['Key_name'], $row['Non_unique'] ) && (string) $row['Key_name'] === (string) $index_name ) {
                return 0 === absint( $row['Non_unique'] );
            }
        }
        return false;
    }

    public static function admin_notice() {
        if ( ! get_option( self::ERROR_OPTION, array() ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="notice notice-error"><p>' . esc_html( self::readiness_message() ) . '</p></div>';
    }

    public static function begin_transaction() {
        global $wpdb;
        return false !== $wpdb->query( 'START TRANSACTION' );
    }

    public static function commit() {
        global $wpdb;
        return false !== $wpdb->query( 'COMMIT' );
    }

    public static function rollback() {
        global $wpdb;
        $wpdb->query( 'ROLLBACK' );
    }

    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'bfcamel_crm_' . sanitize_key( $name );
    }

    public static function log( $entity_type, $entity_id, $event_type, $message, $meta = array(), $user_id = 0 ) {
        global $wpdb;

        return (bool) $wpdb->insert(
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
