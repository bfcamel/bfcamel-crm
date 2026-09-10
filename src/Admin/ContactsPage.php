<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\CRM\ActivityFormatter;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\NoteService;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Export\Exporter;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactsPage {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct() {}

    public function page() {
        $this->guard();
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'new' === $action ) { $this->create_form(); return; }
        if ( $id ) { $this->detail( $id ); return; }

        $filters = self::filters();
        $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $result = ContactService::query( $filters, $page, 25 );
        $tags = TagService::all();
        $statuses = ConsentService::statuses();
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div><h1><?php esc_html_e( 'Contacts', 'bfcamel-crm' ); ?></h1><p class="description"><?php esc_html_e( 'Search, filter and manage CRM contacts.', 'bfcamel-crm' ); ?></p></div>
                <div class="bfcamel-crm-actions"><?php $this->export_buttons( $filters ); ?><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&action=new' ) ); ?>"><?php esc_html_e( 'Add contact', 'bfcamel-crm' ); ?></a></div>
            </div>

            <form method="get" class="bfcamel-crm-filters">
                <input type="hidden" name="page" value="bfcamel-crm-contacts">
                <div class="bfcamel-crm-filter-search"><label class="screen-reader-text" for="bfcamel-crm-contact-search"><?php esc_html_e( 'Search contacts', 'bfcamel-crm' ); ?></label><input id="bfcamel-crm-contact-search" type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr__( 'Search by name, organization, email or phone', 'bfcamel-crm' ); ?>"></div>
                <select name="tag_id" aria-label="<?php echo esc_attr__( 'Tag', 'bfcamel-crm' ); ?>"><option value="0"><?php esc_html_e( 'All tags', 'bfcamel-crm' ); ?></option><?php foreach ( $tags as $tag ) : ?><option value="<?php echo esc_attr( $tag->id ); ?>" <?php selected( $filters['tag_id'], $tag->id ); ?>><?php echo esc_html( $tag->name ); ?></option><?php endforeach; ?></select>
                <select name="personal_data_consent" aria-label="<?php echo esc_attr__( 'Personal data consent', 'bfcamel-crm' ); ?>"><option value=""><?php esc_html_e( 'Personal data: all', 'bfcamel-crm' ); ?></option><?php foreach ( $statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['personal_data_consent'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                <select name="marketing_consent" aria-label="<?php echo esc_attr__( 'Marketing consent', 'bfcamel-crm' ); ?>"><option value=""><?php esc_html_e( 'Marketing: all', 'bfcamel-crm' ); ?></option><?php foreach ( $statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['marketing_consent'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
                <button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'bfcamel-crm' ); ?></button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts' ) ); ?>"><?php esc_html_e( 'Reset', 'bfcamel-crm' ); ?></a>
            </form>
            <?php if ( isset( $_GET['bulk_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Selected contacts updated.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <p class="bfcamel-crm-results-count"><?php echo esc_html( sprintf( __( 'Found: %d', 'bfcamel-crm' ), $result['total'] ) ); ?></p>
            <?php $this->pagination( $result, $filters, 'top' ); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bfcamel-crm-bulk-form">
                <input type="hidden" name="action" value="bfcamel_crm_bulk_contacts"><?php wp_nonce_field( 'bfcamel_crm_bulk_contacts' ); ?>
                <div class="bfcamel-crm-bulk-bar">
                    <select name="bulk_action"><option value=""><?php esc_html_e( 'Bulk actions', 'bfcamel-crm' ); ?></option><option value="add_tags"><?php esc_html_e( 'Add tags', 'bfcamel-crm' ); ?></option><option value="remove_tags"><?php esc_html_e( 'Remove tags', 'bfcamel-crm' ); ?></option></select>
                    <input type="text" name="bulk_tags" placeholder="<?php echo esc_attr__( 'Select tags', 'bfcamel-crm' ); ?>">
                    <button class="button" type="submit"><?php esc_html_e( 'Apply', 'bfcamel-crm' ); ?></button>
                    <span class="bfcamel-crm-bulk-spacer"></span>
                    <?php if ( current_user_can( 'bfcamel_crm_export_data' ) ) : ?><button class="button" type="submit" name="export_selected" value="csv"><?php esc_html_e( 'Export selected CSV', 'bfcamel-crm' ); ?></button><button class="button" type="submit" name="export_selected" value="xlsx"><?php esc_html_e( 'Export selected XLSX', 'bfcamel-crm' ); ?></button><?php endif; ?>
                </div>
                <div class="bfcamel-crm-panel bfcamel-crm-panel--table"><?php $this->table( $result['rows'] ); ?></div>
            </form>
            <?php $this->pagination( $result, $filters, 'bottom' ); ?>
        </div><?php
    }

    public function create_contact() {
        $this->guard(); check_admin_referer( 'bfcamel_crm_create_contact' );
        $data = isset( $_POST['contact'] ) && is_array( $_POST['contact'] ) ? wp_unslash( $_POST['contact'] ) : array();
        $tags = isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '';
        $consents = current_user_can( 'bfcamel_crm_manage_consents' ) && isset( $_POST['consents'] ) && is_array( $_POST['consents'] ) ? wp_unslash( $_POST['consents'] ) : array();
        $created = ContactService::create_manual( $data, $tags, get_current_user_id(), $consents );
        if ( is_wp_error( $created ) ) wp_die( esc_html( $created->get_error_message() ), esc_html__( 'Could not create contact', 'bfcamel-crm' ), array( 'back_link' => true ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . absint( $created ) . '&created=1' ) ); exit;
    }

    public function update_contact() {
        $this->guard(); $id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0; check_admin_referer( 'bfcamel_crm_update_contact_' . $id );
        if ( ! Schema::begin_transaction() ) wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        $data = isset( $_POST['contact'] ) && is_array( $_POST['contact'] ) ? wp_unslash( $_POST['contact'] ) : array();
        $result = ContactService::update_manual( $id, $data, get_current_user_id() );
        if ( is_wp_error( $result ) ) self::rollback_error( $result->get_error_message() );
        if ( ! Schema::commit() ) self::rollback_error( __( 'The contact transaction could not be committed.', 'bfcamel-crm' ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . $id . '&details_updated=1' ) ); exit;
    }

    public function add_note() {
        $this->guard(); $id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0; check_admin_referer( 'bfcamel_crm_add_contact_note_' . $id );
        $result = NoteService::add( 'contact', $id, isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '', get_current_user_id() );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Could not save note', 'bfcamel-crm' ), array( 'back_link' => true ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . $id . '&note_added=1' ) ); exit;
    }

    public function bulk_contacts() {
        $this->guard(); check_admin_referer( 'bfcamel_crm_bulk_contacts' );
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['contact_ids'] ?? array() ) ) ) ) );
        if ( ! $ids ) wp_die( esc_html__( 'Select at least one contact.', 'bfcamel-crm' ), '', array( 'back_link' => true ) );
        $export_selected = isset( $_POST['export_selected'] ) ? sanitize_key( $_POST['export_selected'] ) : '';
        if ( in_array( $export_selected, array( 'csv', 'xlsx' ), true ) ) {
            if ( ! current_user_can( 'bfcamel_crm_export_data' ) ) wp_die( esc_html__( 'You do not have permission to export CRM data.', 'bfcamel-crm' ) );
            Exporter::download_selected_contacts( $ids, $export_selected );
        }
        $action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
        if ( ! in_array( $action, array( 'add_tags', 'remove_tags' ), true ) ) wp_die( esc_html__( 'Choose a bulk action.', 'bfcamel-crm' ), '', array( 'back_link' => true ) );
        $incoming = array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n\r]+/u', (string) wp_unslash( $_POST['bulk_tags'] ?? '' ) ) ) ) );
        if ( ! Schema::begin_transaction() ) wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        foreach ( $ids as $id ) {
            $old = TagService::names_for_contact( $id );
            $new = 'add_tags' === $action ? array_values( array_unique( array_merge( $old, $incoming ) ) ) : array_values( array_filter( $old, static function ( $name ) use ( $incoming ) { return ! in_array( strtolower( $name ), array_map( 'strtolower', $incoming ), true ); } ) );
            $result = TagService::sync_contact( $id, implode( ', ', $new ) );
            if ( is_wp_error( $result ) ) self::rollback_error( $result->get_error_message() );
            $actual = wp_list_pluck( $result, 'name' ); $a=$old; $b=$actual; sort($a); sort($b);
            if ( $a !== $b && ! Schema::log( 'contact', $id, 'contact_tags_changed', 'Contact tags changed.', array( 'from' => $old, 'to' => $actual ), get_current_user_id() ) ) self::rollback_error( __( 'Could not record contact history.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::commit() ) self::rollback_error( __( 'The contact transaction could not be committed.', 'bfcamel-crm' ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&bulk_updated=1' ) ); exit;
    }

    public function update_tags() {
        $this->guard(); $id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0; check_admin_referer( 'bfcamel_crm_update_contact_tags_' . $id );
        if ( ! Schema::begin_transaction() ) wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        if ( ! ContactService::get( $id, true ) ) { Schema::rollback(); wp_die( esc_html__( 'Contact not found.', 'bfcamel-crm' ) ); }
        $old = TagService::names_for_contact( $id ); $result = TagService::sync_contact( $id, isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '' );
        if ( is_wp_error( $result ) ) self::rollback_error( $result->get_error_message() );
        $new = wp_list_pluck( $result, 'name' ); $a=$old; $b=$new; sort($a); sort($b);
        if ( $a !== $b && ! Schema::log( 'contact', $id, 'contact_tags_changed', 'Contact tags changed.', array( 'from' => $old, 'to' => $new ), get_current_user_id() ) ) self::rollback_error( __( 'Could not record contact history.', 'bfcamel-crm' ) );
        if ( ! Schema::commit() ) self::rollback_error( __( 'The contact transaction could not be committed.', 'bfcamel-crm' ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . $id . '&updated=1' ) ); exit;
    }

    public function update_consents() {
        $this->guard(); if ( ! current_user_can( 'bfcamel_crm_manage_consents' ) ) wp_die( esc_html__( 'You do not have permission to manage consent status.', 'bfcamel-crm' ) );
        $id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0; check_admin_referer( 'bfcamel_crm_update_contact_consents_' . $id );
        if ( ! Schema::begin_transaction() ) wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        if ( ! ContactService::get( $id, true ) ) { Schema::rollback(); wp_die( esc_html__( 'Contact not found.', 'bfcamel-crm' ) ); }
        $current = ConsentService::current_statuses( $id ); $posted = isset( $_POST['consents'] ) && is_array( $_POST['consents'] ) ? wp_unslash( $_POST['consents'] ) : array(); $statuses = ConsentService::statuses();
        foreach ( ConsentService::types() as $type => $label ) { $status = isset( $posted[$type] ) ? sanitize_key( $posted[$type] ) : $current[$type]; if ( ! isset( $statuses[$status] ) ) $status=$current[$type]; if ( $status !== $current[$type] && ! ConsentService::record_manual( $id, $type, $status, get_current_user_id() ) ) self::rollback_error( __( 'Could not record consent status.', 'bfcamel-crm' ) ); }
        if ( ! Schema::commit() ) self::rollback_error( __( 'The consent transaction could not be committed.', 'bfcamel-crm' ) );
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . $id . '&consents_updated=1' ) ); exit;
    }

    public static function filters() {
        return array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'tag_id' => isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'personal_data_consent' => isset( $_GET['personal_data_consent'] ) ? sanitize_key( $_GET['personal_data_consent'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'marketing_consent' => isset( $_GET['marketing_consent'] ) ? sanitize_key( $_GET['marketing_consent'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        );
    }

    private function create_form() {
        $all_tags = TagService::all(); $can = current_user_can( 'bfcamel_crm_manage_consents' ); ?>
        <div class="wrap bfcamel-crm-admin"><div class="bfcamel-crm-page-title"><div><h1><?php esc_html_e( 'Add contact', 'bfcamel-crm' ); ?></h1><p class="description"><?php esc_html_e( 'Create a CRM contact without a form submission.', 'bfcamel-crm' ); ?></p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts' ) ); ?>"><?php esc_html_e( 'Back', 'bfcamel-crm' ); ?></a></div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="bfcamel_crm_create_contact"><?php wp_nonce_field( 'bfcamel_crm_create_contact' ); ?><div class="bfcamel-crm-panel bfcamel-crm-form-card"><div class="bfcamel-crm-two-col"><p><label><strong><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></strong><input class="regular-text" name="contact[name]" required></label></p><p><label><strong><?php esc_html_e( 'Organization', 'bfcamel-crm' ); ?></strong><input class="regular-text" name="contact[organization]"></label></p><p><label><strong><?php esc_html_e( 'Email', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="email" name="contact[email]"></label></p><p><label><strong><?php esc_html_e( 'Phone', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="tel" name="contact[phone]"></label></p></div><p><label><strong><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></strong><input class="large-text" name="tags" list="bfcamel-crm-contact-tags-list"></label></p><datalist id="bfcamel-crm-contact-tags-list"><?php foreach($all_tags as $tag): ?><option value="<?php echo esc_attr($tag->name); ?>"><?php endforeach; ?></datalist>
        <?php if($can): ?><h3><?php esc_html_e( 'Consent status', 'bfcamel-crm' ); ?></h3><div class="bfcamel-crm-two-col"><?php foreach(ConsentService::types() as $type=>$label): ?><p><label><strong><?php echo esc_html($label); ?></strong><select name="consents[<?php echo esc_attr($type); ?>]"><?php foreach(ConsentService::statuses() as $status=>$sl): ?><option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($sl); ?></option><?php endforeach; ?></select></label></p><?php endforeach; ?></div><?php endif; ?><?php submit_button( __( 'Create contact', 'bfcamel-crm' ) ); ?></div></form></div><?php
    }

    private function detail( $id ) {
        $contact=ContactService::get($id); if(!$contact) wp_die(esc_html__('Contact not found.','bfcamel-crm'));
        $emails=ContactService::get_emails($id); $phones=ContactService::get_phones($id); $custom=ContactService::get_custom_fields($id); $tags=TagService::names_for_contact($id); $all_tags=TagService::all(); $consents=ConsentService::events_for_contact($id); $current=ConsentService::current_statuses($id,$consents); $activity=ContactService::activity($id); $notes=NoteService::for_entity('contact',$id);
        global $wpdb; $subs=current_user_can('bfcamel_crm_view_submissions')?$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Schema::table('submissions').' WHERE contact_id=%d ORDER BY submitted_at DESC,id DESC LIMIT 50',$id)):array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $primary_email=$emails?(string)$emails[0]->value:''; $primary_phone=$phones?(string)$phones[0]->value:''; ?>
        <div class="wrap bfcamel-crm-admin"><div class="bfcamel-crm-page-title"><div><h1><?php echo esc_html($contact->display_name); ?></h1><p class="description"><?php echo esc_html($contact->organization); ?></p></div><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=bfcamel-crm-contacts')); ?>"><?php esc_html_e('Back','bfcamel-crm'); ?></a></div>
        <?php foreach(array('created'=>__('Contact created.','bfcamel-crm'),'details_updated'=>__('Contact details updated.','bfcamel-crm'),'updated'=>__('Contact tags updated.','bfcamel-crm'),'consents_updated'=>__('Consent status updated.','bfcamel-crm'),'note_added'=>__('Internal note added.','bfcamel-crm')) as $flag=>$message): if(isset($_GET[$flag])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; endforeach; ?>
        <div class="bfcamel-crm-editor-grid"><main>
        <div class="bfcamel-crm-panel"><h2><?php esc_html_e('Contact details','bfcamel-crm'); ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="bfcamel_crm_update_contact"><input type="hidden" name="contact_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('bfcamel_crm_update_contact_'.$id); ?><div class="bfcamel-crm-two-col"><p><label><strong><?php esc_html_e('Name','bfcamel-crm'); ?></strong><input class="regular-text" name="contact[name]" required value="<?php echo esc_attr($contact->display_name); ?>"></label></p><p><label><strong><?php esc_html_e('Organization','bfcamel-crm'); ?></strong><input class="regular-text" name="contact[organization]" value="<?php echo esc_attr($contact->organization); ?>"></label></p><p><label><strong><?php esc_html_e('Primary email','bfcamel-crm'); ?></strong><input class="regular-text" type="email" name="contact[email]" value="<?php echo esc_attr($primary_email); ?>"></label></p><p><label><strong><?php esc_html_e('Primary phone','bfcamel-crm'); ?></strong><input class="regular-text" type="tel" name="contact[phone]" value="<?php echo esc_attr($primary_phone); ?>"></label></p></div><?php submit_button(__('Save contact','bfcamel-crm'),'primary','submit',false); ?></form><?php if($custom): ?><hr><dl class="bfcamel-crm-dl"><?php foreach($custom as $k=>$v): ?><dt><?php echo esc_html($k); ?></dt><dd><?php echo esc_html($v); ?></dd><?php endforeach; ?></dl><?php endif; ?></div>
        <div class="bfcamel-crm-panel"><h2><?php esc_html_e('Internal notes','bfcamel-crm'); ?></h2><form class="bfcamel-crm-note-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="bfcamel_crm_add_contact_note"><input type="hidden" name="contact_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('bfcamel_crm_add_contact_note_'.$id); ?><textarea name="note" rows="4" required placeholder="<?php echo esc_attr__('Add an internal note…','bfcamel-crm'); ?>"></textarea><button class="button button-primary"><?php esc_html_e('Add note','bfcamel-crm'); ?></button></form><?php $this->notes($notes); ?></div>
        <?php if(current_user_can('bfcamel_crm_view_submissions')): ?><div class="bfcamel-crm-panel"><h2><?php esc_html_e('Submissions','bfcamel-crm'); ?></h2><table class="widefat striped"><thead><tr><th>ID</th><th><?php esc_html_e('Status','bfcamel-crm'); ?></th><th><?php esc_html_e('Received','bfcamel-crm'); ?></th></tr></thead><tbody><?php if(!$subs): ?><tr><td colspan="3">—</td></tr><?php endif; foreach($subs as $sub): ?><tr><td><a href="<?php echo esc_url(admin_url('admin.php?page=bfcamel-crm-submissions&id='.absint($sub->id))); ?>">#<?php echo esc_html($sub->id); ?></a></td><td><?php echo esc_html(SubmissionService::status_label($sub->status)); ?></td><td><?php echo esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'),$sub->submitted_at)); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <div class="bfcamel-crm-panel"><h2><?php esc_html_e('Contact history','bfcamel-crm'); ?></h2><?php if(!$activity): ?><p class="description"><?php esc_html_e('No history entries yet.','bfcamel-crm'); ?></p><?php else: ?><ul class="bfcamel-crm-activity"><?php foreach($activity as $event): ?><li><div class="bfcamel-crm-activity__dot"></div><div><strong><?php echo esc_html(ActivityFormatter::message($event)); ?></strong><div class="bfcamel-crm-activity__meta"><?php echo esc_html($event->actor_name?:__('System','bfcamel-crm')); ?> · <?php echo esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'),$event->created_at)); ?></div></div></li><?php endforeach; ?></ul><?php endif; ?></div>
        </main><aside><div class="bfcamel-crm-panel bfcamel-crm-sticky"><h2><?php esc_html_e('Contact tags','bfcamel-crm'); ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="bfcamel_crm_update_contact_tags"><input type="hidden" name="contact_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('bfcamel_crm_update_contact_tags_'.$id); ?><p><input type="text" name="tags" value="<?php echo esc_attr(implode(', ',$tags)); ?>" list="bfcamel-crm-contact-tags-list"></p><datalist id="bfcamel-crm-contact-tags-list"><?php foreach($all_tags as $tag): ?><option value="<?php echo esc_attr($tag->name); ?>"><?php endforeach; ?></datalist><?php submit_button(__('Update tags','bfcamel-crm'),'primary','submit',false); ?></form></div><?php $this->consent_panel($id,$current,current_user_can('bfcamel_crm_manage_consents')); $this->consent_history($consents); ?></aside></div></div><?php
    }

    private function table( $rows ) { $statuses=ConsentService::statuses(); ?>
        <div class="bfcamel-crm-table-scroll"><table class="widefat striped bfcamel-crm-table bfcamel-crm-contacts-table"><thead><tr><td class="check-column"><input class="bfcamel-crm-select-all" type="checkbox"></td><th><?php esc_html_e('Name','bfcamel-crm'); ?></th><th><?php esc_html_e('Email','bfcamel-crm'); ?></th><th><?php esc_html_e('Phone','bfcamel-crm'); ?></th><th><?php esc_html_e('Organization','bfcamel-crm'); ?></th><th><?php esc_html_e('Personal data consent','bfcamel-crm'); ?></th><th><?php esc_html_e('Marketing consent','bfcamel-crm'); ?></th><th><?php esc_html_e('Tags','bfcamel-crm'); ?></th><th><?php esc_html_e('Updated','bfcamel-crm'); ?></th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="9"><?php esc_html_e('No contacts yet.','bfcamel-crm'); ?></td></tr><?php endif; foreach((array)$rows as $row): $pd=sanitize_key($row->personal_data_consent?:'unknown'); $mk=sanitize_key($row->marketing_consent?:'unknown'); ?><tr><th class="check-column"><input type="checkbox" name="contact_ids[]" value="<?php echo esc_attr($row->id); ?>"></th><td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=bfcamel-crm-contacts&id='.absint($row->id))); ?>"><?php echo esc_html($row->display_name); ?></a></strong></td><td><?php echo esc_html($row->email_values?:'—'); ?></td><td><?php echo esc_html($row->phone_values?:'—'); ?></td><td><?php echo esc_html($row->organization?:'—'); ?></td><td><?php $this->consent_badge($pd,$statuses); ?></td><td><?php $this->consent_badge($mk,$statuses); ?></td><td><?php echo esc_html($row->tag_names?:'—'); ?></td><td><?php echo esc_html(mysql2date(get_option('date_format'),$row->updated_at)); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
    }
    private function consent_badge($status,$labels){ $label=$labels[$status]??$status; echo '<span class="bfcamel-crm-consent bfcamel-crm-consent--'.esc_attr(sanitize_html_class($status)).'">'.esc_html($label).'</span>'; }
    private function notes($notes){ if(!$notes){ echo '<p class="description">'.esc_html__('No internal notes yet.','bfcamel-crm').'</p>'; return; } echo '<ul class="bfcamel-crm-notes">'; foreach($notes as $note){ echo '<li><div class="bfcamel-crm-note__body">'.nl2br(esc_html($note->note_text)).'</div><div class="bfcamel-crm-note__meta">'.esc_html($note->actor_name?:__('System','bfcamel-crm')).' · '.esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'),$note->created_at)).'</div></li>'; } echo '</ul>'; }
    private function consent_panel($id,$current,$can){ ?><div class="bfcamel-crm-panel"><h2><?php esc_html_e('Current consent status','bfcamel-crm'); ?></h2><?php if($can): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="bfcamel_crm_update_contact_consents"><input type="hidden" name="contact_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('bfcamel_crm_update_contact_consents_'.$id); foreach(ConsentService::types() as $type=>$label): ?><p><label><strong><?php echo esc_html($label); ?></strong><select name="consents[<?php echo esc_attr($type); ?>]"><?php foreach(ConsentService::statuses() as $status=>$sl): ?><option value="<?php echo esc_attr($status); ?>" <?php selected($current[$type],$status); ?>><?php echo esc_html($sl); ?></option><?php endforeach; ?></select></label></p><?php endforeach; submit_button(__('Update consent status','bfcamel-crm'),'primary','submit',false); ?></form><?php else: ?><dl class="bfcamel-crm-dl"><?php foreach(ConsentService::types() as $type=>$label): ?><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html(ConsentService::statuses()[$current[$type]]); ?></dd><?php endforeach; ?></dl><?php endif; ?></div><?php }
    private function consent_history($events){ ?><div class="bfcamel-crm-panel"><h2><?php esc_html_e('Consent history','bfcamel-crm'); ?></h2><?php if(!$events): ?><p>—</p><?php else: ?><ul class="bfcamel-crm-timeline"><?php foreach($events as $event): $manual=isset($event->source_type)&&'manual'===$event->source_type; ?><li><strong><?php echo esc_html(ActivityFormatter::consent_type_label($event->consent_type)); ?></strong> — <?php echo esc_html(ActivityFormatter::consent_status_label($event->status)); ?><br><small><?php echo esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'),$event->event_at)); ?> · <?php echo esc_html($manual?__('Manual change','bfcamel-crm'):__('Form submission','bfcamel-crm')); ?><?php if($manual&&!empty($event->recorded_by_name)): ?> · <?php echo esc_html($event->recorded_by_name); endif; ?></small></li><?php endforeach; ?></ul><?php endif; ?></div><?php }

    private function export_buttons($filters){ if(!current_user_can('bfcamel_crm_export_data')) return; foreach(array('csv'=>__('Export CSV','bfcamel-crm'),'xlsx'=>__('Export XLSX','bfcamel-crm')) as $format=>$label){ $args=array_merge(array('action'=>'bfcamel_crm_export','type'=>'contacts','format'=>$format,'s'=>$filters['search']),array_diff_key($filters,array('search'=>true))); $url=wp_nonce_url(add_query_arg($args,admin_url('admin-post.php')),'bfcamel_crm_export_contacts_'.$format); echo '<a class="button" href="'.esc_url($url).'">'.esc_html($label).'</a>'; } }
    private function pagination($result,$filters,$position){ if($result['total_pages']<=1)return; $base=add_query_arg(array('page'=>'bfcamel-crm-contacts','s'=>$filters['search'],'tag_id'=>$filters['tag_id'],'personal_data_consent'=>$filters['personal_data_consent'],'marketing_consent'=>$filters['marketing_consent'],'paged'=>'%#%'),admin_url('admin.php')); echo '<div class="tablenav bfcamel-crm-pagination--'.esc_attr($position).'"><div class="tablenav-pages">'.wp_kses_post(paginate_links(array('base'=>$base,'format'=>'','current'=>$result['page'],'total'=>$result['total_pages']))).'</div></div>'; }
    private function guard(){ if(!current_user_can('bfcamel_crm_manage_contacts')) wp_die(esc_html__('You do not have permission to access this page.','bfcamel-crm')); if(!Schema::is_current()) wp_die(esc_html(Schema::readiness_message()),esc_html__('CRM database update required','bfcamel-crm'),array('back_link'=>true)); }
    private static function rollback_error($message){ Schema::rollback(); wp_die(esc_html($message),esc_html__('Could not update contact','bfcamel-crm'),array('back_link'=>true)); }
}
