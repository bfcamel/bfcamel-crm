<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Forms\Repository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorkflowPage {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct() {}

    public function assets( $hook ) {
        if ( false === strpos( (string) $hook, 'bfcamel-crm' ) ) return;
        wp_enqueue_script( 'bfcamel-crm-admin-04', BFCAMEL_CRM_URL . 'assets/admin-04.js', array( 'bfcamel-crm-admin' ), BFCAMEL_CRM_VERSION, true );
        wp_enqueue_style( 'bfcamel-crm-admin-04', BFCAMEL_CRM_URL . 'assets/admin-04.css', array( 'bfcamel-crm-admin' ), BFCAMEL_CRM_VERSION );
        wp_add_inline_style( 'bfcamel-crm-admin-04', $this->badge_styles() );

        $form_settings = array();
        if ( false !== strpos( (string) $hook, 'bfcamel-crm-forms' ) ) {
            $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $form = $id ? Repository::get( $id ) : null;
            $revision = $form ? Repository::current_revision( $form ) : null;
            $form_settings = $revision ? Repository::decode_settings( $revision ) : Repository::default_settings();
        }

        $submission_tags = array(); foreach ( TagService::all( 'submission' ) as $tag ) $submission_tags[] = array( 'id' => absint( $tag->id ), 'name' => (string) $tag->name );
        $contact_tags = array(); foreach ( TagService::all( 'contact' ) as $tag ) $contact_tags[] = array( 'id' => absint( $tag->id ), 'name' => (string) $tag->name );
        $forms_status = array(); foreach ( Repository::all() as $form ) $forms_status[ absint( $form->id ) ] = (string) $form->status;

        wp_localize_script( 'bfcamel-crm-admin-04', 'BfCamelCRM04', array(
            'statuses'       => WorkflowService::statuses(),
            'priorities'     => WorkflowService::priorities(),
            'formSettings'   => $form_settings,
            'submissionTags' => $submission_tags,
            'contactTags'    => $contact_tags,
            'formsStatus'    => $forms_status,
            'archiveFormUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=bfcamel_crm_archive_form&form_id=__ID__' ), 'bfcamel_crm_archive_form' ),
            'restoreFormUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=bfcamel_crm_restore_form&form_id=__ID__' ), 'bfcamel_crm_restore_form' ),
            'strings'        => array(
                'defaultStatus'  => __( 'Default status', 'bfcamel-crm' ),
                'defaultPriority'=> __( 'Default priority', 'bfcamel-crm' ),
                'fieldCssClass'  => __( 'Field CSS class', 'bfcamel-crm' ),
                'styleModeTheme' => __( 'Theme / unstyled', 'bfcamel-crm' ),
                'deleteForm'     => __( 'Delete form', 'bfcamel-crm' ),
                'restoreForm'    => __( 'Restore form', 'bfcamel-crm' ),
                'archived'       => __( 'Archived', 'bfcamel-crm' ),
                'tagPicker'      => __( 'Select tags', 'bfcamel-crm' ),
            ),
        ) );
    }

    public function legal_notice() {
        if ( ! current_user_can( 'bfcamel_crm_manage_settings' ) && ! current_user_can( 'manage_options' ) ) return;
        $missing = \BfCamel\CRM\Consent\ConsentService::missing_document_types();
        if ( ! $missing ) return;
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'BfCamel CRM: one or more legal document URLs are missing. Consent fields that depend on those documents are automatically hidden and no automatic consent event is recorded until the required URLs are configured.', 'bfcamel-crm' ) . '</p></div>';
    }

    public function page() {
        $this->guard();
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'workflow'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tabs = array(
            'workflow'   => __( 'Statuses & priorities', 'bfcamel-crm' ),
            'tags'       => __( 'Tags', 'bfcamel-crm' ),
            'automation' => __( 'Automation', 'bfcamel-crm' ),
            'css'        => __( 'Form CSS', 'bfcamel-crm' ),
        );
        if ( ! isset( $tabs[ $tab ] ) ) $tab = 'workflow';
        ?>
        <div class="wrap bfcamel-crm-admin">
            <h1><?php esc_html_e( 'CRM configuration', 'bfcamel-crm' ); ?></h1>
            <nav class="nav-tab-wrapper"><?php foreach ( $tabs as $key => $label ) : ?><a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-workflow&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
            <?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <?php if ( 'workflow' === $tab ) $this->workflow_tab(); elseif ( 'tags' === $tab ) $this->tags_tab(); elseif ( 'automation' === $tab ) $this->automation_tab(); else $this->css_tab(); ?>
        </div>
        <?php
    }

    private function workflow_tab() {
        $config = WorkflowService::config();
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="bfcamel_crm_save_workflow"><?php wp_nonce_field( 'bfcamel_crm_save_workflow' ); ?>
            <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Submission statuses', 'bfcamel-crm' ); ?></h2><p class="description"><?php esc_html_e( 'Rename statuses, add your own, disable unused values and choose the default status for new submissions. Existing submissions keep their stored status.', 'bfcamel-crm' ); ?></p><?php $this->definition_table( 'statuses', $config['statuses'] ); ?></div>
            <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Submission priorities', 'bfcamel-crm' ); ?></h2><p class="description"><?php esc_html_e( 'Rename priorities, add custom values and choose the default. Weight controls ordering: larger weights represent higher priority.', 'bfcamel-crm' ); ?></p><?php $this->definition_table( 'priorities', $config['priorities'] ); ?></div>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function definition_table( $key, $items ) {
        $rows = array_values( (array) $items );
        for ( $i = 0; $i < 4; $i++ ) $rows[] = array( 'slug'=>'', 'name'=>'', 'color'=>'#8c8f94', 'weight'=>( count( $rows ) + 1 ) * 10, 'enabled'=>1, 'default'=>0 );
        ?>
        <div class="bfcamel-crm-table-scroll"><table class="widefat striped bfcamel-crm-definition-table"><thead><tr><th><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Slug', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Color', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Order', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Enabled', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Default', 'bfcamel-crm' ); ?></th></tr></thead><tbody>
        <?php foreach ( $rows as $i => $item ) : $item = wp_parse_args( $item, array( 'slug'=>'','name'=>'','color'=>'#8c8f94','weight'=>0,'enabled'=>1,'default'=>0 ) ); ?>
            <tr><td><input type="hidden" name="workflow[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $i ); ?>][builtin]" value="<?php echo ! empty( $item['builtin'] ) ? '1' : '0'; ?>"><input type="text" name="workflow[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $item['name'] ); ?>"></td><td><input type="text" name="workflow[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $i ); ?>][slug]" value="<?php echo esc_attr( $item['slug'] ); ?>" <?php echo $item['slug'] ? 'readonly' : ''; ?>></td><td><input type="color" name="workflow[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $i ); ?>][color]" value="<?php echo esc_attr( $item['color'] ); ?>"></td><td><input type="number" name="workflow[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $i ); ?>][weight]" value="<?php echo esc_attr( $item['weight'] ); ?>"></td><td><input type="checkbox" name="workflow[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $i ); ?>][enabled]" value="1" <?php checked( ! empty( $item['enabled'] ) ); ?>></td><td><input type="radio" name="workflow[<?php echo esc_attr( $key ); ?>_default]" value="<?php echo esc_attr( $i ); ?>" <?php checked( ! empty( $item['default'] ) ); ?>></td></tr>
        <?php endforeach; ?></tbody></table></div>
        <?php
    }

    private function tags_tab() {
        $tags = TagService::catalog(); $rows = array_values( (array) $tags ); $rows[] = (object) array( 'id'=>0,'name'=>'','for_submissions'=>true,'for_contacts'=>true );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="bfcamel_crm_save_tag_catalog"><?php wp_nonce_field( 'bfcamel_crm_save_tag_catalog' ); ?>
            <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Tag catalog', 'bfcamel-crm' ); ?></h2><p class="description"><?php esc_html_e( 'Create tags once here. Submission and contact screens use this catalog instead of free-text tag entry.', 'bfcamel-crm' ); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Submissions', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Contacts', 'bfcamel-crm' ); ?></th><th></th></tr></thead><tbody>
            <?php foreach ( $rows as $i => $tag ) : ?><tr><td><input type="hidden" name="tags[<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $tag->id ); ?>"><input type="text" name="tags[<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $tag->name ); ?>" placeholder="<?php echo esc_attr__( 'Tag', 'bfcamel-crm' ); ?>"></td><td><input type="checkbox" name="tags[<?php echo esc_attr( $i ); ?>][submission]" value="1" <?php checked( ! empty( $tag->for_submissions ) ); ?>></td><td><input type="checkbox" name="tags[<?php echo esc_attr( $i ); ?>][contact]" value="1" <?php checked( ! empty( $tag->for_contacts ) ); ?>></td><td><?php if ( $tag->id ) : ?><a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bfcamel_crm_delete_tag&tag_id=' . absint( $tag->id ) ), 'bfcamel_crm_delete_tag_' . absint( $tag->id ) ) ); ?>"><?php esc_html_e( 'Delete', 'bfcamel-crm' ); ?></a><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php submit_button(); ?>
        </form>
        <?php
    }

    private function automation_tab() {
        $rules = WorkflowService::rules(); $fields = WorkflowService::field_catalog(); $priorities = WorkflowService::priorities();
        for ( $i = 0; $i < 5; $i++ ) $rules[] = array( 'id'=>'','form_id'=>0,'field'=>'','operator'=>'equals','value'=>'','priority'=>WorkflowService::default_priority(),'enabled'=>1 );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="bfcamel_crm_save_rules"><?php wp_nonce_field( 'bfcamel_crm_save_rules' ); ?>
            <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Priority rules', 'bfcamel-crm' ); ?></h2><p class="description"><?php esc_html_e( 'Rules are evaluated after the form default. Later matching rules override the priority chosen earlier.', 'bfcamel-crm' ); ?></p><div class="bfcamel-crm-table-scroll"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Field', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Condition', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Value', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Priority', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Enabled', 'bfcamel-crm' ); ?></th></tr></thead><tbody>
            <?php foreach ( $rules as $i => $rule ) : $current = absint( $rule['form_id'] ?? 0 ) . '|' . sanitize_key( $rule['field'] ?? '' ); ?><tr><td><input type="hidden" name="rules[<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $rule['id'] ?? '' ); ?>"><select name="rules[<?php echo esc_attr( $i ); ?>][target]"><option value="">—</option><?php foreach ( $fields as $field ) : $target = $field['form_id'] . '|' . $field['field']; ?><option value="<?php echo esc_attr( $target ); ?>" <?php selected( $current, $target ); ?>><?php echo esc_html( $field['form_name'] . ' — ' . $field['label'] . ' [' . $field['field'] . ']' ); ?></option><?php endforeach; ?></select></td><td><select name="rules[<?php echo esc_attr( $i ); ?>][operator]"><?php foreach ( array( 'equals'=>__( 'Equals', 'bfcamel-crm' ), 'not_equals'=>__( 'Does not equal', 'bfcamel-crm' ), 'contains'=>__( 'Contains', 'bfcamel-crm' ), 'filled'=>__( 'Is filled', 'bfcamel-crm' ) ) as $op => $label ) : ?><option value="<?php echo esc_attr( $op ); ?>" <?php selected( $rule['operator'] ?? 'equals', $op ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td><td><input type="text" name="rules[<?php echo esc_attr( $i ); ?>][value]" value="<?php echo esc_attr( $rule['value'] ?? '' ); ?>"></td><td><select name="rules[<?php echo esc_attr( $i ); ?>][priority]"><?php foreach ( $priorities as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $rule['priority'] ?? WorkflowService::default_priority(), $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td><td><input type="checkbox" name="rules[<?php echo esc_attr( $i ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>></td></tr><?php endforeach; ?>
            </tbody></table></div></div><?php submit_button(); ?>
        </form>
        <?php
    }

    private function css_tab() {
        ?>
        <div class="bfcamel-crm-panel bfcamel-crm-css-doc">
            <h2><?php esc_html_e( 'CSS-first form layout', 'bfcamel-crm' ); ?></h2>
            <p><?php esc_html_e( 'Choose Theme / unstyled in the form editor when the page builder or theme should control visual design. BfCamel CRM keeps only structural grid classes in that mode.', 'bfcamel-crm' ); ?></p>
            <h3><?php esc_html_e( 'Stable selectors', 'bfcamel-crm' ); ?></h3><pre><code>.bfcamel-form
.bfcamel-form-grid
.bfcamel-field
.bfcamel-col-12  /* 100% */
.bfcamel-col-6   /* 50% */
.bfcamel-col-4   /* 33% */
.bfcamel-col-3   /* 25% */
.bfcamel-field-key-contact_email
.bfcamel-control
.bfcamel-submit</code></pre>
            <h3><?php esc_html_e( 'Example for page-level CSS', 'bfcamel-crm' ); ?></h3><pre><code>.my-page-form {
  --bfcamel-gap: 24px;
  --bfcamel-row-gap: 18px;
}
.my-page-form .bfcamel-control {
  border: 1px solid #133641;
  border-radius: 18px;
  padding: 14px 16px;
}
.my-page-form .bfcamel-submit {
  background: #18434e;
  color: #fff;
  border-radius: 999px;
}</code></pre>
            <p><?php esc_html_e( 'Add a custom wrapper class in the form editor (for example my-page-form), then place CSS in the page editor, Elementor Custom CSS, site CSS, or the theme. The same form can look different on different pages by scoping rules to the page container.', 'bfcamel-crm' ); ?></p>
        </div>
        <?php
    }

    public function save_workflow() {
        $this->guard(); check_admin_referer( 'bfcamel_crm_save_workflow' );
        $posted = isset( $_POST['workflow'] ) && is_array( $_POST['workflow'] ) ? wp_unslash( $_POST['workflow'] ) : array();
        foreach ( array( 'statuses','priorities' ) as $key ) {
            $default_index = isset( $posted[ $key . '_default' ] ) ? absint( $posted[ $key . '_default' ] ) : null;
            unset( $posted[ $key . '_default' ] );
            if ( isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ) foreach ( $posted[ $key ] as $i => &$row ) $row['default'] = null !== $default_index && absint( $i ) === $default_index ? 1 : 0;
            unset( $row );
        }
        WorkflowService::save_config( $posted ); $this->redirect( 'workflow' );
    }

    public function save_rules() {
        $this->guard(); check_admin_referer( 'bfcamel_crm_save_rules' );
        $posted = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : array(); $rows = array();
        foreach ( $posted as $row ) { $target = isset( $row['target'] ) ? explode( '|', (string) $row['target'], 2 ) : array(); $row['form_id'] = absint( $target[0] ?? 0 ); $row['field'] = sanitize_key( $target[1] ?? '' ); $rows[] = $row; }
        WorkflowService::save_rules( $rows ); $this->redirect( 'automation' );
    }

    public function save_tags() {
        $this->guard(); check_admin_referer( 'bfcamel_crm_save_tag_catalog' );
        $rows = isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : array(); $result = TagService::save_catalog( $rows );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) ); $this->redirect( 'tags' );
    }

    public function delete_tag() {
        $this->guard(); $id = isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0; check_admin_referer( 'bfcamel_crm_delete_tag_' . $id ); $result=TagService::delete_catalog_tag( $id ); if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); if(!$result)wp_die(esc_html__( 'Could not remove a tag.', 'bfcamel-crm' )); $this->redirect( 'tags' );
    }

    public function archive_form() {
        $this->guard_forms(); $id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; check_admin_referer( 'bfcamel_crm_archive_form' ); if(!Repository::archive( $id, get_current_user_id() ))wp_die(esc_html__( 'The form could not be updated.', 'bfcamel-crm' )); wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-forms&archived=1' ) ); exit;
    }
    public function restore_form() {
        $this->guard_forms(); $id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; check_admin_referer( 'bfcamel_crm_restore_form' ); if(!Repository::restore( $id, get_current_user_id() ))wp_die(esc_html__( 'The form could not be updated.', 'bfcamel-crm' )); wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-forms&restored=1' ) ); exit;
    }

    private function redirect( $tab ) { wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-workflow&tab=' . $tab . '&saved=1' ) ); exit; }
    private function guard() { if ( ! current_user_can( 'bfcamel_crm_manage_settings' ) && ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to access this page.', 'bfcamel-crm' ) ); }
    private function guard_forms() { if ( ! current_user_can( 'bfcamel_crm_manage_forms' ) ) wp_die( esc_html__( 'You do not have permission to access this page.', 'bfcamel-crm' ) ); }

    private function badge_styles() {
        $rules = array();
        foreach ( array( 'status'=>'bfcamel-crm-badge', 'priority'=>'bfcamel-crm-priority' ) as $type => $class ) {
            foreach ( WorkflowService::definitions( $type, false ) as $item ) {
                $slug = sanitize_html_class( $item['slug'] );
                $color = WorkflowService::color( $type, $item['slug'] );
                if ( $slug && $color ) $rules[] = '.' . $class . '--' . $slug . '{box-shadow:inset 4px 0 0 ' . $color . ';}';
            }
        }
        return implode( '', $rules );
    }
}
