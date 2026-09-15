<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Direct queries below only touch plugin-owned tables and are used for the explicit, administrator-confirmed permanent form deletion flow.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

final class InterfaceEnhancements {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public function register() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'notices' ) );
        add_action( 'admin_post_bfcamel_crm_delete_form', array( $this, 'delete_form' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'bfcamel-crm' ) ) return;

        $page = Request::get_key( 'page' );
        wp_enqueue_style(
            'bfcamel-crm-interface',
            BFCAMEL_CRM_URL . 'assets/admin-modern.css',
            array( 'bfcamel-crm-admin' ),
            BFCAMEL_CRM_VERSION
        );

        $deps = array( 'bfcamel-crm-admin' );
        if ( in_array( $page, array( 'bfcamel-crm-contacts', 'bfcamel-crm-submissions' ), true ) && wp_script_is( 'bfcamel-crm-data-enhancements', 'enqueued' ) ) {
            $deps[] = 'bfcamel-crm-data-enhancements';
        }
        wp_enqueue_script(
            'bfcamel-crm-interface',
            BFCAMEL_CRM_URL . 'assets/interface-enhancements.js',
            $deps,
            BFCAMEL_CRM_VERSION,
            true
        );

        $labels = $this->labels();
        wp_localize_script(
            'bfcamel-crm-interface',
            'BfCamelCRMInterface',
            array(
                'adminPost'       => admin_url( 'admin-post.php' ),
                'page'            => $page,
                'formId'          => 'bfcamel-crm-forms' === $page ? Request::get_id( 'id' ) : 0,
                'deleteFormNonce' => current_user_can( 'bfcamel_crm_manage_forms' ) ? wp_create_nonce( 'bfcamel_crm_delete_form' ) : '',
                'labels'          => $labels,
            )
        );

        // These two mappings were introduced in 0.5.3 after the bundled catalog was generated.
        // Override only the visible labels for Russian; the stable technical keys remain city/social_page.
        if ( 'bfcamel-crm-forms' === $page && $this->is_russian_locale() ) {
            $script = 'window.BfCamelCRMBuilder=window.BfCamelCRMBuilder||{};window.BfCamelCRMBuilder.labels=window.BfCamelCRMBuilder.labels||{};'
                . 'window.BfCamelCRMBuilder.labels.contactCity=' . wp_json_encode( $labels['contactCity'] ) . ';'
                . 'window.BfCamelCRMBuilder.labels.contactSocialPage=' . wp_json_encode( $labels['contactSocialPage'] ) . ';';
            wp_add_inline_script( 'bfcamel-crm-admin', $script, 'before' );
        }
    }

    public function notices() {
        if ( 'bfcamel-crm-forms' === Request::get_key( 'page' ) && Request::get_flag( 'form_deleted' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $this->labels()['formDeleted'] ) . '</p></div>';
        }
    }

    public function delete_form() {
        if ( ! current_user_can( 'bfcamel_crm_manage_forms' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage forms.', 'bfcamel-crm' ) );
        }

        $id = Request::post_id( 'form_id' );
        check_admin_referer( 'bfcamel_crm_delete_form' );
        $form = $id ? Repository::get( $id ) : null;
        if ( ! $form ) {
            wp_die( esc_html__( 'Form not found.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::begin_transaction() ) {
            wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }

        global $wpdb;
        $submissions_table = Schema::table( 'submissions' );
        $submission_ids = $wpdb->get_col(
            $wpdb->prepare( 'SELECT id FROM %i WHERE form_id=%d', $submissions_table, $id )
        );
        $ok = true;

        foreach ( array_map( 'absint', (array) $submission_ids ) as $submission_id ) {
            if ( ! $submission_id ) continue;
            $ok = $ok && false !== $wpdb->delete( Schema::table( 'submission_tags' ), array( 'submission_id' => $submission_id ), array( '%d' ) );
            $ok = $ok && false !== $wpdb->delete( Schema::table( 'consent_events' ), array( 'submission_id' => $submission_id ), array( '%d' ) );
            $ok = $ok && false !== $wpdb->delete( Schema::table( 'notes' ), array( 'entity_type' => 'submission', 'entity_id' => $submission_id ), array( '%s', '%d' ) );
            $ok = $ok && false !== $wpdb->delete( Schema::table( 'activity_log' ), array( 'entity_type' => 'submission', 'entity_id' => $submission_id ), array( '%s', '%d' ) );
        }

        // Remove consent events by form as well, including records not linked to a submission row.
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'consent_events' ), array( 'form_id' => $id ), array( '%d' ) );
        $ok = $ok && false !== $wpdb->delete( $submissions_table, array( 'form_id' => $id ), array( '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'form_revisions' ), array( 'form_id' => $id ), array( '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'notes' ), array( 'entity_type' => 'form', 'entity_id' => $id ), array( '%s', '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'activity_log' ), array( 'entity_type' => 'form', 'entity_id' => $id ), array( '%s', '%d' ) );
        $ok = $ok && false !== $wpdb->delete( Schema::table( 'forms' ), array( 'id' => $id ), array( '%d' ) );

        if ( ! $ok || ! Schema::commit() ) {
            Schema::rollback();
            wp_die( esc_html__( 'The form could not be deleted.', 'bfcamel-crm' ) );
        }

        $rules = array_values(
            array_filter(
                WorkflowService::rules(),
                static function ( $rule ) use ( $id ) {
                    return absint( $rule['form_id'] ?? 0 ) !== $id;
                }
            )
        );
        WorkflowService::save_rules( $rules );

        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( Repository::CACHE_GROUP );
        } else {
            wp_cache_delete( 'form:id:' . $id, Repository::CACHE_GROUP );
            wp_cache_delete( 'form:slug:' . sanitize_title( $form->slug ), Repository::CACHE_GROUP );
            wp_cache_delete( 'forms:all:1', Repository::CACHE_GROUP );
            wp_cache_delete( 'forms:all:0', Repository::CACHE_GROUP );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-forms&form_deleted=1' ) );
        exit;
    }

    private function labels() {
        if ( $this->is_russian_locale() ) {
            return array(
                'city'              => 'Город',
                'socialPages'       => 'Страницы в социальных сетях',
                'contactCity'       => 'Контакт: город',
                'contactSocialPage' => 'Контакт: страница в социальной сети',
                'deleteForm'        => 'Удалить форму навсегда',
                'confirmDeleteForm' => 'Удалить эту форму навсегда? Все заявки этой формы и связанные с ними данные будут удалены. Контакты сохранятся. Это действие нельзя отменить.',
                'formDeleted'       => 'Форма и связанные с ней заявки удалены навсегда.',
            );
        }

        return array(
            'city'              => __( 'City', 'bfcamel-crm' ),
            'socialPages'       => __( 'Social pages', 'bfcamel-crm' ),
            'contactCity'       => __( 'Contact: city', 'bfcamel-crm' ),
            'contactSocialPage' => __( 'Contact: social page', 'bfcamel-crm' ),
            'deleteForm'        => __( 'Delete form permanently', 'bfcamel-crm' ),
            'confirmDeleteForm' => __( 'Delete this form permanently? All submissions for this form and their related data will be deleted. Contacts will be preserved. This cannot be undone.', 'bfcamel-crm' ),
            'formDeleted'       => __( 'The form and its related submissions were deleted permanently.', 'bfcamel-crm' ),
        );
    }

    private function is_russian_locale() {
        return 0 === strpos( (string) determine_locale(), 'ru' );
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
