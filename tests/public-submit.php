<?php
// Separate process: exercise real WordPress request handling or a concurrent transaction.
$request = json_decode( stream_get_contents( STDIN ), true );
require getenv( 'BFCAMEL_TEST_WP_LOAD' );
if ( 'contact' === ( $request['mode'] ?? '' ) ) {
    if ( ! \BfCamel\CRM\Database\Schema::begin_transaction() ) exit( 2 );
    $result = \BfCamel\CRM\CRM\ContactService::resolve_and_sync(
        array( array( 'name' => 'email', 'mapping' => 'contact.email' ), array( 'name' => 'phone', 'mapping' => 'contact.phone' ) ),
        array( 'email' => 'concurrent@example.test', 'phone' => '78888888888' )
    );
    usleep( 250000 );
    if ( 'error' === $result['status'] || ! \BfCamel\CRM\Database\Schema::commit() ) exit( 3 );
    echo wp_json_encode( $result );
    exit;
}
$_SERVER['REMOTE_ADDR'] = $request['ip'];
$_SERVER['HTTP_USER_AGENT'] = $request['agent'] ?? 'Integration test';
$_SERVER['REQUEST_METHOD'] = 'POST';
$posted = array(
    'action' => 'bfcamel_crm_submit', 'form_id' => $request['form_id'], 'revision_id' => $request['revision_id'],
    'return_url' => home_url( '/' ), 'bfcamel_crm_nonce' => wp_create_nonce( 'bfcamel_crm_submit_' . $request['form_id'] . '_' . $request['revision_id'] ),
);
if ( ! empty( $request['legacy'] ) ) {
    $posted = array_merge( $posted, $request['fields'] );
} else {
    $posted['bfcamel_crm_payload_version'] = '2';
    $posted['fields'] = $request['fields'];
}
$_POST = wp_slash( $posted );
\BfCamel\CRM\Forms\SubmissionHandler::instance()->handle();
