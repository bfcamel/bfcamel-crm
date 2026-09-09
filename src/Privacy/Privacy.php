<?php
namespace BfCamel\CRM\Privacy;

use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\NoteService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Privacy {
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
        $ids = ContactService::find_contact_ids_by_email_value( $email_address );
        $data = array();

        foreach ( $ids as $contact_id ) {
            $contact = ContactService::get( $contact_id );
            if ( ! $contact ) {
                continue;
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

            $data[] = array(
                'group_id'    => 'bfcamel-crm-contact',
                'group_label' => __( 'BfCamel CRM contact', 'bfcamel-crm' ),
                'item_id'     => 'contact-' . absint( $contact_id ),
                'data'        => $items,
            );

            foreach ( $this->submissions_for_contact( $contact_id ) as $submission ) {
                $payload = json_decode( $submission->payload_json, true );
                $payload = is_array( $payload ) ? $payload : array();
                $submission_data = array(
                    array( 'name' => __( 'Submitted at', 'bfcamel-crm' ), 'value' => $submission->submitted_at ),
                    array( 'name' => __( 'Source URL', 'bfcamel-crm' ), 'value' => $submission->source_url ),
                );
                foreach ( $payload as $key => $value ) {
                    $submission_data[] = array(
                        'name'  => $key,
                        'value' => is_array( $value ) ? implode( ', ', $value ) : (string) $value,
                    );
                }
                foreach ( NoteService::for_entity( 'submission', $submission->id ) as $note ) {
                    $submission_data[] = array( 'name' => __( 'Internal note', 'bfcamel-crm' ), 'value' => $note->note_text );
                }
                $data[] = array(
                    'group_id'    => 'bfcamel-crm-submissions',
                    'group_label' => __( 'BfCamel CRM submissions', 'bfcamel-crm' ),
                    'item_id'     => 'submission-' . absint( $submission->id ),
                    'data'        => $submission_data,
                );
            }
        }

        return array(
            'data' => $data,
            'done' => true,
        );
    }

    public function erase_personal_data( $email_address, $page = 1 ) {
        $ids = ContactService::find_contact_ids_by_email_value( $email_address );
        $removed = false;
        $retained = false;
        $messages = array();

        foreach ( $ids as $contact_id ) {
            $submissions = $this->submissions_for_contact( $contact_id );
            foreach ( $submissions as $submission ) {
                $this->scrub_submission_payload( $submission );
                NoteService::delete_for_entity( 'submission', $submission->id );
            }
            NoteService::delete_for_entity( 'contact', $contact_id );
            $this->scrub_consent_metadata( $contact_id );
            if ( ContactService::anonymize( $contact_id ) ) {
                $removed = true;
            }
        }

        if ( $removed ) {
            $messages[] = __( 'BfCamel CRM contact identifiers and mapped submission fields were anonymized, and internal notes linked to the contact were removed. Consent event timestamps and document snapshots were retained as non-contact audit records.', 'bfcamel-crm' );
            $retained = true;
        }

        return array(
            'items_removed'  => $removed,
            'items_retained' => $retained,
            'messages'       => $messages,
            'done'           => true,
        );
    }

    public function privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = '<p>' . esc_html__( 'If forms created with BfCamel CRM are used on this site, the plugin may store submitted form data, CRM contacts, internal notes, consent events, source URLs and browser User-Agent strings. IP address storage is optional and disabled by default. Administrators should describe the actual forms, purposes, retention periods and legal basis used on their site.', 'bfcamel-crm' ) . '</p>';
        wp_add_privacy_policy_content( 'BfCamel CRM', wp_kses_post( wpautop( $content ) ) );
    }

    private function submissions_for_contact( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'submissions' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE contact_id=%d ORDER BY id ASC", absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private function scrub_submission_payload( $submission ) {
        global $wpdb;
        $revision = Repository::get_revision( $submission->revision_id );
        $schema   = Repository::decode_schema( $revision );
        $payload  = json_decode( $submission->payload_json, true );
        $payload  = is_array( $payload ) ? $payload : array();

        foreach ( $schema as $field ) {
            $mapping = (string) ( $field['mapping'] ?? 'submission_only' );
            $name    = sanitize_key( $field['name'] ?? '' );
            if ( $name && 0 === strpos( $mapping, 'contact.' ) && array_key_exists( $name, $payload ) ) {
                $payload[ $name ] = is_array( $payload[ $name ] ) ? array() : '';
            }
        }

        $wpdb->update(
            Schema::table( 'submissions' ),
            array(
                'payload_json' => wp_json_encode( $payload ),
                'source_ip'    => '',
                'user_agent'   => '',
            ),
            array( 'id' => absint( $submission->id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    private function scrub_consent_metadata( $contact_id ) {
        global $wpdb;
        $wpdb->update(
            Schema::table( 'consent_events' ),
            array( 'source_ip' => '', 'user_agent' => '' ),
            array( 'contact_id' => absint( $contact_id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }
}
