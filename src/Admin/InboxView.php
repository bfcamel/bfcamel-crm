<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Notifications\Notifications;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InboxView {
    public static function list_url( $filters = array(), $page = 1 ) {
        $args = array( 'page' => 'bfcamel-crm-submissions', 's' => $filters['search'] ?? '', 'paged' => max( 1, absint( $page ) ) );
        foreach ( array( 'view', 'status', 'priority', 'assigned_to', 'form_id', 'tag_id', 'date_from', 'date_to' ) as $key ) if ( isset( $filters[ $key ] ) && '' !== (string) $filters[ $key ] ) $args[ $key ] = $filters[ $key ];
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    public static function render( $result, $filters ) {
        $view = $filters['view'];
        $can_edit = current_user_can( 'bfcamel_crm_edit_submissions' );
        $extra = '' !== $filters['status'] || '' !== $filters['priority'] || '' !== $filters['assigned_to'] || $filters['form_id'] || $filters['tag_id'] || $filters['date_from'] || $filters['date_to'];
        $list_url = self::list_url( $filters, $result['page'] );
        ?>
        <div class="wrap bfcamel-crm-admin bfcamel-crm-inbox" data-bfcamel-inbox>
            <div class="bfcamel-crm-page-title"><div><h1><?php esc_html_e( 'Submissions', 'bfcamel-crm' ); ?></h1><p class="description"><?php esc_html_e( 'Read the request, take ownership and keep the next action clear.', 'bfcamel-crm' ); ?></p></div>
                <?php if ( current_user_can( 'bfcamel_crm_export_data' ) ) : ?><details class="bfcamel-crm-more"><summary><?php esc_html_e( 'More', 'bfcamel-crm' ); ?></summary><div>
                    <?php foreach ( array( 'csv' => __( 'Export CSV', 'bfcamel-crm' ), 'xlsx' => __( 'Export XLSX', 'bfcamel-crm' ) ) as $format => $label ) : $args = array_merge( array( 'action' => 'bfcamel_crm_export', 'type' => 'submissions', 'format' => $format, 's' => $filters['search'] ), array_diff_key( $filters, array( 'search' => true ) ) ); ?>
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'bfcamel_crm_export_submissions_' . $format ) ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endforeach; ?>
                </div></details><?php endif; ?>
            </div>
            <nav class="bfcamel-crm-quick-views" aria-label="<?php esc_attr_e( 'Submission views', 'bfcamel-crm' ); ?>">
                <?php foreach ( array( 'attention' => __( 'Needs attention', 'bfcamel-crm' ), 'mine' => __( 'My submissions', 'bfcamel-crm' ), 'all' => __( 'All submissions', 'bfcamel-crm' ) ) as $key => $label ) : ?><a class="button<?php echo $key === $view ? ' button-primary' : ''; ?>" <?php if ( $key === $view ) echo 'aria-current="page"'; ?> href="<?php echo esc_url( self::list_url( array( 'view' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
            </nav>
            <form method="get" class="bfcamel-crm-inbox-search"><input type="hidden" name="page" value="bfcamel-crm-submissions"><input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
                <div class="bfcamel-crm-search-row"><label class="screen-reader-text" for="bfcamel-crm-search"><?php esc_html_e( 'Search submissions', 'bfcamel-crm' ); ?></label><input id="bfcamel-crm-search" type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name, phone or request contents', 'bfcamel-crm' ); ?>"><button class="button" type="submit"><?php esc_html_e( 'Search', 'bfcamel-crm' ); ?></button></div>
                <details class="bfcamel-crm-extra-filters" <?php if ( $extra ) echo 'open'; ?>><summary><?php esc_html_e( 'Filters', 'bfcamel-crm' ); ?><?php if ( $extra ) : ?> · <?php esc_html_e( 'Active', 'bfcamel-crm' ); ?><?php endif; ?></summary><div class="bfcamel-crm-filters">
                    <label><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?><select name="status"><option value=""><?php esc_html_e( 'All statuses', 'bfcamel-crm' ); ?></option><?php foreach ( SubmissionService::statuses() as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['status'], $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e( 'Priority', 'bfcamel-crm' ); ?><select name="priority"><option value=""><?php esc_html_e( 'All priorities', 'bfcamel-crm' ); ?></option><?php foreach ( SubmissionService::priorities() as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['priority'], $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e( 'Form', 'bfcamel-crm' ); ?><select name="form_id"><option value="0"><?php esc_html_e( 'All forms', 'bfcamel-crm' ); ?></option><?php foreach ( Repository::all() as $form ) : ?><option value="<?php echo absint( $form->id ); ?>" <?php selected( $filters['form_id'], $form->id ); ?>><?php echo esc_html( $form->name ); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e( 'Responsible', 'bfcamel-crm' ); ?><select name="assigned_to"><option value=""><?php esc_html_e( 'All responsible employees', 'bfcamel-crm' ); ?></option><option value="0" <?php selected( $filters['assigned_to'], '0' ); ?>><?php esc_html_e( 'Unassigned', 'bfcamel-crm' ); ?></option><?php foreach ( SubmissionService::assignees() as $user ) : ?><option value="<?php echo absint( $user->ID ); ?>" <?php selected( $filters['assigned_to'], (string) $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e( 'Tag', 'bfcamel-crm' ); ?><select name="tag_id"><option value="0"><?php esc_html_e( 'All tags', 'bfcamel-crm' ); ?></option><?php foreach ( TagService::all( 'submission' ) as $tag ) : ?><option value="<?php echo absint( $tag->id ); ?>" <?php selected( $filters['tag_id'], $tag->id ); ?>><?php echo esc_html( $tag->name ); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e( 'From', 'bfcamel-crm' ); ?><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label><label><?php esc_html_e( 'To', 'bfcamel-crm' ); ?><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
                    <button class="button" type="submit"><?php esc_html_e( 'Filter', 'bfcamel-crm' ); ?></button><a href="<?php echo esc_url( self::list_url( array( 'view' => $view ) ) ); ?>"><?php esc_html_e( 'Reset', 'bfcamel-crm' ); ?></a>
                </div></details>
            </form>
            <?php if ( Request::get_flag( 'bulk_updated' ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Selected submissions updated.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <?php if ( Request::get_flag( 'deleted' ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted permanently.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <div class="bfcamel-crm-inbox-layout"><div class="bfcamel-crm-inbox-list">
                <?php self::pagination($result,'top'); ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bfcamel-crm-bulk-form"><input type="hidden" name="action" value="bfcamel_crm_bulk_submissions"><?php wp_nonce_field( 'bfcamel_crm_bulk_submissions' ); ?>
                    <?php if ( $can_edit ) : ?><div class="bfcamel-crm-bulk-bar" hidden><span data-bfcamel-selected-count></span><select name="bulk_action" aria-label="<?php esc_attr_e( 'Bulk actions', 'bfcamel-crm' ); ?>"><option value=""><?php esc_html_e( 'Bulk actions', 'bfcamel-crm' ); ?></option><?php foreach ( array( 'status' => __( 'Set status', 'bfcamel-crm' ), 'priority' => __( 'Set priority', 'bfcamel-crm' ), 'assigned_to' => __( 'Set responsible', 'bfcamel-crm' ), 'add_tags' => __( 'Add tags', 'bfcamel-crm' ), 'remove_tags' => __( 'Remove tags', 'bfcamel-crm' ) ) as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                        <select name="bulk_status" aria-label="<?php esc_attr_e( 'Status', 'bfcamel-crm' ); ?>"><?php foreach ( SubmissionService::statuses() as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                        <select name="bulk_priority" aria-label="<?php esc_attr_e( 'Priority', 'bfcamel-crm' ); ?>"><?php foreach ( SubmissionService::priorities() as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                        <select name="bulk_assigned_to" aria-label="<?php esc_attr_e( 'Responsible', 'bfcamel-crm' ); ?>"><option value="0"><?php esc_html_e( 'Unassigned', 'bfcamel-crm' ); ?></option><?php foreach ( SubmissionService::assignees() as $user ) : ?><option value="<?php echo absint( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option><?php endforeach; ?></select>
                        <input type="text" name="bulk_tags" aria-label="<?php esc_attr_e( 'Tags', 'bfcamel-crm' ); ?>"><button type="submit" class="button"><?php esc_html_e( 'Apply', 'bfcamel-crm' ); ?></button>
                    </div><?php endif; ?>
                    <div class="bfcamel-crm-panel bfcamel-crm-panel--table"><table class="widefat bfcamel-crm-table bfcamel-crm-submissions-table"><thead><tr><?php if ( $can_edit ) : ?><td class="check-column"><input type="checkbox" class="bfcamel-crm-select-all" aria-label="<?php esc_attr_e( 'Select all submissions', 'bfcamel-crm' ); ?>"></td><?php endif; ?><th><?php esc_html_e( 'Request', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Priority', 'bfcamel-crm' ); ?></th><th class="bfcamel-crm-owner-column"><?php esc_html_e( 'Responsible', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Received', 'bfcamel-crm' ); ?></th></tr></thead><tbody>
                    <?php if ( ! $result['rows'] ) : ?><tr><td colspan="<?php echo $can_edit ? 5 : 4; ?>" class="bfcamel-crm-empty"><?php esc_html_e( 'No submissions match this view.', 'bfcamel-crm' ); ?> <a href="<?php echo esc_url( self::list_url( array( 'view' => 'all' ) ) ); ?>"><?php esc_html_e( 'All submissions', 'bfcamel-crm' ); ?></a></td></tr><?php endif; ?>
                    <?php foreach ( (array) $result['rows'] as $row ) : $summary = SubmissionService::summary( $row ); $url = add_query_arg( 'bfcamel_return', $list_url . '#submission-' . absint( $row->id ), Notifications::url( $row->id ) ); $level = Notifications::level( $row->priority ); ?>
                    <tr data-bfcamel-row-url="<?php echo esc_url( $url ); ?>" data-bfcamel-row-id="<?php echo absint( $row->id ); ?>" class="bfcamel-crm-request-row bfcamel-crm-level-<?php echo esc_attr( $level ); ?>">
                        <?php if ( $can_edit ) : ?><th class="check-column"><input type="checkbox" name="submission_ids[]" value="<?php echo absint( $row->id ); ?>" aria-label="<?php echo esc_attr( $summary['title'] ); ?>"></th><?php endif; ?>
                        <td><a class="bfcamel-crm-request-title" data-bfcamel-open="<?php echo absint( $row->id ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $summary['title'] ); ?></a>
                            <div class="bfcamel-crm-request-person"><?php if ( $row->contact_id && current_user_can( 'bfcamel_crm_manage_contacts' ) ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bfcamel-crm-contacts', 'id' => absint( $row->contact_id ), 'bfcamel_return' => $list_url ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $summary['person'] ?: __( 'Open contact', 'bfcamel-crm' ) ); ?> ↗</a><?php else : ?><?php echo esc_html( $summary['person'] ); ?><?php endif; ?></div>
                            <?php if ( $summary['preview'] ) : ?><div class="bfcamel-crm-request-preview"><?php echo esc_html( $summary['preview'] ); ?></div><?php endif; ?>
                            <div class="bfcamel-crm-request-meta"><span><?php echo esc_html( SubmissionService::status_label( $row->status ) ); ?></span><span>#<?php echo absint( $row->id ); ?></span><a data-bfcamel-open="<?php echo absint( $row->id ); ?>" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open submission', 'bfcamel-crm' ); ?> →</a></div>
                        </td><td><span class="bfcamel-crm-priority-label bfcamel-crm-priority-label--<?php echo esc_attr( $level ); ?>"><?php echo esc_html( SubmissionService::priority_label( $row->priority ) ); ?></span></td>
                        <td class="bfcamel-crm-owner-column"><?php echo esc_html( $row->assignee_name ?: __( 'Unassigned', 'bfcamel-crm' ) ); ?></td><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->submitted_at ) ); ?></td>
                    </tr><?php endforeach; ?>
                    </tbody></table></div>
                </form>
                <?php self::pagination($result,'bottom'); ?>
            </div><aside class="bfcamel-crm-preview" aria-label="<?php esc_attr_e( 'Submission card', 'bfcamel-crm' ); ?>" hidden><div class="bfcamel-crm-preview-toolbar"><a class="button" data-bfcamel-full-card href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Open full page', 'bfcamel-crm' ); ?></a><button type="button" class="button" data-bfcamel-preview-close><?php esc_html_e( 'Close', 'bfcamel-crm' ); ?></button></div><div data-bfcamel-preview-content aria-live="polite"></div></aside></div>
        </div><?php
    }

    private static function pagination( $result, $position ) {
        if ( $result['total_pages'] <= 1 ) return;
        $base = add_query_arg( 'paged', '%#%', remove_query_arg( array( 'id', 'bfcamel_return', 'paged' ) ) );
        $links = paginate_links( array( 'base' => $base, 'format' => '', 'current' => $result['page'], 'total' => $result['total_pages'], 'prev_text' => '&lsaquo;', 'next_text' => '&rsaquo;' ) );
        if ( $links ) echo '<div class="tablenav bfcamel-crm-pagination--' . esc_attr( $position ) . '"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
    }
}
