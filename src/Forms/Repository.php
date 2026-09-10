<?php
namespace BfCamel\CRM\Forms;

use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Repository {
    public static function all( $include_archived = true ) {
        global $wpdb; $table=Schema::table('forms');
        $where = $include_archived ? '' : " WHERE status='publish'";
        return $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY updated_at DESC,id DESC");
    }
    public static function get( $id ) { global $wpdb;$table=Schema::table('forms');return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1",absint($id))); }
    public static function get_by_slug( $slug ) { global $wpdb;$table=Schema::table('forms');return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug=%s LIMIT 1",sanitize_title($slug))); }
    public static function get_revision( $revision_id ) { global $wpdb;$table=Schema::table('form_revisions');return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1",absint($revision_id))); }
    public static function current_revision( $form ) { return $form && !empty($form->current_revision_id) ? self::get_revision($form->current_revision_id) : null; }

    public static function archive( $id, $user_id = 0 ) { return self::set_status($id,'archived',$user_id); }
    public static function restore( $id, $user_id = 0 ) { return self::set_status($id,'publish',$user_id); }
    private static function set_status( $id, $status, $user_id ) {
        global $wpdb; $id=absint($id); $status='publish'===$status?'publish':'archived'; if(!$id||!self::get($id)||!Schema::begin_transaction())return false;
        if(!$wpdb->get_var($wpdb->prepare("SELECT id FROM ".Schema::table('forms')." WHERE id=%d FOR UPDATE",$id))){Schema::rollback();return false;}
        $ok=$wpdb->update(Schema::table('forms'),array('status'=>$status,'updated_at'=>current_time('mysql')),array('id'=>$id),array('%s','%s'),array('%d'));
        if(false===$ok){Schema::rollback();return false;}
        if(!Schema::log('form',$id,'publish'===$status?'form_restored':'form_archived','publish'===$status?'Form restored.':'Form archived. To preserve submission history the form record was not physically deleted.',array('status'=>$status),absint($user_id))){Schema::rollback();return false;}
        if(!Schema::commit()){Schema::rollback();return false;}
        return true;
    }

    public static function save( $id, $name, $slug, $schema, $settings, $user_id ) {
        global $wpdb;
        $forms = Schema::table( 'forms' );
        $revisions = Schema::table( 'form_revisions' );
        $now = current_time( 'mysql' );
        $id = absint( $id );
        $name = sanitize_text_field( $name );
        $slug = sanitize_title( $slug ?: $name );
        $user_id = absint( $user_id );
        if ( '' === $name ) return new \WP_Error( 'bfcamel_crm_form_name', __( 'Form name is required.', 'bfcamel-crm' ) );
        if ( '' === $slug ) return new \WP_Error( 'bfcamel_crm_form_slug', __( 'Form slug is required.', 'bfcamel-crm' ) );

        $schema = self::sanitize_schema( $schema );
        if ( is_wp_error( $schema ) ) return $schema;
        $settings = self::sanitize_settings( $settings );
        if ( ! Schema::begin_transaction() ) return new \WP_Error( 'bfcamel_crm_form_transaction', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );

        if ( $id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$forms} WHERE id=%d FOR UPDATE", $id ) ) ) {
            return self::rollback_error( 'bfcamel_crm_form_update', __( 'The form could not be updated.', 'bfcamel-crm' ) );
        }
        $duplicate = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$forms} WHERE slug=%s AND id<>%d LIMIT 1", $slug, $id ) );
        if ( $duplicate ) return self::rollback_error( 'bfcamel_crm_form_slug_exists', __( 'Another form already uses this slug.', 'bfcamel-crm' ) );

        if ( $id ) {
            $updated = $wpdb->update( $forms, array( 'name'=>$name, 'slug'=>$slug, 'settings_json'=>wp_json_encode($settings), 'updated_at'=>$now ), array( 'id'=>$id ), array( '%s','%s','%s','%s' ), array( '%d' ) );
            if ( false === $updated ) return self::rollback_error( 'bfcamel_crm_form_update', __( 'The form could not be updated.', 'bfcamel-crm' ) );
        } else {
            $inserted = $wpdb->insert( $forms, array( 'name'=>$name, 'slug'=>$slug, 'status'=>'publish', 'current_revision_id'=>0, 'settings_json'=>wp_json_encode($settings), 'created_by'=>$user_id, 'created_at'=>$now, 'updated_at'=>$now ), array( '%s','%s','%s','%d','%s','%d','%s','%s' ) );
            if ( ! $inserted ) return self::rollback_error( 'bfcamel_crm_form_insert', __( 'The form could not be created.', 'bfcamel-crm' ) );
            $id = absint( $wpdb->insert_id );
        }

        $version = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(version) FROM {$revisions} WHERE form_id=%d", $id ) ) + 1;
        $inserted_revision = $wpdb->insert( $revisions, array( 'form_id'=>$id, 'version'=>$version, 'schema_json'=>wp_json_encode($schema), 'settings_json'=>wp_json_encode($settings), 'created_by'=>$user_id, 'created_at'=>$now ), array( '%d','%d','%s','%s','%d','%s' ) );
        if ( ! $inserted_revision ) return self::rollback_error( 'bfcamel_crm_revision_insert', __( 'The form revision could not be created.', 'bfcamel-crm' ) );

        $revision_id = absint( $wpdb->insert_id );
        if ( false === $wpdb->update( $forms, array( 'current_revision_id'=>$revision_id, 'updated_at'=>$now ), array( 'id'=>$id ), array( '%d','%s' ), array( '%d' ) ) ) {
            return self::rollback_error( 'bfcamel_crm_form_update', __( 'The form could not be updated.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::log( 'form', $id, 'revision_published', sprintf( __( 'Form revision %d published.', 'bfcamel-crm' ), $version ), array( 'revision_id'=>$revision_id, 'version'=>$version ), $user_id ) ) {
            return self::rollback_error( 'bfcamel_crm_form_update', __( 'The form could not be updated.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::commit() ) return self::rollback_error( 'bfcamel_crm_form_update', __( 'The form could not be updated.', 'bfcamel-crm' ) );
        return $id;
    }

    public static function decode_schema( $revision ) { if(!$revision)return array();$decoded=json_decode((string)$revision->schema_json,true);return is_array($decoded)?$decoded:array(); }
    public static function decode_settings( $source ) { $json=is_object($source)&&isset($source->settings_json)?$source->settings_json:'';$decoded=json_decode((string)$json,true);return wp_parse_args(is_array($decoded)?$decoded:array(),self::default_settings()); }

    public static function default_schema() {
        return array(
            array('name'=>'contact_name','type'=>'text','label'=>__( 'Name', 'bfcamel-crm' ),'required'=>true,'width'=>'50','placeholder'=>__( 'How should we address you?', 'bfcamel-crm' ),'mapping'=>'contact.name','custom_key'=>'','css_class'=>'','options'=>array()),
            array('name'=>'contact_phone','type'=>'tel','label'=>__( 'Phone', 'bfcamel-crm' ),'required'=>false,'width'=>'50','placeholder'=>'+1 555 000 0000','mapping'=>'contact.phone','custom_key'=>'','css_class'=>'','options'=>array()),
            array('name'=>'contact_email','type'=>'email','label'=>__( 'Email', 'bfcamel-crm' ),'required'=>false,'width'=>'50','placeholder'=>'you@example.com','mapping'=>'contact.email','custom_key'=>'','css_class'=>'','options'=>array()),
            array('name'=>'message','type'=>'textarea','label'=>__( 'Message', 'bfcamel-crm' ),'required'=>false,'width'=>'100','placeholder'=>'','mapping'=>'submission_only','custom_key'=>'','css_class'=>'','options'=>array()),
            array('name'=>'consent_personal_data','type'=>'consent_personal_data','label'=>__( 'I consent to {personal_data_consent} and confirm that I have read the {privacy_policy}.', 'bfcamel-crm' ),'required'=>true,'width'=>'100','placeholder'=>'','mapping'=>'submission_only','custom_key'=>'','css_class'=>'','options'=>array()),
            array('name'=>'consent_marketing','type'=>'consent_marketing','label'=>__( 'I consent to receive informational and marketing messages under the {marketing_consent}.', 'bfcamel-crm' ),'required'=>false,'width'=>'100','placeholder'=>'','mapping'=>'submission_only','custom_key'=>'','css_class'=>'','options'=>array()),
        );
    }

    public static function default_settings() {
        return array(
            'style_mode'=>'default','columns'=>'2','primary_color'=>'#18434e','text_color'=>'#243b40','field_bg'=>'#faf7f6','border_color'=>'#e4dad8','button_color'=>'#18434e','button_text'=>'#ffffff','border_radius'=>'12','custom_class'=>'','submit_label'=>__( 'Submit', 'bfcamel-crm' ),'success_message'=>__( 'Thank you. Your message has been received.', 'bfcamel-crm' ),'error_message'=>__( 'Please check the form and try again.', 'bfcamel-crm' ),
            'default_status'=>WorkflowService::default_status(),'default_priority'=>WorkflowService::default_priority(),
        );
    }

    private static function sanitize_schema( $schema ) {
        if(!is_array($schema))return new \WP_Error('bfcamel_crm_schema_invalid',__( 'The form schema is invalid.', 'bfcamel-crm' ));
        $allowed_types=array('text','email','tel','number','date','textarea','select','radio','checkbox','hidden','consent_personal_data','consent_marketing','html');
        $allowed_mappings=array('submission_only','contact.name','contact.email','contact.phone','contact.organization','contact.custom');$allowed_widths=array('100','50','33','25');$result=array();$names=array();
        foreach($schema as $field){if(!is_array($field))continue;$type=sanitize_key($field['type']??'text');if(!in_array($type,$allowed_types,true))$type='text';$name=sanitize_key($field['name']??'');
            if('html'===$type){if(''===$name)$name='content_'.wp_generate_password(8,false,false);}elseif(''===$name)return new \WP_Error('bfcamel_crm_field_name',__( 'Every form field needs a unique field key.', 'bfcamel-crm' ));
            if(isset($names[$name]))return new \WP_Error('bfcamel_crm_field_duplicate',sprintf(__( 'Duplicate field key: %s.', 'bfcamel-crm' ),$name));$names[$name]=true;
            $mapping=sanitize_text_field($field['mapping']??'submission_only');if(!in_array($mapping,$allowed_mappings,true))$mapping='submission_only';$width=sanitize_text_field($field['width']??'100');if(!in_array($width,$allowed_widths,true))$width='100';
            $options=array();foreach((array)($field['options']??array()) as $option){$option=sanitize_text_field($option);if(''!==$option)$options[]=$option;}
            $label='html'===$type?wp_kses_post($field['label']??''):sanitize_textarea_field($field['label']??'');
            $classes=array_filter(array_map('sanitize_html_class',preg_split('/\s+/',(string)($field['css_class']??''))));
            $result[]=array('name'=>$name,'type'=>$type,'label'=>$label,'required'=>!empty($field['required']),'width'=>$width,'placeholder'=>sanitize_text_field($field['placeholder']??''),'mapping'=>$mapping,'custom_key'=>sanitize_key($field['custom_key']??''),'css_class'=>implode(' ',$classes),'options'=>$options);
        }return $result;
    }

    private static function sanitize_settings( $settings ) {
        $defaults=self::default_settings();$settings=wp_parse_args(is_array($settings)?$settings:array(),$defaults);$mode=sanitize_key($settings['style_mode']);if('unstyled'===$mode)$mode='theme';if(!in_array($mode,array('theme','default','custom'),true))$mode='default';
        $columns=in_array((string)$settings['columns'],array('1','2'),true)?(string)$settings['columns']:'2';$radius=min(60,max(0,absint($settings['border_radius'])));$status=sanitize_key($settings['default_status']);$priority=sanitize_key($settings['default_priority']);
        if(!WorkflowService::is_valid('status',$status))$status=WorkflowService::default_status();if(!WorkflowService::is_valid('priority',$priority))$priority=WorkflowService::default_priority();
        return array('style_mode'=>$mode,'columns'=>$columns,'primary_color'=>sanitize_hex_color($settings['primary_color'])?:$defaults['primary_color'],'text_color'=>sanitize_hex_color($settings['text_color'])?:$defaults['text_color'],'field_bg'=>sanitize_hex_color($settings['field_bg'])?:$defaults['field_bg'],'border_color'=>sanitize_hex_color($settings['border_color'])?:$defaults['border_color'],'button_color'=>sanitize_hex_color($settings['button_color'])?:$defaults['button_color'],'button_text'=>sanitize_hex_color($settings['button_text'])?:$defaults['button_text'],'border_radius'=>(string)$radius,'custom_class'=>sanitize_html_class($settings['custom_class']),'submit_label'=>sanitize_text_field($settings['submit_label']),'success_message'=>sanitize_text_field($settings['success_message']),'error_message'=>sanitize_text_field($settings['error_message']),'default_status'=>$status,'default_priority'=>$priority);
    }

    private static function rollback_error( $code, $message ) {
        global $wpdb;
        $database_error = $wpdb->last_error;
        Schema::rollback();
        if ( $database_error ) $message .= ' ' . sanitize_text_field( $database_error );
        return new \WP_Error( $code, $message );
    }
}
