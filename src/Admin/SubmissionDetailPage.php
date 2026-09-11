<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\ActivityFormatter;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\NoteService;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Support\Request;

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
        $can_edit = current_user_can( 'bfcamel_crm_edit_submissions' );
        $statuses = $can_edit ? SubmissionService::statuses() : array();
        $priorities = $can_edit ? SubmissionService::priorities() : array();
        if ( $can_edit && ! isset( $statuses[ $row->status ] ) ) $statuses[ $row->status ] = SubmissionService::status_label( $row->status );
        $current_priority = $row->priority ?: WorkflowService::default_priority();
        if ( $can_edit && ! isset( $priorities[ $current_priority ] ) ) $priorities[ $current_priority ] = SubmissionService::priority_label( $current_priority );
        $assignees = $can_edit ? SubmissionService::assignees() : array();
        $submission_tags = TagService::names_for_submission( $id );
        $all_tags = $can_edit ? TagService::all() : array();
        $activity = SubmissionService::activity( $id );
        $notes = NoteService::for_entity( 'submission', $id );
        $updated = Request::get_key( 'updated' );
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div>
                    <h1><?php
                        /* translators: %d: submission ID. */
                        echo esc_html( sprintf( __( 'Submission #%d', 'bfcamel-crm' ), $id ) );
                    ?></h1>
                    <p class="description"><?php echo esc_html( $form ? $form->name : '#' . $row->form_id ); ?> · UUID <code><?php echo esc_html( $row->submission_uuid ); ?></code></p>
                </div>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions' ) ); ?>"><?php esc_html_e( 'Back', 'bfcamel-crm' ); ?></a>
            </div>

            <?php if ( '1' === $updated ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission updated.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <?php if ( Request::get_flag( 'note_added' ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Internal note added.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>

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
                        <h2><?php esc_html_e( 'Internal notes', 'bfcamel-crm' ); ?></h2>
                        <?php if ( $can_edit ) : ?>
                            <form class="bfcamel-crm-note-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="bfcamel_crm_add_submission_note">
                                <input type="hidden" name="submission_id" value="<?php echo esc_attr( $id ); ?>">
                                <?php wp_nonce_field( 'bfcamel_crm_add_submission_note_' . $id ); ?>
                                <textarea name="note" rows="4" required placeholder="<?php echo esc_attr__( 'Add an internal note…', 'bfcamel-crm' ); ?>"></textarea>
                                <button class="button button-primary" type="submit"><?php esc_html_e( 'Add note', 'bfcamel-crm' ); ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ( ! $notes ) : ?><p class="description"><?php esc_html_e( 'No internal notes yet.', 'bfcamel-crm' ); ?></p><?php else : ?><ul class="bfcamel-crm-notes"><?php foreach ( $notes as $note ) : ?><li><div class="bfcamel-crm-note__body"><?php echo nl2br( esc_html( $note->note_text ) ); ?></div><div class="bfcamel-crm-note__meta"><?php echo esc_html( $note->actor_name ?: __( 'System', 'bfcamel-crm' ) ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $note->created_at ) ); ?></div></li><?php endforeach; ?></ul><?php endif; ?>
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
                                            <strong><?php echo esc_html( ActivityFormatter::message( $event ) ); ?></strong>
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
                        <p><strong><?php esc_html_e( 'Contact sync', 'bfcamel-crm' ); ?>:</strong> <?php echo esc_html( ActivityFormatter::sync_label( $row->contact_sync_status ) ); ?></p>
                        <?php if ( $row->contact_id && current_user_can( 'bfcamel_crm_manage_contacts' ) ) : ?><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . absint( $row->contact_id ) ) ); ?>"><?php esc_html_e( 'Open contact', 'bfcamel-crm' ); ?></a></p><?php endif; ?>

                        <?php if ( $can_edit ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="bfcamel_crm_update_submission">
                            <input type="hidden" name="submission_id" value="<?php echo esc_attr( $id ); ?>">
                            <?php wp_nonce_field( 'bfcamel_crm_update_submission_' . $id ); ?>

                            <p><label><strong><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></strong><select name="status"><?php foreach ( $statuses as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row->status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><strong><?php esc_html_e( 'Priority', 'bfcamel-crm' ); ?></strong><select name="priority"><?php foreach ( $priorities as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_priority, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><strong><?php esc_html_e( 'Responsible', 'bfcamel-crm' ); ?></strong><select name="assigned_to"><option value="0"><?php esc_html_e( 'Unassigned', 'bfcamel-crm' ); ?></option><?php foreach ( $assignees as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( absint( $row->assigned_to ), absint( $user->ID ) ); ?>><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><strong><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></strong><input type="text" name="tags" value="<?php echo esc_attr( implode( ', ', $submission_tags ) ); ?>" list="bfcamel-crm-tags-list" placeholder="<?php echo esc_attr__( 'e.g. urgent, transport, volunteer', 'bfcamel-crm' ); ?>"></label></p>
                            <datalist id="bfcamel-crm-tags-list"><?php foreach ( $all_tags as $tag ) : ?><option value="<?php echo esc_attr( $tag->name ); ?>"><?php endforeach; ?></datalist>
                            <p class="description"><?php esc_html_e( 'Separate tags with commas.', 'bfcamel-crm' ); ?></p>
                            <?php submit_button( __( 'Update', 'bfcamel-crm' ), 'primary', 'submit', false ); ?>
                        </form>
                        <?php else : ?>
                            <dl class="bfcamel-crm-dl">
                                <dt><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( SubmissionService::status_label( $row->status ) ); ?></dd>
                                <dt><?php esc_html_e( 'Priority', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( SubmissionService::priority_label( $current_priority ) ); ?></dd>
                                <dt><?php esc_html_e( 'Responsible', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( $row->assignee_name ?: __( 'Unassigned', 'bfcamel-crm' ) ); ?></dd>
                                <dt><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( $submission_tags ? implode( ', ', $submission_tags ) : '—' ); ?></dd>
                            </dl>
                            <p class="description"><?php esc_html_e( 'You have read-only access to this submission.', 'bfcamel-crm' ); ?></p>
                        <?php endif; ?>
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
        $id = Request::post_id( 'submission_id' );
        check_admin_referer( 'bfcamel_crm_update_submission_' . $id );

        if ( ! Schema::begin_transaction() ) {
            wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }

        $current = SubmissionService::get( $id, true );
        if ( ! $current ) {
            Schema::rollback();
            wp_die( esc_html__( 'Submission not found.', 'bfcamel-crm' ) );
        }

        $statuses = SubmissionService::statuses();
        $status = Request::post_key( 'status', WorkflowService::default_status() );
        if ( ! isset( $statuses[ $status ] ) && $status !== sanitize_key( $current->status ) ) $status = WorkflowService::default_status();

        $priorities = SubmissionService::priorities();
        $priority = Request::post_key( 'priority', WorkflowService::default_priority() );
        if ( ! isset( $priorities[ $priority ] ) && $priority !== sanitize_key( $current->priority ) ) $priority = WorkflowService::default_priority();

        $assigned_to = Request::post_id( 'assigned_to' );
        if ( $assigned_to ) {
            $allowed_assignees = array_map( 'absint', wp_list_pluck( SubmissionService::assignees(), 'ID' ) );
            if ( ! in_array( $assigned_to, $allowed_assignees, true ) ) {
                $assigned_to = 0;
            }
        }

        $raw_tags = Request::post_textarea( 'tags' );
        $old_tags = TagService::names_for_submission( $id );

        global $wpdb;
        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional write to the plugin's custom submissions table.
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
        if ( false === $updated ) {
            self::rollback_error( __( 'The submission could not be updated.', 'bfcamel-crm' ) );
        }

        $user_id = get_current_user_id();
        if ( (string) $current->status !== $status && ! Schema::log( 'submission', $id, 'status_changed', 'Submission status changed.', array( 'from' => $current->status, 'to' => $status ), $user_id ) ) {
            self::rollback_error( __( 'Could not record submission history.', 'bfcamel-crm' ) );
        }
        $previous_priority = $current->priority ?: WorkflowService::default_priority();
        if ( (string) $previous_priority !== $priority && ! Schema::log( 'submission', $id, 'priority_changed', 'Submission priority changed.', array( 'from' => $previous_priority, 'to' => $priority ), $user_id ) ) {
            self::rollback_error( __( 'Could not record submission history.', 'bfcamel-crm' ) );
        }
        if ( absint( $current->assigned_to ) !== $assigned_to && ! Schema::log( 'submission', $id, 'assignee_changed', 'Submission assignee changed.', array( 'from' => absint( $current->assigned_to ), 'to' => $assigned_to ), $user_id ) ) {
            self::rollback_error( __( 'Could not record submission history.', 'bfcamel-crm' ) );
        }

        $tag_result = TagService::sync_submission( $id, $raw_tags );
        if ( is_wp_error( $tag_result ) ) {
            self::rollback_error( $tag_result->get_error_message() );
        }
        $new_tags = wp_list_pluck( $tag_result, 'name' );
        $old_compare = $old_tags;
        $new_compare = $new_tags;
        sort( $old_compare );
        sort( $new_compare );
        if ( $old_compare !== $new_compare && ! Schema::log( 'submission', $id, 'tags_changed', 'Submission tags changed.', array( 'from' => $old_tags, 'to' => $new_tags ), $user_id ) ) {
            self::rollback_error( __( 'Could not record submission history.', 'bfcamel-crm' ) );
        }

        if ( ! Schema::commit() ) {
            self::rollback_error( __( 'The submission transaction could not be committed.', 'bfcamel-crm' ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . $id . '&updated=1' ) );
        exit;
    }

    private static function rollback_error( $message ) {
        Schema::rollback();
        wp_die( esc_html( $message ), esc_html__( 'Could not update submission', 'bfcamel-crm' ), array( 'back_link' => true ) );
    }
}
