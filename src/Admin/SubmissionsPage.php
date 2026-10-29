<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\NoteService;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SubmissionsPage {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct() {}

    public function page() {
        $this->guard( 'bfcamel_crm_view_submissions' );
        $id = Request::get_id( 'id' );
        if ( $id ) { SubmissionDetailPage::render( $id ); return; }
        $filters = $this->filters();
        $result = SubmissionService::query( $filters, max( 1, Request::get_id( 'paged', 1 ) ), 25 );
        InboxView::render( $result, $filters );
    }

    public function update_submission(){ $this->guard('bfcamel_crm_edit_submissions'); SubmissionDetailPage::update(); }
    public function add_note(){ $this->guard('bfcamel_crm_edit_submissions'); $id=Request::post_id('submission_id'); check_admin_referer('bfcamel_crm_add_submission_note_'.$id); if(!SubmissionService::get($id)) wp_die(esc_html__('Submission not found.','bfcamel-crm')); $r=NoteService::add('submission',$id,Request::post_textarea('note'),get_current_user_id()); if(is_wp_error($r)) wp_die(esc_html($r->get_error_message()),esc_html__('Could not save note','bfcamel-crm'),array('back_link'=>true)); wp_safe_redirect(add_query_arg('bfcamel_return',NotificationAdmin::return_url(),admin_url('admin.php?page=bfcamel-crm-submissions&id='.$id.'&note_added=1'))); exit; }
    public function delete_submission(){ $this->guard('bfcamel_crm_edit_submissions'); $id=Request::post_id('submission_id'); check_admin_referer('bfcamel_crm_delete_submission_'.$id); $result=SubmissionService::delete_permanently($id); if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),esc_html__('Could not delete submission','bfcamel-crm'),array('back_link'=>true)); wp_safe_redirect(admin_url('admin.php?page=bfcamel-crm-submissions&deleted=1')); exit; }
    public function bulk_submissions(){ $this->guard('bfcamel_crm_edit_submissions'); check_admin_referer('bfcamel_crm_bulk_submissions'); $ids=Request::post_id_list('submission_ids'); $action=Request::post_key('bulk_action'); $value=''; if('status'===$action)$value=Request::post_key('bulk_status'); elseif('priority'===$action)$value=Request::post_key('bulk_priority'); elseif('assigned_to'===$action)$value=Request::post_id('bulk_assigned_to'); elseif(in_array($action,array('add_tags','remove_tags'),true))$value=Request::post_textarea('bulk_tags'); else wp_die(esc_html__('Choose a bulk action.','bfcamel-crm'),'',array('back_link'=>true)); $r=SubmissionService::bulk_apply($ids,$action,$value,get_current_user_id()); if(is_wp_error($r))wp_die(esc_html($r->get_error_message()),esc_html__('Could not update submissions','bfcamel-crm'),array('back_link'=>true)); wp_safe_redirect(admin_url('admin.php?page=bfcamel-crm-submissions&bulk_updated=1')); exit; }

    private function table($rows,$checkboxes){ $default_priority=WorkflowService::default_priority(); ?><div class="bfcamel-crm-table-scroll"><table class="widefat striped bfcamel-crm-table bfcamel-crm-submissions-table"><thead><tr><?php if($checkboxes): ?><td class="check-column"><input class="bfcamel-crm-select-all" type="checkbox"></td><?php endif; ?><th>ID</th><th><?php esc_html_e('Form','bfcamel-crm'); ?></th><th><?php esc_html_e('Contact','bfcamel-crm'); ?></th><th><?php esc_html_e('Status','bfcamel-crm'); ?></th><th><?php esc_html_e('Priority','bfcamel-crm'); ?></th><th><?php esc_html_e('Responsible','bfcamel-crm'); ?></th><th><?php esc_html_e('Tags','bfcamel-crm'); ?></th><th><?php esc_html_e('Received','bfcamel-crm'); ?></th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="<?php echo $checkboxes?9:8; ?>"><?php esc_html_e('No submissions yet.','bfcamel-crm'); ?></td></tr><?php endif; foreach((array)$rows as $row): $priority=$row->priority?:$default_priority; ?><tr><?php if($checkboxes): ?><th class="check-column"><input type="checkbox" name="submission_ids[]" value="<?php echo esc_attr($row->id); ?>"></th><?php endif; ?><td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=bfcamel-crm-submissions&id='.absint($row->id))); ?>">#<?php echo esc_html($row->id); ?></a></strong></td><td><?php echo esc_html($row->form_name?:'#'.$row->form_id); ?></td><td><?php echo esc_html($row->contact_name?:('conflict'===$row->contact_sync_status?__('Needs review','bfcamel-crm'):'—')); ?></td><td><span class="bfcamel-crm-badge bfcamel-crm-badge--<?php echo esc_attr(sanitize_html_class($row->status)); ?>"><?php echo esc_html(SubmissionService::status_label($row->status)); ?></span></td><td><span class="bfcamel-crm-priority bfcamel-crm-priority--<?php echo esc_attr(sanitize_html_class($priority)); ?>"><?php echo esc_html(SubmissionService::priority_label($priority)); ?></span></td><td><?php echo esc_html($row->assignee_name?:__('Unassigned','bfcamel-crm')); ?></td><td><?php $this->tags($row->tag_names); ?></td><td><?php echo esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'),$row->submitted_at)); ?></td></tr><?php endforeach; ?></tbody></table></div><?php }
    private function tags($names){ $items=array_filter(array_map('trim',explode(',',(string)$names))); if(!$items){echo '—';return;} foreach($items as $name) echo '<span class="bfcamel-crm-tag">'.esc_html($name).'</span> '; }
    private function pagination($result,$position){ if($result['total_pages']<=1)return; $base=remove_query_arg('paged'); $base=add_query_arg('paged','%#%',$base); $links=paginate_links(array('base'=>$base,'format'=>'','current'=>$result['page'],'total'=>$result['total_pages'],'prev_text'=>'&lsaquo;','next_text'=>'&rsaquo;')); if($links) echo '<div class="tablenav bfcamel-crm-pagination--'.esc_attr($position).'"><div class="tablenav-pages">'.wp_kses_post($links).'</div></div>'; }
    private function filters(){ return array('view'=>in_array(Request::get_key('view','attention'),array('attention','mine','all'),true)?Request::get_key('view','attention'):'attention','search'=>Request::get_text('s'),'status'=>Request::get_key('status'),'form_id'=>Request::get_id('form_id'),'assigned_to'=>Request::get_text('assigned_to'),'priority'=>Request::get_key('priority'),'tag_id'=>Request::get_id('tag_id'),'date_from'=>Request::get_text('date_from'),'date_to'=>Request::get_text('date_to'));
    }
    private function guard($cap){ if(!current_user_can($cap))wp_die(esc_html__('You do not have permission to access this page.','bfcamel-crm')); if(!Schema::is_current())wp_die(esc_html(Schema::readiness_message()),esc_html__('CRM database update required','bfcamel-crm'),array('back_link'=>true)); }
}

