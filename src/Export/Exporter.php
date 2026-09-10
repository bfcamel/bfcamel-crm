<?php
namespace BfCamel\CRM\Export;

use BfCamel\CRM\Admin\ContactsPage;
use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Exporter {
    public static function handle() {
        if ( ! current_user_can( 'bfcamel_crm_export_data' ) ) {
            wp_die( esc_html__( 'You do not have permission to export CRM data.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::is_current() ) {
            wp_die( esc_html( Schema::readiness_message() ), esc_html__( 'CRM database update required', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        $type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
        $format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : '';
        if ( ! in_array( $type, array( 'contacts', 'submissions' ), true ) || ! in_array( $format, array( 'csv', 'xlsx' ), true ) ) {
            wp_die( esc_html__( 'Invalid export request.', 'bfcamel-crm' ) );
        }
        $source_capability = 'contacts' === $type ? 'bfcamel_crm_manage_contacts' : 'bfcamel_crm_view_submissions';
        if ( ! current_user_can( $source_capability ) ) {
            wp_die( esc_html__( 'You do not have permission to export this CRM area.', 'bfcamel-crm' ) );
        }
        check_admin_referer( 'bfcamel_crm_export_' . $type . '_' . $format );

        if ( 'contacts' === $type ) {
            list( $headers, $rows ) = self::contacts();
        } else {
            list( $headers, $rows ) = self::submissions();
        }
        $filename = 'bfcamel-crm-' . $type . '-' . gmdate( 'Y-m-d-His' ) . '.' . $format;
        self::download( $filename, $format, $headers, $rows );
    }

    public static function download_selected_contacts( $ids, $format ) {
        if ( ! current_user_can( 'bfcamel_crm_export_data' ) || ! current_user_can( 'bfcamel_crm_manage_contacts' ) ) {
            wp_die( esc_html__( 'You do not have permission to export CRM data.', 'bfcamel-crm' ) );
        }
        $format = sanitize_key( $format );
        if ( ! in_array( $format, array( 'csv', 'xlsx' ), true ) ) {
            wp_die( esc_html__( 'Invalid export request.', 'bfcamel-crm' ) );
        }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
        if ( ! $ids ) {
            wp_die( esc_html__( 'Select at least one contact.', 'bfcamel-crm' ) );
        }
        list( $headers, $rows ) = self::contacts( array( 'ids' => $ids ) );
        self::download( 'bfcamel-crm-contacts-selected-' . gmdate( 'Y-m-d-His' ) . '.' . $format, $format, $headers, $rows );
    }

    private static function contacts( $override_filters = null ) {
        $filters = is_array( $override_filters ) ? $override_filters : ContactsPage::filters();
        $headers = array( 'ID', __( 'Name', 'bfcamel-crm' ), __( 'Email', 'bfcamel-crm' ), __( 'Phone', 'bfcamel-crm' ), __( 'Organization', 'bfcamel-crm' ), __( 'Status', 'bfcamel-crm' ), __( 'Tags', 'bfcamel-crm' ), __( 'Personal data consent', 'bfcamel-crm' ), __( 'Marketing consent', 'bfcamel-crm' ), __( 'Created', 'bfcamel-crm' ), __( 'Updated', 'bfcamel-crm' ) );
        $rows = array();
        $consent_statuses = ConsentService::statuses();
        foreach ( ContactService::all_for_export( $filters ) as $contact ) {
            $personal_data = sanitize_key( $contact->personal_data_consent ?: 'unknown' );
            $marketing = sanitize_key( $contact->marketing_consent ?: 'unknown' );
            $rows[] = array( $contact->id, $contact->display_name, $contact->email_values, $contact->phone_values, $contact->organization, $contact->status, $contact->tag_names, $consent_statuses[ $personal_data ] ?? $personal_data, $consent_statuses[ $marketing ] ?? $marketing, $contact->created_at, $contact->updated_at );
        }
        return array( $headers, $rows );
    }

    private static function submissions() {
        $filters = self::submission_filters();
        $items = SubmissionService::all_for_export( $filters );
        $payload_keys = array();
        $payloads = array();
        foreach ( $items as $item ) {
            $payload = json_decode( $item->payload_json, true );
            $payload = is_array( $payload ) ? $payload : array();
            $payloads[ $item->id ] = $payload;
            foreach ( array_keys( $payload ) as $key ) {
                $key = sanitize_key( $key );
                if ( $key && ! in_array( $key, $payload_keys, true ) ) {
                    $payload_keys[] = $key;
                }
            }
        }
        sort( $payload_keys );

        $headers = array( 'ID', 'UUID', __( 'Form', 'bfcamel-crm' ), __( 'Contact', 'bfcamel-crm' ), __( 'Status', 'bfcamel-crm' ), __( 'Priority', 'bfcamel-crm' ), __( 'Responsible', 'bfcamel-crm' ), __( 'Tags', 'bfcamel-crm' ), __( 'Received', 'bfcamel-crm' ), __( 'Source URL', 'bfcamel-crm' ) );
        foreach ( $payload_keys as $key ) {
            /* translators: %s: form field key. */
            $headers[] = sprintf( __( 'Field: %s', 'bfcamel-crm' ), $key );
        }

        $rows = array();
        foreach ( $items as $item ) {
            $row = array( $item->id, $item->submission_uuid, $item->form_name, $item->contact_name, SubmissionService::status_label( $item->status ), SubmissionService::priority_label( $item->priority ), $item->assignee_name, $item->tag_names, $item->submitted_at, $item->source_url );
            foreach ( $payload_keys as $key ) {
                $value = $payloads[ $item->id ][ $key ] ?? '';
                $row[] = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
            }
            $rows[] = $row;
        }
        return array( $headers, $rows );
    }

    private static function submission_filters() {
        return array(
            'search'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'status'      => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
            'form_id'     => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0,
            'assigned_to' => isset( $_GET['assigned_to'] ) ? sanitize_text_field( wp_unslash( $_GET['assigned_to'] ) ) : '',
            'priority'    => isset( $_GET['priority'] ) ? sanitize_key( $_GET['priority'] ) : '',
            'tag_id'      => isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0,
            'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
            'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
        ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    private static function download( $filename, $format, $headers, $rows ) {
        if ( 'csv' === $format ) {
            self::csv( $filename, $headers, $rows );
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

    private static function csv( $filename, $headers, $rows ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        $stream = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fwrite( $stream, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fputcsv( $stream, $headers );
        foreach ( $rows as $row ) {
            fputcsv( $stream, array_map( array( __CLASS__, 'safe_csv_cell' ), $row ) );
        }
        fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    private static function safe_csv_cell( $value ) {
        $value = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
        return preg_match( '/^[=+\-@\t\r]/u', $value ) ? "'" . $value : $value;
    }
}
