<?php
// Run with wp eval-file on an isolated WordPress database, using the 0.5.6 plugin.
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\CRM\NoteService;

function crm_assert( $condition, $message ) {
    if ( ! $condition ) throw new RuntimeException( $message );
    echo 'PASS ' . $message . "\n";
}
function crm_snapshot() {
    global $wpdb;
    $snapshot = array();
    foreach ( array( 'forms', 'form_revisions', 'contacts', 'contact_emails', 'contact_phones', 'contact_fields', 'submissions', 'consent_events', 'tags', 'submission_tags', 'contact_tags', 'notes', 'activity_log' ) as $suffix ) {
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', Schema::table( $suffix ) ), ARRAY_A );
        $rows = array_map( 'wp_json_encode', $rows );
        sort( $rows, SORT_STRING );
        $snapshot[ $suffix ] = hash( 'sha256', wp_json_encode( $rows ) );
    }
    return $snapshot;
}
function crm_insert_submission( $form, $contact_id, $payload, $sync_status = 'conflict' ) {
    global $wpdb;
    $ok = $wpdb->insert( Schema::table( 'submissions' ), array(
        'form_id' => $form->id, 'revision_id' => $form->current_revision_id,
        'submission_uuid' => wp_generate_uuid4(), 'contact_id' => $contact_id,
        'status' => 'needs_review', 'priority' => 'normal', 'assigned_to' => 0,
        'contact_sync_status' => $sync_status, 'payload_json' => wp_json_encode( $payload ),
        'source_url' => 'https://example.test/private?reference=fixture',
        'source_ip' => '192.0.2.10', 'user_agent' => 'Fixture browser', 'submitted_at' => current_time( 'mysql' ),
    ) );
    if ( ! $ok ) throw new RuntimeException( $wpdb->last_error );
    $id = (int) $wpdb->insert_id;
    crm_assert( ! is_wp_error( NoteService::add( 'submission', $id, 'Private fixture note', 1 ) ), 'seed submission note' );
    crm_assert( ConsentService::record( $contact_id, $id, 'personal_data', 'granted', $form->id, $form->current_revision_id, array( 'version' => 'legacy' ), array( 'source_url' => 'https://example.test/private', 'source_ip' => '192.0.2.10', 'user_agent' => 'Fixture browser' ) ), 'seed consent' );
    Schema::log( 'submission', $id, 'created', 'Legacy submission', array( 'email' => $payload['email'] ), 1 );
    return $id;
}

if ( defined( 'BFCAMEL_CRM_TEST_HELPERS_ONLY' ) ) return;
crm_assert( '0.5.6' === BFCAMEL_CRM_VERSION, 'seed uses pre-fix 0.5.6' );
$schema = array(
    array( 'name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'mapping' => 'contact.name' ),
    array( 'name' => 'email', 'type' => 'email', 'label' => 'Email', 'mapping' => 'contact.email' ),
    array( 'name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'mapping' => 'contact.phone' ),
    array( 'name' => 'city', 'type' => 'text', 'label' => 'City', 'mapping' => 'contact.custom', 'custom_key' => 'city' ),
    array( 'name' => 'action', 'type' => 'text', 'label' => 'Action' ),
    array( 'name' => 'choices', 'type' => 'checkbox', 'label' => 'Choices', 'options' => array( 'One', 'Two' ) ),
    array( 'name' => 'consent', 'type' => 'consent_personal_data', 'label' => 'Consent', 'required' => true ),
);
$form_id = Repository::save( 0, 'Legacy form', 'legacy-form', $schema, array(), 1 );
crm_assert( ! is_wp_error( $form_id ), 'seed legacy form and immutable revision' );
$form = Repository::get( $form_id );
$victim = ContactService::create_manual( array( 'name' => 'Existing person', 'email' => 'victim@example.test', 'phone' => '79990000000', 'city' => 'Original city' ), array( 'Original tag' ), 1, array( 'marketing' => 'revoked' ) );
crm_assert( ! is_wp_error( $victim ), 'seed existing contact, tags and consent' );
$ids = array();
for ( $i = 0; $i < 105; $i++ ) {
    $email = 0 === $i % 2 ? 'orphan@example.test' : 'unrelated@example.test';
    $ids[] = crm_insert_submission( $form, 0, array( 'name' => 'Legacy person ' . $i, 'email' => $email, 'phone' => '70000000000' ), 'conflict' );
}
$linked = crm_insert_submission( $form, $victim, array( 'name' => 'Existing person', 'email' => 'victim@example.test' ), 'linked' );
// A valid legacy email field with submission_only mapping must also remain discoverable.
$schema[1]['mapping'] = 'submission_only';
$standalone = Repository::save( 0, 'Standalone form', 'standalone-form', $schema, array(), 1 );
$standalone_id = crm_insert_submission( Repository::get( $standalone ), 0, array( 'name' => 'Standalone', 'email' => 'standalone@example.test' ), 'created' );
update_option( 'bfcamel_crm_test_fixture', array( 'form_id' => $form_id, 'victim' => $victim, 'ids' => $ids, 'linked' => $linked, 'standalone' => $standalone_id ), false );
update_option( 'bfcamel_crm_test_snapshot', crm_snapshot(), false );
echo "Legacy fixtures saved.\n";
