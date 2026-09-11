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
        if ( ! Schema::begin_transaction() ) {
            return new \WP_Error( 'bfcamel_crm_note_transaction_failed', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }
        $ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional insert into the plugin's custom notes table.
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
            Schema::rollback();
            return new \WP_Error( 'bfcamel_crm_note_save_failed', __( 'Could not save the note.', 'bfcamel-crm' ) );
        }
        $note_id = absint( $wpdb->insert_id );
        if ( ! Schema::log( $entity_type, $entity_id, 'note_added', 'Internal note added.', array( 'note_id' => $note_id ), absint( $user_id ) ) || ! Schema::commit() ) {
            Schema::rollback();
            return new \WP_Error( 'bfcamel_crm_note_history_failed', __( 'Could not save the note history.', 'bfcamel-crm' ) );
        }
        return $note_id;
    }

    public static function for_entity( $entity_type, $entity_id ) {
        global $wpdb;
        $notes = Schema::table( 'notes' );
        $users = $wpdb->users;
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Notes and their actors must reflect current custom-table state.
            $wpdb->prepare(
                'SELECT n.*,u.display_name AS actor_name FROM %i n LEFT JOIN %i u ON u.ID=n.user_id WHERE n.entity_type=%s AND n.entity_id=%d ORDER BY n.created_at DESC,n.id DESC',
                $notes, $users, sanitize_key( $entity_type ), absint( $entity_id )
            )
        );
    }

    public static function delete_for_entity( $entity_type, $entity_id ) {
        global $wpdb;
        return false !== $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure must delete current custom-table notes.
            Schema::table( 'notes' ),
            array( 'entity_type' => sanitize_key( $entity_type ), 'entity_id' => absint( $entity_id ) ),
            array( '%s', '%d' )
        );
    }
}
