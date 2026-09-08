<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SubmissionDetailPage {
    public static function render( $id ) {
        $row = SubmissionService::get( $id );
        if ( ! $row ) {
            wp_die( esc_html__( 'Submission not found.', 'bfcamel-crm' ) );
        }

        $form = Repository::get( $row->form_id );
        $revision = Repository::get_revision( $row->revision_id );
        $schema = Repository::decode_schema( $revision );
        $payload = json_decode( $row->payload_json, true );
        $payload = is_array( $payload ) ? $payload : array();
        $statuses = SubmissionService::statuses();
        $priorities = SubmissionService::priorities();
        $assignees = SubmissionService::assignees();
        $submission_tags = TagService::names_for_submission( $id );
        $all_tags = TagService::all();
        $activity = SubmissionService::activity( $id );
        $updated = isset( $_GET['updated'] ) ? sanitize_key( $_GET['updated'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div>
                    <h1><?php echo esc_html( sprintf( __( 'Submission #%d', 'bfcamel-crm' ), $id ) ); ?></h1>
                    <p class="description"><?php echo esc_html( $form ? $form->name : '#' . $row->form_id ); ?> · UUID <code><?php echo esc_html( $row->submission_uuid ); ?></code></p>
                </div>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions' ) ); ?>"><?php esc_html_e( 'Back', 'bfcamel-crm' ); ?></a>
            </div>

            <?php if ( '1' === $updated ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission updated.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>

            <div class="bfcamel-crm-editor-grid">
                <main>
                    <div class="bfcamel-crm-panel">
                        <h2><?php esc_html_e( 'Submitted data', 'bfcamel-crm' ); ?></h2>
                        <dl class="bfcamel-crm-dl">
                            <?php foreach ( $schema as $field ) : ?>
                                <?php
                                $name = $field['name'] ?? '';
                                if ( ! $name || 'html' === ( $field['type'] ?? '' ) ) {
                                    continue;
                                }
                                $value = $payload[ $name ] ?? '';
                                ?>
                                <dt><?php echo esc_html( $field['label'] ?: $name ); ?></dt>
                                <dd><?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : (string) $value ); ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    </div>

                    <div class="bfcamel-crm-panel">
                        <h2><?php esc_html_e( 'Submission history', 'bfcamel-crm' ); ?></h2>
                        <?php if ( ! $activity ) : ?>
                            <p class="description"><?php esc_html_e( 'No history entries yet.', 'bfcamel-crm' ); ?></p>
                        <?php else : ?>
                            <ul class="bfcamel-crm-activity">
                                <?php foreach ( $activity as $event ) : ?>
                                    <li>
                                        <div class="bfcamel-crm-activity__dot"></div>
                                        <div>
                                            <strong><?php echo esc_html( $event->message ); ?></strong>
                                            <div class="bfcamel-crm-activity__meta"><?php echo esc_html( $event->actor_name ?: __( 'System', 'bfcamel-crm' ) ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->created_at ) ); ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </main>

                <aside>
                    <div class="bfcamel-crm-panel bfcamel-crm-sticky">
                        <h2><?php esc_html_e( 'CRM', 'bfcamel-crm' ); ?></h2>
                        <p><strong><?php esc_html_e( 'Contact sync', 'bfcamel-crm' ); ?>:</strong> <?php echo esc_html( $row->contact_sync_status ); ?></p>
                        <?php if ( $row->contact_id ) : ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . absint( $row->contact_id ) ) ); ?>"><?php esc_html_e( 'Open contact', 'bfcamel-crm' ); ?></a></p><?php endif; ?>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="bfcamel_crm_update_submission">
                            <input type="hidden" name="submission_id" value="<?php echo esc_attr( $id ); ?>">
                            <?php wp_nonce_field( 'bfcamel_crm_update_submission_' . $id ); ?>

                            <p><label><strong><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></strong><select name="status"><?php foreach ( $statuses as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row->status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><strong><?php esc_html_e( 'Priority', 'bfcamel-crm' ); ?></strong><select name="priority"><?php foreach ( $priorities as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row->priority ?: 'normal', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><strong><?php esc_html_e( 'Responsible', 'bfcamel-crm' ); ?></strong><select name="assigned_to"><option value="0"><?php esc_html_e( 'Unassigned', 'bfcamel-crm' ); ?></option><?php foreach ( $assignees as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( absint( $row->assigned_to ), absint( $user->ID ) ); ?>><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><strong><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></strong><input type="text" name="tags" value="<?php echo esc_attr( implode( ', ', $submission_tags ) ); ?>" list="bfcamel-crm-tags-list" placeholder="<?php echo esc_attr__( 'e.g. urgent, transport, volunteer', 'bfcamel-crm' ); ?>"></label></p>
                            <datalist id="bfcamel-crm-tags-list"><?php foreach ( $all_tags as $tag ) : ?><option value="<?php echo esc_attr( $tag->name ); ?>"><?php endforeach; ?></datalist>
                            <p class="description"><?php esc_html_e( 'Separate tags with commas.', 'bfcamel-crm' ); ?></p>
                            <?php submit_button( __( 'Update', 'bfcamel-crm' ), 'primary', 'submit', false ); ?>
                        </form>
                    </div>

                    <div class="bfcamel-crm-panel">
                        <h2><?php esc_html_e( 'Evidence', 'bfcamel-crm' ); ?></h2>
                        <p><strong><?php esc_html_e( 'Form revision', 'bfcamel-crm' ); ?>:</strong> <?php echo esc_html( $revision ? $revision->version : $row->revision_id ); ?></p>
                        <p><strong><?php esc_html_e( 'Source URL', 'bfcamel-crm' ); ?>:</strong><br><code><?php echo esc_html( $row->source_url ); ?></code></p>
                        <p><strong><?php esc_html_e( 'IP stored', 'bfcamel-crm' ); ?>:</strong> <?php echo $row->source_ip ? esc_html( $row->source_ip ) : esc_html__( 'No', 'bfcamel-crm' ); ?></p>
                    </div>
                </aside>
            </div>
        </div>
        <?php
    }

    public static function update() {
        $id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        check_admin_referer( 'bfcamel_crm_update_submission_' . $id );

        $current = SubmissionService::get( $id );
        if ( ! $current ) {
            wp_die( esc_html__( 'Submission not found.', 'bfcamel-crm' ) );
        }

        $statuses = SubmissionService::statuses();
        $status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'new';
        if ( ! isset( $statuses[ $status ] ) ) {
            $status = 'new';
        }

        $priorities = SubmissionService::priorities();
        $priority = isset( $_POST['priority'] ) ? sanitize_key( $_POST['priority'] ) : 'normal';
        if ( ! isset( $priorities[ $priority ] ) ) {
            $priority = 'normal';
        }

        $assigned_to = isset( $_POST['assigned_to'] ) ? absint( $_POST['assigned_to'] ) : 0;
        if ( $assigned_to ) {
            $allowed_assignees = array_map( 'absint', wp_list_pluck( SubmissionService::assignees(), 'ID' ) );
            if ( ! in_array( $assigned_to, $allowed_assignees, true ) ) {
                $assigned_to = 0;
            }
        }

        $raw_tags = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
        $old_tags = TagService::names_for_submission( $id );

        global $wpdb;
        $wpdb->update(
            Schema::table( 'submissions' ),
            array(
                'status'      => $status,
                'priority'    => $priority,
                'assigned_to' => $assigned_to,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%d' ),
            array( '%d' )
        );

        $user_id = get_current_user_id();
        if ( (string) $current->status !== $status ) {
            Schema::log( 'submission', $id, 'status_changed', __( 'Submission status changed.', 'bfcamel-crm' ), array( 'from' => $current->status, 'to' => $status ), $user_id );
        }
        if ( (string) ( $current->priority ?: 'normal' ) !== $priority ) {
            Schema::log( 'submission', $id, 'priority_changed', __( 'Submission priority changed.', 'bfcamel-crm' ), array( 'from' => $current->priority, 'to' => $priority ), $user_id );
        }
        if ( absint( $current->assigned_to ) !== $assigned_to ) {
            Schema::log( 'submission', $id, 'assignee_changed', __( 'Submission assignee changed.', 'bfcamel-crm' ), array( 'from' => absint( $current->assigned_to ), 'to' => $assigned_to ), $user_id );
        }

        $new_tags = wp_list_pluck( TagService::sync_submission( $id, $raw_tags ), 'name' );
        $old_compare = $old_tags;
        $new_compare = $new_tags;
        sort( $old_compare );
        sort( $new_compare );
        if ( $old_compare !== $new_compare ) {
            Schema::log( 'submission', $id, 'tags_changed', __( 'Submission tags changed.', 'bfcamel-crm' ), array( 'from' => $old_tags, 'to' => $new_tags ), $user_id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . $id . '&updated=1' ) );
        exit;
    }
}
