<?php
namespace BfCamel\CRM\Export;

use BfCamel\CRM\Admin\ContactsPage;
use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Exporter {
    public static function handle() {
        if ( ! Schema::is_current() ) {
            wp_die( esc_html( Schema::readiness_message() ), esc_html__( 'CRM database update required', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        $type = Request::get_key( 'type' );
        $format = Request::get_key( 'format' );
        if ( ! in_array( $type, array( 'contacts', 'submissions' ), true ) || ! in_array( $format, array( 'csv', 'xlsx' ), true ) ) {
            wp_die( esc_html__( 'Invalid export request.', 'bfcamel-crm' ) );
        }
        $source_capability = 'contacts' === $type ? 'bfcamel_crm_manage_contacts' : 'bfcamel_crm_view_submissions';
        if ( ! current_user_can( $source_capability ) || ! current_user_can( 'bfcamel_crm_export_' . $type ) ) {
            wp_die( esc_html__( 'You do not have permission to export this CRM area.', 'bfcamel-crm' ) );
        }
        check_admin_referer( 'bfcamel_crm_export_' . $type . '_' . $format );

        $ids = Request::post_id_list( 'selected_ids' );
        $selected = Request::post_id( 'selected_only' );
        if ( $selected && ( 'contacts' !== $type || ! $ids ) ) wp_die( 'Выберите контакты для экспорта.' );
        self::prepare( $type, $format, $selected ? $ids : array() );
    }

    private static function prepare( $type, $format, $ids ) {
        $filters = 'contacts' === $type ? ContactsPage::filters() : self::submission_filters();
        if ( $ids ) $filters = array( 'ids' => $ids );
        $result = 'contacts' === $type ? ContactService::query( $filters, 1, 10 ) : SubmissionService::query( $filters, 1, 10 );
        if ( $result['total'] > 5000 ) wp_die( 'Выберите более узкий фильтр: за одну операцию можно экспортировать до 5000 записей.' );
        list( $headers, $rows, $keys ) = 'contacts' === $type ? self::contacts( $filters ) : self::submissions( $filters );
        if ( Request::post_id( 'download' ) ) {
            $columns = Request::post_array( 'columns' );
            $indexes = array();
            foreach ( $columns as $key ) {
                $i = array_search( $key, $keys, true );
                if ( false === $i ) wp_die( 'Состав полей изменился. Откройте экспорт заново.' );
                $indexes[$i] = $i;
            }
            if ( ! $indexes ) wp_die( 'Выберите хотя бы одно поле.' );
            $project = static function( $row ) use ( $indexes ) {
                $values = array();
                foreach ( $indexes as $index ) $values[] = $row[$index];
                return $values;
            };
            self::download( 'bfcamel-crm-' . $type . '-' . gmdate( 'Ymd-His' ) . '.' . $format, $format, $project( $headers ), array_map( $project, $rows ) );
        }
        $args = array( 'action' => 'bfcamel_crm_export', 'type' => $type, 'format' => $format );
        if ( ! $ids ) foreach ( $filters as $key => $value ) $args[ 'search' === $key ? 's' : $key ] = $value;
        $url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
        nocache_headers();
        ?><!doctype html><html lang="ru"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Экспорт CRM</title><style>body{margin:0;background:#f0f0f1;color:#1d2327;font:16px/1.5 system-ui,sans-serif}main{max-width:44rem;margin:2rem auto;padding:1.5rem;background:white;border:1px solid #c3c4c7}fieldset{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.5rem;padding:1rem;margin:1rem 0}fieldset br{display:none}button{padding:.6rem 1rem;cursor:pointer}</style><main><h1><?php echo 'contacts' === $type ? 'Экспорт контактов' : 'Экспорт заявок'; ?></h1>
        <p>Записей по выбранным фильтрам: <?php echo absint( count( $rows ) ); ?>. Формат: <?php echo esc_html( strtoupper( $format ) ); ?>.</p>
        <?php $back = 'contacts' === $type ? 'bfcamel-crm-contacts' : 'bfcamel-crm-submissions'; ?>
        <p><a href="<?php echo esc_url( add_query_arg( array_merge( array_diff_key( $args, array_flip( array( 'type', 'action', 'format' ) ) ), array( 'page' => $back ) ), admin_url( 'admin.php' ) ) ); ?>">Вернуться к фильтрам</a></p>
        <form action="<?php echo esc_url( $url ); ?>" method="post"><?php wp_nonce_field( 'bfcamel_crm_export_' . $type . '_' . $format ); ?>
        <?php if ( $ids ) : ?><input type="hidden" name="selected_only" value="1"><?php foreach ( $ids as $id ) : ?><input type="hidden" name="selected_ids[]" value="<?php echo absint( $id ); ?>"><?php endforeach; endif; ?>
        <fieldset><legend>Поля файла</legend><?php foreach ( $headers as $i => $label ) : ?><label><input type="checkbox" name="columns[]" value="<?php echo esc_attr( $keys[$i] ); ?>" <?php checked( 0 === $i ); ?>><?php echo esc_html( $label ); ?></label><br><?php endforeach; ?></fieldset>
        <button name="download" value="1" type="submit">Скачать файл</button></form></main></html><?php
        exit;
    }

    public static function download_selected_contacts( $ids, $format ) {
        if ( ! current_user_can( 'bfcamel_crm_export_contacts' ) || ! current_user_can( 'bfcamel_crm_manage_contacts' ) ) {
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
        self::prepare( 'contacts', $format, $ids );
    }

    private static function contacts( $override_filters = null ) {
        $filters = is_array( $override_filters ) ? $override_filters : ContactsPage::filters();
        $contacts = ContactService::all_for_export( $filters );
        $contact_ids = array();
        foreach ( $contacts as $contact ) {
            $contact_ids[] = absint( $contact->id );
        }
        $custom_by_contact = ContactService::get_custom_fields_for_contacts( $contact_ids );
        $custom_keys = array( ContactService::CITY_FIELD, ContactService::SOCIAL_PAGES_FIELD );

        foreach ( $contacts as $contact ) {
            $custom = $custom_by_contact[ $contact->id ] ?? array();
            foreach ( array_keys( $custom ) as $key ) {
                $key = sanitize_key( $key );
                if ( $key && ! in_array( $key, $custom_keys, true ) ) $custom_keys[] = $key;
            }
        }

        $standard_keys = array( ContactService::CITY_FIELD, ContactService::SOCIAL_PAGES_FIELD );
        $rest = array_values( array_diff( $custom_keys, $standard_keys ) );
        sort( $rest );
        $custom_keys = array_merge( $standard_keys, $rest );

        $headers = array( 'ID', __( 'Name', 'bfcamel-crm' ), __( 'Email', 'bfcamel-crm' ), __( 'Phone', 'bfcamel-crm' ), __( 'Organization', 'bfcamel-crm' ) );
        foreach ( $custom_keys as $key ) {
            if ( ContactService::CITY_FIELD === $key ) $headers[] = __( 'City', 'bfcamel-crm' );
            elseif ( ContactService::STAGE_FIELD === $key ) $headers[] = 'Рабочий статус';
            elseif ( ContactService::SOCIAL_PAGES_FIELD === $key ) $headers[] = __( 'Social pages', 'bfcamel-crm' );
            else {
                /* translators: %s: custom contact field key. */
                $headers[] = sprintf( __( 'Custom field: %s', 'bfcamel-crm' ), $key );
            }
        }
        $headers = array_merge( $headers, array( __( 'Status', 'bfcamel-crm' ), __( 'Tags', 'bfcamel-crm' ), __( 'Personal data consent', 'bfcamel-crm' ), __( 'Marketing consent', 'bfcamel-crm' ), __( 'Created', 'bfcamel-crm' ), __( 'Updated', 'bfcamel-crm' ) ) );

        $rows = array();
        $consent_statuses = ConsentService::statuses();
        foreach ( $contacts as $contact ) {
            $personal_data = sanitize_key( $contact->personal_data_consent ?: 'unknown' );
            $marketing = sanitize_key( $contact->marketing_consent ?: 'unknown' );
            $row = array( $contact->id, $contact->display_name, $contact->email_values, $contact->phone_values, $contact->organization );
            $custom = $custom_by_contact[ $contact->id ] ?? array();
            foreach ( $custom_keys as $key ) {
                $value = isset( $custom[ $key ] ) ? (string) $custom[ $key ] : '';
                if ( ContactService::SOCIAL_PAGES_FIELD === $key ) $value = implode( '; ', ContactService::split_social_pages( $value ) );
                $row[] = $value;
            }
            $row[] = ContactService::status_label( $contact->status );
            $row[] = $contact->tag_names;
            $row[] = $consent_statuses[ $personal_data ] ?? $personal_data;
            $row[] = $consent_statuses[ $marketing ] ?? $marketing;
            $row[] = $contact->created_at;
            $row[] = $contact->updated_at;
            $rows[] = $row;
        }
        $keys = array_merge( array( 'id','name','email','phone','organization' ), array_map( static function( $key ) { return 'custom:' . $key; }, $custom_keys ), array( 'status','tags','personal_data','marketing','created','updated' ) );
        return array( $headers, $rows, $keys );
    }

    private static function submissions( $filters ) {
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
        $keys = array_merge( array( 'id','uuid','form','contact','status','priority','owner','tags','received','source' ), array_map( static function( $key ) { return 'field:' . $key; }, $payload_keys ) );
        return array( $headers, $rows, $keys );
    }

    private static function submission_filters() {
        return array(
            'search'      => Request::get_text( 's' ),
            'view'        => Request::get_key( 'view' ),
            'status'      => Request::get_key( 'status' ),
            'form_id'     => Request::get_id( 'form_id' ),
            'assigned_to' => Request::get_text( 'assigned_to' ),
            'priority'    => Request::get_key( 'priority' ),
            'tag_id'      => Request::get_id( 'tag_id' ),
            'date_from'   => Request::get_text( 'date_from' ),
            'date_to'     => Request::get_text( 'date_to' ),
        );
    }

    private static function download( $filename, $format, $headers, $rows ) {
        if ( ! \BfCamel\CRM\Privacy\Register::log( 'export', array( 'file' => $filename, 'columns' => $headers, 'count' => count( $rows ), 'purpose' => 'Рабочая выгрузка CRM' ) ) ) wp_die( 'Не удалось зарегистрировать выгрузку.' );
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

