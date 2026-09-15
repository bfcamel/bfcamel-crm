<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DataEnhancements {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public function register() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'notices' ) );
        add_action( 'admin_post_bfcamel_crm_delete_contact', array( $this, 'delete_contact' ) );
        add_action( 'admin_post_bfcamel_crm_delete_submission', array( $this, 'delete_submission' ) );
        add_action( 'bfcamel_crm_submission_created', array( $this, 'submission_created' ), 10, 4 );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'bfcamel-crm' ) ) return;

        $page = Request::get_key( 'page' );

        if ( 'bfcamel-crm-forms' === $page ) {
            $script = 'window.BfCamelCRMBuilder=window.BfCamelCRMBuilder||{};window.BfCamelCRMBuilder.labels=window.BfCamelCRMBuilder.labels||{};'
                . 'window.BfCamelCRMBuilder.labels.contactCity=' . wp_json_encode( __( 'Contact: city', 'bfcamel-crm' ) ) . ';'
                . 'window.BfCamelCRMBuilder.labels.contactSocialPage=' . wp_json_encode( __( 'Contact: social page', 'bfcamel-crm' ) ) . ';';
            wp_add_inline_script( 'bfcamel-crm-admin', $script, 'before' );
            return;
        }

        if ( ! in_array( $page, array( 'bfcamel-crm-contacts', 'bfcamel-crm-submissions' ), true ) ) return;

        $config = array(
            'adminPost' => admin_url( 'admin-post.php' ),
            'labels'    => array(
                'allEmails'         => __( 'All email addresses', 'bfcamel-crm' ),
                'allPhones'         => __( 'All phone numbers', 'bfcamel-crm' ),
                'city'              => __( 'City', 'bfcamel-crm' ),
                'socialPages'       => __( 'Social pages', 'bfcamel-crm' ),
                'customFields'      => __( 'Additional fields', 'bfcamel-crm' ),
                'primary'           => __( 'Primary', 'bfcamel-crm' ),
                'deleteContact'     => __( 'Delete contact permanently', 'bfcamel-crm' ),
                'deleteSubmission'  => __( 'Delete submission permanently', 'bfcamel-crm' ),
                'confirmContact'    => __( 'Delete this contact permanently? This cannot be undone.', 'bfcamel-crm' ),
                'confirmSubmission' => __( 'Delete this submission permanently? This cannot be undone.', 'bfcamel-crm' ),
            ),
        );

        if ( 'bfcamel-crm-contacts' === $page ) {
            $id = Request::get_id( 'id' );
            if ( $id ) {
                $custom = self::contact_export_fields( $id );
                $config['contact'] = array(
                    'id'          => $id,
                    'emails'      => self::identifier_rows( ContactService::get_emails( $id ) ),
                    'phones'      => self::identifier_rows( ContactService::get_phones( $id ) ),
                    'city'        => isset( $custom['city'] ) ? (string) $custom['city'] : '',
                    'socialPages' => self::split_multi_value( isset( $custom['social_page'] ) ? $custom['social_page'] : '' ),
                    'custom'      => array_diff_key( $custom, array( 'city' => true, 'social_page' => true ) ),
                    'nonce'       => current_user_can( 'bfcamel_crm_manage_contacts' ) ? wp_create_nonce( 'bfcamel_crm_delete_contact_' . $id ) : '',
                );
            } else {
                $page_num = max( 1, Request::get_id( 'paged', 1 ) );
                $result = ContactService::query( ContactsPage::filters(), $page_num, 25 );
                $cities = array();
                foreach ( (array) $result['rows'] as $row ) {
                    $custom = ContactService::get_custom_fields( $row->id );
                    $cities[ (string) $row->id ] = isset( $custom['city'] ) ? (string) $custom['city'] : '';
                }
                $config['cities'] = $cities;
            }
        }

        if ( 'bfcamel-crm-submissions' === $page ) {
            $id = Request::get_id( 'id' );
            if ( $id && current_user_can( 'bfcamel_crm_edit_submissions' ) ) {
                $config['submission'] = array(
                    'id'    => $id,
                    'nonce' => wp_create_nonce( 'bfcamel_crm_delete_submission_' . $id ),
                );
            }
        }

        wp_enqueue_script( 'bfcamel-crm-data-enhancements', BFCAMEL_CRM_URL . 'assets/data-enhancements.js', array(), BFCAMEL_CRM_VERSION, true );
        wp_localize_script( 'bfcamel-crm-data-enhancements', 'BfCamelCRMData', $config );
    }

    public function notices() {
        $page = Request::get_key( 'page' );
        if ( 'bfcamel-crm-contacts' === $page && Request::get_flag( 'deleted' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Contact deleted permanently.', 'bfcamel-crm' ) . '</p></div>';
        }
        if ( 'bfcamel-crm-submissions' === $page && Request::get_flag( 'deleted' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Submission deleted permanently.', 'bfcamel-crm' ) . '</p></div>';
        }
    }

    public function submission_created( $submission_id, $contact_id, $form_id, $revision_id ) {
        $contact_id = absint( $contact_id );
        if ( ! $contact_id ) return;
        $pages = self::social_pages_for_contact( $contact_id );
        if ( $pages ) self::write_custom_field( $contact_id, 'social_page', implode( "\n", $pages ) );
    }

    public function delete_contact() {
        if ( ! current_user_can( 'bfcamel_crm_manage_contacts' ) ) wp_die( esc_html__( 'You do not have permission to delete contacts.', 'bfcamel-crm' ) );
        $id = Request::post_id( 'contact_id' );
        check_admin_referer( 'bfcamel_crm_delete_contact_' . $id );
        if ( ! $id || ! ContactService::get( $id ) ) wp_die( esc_html__( 'Contact not found.', 'bfcamel-crm' ) );
        if ( ! Schema::begin_transaction() ) wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );

        global $wpdb;
        $ok = true;
        $ok = $ok && false !== $wpdb->update( Schema::table( 'submissions' ), array( 'contact_id' => 0, 'contact_sync_status' => 'deleted' ), array( 'contact_id' => $id ), array( '%d', '%s' ), array( '%d' ) );
        $ok = $ok && false !== $wpdb->update( Schema::table( 'consent_events' ), array( 'contact_id' => 0 ), array( 'contact_id' => $id ), array( '%d' ), array( '%d' ) );
        foreach ( array( 'contact_emails', 'contact_phones', 'contact_fields', 'contact_tags' ) as $table ) {
            $ok = $ok && false !== $wpdb->delete( Schema::table( $table ), array( 'contact_id' => $id ), array( '%d' ) );
        }
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'notes' ), array( 'entity_type' => 'contact', 'entity_id' => $id ), array( '%s', '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'activity_log' ), array( 'entity_type' => 'contact', 'entity_id' => $id ), array( '%s', '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'contacts' ), array( 'id' => $id ), array( '%d' ) );

        if ( ! $ok || ! Schema::commit() ) {
            Schema::rollback();
            wp_die( esc_html__( 'The contact could not be deleted.', 'bfcamel-crm' ) );
        }
        wp_cache_delete( 'contact:id:' . $id, 'bfcamel_crm' );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&deleted=1' ) );
        exit;
    }

    public function delete_submission() {
        if ( ! current_user_can( 'bfcamel_crm_edit_submissions' ) ) wp_die( esc_html__( 'You do not have permission to delete submissions.', 'bfcamel-crm' ) );
        $id = Request::post_id( 'submission_id' );
        check_admin_referer( 'bfcamel_crm_delete_submission_' . $id );
        if ( ! $id || ! SubmissionService::get( $id ) ) wp_die( esc_html__( 'Submission not found.', 'bfcamel-crm' ) );
        if ( ! Schema::begin_transaction() ) wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );

        global $wpdb;
        $ok = true;
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'submission_tags' ), array( 'submission_id' => $id ), array( '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'consent_events' ), array( 'submission_id' => $id ), array( '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'notes' ), array( 'entity_type' => 'submission', 'entity_id' => $id ), array( '%s', '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'activity_log' ), array( 'entity_type' => 'submission', 'entity_id' => $id ), array( '%s', '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'submissions' ), array( 'id' => $id ), array( '%d' ) );

        if ( ! $ok || ! Schema::commit() ) {
            Schema::rollback();
            wp_die( esc_html__( 'The submission could not be deleted.', 'bfcamel-crm' ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-submissions&deleted=1' ) );
        exit;
    }

    public static function contact_export_fields( $contact_id ) {
        $fields = ContactService::get_custom_fields( $contact_id );
        $pages = self::social_pages_for_contact( $contact_id );
        if ( $pages ) $fields['social_page'] = implode( "\n", $pages );
        return $fields;
    }

    public static function social_pages_for_contact( $contact_id ) {
        global $wpdb;
        $contact_id = absint( $contact_id );
        if ( ! $contact_id ) return array();

        $values = array();
        $existing = ContactService::get_custom_fields( $contact_id );
        foreach ( self::split_multi_value( isset( $existing['social_page'] ) ? $existing['social_page'] : '' ) as $value ) $values[] = $value;

        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT revision_id,payload_json FROM %i WHERE contact_id=%d ORDER BY id ASC', Schema::table( 'submissions' ), $contact_id ) );
        $schemas = array();
        foreach ( (array) $rows as $row ) {
            $revision_id = absint( $row->revision_id );
            if ( ! isset( $schemas[ $revision_id ] ) ) {
                $revision = Repository::get_revision( $revision_id );
                $schemas[ $revision_id ] = $revision ? Repository::decode_schema( $revision ) : array();
            }
            $payload = json_decode( (string) $row->payload_json, true );
            if ( ! is_array( $payload ) ) continue;
            foreach ( (array) $schemas[ $revision_id ] as $field ) {
                $name = sanitize_key( $field['name'] ?? '' );
                if ( ! $name || ! array_key_exists( $name, $payload ) ) continue;
                $mapping = (string) ( $field['mapping'] ?? '' );
                $custom_key = sanitize_key( $field['custom_key'] ?? $name );
                if ( 'contact.custom' !== $mapping || 'social_page' !== $custom_key ) continue;
                foreach ( self::flatten_values( $payload[ $name ] ) as $value ) $values[] = $value;
            }
        }

        $result = array();
        foreach ( $values as $value ) {
            $value = trim( sanitize_text_field( $value ) );
            if ( '' !== $value && ! in_array( $value, $result, true ) ) $result[] = $value;
        }
        return $result;
    }

    private static function identifier_rows( $rows ) {
        $result = array();
        foreach ( (array) $rows as $row ) {
            $result[] = array( 'value' => (string) $row->value, 'primary' => ! empty( $row->is_primary ) );
        }
        return $result;
    }

    private static function split_multi_value( $value ) {
        if ( is_array( $value ) ) return array_values( array_filter( array_map( 'trim', $value ) ) );
        $parts = preg_split( '/\r?\n+/', (string) $value );
        return array_values( array_filter( array_map( 'trim', (array) $parts ) ) );
    }

    private static function flatten_values( $value ) {
        $result = array();
        if ( is_array( $value ) ) {
            array_walk_recursive( $value, static function ( $item ) use ( &$result ) { if ( is_scalar( $item ) ) $result[] = (string) $item; } );
        } elseif ( is_scalar( $value ) ) {
            $result[] = (string) $value;
        }
        return $result;
    }

    private static function write_custom_field( $contact_id, $key, $value ) {
        global $wpdb;
        $table = Schema::table( 'contact_fields' );
        $key = sanitize_key( $key );
        $exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE contact_id=%d AND field_key=%s LIMIT 1', $table, absint( $contact_id ), $key ) );
        if ( $exists ) {
            return false !== $wpdb->update( $table, array( 'field_value' => (string) $value, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $exists ), array( '%s', '%s' ), array( '%d' ) );
        }
        return (bool) $wpdb->insert( $table, array( 'contact_id' => absint( $contact_id ), 'field_key' => $key, 'field_value' => (string) $value, 'updated_at' => current_time( 'mysql' ) ), array( '%d', '%s', '%s', '%s' ) );
    }
}
