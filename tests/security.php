<?php
// Fast regressions using explicit WP/DB doubles; real MySQL coverage is in integration.php.
error_reporting( E_ALL & ~E_DEPRECATED );
require __DIR__ . '/smoke.php';
define( 'DB_NAME', 'crm_security_test' );
define( 'ARRAY_A', 'ARRAY_A' );
require dirname( __DIR__ ) . '/src/Forms/Repository.php';
require dirname( __DIR__ ) . '/src/Forms/Renderer.php';
require dirname( __DIR__ ) . '/src/Forms/SubmissionHandler.php';
require dirname( __DIR__ ) . '/src/Support/Request.php';
require dirname( __DIR__ ) . '/src/Privacy/Privacy.php';
class WP_Error { public function __construct( ...$args ) {} }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_salt( $scheme ) { return 'test-only'; }
function wp_get_session_token() { return ''; }
function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $v ); }
function esc_attr( $v ) { return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $v ) { return esc_html( $v ); }
function wp_rand( $min, $max ) { return $min; }
function map_deep( $value, $callback ) { return is_array( $value ) ? array_map( static function( $item ) use ( $callback ) { return map_deep( $item, $callback ); }, $value ) : $callback( $value ); }
function wp_clear_scheduled_hook( $hook ) {}
function delete_option( $key ) { global $bfcamel_test_options; unset( $bfcamel_test_options[ $key ] ); }
$test_transients = array();
function get_transient( $key ) { global $test_transients; return $test_transients[ $key ] ?? false; }
function set_transient( $key, $value, $seconds ) { global $test_transients; $test_transients[ $key ] = $value; return true; }
class SecurityRole {
    public $caps = array();
    public function add_cap( $cap ) { $this->caps[ $cap ] = true; }
    public function remove_cap( $cap ) { unset( $this->caps[ $cap ] ); }
}
$security_roles = (object) array( 'roles' => array( 'administrator' => array( 'name' => 'Administrator' ), 'subscriber' => array( 'name' => 'Subscriber' ) ) );
$security_role_objects = array( 'administrator' => new SecurityRole(), 'subscriber' => new SecurityRole() );
function wp_roles() { global $security_roles; return $security_roles; }
function get_role( $slug ) { global $security_role_objects; return $security_role_objects[ $slug ]; }
class SecurityDB {
    public $prefix = 'wp_';
    public $trace = array();
    public $writes = 0;
    public $lock_available = true;
    public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
    public function query( $sql ) { $this->trace[] = $sql; return 1; }
    public function get_var( $q ) { $this->trace[] = $q[0]; return false !== strpos( $q[0], 'GET_LOCK' ) ? (int) $this->lock_available : 1; }
    public function get_col( $q ) {
        return in_array( $q[1][1], array( 'victim@example.test', '79990000000' ), true ) ? array( 7 ) : array();
    }
    public function insert( ...$args ) { $this->writes++; return 1; }
    public function update( ...$args ) { $this->writes++; return 1; }
}
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\Forms\SubmissionHandler;
use BfCamel\CRM\Forms\Renderer;
use BfCamel\CRM\Support\Request;
use BfCamel\CRM\Privacy\Privacy;
use BfCamel\CRM\Access\RoleManager;
$wpdb = new SecurityDB();
$schema = array( array( 'name' => 'email', 'mapping' => 'contact.email' ), array( 'name' => 'phone', 'mapping' => 'contact.phone' ) );
check( ! Schema::hold_transaction_lock( 'outside' ), 'Lock must have a transaction owner.' );
check( Schema::begin_transaction(), 'Transaction failed to start.' );
check( ! Schema::begin_transaction(), 'Nested transaction would silently commit existing writes.' );
$result = ContactService::resolve_and_sync( $schema, array( 'email' => 'attacker@example.test', 'phone' => '79990000000' ) );
check( 'conflict' === $result['status'] && 0 === $result['contact_id'] && 0 === $wpdb->writes, 'Unverified email can change a known identity.' );
check( ! preg_grep( '/RELEASE_LOCK/', $wpdb->trace ), 'Identity locks released before commit.' );
Schema::commit();
$commit_index = array_search( 'COMMIT', $wpdb->trace, true );
foreach ( $wpdb->trace as $index => $sql ) if ( false !== strpos( $sql, 'RELEASE_LOCK' ) ) check( $index > $commit_index, 'Advisory lock released before COMMIT.' );
Schema::begin_transaction();
$result = ContactService::resolve_and_sync( $schema, array( 'email' => 'victim@example.test', 'phone' => '79990000000' ) );
check( 'linked' === $result['status'] && 7 === $result['contact_id'] && 0 === $wpdb->writes, 'Known identifiers should link without modifying identity.' );
Schema::rollback();
$rollback_index = array_search( 'ROLLBACK', $wpdb->trace, true );
check( false !== $rollback_index && false !== strpos( end( $wpdb->trace ), 'RELEASE_LOCK' ), 'Rollback did not release held locks.' );

$_SERVER = array( 'REMOTE_ADDR' => '192.0.2.1', 'HTTP_USER_AGENT' => 'First' );
$limiter = new ReflectionMethod( SubmissionHandler::class, 'is_rate_limited' );
$limiter->setAccessible( true );
check( false === $limiter->invoke( SubmissionHandler::instance(), 1 ), 'First request rejected.' );
$_SERVER['HTTP_USER_AGENT'] = 'Rotated';
check( true === $limiter->invoke( SubmissionHandler::instance(), 1 ), 'User-Agent rotation bypasses rate limit.' );
$wpdb->lock_available = false;
check( true === $limiter->invoke( SubmissionHandler::instance(), 2 ), 'Concurrent limiter lock failure must reject request.' );

$_POST = array( 'action' => 'bfcamel_crm_submit', 'bfcamel_crm_payload_version' => '2', 'fields' => array( 'action' => 'custom', 'choices' => array( 'One' ) ) );
check( array( 'action' => 'custom', 'choices' => array( 'One' ) ) === Request::post_payload(), 'Namespaced form payload is broken.' );
$_POST = array( 'action' => 'bfcamel_crm_submit', 'name' => 'Legacy visitor' );
check( 'Legacy visitor' === Request::post_payload()['name'], 'Already-rendered legacy form is incompatible.' );
$render = new ReflectionMethod( Renderer::class, 'render_field' ); $render->setAccessible( true );
$html = $render->invoke( Renderer::instance(), array( 'name' => 'action', 'type' => 'text', 'label' => 'Action', 'width' => '100' ) );
check( false !== strpos( $html, 'name="fields[action]"' ), 'Reserved field can override request routing.' );
$html = $render->invoke( Renderer::instance(), array( 'name' => 'choices', 'type' => 'checkbox', 'width' => '100', 'options' => array( 'One' ) ) );
check( false !== strpos( $html, 'name="fields[choices][]"' ), 'Checkbox arrays are incompatible.' );
$matches = new ReflectionMethod( Privacy::class, 'submission_has_email' ); $matches->setAccessible( true );
$row = (object) array( 'schema_json' => json_encode( array( array( 'name' => 'email', 'type' => 'email', 'mapping' => 'submission_only' ) ) ), 'payload_json' => json_encode( array( 'email' => 'Orphan@example.test' ) ) );
check( $matches->invoke( Privacy::instance(), $row, 'orphan@example.test' ), 'Standalone email lost from privacy discovery.' );
check( ! $matches->invoke( Privacy::instance(), $row, 'unrelated@example.test' ), 'Privacy discovery matches unrelated data.' );

RoleManager::ensure_roles();
check( ! empty( get_role( 'administrator' )->caps['bfcamel_crm_access'] ), 'Administrator permissions missing.' );
define( 'WP_UNINSTALL_PLUGIN', true );
require dirname( __DIR__ ) . '/uninstall.php';
RoleManager::ensure_roles();
check( ! empty( get_role( 'administrator' )->caps['bfcamel_crm_access'] ), 'Retained permission matrix not reapplied after uninstall.' );
get_role( 'administrator' )->remove_cap( 'bfcamel_crm_access' );
RoleManager::ensure_roles( true );
check( ! empty( get_role( 'administrator' )->caps['bfcamel_crm_access'] ), 'Activation cannot repair missing capabilities.' );
echo "Security regression tests passed.\n";
