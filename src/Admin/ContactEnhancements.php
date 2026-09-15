<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Export\XlsxWriter;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Contact-oriented UX additions that intentionally live outside the core
 * form/submission classes so older form revisions remain compatible.
 */
final class ContactEnhancements {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function register() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ), 30 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

        // Run before the built-in handlers. For non-enhancement requests we simply return.
        add_action( 'admin_post_bfcamel_crm_bulk_contacts', array( $this, 'intercept_bulk_contacts' ), 1 );
        add_action( 'admin_post_bfcamel_crm_bulk_submissions', array( $this, 'intercept_bulk_submissions' ), 1 );
        add_action( 'admin_post_bfcamel_crm_export', array( $this, 'intercept_contact_export' ), 1 );

        add_action( 'admin_post_bfcamel_crm_delete_contact', array( $this, 'delete_contact' ) );
        add_action( 'admin_post_bfcamel_crm_delete_submission', array( $this, 'delete_submission' ) );
    }

    public function admin_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'bfcamel-crm' ) ) {
            return;
        }

        $page = Request::get_key( 'page' );
        if ( ! in_array( $page, array( 'bfcamel-crm-forms', 'bfcamel-crm-contacts', 'bfcamel-crm-submissions' ), true ) ) {
            return;
        }

        wp_enqueue_script(
            'bfcamel-crm-contact-enhancements',
            BFCAMEL_CRM_URL . 'assets/contact-enhancements.js',
            array( 'bfcamel-crm-admin' ),
            BFCAMEL_CRM_VERSION,
            true
        );

        $config = array(
            'page' => $page,
            'labels' => array(
                'contactCity'        => __( 'Contact: city', 'bfcamel-crm' ),
                'contactSocialPage'  => __( 'Contact: social page', 'bfcamel-crm' ),
                'city'               => __( 'City', 'bfcamel-crm' ),
                'socialPages'        => __( 'Social pages', 'bfcamel-crm' ),
                'knownEmails'        => __( 'Known email addresses', 'bfcamel-crm' ),
                'knownPhones'        => __( 'Known phone numbers', 'bfcamel-crm' ),
                'additionalData'     => __( 'Additional contact data', 'bfcamel-crm' ),
                'customFields'       => __( 'Custom fields', 'bfcamel-crm' ),
                'primary'            => __( 'primary', 'bfcamel-crm' ),
                'deletePermanently'  => __( 'Delete permanently', 'bfcamel-crm' ),
                'deleteContact'      => __( 'Delete contact permanently', 'bfcamel-crm' ),
                'deleteSubmission'   => __( 'Delete submission permanently', 'bfcamel-crm' ),
                'deleteContactWarn'  => __( 'Permanently delete this contact and all submissions linked to it? This cannot be undone.', 'bfcamel-crm' ),
                'deleteSubmissionWarn'=> __( 'Permanently delete this submission? This cannot be undone.', 'bfcamel-crm' ),
                'bulkDeleteWarn'     => __( 'Permanently delete the selected records? This cannot be undone.', 'bfcamel-crm' ),
                'none'               => __( '—', 'bfcamel-crm' ),
            ),
            'adminPostUrl' => admin_url( 'admin-post.php' ),
        );

        if ( 'bfcamel-crm-contacts' === $page ) {
            $id = Request::get_id( 'id' );
            if ( $id ) {
                $config['contactDetail'] = $this->contact_detail_payload( $id );
                $config['deleteContact'] = array(
                    'id'    => $id,
                    'nonce' => wp_create_nonce( 'bfcamel_crm_delete_contact_' . $id ),
                );
            } else {
                $filters = ContactsPage::filters();
                $paged = max( 1, Request::get_id( 'paged', 1 ) );
                $result = ContactService::query( $filters, $paged, 25 );
                $ids = array_map( 'absint', wp_list_pluck( (array) $result['rows'], 'id' ) );
                $config['contactListMeta'] = $this->contact_list_meta( $ids );
            }
        } elseif ( 'bfcamel-crm-submissions' === $page ) {
            $id = Request::get_id( 'id' );
            if ( $id ) {
                $config['deleteSubmission'] = array(
                    'id'    => $id,
                    'nonce' => wp_create_nonce( 'bfcamel_crm_delete_submission_' . $id ),
                );
            }
        }

        wp_localize_script( 'bfcamel-crm-contact-enhancements', 'BfCamelCRMEnhancements', $config );
    }

    private function contact_detail_payload( $contact_id ) {
        $contact_id = absint( $contact_id );
        $contact = ContactService::get( $contact_id );
        if ( ! $contact ) {
            return array();
        }

        $emails = array();
        foreach ( ContactService::get_emails( $contact_id ) as $email ) {
            $emails[] = array(
                'value'      => (string) $email->value,
                'is_primary' => ! empty( $email->is_primary ),
            );
        }
        $phones = array();
        foreach ( ContactService::get_phones( $contact_id ) as $phone ) {
            $phones[] = array(
                'value'      => (string) $phone->value,
                'is_primary' => ! empty( $phone->is_primary ),
            );
        }

        $custom = ContactService::get_custom_fields( $contact_id );
        $special = $this->special_fields_for_contacts( array( $contact_id ) );
        $special = isset( $special[ $contact_id ] ) ? $special[ $contact_id ] : array( 'city' => '', 'social_pages' => array() );
        unset( $custom['city'], $custom['social_page'] );

        return array(
            'id'          => $contact_id,
            'name'        => (string) $contact->display_name,
            'organization'=> (string) $contact->organization,
            'city'        => (string) $special['city'],
            'emails'      => $emails,
            'phones'      => $phones,
            'socialPages' => array_values( $special['social_pages'] ),
            'custom'      => $custom,
        );
    }

    private function contact_list_meta( $contact_ids ) {
        $special = $this->special_fields_for_contacts( $contact_ids );
        $result = array();
        foreach ( array_map( 'absint', (array) $contact_ids ) as $contact_id ) {
            $item = isset( $special[ $contact_id ] ) ? $special[ $contact_id ] : array( 'city' => '', 'social_pages' => array() );
            $result[ (string) $contact_id ] = array(
                'city'        => (string) $item['city'],
                'socialPages' => array_values( $item['social_pages'] ),
            );
        }
        return $result;
    }

    /**
     * Returns a single city and every known social page for each contact.
     * social_page stays backward compatible with existing contact.custom fields,
     * while history is rebuilt from immutable form submissions so values are not lost.
     */
    private function special_fields_for_contacts( $contact_ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $contact_ids ) ) ) );
        $result = array();
        foreach ( $ids as $id ) {
            $result[ $id ] = array( 'city' => '', 'social_pages' => array() );
        }
        if ( ! $ids ) {
            return $result;
        }

        foreach ( array_chunk( $ids, 200 ) as $chunk ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
            $fields_sql = $wpdb->prepare(
                'SELECT contact_id,field_key,field_value FROM %i WHERE contact_id IN (' . $placeholders . ') AND field_key IN (%s,%s)',
                array_merge( array( Schema::table( 'contact_fields' ) ), $chunk, array( 'city', 'social_page' ) )
            );
            $rows = $wpdb->get_results( $fields_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only query against plugin-owned tables with prepared identifiers/values.
            foreach ( (array) $rows as $row ) {
                $contact_id = absint( $row->contact_id );
                if ( 'city' === $row->field_key ) {
                    $result[ $contact_id ]['city'] = sanitize_text_field( $row->field_value );
                } elseif ( 'social_page' === $row->field_key ) {
                    foreach ( $this->split_social_pages( $row->field_value ) as $page ) {
                        $this->push_unique( $result[ $contact_id ]['social_pages'], $page );
                    }
                }
            }

            $submissions_sql = $wpdb->prepare(
                'SELECT contact_id,revision_id,payload_json FROM %i WHERE contact_id IN (' . $placeholders . ') ORDER BY submitted_at ASC,id ASC',
                array_merge( array( Schema::table( 'submissions' ) ), $chunk )
            );
            $submissions = $wpdb->get_results( $submissions_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Rebuild known contact channels from immutable submissions.
            foreach ( (array) $submissions as $submission ) {
                $contact_id = absint( $submission->contact_id );
                $revision = Repository::get_revision( absint( $submission->revision_id ) );
                $schema = Repository::decode_schema( $revision );
                $payload = json_decode( (string) $submission->payload_json, true );
                if ( ! is_array( $payload ) ) {
                    continue;
                }
                foreach ( $schema as $field ) {
                    $name = sanitize_key( $field['name'] ?? '' );
                    if ( ! $name || ! array_key_exists( $name, $payload ) ) {
                        continue;
                    }
                    $mapping = (string) ( $field['mapping'] ?? 'submission_only' );
                    $custom_key = sanitize_key( $field['custom_key'] ?? '' );
                    $is_city = 'contact.city' === $mapping || ( 'contact.custom' === $mapping && 'city' === $custom_key );
                    $is_social = 'contact.social_page' === $mapping || ( 'contact.custom' === $mapping && 'social_page' === $custom_key );
                    if ( $is_city && '' === $result[ $contact_id ]['city'] ) {
                        $result[ $contact_id ]['city'] = sanitize_text_field( $this->scalar( $payload[ $name ] ) );
                    }
                    if ( $is_social ) {
                        foreach ( $this->values( $payload[ $name ] ) as $page ) {
                            $this->push_unique( $result[ $contact_id ]['social_pages'], $page );
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function split_social_pages( $value ) {
        $parts = preg_split( '/[\r\n]+/u', (string) $value );
        $result = array();
        foreach ( (array) $parts as $part ) {
            $part = sanitize_text_field( trim( $part ) );
            if ( '' !== $part ) {
                $result[] = $part;
            }
        }
        return $result;
    }

    private function values( $value ) {
        if ( is_array( $value ) ) {
            $result = array();
            array_walk_recursive(
                $value,
                static function ( $item ) use ( &$result ) {
                    if ( is_scalar( $item ) ) {
                        $item = sanitize_text_field( trim( (string) $item ) );
                        if ( '' !== $item ) {
                            $result[] = $item;
                        }
                    }
                }
            );
            return $result;
        }
        $value = is_scalar( $value ) ? sanitize_text_field( trim( (string) $value ) ) : '';
        return '' === $value ? array() : array( $value );
    }

    private function scalar( $value ) {
        $values = $this->values( $value );
        return $values ? (string) $values[0] : '';
    }

    private function push_unique( &$items, $value ) {
        $value = sanitize_text_field( trim( (string) $value ) );
        if ( '' === $value ) {
            return;
        }
        foreach ( $items as $existing ) {
            if ( 0 === strcasecmp( $existing, $value ) ) {
                return;
            }
        }
        $items[] = $value;
    }

    public function intercept_contact_export() {
        if ( 'contacts' !== Request::get_key( 'type' ) ) {
            return;
        }
        $format = Request::get_key( 'format' );
        if ( ! in_array( $format, array( 'csv', 'xlsx' ), true ) ) {
            return;
        }
        if ( ! current_user_can( 'bfcamel_crm_export_data' ) || ! current_user_can( 'bfcamel_crm_manage_contacts' ) ) {
            wp_die( esc_html__( 'You do not have permission to export CRM data.', 'bfcamel-crm' ) );
        }
        check_admin_referer( 'bfcamel_crm_export_contacts_' . $format );
        $this->download_contacts( ContactsPage::filters(), $format );
    }

    public function intercept_bulk_contacts() {
        $export = Request::post_key( 'export_selected' );
        $action = Request::post_key( 'bulk_action' );
        if ( ! in_array( $export, array( 'csv', 'xlsx' ), true ) && 'delete' !== $action ) {
            return;
        }
        if ( ! current_user_can( 'bfcamel_crm_manage_contacts' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage contacts.', 'bfcamel-crm' ) );
        }
        check_admin_referer( 'bfcamel_crm_bulk_contacts' );
        $ids = Request::post_id_list( 'contact_ids' );
        if ( ! $ids ) {
            wp_die( esc_html__( 'Select at least one contact.', 'bfcamel-crm' ), '', array( 'back_link' => true ) );
        }

        if ( $export ) {
            if ( ! current_user_can( 'bfcamel_crm_export_data' ) ) {
                wp_die( esc_html__( 'You do not have permission to export CRM data.', 'bfcamel-crm' ) );
            }
            $this->download_contacts( array( 'ids' => $ids ), $export, true );
        }

        $deleted = $this->hard_delete_contacts( $ids );
        if ( is_wp_error( $deleted ) ) {
            wp_die( esc_html( $deleted->get_error_message() ), esc_html__( 'Could not delete contacts', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&bulk_deleted=' . absint( $deleted ) ) );
        exit;
    }

    public function intercept_bulk_submissions() {
        if ( 'delete' !== Request::post_key( 'bulk_action' ) ) {
            return;
        }
        if ( ! current_user_can( 'bfcamel_crm_edit_submissions' ) ) {
            wp_die( esc_html__( 'You do not have permission to delete submissions.', 'bfcamel-crm' ) );
        }
        check_admin_referer( 'bfcamel_crm_bulk_submissions' );
        $ids = Request::post_id_list( 'submission_ids' );
        if ( ! $ids ) {
            wp_die( esc_html__( 'Select at least one submission.', 'bfcamel-crm' ), '', array( 'back_link' => true ) );
        }
        $deleted = $this->hard_delete_submissions( $ids );
        if ( is_wp_error( $deleted ) ) {
            wp_die( esc_html( $deleted->get_error_message() ), esc_html__( 'Could not delete submissions', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-submissions&bulk_deleted=' . absint( $deleted ) ) );
        exit;
    }

    public function delete_contact() {
        if ( ! current_user_can( 'bfcamel_crm_manage_contacts' ) ) {
            wp_die( esc_html__( 'You do not have permission to delete contacts.', 'bfcamel-crm' ) );
        }
        $id = Request::post_id( 'contact_id' );
        check_admin_referer( 'bfcamel_crm_delete_contact_' . $id );
        $deleted = $this->hard_delete_contacts( array( $id ) );
        if ( is_wp_error( $deleted ) ) {
            wp_die( esc_html( $deleted->get_error_message() ), esc_html__( 'Could not delete contact', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&deleted=1' ) );
        exit;
    }

    public function delete_submission() {
        if ( ! current_user_can( 'bfcamel_crm_edit_submissions' ) ) {
            wp_die( esc_html__( 'You do not have permission to delete submissions.', 'bfcamel-crm' ) );
        }
        $id = Request::post_id( 'submission_id' );
        check_admin_referer( 'bfcamel_crm_delete_submission_' . $id );
        $deleted = $this->hard_delete_submissions( array( $id ) );
        if ( is_wp_error( $deleted ) ) {
            wp_die( esc_html( $deleted->get_error_message() ), esc_html__( 'Could not delete submission', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-submissions&deleted=1' ) );
        exit;
    }

    private function hard_delete_submissions( $ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
        if ( ! $ids ) {
            return 0;
        }
        if ( ! Schema::begin_transaction() ) {
            return new \WP_Error( 'bfcamel_crm_delete_transaction', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }
        $result = $this->delete_submission_rows( $ids );
        if ( is_wp_error( $result ) ) {
            Schema::rollback();
            return $result;
        }
        if ( ! Schema::commit() ) {
            Schema::rollback();
            return new \WP_Error( 'bfcamel_crm_delete_commit', __( 'Could not commit the deletion transaction.', 'bfcamel-crm' ) );
        }
        return $result;
    }

    private function hard_delete_contacts( $ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
        if ( ! $ids ) {
            return 0;
        }
        if ( ! Schema::begin_transaction() ) {
            return new \WP_Error( 'bfcamel_crm_delete_transaction', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }

        $submission_ids = array();
        foreach ( array_chunk( $ids, 200 ) as $chunk ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
            $sql = $wpdb->prepare(
                'SELECT id FROM %i WHERE contact_id IN (' . $placeholders . ')',
                array_merge( array( Schema::table( 'submissions' ) ), $chunk )
            );
            $submission_ids = array_merge( $submission_ids, array_map( 'absint', (array) $wpdb->get_col( $sql ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Permanent contact deletion must find all linked submissions.
        }
        if ( $submission_ids ) {
            $deleted_submissions = $this->delete_submission_rows( $submission_ids );
            if ( is_wp_error( $deleted_submissions ) ) {
                Schema::rollback();
                return $deleted_submissions;
            }
        }

        $tables = array( 'contact_emails', 'contact_phones', 'contact_fields', 'contact_tags', 'consent_events' );
        foreach ( $tables as $suffix ) {
            if ( false === $this->delete_ids( Schema::table( $suffix ), 'contact_id', $ids ) ) {
                Schema::rollback();
                return new \WP_Error( 'bfcamel_crm_delete_contact_related', __( 'Could not delete related contact data.', 'bfcamel-crm' ) );
            }
        }
        if ( false === $this->delete_entity_rows( Schema::table( 'notes' ), 'contact', $ids ) || false === $this->delete_entity_rows( Schema::table( 'activity_log' ), 'contact', $ids ) ) {
            Schema::rollback();
            return new \WP_Error( 'bfcamel_crm_delete_contact_history', __( 'Could not delete contact history.', 'bfcamel-crm' ) );
        }
        if ( false === $this->delete_ids( Schema::table( 'contacts' ), 'id', $ids ) ) {
            Schema::rollback();
            return new \WP_Error( 'bfcamel_crm_delete_contacts', __( 'Could not delete contacts.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::commit() ) {
            Schema::rollback();
            return new \WP_Error( 'bfcamel_crm_delete_commit', __( 'Could not commit the deletion transaction.', 'bfcamel-crm' ) );
        }
        foreach ( $ids as $id ) {
            wp_cache_delete( 'contact:id:' . $id, ContactService::CACHE_GROUP );
        }
        wp_cache_delete( 'contacts:count', ContactService::CACHE_GROUP );
        return count( $ids );
    }

    private function delete_submission_rows( $ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
        if ( ! $ids ) {
            return 0;
        }
        if ( false === $this->delete_ids( Schema::table( 'consent_events' ), 'submission_id', $ids ) ) {
            return new \WP_Error( 'bfcamel_crm_delete_submission_consents', __( 'Could not delete submission consent events.', 'bfcamel-crm' ) );
        }
        if ( false === $this->delete_ids( Schema::table( 'submission_tags' ), 'submission_id', $ids ) ) {
            return new \WP_Error( 'bfcamel_crm_delete_submission_tags', __( 'Could not delete submission tags.', 'bfcamel-crm' ) );
        }
        if ( false === $this->delete_entity_rows( Schema::table( 'notes' ), 'submission', $ids ) || false === $this->delete_entity_rows( Schema::table( 'activity_log' ), 'submission', $ids ) ) {
            return new \WP_Error( 'bfcamel_crm_delete_submission_history', __( 'Could not delete submission history.', 'bfcamel-crm' ) );
        }
        if ( false === $this->delete_ids( Schema::table( 'submissions' ), 'id', $ids ) ) {
            return new \WP_Error( 'bfcamel_crm_delete_submissions', __( 'Could not delete submissions.', 'bfcamel-crm' ) );
        }
        return count( $ids );
    }

    private function delete_ids( $table, $column, $ids ) {
        global $wpdb;
        foreach ( array_chunk( $ids, 200 ) as $chunk ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
            $sql = $wpdb->prepare(
                'DELETE FROM %i WHERE %i IN (' . $placeholders . ')',
                array_merge( array( $table, $column ), $chunk )
            );
            if ( false === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Permanent deletion from plugin-owned tables.
                return false;
            }
        }
        return true;
    }

    private function delete_entity_rows( $table, $entity_type, $ids ) {
        global $wpdb;
        foreach ( array_chunk( $ids, 200 ) as $chunk ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
            $sql = $wpdb->prepare(
                'DELETE FROM %i WHERE entity_type=%s AND entity_id IN (' . $placeholders . ')',
                array_merge( array( $table, $entity_type ), $chunk )
            );
            if ( false === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Permanent deletion from plugin-owned history tables.
                return false;
            }
        }
        return true;
    }

    private function download_contacts( $filters, $format, $selected = false ) {
        $contacts = ContactService::all_for_export( $filters );
        $ids = array_map( 'absint', wp_list_pluck( $contacts, 'id' ) );
        $custom = $this->custom_fields_for_contacts( $ids );
        $special = $this->special_fields_for_contacts( $ids );

        $custom_keys = array();
        foreach ( $custom as $fields ) {
            foreach ( array_keys( $fields ) as $key ) {
                if ( in_array( $key, array( 'city', 'social_page' ), true ) ) {
                    continue;
                }
                if ( ! in_array( $key, $custom_keys, true ) ) {
                    $custom_keys[] = $key;
                }
            }
        }
        sort( $custom_keys, SORT_NATURAL | SORT_FLAG_CASE );

        $headers = array(
            'ID',
            __( 'Name', 'bfcamel-crm' ),
            __( 'Email', 'bfcamel-crm' ),
            __( 'Phone', 'bfcamel-crm' ),
            __( 'Organization', 'bfcamel-crm' ),
            __( 'City', 'bfcamel-crm' ),
            __( 'Social pages', 'bfcamel-crm' ),
            __( 'Status', 'bfcamel-crm' ),
            __( 'Tags', 'bfcamel-crm' ),
            __( 'Personal data consent', 'bfcamel-crm' ),
            __( 'Marketing consent', 'bfcamel-crm' ),
        );
        foreach ( $custom_keys as $key ) {
            /* translators: %s: custom contact field key. */
            $headers[] = sprintf( __( 'Custom: %s', 'bfcamel-crm' ), $key );
        }
        $headers[] = __( 'Created', 'bfcamel-crm' );
        $headers[] = __( 'Updated', 'bfcamel-crm' );

        $consent_statuses = \BfCamel\CRM\Consent\ConsentService::statuses();
        $rows = array();
        foreach ( $contacts as $contact ) {
            $id = absint( $contact->id );
            $pd = sanitize_key( $contact->personal_data_consent ?: 'unknown' );
            $mk = sanitize_key( $contact->marketing_consent ?: 'unknown' );
            $item = isset( $special[ $id ] ) ? $special[ $id ] : array( 'city' => '', 'social_pages' => array() );
            $row = array(
                $id,
                $contact->display_name,
                $contact->email_values,
                $contact->phone_values,
                $contact->organization,
                $item['city'],
                implode( '; ', $item['social_pages'] ),
                $contact->status,
                $contact->tag_names,
                $consent_statuses[ $pd ] ?? $pd,
                $consent_statuses[ $mk ] ?? $mk,
            );
            foreach ( $custom_keys as $key ) {
                $row[] = isset( $custom[ $id ][ $key ] ) ? $custom[ $id ][ $key ] : '';
            }
            $row[] = $contact->created_at;
            $row[] = $contact->updated_at;
            $rows[] = $row;
        }

        $suffix = $selected ? '-selected' : '';
        $filename = 'bfcamel-crm-contacts' . $suffix . '-' . gmdate( 'Y-m-d-His' ) . '.' . $format;
        if ( 'csv' === $format ) {
            $this->csv( $filename, $headers, $rows );
        }

        $path = XlsxWriter::create( $headers, $rows );
        if ( is_wp_error( $path ) ) {
            wp_die( esc_html( $path->get_error_message() ), esc_html__( 'Could not export data', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        wp_delete_file( $path );
        exit;
    }

    private function custom_fields_for_contacts( $contact_ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $contact_ids ) ) ) );
        $result = array();
        foreach ( $ids as $id ) {
            $result[ $id ] = array();
        }
        foreach ( array_chunk( $ids, 200 ) as $chunk ) {
            if ( ! $chunk ) {
                continue;
            }
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
            $sql = $wpdb->prepare(
                'SELECT contact_id,field_key,field_value FROM %i WHERE contact_id IN (' . $placeholders . ') ORDER BY field_key ASC',
                array_merge( array( Schema::table( 'contact_fields' ) ), $chunk )
            );
            $rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Export current custom fields from plugin-owned tables.
            foreach ( (array) $rows as $row ) {
                $result[ absint( $row->contact_id ) ][ sanitize_key( $row->field_key ) ] = (string) $row->field_value;
            }
        }
        return $result;
    }

    private function csv( $filename, $headers, $rows ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        $stream = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fwrite( $stream, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fputcsv( $stream, $headers );
        foreach ( $rows as $row ) {
            fputcsv( $stream, array_map( array( $this, 'safe_csv_cell' ), $row ) );
        }
        fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    public function safe_csv_cell( $value ) {
        $value = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
        return preg_match( '/^[=+\-@\t\r]/u', $value ) ? "'" . $value : $value;
    }

    public function admin_notices() {
        $page = Request::get_key( 'page' );
        if ( 'bfcamel-crm-contacts' === $page ) {
            if ( Request::get_flag( 'deleted' ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Contact permanently deleted.', 'bfcamel-crm' ) . '</p></div>';
            }
            $count = Request::get_id( 'bulk_deleted' );
            if ( $count ) {
                /* translators: %d: number of deleted contacts. */
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Permanently deleted contacts: %d.', 'bfcamel-crm' ), $count ) ) . '</p></div>';
            }
        }
        if ( 'bfcamel-crm-submissions' === $page ) {
            if ( Request::get_flag( 'deleted' ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Submission permanently deleted.', 'bfcamel-crm' ) . '</p></div>';
            }
            $count = Request::get_id( 'bulk_deleted' );
            if ( $count ) {
                /* translators: %d: number of deleted submissions. */
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Permanently deleted submissions: %d.', 'bfcamel-crm' ), $count ) ) . '</p></div>';
            }
        }
    }
}
