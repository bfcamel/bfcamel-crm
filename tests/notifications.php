<?php
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Notifications\Notifications;
use BfCamel\CRM\Admin\SubmissionsPage;
use BfCamel\CRM\Admin\SubmissionDetailPage;
use BfCamel\CRM\Admin\NotificationAdmin;

define( 'BFCAMEL_CRM_TEST_HELPERS_ONLY', true );
require __DIR__ . '/integration-seed.php';
global $wpdb;
wp_set_current_user( 1 );
$fixture = get_option( 'bfcamel_crm_test_fixture' );
$form = Repository::get( $fixture['form_id'] );
$events = Schema::table( 'notifications' ); $receipts = Schema::table( 'notification_receipts' );
$initial = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events ) );
Notifications::created( $fixture['linked'] );
crm_assert( $initial === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events ) ), 'no historical notification flood on upgrade' );
$mail = array(); $telegram = array(); $fail_mail = false;
add_filter( 'pre_wp_mail', static function( $value, $args ) use ( &$mail, &$fail_mail ) { $mail[] = $args; return ! $fail_mail; }, 10, 2 );
add_filter( 'pre_http_request', static function( $value, $args, $url ) use ( &$telegram ) {
    if ( 0 === strpos( $url, 'https://api.telegram.org/bot123456:TEST_TOKEN/sendMessage' ) ) { $telegram[] = $args['body']; return array( 'response' => array( 'code' => 200 ), 'body' => '{"ok":true}' ); }
    return new WP_Error( 'test_no_network', 'No outbound network in notification tests.' );
}, 10, 3 );
$settings = array_merge( Notifications::settings(), array( 'email_enabled' => true, 'recipients' => array( 1 ), 'normal_digest' => true, 'telegram_enabled' => true, 'telegram_token' => '123456:TEST_TOKEN', 'telegram_chat' => '-100123', 'reminder_minutes' => 5 ) );
update_option( Notifications::OPTION, $settings, false );
function new_notification_fixture( $priority, $form ) {
    global $wpdb;
    $id = crm_insert_submission( $form, 0, array( 'name' => 'Private customer', 'email' => 'private@example.test', 'phone' => '71111111111' ) );
    $wpdb->update( Schema::table( 'submissions' ), array( 'priority' => $priority ), array( 'id' => $id ) );
    Notifications::created( $id ); return $id;
}
$urgent = new_notification_fixture( 'urgent', $form );
$high = new_notification_fixture( 'high', $form );
$ordinary = new_notification_fixture( 'normal', $form );
$ordinary2 = new_notification_fixture( 'normal', $form );
$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events ) );
Notifications::created( $urgent );
crm_assert( $count === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events ) ), 'created hooks are idempotent' );
crm_assert( 'urgent' === Notifications::level( 'urgent' ) && 'high' === Notifications::level( 'high' ) && 'normal' === Notifications::level( 'normal' ), 'priority levels follow configured weights' );
crm_assert( Notifications::feed( 1 )['urgent'] >= 1, 'unclaimed urgent request is visible' );
$unread = Notifications::feed( 1 )['unread']; Notifications::read( $urgent, 1 );
crm_assert( Notifications::feed( 1 )['unread'] === $unread - 1 && Notifications::feed( 1 )['urgent'] >= 1, 'reading is personal and does not acknowledge shared urgency' );
Notifications::tick();
crm_assert( 2 === count( $mail ) && 1 === count( $telegram ), 'urgent and high email immediately; only urgent goes to Telegram' );
crm_assert( false === strpos( wp_json_encode( array( $mail, $telegram ) ), 'private@example.test' ) && false === strpos( wp_json_encode( array( $mail, $telegram ) ), '71111111111' ), 'external messages contain no submitted personal data' );
Notifications::tick();
crm_assert( 2 === count( $mail ) && 1 === count( $telegram ), 'worker reruns do not duplicate successful delivery' );
$wpdb->query( $wpdb->prepare( "UPDATE %i r INNER JOIN %i n ON n.id=r.event_id SET r.available_at=%s WHERE n.level='normal' AND r.channel='email' AND r.state='pending'", $receipts, $events, gmdate( 'Y-m-d H:i:s', time() - 1 ) ) );
Notifications::tick();
crm_assert( 3 === count( $mail ) && false !== strpos( $mail[2]['message'], '#' . $ordinary ) && false !== strpos( $mail[2]['message'], '#' . $ordinary2 ), 'ordinary requests are grouped in one digest' );
$second = wp_create_user( 'crm-second', 'integration-only', 'second@example.test' );
$user = get_user_by( 'id', $second ); $user->add_cap( 'bfcamel_crm_view_submissions' ); $user->add_cap( 'bfcamel_crm_edit_submissions' );
crm_assert( Notifications::feed( $second )['unread'] > Notifications::feed( 1 )['unread'], 'read state is independent for different staff members' );
crm_assert( true === SubmissionService::claim( $urgent, 1 ), 'staff member takes ownership' );
crm_assert( is_wp_error( SubmissionService::claim( $urgent, $second ) ), 'another staff member cannot steal ownership with the quick action' );
crm_assert( (int) SubmissionService::get( $urgent )->assigned_to === 1 && Notifications::feed( 1 )['urgent'] === 0, 'ownership acknowledges urgency for the team' );
crm_assert( is_wp_error( SubmissionService::complete( $urgent, $second ) ) && true === SubmissionService::complete( $urgent, 1 ), 'only current owner can complete using quick action' );
$retry = new_notification_fixture( 'high', $form ); $fail_mail = true; Notifications::tick();
$retry_event = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE submission_id=%d', $events, $retry ) );
$state = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE event_id=%d AND channel='email'", $receipts, $retry_event ) );
crm_assert( 'pending' === $state->state && 1 === (int) $state->attempts && null !== SubmissionService::get( $retry ), 'failed transport retains request and schedules retry' );
$fail_mail = false; $wpdb->update( $receipts, array( 'available_at' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ), array( 'event_id' => $retry_event ) ); Notifications::tick();
crm_assert( 'sent' === $wpdb->get_var( $wpdb->prepare( "SELECT state FROM %i WHERE event_id=%d AND channel='email'", $receipts, $retry_event ) ), 'transport retries successfully' );
$reminder = new_notification_fixture( 'urgent', $form );
$wpdb->update( $events, array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ), array( 'submission_id' => $reminder ) ); Notifications::tick(); Notifications::tick();
crm_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE submission_id=%d AND kind='reminder'", $events, $reminder ) ), 'unclaimed urgent request escalates once' );
SubmissionService::claim( $reminder, 1 ); Notifications::tick();
crm_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE submission_id=%d AND kind='reminder'", $events, $reminder ) ), 'acknowledged requests do not keep escalating' );
SubmissionService::bulk_apply( array( $ordinary ), 'priority', 'urgent', 1 ); Notifications::updated( $ordinary, 'normal' );
crm_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE submission_id=%d AND kind='raised'", $events, $ordinary ) ), 'raising priority alerts once even when update hook is replayed' );
$user->remove_cap( 'bfcamel_crm_view_submissions' );
crm_assert( 0 === Notifications::feed( $second )['unread'], 'revoked users cannot read notifications' );
$before_delete = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( 'submissions' ) ) );
crm_assert( true === SubmissionService::delete_permanently( $retry ), 'explicit submission deletion succeeds' );
crm_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_id=%d', $receipts, $retry_event ) ) && $before_delete - 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( 'submissions' ) ) ), 'delete removes only its dependent notification metadata' );
$_GET = array( 'view' => 'all', 'page' => 'bfcamel-crm-submissions' );
ob_start(); SubmissionsPage::instance()->page(); $html = ob_get_clean();
crm_assert( false !== strpos( $html, 'data-bfcamel-open' ) && false !== strpos( $html, 'bfcamel-crm-preview' ) && false !== strpos( $html, 'data-bfcamel-inbox' ), 'inbox provides large links and a preview panel' );
$_GET['bfcamel_return'] = admin_url( 'admin.php?page=bfcamel-crm-submissions&view=mine&s=legacy' );
ob_start(); SubmissionDetailPage::render( $reminder ); $detail = ob_get_clean();
crm_assert( false !== strpos( $detail, 'data-bfcamel-complete' ) && false !== strpos( $detail, 'name="bfcamel_return"' ), 'detail provides quick completion and preserves list context' );
$_GET['bfcamel_return'] = 'https://external.example/';
crm_assert( 0 === strpos( NotificationAdmin::return_url(), admin_url() ), 'return links reject external origins' );
update_option( Notifications::OPTION, array_merge( Notifications::settings(), array( 'email_enabled' => false, 'telegram_enabled' => false, 'telegram_token' => '' ) ), false );
$ui_form = Repository::save( 0, 'Обращение за помощью', 'ui-preview', array(
    array( 'name' => 'name', 'type' => 'text', 'label' => 'Имя', 'mapping' => 'contact.name' ),
    array( 'name' => 'email', 'type' => 'email', 'label' => 'Email' ),
    array( 'name' => 'subject', 'type' => 'text', 'label' => 'Тема' ),
    array( 'name' => 'message', 'type' => 'textarea', 'label' => 'Сообщение' ),
), array(), 1 );
$ui_id = crm_insert_submission( Repository::get( $ui_form ), 0, array( 'name' => 'Анна · пример', 'email' => 'demo@example.test', 'subject' => 'Нужна помощь животному', 'message' => 'Нашли раненое животное. Нужна помощь с доставкой к ветеринару.' ) );
$wpdb->update( Schema::table( 'submissions' ), array( 'priority' => 'urgent' ), array( 'id' => $ui_id ) );
Notifications::created( $ui_id );
update_option( 'bfcamel_crm_ui_test_id', $ui_id, false );
echo "Notification, delivery, inbox and ownership tests passed.\n";
