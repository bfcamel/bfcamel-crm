<?php
namespace BfCamel\CRM\Notifications;

use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Notifications {
    const OPTION = 'bfcamel_crm_notifications';
    const START_OPTION = 'bfcamel_crm_notification_start_id';
    const CRON = 'bfcamel_crm_notification_tick';

    public static function register() {
        add_action( 'bfcamel_crm_submission_created', array( __CLASS__, 'created' ) );
        add_action( 'bfcamel_crm_submission_updated', array( __CLASS__, 'updated' ), 10, 2 );
        add_action( self::CRON, array( __CLASS__, 'tick' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
    }

    public static function schedules( $schedules ) {
        $schedules['bfcamel_crm_minute'] = array( 'interval' => 60, 'display' => __( 'Every minute', 'bfcamel-crm' ) );
        return $schedules;
    }

    public static function initialize() {
        if ( ! Schema::is_current() ) return;
        global $wpdb;
        if ( false === get_option( self::START_OPTION, false ) ) {
            $latest = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(id),0) FROM %i', Schema::table( 'submissions' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Establish an activation boundary without notifying about historical submissions.
            add_option( self::START_OPTION, $latest, '', false );
        }
        if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_event( time() + 60, 'bfcamel_crm_minute', self::CRON );
    }

    public static function settings() {
        $value = get_option( self::OPTION, array() );
        return wp_parse_args( is_array( $value ) ? $value : array(), array(
            'email_enabled' => false, 'recipients' => array(), 'normal_digest' => false,
            'telegram_enabled' => false, 'telegram_chat' => '', 'telegram_token' => '',
            'reminder_minutes' => 10, 'closed_statuses' => array( 'completed' ),
        ) );
    }

    public static function closed_statuses() {
        return array_values( array_intersect( array_keys( SubmissionService::statuses() ), (array) self::settings()['closed_statuses'] ) );
    }

    public static function is_closed( $row ) { return in_array( $row->status, self::closed_statuses(), true ); }

    public static function level( $priority ) {
        $definitions = WorkflowService::definitions( 'priority', true );
        if ( count( $definitions ) < 2 ) return 'normal';
        $weights = array_column( $definitions, 'weight', 'slug' );
        $weight = isset( $weights[ $priority ] ) ? (int) $weights[ $priority ] : min( array_map( 'intval', $weights ) );
        $min = min( array_map( 'intval', $weights ) ); $max = max( array_map( 'intval', $weights ) );
        if ( $weight > $min && $weight === $max ) return 'urgent';
        return $weight > $min ? 'high' : 'normal';
    }

    public static function urgent_priorities() {
        $slugs = array();
        foreach ( SubmissionService::priorities() as $slug => $label ) if ( 'urgent' === self::level( $slug ) ) $slugs[] = $slug;
        return $slugs;
    }

    /** Fixed internal SQL fragment shared by inbox and notifications. */
    public static function open_sql() {
        global $wpdb;
        $closed = self::closed_statuses();
        return $closed ? $wpdb->prepare( 's.status NOT IN (' . implode( ',', array_fill( 0, count( $closed ), '%s' ) ) . ')', $closed ) : '1=1'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed alias and generated placeholders; every status is prepared.
    }

    public static function created( $submission_id ) {
        if ( ! Schema::is_current() || false === get_option( self::START_OPTION, false ) || absint( $submission_id ) <= (int) get_option( self::START_OPTION ) ) return;
        $row = SubmissionService::get( $submission_id );
        if ( $row ) self::record( $row, 'created', 'created:' . absint( $submission_id ) );
    }

    public static function updated( $submission_id, $old_priority ) {
        if ( ! Schema::is_current() ) return;
        $row = SubmissionService::get( $submission_id );
        $rank = array( 'normal' => 0, 'high' => 1, 'urgent' => 2 );
        if ( $row && ! self::is_closed( $row ) && $rank[ self::level( $row->priority ) ] > $rank[ self::level( $old_priority ) ] ) {
            global $wpdb;
            $change_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(id),0) FROM %i WHERE entity_type='submission' AND entity_id=%d AND event_type='priority_changed'", Schema::table( 'activity_log' ), $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Replayed update hooks resolve to the same audited priority change.
            self::record( $row, 'raised', 'raised:' . absint( $submission_id ) . ':' . $change_id . ':' . $row->priority );
        }
    }

    private static function record( $row, $kind, $key ) {
        global $wpdb;
        $table = Schema::table( 'notifications' );
        // INSERT IGNORE is intentional: submission-created hooks and recovery scans may overlap.
        $inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (submission_id,event_key,kind,level,created_at) VALUES (%d,%s,%s,%s,%s)', $table, $row->id, $key, $kind, self::level( $row->priority ), gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Unique event key prevents duplicate notifications across workers.
        if ( 1 !== $inserted ) return;
        $id = (int) $wpdb->insert_id;
        self::queue( $id, $row, self::level( $row->priority ) );
    }

    private static function queue( $event_id, $row, $level ) {
        $settings = self::settings();
        $ok = true;
        $available = 'normal' === $level ? ( (int) floor( time() / HOUR_IN_SECONDS ) + 1 ) * HOUR_IN_SECONDS : time();
        if ( ! empty( $settings['email_enabled'] ) && ( 'normal' !== $level || ! empty( $settings['normal_digest'] ) ) ) {
            $ids = array_map( 'absint', (array) $settings['recipients'] );
            if ( $row->assigned_to ) $ids[] = absint( $row->assigned_to );
            foreach ( array_unique( $ids ) as $user_id ) if ( user_can( $user_id, 'bfcamel_crm_view_submissions' ) && false === self::receipt( $event_id, $user_id, 'email', 'pending', $available ) ) $ok = false;
        }
        if ( 'urgent' === $level && ! empty( $settings['telegram_enabled'] ) && $settings['telegram_chat'] && $settings['telegram_token'] && false === self::receipt( $event_id, 0, 'telegram', 'pending', time() ) ) $ok = false;
        if ( $ok ) {
            global $wpdb;
            $wpdb->update( Schema::table( 'notifications' ), array( 'queued' => 1 ), array( 'id' => $event_id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Only mark fully enqueued events; failed receipt writes are retried.
        }
    }

    private static function receipt( $event_id, $user_id, $channel, $state, $available = 0 ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (event_id,user_id,channel,state,attempts,available_at,updated_at) VALUES (%d,%d,%s,%s,0,%s,%s)', Schema::table( 'notification_receipts' ), $event_id, $user_id, $channel, $state, gmdate( 'Y-m-d H:i:s', $available ?: time() ), gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Composite primary key makes each recipient/channel enqueue idempotent.
    }

    public static function read( $submission_id, $user_id ) {
        if ( ! user_can( $user_id, 'bfcamel_crm_view_submissions' ) ) return false;
        global $wpdb;
        return false !== $wpdb->query( $wpdb->prepare( "INSERT INTO %i (event_id,user_id,channel,state,attempts,available_at,updated_at) SELECT id,%d,'in_app','read',0,%s,%s FROM %i WHERE submission_id=%d ON DUPLICATE KEY UPDATE state='read',updated_at=VALUES(updated_at)", Schema::table( 'notification_receipts' ), $user_id, gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s' ), Schema::table( 'notifications' ), $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Per-user read receipts never change shared submission workflow state.
    }

    public static function feed( $user_id ) {
        if ( ! Schema::is_current() || ! user_can( $user_id, 'bfcamel_crm_view_submissions' ) ) return array( 'unread' => 0, 'urgent' => 0, 'latest' => 0, 'items' => array() );
        global $wpdb;
        $open = self::open_sql();
        $from = $wpdb->prepare( "FROM %i n INNER JOIN %i s ON s.id=n.submission_id LEFT JOIN %i r ON r.event_id=n.id AND r.user_id=%d AND r.channel='in_app'", Schema::table( 'notifications' ), Schema::table( 'submissions' ), Schema::table( 'notification_receipts' ), $user_id );
        $unread = (int) $wpdb->get_var( "SELECT COUNT(*) {$from} WHERE {$open} AND COALESCE(r.state,'')<>'read'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal fragments are fully prepared above.
        $rows = $wpdb->get_results( "SELECT n.*,s.priority,s.assigned_to,COALESCE(r.state,'') AS receipt_state {$from} WHERE {$open} ORDER BY n.id DESC LIMIT 30" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal fragments are fully prepared above; no payload or contact data enters notification transport.
        $urgent_slugs = self::urgent_priorities();
        $urgent = 0;
        if ( $urgent_slugs ) {
            $condition = $wpdb->prepare( 's.priority IN (' . implode( ',', array_fill( 0, count( $urgent_slugs ), '%s' ) ) . ')', $urgent_slugs ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Generated placeholders only.
            $urgent = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT s.id) {$from} WHERE {$open} AND s.assigned_to=0 AND {$condition}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared internal fragments.
        }
        $latest = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(id),0) FROM %i', Schema::table( 'notifications' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Monotonic cursor prevents replaying toasts after reload.
        $items = array();
        foreach ( (array) $rows as $row ) $items[] = array(
            'id' => (int) $row->id, 'submissionId' => (int) $row->submission_id,
            'level' => self::level( $row->priority ), 'read' => 'read' === $row->receipt_state,
            'unclaimed' => ! $row->assigned_to, 'url' => self::url( $row->submission_id ),
            'title' => self::event_title( $row->submission_id, $row->kind ), 'createdAt' => $row->created_at,
        );
        return array( 'unread' => $unread, 'urgent' => $urgent, 'latest' => $latest, 'items' => $items );
    }

    public static function url( $submission_id ) { return admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . absint( $submission_id ) ); }

    public static function event_title( $submission_id, $kind = 'created' ) {
        /* translators: %d: submission ID. */
        $format = 'reminder' === $kind ? __( 'Still waiting for an owner: submission #%d', 'bfcamel-crm' ) : ( 'raised' === $kind ? __( 'Priority raised: submission #%d', 'bfcamel-crm' ) : __( 'New submission #%d', 'bfcamel-crm' ) );
        return sprintf( $format, absint( $submission_id ) );
    }

    /** Recover missed post-commit hooks, queue reminders and deliver outside the public request. */
    public static function tick() {
        if ( ! Schema::is_current() || false === get_option( self::START_OPTION, false ) ) return;
        global $wpdb;
        $lock = 'bfcamel_crm_notify_' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->prefix ), 0, 40 );
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) return; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One delivery worker per site.
        try {
            $rows = $wpdb->get_col( $wpdb->prepare( "SELECT s.id FROM %i s LEFT JOIN %i n ON n.event_key=CONCAT('created:',s.id) WHERE s.id>%d AND n.id IS NULL ORDER BY s.id ASC LIMIT 50", Schema::table( 'submissions' ), Schema::table( 'notifications' ), get_option( self::START_OPTION ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded recovery never scans or notifies pre-activation history.
            foreach ( (array) $rows as $id ) self::created( $id );
            $unqueued = $wpdb->get_results( $wpdb->prepare( 'SELECT id,submission_id,level FROM %i WHERE queued=0 ORDER BY id ASC LIMIT 50', Schema::table( 'notifications' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Recover interrupted delivery enqueue operations.
            foreach ( (array) $unqueued as $event ) {
                $row = SubmissionService::get( $event->submission_id );
                if ( $row ) self::queue( $event->id, $row, $event->level );
            }
            self::reminders();
            self::deliver();
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release after delivery state is persisted.
        }
    }

    private static function reminders() {
        global $wpdb;
        $minutes = (int) self::settings()['reminder_minutes'];
        if ( $minutes < 1 ) return;
        $open = self::open_sql();
        $events_sql = $wpdb->prepare( '%i', Schema::table( 'notifications' ) );
        $sql = $wpdb->prepare( "SELECT n.id,n.submission_id FROM %i n INNER JOIN %i s ON s.id=n.submission_id LEFT JOIN %i reminder ON reminder.event_key=CONCAT('reminder:',n.id) WHERE n.kind IN ('created','raised') AND n.id=(SELECT MAX(latest.id) FROM {$events_sql} latest WHERE latest.submission_id=n.submission_id AND latest.kind IN ('created','raised')) AND n.level='urgent' AND s.assigned_to=0 AND n.created_at<=%s AND reminder.id IS NULL AND {$open} ORDER BY n.id ASC LIMIT 20", Schema::table( 'notifications' ), Schema::table( 'submissions' ), Schema::table( 'notifications' ), gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Both SQL fragments contain only allowlisted identifiers and prepared status placeholders.
        $rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared internal open-status fragment and parameters.
        foreach ( (array) $rows as $event ) {
            $row = SubmissionService::get( $event->submission_id );
            if ( $row && 'urgent' === self::level( $row->priority ) ) self::record( $row, 'reminder', 'reminder:' . $event->id );
        }
    }

    private static function deliver() {
        global $wpdb;
        $started = microtime( true );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT r.*,n.submission_id,n.kind,n.level FROM %i r INNER JOIN %i n ON n.id=r.event_id WHERE r.channel IN ('email','telegram') AND r.state IN ('pending','sending') AND r.available_at<=%s ORDER BY CASE n.level WHEN 'urgent' THEN 2 WHEN 'high' THEN 1 ELSE 0 END DESC,r.event_id ASC LIMIT 20", Schema::table( 'notification_receipts' ), Schema::table( 'notifications' ), gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded durable delivery queue with retry leases.
        $done = array();
        foreach ( (array) $rows as $receipt ) {
            $key = $receipt->event_id . ':' . $receipt->user_id . ':' . $receipt->channel;
            if ( isset( $done[ $key ] ) || microtime( true ) - $started > 20 ) continue;
            $row = SubmissionService::get( $receipt->submission_id );
            $settings = self::settings();
            $eligible = $row && ! self::is_closed( $row );
            if ( 'reminder' === $receipt->kind && $row && $row->assigned_to ) $eligible = false;
            if ( 'email' === $receipt->channel ) $eligible = $eligible && ! empty( $settings['email_enabled'] ) && user_can( $receipt->user_id, 'bfcamel_crm_view_submissions' ) && ( in_array( (int) $receipt->user_id, array_map( 'absint', (array) $settings['recipients'] ), true ) || (int) $row->assigned_to === (int) $receipt->user_id );
            else $eligible = $eligible && ! empty( $settings['telegram_enabled'] ) && 'urgent' === self::level( $row->priority );
            if ( ! $eligible ) { self::delivery_state( $receipt, 'cancelled' ); continue; }
            $batch = array( $receipt );
            if ( 'email' === $receipt->channel && 'normal' === $receipt->level ) {
                if ( empty( $settings['normal_digest'] ) ) { self::delivery_state( $receipt, 'cancelled' ); continue; }
                foreach ( $rows as $other ) {
                    if ( $other !== $receipt && 'email' === $other->channel && (int) $other->user_id === (int) $receipt->user_id && 'normal' === $other->level ) {
                        $other_row = SubmissionService::get( $other->submission_id );
                        if ( $other_row && ! self::is_closed( $other_row ) ) $batch[] = $other;
                    }
                }
            }
            $leased = array();
            foreach ( $batch as $item ) {
                if ( self::delivery_state( $item, 'sending', time() + 300 ) ) {
                    $leased[] = $item; $done[ $item->event_id . ':' . $item->user_id . ':' . $item->channel ] = true;
                }
            }
            if ( ! $leased ) continue;
            $sent = 'email' === $receipt->channel ? self::email( $leased ) : self::telegram( $receipt );
            foreach ( $leased as $item ) self::delivery_state( $item, $sent ? 'sent' : ( (int) $item->attempts >= 4 ? 'failed' : 'pending' ), time() + min( HOUR_IN_SECONDS, 60 * ( 2 ** (int) $item->attempts ) ) );
        }
    }

    private static function delivery_state( $receipt, $state, $available = 0 ) {
        global $wpdb;
        $data = array( 'state' => $state, 'available_at' => gmdate( 'Y-m-d H:i:s', $available ?: time() ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) );
        $formats = array( '%s', '%s', '%s' );
        if ( 'sending' === $state ) { $data['attempts'] = (int) $receipt->attempts + 1; $formats[] = '%d'; }
        return false !== $wpdb->update( Schema::table( 'notification_receipts' ), $data, array( 'event_id' => $receipt->event_id, 'user_id' => $receipt->user_id, 'channel' => $receipt->channel ), $formats, array( '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Durable per-channel delivery status; no credentials or payloads stored in the queue.
    }

    private static function email( $batch ) {
        $user = get_userdata( $batch[0]->user_id );
        if ( ! $user || ! is_email( $user->user_email ) ) return false;
        $lines = array();
        foreach ( $batch as $event ) $lines[] = self::event_title( $event->submission_id, $event->kind ) . "\n" . self::url( $event->submission_id );
        $title = count( $batch ) > 1 || 'normal' === $batch[0]->level ? __( 'CRM submission digest', 'bfcamel-crm' ) : ( 'urgent' === $batch[0]->level ? __( 'Urgent CRM submission', 'bfcamel-crm' ) : __( 'High-priority CRM submission', 'bfcamel-crm' ) );
        return wp_mail( $user->user_email, $title, implode( "\n\n", $lines ) );
    }

    private static function telegram( $event ) {
        $settings = self::settings();
        if ( ! preg_match( '/^\d+:[A-Za-z0-9_-]+$/', $settings['telegram_token'] ) || ! $settings['telegram_chat'] ) return false;
        $response = wp_remote_post( 'https://api.telegram.org/bot' . $settings['telegram_token'] . '/sendMessage', array( 'timeout' => 8, 'redirection' => 0, 'body' => array( 'chat_id' => $settings['telegram_chat'], 'text' => __( 'Urgent CRM submission', 'bfcamel-crm' ) . "\n" . self::event_title( $event->submission_id, $event->kind ) . "\n" . self::url( $event->submission_id ), 'disable_web_page_preview' => true ) ) );
        return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) && ! empty( json_decode( wp_remote_retrieve_body( $response ), true )['ok'] );
    }
}
