<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContactService {
    public static function resolve_and_sync( $schema, $payload ) {
        $mapped = self::extract_mapped_values( $schema, $payload );
        $email  = self::normalize_email( $mapped['email'] );
        $phone  = self::normalize_phone( $mapped['phone'] );

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
            self::update_contact( $contact_id, $mapped );
            $status = 'linked';
        }

        if ( $email ) {
            self::add_email( $contact_id, $mapped['email'] );
        }
        if ( $phone ) {
            self::add_phone( $contact_id, $mapped['phone'] );
        }

        foreach ( $mapped['custom'] as $key => $value ) {
            self::upsert_custom_field( $contact_id, $key, $value );
        }

        Schema::log(
            'contact',
            $contact_id,
            'form_sync',
            __( 'Contact synchronized from a form submission.', 'bfcamel-crm' ),
            array( 'status' => $status )
        );

        return array( 'contact_id' => $contact_id, 'status' => $status );
    }

    public static function get( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'contacts' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d LIMIT 1", absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function get_emails( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'contact_emails' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE contact_id=%d ORDER BY is_primary DESC,id ASC", absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function get_phones( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'contact_phones' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE contact_id=%d ORDER BY is_primary DESC,id ASC", absint( $contact_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function get_custom_fields( $contact_id ) {
        global $wpdb;
        $table = Schema::table( 'contact_fields' );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT field_key,field_value FROM {$table} WHERE contact_id=%d ORDER BY field_key ASC", absint( $contact_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = array();
        foreach ( (array) $rows as $row ) {
            $result[ $row['field_key'] ] = $row['field_value'];
        }
        return $result;
    }

    public static function find_contact_ids_by_email_value( $email ) {
        return self::find_by_email( self::normalize_email( $email ) );
    }

    public static function anonymize( $contact_id ) {
        global $wpdb;
        $contact_id = absint( $contact_id );
        if ( ! $contact_id ) {
            return false;
        }

        $wpdb->delete( Schema::table( 'contact_emails' ), array( 'contact_id' => $contact_id ), array( '%d' ) );
        $wpdb->delete( Schema::table( 'contact_phones' ), array( 'contact_id' => $contact_id ), array( '%d' ) );
        $wpdb->delete( Schema::table( 'contact_fields' ), array( 'contact_id' => $contact_id ), array( '%d' ) );
        $wpdb->update(
            Schema::table( 'contacts' ),
            array(
                'display_name' => sprintf( __( 'Anonymized contact #%d', 'bfcamel-crm' ), $contact_id ),
                'organization' => '',
                'status'       => 'anonymized',
                'updated_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => $contact_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        Schema::log( 'contact', $contact_id, 'anonymized', __( 'Contact personal data anonymized.', 'bfcamel-crm' ) );
        return true;
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
        $name = sanitize_text_field( $mapped['name'] );
        if ( '' === $name ) {
            $name = sanitize_email( $mapped['email'] );
        }
        if ( '' === $name ) {
            $name = sanitize_text_field( $mapped['phone'] );
        }
        if ( '' === $name ) {
            $name = __( 'Website contact', 'bfcamel-crm' );
        }

        $ok = $wpdb->insert(
            Schema::table( 'contacts' ),
            array(
                'display_name' => $name,
                'organization' => sanitize_text_field( $mapped['organization'] ),
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        return $ok ? absint( $wpdb->insert_id ) : 0;
    }

    private static function update_contact( $contact_id, $mapped ) {
        global $wpdb;
        $contact = self::get( $contact_id );
        if ( ! $contact ) {
            return;
        }

        $name = sanitize_text_field( $mapped['name'] );
        $org  = sanitize_text_field( $mapped['organization'] );
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

        $wpdb->update( Schema::table( 'contacts' ), $data, array( 'id' => absint( $contact_id ) ), $formats, array( '%d' ) );
    }

    private static function add_email( $contact_id, $value ) {
        global $wpdb;
        $normalized = self::normalize_email( $value );
        if ( ! $normalized ) {
            return;
        }

        $table = Schema::table( 'contact_emails' );
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE contact_id=%d AND normalized=%s LIMIT 1", absint( $contact_id ), $normalized ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $exists ) {
            return;
        }
        $primary = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE contact_id=%d", absint( $contact_id ) ) ) ? 0 : 1; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->insert(
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
            return;
        }

        $table = Schema::table( 'contact_phones' );
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE contact_id=%d AND normalized=%s LIMIT 1", absint( $contact_id ), $normalized ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $exists ) {
            return;
        }
        $primary = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE contact_id=%d", absint( $contact_id ) ) ) ? 0 : 1; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->insert(
            $table,
            array(
                'contact_id' => absint( $contact_id ),
                'value'      => sanitize_text_field( $value ),
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
            return;
        }

        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE contact_id=%d AND field_key=%s LIMIT 1", absint( $contact_id ), $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $exists ) {
            $wpdb->update(
                $table,
                array( 'field_value' => $value, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $exists ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
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
        return array_values( array_unique( array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT contact_id FROM {$table} WHERE normalized=%s", $normalized ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function find_by_phone( $normalized ) {
        global $wpdb;
        if ( ! $normalized ) {
            return array();
        }
        $table = Schema::table( 'contact_phones' );
        return array_values( array_unique( array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT contact_id FROM {$table} WHERE normalized=%s", $normalized ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function normalize_email( $email ) {
        $email = sanitize_email( $email );
        return $email && is_email( $email ) ? strtolower( $email ) : '';
    }

    public static function normalize_phone( $phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );
        return is_string( $digits ) && strlen( $digits ) >= 7 ? $digits : '';
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
}
