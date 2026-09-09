<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\Access\RoleManager;
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

    public function assets( $hook ) {
        if ( false === strpos( (string) $hook, 'bfcamel-crm' ) ) {
            return;
        }

        wp_enqueue_style( 'bfcamel-crm-admin', BFCAMEL_CRM_URL . 'assets/admin.css', array(), BFCAMEL_CRM_VERSION );
        wp_enqueue_style( 'bfcamel-crm-admin-03', BFCAMEL_CRM_URL . 'assets/admin-03.css', array( 'bfcamel-crm-admin' ), BFCAMEL_CRM_VERSION );
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
                        'field'               => __( 'Field', 'bfcamel-crm' ),
                        'remove'              => __( 'Remove', 'bfcamel-crm' ),
                        'moveUp'              => __( 'Move up', 'bfcamel-crm' ),
                        'moveDown'            => __( 'Move down', 'bfcamel-crm' ),
                        'label'               => __( 'Label / text', 'bfcamel-crm' ),
                        'key'                 => __( 'Field key', 'bfcamel-crm' ),
                        'placeholder'         => __( 'Placeholder / hidden value', 'bfcamel-crm' ),
                        'required'            => __( 'Required', 'bfcamel-crm' ),
                        'width'               => __( 'Width', 'bfcamel-crm' ),
                        'mapping'             => __( 'CRM mapping', 'bfcamel-crm' ),
                        'customKey'           => __( 'Custom field key', 'bfcamel-crm' ),
                        'options'             => __( 'Options, one per line', 'bfcamel-crm' ),
                        'submissionOnly'      => __( 'Submission only', 'bfcamel-crm' ),
                        'contactName'         => __( 'Contact: name', 'bfcamel-crm' ),
                        'contactEmail'        => __( 'Contact: email', 'bfcamel-crm' ),
                        'contactPhone'        => __( 'Contact: phone', 'bfcamel-crm' ),
                        'contactOrganization' => __( 'Contact: organization', 'bfcamel-crm' ),
                        'contactCustom'       => __( 'Contact: custom field', 'bfcamel-crm' ),
                        'personalDataDefault' => __( 'I consent to {personal_data_consent} and confirm that I have read the {privacy_policy}.', 'bfcamel-crm' ),
                        'marketingDefault'    => __( 'I consent to receive informational and marketing messages under the {marketing_consent}.', 'bfcamel-crm' ),
                        'sectionTitle'        => __( 'Section title', 'bfcamel-crm' ),
                        'sectionHelp'         => __( 'Add explanatory text here.', 'bfcamel-crm' ),
                    ),
                    'types' => $this->field_types(),
                )
            );
        }
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

    /** Backward-compatible proxy for integrations using the old controller. */
    public function submissions_page() {
        SubmissionsPage::instance()->page();
    }

    /** Backward-compatible proxy for the old admin-post callback. */
    public function update_submission() {
        SubmissionsPage::instance()->update_submission();
    }

    /** Backward-compatible proxy for integrations using the old controller. */
    public function contacts_page() {
        ContactsPage::instance()->page();
    }

    public function settings_page() {
        $this->guard_settings();
        $legal = get_option( 'bfcamel_crm_legal_documents', array() );
        $settings = get_option( 'bfcamel_crm_settings', array() );
        $permissions = RoleManager::permissions();
        $roles = wp_roles();
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
            <div class="bfcamel-crm-panel bfcamel-crm-panel--table">
                <div class="bfcamel-crm-panel__head bfcamel-crm-panel__head--padded"><div><h2><?php esc_html_e( 'Role permissions', 'bfcamel-crm' ); ?></h2><p class="description"><?php esc_html_e( 'Choose which CRM areas each WordPress role can use. New roles receive all main CRM permissions by default; settings remain administrator-only.', 'bfcamel-crm' ); ?></p></div></div>
                <div class="bfcamel-crm-table-scroll"><table class="widefat striped bfcamel-crm-table bfcamel-crm-permissions"><thead><tr><th><?php esc_html_e( 'Role', 'bfcamel-crm' ); ?></th><?php foreach ( RoleManager::managed_capabilities() as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr></thead><tbody>
                <?php if ( $roles ) : foreach ( $roles->roles as $role_slug => $role_data ) : ?><tr><th scope="row"><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></th><?php foreach ( RoleManager::managed_capabilities() as $capability => $label ) : ?><td><label><span class="screen-reader-text"><?php echo esc_html( $label ); ?></span><input type="checkbox" name="role_permissions[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $capability ); ?>]" value="1" <?php checked( 'administrator' === $role_slug || ! empty( $permissions[ $role_slug ][ $capability ] ) ); ?> <?php disabled( 'administrator' === $role_slug ); ?>></label></td><?php endforeach; ?></tr><?php endforeach; endif; ?>
                </tbody></table></div>
            </div>
            <?php submit_button(); ?>
        </form></div>
        <?php
    }

    public function save_settings() {
        $this->guard_settings();
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

        $posted_permissions = isset( $_POST['role_permissions'] ) && is_array( $_POST['role_permissions'] ) ? wp_unslash( $_POST['role_permissions'] ) : array();
        $saved_permissions = RoleManager::save( $posted_permissions );
        if ( is_wp_error( $saved_permissions ) ) {
            wp_die( esc_html( $saved_permissions->get_error_message() ), esc_html__( 'Could not save role permissions', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }

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
        if ( ! Schema::is_current() ) {
            wp_die( esc_html( Schema::readiness_message() ), esc_html__( 'CRM database update required', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
    }

    private function guard_settings() {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'bfcamel_crm_manage_settings' ) ) {
            wp_die( esc_html__( 'Only administrators can access CRM settings.', 'bfcamel-crm' ) );
        }
    }
}
