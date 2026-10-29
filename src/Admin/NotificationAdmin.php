<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Notifications\Notifications;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NotificationAdmin {
    public static function register() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_bar_menu', array( __CLASS__, 'toolbar' ), 90 );
        add_action( 'admin_notices', array( __CLASS__, 'urgent_notice' ) );
        add_action( 'admin_footer', array( __CLASS__, 'center' ) );
        add_action( 'wp_ajax_bfcamel_crm_notification_feed', array( __CLASS__, 'ajax_feed' ) );
        add_action( 'wp_ajax_bfcamel_crm_notification_read', array( __CLASS__, 'ajax_read' ) );
        add_action( 'wp_ajax_bfcamel_crm_submission_detail', array( __CLASS__, 'ajax_detail' ) );
        add_action( 'wp_ajax_bfcamel_crm_complete_submission', array( __CLASS__, 'ajax_complete' ) );
        add_action( 'admin_post_bfcamel_crm_complete_submission', array( __CLASS__, 'post_complete' ) );
        add_action( 'wp_ajax_bfcamel_crm_claim_submission', array( __CLASS__, 'ajax_claim' ) );
        add_action( 'admin_post_bfcamel_crm_claim_submission', array( __CLASS__, 'post_claim' ) );
        add_action( 'admin_post_bfcamel_crm_save_notifications', array( __CLASS__, 'save' ) );
    }

    public static function can_view() { return Schema::is_current() && current_user_can( 'bfcamel_crm_view_submissions' ); }

    public static function assets() {
        if ( ! Schema::is_current() || ( ! current_user_can( 'bfcamel_crm_access' ) && ! current_user_can( 'bfcamel_crm_view_submissions' ) ) ) return;
        wp_enqueue_style( 'bfcamel-crm-notifications', BFCAMEL_CRM_URL . 'assets/notifications.css', array(), BFCAMEL_CRM_VERSION . '.' . filemtime( BFCAMEL_CRM_DIR . 'assets/notifications.css' ) );
        wp_enqueue_script( 'bfcamel-crm-inbox', BFCAMEL_CRM_URL . 'assets/inbox.js', array(), BFCAMEL_CRM_VERSION . '.' . filemtime( BFCAMEL_CRM_DIR . 'assets/inbox.js' ), true );
        wp_localize_script( 'bfcamel-crm-inbox', 'BfCamelCRMInbox', array(
            'enabled' => self::can_view(),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'bfcamel_crm_inbox' ),
            'storageKey' => 'bfcamel-crm:' . md5( home_url( '/' ) ) . ':' . get_current_user_id(),
            'feed' => Notifications::feed( get_current_user_id() ),
            'strings' => array(
                'close' => __( 'Close', 'bfcamel-crm' ), 'loading' => __( 'Loading…', 'bfcamel-crm' ),
                'notifications' => __( 'Notifications', 'bfcamel-crm' ), 'empty' => __( 'No new notifications.', 'bfcamel-crm' ),
                'urgent' => __( 'Urgent CRM submission', 'bfcamel-crm' ), 'high' => __( 'High-priority CRM submission', 'bfcamel-crm' ),
                'normal' => __( 'New CRM submissions', 'bfcamel-crm' ), 'open' => __( 'Open submission', 'bfcamel-crm' ),
                'error' => __( 'Could not load the card. Open the full page or try again.', 'bfcamel-crm' ),
                'claimError' => __( 'Could not take the submission. Refresh to check its current owner.', 'bfcamel-crm' ),
                'claimed' => __( 'You are now responsible for this submission.', 'bfcamel-crm' ),
                'browserDenied' => __( 'Browser notifications are unavailable or blocked. CRM notifications remain available.', 'bfcamel-crm' ),
                'browserEnabled' => __( 'Browser notifications enabled on this device.', 'bfcamel-crm' ),
                'confirm' => __( 'Are you sure you want to continue?', 'bfcamel-crm' ),
                'unsaved' => __( 'You have unsaved changes.', 'bfcamel-crm' ),
            ),
        ) );
    }

    public static function toolbar( $bar ) {
        if ( ! self::can_view() ) return;
        $feed = Notifications::feed( get_current_user_id() );
        $bar->add_node( array( 'id' => 'bfcamel-crm-notifications', 'title' => '<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span><span class="bfcamel-notification-label">' . esc_html__( 'CRM notifications', 'bfcamel-crm' ) . '</span> <span class="bfcamel-notification-count">' . absint( $feed['unread'] ) . '</span>', 'href' => admin_url( 'admin.php?page=bfcamel-crm-submissions' ), 'meta' => array( 'title' => __( 'Notifications', 'bfcamel-crm' ) ) ) );
    }

    public static function urgent_notice() {
        if ( ! self::can_view() ) return;
        $feed = Notifications::feed( get_current_user_id() );
        ?><div class="notice notice-error bfcamel-urgent-notice" <?php if ( ! $feed['urgent'] ) echo 'hidden'; ?>><p><strong><?php esc_html_e( 'Urgent submissions waiting for an owner:', 'bfcamel-crm' ); ?> <span data-bfcamel-urgent-count><?php echo absint( $feed['urgent'] ); ?></span></strong> <a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&view=attention' ) ); ?>"><?php esc_html_e( 'Open submissions', 'bfcamel-crm' ); ?></a></p></div><?php
    }

    public static function center() {
        if ( ! self::can_view() ) return;
        ?><section id="bfcamel-notification-center" class="bfcamel-notification-center" aria-label="<?php esc_attr_e( 'Notifications', 'bfcamel-crm' ); ?>" hidden>
            <div class="bfcamel-notification-heading"><h2><?php esc_html_e( 'Notifications', 'bfcamel-crm' ); ?></h2><button type="button" class="button" data-bfcamel-notification-close><?php esc_html_e( 'Close', 'bfcamel-crm' ); ?></button></div>
            <div data-bfcamel-notification-items></div>
            <details><summary><?php esc_html_e( 'On this device', 'bfcamel-crm' ); ?></summary>
                <p><label><input type="checkbox" data-bfcamel-sound> <?php esc_html_e( 'Sound for urgent submissions', 'bfcamel-crm' ); ?></label></p>
                <p><button type="button" class="button" data-bfcamel-browser><?php esc_html_e( 'Enable browser notifications', 'bfcamel-crm' ); ?></button></p>
                <p class="description" data-bfcamel-browser-status></p>
            </details>
        </section><div id="bfcamel-notification-toast" class="bfcamel-notification-toast" aria-live="polite" hidden></div><?php
    }

    private static function ajax_guard() {
        if ( ! self::can_view() ) wp_send_json_error( array( 'message' => __( 'You do not have permission to access this page.', 'bfcamel-crm' ) ), 403 );
        check_ajax_referer( 'bfcamel_crm_inbox', 'nonce' );
        nocache_headers();
    }

    public static function ajax_feed() { self::ajax_guard(); wp_send_json_success( Notifications::feed( get_current_user_id() ) ); }
    public static function ajax_read() {
        self::ajax_guard();
        if ( ! Notifications::read( Request::post_id( 'submission_id' ), get_current_user_id() ) ) wp_send_json_error( array(), 500 );
        wp_send_json_success( Notifications::feed( get_current_user_id() ) );
    }
    public static function ajax_detail() {
        self::ajax_guard();
        ob_start(); SubmissionDetailPage::render( Request::get_id( 'id' ) ); $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }
    public static function ajax_claim() {
        self::ajax_guard();
        $result = SubmissionService::claim( Request::post_id( 'submission_id' ), get_current_user_id() );
        if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
        Notifications::read( Request::post_id( 'submission_id' ), get_current_user_id() );
        wp_send_json_success( self::changed_feed( Request::post_id( 'submission_id' ) ) );
    }
    public static function post_claim() {
        $id = Request::post_id( 'submission_id' );
        check_admin_referer( 'bfcamel_crm_claim_' . $id );
        $result = SubmissionService::claim( $id, get_current_user_id() );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'updated' => 1, 'bfcamel_return' => rawurlencode( self::return_url() ) ), Notifications::url( $id ) ) ); exit;
    }

    private static function changed_feed( $id ) {
        $feed = Notifications::feed( get_current_user_id() ); $row = SubmissionService::get( $id );
        if ( $row ) $feed['changed'] = array( 'id' => (int) $row->id, 'status' => SubmissionService::status_label( $row->status ), 'assignee' => $row->assignee_name, 'closed' => Notifications::is_closed( $row ), 'attention' => ! Notifications::is_closed( $row ) && ( ! $row->assigned_to || $row->status === WorkflowService::default_status() || 'conflict' === $row->contact_sync_status ) );
        return $feed;
    }

    public static function ajax_complete() {
        self::ajax_guard();
        $id = Request::post_id( 'submission_id' );
        $result = SubmissionService::complete( $id, get_current_user_id() );
        if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
        Notifications::read( $id, get_current_user_id() );
        wp_send_json_success( self::changed_feed( $id ) );
    }

    public static function post_complete() {
        $id = Request::post_id( 'submission_id' ); check_admin_referer( 'bfcamel_crm_complete_' . $id );
        $result = SubmissionService::complete( $id, get_current_user_id() );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        wp_safe_redirect( self::return_url() ); exit;
    }

    public static function return_url( $area = 'submissions' ) {
        $fallback = admin_url( 'admin.php?page=bfcamel-crm-' . $area );
        $candidate = wp_validate_redirect( Request::post_url( 'bfcamel_return', Request::get_url( 'bfcamel_return' ) ), $fallback );
        if ( 0 === strpos( $candidate, '/' ) && 0 !== strpos( $candidate, '//' ) ) {
            $parts = wp_parse_url( admin_url() );
            $candidate = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $candidate;
        }
        return 0 === strpos( $candidate, admin_url() ) ? $candidate : $fallback;
    }

    public static function settings_panel() {
        $settings = Notifications::settings();
        global $wpdb;
        $failed = Schema::is_current() ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE state='failed'", Schema::table( 'notification_receipts' ) ) ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administrator sees current transport failures without exposing credentials or personal data.
        ?><details class="bfcamel-crm-panel" id="bfcamel-notification-settings"><summary><strong><?php esc_html_e( 'Notifications and delivery', 'bfcamel-crm' ); ?></strong></summary>
            <p><?php esc_html_e( 'Internal notifications are enabled. External delivery is opt-in and contains only a submission number and a secure CRM link.', 'bfcamel-crm' ); ?></p>
            <?php if ( $failed ) : ?><p class="notice notice-warning"><?php esc_html_e( 'Failed deliveries:', 'bfcamel-crm' ); ?> <?php echo absint( $failed ); ?></p><?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="bfcamel_crm_save_notifications"><?php wp_nonce_field( 'bfcamel_crm_save_notifications' ); ?>
                <p><label><input type="checkbox" name="notifications[email_enabled]" value="1" <?php checked( ! empty( $settings['email_enabled'] ) ); ?>> <?php esc_html_e( 'Email for urgent and high-priority submissions', 'bfcamel-crm' ); ?></label></p>
                <fieldset><legend><strong><?php esc_html_e( 'Email recipients', 'bfcamel-crm' ); ?></strong></legend>
                    <?php foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) : if ( ! user_can( $user, 'bfcamel_crm_view_submissions' ) ) continue; ?>
                    <label class="bfcamel-crm-recipient"><input type="checkbox" name="notifications[recipients][]" value="<?php echo absint( $user->ID ); ?>" <?php checked( in_array( absint( $user->ID ), array_map( 'absint', (array) $settings['recipients'] ), true ) ); ?>> <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <p><label><input type="checkbox" name="notifications[normal_digest]" value="1" <?php checked( ! empty( $settings['normal_digest'] ) ); ?>> <?php esc_html_e( 'Hourly email digest for ordinary submissions', 'bfcamel-crm' ); ?></label></p>
                <p><label><input type="checkbox" name="notifications[telegram_enabled]" value="1" <?php checked( ! empty( $settings['telegram_enabled'] ) ); ?>> <?php esc_html_e( 'Telegram for urgent submissions', 'bfcamel-crm' ); ?></label></p>
                <p><label><?php esc_html_e( 'Telegram bot token', 'bfcamel-crm' ); ?><input type="password" name="notifications[telegram_token]" value="" autocomplete="new-password" class="regular-text"></label><br><span class="description"><?php echo esc_html( $settings['telegram_token'] ? __( 'Configured. Leave blank to keep the token.', 'bfcamel-crm' ) : __( 'Create a bot with BotFather and add it to the destination chat.', 'bfcamel-crm' ) ); ?></span></p>
                <p><label><input type="checkbox" name="notifications[clear_token]" value="1"> <?php esc_html_e( 'Remove the saved Telegram token', 'bfcamel-crm' ); ?></label></p>
                <p><label><?php esc_html_e( 'Telegram chat ID', 'bfcamel-crm' ); ?><input type="text" name="notifications[telegram_chat]" class="regular-text" value="<?php echo esc_attr( $settings['telegram_chat'] ); ?>"></label></p>
                <p><label><?php esc_html_e( 'Remind about unclaimed urgent submissions after', 'bfcamel-crm' ); ?> <select name="notifications[reminder_minutes]"><?php foreach ( array( 0 => __( 'Never', 'bfcamel-crm' ), 5 => '5', 10 => '10', 15 => '15', 30 => '30' ) as $minutes => $label ) : ?><option value="<?php echo absint( $minutes ); ?>" <?php selected( (int) $settings['reminder_minutes'], $minutes ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select> <?php esc_html_e( 'minutes', 'bfcamel-crm' ); ?></label></p>
                <p><label><?php esc_html_e( 'Status after taking a submission', 'bfcamel-crm' ); ?> <select name="notifications[working_status]"><option value=""><?php esc_html_e( 'Keep current status', 'bfcamel-crm' ); ?></option><?php foreach ( SubmissionService::statuses() as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['working_status'] ?? 'in_progress', $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
                <fieldset><legend><strong><?php esc_html_e( 'Closed statuses: exclude from attention and notifications', 'bfcamel-crm' ); ?></strong></legend><?php foreach ( SubmissionService::statuses() as $slug => $label ) : ?><label class="bfcamel-crm-recipient"><input type="checkbox" name="notifications[closed_statuses][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['closed_statuses'], true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset>
                <p class="description"><?php esc_html_e( 'Delivery uses WordPress scheduled tasks. Configure a server cron for reliable delivery on quiet sites. A successful mail handoff does not guarantee inbox delivery.', 'bfcamel-crm' ); ?></p>
                <?php submit_button( __( 'Save notifications', 'bfcamel-crm' ) ); ?>
            </form>
        </details><?php
    }

    public static function save() {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'bfcamel_crm_manage_settings' ) ) wp_die( esc_html__( 'Only administrators can access CRM settings.', 'bfcamel-crm' ) );
        check_admin_referer( 'bfcamel_crm_save_notifications' );
        $posted = Request::post_array( 'notifications' ); $old = Notifications::settings();
        $recipients = array();
        foreach ( array_map( 'absint', (array) ( $posted['recipients'] ?? array() ) ) as $id ) if ( user_can( $id, 'bfcamel_crm_view_submissions' ) ) $recipients[] = $id;
        $token = ! empty( $posted['clear_token'] ) ? '' : $old['telegram_token'];
        if ( ! empty( $posted['telegram_token'] ) ) {
            if ( ! preg_match( '/^\d+:[A-Za-z0-9_-]+$/', $posted['telegram_token'] ) ) wp_die( esc_html__( 'Invalid Telegram bot token.', 'bfcamel-crm' ) );
            $token = $posted['telegram_token'];
        }
        $chat = sanitize_text_field( $posted['telegram_chat'] ?? '' );
        if ( $chat && ! preg_match( '/^(?:-?\d+|@[A-Za-z0-9_]+)$/', $chat ) ) wp_die( esc_html__( 'Invalid Telegram chat ID.', 'bfcamel-crm' ) );
        if ( ! empty( $posted['telegram_enabled'] ) && ( ! $token || ! $chat ) ) wp_die( esc_html__( 'Configure the bot token and chat ID before enabling Telegram.', 'bfcamel-crm' ) );
        $working = sanitize_key( $posted['working_status'] ?? '' );
        $settings = array(
            'email_enabled' => ! empty( $posted['email_enabled'] ), 'recipients' => array_values( array_unique( $recipients ) ),
            'normal_digest' => ! empty( $posted['normal_digest'] ), 'telegram_enabled' => ! empty( $posted['telegram_enabled'] ),
            'telegram_token' => $token, 'telegram_chat' => $chat,
            'reminder_minutes' => in_array( (int) ( $posted['reminder_minutes'] ?? 10 ), array( 0, 5, 10, 15, 30 ), true ) ? (int) $posted['reminder_minutes'] : 10,
            'working_status' => isset( SubmissionService::statuses()[ $working ] ) ? $working : '',
            'closed_statuses' => array_values( array_intersect( array_keys( SubmissionService::statuses() ), array_map( 'sanitize_key', (array) ( $posted['closed_statuses'] ?? array() ) ) ) ),
        );
        update_option( Notifications::OPTION, $settings, false );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-settings&saved=1#bfcamel-notification-settings' ) ); exit;
    }
}
