<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Admin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function register() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_post_bfcamel_crm_save_form', array( $this, 'save_form' ) );
        add_action( 'admin_post_bfcamel_crm_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_bfcamel_crm_update_submission', array( $this, 'update_submission' ) );
    }

    public function menu() {
        add_menu_page(
            __( 'BfCamel CRM', 'bfcamel-crm' ),
            __( 'BfCamel CRM', 'bfcamel-crm' ),
            'bfcamel_crm_view_submissions',
            'bfcamel-crm',
            array( $this, 'dashboard' ),
            'dashicons-feedback',
            26
        );

        add_submenu_page( 'bfcamel-crm', __( 'Dashboard', 'bfcamel-crm' ), __( 'Dashboard', 'bfcamel-crm' ), 'bfcamel_crm_view_submissions', 'bfcamel-crm', array( $this, 'dashboard' ) );
        add_submenu_page( 'bfcamel-crm', __( 'Forms', 'bfcamel-crm' ), __( 'Forms', 'bfcamel-crm' ), 'bfcamel_crm_manage_forms', 'bfcamel-crm-forms', array( $this, 'forms_page' ) );
        add_submenu_page( 'bfcamel-crm', __( 'Submissions', 'bfcamel-crm' ), __( 'Submissions', 'bfcamel-crm' ), 'bfcamel_crm_view_submissions', 'bfcamel-crm-submissions', array( $this, 'submissions_page' ) );
        add_submenu_page( 'bfcamel-crm', __( 'Contacts', 'bfcamel-crm' ), __( 'Contacts', 'bfcamel-crm' ), 'bfcamel_crm_manage_contacts', 'bfcamel-crm-contacts', array( $this, 'contacts_page' ) );
        add_submenu_page( 'bfcamel-crm', __( 'Settings', 'bfcamel-crm' ), __( 'Settings', 'bfcamel-crm' ), 'bfcamel_crm_manage_settings', 'bfcamel-crm-settings', array( $this, 'settings_page' ) );
    }

    public function assets( $hook ) {
        if ( false === strpos( (string) $hook, 'bfcamel-crm' ) ) {
            return;
        }

        wp_enqueue_style( 'bfcamel-crm-admin', BFCAMEL_CRM_URL . 'assets/admin.css', array(), BFCAMEL_CRM_VERSION );
        wp_enqueue_script( 'bfcamel-crm-admin', BFCAMEL_CRM_URL . 'assets/admin.js', array(), BFCAMEL_CRM_VERSION, true );

        if ( 'bfcamel-crm_page_bfcamel-crm-forms' === $hook ) {
            $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form = $id ? Repository::get( $id ) : null;
            $revision = $form ? Repository::current_revision( $form ) : null;
            $schema = $revision ? Repository::decode_schema( $revision ) : Repository::default_schema();

            wp_localize_script(
                'bfcamel-crm-admin',
                'BfCamelCRMBuilder',
                array(
                    'schema' => $schema,
                    'labels' => array(
                        'field'       => __( 'Field', 'bfcamel-crm' ),
                        'remove'      => __( 'Remove', 'bfcamel-crm' ),
                        'moveUp'      => __( 'Move up', 'bfcamel-crm' ),
                        'moveDown'    => __( 'Move down', 'bfcamel-crm' ),
                        'label'       => __( 'Label / text', 'bfcamel-crm' ),
                        'key'         => __( 'Field key', 'bfcamel-crm' ),
                        'placeholder' => __( 'Placeholder / hidden value', 'bfcamel-crm' ),
                        'required'    => __( 'Required', 'bfcamel-crm' ),
                        'width'       => __( 'Width', 'bfcamel-crm' ),
                        'mapping'     => __( 'CRM mapping', 'bfcamel-crm' ),
                        'customKey'   => __( 'Custom field key', 'bfcamel-crm' ),
                        'options'     => __( 'Options, one per line', 'bfcamel-crm' ),
                    ),
                )
            );
        }
    }

    public function dashboard() {
        $this->guard( 'bfcamel_crm_view_submissions' );
        global $wpdb;

        $submissions = Schema::table( 'submissions' );
        $contacts    = Schema::table( 'contacts' );
        $forms       = Schema::table( 'forms' );
        $consents    = Schema::table( 'consent_events' );

        $counts = array(
            'forms'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$forms}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'submissions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$submissions}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'contacts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$contacts}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'consents'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$consents}" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        $recent = $wpdb->get_results(
            "SELECT s.*, f.name AS form_name, c.display_name AS contact_name
             FROM {$submissions} s
             LEFT JOIN {$forms} f ON f.id=s.form_id
             LEFT JOIN {$contacts} c ON c.id=s.contact_id
             ORDER BY s.submitted_at DESC, s.id DESC LIMIT 8" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        ?>
        <div class="wrap bfcamel-crm-admin">
            <h1><?php esc_html_e( 'BfCamel CRM', 'bfcamel-crm' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Native forms, submissions, contacts and consent evidence for WordPress.', 'bfcamel-crm' ); ?></p>

            <div class="bfcamel-crm-stats">
                <?php foreach ( $counts as $key => $count ) : ?>
                    <div class="bfcamel-crm-stat">
                        <strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
                        <span><?php echo esc_html( ucfirst( $key ) ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bfcamel-crm-panel">
                <div class="bfcamel-crm-panel__head">
                    <h2><?php esc_html_e( 'Recent submissions', 'bfcamel-crm' ); ?></h2>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions' ) ); ?>"><?php esc_html_e( 'View all', 'bfcamel-crm' ); ?></a>
                </div>
                <?php $this->submissions_table( $recent ); ?>
            </div>
        </div>
        <?php
    }

    public function forms_page() {
        $this->guard( 'bfcamel_crm_manage_forms' );
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
            $this->form_editor();
            return;
        }

        $forms = Repository::all();
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div><h1><?php esc_html_e( 'Forms', 'bfcamel-crm' ); ?></h1><p class="description"><?php esc_html_e( 'Published form schemas are the CRM contracts. Every save creates an immutable revision.', 'bfcamel-crm' ); ?></p></div>
                <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-forms&action=new' ) ); ?>"><?php esc_html_e( 'Add form', 'bfcamel-crm' ); ?></a>
            </div>

            <div class="bfcamel-crm-panel">
                <table class="widefat striped bfcamel-crm-table">
                    <thead><tr><th><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Slug', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Shortcode', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Updated', 'bfcamel-crm' ); ?></th></tr></thead>
                    <tbody>
                    <?php if ( ! $forms ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'No forms yet.', 'bfcamel-crm' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $forms as $form ) : ?>
                            <tr>
                                <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-forms&action=edit&id=' . absint( $form->id ) ) ); ?>"><?php echo esc_html( $form->name ); ?></a></strong></td>
                                <td><code><?php echo esc_html( $form->slug ); ?></code></td>
                                <td><code>[bfcamel_form id="<?php echo esc_html( $form->id ); ?>"]</code></td>
                                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $form->updated_at ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function form_editor() {
        $id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $form     = $id ? Repository::get( $id ) : null;
        $revision = $form ? Repository::current_revision( $form ) : null;
        $settings = $revision ? Repository::decode_settings( $revision ) : Repository::default_settings();
        $version  = $revision ? absint( $revision->version ) : 0;

        $notice = isset( $_GET['saved'] ) ? sanitize_key( $_GET['saved'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div>
                    <h1><?php echo esc_html( $form ? sprintf( __( 'Edit form: %s', 'bfcamel-crm' ), $form->name ) : __( 'Add form', 'bfcamel-crm' ) ); ?></h1>
                    <?php if ( $version ) : ?><p class="description"><?php echo esc_html( sprintf( __( 'Current revision: %d', 'bfcamel-crm' ), $version ) ); ?></p><?php endif; ?>
                </div>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-forms' ) ); ?>"><?php esc_html_e( 'Back to forms', 'bfcamel-crm' ); ?></a>
            </div>

            <?php if ( '1' === $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form saved and a new revision was published.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bfcamel-crm-form-editor">
                <input type="hidden" name="action" value="bfcamel_crm_save_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $id ); ?>">
                <?php wp_nonce_field( 'bfcamel_crm_save_form' ); ?>

                <div class="bfcamel-crm-editor-grid">
                    <main>
                        <div class="bfcamel-crm-panel">
                            <h2><?php esc_html_e( 'Form', 'bfcamel-crm' ); ?></h2>
                            <div class="bfcamel-crm-two-col">
                                <p><label><strong><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="text" name="form_name" required value="<?php echo esc_attr( $form ? $form->name : '' ); ?>"></label></p>
                                <p><label><strong><?php esc_html_e( 'Slug', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="text" name="form_slug" value="<?php echo esc_attr( $form ? $form->slug : '' ); ?>" placeholder="contact-form"></label></p>
                            </div>
                        </div>

                        <div class="bfcamel-crm-panel">
                            <div class="bfcamel-crm-panel__head"><h2><?php esc_html_e( 'Fields', 'bfcamel-crm' ); ?></h2><span class="description"><?php esc_html_e( 'Reorder fields and map them directly to CRM contact data.', 'bfcamel-crm' ); ?></span></div>
                            <div id="bfcamel-crm-builder"></div>
                            <input type="hidden" id="bfcamel-crm-schema-json" name="schema_json" value="">
                            <div class="bfcamel-crm-add-field">
                                <?php foreach ( $this->field_types() as $type => $label ) : ?>
                                    <button type="button" class="button bfcamel-crm-add-field-button" data-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </main>

                    <aside>
                        <div class="bfcamel-crm-panel bfcamel-crm-sticky">
                            <h2><?php esc_html_e( 'Appearance', 'bfcamel-crm' ); ?></h2>
                            <p><label><?php esc_html_e( 'Style mode', 'bfcamel-crm' ); ?><select name="settings[style_mode]"><?php foreach ( array( 'theme' => __( 'Theme / unstyled', 'bfcamel-crm' ), 'default' => __( 'Default', 'bfcamel-crm' ), 'custom' => __( 'Custom', 'bfcamel-crm' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['style_mode'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><?php esc_html_e( 'Columns', 'bfcamel-crm' ); ?><select name="settings[columns]"><option value="1" <?php selected( $settings['columns'], '1' ); ?>>1</option><option value="2" <?php selected( $settings['columns'], '2' ); ?>>2</option></select></label></p>
                            <?php $this->color_input( 'primary_color', __( 'Primary / focus color', 'bfcamel-crm' ), $settings ); ?>
                            <?php $this->color_input( 'text_color', __( 'Text color', 'bfcamel-crm' ), $settings ); ?>
                            <?php $this->color_input( 'field_bg', __( 'Field background', 'bfcamel-crm' ), $settings ); ?>
                            <?php $this->color_input( 'border_color', __( 'Border color', 'bfcamel-crm' ), $settings ); ?>
                            <?php $this->color_input( 'button_color', __( 'Button color', 'bfcamel-crm' ), $settings ); ?>
                            <?php $this->color_input( 'button_text', __( 'Button text', 'bfcamel-crm' ), $settings ); ?>
                            <p><label><?php esc_html_e( 'Border radius, px', 'bfcamel-crm' ); ?><input type="number" min="0" max="40" name="settings[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ); ?>"></label></p>
                            <p><label><?php esc_html_e( 'Custom wrapper class', 'bfcamel-crm' ); ?><input type="text" name="settings[custom_class]" value="<?php echo esc_attr( $settings['custom_class'] ); ?>"></label></p>
                            <hr>
                            <p><label><?php esc_html_e( 'Submit button label', 'bfcamel-crm' ); ?><input type="text" name="settings[submit_label]" value="<?php echo esc_attr( $settings['submit_label'] ); ?>"></label></p>
                            <p><label><?php esc_html_e( 'Success message', 'bfcamel-crm' ); ?><textarea name="settings[success_message]" rows="3"><?php echo esc_textarea( $settings['success_message'] ); ?></textarea></label></p>
                            <p><label><?php esc_html_e( 'Error message', 'bfcamel-crm' ); ?><textarea name="settings[error_message]" rows="3"><?php echo esc_textarea( $settings['error_message'] ); ?></textarea></label></p>
                            <?php submit_button( $form ? __( 'Save & publish revision', 'bfcamel-crm' ) : __( 'Create form', 'bfcamel-crm' ), 'primary large', 'submit', false ); ?>
                        </div>
                    </aside>
                </div>
            </form>
        </div>
        <?php
    }

    public function save_form() {
        $this->guard( 'bfcamel_crm_manage_forms' );
        check_admin_referer( 'bfcamel_crm_save_form' );

        $schema_json = isset( $_POST['schema_json'] ) ? wp_unslash( $_POST['schema_json'] ) : '';
        $schema      = json_decode( $schema_json, true );
        $settings    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
        $id          = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $name        = isset( $_POST['form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['form_name'] ) ) : '';
        $slug        = isset( $_POST['form_slug'] ) ? sanitize_title( wp_unslash( $_POST['form_slug'] ) ) : '';

        $saved = Repository::save( $id, $name, $slug, $schema, $settings, get_current_user_id() );
        if ( is_wp_error( $saved ) ) {
            wp_die( esc_html( $saved->get_error_message() ), esc_html__( 'Could not save form', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-forms&action=edit&id=' . absint( $saved ) . '&saved=1' ) );
        exit;
    }

    public function submissions_page() {
        $this->guard( 'bfcamel_crm_view_submissions' );
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $id ) {
            $this->submission_detail( $id );
            return;
        }

        global $wpdb;
        $submissions = Schema::table( 'submissions' );
        $forms = Schema::table( 'forms' );
        $contacts = Schema::table( 'contacts' );
        $rows = $wpdb->get_results(
            "SELECT s.*, f.name AS form_name, c.display_name AS contact_name
             FROM {$submissions} s
             LEFT JOIN {$forms} f ON f.id=s.form_id
             LEFT JOIN {$contacts} c ON c.id=s.contact_id
             ORDER BY s.submitted_at DESC,s.id DESC LIMIT 250" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        ?>
        <div class="wrap bfcamel-crm-admin">
            <h1><?php esc_html_e( 'Submissions', 'bfcamel-crm' ); ?></h1>
            <p class="description"><?php esc_html_e( 'The first 250 most recent submissions are shown in this alpha release.', 'bfcamel-crm' ); ?></p>
            <div class="bfcamel-crm-panel"><?php $this->submissions_table( $rows ); ?></div>
        </div>
        <?php
    }

    private function submissions_table( $rows ) {
        ?>
        <table class="widefat striped bfcamel-crm-table">
            <thead><tr><th>ID</th><th><?php esc_html_e( 'Form', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Contact', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Received', 'bfcamel-crm' ); ?></th></tr></thead>
            <tbody>
            <?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No submissions yet.', 'bfcamel-crm' ); ?></td></tr><?php endif; ?>
            <?php foreach ( (array) $rows as $row ) : ?>
                <tr>
                    <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . absint( $row->id ) ) ); ?>">#<?php echo esc_html( $row->id ); ?></a></td>
                    <td><?php echo esc_html( $row->form_name ?: '#' . $row->form_id ); ?></td>
                    <td><?php echo esc_html( $row->contact_name ?: ( 'conflict' === $row->contact_sync_status ? __( 'Needs review', 'bfcamel-crm' ) : '—' ) ); ?></td>
                    <td><span class="bfcamel-crm-badge bfcamel-crm-badge--<?php echo esc_attr( sanitize_html_class( $row->status ) ); ?>"><?php echo esc_html( $row->status ); ?></span></td>
                    <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->submitted_at ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function submission_detail( $id ) {
        global $wpdb;
        $table = Schema::table( 'submissions' );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d LIMIT 1", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! $row ) {
            wp_die( esc_html__( 'Submission not found.', 'bfcamel-crm' ) );
        }
        $form = Repository::get( $row->form_id );
        $revision = Repository::get_revision( $row->revision_id );
        $schema = Repository::decode_schema( $revision );
        $payload = json_decode( $row->payload_json, true );
        $payload = is_array( $payload ) ? $payload : array();
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title"><div><h1><?php echo esc_html( sprintf( __( 'Submission #%d', 'bfcamel-crm' ), $id ) ); ?></h1><p class="description"><?php echo esc_html( $form ? $form->name : '#' . $row->form_id ); ?> · UUID <code><?php echo esc_html( $row->submission_uuid ); ?></code></p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions' ) ); ?>"><?php esc_html_e( 'Back', 'bfcamel-crm' ); ?></a></div>
            <div class="bfcamel-crm-editor-grid">
                <main>
                    <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Submitted data', 'bfcamel-crm' ); ?></h2><dl class="bfcamel-crm-dl">
                        <?php foreach ( $schema as $field ) : $name = $field['name'] ?? ''; if ( ! $name || 'html' === ( $field['type'] ?? '' ) ) continue; $value = $payload[ $name ] ?? ''; ?>
                            <dt><?php echo esc_html( $field['label'] ?: $name ); ?></dt><dd><?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : (string) $value ); ?></dd>
                        <?php endforeach; ?>
                    </dl></div>
                </main>
                <aside>
                    <div class="bfcamel-crm-panel">
                        <h2><?php esc_html_e( 'CRM', 'bfcamel-crm' ); ?></h2>
                        <p><strong><?php esc_html_e( 'Contact sync', 'bfcamel-crm' ); ?>:</strong> <?php echo esc_html( $row->contact_sync_status ); ?></p>
                        <?php if ( $row->contact_id ) : ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . absint( $row->contact_id ) ) ); ?>"><?php esc_html_e( 'Open contact', 'bfcamel-crm' ); ?></a></p><?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="bfcamel_crm_update_submission"><input type="hidden" name="submission_id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'bfcamel_crm_update_submission_' . $id ); ?>
                            <p><label><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?><select name="status"><?php foreach ( array( 'new', 'in_progress', 'waiting', 'completed', 'needs_review' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $row->status, $status ); ?>><?php echo esc_html( $status ); ?></option><?php endforeach; ?></select></label></p>
                            <?php submit_button( __( 'Update', 'bfcamel-crm' ), 'secondary', 'submit', false ); ?>
                        </form>
                    </div>
                    <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Evidence', 'bfcamel-crm' ); ?></h2><p><strong><?php esc_html_e( 'Form revision', 'bfcamel-crm' ); ?>:</strong> <?php echo esc_html( $revision ? $revision->version : $row->revision_id ); ?></p><p><strong><?php esc_html_e( 'Source URL', 'bfcamel-crm' ); ?>:</strong><br><code><?php echo esc_html( $row->source_url ); ?></code></p><p><strong><?php esc_html_e( 'IP stored', 'bfcamel-crm' ); ?>:</strong> <?php echo $row->source_ip ? esc_html( $row->source_ip ) : esc_html__( 'No', 'bfcamel-crm' ); ?></p></div>
                </aside>
            </div>
        </div>
        <?php
    }

    public function update_submission() {
        $this->guard( 'bfcamel_crm_edit_submissions' );
        $id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        check_admin_referer( 'bfcamel_crm_update_submission_' . $id );
        $status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'new';
        $allowed = array( 'new', 'in_progress', 'waiting', 'completed', 'needs_review' );
        if ( ! in_array( $status, $allowed, true ) ) {
            $status = 'new';
        }
        global $wpdb;
        $wpdb->update( Schema::table( 'submissions' ), array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
        Schema::log( 'submission', $id, 'status_changed', __( 'Submission status changed.', 'bfcamel-crm' ), array( 'status' => $status ), get_current_user_id() );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . $id ) );
        exit;
    }

    public function contacts_page() {
        $this->guard( 'bfcamel_crm_manage_contacts' );
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $id ) {
            $this->contact_detail( $id );
            return;
        }

        global $wpdb;
        $contacts = Schema::table( 'contacts' );
        $emails = Schema::table( 'contact_emails' );
        $phones = Schema::table( 'contact_phones' );
        $rows = $wpdb->get_results(
            "SELECT c.*,
                (SELECT value FROM {$emails} e WHERE e.contact_id=c.id ORDER BY e.is_primary DESC,e.id ASC LIMIT 1) AS email,
                (SELECT value FROM {$phones} p WHERE p.contact_id=c.id ORDER BY p.is_primary DESC,p.id ASC LIMIT 1) AS phone
             FROM {$contacts} c ORDER BY c.updated_at DESC,c.id DESC LIMIT 250" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        ?>
        <div class="wrap bfcamel-crm-admin"><h1><?php esc_html_e( 'Contacts', 'bfcamel-crm' ); ?></h1><p class="description"><?php esc_html_e( 'Contacts are matched by normalized email and phone. Conflicting identifiers are never merged automatically.', 'bfcamel-crm' ); ?></p><div class="bfcamel-crm-panel"><table class="widefat striped bfcamel-crm-table"><thead><tr><th><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Email', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Phone', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Organization', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Updated', 'bfcamel-crm' ); ?></th></tr></thead><tbody>
            <?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No contacts yet.', 'bfcamel-crm' ); ?></td></tr><?php endif; ?>
            <?php foreach ( (array) $rows as $row ) : ?><tr><td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . absint( $row->id ) ) ); ?>"><?php echo esc_html( $row->display_name ); ?></a></strong></td><td><?php echo esc_html( $row->email ?: '—' ); ?></td><td><?php echo esc_html( $row->phone ?: '—' ); ?></td><td><?php echo esc_html( $row->organization ?: '—' ); ?></td><td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row->updated_at ) ); ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
        <?php
    }

    private function contact_detail( $id ) {
        $contact = ContactService::get( $id );
        if ( ! $contact ) {
            wp_die( esc_html__( 'Contact not found.', 'bfcamel-crm' ) );
        }
        $emails = ContactService::get_emails( $id );
        $phones = ContactService::get_phones( $id );
        $custom = ContactService::get_custom_fields( $id );
        $consents = ConsentService::events_for_contact( $id );
        global $wpdb;
        $subs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . Schema::table( 'submissions' ) . " WHERE contact_id=%d ORDER BY submitted_at DESC,id DESC LIMIT 50", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title"><div><h1><?php echo esc_html( $contact->display_name ); ?></h1><p class="description"><?php echo esc_html( $contact->organization ); ?></p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts' ) ); ?>"><?php esc_html_e( 'Back', 'bfcamel-crm' ); ?></a></div>
            <div class="bfcamel-crm-editor-grid">
                <main>
                    <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Contact details', 'bfcamel-crm' ); ?></h2><dl class="bfcamel-crm-dl"><dt><?php esc_html_e( 'Email', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( $emails ? implode( ', ', wp_list_pluck( $emails, 'value' ) ) : '—' ); ?></dd><dt><?php esc_html_e( 'Phone', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( $phones ? implode( ', ', wp_list_pluck( $phones, 'value' ) ) : '—' ); ?></dd><?php foreach ( $custom as $key => $value ) : ?><dt><?php echo esc_html( $key ); ?></dt><dd><?php echo esc_html( $value ); ?></dd><?php endforeach; ?></dl></div>
                    <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Submissions', 'bfcamel-crm' ); ?></h2><table class="widefat striped"><thead><tr><th>ID</th><th><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Received', 'bfcamel-crm' ); ?></th></tr></thead><tbody><?php if ( ! $subs ) : ?><tr><td colspan="3">—</td></tr><?php endif; ?><?php foreach ( $subs as $sub ) : ?><tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . absint( $sub->id ) ) ); ?>">#<?php echo esc_html( $sub->id ); ?></a></td><td><?php echo esc_html( $sub->status ); ?></td><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sub->submitted_at ) ); ?></td></tr><?php endforeach; ?></tbody></table></div>
                </main>
                <aside><div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Consent history', 'bfcamel-crm' ); ?></h2><?php if ( ! $consents ) : ?><p>—</p><?php else : ?><ul class="bfcamel-crm-timeline"><?php foreach ( $consents as $event ) : ?><li><strong><?php echo esc_html( $event->consent_type ); ?></strong> — <?php echo esc_html( $event->status ); ?><br><small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->event_at ) ); ?></small></li><?php endforeach; ?></ul><?php endif; ?></div></aside>
            </div>
        </div>
        <?php
    }

    public function settings_page() {
        $this->guard( 'bfcamel_crm_manage_settings' );
        $legal    = get_option( 'bfcamel_crm_legal_documents', array() );
        $settings = get_option( 'bfcamel_crm_settings', array() );
        $saved = isset( $_GET['saved'] ) ? sanitize_key( $_GET['saved'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap bfcamel-crm-admin"><h1><?php esc_html_e( 'BfCamel CRM settings', 'bfcamel-crm' ); ?></h1><?php if ( '1' === $saved ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="bfcamel_crm_save_settings"><?php wp_nonce_field( 'bfcamel_crm_save_settings' ); ?>
            <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Legal documents', 'bfcamel-crm' ); ?></h2><p class="description"><?php esc_html_e( 'Links and versions are copied into consent evidence at submission time, so later edits do not rewrite history.', 'bfcamel-crm' ); ?></p>
                <?php $this->legal_row( 'personal_data_consent', __( 'Personal Data Processing Consent', 'bfcamel-crm' ), $legal ); ?>
                <?php $this->legal_row( 'privacy_policy', __( 'Privacy Policy', 'bfcamel-crm' ), $legal ); ?>
                <?php $this->legal_row( 'marketing_consent', __( 'Marketing / Information Messages Consent', 'bfcamel-crm' ), $legal ); ?>
            </div>
            <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Privacy', 'bfcamel-crm' ); ?></h2><p><label><input type="checkbox" name="settings[store_ip]" value="1" <?php checked( ! empty( $settings['store_ip'] ) ); ?>> <?php esc_html_e( 'Store visitor IP addresses as submission and consent evidence', 'bfcamel-crm' ); ?></label></p><p class="description"><?php esc_html_e( 'Disabled by default. User-Agent and source URL are still stored with submissions.', 'bfcamel-crm' ); ?></p><p><label><input type="checkbox" name="settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete all BfCamel CRM data when the plugin is uninstalled', 'bfcamel-crm' ); ?></label></p><p class="description"><?php esc_html_e( 'Keep this disabled unless you explicitly want uninstall.php to remove CRM tables and settings.', 'bfcamel-crm' ); ?></p></div>
            <?php submit_button(); ?>
        </form></div>
        <?php
    }

    public function save_settings() {
        $this->guard( 'bfcamel_crm_manage_settings' );
        check_admin_referer( 'bfcamel_crm_save_settings' );
        $posted_legal = isset( $_POST['legal'] ) && is_array( $_POST['legal'] ) ? wp_unslash( $_POST['legal'] ) : array();
        $legal = array();
        foreach ( array( 'personal_data_consent', 'privacy_policy', 'marketing_consent' ) as $type ) {
            $legal[ $type ] = array(
                'url'     => esc_url_raw( $posted_legal[ $type ]['url'] ?? '' ),
                'version' => sanitize_text_field( $posted_legal[ $type ]['version'] ?? '' ),
            );
        }
        update_option( 'bfcamel_crm_legal_documents', $legal, false );

        $settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
        update_option(
            'bfcamel_crm_settings',
            array(
                'store_ip'                 => ! empty( $settings['store_ip'] ),
                'delete_data_on_uninstall' => ! empty( $settings['delete_data_on_uninstall'] ),
            ),
            false
        );

        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-settings&saved=1' ) );
        exit;
    }

    private function field_types() {
        return array(
            'text'                  => __( 'Text', 'bfcamel-crm' ),
            'email'                 => __( 'Email', 'bfcamel-crm' ),
            'tel'                   => __( 'Phone', 'bfcamel-crm' ),
            'number'                => __( 'Number', 'bfcamel-crm' ),
            'date'                  => __( 'Date', 'bfcamel-crm' ),
            'textarea'              => __( 'Textarea', 'bfcamel-crm' ),
            'select'                => __( 'Select', 'bfcamel-crm' ),
            'radio'                 => __( 'Radio', 'bfcamel-crm' ),
            'checkbox'              => __( 'Checkboxes', 'bfcamel-crm' ),
            'hidden'                => __( 'Hidden', 'bfcamel-crm' ),
            'consent_personal_data' => __( 'Personal data consent', 'bfcamel-crm' ),
            'consent_marketing'     => __( 'Marketing consent', 'bfcamel-crm' ),
            'html'                  => __( 'Text / heading', 'bfcamel-crm' ),
        );
    }

    private function color_input( $key, $label, $settings ) {
        ?><p><label><?php echo esc_html( $label ); ?><input type="color" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>"></label></p><?php
    }

    private function legal_row( $key, $label, $legal ) {
        $doc = wp_parse_args( $legal[ $key ] ?? array(), array( 'url' => '', 'version' => '' ) );
        ?><div class="bfcamel-crm-legal-row"><div><strong><?php echo esc_html( $label ); ?></strong></div><label><?php esc_html_e( 'URL', 'bfcamel-crm' ); ?><input type="url" name="legal[<?php echo esc_attr( $key ); ?>][url]" value="<?php echo esc_attr( $doc['url'] ); ?>" placeholder="https://"></label><label><?php esc_html_e( 'Version', 'bfcamel-crm' ); ?><input type="text" name="legal[<?php echo esc_attr( $key ); ?>][version]" value="<?php echo esc_attr( $doc['version'] ); ?>" placeholder="2026-09-01"></label></div><?php
    }

    private function guard( $capability ) {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'bfcamel-crm' ) );
        }
    }
}
