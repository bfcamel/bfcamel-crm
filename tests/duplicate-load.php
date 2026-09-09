<?php
/** Regression test for a source archive activated beside an older copy. */

define( 'ABSPATH', __DIR__ . '/' );
define( 'BFCAMEL_CRM_VERSION', '0.2.0' );
define( 'BFCAMEL_CRM_FILE', '/wordpress/wp-content/plugins/bfcamel-crm/bfcamel-crm.php' );
define( 'BFCAMEL_CRM_DIR', '/wordpress/wp-content/plugins/bfcamel-crm/' );
define( 'BFCAMEL_CRM_URL', 'https://example.test/wp-content/plugins/bfcamel-crm/' );

$duplicate_actions = array();
function add_action( $hook, $callback ) {
    global $duplicate_actions;
    $duplicate_actions[] = $hook;
}

set_error_handler(
    static function ( $severity, $message ) {
        throw new ErrorException( $message, 0, $severity );
    }
);

require dirname( __DIR__ ) . '/bfcamel-crm.php';
restore_error_handler();

if ( '0.2.0' !== BFCAMEL_CRM_VERSION ) {
    fwrite( STDERR, "The duplicate copy replaced constants from the active installation.\n" );
    exit( 1 );
}
if ( in_array( 'plugins_loaded', $duplicate_actions, true ) || in_array( 'init', $duplicate_actions, true ) ) {
    fwrite( STDERR, "The duplicate copy registered runtime hooks.\n" );
    exit( 1 );
}
if ( ! in_array( 'admin_notices', $duplicate_actions, true ) ) {
    fwrite( STDERR, "The duplicate-copy administrator notice was not registered.\n" );
    exit( 1 );
}

echo "Duplicate-load regression test passed.\n";
