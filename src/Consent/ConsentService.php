<?php
namespace BfCamel\CRM\Consent;

use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ConsentService {
    const CHANNELS = array( 'email' => 'Электронная почта', 'sms' => 'SMS', 'call' => 'Звонки', 'message' => 'Личные сообщения' );

    public static function types() {
        return array(
            'personal_data' => __( 'Personal data processing', 'bfcamel-crm' ),
            'marketing'     => __( 'Email and marketing messages', 'bfcamel-crm' ),
        );
    }

    public static function statuses() {
        return array(
            'unknown' => __( 'Not specified', 'bfcamel-crm' ),
            'granted' => __( 'Granted', 'bfcamel-crm' ),
            'denied'  => __( 'Not granted', 'bfcamel-crm' ),
            'revoked' => __( 'Revoked', 'bfcamel-crm' ),
        );
    }

    /**
     * Legal documents are optional owner configuration, not a collection gate.
     *
     * @param array|string $field Field definition or field type.
     * @return bool
     */
    public static function field_available( $field ) {
        unset( $field );
        return true;
    }

    public static function missing_document_types() {
        $documents = get_option( 'bfcamel_crm_legal_documents', array() );
        $missing   = array();

        foreach ( array( 'personal_data_consent', 'privacy_policy', 'marketing_consent' ) as $key ) {
            if ( empty( $documents[ $key ]['archive_id'] ) ) {
                $missing[] = $key;
            }
        }
        return $missing;
    }

    public static function capture_from_submission( $submission_id, $contact_id, $form_id, $revision_id, $schema, $payload, $source ) {
        $choices = array();

        foreach ( (array) $schema as $field ) {
            $type = sanitize_key( $field['type'] ?? '' );
            $name = sanitize_key( $field['name'] ?? '' );
            if ( ! in_array( $type, array( 'consent_personal_data', 'consent_marketing' ), true ) || ! $name ) {
                continue;
            }

            $consent_type = 'consent_personal_data' === $type ? 'personal_data' : 'marketing';
            if ( 'marketing' === $consent_type && empty( $payload[ $name ] ) ) continue;
            $status       = self::status_from_form_value( $payload[ $name ] ?? '' );
            if ( ! isset( $choices[ $consent_type ] ) || 'granted' === $status ) {
                $choices[ $consent_type ] = array(
                    'status'    => $status,
                    'field'     => $field,
                    'field_key' => $name,
                );
            }
        }

        foreach ( $choices as $consent_type => $choice ) {
            $evidence              = self::document_snapshot( $consent_type, $choice['field'] );
            foreach ( $evidence['documents'] as $doc ) {
                if ( empty( $doc['archive_id'] ) ) return false;
            }
            if ( 'marketing' === $consent_type ) {
                $evidence['channels'] = array();
                foreach ( $schema as $contact_field ) {
                    $key = $contact_field['name'];
                    if ( empty( $payload[ $key ] ) ) continue;
                    if ( 'email' === $contact_field['type'] ) $evidence['channels']['email'] = $key;
                    if ( 'tel' === $contact_field['type'] ) {
                        foreach ( array( 'sms', 'call', 'message' ) as $channel ) $evidence['channels'][ $channel ] = $key;
                    }
                    if ( 'social_pages' === ( $contact_field['custom_key'] ?? '' ) ) $evidence['channels']['message'] = $key;
                }
            }
            $evidence['utc'] = gmdate( 'Y-m-d\TH:i:s\Z' );
            $evidence['form_name'] = $source['form_name'] ?? '';
            $evidence['field_key'] = $choice['field_key'];
            if ( ! self::record( $contact_id, $submission_id, $consent_type, $choice['status'], $form_id, $revision_id, $evidence, $source, 0, 'form' ) ) {
                return false;
            }
        }
        return true;
    }

    public static function record_manual( $contact_id, $consent_type, $status, $user_id, $channel = '', $basis = '' ) {
        $types        = self::types();
        $statuses     = self::statuses();
        $consent_type = sanitize_key( $consent_type );
        $status       = sanitize_key( $status );
        if ( ! absint( $contact_id ) || ! isset( $types[ $consent_type ], $statuses[ $status ] ) ) {
            return false;
        }

        $source = array(
            'source_url' => '',
            'source_ip'  => '',
            'user_agent' => Request::server_textarea( 'HTTP_USER_AGENT', 1000 ),
        );
        if ( '' === trim( $basis ) || ( $channel && ( 'marketing' !== $consent_type || ! isset( self::CHANNELS[ $channel ] ) || 'revoked' !== $status ) ) ) return false;
        $evidence = self::document_snapshot( $consent_type );
        $evidence['basis'] = sanitize_text_field( $basis );
        $evidence['utc'] = gmdate( 'c' );
        return self::record( $contact_id, 0, $consent_type, $status, 0, 0, $evidence, $source, $user_id, 'manual', $channel );
    }

    public static function record( $contact_id, $submission_id, $consent_type, $status, $form_id, $revision_id, $documents, $source, $user_id = 0, $source_type = 'form', $channel = '' ) {
        global $wpdb;

        $consent_type = sanitize_key( $consent_type );
        $status       = sanitize_key( $status );
        $source_type  = 'manual' === sanitize_key( $source_type ) ? 'manual' : 'form';
        if ( ! isset( self::types()[ $consent_type ], self::statuses()[ $status ] ) ) {
            return false;
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes immutable evidence to the plugin's custom consent table.
            Schema::table( 'consent_events' ),
            array(
                'contact_id'     => absint( $contact_id ),
                'submission_id'  => absint( $submission_id ),
                'consent_type'   => $consent_type,
                'status'         => $status,
                'form_id'        => absint( $form_id ),
                'revision_id'    => absint( $revision_id ),
                'documents_json' => wp_json_encode( $documents ),
                'source_url'     => esc_url_raw( $source['source_url'] ?? '' ),
                'source_ip'      => sanitize_text_field( $source['source_ip'] ?? '' ),
                'user_agent'     => sanitize_textarea_field( $source['user_agent'] ?? '' ),
                'source_type'    => $source_type,
                'channel'        => $channel,
                'recorded_by'    => absint( $user_id ),
                'event_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
        );
        if ( ! $inserted ) {
            return false;
        }

        return Schema::log(
            'contact',
            absint( $contact_id ),
            'consent_' . $status,
            'Consent status recorded.',
            array(
                'submission_id' => absint( $submission_id ),
                'consent_type'  => $consent_type,
                'status'        => $status,
                'source_type'   => $source_type,
            ),
            absint( $user_id )
        );
    }

    public static function current_statuses( $contact_id, $events = null ) {
        $current  = array_fill_keys( array_keys( self::types() ), 'unknown' );
        $found    = array();
        $statuses = self::statuses();
        $events   = null === $events ? self::events_for_contact( $contact_id ) : (array) $events;

        foreach ( $events as $event ) {
            if ( ! empty( $event->channel ) ) continue;
            $type   = sanitize_key( $event->consent_type );
            $status = sanitize_key( $event->status );
            if ( isset( $current[ $type ], $statuses[ $status ] ) && ! isset( $found[ $type ] ) ) {
                $current[ $type ] = $status;
                $found[ $type ]   = true;
            }
        }
        return $current;
    }

    public static function channel_states( $contact_id ) {
        $states = array();
        foreach ( self::events_for_contact( $contact_id ) as $event ) {
            if ( 'marketing' !== $event->consent_type ) continue;
            $proof = json_decode( $event->documents_json, true );
            foreach ( self::CHANNELS as $channel => $label ) {
                if ( isset( $states[ $channel ] ) || ( $event->channel && $event->channel !== $channel ) ) continue;
                if ( 'granted' === $event->status && ! isset( $proof['channels'][ $channel ] ) ) continue;
                $states[ $channel ] = $event->status;
            }
        }
        return $states;
    }

    public static function events_for_contact( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'consent_events' );
        $users = $wpdb->users;
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Consent history is audit evidence and must not be served stale.
            $wpdb->prepare(
                'SELECT e.*,u.display_name AS recorded_by_name FROM %i e LEFT JOIN %i u ON u.ID=e.recorded_by WHERE e.contact_id=%d ORDER BY e.event_at DESC,e.id DESC',
                $table, $users, absint( $contact_id )
            )
        );
    }

    private static function document_snapshot( $consent_type, $field = array() ) {
        $documents = get_option( 'bfcamel_crm_legal_documents', array() );
        $snapshot  = array(
            'field_text'           => sanitize_textarea_field( $field['label'] ?? '' ),
            'documents_configured' => true,
            'documents'            => array(),
        );

        $keys = 'personal_data' === $consent_type
            ? array( 'personal_data_consent', 'privacy_policy' )
            : array( 'marketing_consent' );

        foreach ( $keys as $key ) {
            $document = self::clean_doc( $documents[ $key ] ?? array() );
            if ( empty( $document['archive_id'] ) ) {
                $snapshot['documents_configured'] = false;
            }
            $snapshot['documents'][ $key ] = $document;
        }
        return $snapshot;
    }

    private static function clean_doc( $document ) {
        return array(
            'archive_id'     => sanitize_key( $document['archive_id'] ?? '' ),
            'title'          => sanitize_text_field( $document['title'] ?? '' ),
            'url'            => esc_url_raw( $document['url'] ?? '' ),
            'version'        => sanitize_text_field( $document['version'] ?? '' ),
            'effective_date' => sanitize_text_field( $document['effective_date'] ?? '' ),
            'link_text'      => sanitize_text_field( $document['link_text'] ?? '' ),
        );
    }

    private static function status_from_form_value( $value ) {
        return empty( $value ) ? 'denied' : 'granted';
    }
}
