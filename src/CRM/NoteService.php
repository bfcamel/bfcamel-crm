<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NoteService {
    public static function add( $entity_type, $entity_id, $text, $user_id ) {
        global $wpdb;
        $entity_type = sanitize_key( $entity_type );
        $entity_id = absint( $entity_id );
        $text = trim( sanitize_textarea_field( $text ) );
        if ( ! in_array( $entity_type, array( 'contact', 'submission' ), true ) || ! $entity_id || '' === $text ) {
            return new \WP_Error( 'bfcamel_crm_invalid_note', __( 'Enter a note.', 'bfcamel-crm' ) );
        }
        $ok = $wpdb->insert(
            Schema::table( 'notes' ),
            array(
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'note_text'   => $text,
                'user_id'     => absint( $user_id ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%d', '%s' )
        );
        if ( ! $ok ) {
            return new \WP_Error( 'bfcamel_crm_note_save_failed', __( 'Could not save the note.', 'bfcamel-crm' ) );
        }
        Schema::log( $entity_type, $entity_id, 'note_added', 'Internal note added.', array( 'note_id' => absint( $wpdb->insert_id ) ), absint( $user_id ) );
        return absint( $wpdb->insert_id );
    }

    public static function for_entity( $entity_type, $entity_id ) {
        global $wpdb;
        $notes = Schema::table( 'notes' );
        $users = $wpdb->users;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT n.*,u.display_name AS actor_name FROM {$notes} n LEFT JOIN {$users} u ON u.ID=n.user_id WHERE n.entity_type=%s AND n.entity_id=%d ORDER BY n.created_at DESC,n.id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                sanitize_key( $entity_type ), absint( $entity_id )
            )
        );
    }

    public static function delete_for_entity( $entity_type, $entity_id ) {
        global $wpdb;
        return false !== $wpdb->delete(
            Schema::table( 'notes' ),
            array( 'entity_type' => sanitize_key( $entity_type ), 'entity_id' => absint( $entity_id ) ),
            array( '%s', '%d' )
        );
    }
}
