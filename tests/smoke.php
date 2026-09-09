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

$consent_event = (object) array(
    'event_type' => 'consent_unknown',
    'meta_json'  => json_encode( array( 'consent_type' => 'marketing', 'status' => 'unknown', 'source_type' => 'manual' ) ),
    'message'    => 'Consent status recorded.',
);
check( 'Consent Marketing: Unknown.' === ActivityFormatter::message( $consent_event ), 'Manual consent details are not rendered from metadata.' );
check( array( 'personal_data', 'marketing' ) === array_keys( ConsentService::types() ), 'Consent types are incomplete.' );
check( array( 'unknown', 'granted', 'denied', 'revoked' ) === array_keys( ConsentService::statuses() ), 'Consent statuses are incomplete.' );
$form_consent_status = new ReflectionMethod( ConsentService::class, 'status_from_form_value' );
$form_consent_status->setAccessible( true );
check( 'denied' === $form_consent_status->invoke( null, '' ), 'An unchecked form consent is not recorded as denied.' );
check( 'granted' === $form_consent_status->invoke( null, '1' ), 'A checked form consent is not recorded as granted.' );
$current_consents = ConsentService::current_statuses(
    1,
    array(
        (object) array( 'consent_type' => 'marketing', 'status' => 'denied' ),
        (object) array( 'consent_type' => 'marketing', 'status' => 'granted' ),
        (object) array( 'consent_type' => 'personal_data', 'status' => 'granted' ),
    )
);
check( 'granted' === $current_consents['personal_data'] && 'denied' === $current_consents['marketing'], 'Latest consent choices are not selected correctly.' );

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
check( false !== strpos( $plugin, 'Version: 0.3.1' ), 'Plugin header version is not 0.3.1.' );
check( false !== strpos( $plugin, "define( 'BFCAMEL_CRM_VERSION', '0.3.1' )" ), 'Plugin constant version is not 0.3.1.' );
check( false !== strpos( $readme, 'Stable tag: 0.3.1' ), 'Stable tag is not 0.3.1.' );
check( strpos( $plugin, "if ( defined( 'BFCAMEL_CRM_FILE' ) )" ) < strpos( $plugin, "define( 'BFCAMEL_CRM_VERSION'" ), 'Duplicate-copy guard must run before constants are defined.' );
$build_script = file_get_contents( $root . '/scripts/build-release.sh' );
check( false !== strpos( $build_script, 'PACKAGE_DIR="$BUILD_DIR/bfcamel-crm"' ), 'Release root folder is not bfcamel-crm.' );
check( false !== strpos( $build_script, "bfcamel-crm/bfcamel-crm.php" ), 'Release does not verify the established plugin basename.' );
check( false !== strpos( $build_script, 'LATEST_ZIP_PATH="$BUILD_DIR/bfcamel-crm.zip"' ), 'Stable install/update asset is missing.' );
check( false !== strpos( file_get_contents( $root . '/src/Admin/SubmissionDetailPage.php' ), 'Schema::begin_transaction()' ), 'Submission update is not transactional.' );
check( false !== strpos( file_get_contents( $root . '/src/Admin/SubmissionDetailPage.php' ), '$can_edit' ), 'Read-only rendering guard is missing.' );
check( false === strpos( file_get_contents( $root . '/src/I18n.php' ), "add_filter( 'gettext'" ), 'Runtime gettext override must not be used.' );
$schema_source = file_get_contents( $root . '/src/Database/Schema.php' );
check( false !== strpos( $schema_source, "const VERSION = '4'" ), 'Database schema version is not 4.' );
check( false !== strpos( $schema_source, "'contact_tags'" ), 'Contact-tag table is missing from the schema.' );
check( false !== strpos( $schema_source, 'recorded_by BIGINT UNSIGNED' ), 'Manual consent audit columns are missing from the schema.' );
check( false !== strpos( $schema_source, 'ENGINE=InnoDB' ), 'CRM tables are not explicitly transactional.' );
check( strpos( $schema_source, '$verification = self::verify()' ) < strpos( $schema_source, 'update_option( self::OPTION, self::VERSION' ), 'Schema version advances before verification.' );
check( false === strpos( file_get_contents( $root . '/assets/admin.js' ), 'BfCamelCRMI18n' ), 'DOM-based runtime translation override must not be used.' );
$bootstrap_source = file_get_contents( $root . '/src/Admin/Bootstrap.php' );
check( ! preg_match( "/'bfcamel-crm',\\s*array\\( DashboardPage::class, 'render' \\)/", $bootstrap_source ), 'Dashboard page hook has a duplicate render callback.' );
check( is_file( $root . '/languages/bfcamel-crm-ru_RU.mo' ), 'Compiled Russian localization catalog is missing.' );
$contacts_source = file_get_contents( $root . '/src/Admin/ContactsPage.php' );
check( false !== strpos( $contacts_source, 'bfcamel_crm_update_contact_consents' ), 'Manual contact consent control is missing.' );
check( false !== strpos( file_get_contents( $root . '/src/Consent/ConsentService.php' ), "'source_type'    => \$source_type" ), 'Consent event source is not audited.' );

echo "Smoke tests passed.\n";
