<?php
namespace BfCamel\CRM\Forms;

use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SubmissionHandler {
    private static $instance = null;
    public static function instance(){if(null===self::$instance)self::$instance=new self();return self::$instance;}
    private function __construct(){}
    public function register(){add_action('admin_post_nopriv_bfcamel_crm_submit',array($this,'handle'));add_action('admin_post_bfcamel_crm_submit',array($this,'handle'));}

    public function handle(){
        $form_id=isset($_POST['form_id'])?absint($_POST['form_id']):0;$revision_id=isset($_POST['revision_id'])?absint($_POST['revision_id']):0;$nonce=isset($_POST['bfcamel_crm_nonce'])?sanitize_text_field(wp_unslash($_POST['bfcamel_crm_nonce'])):'';$return_url=isset($_POST['return_url'])?esc_url_raw(wp_unslash($_POST['return_url'])):wp_get_referer();$return_url=$return_url?:home_url('/');
        if(!Schema::is_current())$this->redirect($return_url,$form_id,'error');
        if(!$form_id||!$revision_id||!wp_verify_nonce($nonce,'bfcamel_crm_submit_'.$form_id.'_'.$revision_id))$this->redirect($return_url,$form_id,'error');
        if(!empty($_POST['bfcamel_crm_website']))$this->redirect($return_url,$form_id,'success');
        $form=Repository::get($form_id);$revision=Repository::get_revision($revision_id);if(!$form||'publish'!==$form->status||!$revision||absint($revision->form_id)!==$form_id)$this->redirect($return_url,$form_id,'error');
        if($this->is_rate_limited($form_id))$this->redirect($return_url,$form_id,'error');
        $schema=Repository::decode_schema($revision);$result=$this->validate_payload($schema,$_POST); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if(is_wp_error($result))$this->redirect($return_url,$form_id,'error');
        $payload=$result;$settings=Repository::decode_settings($revision);$workflow=WorkflowService::evaluate($form_id,$settings,$payload);$source=$this->source_context($return_url);$uuid=wp_generate_uuid4();
        if(!Schema::begin_transaction())$this->redirect($return_url,$form_id,'error');
        global $wpdb;$inserted=$wpdb->insert(Schema::table('submissions'),array('form_id'=>$form_id,'revision_id'=>$revision_id,'submission_uuid'=>$uuid,'contact_id'=>0,'status'=>$workflow['status'],'contact_sync_status'=>'pending','assigned_to'=>0,'priority'=>$workflow['priority'],'payload_json'=>wp_json_encode($payload),'source_url'=>$source['source_url'],'source_ip'=>$source['source_ip'],'user_agent'=>$source['user_agent'],'submitted_at'=>current_time('mysql')),array('%d','%d','%s','%d','%s','%s','%d','%s','%s','%s','%s','%s','%s'));
        if(!$inserted)$this->rollback_and_redirect($return_url,$form_id);
        $submission_id=absint($wpdb->insert_id);$contact=ContactService::resolve_and_sync($schema,$payload);$contact_id=absint($contact['contact_id']??0);$sync_status=sanitize_key($contact['status']??'error');
        if('error'===$sync_status)$this->rollback_and_redirect($return_url,$form_id);
        $status=$workflow['status'];if('conflict'===$sync_status){$status=WorkflowService::is_valid('status','needs_review')?'needs_review':WorkflowService::default_status();}
        if(false===$wpdb->update(Schema::table('submissions'),array('contact_id'=>$contact_id,'contact_sync_status'=>$sync_status,'status'=>$status),array('id'=>$submission_id),array('%d','%s','%s'),array('%d')))$this->rollback_and_redirect($return_url,$form_id);
        if(!ConsentService::capture_from_submission($submission_id,$contact_id,$form_id,$revision_id,$schema,$payload,$source))$this->rollback_and_redirect($return_url,$form_id);
        if(!Schema::log('submission',$submission_id,'created','Form submission received.',array('form_id'=>$form_id,'revision_id'=>$revision_id,'submission_uuid'=>$uuid,'contact_id'=>$contact_id,'contact_sync_status'=>$sync_status,'status'=>$status,'priority'=>$workflow['priority'])))$this->rollback_and_redirect($return_url,$form_id);
        if(!Schema::commit())$this->rollback_and_redirect($return_url,$form_id);
        do_action('bfcamel_crm_submission_created',$submission_id,$contact_id,$form_id,$revision_id);$this->redirect($return_url,$form_id,'success');
    }

    private function validate_payload( $schema, $posted ) {
        $payload = array();
        foreach ( (array) $schema as $field ) {
            $name     = sanitize_key( $field['name'] ?? '' );
            $type     = sanitize_key( $field['type'] ?? 'text' );
            $required = ! empty( $field['required'] );
            if ( ! $name || 'html' === $type ) {
                continue;
            }
            $raw   = isset( $posted[ $name ] ) ? wp_unslash( $posted[ $name ] ) : '';
            $value = $this->sanitize_value( $type, $raw, (array) ( $field['options'] ?? array() ) );
            if ( is_wp_error( $value ) ) {
                return $value;
            }
            if ( $required && $this->is_empty( $value ) ) {
                /* translators: %s: required form field key. */
                return new \WP_Error( 'bfcamel_crm_required', sprintf( __( 'Required field missing: %s', 'bfcamel-crm' ), $name ) );
            }
            if ( 'email' === $type && '' !== $value && ! is_email( $value ) ) {
                return new \WP_Error( 'bfcamel_crm_email', __( 'Invalid email address.', 'bfcamel-crm' ) );
            }
            $payload[ $name ] = in_array( $type, array( 'consent_personal_data', 'consent_marketing' ), true ) ? ( $value ? '1' : '' ) : $value;
        }
        return $payload;
    }
    private function sanitize_value($type,$raw,$options){switch($type){case'email':return sanitize_email(is_scalar($raw)?$raw:'');case'tel':case'text':case'hidden':case'date':return sanitize_text_field(is_scalar($raw)?$raw:'');case'number':if(''===$raw||null===$raw)return'';return is_numeric($raw)?(string)$raw:new \WP_Error('bfcamel_crm_number',__( 'Invalid number.', 'bfcamel-crm' ));case'textarea':return sanitize_textarea_field(is_scalar($raw)?$raw:'');case'select':case'radio':$value=sanitize_text_field(is_scalar($raw)?$raw:'');if(''===$value)return'';return in_array($value,$options,true)?$value:new \WP_Error('bfcamel_crm_option',__( 'Invalid option.', 'bfcamel-crm' ));case'checkbox':$values=array();foreach((array)$raw as $item){$item=sanitize_text_field($item);if(in_array($item,$options,true))$values[]=$item;}return array_values(array_unique($values));case'consent_personal_data':case'consent_marketing':return !empty($raw);default:return sanitize_text_field(is_scalar($raw)?$raw:'');}}
    private function is_empty($value){if(is_array($value))return 0===count($value);if(is_bool($value))return false===$value;return''===trim((string)$value);}
    private function source_context($source_url){$settings=get_option('bfcamel_crm_settings',array());$store_ip=!empty($settings['store_ip']);$store_url=!isset($settings['store_source_url'])||!empty($settings['store_source_url']);$store_agent=!isset($settings['store_user_agent'])||!empty($settings['store_user_agent']);$ip='';if($store_ip&&!empty($_SERVER['REMOTE_ADDR'])){$candidate=sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));if(filter_var($candidate,FILTER_VALIDATE_IP))$ip=$candidate;}return array('source_url'=>$store_url?esc_url_raw($source_url):'','source_ip'=>$ip,'user_agent'=>$store_agent&&isset($_SERVER['HTTP_USER_AGENT'])?substr(sanitize_textarea_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])),0,1000):'');}
    private function is_rate_limited($form_id){$ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'';$agent=isset($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])):'';$fingerprint=$ip.'|'.$agent;if('|'===$fingerprint)$fingerprint=wp_get_session_token();$key='bfcamel_crm_rl_'.hash_hmac('sha256',absint($form_id).'|'.$fingerprint,wp_salt('nonce'));if(get_transient($key))return true;set_transient($key,1,5);return false;}
    private function rollback_and_redirect($url,$form_id){Schema::rollback();$this->redirect($url,$form_id,'error');}
    private function redirect($url,$form_id,$state){$url=remove_query_arg(array('bfcamel_crm_form','bfcamel_crm_form_id'),$url);$url=add_query_arg(array('bfcamel_crm_form'=>sanitize_key($state),'bfcamel_crm_form_id'=>absint($form_id)),$url);wp_safe_redirect($url);exit;}
}
