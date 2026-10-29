<?php
// Run with wp eval-file after replacing 0.5.6 sources in the same isolated installation.
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Forms\Renderer;
use BfCamel\CRM\Forms\SubmissionHandler;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\Privacy\Privacy;
use BfCamel\CRM\Access\RoleManager;
define( 'BFCAMEL_CRM_TEST_HELPERS_ONLY', true );
require __DIR__ . '/integration-seed.php';
global $wpdb;
wp_set_current_user( 1 );
$fixture = get_option( 'bfcamel_crm_test_fixture' );
crm_assert( get_option( 'bfcamel_crm_test_snapshot' ) === crm_snapshot(), 'loading fixed code preserves every legacy CRM row byte-for-byte' );
\BfCamel\CRM\Plugin::instance()->upgrade();
crm_assert( get_option( 'bfcamel_crm_test_snapshot' ) === crm_snapshot(), 'upgrade preserves all submissions, revisions, identifiers, notes and evidence' );
crm_assert( '6' === get_option( Schema::OPTION ), 'no destructive schema migration' );

// Retained data and permissions survive a real uninstall/reinstall lifecycle.
update_option( 'bfcamel_crm_settings', array( 'delete_data_on_uninstall' => false ) );
define( 'WP_UNINSTALL_PLUGIN', 'bfcamel-crm/bfcamel-crm.php' );
require dirname( __DIR__ ) . '/uninstall.php';
RoleManager::ensure_roles();
wp_set_current_user( 0 ); wp_set_current_user( 1 );
crm_assert( current_user_can( 'bfcamel_crm_access' ) && current_user_can( 'bfcamel_crm_manage_settings' ), 'CRM access restored after keep-data uninstall' );
crm_assert( get_option( 'bfcamel_crm_test_snapshot' ) === crm_snapshot(), 'keep-data lifecycle preserves every CRM row' );

function crm_worker_start( $request ) {
    $process = proc_open( array( PHP_BINARY, __DIR__ . '/public-submit.php' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
    fwrite( $pipes[0], json_encode( $request ) ); fclose( $pipes[0] );
    return array( $process, $pipes );
}
function crm_worker_end( $worker ) {
    list( $process, $pipes ) = $worker;
    $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] );
    fclose( $pipes[1] ); fclose( $pipes[2] );
    crm_assert( 0 === proc_close( $process ), 'public worker finished: ' . $err );
    return $out;
}
$form = Repository::get( $fixture['form_id'] );
$victim_before = wp_json_encode( array( ContactService::get_emails( $fixture['victim'] ), ContactService::get_phones( $fixture['victim'] ), ContactService::get_custom_fields( $fixture['victim'] ), ConsentService::current_statuses( $fixture['victim'] ) ) );
$request = array( 'form_id' => $form->id, 'revision_id' => $form->current_revision_id, 'ip' => '198.51.100.1', 'fields' => array( 'name' => 'Changed name', 'email' => 'attacker@example.test', 'phone' => '79990000000', 'city' => 'Attacker city', 'consent' => '1', 'action' => 'user-selected-action', 'choices' => array( 'Two' ) ) );
$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( 'submissions' ) ) );
crm_worker_end( crm_worker_start( $request ) );
$last = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', Schema::table( 'submissions' ) ) );
crm_assert( $count + 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( 'submissions' ) ) ), 'conflicting request is saved, never lost' );
crm_assert( 'conflict' === $last->contact_sync_status && 0 === (int) $last->contact_id, 'unverified extra identifier goes to review' );
$payload = json_decode( $last->payload_json, true );
crm_assert( 'user-selected-action' === $payload['action'] && array( 'Two' ) === $payload['choices'], 'namespaced reserved field and checkbox data preserved' );
crm_assert( array() === ContactService::find_contact_ids_by_email_value( 'attacker@example.test' ), 'attacker email never added to victim identity' );
crm_assert( $victim_before === wp_json_encode( array( ContactService::get_emails( $fixture['victim'] ), ContactService::get_phones( $fixture['victim'] ), ContactService::get_custom_fields( $fixture['victim'] ), ConsentService::current_statuses( $fixture['victim'] ) ) ), 'public conflict cannot change original contact or consent' );

$request['ip'] = '198.51.100.2';
$request['fields']['email'] = 'victim@example.test';
crm_worker_end( crm_worker_start( $request ) );
$last = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', Schema::table( 'submissions' ) ) );
crm_assert( 'linked' === $last->contact_sync_status && (int) $fixture['victim'] === (int) $last->contact_id, 'known identifiers still link legitimate repeat submissions' );
crm_assert( $victim_before === wp_json_encode( array( ContactService::get_emails( $fixture['victim'] ), ContactService::get_phones( $fixture['victim'] ), ContactService::get_custom_fields( $fixture['victim'] ), ConsentService::current_statuses( $fixture['victim'] ) ) ), 'even linked public requests leave fields and contact consent unchanged' );
$request['ip'] = '198.51.100.3'; $request['legacy'] = true; unset( $request['fields']['action'] );
crm_worker_end( crm_worker_start( $request ) );
crm_assert( $count + 3 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( 'submissions' ) ) ), 'already-rendered legacy forms continue saving submissions' );

$_SERVER['REMOTE_ADDR'] = '198.51.100.99'; $_SERVER['HTTP_USER_AGENT'] = 'First agent';
$limiter = new ReflectionMethod( SubmissionHandler::class, 'is_rate_limited' ); $limiter->setAccessible( true );
crm_assert( false === $limiter->invoke( SubmissionHandler::instance(), $form->id ), 'first request passes rate limit' );
$_SERVER['HTTP_USER_AGENT'] = 'Rotated agent';
crm_assert( true === $limiter->invoke( SubmissionHandler::instance(), $form->id ), 'User-Agent rotation cannot reset rate limit' );

$workers = array();
for ( $i = 0; $i < 4; $i++ ) $workers[] = crm_worker_start( array( 'mode' => 'contact' ) );
foreach ( $workers as $worker ) crm_worker_end( $worker );
crm_assert( 1 === count( ContactService::find_contact_ids_by_email_value( 'concurrent@example.test' ) ), 'parallel MySQL transactions create exactly one identity' );

function crm_export_all( $email ) {
    $all = array();
    for ( $page = 1; $page < 100; $page++ ) {
        $result = Privacy::instance()->export_personal_data( $email, $page );
        crm_assert( ! is_wp_error( $result ), 'privacy export batch succeeds' );
        $all = array_merge( $all, $result['data'] );
        if ( $result['done'] ) return $all;
    }
    throw new RuntimeException( 'Privacy exporter did not terminate.' );
}
$orphan_data = crm_export_all( 'orphan@example.test' );
$orphan_items = array_filter( $orphan_data, static function( $item ) { return 'bfcamel-crm-submissions' === $item['group_id']; } );
crm_assert( 53 === count( $orphan_items ), 'all detached/conflict submissions exported across three batches' );
crm_assert( false !== strpos( wp_json_encode( $orphan_data ), 'Fixture browser' ), 'orphan consent and technical metadata included' );
crm_assert( false !== strpos( wp_json_encode( crm_export_all( 'standalone@example.test' ) ), 'Standalone' ), 'submission-only email discovered from original revision' );
crm_assert( false === strpos( wp_json_encode( crm_export_all( 'attacker@example.test' ) ), 'victim@example.test' ), 'attacker privacy request cannot export victim identity' );

$rows_before_erasure = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( 'submissions' ) ) );
for ( $page = 1; $page < 100; $page++ ) {
    $result = Privacy::instance()->erase_personal_data( 'orphan@example.test', $page );
    if ( $result['done'] ) break;
}
crm_assert( $page < 100, 'orphan erasure finishes across batches' );
crm_assert( $rows_before_erasure === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( 'submissions' ) ) ), 'explicit privacy erasure anonymizes records without deleting submissions' );
foreach ( $fixture['ids'] as $index => $id ) {
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Schema::table( 'submissions' ), $id ) );
    $payload = json_decode( $row->payload_json, true );
    if ( 0 === $index % 2 ) {
        crm_assert( '' === $payload['email'] && '' === $row->source_ip && '' === $row->user_agent, 'matched orphan payload scrubbed' );
        crm_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE submission_id=%d AND (source_ip<>'' OR user_agent<>'')", Schema::table( 'consent_events' ), $id ) ), 'detached evidence metadata scrubbed' );
        crm_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE entity_type='submission' AND entity_id=%d", Schema::table( 'notes' ), $id ) ), 'matched orphan notes scrubbed' );
    } else {
        crm_assert( 'unrelated@example.test' === $payload['email'] && 'Fixture browser' === $row->user_agent, 'unrelated legacy submission unchanged' );
    }
}
crm_assert( 'Original city' === ContactService::get_custom_fields( $fixture['victim'] )['city'], 'existing victim details survive all operations' );
echo "WordPress/MySQL security and data-preservation integration tests passed.\n";
