<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SubmissionService {
    public static function statuses() {
        return array(
            'new'          => __( 'New', 'bfcamel-crm' ),
            'in_progress'  => __( 'In progress', 'bfcamel-crm' ),
            'waiting'      => __( 'Waiting', 'bfcamel-crm' ),
            'completed'    => __( 'Completed', 'bfcamel-crm' ),
            'needs_review' => __( 'Needs review', 'bfcamel-crm' ),
        );
    }

    public static function priorities() {
        return array(
            'normal' => __( 'Normal', 'bfcamel-crm' ),
            'high'   => __( 'High', 'bfcamel-crm' ),
            'urgent' => __( 'Urgent', 'bfcamel-crm' ),
        );
    }

    public static function assignees() {
        $users = get_users(
            array(
                'orderby' => 'display_name',
                'order'   => 'ASC',
            )
        );
        $result = array();
        foreach ( $users as $user ) {
            if ( user_can( $user, 'bfcamel_crm_edit_submissions' ) || user_can( $user, 'edit_posts' ) ) {
                $result[] = $user;
            }
        }
        return $result;
    }

    public static function get( $submission_id ) {
        global $wpdb;
        $submissions = Schema::table( 'submissions' );
        $forms = Schema::table( 'forms' );
        $contacts = Schema::table( 'contacts' );
        $users = $wpdb->users;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, f.name AS form_name, c.display_name AS contact_name, u.display_name AS assignee_name
                 FROM {$submissions} s
                 LEFT JOIN {$forms} f ON f.id=s.form_id
                 LEFT JOIN {$contacts} c ON c.id=s.contact_id
                 LEFT JOIN {$users} u ON u.ID=s.assigned_to
                 WHERE s.id=%d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $submission_id )
            )
        );
    }

    public static function query( $filters = array(), $page = 1, $per_page = 25 ) {
        global $wpdb;
        $submissions = Schema::table( 'submissions' );
        $forms = Schema::table( 'forms' );
        $contacts = Schema::table( 'contacts' );
        $emails = Schema::table( 'contact_emails' );
        $phones = Schema::table( 'contact_phones' );
        $tags = Schema::table( 'tags' );
        $links = Schema::table( 'submission_tags' );
        $users = $wpdb->users;

        $where = array( '1=1' );
        $args = array();

        $search = isset( $filters['search'] ) ? trim( (string) $filters['search'] ) : '';
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $parts = array(
                's.submission_uuid LIKE %s',
                'f.name LIKE %s',
                'c.display_name LIKE %s',
                's.payload_json LIKE %s',
                "EXISTS (SELECT 1 FROM {$emails} e WHERE e.contact_id=s.contact_id AND e.value LIKE %s)",
                "EXISTS (SELECT 1 FROM {$phones} p WHERE p.contact_id=s.contact_id AND p.value LIKE %s)",
            );
            $search_args = array( $like, $like, $like, $like, $like, $like );
            if ( ctype_digit( $search ) ) {
                array_unshift( $parts, 's.id=%d' );
                array_unshift( $search_args, absint( $search ) );
            }
            $where[] = '(' . implode( ' OR ', $parts ) . ')';
            $args = array_merge( $args, $search_args );
        }

        $statuses = self::statuses();
        $status = isset( $filters['status'] ) ? sanitize_key( $filters['status'] ) : '';
        if ( isset( $statuses[ $status ] ) ) {
            $where[] = 's.status=%s';
            $args[] = $status;
        }

        $priorities = self::priorities();
        $priority = isset( $filters['priority'] ) ? sanitize_key( $filters['priority'] ) : '';
        if ( isset( $priorities[ $priority ] ) ) {
            $where[] = 's.priority=%s';
            $args[] = $priority;
        }

        $form_id = isset( $filters['form_id'] ) ? absint( $filters['form_id'] ) : 0;
        if ( $form_id ) {
            $where[] = 's.form_id=%d';
            $args[] = $form_id;
        }

        if ( isset( $filters['assigned_to'] ) && '' !== (string) $filters['assigned_to'] ) {
            $assigned_to = absint( $filters['assigned_to'] );
            $where[] = 's.assigned_to=%d';
            $args[] = $assigned_to;
        }

        $tag_id = isset( $filters['tag_id'] ) ? absint( $filters['tag_id'] ) : 0;
        if ( $tag_id ) {
            $where[] = "EXISTS (SELECT 1 FROM {$links} stf WHERE stf.submission_id=s.id AND stf.tag_id=%d)";
            $args[] = $tag_id;
        }

        $date_from = isset( $filters['date_from'] ) ? self::date_value( $filters['date_from'] ) : '';
        if ( $date_from ) {
            $where[] = 's.submitted_at >= %s';
            $args[] = $date_from . ' 00:00:00';
        }
        $date_to = isset( $filters['date_to'] ) ? self::date_value( $filters['date_to'] ) : '';
        if ( $date_to ) {
            $where[] = 's.submitted_at <= %s';
            $args[] = $date_to . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );
        $base_from = "FROM {$submissions} s
            LEFT JOIN {$forms} f ON f.id=s.form_id
            LEFT JOIN {$contacts} c ON c.id=s.contact_id
            LEFT JOIN {$users} u ON u.ID=s.assigned_to";

        $count_sql = "SELECT COUNT(*) {$base_from} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var( self::prepare_sql( $count_sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $page = max( 1, absint( $page ) );
        $per_page = min( 100, max( 10, absint( $per_page ) ) );
        $offset = ( $page - 1 ) * $per_page;

        $select_sql = "SELECT s.*, f.name AS form_name, c.display_name AS contact_name, u.display_name AS assignee_name,
            (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM {$links} st INNER JOIN {$tags} t ON t.id=st.tag_id WHERE st.submission_id=s.id) AS tag_names
            {$base_from}
            WHERE {$where_sql}
            ORDER BY s.submitted_at DESC,s.id DESC
            LIMIT %d OFFSET %d";
        $select_args = array_merge( $args, array( $per_page, $offset ) );
        $rows = $wpdb->get_results( self::prepare_sql( $select_sql, $select_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages'=> max( 1, (int) ceil( $total / $per_page ) ),
        );
    }

    public static function activity( $submission_id ) {
        global $wpdb;
        $activity = Schema::table( 'activity_log' );
        $users = $wpdb->users;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name AS actor_name FROM {$activity} a LEFT JOIN {$users} u ON u.ID=a.user_id WHERE a.entity_type='submission' AND a.entity_id=%d ORDER BY a.created_at DESC,a.id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $submission_id )
            )
        );
    }

    public static function status_label( $status ) {
        $items = self::statuses();
        return isset( $items[ $status ] ) ? $items[ $status ] : (string) $status;
    }

    public static function priority_label( $priority ) {
        $items = self::priorities();
        return isset( $items[ $priority ] ) ? $items[ $priority ] : (string) $priority;
    }

    private static function date_value( $value ) {
        $value = sanitize_text_field( (string) $value );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
    }

    private static function prepare_sql( $sql, $args ) {
        global $wpdb;
        return $args ? $wpdb->prepare( $sql, $args ) : $sql;
    }
}
