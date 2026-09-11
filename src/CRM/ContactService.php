<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContactService {
    const CACHE_GROUP = 'bfcamel_crm';

    public static function resolve_and_sync( $schema, $payload ) {
        $mapped = self::extract_mapped_values( $schema, $payload );
        $email  = self::normalize_email( $mapped['email'] );
        $phone  = self::normalize_phone( $mapped['phone'] );
        $locks  = self::identifier_locks( $email, $phone );
        $locked = array();

        foreach ( $locks as $lock ) {
            if ( ! self::acquire_lock( $lock ) ) {
                foreach ( array_reverse( $locked ) as $acquired ) {
                    self::release_lock( $acquired );
                }
                return array( 'contact_id' => 0, 'status' => 'error' );
            }
            $locked[] = $lock;
        }

        try {
            return self::resolve_and_sync_locked( $mapped, $email, $phone );
        } finally {
            foreach ( array_reverse( $locked ) as $lock ) {
                self::release_lock( $lock );
            }
        }
    }

    private static function resolve_and_sync_locked( $mapped, $email, $phone ) {
        $email_ids = $email ? self::find_by_email( $email ) : array();
        $phone_ids = $phone ? self::find_by_phone( $phone ) : array();
        $all_ids   = array_values( array_unique( array_merge( $email_ids, $phone_ids ) ) );

        if ( count( $email_ids ) > 1 || count( $phone_ids ) > 1 || count( $all_ids ) > 1 ) {
            return array(
                'contact_id' => 0,
                'status'     => 'conflict',
                'email_ids'  => $email_ids,
                'phone_ids'  => $phone_ids,
            );
        }

        $contact_id = 1 === count( $all_ids ) ? absint( $all_ids[0] ) : 0;
        if ( ! $contact_id ) {
            $contact_id = self::create_contact( $mapped );
            if ( ! $contact_id ) {
                return array( 'contact_id' => 0, 'status' => 'error' );
            }
            $status = 'created';
        } else {
            if ( ! self::update_contact( $contact_id, $mapped ) ) return array( 'contact_id' => 0, 'status' => 'error' );
            $status = 'linked';
        }

        if ( $email && ! self::add_email( $contact_id, $mapped['email'] ) ) return array( 'contact_id' => 0, 'status' => 'error' );
        if ( $phone && ! self::add_phone( $contact_id, $mapped['phone'] ) ) return array( 'contact_id' => 0, 'status' => 'error' );

        foreach ( $mapped['custom'] as $key => $value ) {
            if ( ! self::upsert_custom_field( $contact_id, $key, $value ) ) return array( 'contact_id' => 0, 'status' => 'error' );
        }

        if ( ! Schema::log(
            'contact',
            $contact_id,
            'form_sync',
            'Contact synchronized from a form submission.',
            array( 'status' => $status )
        ) ) return array( 'contact_id' => 0, 'status' => 'error' );

        return array( 'contact_id' => $contact_id, 'status' => $status );
    }

    private static function identifier_locks( $email, $phone ) {
        $identifiers = array();
        if ( $email ) {
            $identifiers[] = 'email|' . $email;
        }
        if ( $phone ) {
            $identifiers[] = 'phone|' . $phone;
        }
        $locks = array_map(
            static function ( $identifier ) {
                return 'bfcamel_crm_' . substr( hash( 'sha256', $identifier ), 0, 48 );
            },
            $identifiers
        );
        sort( $locks, SORT_STRING );
        return array_values( array_unique( $locks ) );
    }

    private static function acquire_lock( $name ) {
        global $wpdb;
        return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory locks must always query the current database connection.
    }

    private static function release_lock( $name ) {
        global $wpdb;
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory locks must be released on the current database connection.
    }

    public static function get( $contact_id, $for_update = false ) {
        global $wpdb;
        $table = Schema::table( 'contacts' );
        if ( $for_update ) {
            return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d LIMIT 1 FOR UPDATE', $table, absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- FOR UPDATE must bypass caches inside the active transaction.
        }
        $contact_id = absint( $contact_id );
        $cache_key = 'contact:id:' . $contact_id;
        $found = false;
        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
        if ( $found ) return $cached ?: null;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d LIMIT 1', $table, $contact_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached read from the plugin's custom contacts table.
        wp_cache_set( $cache_key, $row ?: false, self::CACHE_GROUP );
        return $row;
    }

    public static function query( $filters = array(), $page = 1, $per_page = 25 ) {
        global $wpdb;
        $contacts = Schema::table( 'contacts' );
        $emails = Schema::table( 'contact_emails' );
        $phones = Schema::table( 'contact_phones' );
        $tags = Schema::table( 'tags' );
        $links = Schema::table( 'contact_tags' );
        $consents = Schema::table( 'consent_events' );
        $contacts_sql = $wpdb->prepare( '%i', $contacts );
        $emails_sql = $wpdb->prepare( '%i', $emails );
        $phones_sql = $wpdb->prepare( '%i', $phones );
        $tags_sql = $wpdb->prepare( '%i', $tags );
        $links_sql = $wpdb->prepare( '%i', $links );
        $consents_sql = $wpdb->prepare( '%i', $consents );

        $where = array( '1=1' );
        $args = array();
        $search = isset( $filters['search'] ) ? trim( (string) $filters['search'] ) : '';
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = "(c.display_name LIKE %s OR c.organization LIKE %s
                OR EXISTS (SELECT 1 FROM {$emails_sql} e1 WHERE e1.contact_id=c.id AND e1.value LIKE %s)
                OR EXISTS (SELECT 1 FROM {$phones_sql} p1 WHERE p1.contact_id=c.id AND p1.value LIKE %s))";
            $args = array_merge( $args, array( $like, $like, $like, $like ) );
        }

        $tag_id = isset( $filters['tag_id'] ) ? absint( $filters['tag_id'] ) : 0;
        if ( $tag_id ) {
            $where[] = "EXISTS (SELECT 1 FROM {$links_sql} ctf WHERE ctf.contact_id=c.id AND ctf.tag_id=%d)";
            $args[] = $tag_id;
        }

        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $filters['ids'] ?? array() ) ) ) ) );
        if ( $ids ) {
            $where[] = 'c.id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
            $args = array_merge( $args, $ids );
        }

        $allowed_consent = array_keys( ConsentService::statuses() );
        foreach ( array( 'personal_data', 'marketing' ) as $consent_type ) {
            $filter_key = $consent_type . '_consent';
            $status = isset( $filters[ $filter_key ] ) ? sanitize_key( $filters[ $filter_key ] ) : '';
            if ( in_array( $status, $allowed_consent, true ) ) {
                $where[] = "COALESCE((SELECT ce2.status FROM {$consents_sql} ce2 WHERE ce2.contact_id=c.id AND ce2.consent_type=%s ORDER BY ce2.event_at DESC,ce2.id DESC LIMIT 1),'unknown')=%s";
                $args[] = $consent_type;
                $args[] = $status;
            }
        }

        $where_sql = implode( ' AND ', $where );
        $count_sql = "SELECT COUNT(*) FROM {$contacts_sql} c WHERE {$where_sql}";
        $total = (int) $wpdb->get_var( self::prepare_sql( $count_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are prepared with %i; WHERE fragments are fixed and values are prepared by prepare_sql().
        $page = max( 1, absint( $page ) );
        $per_page = min( 500, max( 10, absint( $per_page ) ) );
        $offset = ( $page - 1 ) * $per_page;

        $select_sql = "SELECT c.*,
            (SELECT GROUP_CONCAT(e.value ORDER BY e.is_primary DESC,e.id ASC SEPARATOR ', ') FROM {$emails_sql} e WHERE e.contact_id=c.id) AS email_values,
            (SELECT GROUP_CONCAT(p.value ORDER BY p.is_primary DESC,p.id ASC SEPARATOR ', ') FROM {$phones_sql} p WHERE p.contact_id=c.id) AS phone_values,
            (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM {$links_sql} ct INNER JOIN {$tags_sql} t ON t.id=ct.tag_id WHERE ct.contact_id=c.id) AS tag_names,
            (SELECT ce.status FROM {$consents_sql} ce WHERE ce.contact_id=c.id AND ce.consent_type='personal_data' ORDER BY ce.event_at DESC,ce.id DESC LIMIT 1) AS personal_data_consent,
            (SELECT ce.status FROM {$consents_sql} ce WHERE ce.contact_id=c.id AND ce.consent_type='marketing' ORDER BY ce.event_at DESC,ce.id DESC LIMIT 1) AS marketing_consent
            FROM {$contacts_sql} c
            WHERE {$where_sql}
            ORDER BY c.updated_at DESC,c.id DESC
            LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( self::prepare_sql( $select_sql, array_merge( $args, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are prepared with %i; WHERE fragments are fixed and values are prepared by prepare_sql().

        return array(
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
        );
    }

    public static function all_for_export( $filters = array() ) {
        $rows = array();
        $page = 1;
        do {
            $result = self::query( $filters, $page, 500 );
            $rows = array_merge( $rows, (array) $result['rows'] );
            $page++;
        } while ( $page <= $result['total_pages'] );
        return $rows;
    }

    public static function count() {
        global $wpdb;
        $table = Schema::table( 'contacts' );
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard totals must reflect current custom-table data.
    }

    public static function activity( $contact_id ) {
        global $wpdb;
        $activity = Schema::table( 'activity_log' );
        $users = $wpdb->users;
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit history must reflect current custom-table state.
            $wpdb->prepare(
                "SELECT a.*, u.display_name AS actor_name FROM %i a LEFT JOIN %i u ON u.ID=a.user_id WHERE a.entity_type='contact' AND a.entity_id=%d ORDER BY a.created_at DESC,a.id DESC",
                $activity, $users, absint( $contact_id )
            )
        );
    }

    public static function get_emails( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'contact_emails' );
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE contact_id=%d ORDER BY is_primary DESC,id ASC', $table, absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Contact identifiers must reflect current custom-table state.
    }

    public static function get_phones( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'contact_phones' );
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE contact_id=%d ORDER BY is_primary DESC,id ASC', $table, absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Contact identifiers must reflect current custom-table state.
    }

    public static function get_custom_fields( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'contact_fields' );
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT field_key,field_value FROM %i WHERE contact_id=%d ORDER BY field_key ASC', $table, absint( $contact_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Contact fields must reflect current custom-table state.
        $result = array();
        foreach ( (array) $rows as $row ) {
            $result[ $row['field_key'] ] = $row['field_value'];
        }
        return $result;
    }

    public static function find_contact_ids_by_email_value( $email ) {
        return self::find_by_email( self::normalize_email( $email ) );
    }

    public static function create_manual( $data, $tags, $user_id, $consents = array() ) {
        $data = is_array( $data ) ? $data : array();
        $name = self::truncate_text( sanitize_text_field( $data['name'] ?? '' ), 190 );
        $email_value = sanitize_email( $data['email'] ?? '' );
        $phone_value = self::truncate_text( sanitize_text_field( $data['phone'] ?? '' ), 80 );
        $organization = self::truncate_text( sanitize_text_field( $data['organization'] ?? '' ), 190 );

        if ( '' === $name ) {
            return new \WP_Error( 'bfcamel_crm_contact_name_required', __( 'Contact name is required.', 'bfcamel-crm' ) );
        }
        if ( ! empty( $data['email'] ) && ! self::normalize_email( $email_value ) ) {
            return new \WP_Error( 'bfcamel_crm_contact_email_invalid', __( 'Enter a valid email address.', 'bfcamel-crm' ) );
        }
        if ( '' !== $phone_value && ! self::normalize_phone( $phone_value ) ) {
            return new \WP_Error( 'bfcamel_crm_contact_phone_invalid', __( 'Enter a valid phone number.', 'bfcamel-crm' ) );
        }

        $duplicates = array_unique(
            array_merge(
                $email_value ? self::find_by_email( self::normalize_email( $email_value ) ) : array(),
                $phone_value ? self::find_by_phone( self::normalize_phone( $phone_value ) ) : array()
            )
        );
        if ( $duplicates ) {
            return new \WP_Error(
                'bfcamel_crm_contact_duplicate',
                sprintf(
                    /* translators: %s: existing contact IDs. */
                    __( 'A contact with this email or phone already exists: #%s.', 'bfcamel-crm' ),
                    implode( ', #', array_map( 'absint', $duplicates ) )
                )
            );
        }

        if ( ! Schema::begin_transaction() ) {
            return new \WP_Error( 'bfcamel_crm_contact_transaction', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }

        $contact_id = self::create_contact(
            array(
                'name'         => $name,
                'email'        => $email_value,
                'phone'        => $phone_value,
                'organization' => $organization,
                'custom'       => array(),
            )
        );
        if ( ! $contact_id ) {
            return self::rollback_error( __( 'The contact could not be created.', 'bfcamel-crm' ) );
        }
        if ( $email_value && ! self::add_email( $contact_id, $email_value ) ) {
            return self::rollback_error( __( 'The contact email could not be saved.', 'bfcamel-crm' ) );
        }
        if ( $phone_value && ! self::add_phone( $contact_id, $phone_value ) ) {
            return self::rollback_error( __( 'The contact phone could not be saved.', 'bfcamel-crm' ) );
        }

        $tag_result = TagService::sync_contact( $contact_id, $tags );
        if ( is_wp_error( $tag_result ) ) {
            Schema::rollback();
            return $tag_result;
        }
        $allowed_types = ConsentService::types();
        $allowed_statuses = ConsentService::statuses();
        foreach ( (array) $consents as $consent_type => $status ) {
            $consent_type = sanitize_key( $consent_type );
            $status = sanitize_key( $status );
            if ( ! isset( $allowed_types[ $consent_type ], $allowed_statuses[ $status ] ) || 'unknown' === $status ) {
                continue;
            }
            if ( ! ConsentService::record_manual( $contact_id, $consent_type, $status, $user_id ) ) {
                return self::rollback_error( __( 'Could not record consent status.', 'bfcamel-crm' ) );
            }
        }
        if ( ! Schema::log( 'contact', $contact_id, 'contact_created_manual', 'Contact created manually.', array(), absint( $user_id ) ) ) {
            return self::rollback_error( __( 'Could not record contact history.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::commit() ) {
            return self::rollback_error( __( 'The contact transaction could not be committed.', 'bfcamel-crm' ) );
        }

        return $contact_id;
    }

    public static function update_manual( $contact_id, $data, $user_id ) {
        global $wpdb;
        $contact_id = absint( $contact_id );
        $data = is_array( $data ) ? $data : array();
        $current = self::get( $contact_id, true );
        if ( ! $current ) {
            return new \WP_Error( 'bfcamel_crm_contact_missing', __( 'Contact not found.', 'bfcamel-crm' ) );
        }

        $name = self::truncate_text( sanitize_text_field( $data['name'] ?? '' ), 190 );
        $organization = self::truncate_text( sanitize_text_field( $data['organization'] ?? '' ), 190 );
        $email = sanitize_email( $data['email'] ?? '' );
        $phone = self::truncate_text( sanitize_text_field( $data['phone'] ?? '' ), 80 );
        if ( '' === $name ) {
            return new \WP_Error( 'bfcamel_crm_contact_name_required', __( 'Contact name is required.', 'bfcamel-crm' ) );
        }
        if ( ! empty( $data['email'] ) && ! self::normalize_email( $email ) ) {
            return new \WP_Error( 'bfcamel_crm_contact_email_invalid', __( 'Enter a valid email address.', 'bfcamel-crm' ) );
        }
        if ( '' !== $phone && ! self::normalize_phone( $phone ) ) {
            return new \WP_Error( 'bfcamel_crm_contact_phone_invalid', __( 'Enter a valid phone number.', 'bfcamel-crm' ) );
        }

        foreach ( array( 'email' => $email, 'phone' => $phone ) as $kind => $value ) {
            if ( '' === $value ) {
                continue;
            }
            $matches = 'email' === $kind ? self::find_by_email( self::normalize_email( $value ) ) : self::find_by_phone( self::normalize_phone( $value ) );
            $matches = array_values( array_diff( $matches, array( $contact_id ) ) );
            if ( $matches ) {
                /* translators: %s: existing contact IDs. */
                return new \WP_Error( 'bfcamel_crm_contact_duplicate', sprintf( __( 'A contact with this email or phone already exists: #%s.', 'bfcamel-crm' ), implode( ', #', array_map( 'absint', $matches ) ) ) );
            }
        }

        $old_emails = self::get_emails( $contact_id );
        $old_phones = self::get_phones( $contact_id );
        $old_email = $old_emails ? (string) $old_emails[0]->value : '';
        $old_phone = $old_phones ? (string) $old_phones[0]->value : '';
        $changes = array();
        if ( (string) $current->display_name !== $name ) $changes['name'] = array( 'from' => (string) $current->display_name, 'to' => $name );
        if ( (string) $current->organization !== $organization ) $changes['organization'] = array( 'from' => (string) $current->organization, 'to' => $organization );
        if ( $old_email !== $email ) $changes['email'] = array( 'from' => $old_email, 'to' => $email );
        if ( $old_phone !== $phone ) $changes['phone'] = array( 'from' => $old_phone, 'to' => $phone );

        if ( false === $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional write to the plugin's custom contacts table.
            Schema::table( 'contacts' ),
            array( 'display_name' => $name, 'organization' => $organization, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $contact_id ),
            array( '%s', '%s', '%s' ), array( '%d' )
        ) ) {
            return new \WP_Error( 'bfcamel_crm_contact_update_failed', __( 'The contact could not be updated.', 'bfcamel-crm' ) );
        }
        if ( ! self::set_primary_identifier( 'email', $contact_id, $email ) || ! self::set_primary_identifier( 'phone', $contact_id, $phone ) ) {
            return new \WP_Error( 'bfcamel_crm_contact_identifier_failed', __( 'The contact email or phone could not be updated.', 'bfcamel-crm' ) );
        }
        if ( $changes && ! Schema::log( 'contact', $contact_id, 'contact_updated', 'Contact details updated.', array( 'changes' => $changes ), absint( $user_id ) ) ) {
            return new \WP_Error( 'bfcamel_crm_contact_history_failed', __( 'Could not record contact history.', 'bfcamel-crm' ) );
        }
        self::clear_contact_cache( $contact_id );
        return true;
    }

    private static function set_primary_identifier( $kind, $contact_id, $value ) {
        global $wpdb;
        $contact_id = absint( $contact_id );
        $table = Schema::table( 'email' === $kind ? 'contact_emails' : 'contact_phones' );
        $rows = 'email' === $kind ? self::get_emails( $contact_id ) : self::get_phones( $contact_id );
        $normalized = 'email' === $kind ? self::normalize_email( $value ) : self::normalize_phone( $value );

        $wpdb->update( $table, array( 'is_primary' => 0 ), array( 'contact_id' => $contact_id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional identifier update requires current custom-table state.
        if ( '' === $value ) {
            if ( $rows ) {
                $primary_id = absint( $rows[0]->id );
                $wpdb->delete( $table, array( 'id' => $primary_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional identifier cleanup in a custom table.
            }
            $next = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE contact_id=%d ORDER BY id ASC LIMIT 1', $table, $contact_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifier synchronization requires current custom-table state.
            if ( $next ) $wpdb->update( $table, array( 'is_primary' => 1 ), array( 'id' => $next ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional identifier update requires current custom-table state.
            return true;
        }

        $existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE contact_id=%d AND normalized=%s LIMIT 1', $table, $contact_id, $normalized ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifier synchronization requires current custom-table state.
        if ( $existing ) {
            return false !== $wpdb->update( $table, array( 'value' => $value, 'is_primary' => 1 ), array( 'id' => $existing ), array( '%s', '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional identifier update requires current custom-table state.
        }
        if ( $rows ) {
            $id = absint( $rows[0]->id );
            return false !== $wpdb->update( $table, array( 'value' => $value, 'normalized' => $normalized, 'is_primary' => 1 ), array( 'id' => $id ), array( '%s', '%s', '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional identifier update requires current custom-table state.
        }
        return (bool) $wpdb->insert( $table, array( 'contact_id' => $contact_id, 'value' => $value, 'normalized' => $normalized, 'is_primary' => 1 ), array( '%d', '%s', '%s', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional insert into a plugin-owned identifier table.
    }

    public static function anonymize( $contact_id ) {
        global $wpdb;
        $contact_id = absint( $contact_id );
        if ( ! $contact_id ) {
            return false;
        }

        if ( false === $wpdb->delete( Schema::table( 'contact_emails' ), array( 'contact_id' => $contact_id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure must update current custom-table data.
            return false;
        }
        if ( false === $wpdb->delete( Schema::table( 'contact_phones' ), array( 'contact_id' => $contact_id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure must update current custom-table data.
            return false;
        }
        if ( false === $wpdb->delete( Schema::table( 'contact_fields' ), array( 'contact_id' => $contact_id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure must update current custom-table data.
            return false;
        }
        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure writes anonymized values to the plugin's custom contacts table.
            Schema::table( 'contacts' ),
            array(
                /* translators: %d: anonymized contact ID. */
                'display_name' => sprintf( __( 'Anonymized contact #%d', 'bfcamel-crm' ), $contact_id ),
                'organization' => '',
                'status'       => 'anonymized',
                'updated_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => $contact_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            return false;
        }
        self::clear_contact_cache( $contact_id );
        return Schema::log( 'contact', $contact_id, 'anonymized', 'Contact personal data anonymized.' );
    }

    private static function extract_mapped_values( $schema, $payload ) {
        $result = array(
            'name'         => '',
            'email'        => '',
            'phone'        => '',
            'organization' => '',
            'custom'       => array(),
        );

        foreach ( (array) $schema as $field ) {
            $name    = sanitize_key( $field['name'] ?? '' );
            $mapping = (string) ( $field['mapping'] ?? 'submission_only' );
            if ( ! $name || ! array_key_exists( $name, $payload ) ) {
                continue;
            }

            $value = self::scalar( $payload[ $name ] );
            switch ( $mapping ) {
                case 'contact.name':
                    $result['name'] = $value;
                    break;
                case 'contact.email':
                    $result['email'] = $value;
                    break;
                case 'contact.phone':
                    $result['phone'] = $value;
                    break;
                case 'contact.organization':
                    $result['organization'] = $value;
                    break;
                case 'contact.custom':
                    $custom_key = sanitize_key( $field['custom_key'] ?? $name );
                    if ( $custom_key && '' !== $value ) {
                        $result['custom'][ $custom_key ] = $value;
                    }
                    break;
            }
        }

        return $result;
    }

    private static function create_contact( $mapped ) {
        global $wpdb;
        $now  = current_time( 'mysql' );
        $name = self::truncate_text( sanitize_text_field( $mapped['name'] ), 190 );
        if ( '' === $name ) {
            $name = self::truncate_text( sanitize_email( $mapped['email'] ), 190 );
        }
        if ( '' === $name ) {
            $name = self::truncate_text( sanitize_text_field( $mapped['phone'] ), 190 );
        }
        if ( '' === $name ) {
            $name = __( 'Website contact', 'bfcamel-crm' );
        }

        $ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional insert into the plugin's custom contacts table.
            Schema::table( 'contacts' ),
            array(
                'display_name' => $name,
                'organization' => self::truncate_text( sanitize_text_field( $mapped['organization'] ), 190 ),
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        $contact_id = $ok ? absint( $wpdb->insert_id ) : 0;
        if ( $contact_id ) self::clear_contact_cache( $contact_id );
        return $contact_id;
    }

    private static function update_contact( $contact_id, $mapped ) {
        global $wpdb;
        $contact = self::get( $contact_id );
        if ( ! $contact ) {
            return false;
        }

        $name = self::truncate_text( sanitize_text_field( $mapped['name'] ), 190 );
        $org  = self::truncate_text( sanitize_text_field( $mapped['organization'] ), 190 );
        $data = array( 'updated_at' => current_time( 'mysql' ) );
        $formats = array( '%s' );

        if ( $name && ( ! $contact->display_name || 0 === strpos( $contact->display_name, __( 'Website contact', 'bfcamel-crm' ) ) ) ) {
            $data['display_name'] = $name;
            $formats[] = '%s';
        }
        if ( $org && ! $contact->organization ) {
            $data['organization'] = $org;
            $formats[] = '%s';
        }

        $updated = false !== $wpdb->update( Schema::table( 'contacts' ), $data, array( 'id' => absint( $contact_id ) ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Contact synchronization updates current custom-table data.
        if ( $updated ) self::clear_contact_cache( $contact_id );
        return $updated;
    }

    private static function add_email( $contact_id, $value ) {
        global $wpdb;
        $normalized = self::normalize_email( $value );
        if ( ! $normalized ) {
            return false;
        }

        $table = Schema::table( 'contact_emails' );
        $exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE contact_id=%d AND normalized=%s LIMIT 1', $table, absint( $contact_id ), $normalized ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifier uniqueness requires current custom-table state.
        if ( $exists ) {
            return true;
        }
        $primary = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE contact_id=%d', $table, absint( $contact_id ) ) ) ? 0 : 1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Primary-identifier selection requires current custom-table state.
        return (bool) $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional insert into a plugin-owned identifier table.
            $table,
            array(
                'contact_id' => absint( $contact_id ),
                'value'      => sanitize_email( $value ),
                'normalized' => $normalized,
                'is_primary' => $primary,
            ),
            array( '%d', '%s', '%s', '%d' )
        );
    }

    private static function add_phone( $contact_id, $value ) {
        global $wpdb;
        $normalized = self::normalize_phone( $value );
        if ( ! $normalized ) {
            return false;
        }

        $table = Schema::table( 'contact_phones' );
        $exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE contact_id=%d AND normalized=%s LIMIT 1', $table, absint( $contact_id ), $normalized ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifier uniqueness requires current custom-table state.
        if ( $exists ) {
            return true;
        }
        $primary = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE contact_id=%d', $table, absint( $contact_id ) ) ) ? 0 : 1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Primary-identifier selection requires current custom-table state.
        return (bool) $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional insert into a plugin-owned identifier table.
            $table,
            array(
                'contact_id' => absint( $contact_id ),
                'value'      => self::truncate_text( sanitize_text_field( $value ), 80 ),
                'normalized' => $normalized,
                'is_primary' => $primary,
            ),
            array( '%d', '%s', '%s', '%d' )
        );
    }

    private static function upsert_custom_field( $contact_id, $key, $value ) {
        global $wpdb;
        $table = Schema::table( 'contact_fields' );
        $key   = sanitize_key( $key );
        $value = self::scalar( $value );
        if ( ! $key || '' === $value ) {
            return true;
        }

        $exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE contact_id=%d AND field_key=%s LIMIT 1', $table, absint( $contact_id ), $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom-field upsert requires current custom-table state.
        if ( $exists ) {
            return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom-field upsert updates current plugin-owned data.
                $table,
                array( 'field_value' => $value, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $exists ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        } else {
            return (bool) $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom-field upsert inserts plugin-owned data.
                $table,
                array(
                    'contact_id'  => absint( $contact_id ),
                    'field_key'   => $key,
                    'field_value' => $value,
                    'updated_at'  => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }
    }

    private static function find_by_email( $normalized ) {
        global $wpdb;
        if ( ! $normalized ) {
            return array();
        }
        $table = Schema::table( 'contact_emails' );
        return array_values( array_unique( array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT contact_id FROM %i WHERE normalized=%s', $table, $normalized ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Contact resolution requires current identifier data.
    }

    private static function find_by_phone( $normalized ) {
        global $wpdb;
        if ( ! $normalized ) {
            return array();
        }
        $table = Schema::table( 'contact_phones' );
        return array_values( array_unique( array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT contact_id FROM %i WHERE normalized=%s', $table, $normalized ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Contact resolution requires current identifier data.
    }

    public static function normalize_email( $email ) {
        $email = sanitize_email( $email );
        return $email && strlen( $email ) <= 190 && is_email( $email ) ? strtolower( $email ) : '';
    }

    public static function normalize_phone( $phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );
        return is_string( $digits ) && strlen( $digits ) >= 7 && strlen( $digits ) <= 40 ? $digits : '';
    }

    private static function scalar( $value ) {
        if ( is_array( $value ) ) {
            $flat = array();
            array_walk_recursive(
                $value,
                static function ( $item ) use ( &$flat ) {
                    if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
                        $flat[] = trim( (string) $item );
                    }
                }
            );
            return sanitize_textarea_field( implode( ', ', $flat ) );
        }
        return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
    }

    private static function truncate_text( $value, $length ) {
        $value = (string) $value;
        $length = max( 1, absint( $length ) );
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, $length, 'UTF-8' );
        }
        if ( function_exists( 'iconv_substr' ) ) {
            $truncated = iconv_substr( $value, 0, $length, 'UTF-8' );
            if ( false !== $truncated ) {
                return $truncated;
            }
        }
        if ( preg_match_all( '/./us', $value, $characters ) ) {
            return implode( '', array_slice( $characters[0], 0, $length ) );
        }
        return substr( $value, 0, $length );
    }

    private static function prepare_sql( $sql, $args ) {
        global $wpdb;
        return $args ? $wpdb->prepare( $sql, $args ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL fragments and placeholders are assembled internally from fixed columns.
    }

    private static function rollback_error( $message ) {
        global $wpdb;
        $database_error = $wpdb->last_error;
        Schema::rollback();
        if ( $database_error ) {
            $message .= ' ' . sanitize_text_field( $database_error );
        }
        return new \WP_Error( 'bfcamel_crm_contact_database_error', $message );
    }

    private static function clear_contact_cache( $contact_id ) {
        wp_cache_delete( 'contact:id:' . absint( $contact_id ), self::CACHE_GROUP );
    }
}
