<?php
use BfCamel\CRM\Access\RoleManager;
use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\CRM\ActivityFormatter;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\Export\Exporter;
use BfCamel\CRM\Export\XlsxWriter;

define( 'ABSPATH', __DIR__ . '/' );
function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $value ) { return $value; }
function sanitize_title( $value ) { return strtolower( rawurlencode( (string) $value ) ); }
function get_userdata( $id ) { return (object) array( 'display_name' => 'User ' . (int) $id ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_tempnam() { return tempnam( sys_get_temp_dir(), 'bfcamel-crm-' ); }

require_once dirname( __DIR__ ) . '/src/Database/Schema.php';
require_once dirname( __DIR__ ) . '/src/Consent/ConsentService.php';
require_once dirname( __DIR__ ) . '/src/CRM/TagService.php';
require_once dirname( __DIR__ ) . '/src/CRM/SubmissionService.php';
require_once dirname( __DIR__ ) . '/src/CRM/ActivityFormatter.php';
require_once dirname( __DIR__ ) . '/src/CRM/NoteService.php';
require_once dirname( __DIR__ ) . '/src/Access/RoleManager.php';
require_once dirname( __DIR__ ) . '/src/Export/Exporter.php';
require_once dirname( __DIR__ ) . '/src/Export/XlsxWriter.php';
function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, $message . PHP_EOL ); exit( 1 ); } }

$slug = new ReflectionMethod( TagService::class, 'slug' ); $slug->setAccessible( true );
$long_tag = str_repeat( 'Очень длинная русская метка ', 20 ); $long_slug = $slug->invoke( null, $long_tag );
check( 0 === strpos( $long_slug, 'tag-' ) && strlen( $long_slug ) <= 120, 'Long Cyrillic tags are not bounded.' );

$event = (object) array( 'event_type' => 'status_changed', 'meta_json' => json_encode( array( 'from' => 'new', 'to' => 'completed' ) ), 'message' => '' );
check( 'Status: New → Completed' === ActivityFormatter::message( $event ), 'Activity details are broken.' );
$note = (object) array( 'event_type' => 'note_added', 'meta_json' => '{}', 'message' => '' );
check( 'Internal note added.' === ActivityFormatter::message( $note ), 'Note activity label is missing.' );
$contact_edit = (object) array( 'event_type' => 'contact_updated', 'meta_json' => json_encode( array( 'changes' => array( 'name' => array( 'from' => 'A', 'to' => 'B' ) ) ) ), 'message' => '' );
check( false !== strpos( ActivityFormatter::message( $contact_edit ), 'A → B' ), 'Contact edit audit details are missing.' );
check( array( 'unknown', 'granted', 'denied', 'revoked' ) === array_keys( ConsentService::statuses() ), 'Consent statuses are incomplete.' );

$defaults = new ReflectionMethod( RoleManager::class, 'default_permissions' ); $defaults->setAccessible( true ); $default_permissions = $defaults->invoke( null );
check( count( RoleManager::capability_keys() ) === count( array_filter( $default_permissions ) ), 'CRM permissions are not enabled by default.' );
$csv_cell = new ReflectionMethod( Exporter::class, 'safe_csv_cell' ); $csv_cell->setAccessible( true );
check( "'=2+2" === $csv_cell->invoke( null, '=2+2' ), 'CSV injection protection is missing.' );
$xlsx_cell = new ReflectionMethod( XlsxWriter::class, 'safe_cell' ); $xlsx_cell->setAccessible( true );
check( "'@SUM(A1)" === $xlsx_cell->invoke( null, '@SUM(A1)' ), 'XLSX injection protection is missing.' );

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/bfcamel-crm.php' ); $readme = file_get_contents( $root . '/readme.txt' );
check( false !== strpos( $plugin, 'Version: 0.3.2' ), 'Plugin header version is not 0.3.2.' );
check( false !== strpos( $plugin, "define( 'BFCAMEL_CRM_VERSION', '0.3.2' )" ), 'Plugin constant version is not 0.3.2.' );
check( false !== strpos( $readme, 'Stable tag: 0.3.2' ), 'Stable tag is not 0.3.2.' );
check( strpos( $plugin, "if ( defined( 'BFCAMEL_CRM_FILE' ) )" ) < strpos( $plugin, "define( 'BFCAMEL_CRM_VERSION'" ), 'Duplicate guard moved after constants.' );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
check( false !== strpos( $schema, "const VERSION = '5'" ), 'Database schema version is not 5.' );
check( false !== strpos( $schema, "self::table( 'notes' )" ), 'Internal notes table is missing.' );
$bootstrap = file_get_contents( $root . '/src/Admin/Bootstrap.php' );
foreach ( array( 'bfcamel_crm_update_contact', 'bfcamel_crm_bulk_contacts', 'bfcamel_crm_add_contact_note', 'bfcamel_crm_bulk_submissions', 'bfcamel_crm_add_submission_note' ) as $hook ) check( false !== strpos( $bootstrap, $hook ), 'Missing admin handler: ' . $hook );
$contacts = file_get_contents( $root . '/src/Admin/ContactsPage.php' );
check( false !== strpos( $contacts, 'personal_data_consent' ) && false !== strpos( $contacts, 'marketing_consent' ), 'Consent filters are missing.' );
check( false !== strpos( $contacts, 'Export selected CSV' ), 'Selected contact export is missing.' );
$submissions = file_get_contents( $root . '/src/Admin/SubmissionsPage.php' );
check( false !== strpos( $submissions, 'My submissions' ), 'My submissions view is missing.' );
check( false !== strpos( $submissions, "pagination(\$result,'top')" ), 'Top submission pagination is missing.' );
check( false !== strpos( $contacts, "pagination( \$result, \$filters, 'top' )" ), 'Top contact pagination is missing.' );
check( is_file( $root . '/languages/bfcamel-crm-ru_RU.mo' ), 'Russian MO catalog is missing.' );

if ( class_exists( 'ZipArchive' ) ) {
    $xlsx_path = XlsxWriter::create( array( 'Имя' ), array( array( 'Тест' ) ) );
    check( is_string( $xlsx_path ) && is_file( $xlsx_path ), 'XLSX export was not created.' );
    @unlink( $xlsx_path );
}
echo "Smoke tests passed.\n";
