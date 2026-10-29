<?php
namespace BfCamel\CRM;

use BfCamel\CRM\Access\RoleManager;
use BfCamel\CRM\Admin\Bootstrap;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Renderer;
use BfCamel\CRM\Forms\SubmissionHandler;
use BfCamel\CRM\Privacy\Privacy;
use BfCamel\CRM\Notifications\Notifications;
use BfCamel\CRM\Admin\NotificationAdmin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Plugin {
    private static $instance = null;
    private $booted = false;
    public static function instance(){ if(null===self::$instance)self::$instance=new self();return self::$instance; }
    private function __construct(){}

    public function boot(){
        if($this->booted)return;$this->booted=true;
        global $wp_textdomain_registry;
        if ( isset( $wp_textdomain_registry ) ) $wp_textdomain_registry->set_custom_path( 'bfcamel-crm', BFCAMEL_CRM_DIR . 'languages' );
        if(is_admin()||(defined('WP_CLI')&&WP_CLI))add_action('init',array($this,'upgrade'),1);
        Notifications::register();
        RoleManager::register();Renderer::instance()->register();SubmissionHandler::instance()->register();Privacy::instance()->register();
        if(is_admin()){add_action('admin_notices',array('BfCamel\\CRM\\Database\\Schema','admin_notice'));Bootstrap::instance()->register();NotificationAdmin::register();}
    }

    public function upgrade(){
        Schema::maybe_upgrade();WorkflowService::seed_defaults();RoleManager::ensure_roles();self::seed_defaults();Notifications::initialize();
    }

    public static function activate( $network_wide = false ){
        if ( $network_wide && is_multisite() ) {
            wp_die( esc_html__( 'BfCamel CRM does not support network-wide activation. Activate it separately on each site.', 'bfcamel-crm' ) );
        }
        $installed=Schema::install();if(is_wp_error($installed))wp_die(esc_html($installed->get_error_message()));self::seed_defaults();WorkflowService::seed_defaults();RoleManager::ensure_roles(true);Notifications::initialize();
    }
    public static function deactivate(){wp_clear_scheduled_hook(Notifications::CRON);}
    public static function capabilities(){return RoleManager::all_capabilities();}

    private static function seed_defaults(){
        if(false===get_option('bfcamel_crm_legal_documents',false))add_option('bfcamel_crm_legal_documents',array('personal_data_consent'=>array('title'=>'','url'=>'','version'=>'','effective_date'=>'','link_text'=>''),'privacy_policy'=>array('title'=>'','url'=>'','version'=>'','effective_date'=>'','link_text'=>''),'marketing_consent'=>array('title'=>'','url'=>'','version'=>'','effective_date'=>'','link_text'=>'')),'',false);
        if(false===get_option('bfcamel_crm_settings',false))add_option('bfcamel_crm_settings',array('store_ip'=>false,'store_source_url'=>true,'store_user_agent'=>true,'delete_data_on_uninstall'=>false,'legal_risk_acknowledged_at'=>'','legal_risk_acknowledged_by'=>0),'',false);
    }
}

