<?php
use BfCamel\CRM\Access\RoleManager;
use BfCamel\CRM\CRM\ActivityFormatter;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\Export\Exporter;
use BfCamel\CRM\Export\XlsxWriter;

define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function sanitize_title( $value ) { return strtolower( rawurlencode( (string) $value ) ); }
function get_userdata( $id ) { return (object) array( 'display_name' => 'User ' . (int) $id ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_tempnam() { return tempnam( sys_get_temp_dir(), 'bfcamel-crm-' ); }

require_once dirname( __DIR__ ) . '/src/Database/Schema.php';
require_once dirname( __DIR__ ) . '/src/CRM/TagService.php';
require_once dirname( __DIR__ ) . '/src/CRM/SubmissionService.php';
require_once dirname( __DIR__ ) . '/src/CRM/ActivityFormatter.php';
require_once dirname( __DIR__ ) . '/src/Access/RoleManager.php';
require_once dirname( __DIR__ ) . '/src/Export/Exporter.php';
require_once dirname( __DIR__ ) . '/src/Export/XlsxWriter.php';

function check( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, $message . PHP_EOL );
        exit( 1 );
    }
}

$slug = new ReflectionMethod( TagService::class, 'slug' );
$slug->setAccessible( true );
$long_tag = str_repeat( 'Очень длинная русская метка ', 20 );
$long_slug = $slug->invoke( null, $long_tag );
check( 0 === strpos( $long_slug, 'tag-' ), 'Long Cyrillic tags must use a deterministic hash slug.' );
check( strlen( $long_slug ) <= 120, 'Tag slug exceeds the database column.' );
check( $long_slug === $slug->invoke( null, $long_tag ), 'Tag slug is not deterministic.' );

$normalize = new ReflectionMethod( TagService::class, 'normalize_names' );
$normalize->setAccessible( true );
$names = $normalize->invoke( null, $long_tag . ', обычная' );
check( 2 === count( $names ), 'Tag parsing failed.' );
check( preg_match( '//u', $names[0] ), 'A truncated Cyrillic tag is not valid UTF-8.' );

$event = (object) array(
    'event_type' => 'status_changed',
    'meta_json'  => json_encode( array( 'from' => 'new', 'to' => 'completed' ) ),
    'message'    => 'Submission status changed.',
);
check( 'Status: New → Completed' === ActivityFormatter::message( $event ), 'Activity details are not rendered from metadata.' );

$defaults = new ReflectionMethod( RoleManager::class, 'default_permissions' );
$defaults->setAccessible( true );
$default_permissions = $defaults->invoke( null );
check( count( RoleManager::capability_keys() ) === count( array_filter( $default_permissions ) ), 'Main CRM permissions are not enabled by default.' );
check( ! isset( $default_permissions['bfcamel_crm_manage_settings'] ), 'Settings must not be part of role defaults.' );

$csv_cell = new ReflectionMethod( Exporter::class, 'safe_csv_cell' );
$csv_cell->setAccessible( true );
check( "'=2+2" === $csv_cell->invoke( null, '=2+2' ), 'CSV formula injection protection is missing.' );
$xlsx_cell = new ReflectionMethod( XlsxWriter::class, 'safe_cell' );
$xlsx_cell->setAccessible( true );
check( "'@SUM(A1)" === $xlsx_cell->invoke( null, '@SUM(A1)' ), 'XLSX formula injection protection is missing.' );
if ( class_exists( 'ZipArchive' ) ) {
    $xlsx_path = XlsxWriter::create( array( 'Имя' ), array( array( 'Тест' ) ) );
    check( is_string( $xlsx_path ) && is_file( $xlsx_path ), 'XLSX export was not created.' );
    $xlsx_zip = new ZipArchive();
    check( true === $xlsx_zip->open( $xlsx_path ), 'XLSX export is not a readable ZIP archive.' );
    $sheet_xml = $xlsx_zip->getFromName( 'xl/worksheets/sheet1.xml' );
    $xlsx_zip->close();
    check( false !== strpos( $sheet_xml, 'Тест' ), 'XLSX export lost UTF-8 content.' );
    unlink( $xlsx_path );
}

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/bfcamel-crm.php' );
$readme = file_get_contents( $root . '/readme.txt' );
check( false !== strpos( $plugin, 'Plugin Name: BfCamel CRM' ), 'Plugin name changed.' );
check( false !== strpos( $plugin, 'Text Domain: bfcamel-crm' ), 'Plugin text domain changed.' );
check( false !== strpos( $plugin, 'Domain Path: /languages' ), 'Plugin language path changed.' );
check( false !== strpos( $plugin, 'Update URI: https://github.com/bfcamel/bfcamel-crm' ), 'Plugin update URI changed.' );
check( false !== strpos( $plugin, 'Version: 0.3.0' ), 'Plugin header version is not 0.3.0.' );
check( false !== strpos( $plugin, "define( 'BFCAMEL_CRM_VERSION', '0.3.0' )" ), 'Plugin constant version is not 0.3.0.' );
check( false !== strpos( $readme, 'Stable tag: 0.3.0' ), 'Stable tag is not 0.3.0.' );
$build_script = file_get_contents( $root . '/scripts/build-release.sh' );
check( false !== strpos( $build_script, 'PACKAGE_DIR="$BUILD_DIR/bfcamel-crm"' ), 'Release root folder is not bfcamel-crm.' );
check( false !== strpos( $build_script, "bfcamel-crm/bfcamel-crm.php" ), 'Release does not verify the established plugin basename.' );
check( false !== strpos( file_get_contents( $root . '/src/Admin/SubmissionDetailPage.php' ), 'Schema::begin_transaction()' ), 'Submission update is not transactional.' );
check( false !== strpos( file_get_contents( $root . '/src/Admin/SubmissionDetailPage.php' ), '$can_edit' ), 'Read-only rendering guard is missing.' );
check( false === strpos( file_get_contents( $root . '/src/I18n.php' ), "add_filter( 'gettext'" ), 'Runtime gettext override must not be used.' );
$schema_source = file_get_contents( $root . '/src/Database/Schema.php' );
check( false !== strpos( $schema_source, "const VERSION = '3'" ), 'Database schema version is not 3.' );
check( false !== strpos( $schema_source, "'contact_tags'" ), 'Contact-tag table is missing from the schema.' );
check( false !== strpos( $schema_source, 'ENGINE=InnoDB' ), 'CRM tables are not explicitly transactional.' );
check( strpos( $schema_source, '$verification = self::verify()' ) < strpos( $schema_source, 'update_option( self::OPTION, self::VERSION' ), 'Schema version advances before verification.' );
check( false === strpos( file_get_contents( $root . '/assets/admin.js' ), 'BfCamelCRMI18n' ), 'DOM-based runtime translation override must not be used.' );

echo "Smoke tests passed.\n";
