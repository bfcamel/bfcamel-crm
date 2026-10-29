<?php
namespace BfCamel\CRM\Privacy;

use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\NoteService;
use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Privacy {
    const PAGE_SIZE = 50;

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function register() {
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
        add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
    }

    public function register_exporter( $exporters ) {
        $exporters['bfcamel-crm'] = array(
            'exporter_friendly_name' => __( 'BfCamel CRM', 'bfcamel-crm' ),
            'callback'               => array( $this, 'export_personal_data' ),
        );
        return $exporters;
    }

    public function register_eraser( $erasers ) {
        $erasers['bfcamel-crm'] = array(
            'eraser_friendly_name' => __( 'BfCamel CRM', 'bfcamel-crm' ),
            'callback'             => array( $this, 'erase_personal_data' ),
        );
        return $erasers;
    }

    public function export_personal_data( $email_address, $page = 1 ) {
        if ( ! Schema::is_current() ) {
            return array( 'data' => array(), 'done' => true );
        }

        $email_address = ContactService::normalize_email( $email_address );
        $contact_ids = $email_address ? ContactService::find_contact_ids_by_email_value( $email_address ) : array();
        if ( ! $email_address ) {
            return array( 'data' => array(), 'done' => true );
        }

        $page = max( 1, absint( $page ) );
        $data = array();
        if ( 1 === $page ) {
            foreach ( $contact_ids as $contact_id ) {
                $contact_item = $this->contact_export_item( $contact_id );
                if ( $contact_item ) {
                    $data[] = $contact_item;
                }
            }
        }

        $submissions = $this->paged_submissions( $email_address, $contact_ids, $page );
        if ( is_wp_error( $submissions ) ) return $submissions;
        foreach ( $submissions['rows'] as $submission ) {
            $payload = json_decode( (string) $submission->payload_json, true );
            $payload = is_array( $payload ) ? $payload : array();
            $items   = array(
                array( 'name' => __( 'Submitted at', 'bfcamel-crm' ), 'value' => $submission->submitted_at ),
                array( 'name' => __( 'Source URL', 'bfcamel-crm' ), 'value' => $submission->source_url ),
                array( 'name' => __( 'IP address', 'bfcamel-crm' ), 'value' => $submission->source_ip ),
                array( 'name' => __( 'Browser information', 'bfcamel-crm' ), 'value' => $submission->user_agent ),
            );
            foreach ( $payload as $key => $value ) {
                $items[] = array( 'name' => (string) $key, 'value' => $this->export_value( $value ) );
            }
            foreach ( NoteService::for_entity( 'submission', $submission->id ) as $note ) {
                $items[] = array( 'name' => __( 'Internal note', 'bfcamel-crm' ), 'value' => $note->note_text );
            }
            // Public evidence can belong to the submission without asserting a contact identity.
            foreach ( $this->submission_consents( $submission->id ) as $event ) {
                if ( ! in_array( absint( $event->contact_id ), $contact_ids, true ) ) {
                    $items[] = array( 'name' => __( 'Consent evidence', 'bfcamel-crm' ), 'value' => $this->export_value( $event ) );
                }
            }
            if ( ! in_array( absint( $submission->contact_id ), $contact_ids, true ) ) {
                $items[] = array( 'name' => __( 'Event details', 'bfcamel-crm' ), 'value' => $this->export_value( $this->submission_activity( $submission->id ) ) );
            }
            $data[] = array(
                'group_id'    => 'bfcamel-crm-submissions',
                'group_label' => __( 'BfCamel CRM submissions', 'bfcamel-crm' ),
                'item_id'     => 'submission-' . absint( $submission->id ),
                'data'        => $items,
            );
        }

        $consents = $this->paged_rows( 'consent_events', $contact_ids, $page );
        foreach ( $consents['rows'] as $event ) {
            $data[] = array(
                'group_id'    => 'bfcamel-crm-consents',
                'group_label' => __( 'BfCamel CRM consent history', 'bfcamel-crm' ),
                'item_id'     => 'consent-' . absint( $event->id ),
                'data'        => array(
                    array( 'name' => __( 'Consent type', 'bfcamel-crm' ), 'value' => $event->consent_type ),
                    array( 'name' => __( 'Consent status', 'bfcamel-crm' ), 'value' => $event->status ),
                    array( 'name' => __( 'Recorded at', 'bfcamel-crm' ), 'value' => $event->event_at ),
                    array( 'name' => __( 'Source URL', 'bfcamel-crm' ), 'value' => $event->source_url ),
                    array( 'name' => __( 'IP address', 'bfcamel-crm' ), 'value' => $event->source_ip ),
                    array( 'name' => __( 'Browser information', 'bfcamel-crm' ), 'value' => $event->user_agent ),
                    array( 'name' => __( 'Consent evidence', 'bfcamel-crm' ), 'value' => $this->export_value( json_decode( (string) $event->documents_json, true ) ) ),
                ),
            );
        }

        $activity = $this->paged_activity( $contact_ids, $page );
        foreach ( $activity['rows'] as $event ) {
            $data[] = array(
                'group_id'    => 'bfcamel-crm-activity',
                'group_label' => __( 'BfCamel CRM activity history', 'bfcamel-crm' ),
                'item_id'     => 'activity-' . absint( $event->id ),
                'data'        => array(
                    array( 'name' => __( 'Event type', 'bfcamel-crm' ), 'value' => $event->event_type ),
                    array( 'name' => __( 'Recorded at', 'bfcamel-crm' ), 'value' => $event->created_at ),
                    array( 'name' => __( 'Event details', 'bfcamel-crm' ), 'value' => $this->export_value( json_decode( (string) $event->meta_json, true ) ) ),
                ),
            );
        }

        return array(
            'data' => $data,
            'done' => ! $submissions['has_more'] && ! $consents['has_more'] && ! $activity['has_more'],
        );
    }

    public function erase_personal_data( $email_address, $page = 1 ) {
        if ( ! Schema::is_current() ) {
            return $this->erase_error( __( 'BfCamel CRM data could not be erased because the database update is incomplete.', 'bfcamel-crm' ) );
        }

        $email_address = ContactService::normalize_email( $email_address );
        $contact_ids = $email_address ? ContactService::find_contact_ids_by_email_value( $email_address ) : array();
        if ( ! $email_address ) {
            return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
        }

        $page        = max( 1, absint( $page ) );
        $submissions = $this->paged_submissions( $email_address, $contact_ids, $page );
        if ( is_wp_error( $submissions ) ) return $this->erase_error( $submissions->get_error_message() );

        if ( ! Schema::begin_transaction() ) {
            return $this->erase_error( __( 'BfCamel CRM could not start a privacy erasure transaction.', 'bfcamel-crm' ) );
        }

        foreach ( $submissions['rows'] as $submission ) {
            if ( ! $this->scrub_submission( $submission ) ) {
                Schema::rollback();
                return $this->erase_error( __( 'BfCamel CRM could not anonymize all submission data.', 'bfcamel-crm' ) );
            }
        }

        if ( $submissions['has_more'] ) {
            if ( ! Schema::commit() ) {
                Schema::rollback();
                return $this->erase_error( __( 'BfCamel CRM could not save the privacy erasure batch.', 'bfcamel-crm' ) );
            }
            return array(
                'items_removed'  => true,
                'items_retained' => false,
                'messages'       => array( __( 'BfCamel CRM anonymized one batch of submission data. Additional batches remain.', 'bfcamel-crm' ) ),
                'done'           => false,
            );
        }

        foreach ( $contact_ids as $contact_id ) {
            if ( ! $this->scrub_contact_relations( $contact_id ) || ! ContactService::anonymize( $contact_id ) ) {
                Schema::rollback();
                return $this->erase_error( __( 'BfCamel CRM could not anonymize all contact data.', 'bfcamel-crm' ) );
            }
        }

        if ( ! Schema::commit() ) {
            Schema::rollback();
            return $this->erase_error( __( 'BfCamel CRM could not save the privacy erasure result.', 'bfcamel-crm' ) );
        }

        return array(
            'items_removed'  => true,
            'items_retained' => true,
            'messages'       => array( __( 'BfCamel CRM removed contact identifiers, submission contents, internal notes and technical metadata. Anonymized consent timestamps and document evidence were retained for audit purposes.', 'bfcamel-crm' ) ),
            'done'           => true,
        );
    }

    public function privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }
        $content = '<p>' . esc_html__( 'If forms created with BfCamel CRM are used on this site, the plugin may store submitted form data, CRM contacts, internal notes, consent events, source URLs and browser information. IP address storage is optional and disabled by default. Site owners choose whether to provide links to legal documents and remain responsible for the legal basis, consent wording and retention periods used on their site.', 'bfcamel-crm' ) . '</p>';
        wp_add_privacy_policy_content( __( 'BfCamel CRM', 'bfcamel-crm' ), wp_kses_post( wpautop( $content ) ) );
    }

    private function contact_export_item( $contact_id ) {
        $contact = ContactService::get( $contact_id );
        if ( ! $contact ) {
            return null;
        }
        $items = array(
            array( 'name' => __( 'Display name', 'bfcamel-crm' ), 'value' => $contact->display_name ),
            array( 'name' => __( 'Organization', 'bfcamel-crm' ), 'value' => $contact->organization ),
            array( 'name' => __( 'Status', 'bfcamel-crm' ), 'value' => $contact->status ),
        );
        foreach ( ContactService::get_emails( $contact_id ) as $email ) {
            $items[] = array( 'name' => __( 'Email', 'bfcamel-crm' ), 'value' => $email->value );
        }
        foreach ( ContactService::get_phones( $contact_id ) as $phone ) {
            $items[] = array( 'name' => __( 'Phone', 'bfcamel-crm' ), 'value' => $phone->value );
        }
        foreach ( ContactService::get_custom_fields( $contact_id ) as $key => $value ) {
            $items[] = array( 'name' => $key, 'value' => $value );
        }
        foreach ( NoteService::for_entity( 'contact', $contact_id ) as $note ) {
            $items[] = array( 'name' => __( 'Internal note', 'bfcamel-crm' ), 'value' => $note->note_text );
        }
        return array(
            'group_id'    => 'bfcamel-crm-contact',
            'group_label' => __( 'BfCamel CRM contact', 'bfcamel-crm' ),
            'item_id'     => 'contact-' . absint( $contact_id ),
            'data'        => $items,
        );
    }

    /** Scan fixed-size batches, including detached, conflict and submission-only email fields. */
    private function paged_submissions( $email_address, $contact_ids, $page ) {
        global $wpdb;
        $offset = ( max( 1, absint( $page ) ) - 1 ) * self::PAGE_SIZE;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT s.*,r.schema_json FROM %i s LEFT JOIN %i r ON r.id=s.revision_id ORDER BY s.id ASC LIMIT %d OFFSET %d',
            Schema::table( 'submissions' ), Schema::table( 'form_revisions' ), self::PAGE_SIZE + 1, $offset
        ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded scan of legacy payloads requires fresh rows, without a destructive backfill.
        if ( null === $rows || $wpdb->last_error ) return new \WP_Error( 'bfcamel_crm_privacy_scan', __( 'BfCamel CRM could not anonymize all submission data.', 'bfcamel-crm' ) );
        $has_more = count( $rows ) > self::PAGE_SIZE;
        $matched = array();
        foreach ( array_slice( $rows, 0, self::PAGE_SIZE ) as $row ) {
            if ( in_array( absint( $row->contact_id ), $contact_ids, true ) || $this->submission_has_email( $row, $email_address ) ) $matched[] = $row;
        }
        return array( 'rows' => $matched, 'has_more' => $has_more );
    }

    private function submission_has_email( $submission, $email_address ) {
        $schema = json_decode( (string) $submission->schema_json, true );
        $payload = json_decode( (string) $submission->payload_json, true );
        if ( ! is_array( $payload ) ) return false;
        if ( ! is_array( $schema ) ) {
            // Deleted/missing historical revisions must not make exact email values invisible.
            foreach ( $payload as $value ) {
                if ( is_string( $value ) && $email_address === ContactService::normalize_email( $value ) ) return true;
            }
            return false;
        }
        foreach ( $schema as $field ) {
            if ( ! is_array( $field ) || ( 'email' !== ( $field['type'] ?? '' ) && 'contact.email' !== ( $field['mapping'] ?? '' ) ) ) continue;
            $value = $payload[ sanitize_key( $field['name'] ?? '' ) ] ?? '';
            if ( is_string( $value ) && $email_address === ContactService::normalize_email( $value ) ) return true;
        }
        return false;
    }

    private function submission_consents( $submission_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE submission_id=%d ORDER BY id ASC', Schema::table( 'consent_events' ), absint( $submission_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Includes consent evidence detached from a contact.
    }

    private function submission_activity( $submission_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE entity_type='submission' AND entity_id=%d ORDER BY id ASC", Schema::table( 'activity_log' ), absint( $submission_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Includes detached submission history.
    }

    private function paged_rows( $table_name, $contact_ids, $page ) {
        if ( ! $contact_ids ) return array( 'rows' => array(), 'has_more' => false );
        global $wpdb;
        $table        = Schema::table( $table_name );
        $placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
        $offset       = ( max( 1, absint( $page ) ) - 1 ) * self::PAGE_SIZE;
        $args         = array_merge( array( $table ), array_map( 'absint', $contact_ids ), array( self::PAGE_SIZE + 1, $offset ) );
        $sql          = "SELECT * FROM %i WHERE contact_id IN ({$placeholders}) ORDER BY id ASC LIMIT %d OFFSET %d";
        $rows         = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Privacy export requires fresh rows; identifier uses %i and the generated IN list contains placeholders only.
        $has_more     = count( $rows ) > self::PAGE_SIZE;
        return array( 'rows' => array_slice( $rows, 0, self::PAGE_SIZE ), 'has_more' => $has_more );
    }

    private function paged_activity( $contact_ids, $page ) {
        if ( ! $contact_ids ) return array( 'rows' => array(), 'has_more' => false );
        global $wpdb;
        $activity     = Schema::table( 'activity_log' );
        $submissions  = Schema::table( 'submissions' );
        $placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
        $offset       = ( max( 1, absint( $page ) ) - 1 ) * self::PAGE_SIZE;
        $args         = array_merge( array( $activity ), array_map( 'absint', $contact_ids ), array( $submissions ), array_map( 'absint', $contact_ids ), array( self::PAGE_SIZE + 1, $offset ) );
        $sql          = "SELECT a.* FROM %i a WHERE (a.entity_type='contact' AND a.entity_id IN ({$placeholders})) OR (a.entity_type='submission' AND a.entity_id IN (SELECT s.id FROM %i s WHERE s.contact_id IN ({$placeholders}))) ORDER BY a.id ASC LIMIT %d OFFSET %d";
        $rows         = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Privacy export requires fresh rows; identifiers use %i and generated IN lists contain placeholders only.
        $has_more     = count( $rows ) > self::PAGE_SIZE;
        return array( 'rows' => array_slice( $rows, 0, self::PAGE_SIZE ), 'has_more' => $has_more );
    }

    private function scrub_submission( $submission ) {
        global $wpdb;
        $payload = json_decode( (string) $submission->payload_json, true );
        $payload = is_array( $payload ) ? array_map( array( $this, 'empty_value' ), $payload ) : array();
        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure must update current custom-table data.
            Schema::table( 'submissions' ),
            array( 'payload_json' => wp_json_encode( $payload ), 'source_url' => '', 'source_ip' => '', 'user_agent' => '' ),
            array( 'id' => absint( $submission->id ) ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        if ( false === $updated || false === NoteService::delete_for_entity( 'submission', $submission->id ) ) {
            return false;
        }
        $scrubbed = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Submission evidence also needs erasure when contact_id is zero.
            Schema::table( 'consent_events' ),
            array( 'source_url' => '', 'source_ip' => '', 'user_agent' => '', 'recorded_by' => 0 ),
            array( 'submission_id' => absint( $submission->id ) ),
            array( '%s', '%s', '%s', '%d' ), array( '%d' )
        );
        return false !== $scrubbed && $this->scrub_activity( 'submission', array( absint( $submission->id ) ) );
    }

    private function scrub_contact_relations( $contact_id ) {
        global $wpdb;
        if ( false === NoteService::delete_for_entity( 'contact', $contact_id ) ) {
            return false;
        }
        $consents = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure must update current consent evidence metadata.
            Schema::table( 'consent_events' ),
            array( 'source_url' => '', 'source_ip' => '', 'user_agent' => '', 'recorded_by' => 0 ),
            array( 'contact_id' => absint( $contact_id ) ),
            array( '%s', '%s', '%s', '%d' ),
            array( '%d' )
        );
        if ( false === $consents ) {
            return false;
        }
        return $this->scrub_activity( 'contact', array( absint( $contact_id ) ) );
    }

    private function scrub_activity( $entity_type, $entity_ids ) {
        global $wpdb;
        $entity_ids   = array_values( array_filter( array_map( 'absint', $entity_ids ) ) );
        $placeholders = implode( ',', array_fill( 0, count( $entity_ids ), '%d' ) );
        if ( ! $placeholders ) {
            return true;
        }
        $args = array_merge( array( Schema::table( 'activity_log' ), '{}', '', sanitize_key( $entity_type ) ), $entity_ids );
        $sql  = "UPDATE %i SET meta_json=%s,message=%s,user_id=0 WHERE entity_type=%s AND entity_id IN ({$placeholders})";
        return false !== $wpdb->query( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Privacy erasure uses %i and a generated list containing placeholders only.
    }

    private function empty_value( $value ) {
        return is_array( $value ) ? array() : '';
    }

    private function export_value( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        }
        return (string) $value;
    }

    private function erase_error( $message ) {
        return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( $message ), 'done' => true );
    }
}

