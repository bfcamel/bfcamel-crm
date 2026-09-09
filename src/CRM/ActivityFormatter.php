<?php
namespace BfCamel\CRM\CRM;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ActivityFormatter {
    public static function message( $event ) {
        $type = isset( $event->event_type ) ? sanitize_key( $event->event_type ) : '';
        $meta = self::meta( $event );

        switch ( $type ) {
            case 'created':
                if ( ! empty( $meta['contact_sync_status'] ) ) {
                    return sprintf(
                        /* translators: %s: contact synchronization result. */
                        __( 'Form submission received; contact sync: %s.', 'bfcamel-crm' ),
                        self::sync_label( $meta['contact_sync_status'] )
                    );
                }
                return __( 'Form submission received.', 'bfcamel-crm' );
            case 'status_changed':
                $from = isset( $meta['from'] ) ? SubmissionService::status_label( $meta['from'] ) : __( 'Unknown', 'bfcamel-crm' );
                $to = isset( $meta['to'] ) ? SubmissionService::status_label( $meta['to'] ) : SubmissionService::status_label( $meta['status'] ?? '' );
                return sprintf(
                    /* translators: 1: previous status, 2: new status. */
                    __( 'Status: %1$s → %2$s', 'bfcamel-crm' ),
                    $from,
                    $to
                );
            case 'priority_changed':
                return sprintf(
                    /* translators: 1: previous priority, 2: new priority. */
                    __( 'Priority: %1$s → %2$s', 'bfcamel-crm' ),
                    SubmissionService::priority_label( $meta['from'] ?? 'normal' ),
                    SubmissionService::priority_label( $meta['to'] ?? 'normal' )
                );
            case 'assignee_changed':
                return sprintf(
                    /* translators: 1: previous assignee, 2: new assignee. */
                    __( 'Responsible: %1$s → %2$s', 'bfcamel-crm' ),
                    self::user_name( $meta['from'] ?? 0 ),
                    self::user_name( $meta['to'] ?? 0 )
                );
            case 'tags_changed':
                return sprintf(
                    /* translators: 1: previous tags, 2: new tags. */
                    __( 'Tags: %1$s → %2$s', 'bfcamel-crm' ),
                    self::tag_list( $meta['from'] ?? array() ),
                    self::tag_list( $meta['to'] ?? array() )
                );
            case 'contact_created_manual':
                return __( 'Contact created manually.', 'bfcamel-crm' );
            case 'contact_updated':
                return self::contact_changes( $meta['changes'] ?? array() );
            case 'note_added':
                return __( 'Internal note added.', 'bfcamel-crm' );
            case 'contact_tags_changed':
                return sprintf(
                    /* translators: 1: previous tags, 2: new tags. */
                    __( 'Contact tags: %1$s → %2$s', 'bfcamel-crm' ),
                    self::tag_list( $meta['from'] ?? array() ),
                    self::tag_list( $meta['to'] ?? array() )
                );
            case 'form_sync':
                if ( ! empty( $meta['status'] ) ) {
                    return sprintf(
                        /* translators: %s: contact synchronization result. */
                        __( 'Contact synchronized from a form submission: %s.', 'bfcamel-crm' ),
                        self::sync_label( $meta['status'] )
                    );
                }
                return __( 'Contact synchronized from a form submission.', 'bfcamel-crm' );
            case 'consent_granted':
            case 'consent_denied':
            case 'consent_revoked':
            case 'consent_unknown':
                return sprintf(
                    /* translators: 1: consent type, 2: consent status. */
                    __( 'Consent %1$s: %2$s.', 'bfcamel-crm' ),
                    self::consent_type_label( $meta['consent_type'] ?? '' ),
                    self::consent_status_label( $meta['status'] ?? str_replace( 'consent_', '', $type ) )
                );
            case 'anonymized':
                return __( 'Contact personal data anonymized.', 'bfcamel-crm' );
        }

        return isset( $event->message ) ? (string) $event->message : __( 'CRM activity recorded.', 'bfcamel-crm' );
    }

    private static function contact_changes( $changes ) {
        $labels = array(
            'name'         => __( 'Name', 'bfcamel-crm' ),
            'organization' => __( 'Organization', 'bfcamel-crm' ),
            'email'        => __( 'Email', 'bfcamel-crm' ),
            'phone'        => __( 'Phone', 'bfcamel-crm' ),
        );
        $parts = array();
        foreach ( (array) $changes as $key => $change ) {
            if ( ! isset( $labels[ $key ] ) || ! is_array( $change ) ) continue;
            $parts[] = sprintf( '%1$s: %2$s → %3$s', $labels[ $key ], (string) ( $change['from'] ?? '' ), (string) ( $change['to'] ?? '' ) );
        }
        return $parts ? implode( '; ', $parts ) : __( 'Contact details updated.', 'bfcamel-crm' );
    }

    private static function meta( $event ) {
        $decoded = isset( $event->meta_json ) ? json_decode( (string) $event->meta_json, true ) : array();
        return is_array( $decoded ) ? $decoded : array();
    }

    private static function user_name( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return __( 'Unassigned', 'bfcamel-crm' );
        }
        $user = get_userdata( $user_id );
        return $user ? $user->display_name : sprintf( __( 'User #%d', 'bfcamel-crm' ), $user_id );
    }

    private static function tag_list( $tags ) {
        $tags = array_values( array_filter( array_map( 'sanitize_text_field', (array) $tags ) ) );
        return $tags ? implode( ', ', $tags ) : __( 'No tags', 'bfcamel-crm' );
    }

    public static function sync_label( $status ) {
        $labels = array(
            'created'  => __( 'Contact created', 'bfcamel-crm' ),
            'linked'   => __( 'Contact linked', 'bfcamel-crm' ),
            'conflict' => __( 'Conflict — needs review', 'bfcamel-crm' ),
            'error'    => __( 'Error', 'bfcamel-crm' ),
            'pending'  => __( 'Pending', 'bfcamel-crm' ),
        );
        $status = sanitize_key( $status );
        return $labels[ $status ] ?? $status;
    }

    public static function consent_type_label( $type ) {
        $labels = array(
            'personal_data' => __( 'Personal data', 'bfcamel-crm' ),
            'marketing'     => __( 'Marketing', 'bfcamel-crm' ),
        );
        $type = sanitize_key( $type );
        return $labels[ $type ] ?? $type;
    }

    public static function consent_status_label( $status ) {
        $labels = array(
            'granted' => __( 'Granted', 'bfcamel-crm' ),
            'denied'  => __( 'Not granted', 'bfcamel-crm' ),
            'revoked' => __( 'Revoked', 'bfcamel-crm' ),
            'unknown' => __( 'Unknown', 'bfcamel-crm' ),
        );
        $status = sanitize_key( $status );
        return $labels[ $status ] ?? $status;
    }
}
