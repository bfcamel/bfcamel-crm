<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SubmissionService {
    public static function statuses() { return WorkflowService::statuses(); }
    public static function priorities() { return WorkflowService::priorities(); }

    public static function assignees() {
        $users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
        $result = array();
        foreach ( $users as $user ) {
            if ( user_can( $user, 'bfcamel_crm_view_submissions' ) && user_can( $user, 'bfcamel_crm_edit_submissions' ) ) $result[] = $user;
        }
        return $result;
    }

    public static function get( $submission_id, $for_update = false ) {
        global $wpdb;
        $submissions = Schema::table( 'submissions' ); $forms = Schema::table( 'forms' ); $contacts = Schema::table( 'contacts' ); $users = $wpdb->users;
        if ( $for_update ) {
            return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- FOR UPDATE must bypass caches inside the active transaction.
                'SELECT s.*, f.name AS form_name, c.display_name AS contact_name, u.display_name AS assignee_name FROM %i s LEFT JOIN %i f ON f.id=s.form_id LEFT JOIN %i c ON c.id=s.contact_id LEFT JOIN %i u ON u.ID=s.assigned_to WHERE s.id=%d LIMIT 1 FOR UPDATE', $submissions, $forms, $contacts, $users, absint( $submission_id )
            ) );
        }
        return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Submission details must reflect current workflow state.
            'SELECT s.*, f.name AS form_name, c.display_name AS contact_name, u.display_name AS assignee_name FROM %i s LEFT JOIN %i f ON f.id=s.form_id LEFT JOIN %i c ON c.id=s.contact_id LEFT JOIN %i u ON u.ID=s.assigned_to WHERE s.id=%d LIMIT 1', $submissions, $forms, $contacts, $users, absint( $submission_id )
        ) );
    }

    public static function query( $filters = array(), $page = 1, $per_page = 25 ) {
        global $wpdb;
        $submissions = Schema::table( 'submissions' ); $forms = Schema::table( 'forms' ); $contacts = Schema::table( 'contacts' );
        $emails = Schema::table( 'contact_emails' ); $phones = Schema::table( 'contact_phones' ); $tags = Schema::table( 'tags' ); $links = Schema::table( 'submission_tags' ); $users = $wpdb->users;
        $where = array( '1=1' ); $args = array();
        $search = isset( $filters['search'] ) ? trim( (string) $filters['search'] ) : '';
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $parts = array( 's.submission_uuid LIKE %s','f.name LIKE %s','c.display_name LIKE %s','s.payload_json LIKE %s',$wpdb->prepare('EXISTS (SELECT 1 FROM %i e WHERE e.contact_id=s.contact_id AND e.value LIKE %%s)',$emails),$wpdb->prepare('EXISTS (SELECT 1 FROM %i p WHERE p.contact_id=s.contact_id AND p.value LIKE %%s)',$phones) );
            $search_args = array( $like,$like,$like,$like,$like,$like );
            if ( ctype_digit( $search ) ) { array_unshift( $parts, 's.id=%d' ); array_unshift( $search_args, absint( $search ) ); }
            $where[] = '(' . implode( ' OR ', $parts ) . ')'; $args = array_merge( $args, $search_args );
        }
        $status = isset( $filters['status'] ) ? sanitize_key( $filters['status'] ) : '';
        if ( isset( self::statuses()[ $status ] ) ) { $where[] = 's.status=%s'; $args[] = $status; }
        $priority = isset( $filters['priority'] ) ? sanitize_key( $filters['priority'] ) : '';
        if ( isset( self::priorities()[ $priority ] ) ) { $where[] = 's.priority=%s'; $args[] = $priority; }
        $form_id = isset( $filters['form_id'] ) ? absint( $filters['form_id'] ) : 0;
        if ( $form_id ) { $where[] = 's.form_id=%d'; $args[] = $form_id; }
        if ( isset( $filters['assigned_to'] ) && '' !== (string) $filters['assigned_to'] ) { $where[] = 's.assigned_to=%d'; $args[] = absint( $filters['assigned_to'] ); }
        $tag_id = isset( $filters['tag_id'] ) ? absint( $filters['tag_id'] ) : 0;
        if ( $tag_id ) { $where[] = $wpdb->prepare( 'EXISTS (SELECT 1 FROM %i stf WHERE stf.submission_id=s.id AND stf.tag_id=%%d)', $links ); $args[] = $tag_id; }
        $date_from = isset( $filters['date_from'] ) ? self::date_value( $filters['date_from'] ) : '';
        if ( $date_from ) { $where[] = 's.submitted_at >= %s'; $args[] = $date_from . ' 00:00:00'; }
        $date_to = isset( $filters['date_to'] ) ? self::date_value( $filters['date_to'] ) : '';
        if ( $date_to ) { $where[] = 's.submitted_at <= %s'; $args[] = $date_to . ' 23:59:59'; }
        $where_sql = implode( ' AND ', $where );
        $base_from = $wpdb->prepare( 'FROM %i s LEFT JOIN %i f ON f.id=s.form_id LEFT JOIN %i c ON c.id=s.contact_id LEFT JOIN %i u ON u.ID=s.assigned_to', $submissions, $forms, $contacts, $users );
        $total = (int) $wpdb->get_var( self::prepare_sql( "SELECT COUNT(*) {$base_from} WHERE {$where_sql}", $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are prepared with %i; WHERE fragments are fixed and values are prepared by prepare_sql().
        $page = max( 1, absint( $page ) ); $per_page = min( 500, max( 1, absint( $per_page ) ) ); $offset = ( $page - 1 ) * $per_page;
        $tag_select = $wpdb->prepare( "(SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM %i st INNER JOIN %i t ON t.id=st.tag_id WHERE st.submission_id=s.id)", $links, $tags );
        $select_sql = "SELECT s.*, f.name AS form_name, c.display_name AS contact_name, u.display_name AS assignee_name, {$tag_select} AS tag_names {$base_from} WHERE {$where_sql} ORDER BY s.submitted_at DESC,s.id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( self::prepare_sql( $select_sql, array_merge( $args, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are prepared with %i; WHERE fragments are fixed and values are prepared by prepare_sql().
        return array( 'rows'=>$rows,'total'=>$total,'page'=>$page,'per_page'=>$per_page,'total_pages'=>max( 1, (int) ceil( $total / $per_page ) ) );
    }

    public static function bulk_apply( $submission_ids, $action, $value, $user_id ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $submission_ids ) ) ) );
        if ( ! $ids ) return new \WP_Error( 'bfcamel_crm_bulk_empty', __( 'Select at least one submission.', 'bfcamel-crm' ) );
        $action = sanitize_key( $action );
        if ( 'status' === $action && ! isset( self::statuses()[ sanitize_key( $value ) ] ) ) return new \WP_Error( 'bfcamel_crm_bulk_status', __( 'Choose status', 'bfcamel-crm' ) );
        if ( 'priority' === $action && ! isset( self::priorities()[ sanitize_key( $value ) ] ) ) return new \WP_Error( 'bfcamel_crm_bulk_priority', __( 'Choose priority', 'bfcamel-crm' ) );
        if ( 'assigned_to' === $action ) {
            $assignee = absint( $value );
            $allowed = array_map( 'absint', wp_list_pluck( self::assignees(), 'ID' ) );
            if ( $assignee && ! in_array( $assignee, $allowed, true ) ) return new \WP_Error( 'bfcamel_crm_bulk_assignee', __( 'Choose a bulk action.', 'bfcamel-crm' ) );
        }
        if ( in_array( $action, array( 'add_tags','remove_tags' ), true ) && '' === trim( (string) $value ) ) return new \WP_Error( 'bfcamel_crm_bulk_tags', __( 'Select tags', 'bfcamel-crm' ) );
        if ( ! in_array( $action, array( 'status','priority','assigned_to','add_tags','remove_tags' ), true ) ) return new \WP_Error( 'bfcamel_crm_bulk_action', __( 'Choose a bulk action.', 'bfcamel-crm' ) );
        if ( ! Schema::begin_transaction() ) return new \WP_Error( 'bfcamel_crm_bulk_transaction', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        foreach ( $ids as $id ) {
            $current = self::get( $id, true );
            if ( ! $current ) { Schema::rollback(); return new \WP_Error( 'bfcamel_crm_bulk_missing', __( 'One of the selected submissions no longer exists.', 'bfcamel-crm' ) ); }
            if ( 'status' === $action ) {
                $new = sanitize_key( $value );
                if ( (string) $current->status !== $new && ( false === $wpdb->update( Schema::table('submissions'), array('status'=>$new), array('id'=>$id), array('%s'), array('%d') ) || ! Schema::log('submission',$id,'status_changed','Submission status changed.',array('from'=>$current->status,'to'=>$new),$user_id) ) ) { Schema::rollback(); return new \WP_Error('bfcamel_crm_bulk_failed',__( 'Could not update selected submissions.', 'bfcamel-crm' )); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional workflow update requires current custom-table state.
            } elseif ( 'priority' === $action ) {
                $new = sanitize_key( $value );
                $old = $current->priority ?: WorkflowService::default_priority();
                if ( $old !== $new && ( false === $wpdb->update( Schema::table('submissions'), array('priority'=>$new), array('id'=>$id), array('%s'), array('%d') ) || ! Schema::log('submission',$id,'priority_changed','Submission priority changed.',array('from'=>$old,'to'=>$new),$user_id) ) ) { Schema::rollback(); return new \WP_Error('bfcamel_crm_bulk_failed',__( 'Could not update selected submissions.', 'bfcamel-crm' )); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional workflow update requires current custom-table state.
            } elseif ( 'assigned_to' === $action ) {
                $new = absint( $value );
                if ( absint( $current->assigned_to ) !== $new && ( false === $wpdb->update( Schema::table('submissions'), array('assigned_to'=>$new), array('id'=>$id), array('%d'), array('%d') ) || ! Schema::log('submission',$id,'assignee_changed','Submission assignee changed.',array('from'=>absint($current->assigned_to),'to'=>$new),$user_id) ) ) { Schema::rollback(); return new \WP_Error('bfcamel_crm_bulk_failed',__( 'Could not update selected submissions.', 'bfcamel-crm' )); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional workflow update requires current custom-table state.
            } elseif ( in_array( $action, array( 'add_tags','remove_tags' ), true ) ) {
                $old = TagService::names_for_submission( $id ); $incoming = array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n\r]+/u', (string) $value ) ) ) );
                if ( 'add_tags' === $action ) $new_names = array_values( array_unique( array_merge( $old, $incoming ) ) );
                else { $lower = array_map( 'strtolower', $incoming ); $new_names = array_values( array_filter( $old, static function($name) use($lower){ return ! in_array(strtolower($name),$lower,true); } ) ); }
                $tag_result = TagService::sync_submission( $id, implode( ', ', $new_names ) ); if ( is_wp_error( $tag_result ) ) { Schema::rollback(); return $tag_result; }
                $actual = wp_list_pluck( $tag_result, 'name' ); $a=$old; $b=$actual; sort($a); sort($b);
                if ( $a !== $b && ! Schema::log('submission',$id,'tags_changed','Submission tags changed.',array('from'=>$old,'to'=>$actual),$user_id) ) { Schema::rollback(); return new \WP_Error('bfcamel_crm_bulk_failed',__( 'Could not update selected submissions.', 'bfcamel-crm' )); }
            }
        }
        if ( ! Schema::commit() ) { Schema::rollback(); return new \WP_Error('bfcamel_crm_bulk_commit',__( 'Could not update selected submissions.', 'bfcamel-crm' )); }
        return count( $ids );
    }

    public static function all_for_export( $filters = array() ) {
        $rows=array(); $page=1; do { $result=self::query($filters,$page,500); $rows=array_merge($rows,(array)$result['rows']); $page++; } while($page <= $result['total_pages']); return $rows;
    }

    public static function dashboard_counts() {
        global $wpdb; $table=Schema::table('submissions'); $default=WorkflowService::default_status(); $review=WorkflowService::is_valid('status','needs_review')?'needs_review':''; $top=WorkflowService::highest_priority();
        $completed = isset( self::statuses()['completed'] ) ? 'completed' : '';
        $unassigned = $completed
            ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE assigned_to=0 AND status<>%s', $table, $completed ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard counters intentionally use current submission data.
            : (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE assigned_to=0', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard counters intentionally use current submission data.
        return array(
            'total'=>(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i',$table)), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard counters intentionally use current submission data.
            'new'=>(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE status=%s',$table,$default)), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard counters intentionally use current submission data.
            'needs_review'=>$review?(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE status=%s',$table,$review)):0, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard counters intentionally use current submission data.
            'urgent'=>(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE priority=%s',$table,$top)), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard counters intentionally use current submission data.
            'unassigned'=>$unassigned,
            'default_status'=>$default,
            'review_status'=>$review,
            'top_priority'=>$top,
        );
    }

    public static function workload() {
        global $wpdb; $table=Schema::table('submissions'); $users=$wpdb->users; $completed=isset(self::statuses()['completed'])?'completed':'';
        $sql = $completed
            ? $wpdb->prepare("SELECT s.assigned_to, COALESCE(u.display_name, '') AS assignee_name, COUNT(*) AS total FROM %i s LEFT JOIN %i u ON u.ID=s.assigned_to WHERE s.status<>%s GROUP BY s.assigned_to,u.display_name ORDER BY total DESC,assignee_name ASC",$table,$users,$completed)
            : $wpdb->prepare("SELECT s.assigned_to, COALESCE(u.display_name, '') AS assignee_name, COUNT(*) AS total FROM %i s LEFT JOIN %i u ON u.ID=s.assigned_to GROUP BY s.assigned_to,u.display_name ORDER BY total DESC,assignee_name ASC",$table,$users);
        return $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Workload data must remain current for assignment decisions.
    }

    public static function activity( $submission_id ) {
        global $wpdb; $activity=Schema::table('activity_log'); $users=$wpdb->users;
        return $wpdb->get_results($wpdb->prepare("SELECT a.*,u.display_name AS actor_name FROM %i a LEFT JOIN %i u ON u.ID=a.user_id WHERE a.entity_type='submission' AND a.entity_id=%d ORDER BY a.created_at DESC,a.id DESC",$activity,$users,absint($submission_id))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit history must reflect current custom-table state.
    }

    public static function status_label( $status ) { return WorkflowService::label( 'status', $status ); }
    public static function priority_label( $priority ) { return WorkflowService::label( 'priority', $priority ); }
    private static function date_value( $value ) { $value=sanitize_text_field((string)$value); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)?$value:''; }
    private static function prepare_sql( $sql, $args ) { global $wpdb; return $args ? $wpdb->prepare($sql,$args) : $sql; } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Callers provide only fixed SQL fragments and a complete placeholder argument list.
}
