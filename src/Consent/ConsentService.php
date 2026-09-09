<?php
namespace BfCamel\CRM\Consent;

use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ConsentService {
    public static function capture_from_submission( $submission_id, $contact_id, $form_id, $revision_id, $schema, $payload, $source ) {
        foreach ( (array) $schema as $field ) {
            $type = sanitize_key( $field['type'] ?? '' );
            $name = sanitize_key( $field['name'] ?? '' );
            if ( ! in_array( $type, array( 'consent_personal_data', 'consent_marketing' ), true ) || ! $name ) {
                continue;
            }

            if ( empty( $payload[ $name ] ) ) {
                continue;
            }

            $consent_type = 'consent_personal_data' === $type ? 'personal_data' : 'marketing';
            self::record(
                $contact_id,
                $submission_id,
                $consent_type,
                'granted',
                $form_id,
                $revision_id,
                self::document_snapshot( $consent_type ),
                $source
            );
        }
    }

    public static function record( $contact_id, $submission_id, $consent_type, $status, $form_id, $revision_id, $documents, $source ) {
        global $wpdb;
        $ok = $wpdb->insert(
            Schema::table( 'consent_events' ),
            array(
                'contact_id'     => absint( $contact_id ),
                'submission_id'  => absint( $submission_id ),
                'consent_type'   => sanitize_key( $consent_type ),
                'status'         => sanitize_key( $status ),
                'form_id'        => absint( $form_id ),
                'revision_id'    => absint( $revision_id ),
                'documents_json' => wp_json_encode( $documents ),
                'source_url'     => esc_url_raw( $source['source_url'] ?? '' ),
                'source_ip'      => sanitize_text_field( $source['source_ip'] ?? '' ),
                'user_agent'     => sanitize_textarea_field( $source['user_agent'] ?? '' ),
                'event_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $ok ) {
            Schema::log(
                'contact',
                absint( $contact_id ),
                'consent_' . sanitize_key( $status ),
                'Consent status recorded.',
                array( 'submission_id' => absint( $submission_id ), 'consent_type' => sanitize_key( $consent_type ) )
            );
        }

        return (bool) $ok;
    }

    public static function events_for_contact( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'consent_events' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE contact_id=%d ORDER BY event_at DESC,id DESC", absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function document_snapshot( $consent_type ) {
        $documents = get_option( 'bfcamel_crm_legal_documents', array() );
        if ( 'personal_data' === $consent_type ) {
            return array(
                'personal_data_consent' => self::clean_doc( $documents['personal_data_consent'] ?? array() ),
                'privacy_policy'        => self::clean_doc( $documents['privacy_policy'] ?? array() ),
            );
        }

        return array(
            'marketing_consent' => self::clean_doc( $documents['marketing_consent'] ?? array() ),
        );
    }

    private static function clean_doc( $doc ) {
        return array(
            'url'     => esc_url_raw( $doc['url'] ?? '' ),
            'version' => sanitize_text_field( $doc['version'] ?? '' ),
        );
    }
}
