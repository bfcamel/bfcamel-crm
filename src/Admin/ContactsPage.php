<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\Consent\ConsentService;
use BfCamel\CRM\CRM\ActivityFormatter;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContactsPage {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function page() {
        $this->guard();
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'new' === $action ) {
            $this->create_form();
            return;
        }
        if ( $id ) {
            $this->detail( $id );
            return;
        }

        $filters = self::filters();
        $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $result = ContactService::query( $filters, $page, 25 );
        $tags = TagService::all();
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div><h1><?php esc_html_e( 'Contacts', 'bfcamel-crm' ); ?></h1><p class="description"><?php esc_html_e( 'Search contacts, organize them with tags, or add a contact manually.', 'bfcamel-crm' ); ?></p></div>
                <div class="bfcamel-crm-actions">
                    <?php $this->export_buttons( $filters ); ?>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&action=new' ) ); ?>"><?php esc_html_e( 'Add contact', 'bfcamel-crm' ); ?></a>
                </div>
            </div>

            <form method="get" class="bfcamel-crm-filters">
                <input type="hidden" name="page" value="bfcamel-crm-contacts">
                <div class="bfcamel-crm-filter-search"><label class="screen-reader-text" for="bfcamel-crm-contact-search"><?php esc_html_e( 'Search contacts', 'bfcamel-crm' ); ?></label><input id="bfcamel-crm-contact-search" type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr__( 'Search by name, organization, email or phone', 'bfcamel-crm' ); ?>"></div>
                <select name="tag_id" aria-label="<?php echo esc_attr__( 'Tag', 'bfcamel-crm' ); ?>"><option value="0"><?php esc_html_e( 'All tags', 'bfcamel-crm' ); ?></option><?php foreach ( $tags as $tag ) : ?><option value="<?php echo esc_attr( $tag->id ); ?>" <?php selected( $filters['tag_id'], $tag->id ); ?>><?php echo esc_html( $tag->name ); ?></option><?php endforeach; ?></select>
                <button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'bfcamel-crm' ); ?></button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts' ) ); ?>"><?php esc_html_e( 'Reset', 'bfcamel-crm' ); ?></a>
            </form>

            <?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Contact created.', 'bfcamel-crm' ); ?></p></div>
            <?php endif; ?>

            <p class="bfcamel-crm-results-count"><?php echo esc_html( sprintf( __( 'Found: %d', 'bfcamel-crm' ), $result['total'] ) ); ?></p>
            <div class="bfcamel-crm-panel bfcamel-crm-panel--table"><?php $this->table( $result['rows'] ); ?></div>
            <?php $this->pagination( $result, $filters ); ?>
        </div>
        <?php
    }

    public function create_contact() {
        $this->guard();
        check_admin_referer( 'bfcamel_crm_create_contact' );
        $data = isset( $_POST['contact'] ) && is_array( $_POST['contact'] ) ? wp_unslash( $_POST['contact'] ) : array();
        $tags = isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '';
        $consents = current_user_can( 'bfcamel_crm_manage_consents' ) && isset( $_POST['consents'] ) && is_array( $_POST['consents'] ) ? wp_unslash( $_POST['consents'] ) : array();
        $created = ContactService::create_manual( $data, $tags, get_current_user_id(), $consents );
        if ( is_wp_error( $created ) ) {
            wp_die( esc_html( $created->get_error_message() ), esc_html__( 'Could not create contact', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . absint( $created ) . '&created=1' ) );
        exit;
    }

    public function update_tags() {
        $this->guard();
        $id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
        check_admin_referer( 'bfcamel_crm_update_contact_tags_' . $id );
        if ( ! Schema::begin_transaction() ) {
            wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }
        if ( ! ContactService::get( $id, true ) ) {
            Schema::rollback();
            wp_die( esc_html__( 'Contact not found.', 'bfcamel-crm' ) );
        }

        $old_tags = TagService::names_for_contact( $id );
        $raw_tags = isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '';
        $result = TagService::sync_contact( $id, $raw_tags );
        if ( is_wp_error( $result ) ) {
            self::rollback_error( $result->get_error_message() );
        }
        $new_tags = wp_list_pluck( $result, 'name' );
        $old_compare = $old_tags;
        $new_compare = $new_tags;
        sort( $old_compare );
        sort( $new_compare );
        if ( $old_compare !== $new_compare && ! Schema::log( 'contact', $id, 'contact_tags_changed', 'Contact tags changed.', array( 'from' => $old_tags, 'to' => $new_tags ), get_current_user_id() ) ) {
            self::rollback_error( __( 'Could not record contact history.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::commit() ) {
            self::rollback_error( __( 'The contact transaction could not be committed.', 'bfcamel-crm' ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . $id . '&updated=1' ) );
        exit;
    }

    public function update_consents() {
        $this->guard();
        if ( ! current_user_can( 'bfcamel_crm_manage_consents' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage consent status.', 'bfcamel-crm' ) );
        }

        $id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
        check_admin_referer( 'bfcamel_crm_update_contact_consents_' . $id );
        if ( ! Schema::begin_transaction() ) {
            wp_die( esc_html__( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        }
        if ( ! ContactService::get( $id, true ) ) {
            Schema::rollback();
            wp_die( esc_html__( 'Contact not found.', 'bfcamel-crm' ) );
        }

        $current = ConsentService::current_statuses( $id );
        $posted = isset( $_POST['consents'] ) && is_array( $_POST['consents'] ) ? wp_unslash( $_POST['consents'] ) : array();
        $statuses = ConsentService::statuses();
        foreach ( ConsentService::types() as $type => $label ) {
            $status = isset( $posted[ $type ] ) ? sanitize_key( $posted[ $type ] ) : $current[ $type ];
            if ( ! isset( $statuses[ $status ] ) ) {
                $status = $current[ $type ];
            }
            if ( $status !== $current[ $type ] && ! ConsentService::record_manual( $id, $type, $status, get_current_user_id() ) ) {
                self::rollback_error( __( 'Could not record consent status.', 'bfcamel-crm' ) );
            }
        }
        if ( ! Schema::commit() ) {
            self::rollback_error( __( 'The consent transaction could not be committed.', 'bfcamel-crm' ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . $id . '&consents_updated=1' ) );
        exit;
    }

    public static function filters() {
        return array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'tag_id' => isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        );
    }

    private function create_form() {
        $all_tags = TagService::all();
        $can_manage_consents = current_user_can( 'bfcamel_crm_manage_consents' );
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title"><div><h1><?php esc_html_e( 'Add contact', 'bfcamel-crm' ); ?></h1><p class="description"><?php esc_html_e( 'Create a CRM contact without a form submission.', 'bfcamel-crm' ); ?></p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts' ) ); ?>"><?php esc_html_e( 'Back', 'bfcamel-crm' ); ?></a></div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="bfcamel_crm_create_contact"><?php wp_nonce_field( 'bfcamel_crm_create_contact' ); ?>
                <div class="bfcamel-crm-panel bfcamel-crm-form-card">
                    <div class="bfcamel-crm-two-col">
                        <p><label><strong><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="text" name="contact[name]" required></label></p>
                        <p><label><strong><?php esc_html_e( 'Organization', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="text" name="contact[organization]"></label></p>
                        <p><label><strong><?php esc_html_e( 'Email', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="email" name="contact[email]"></label></p>
                        <p><label><strong><?php esc_html_e( 'Phone', 'bfcamel-crm' ); ?></strong><input class="regular-text" type="tel" name="contact[phone]"></label></p>
                    </div>
                    <p><label><strong><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></strong><input class="large-text" type="text" name="tags" list="bfcamel-crm-contact-tags-list" placeholder="<?php echo esc_attr__( 'e.g. donor, volunteer, partner', 'bfcamel-crm' ); ?>"></label></p>
                    <datalist id="bfcamel-crm-contact-tags-list"><?php foreach ( $all_tags as $tag ) : ?><option value="<?php echo esc_attr( $tag->name ); ?>"><?php endforeach; ?></datalist>
                    <p class="description"><?php esc_html_e( 'Separate tags with commas.', 'bfcamel-crm' ); ?></p>
                    <?php if ( $can_manage_consents ) : ?>
                        <h3><?php esc_html_e( 'Consent status', 'bfcamel-crm' ); ?></h3>
                        <div class="bfcamel-crm-two-col">
                            <?php foreach ( ConsentService::types() as $type => $label ) : ?>
                                <p><label><strong><?php echo esc_html( $label ); ?></strong><select name="consents[<?php echo esc_attr( $type ); ?>]"><?php foreach ( ConsentService::statuses() as $status => $status_label ) : ?><option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></option><?php endforeach; ?></select></label></p>
                            <?php endforeach; ?>
                        </div>
                        <p class="description"><?php esc_html_e( 'Selected consent values will be stored as manual audit events. Form submissions record consent choices automatically.', 'bfcamel-crm' ); ?></p>
                    <?php endif; ?>
                    <?php submit_button( __( 'Create contact', 'bfcamel-crm' ) ); ?>
                </div>
            </form>
        </div>
        <?php
    }

    private function detail( $id ) {
        $contact = ContactService::get( $id );
        if ( ! $contact ) {
            wp_die( esc_html__( 'Contact not found.', 'bfcamel-crm' ) );
        }
        $emails = ContactService::get_emails( $id );
        $phones = ContactService::get_phones( $id );
        $custom = ContactService::get_custom_fields( $id );
        $tags = TagService::names_for_contact( $id );
        $all_tags = TagService::all();
        $consents = ConsentService::events_for_contact( $id );
        $current_consents = ConsentService::current_statuses( $id, $consents );
        $can_manage_consents = current_user_can( 'bfcamel_crm_manage_consents' );
        $activity = ContactService::activity( $id );
        global $wpdb;
        $subs = current_user_can( 'bfcamel_crm_view_submissions' )
            ? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'submissions' ) . ' WHERE contact_id=%d ORDER BY submitted_at DESC,id DESC LIMIT 50', $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            : array();
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title"><div><h1><?php echo esc_html( $contact->display_name ); ?></h1><p class="description"><?php echo esc_html( $contact->organization ); ?></p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts' ) ); ?>"><?php esc_html_e( 'Back', 'bfcamel-crm' ); ?></a></div>
            <?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Contact created.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Contact tags updated.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <?php if ( isset( $_GET['consents_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Consent status updated.', 'bfcamel-crm' ); ?></p></div><?php endif; ?>
            <div class="bfcamel-crm-editor-grid">
                <main>
                    <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Contact details', 'bfcamel-crm' ); ?></h2><dl class="bfcamel-crm-dl"><dt><?php esc_html_e( 'Email', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( $emails ? implode( ', ', wp_list_pluck( $emails, 'value' ) ) : '—' ); ?></dd><dt><?php esc_html_e( 'Phone', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( $phones ? implode( ', ', wp_list_pluck( $phones, 'value' ) ) : '—' ); ?></dd><dt><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></dt><dd><?php echo esc_html( $tags ? implode( ', ', $tags ) : '—' ); ?></dd><?php foreach ( $custom as $key => $value ) : ?><dt><?php echo esc_html( $key ); ?></dt><dd><?php echo esc_html( $value ); ?></dd><?php endforeach; ?></dl></div>
                    <?php if ( current_user_can( 'bfcamel_crm_view_submissions' ) ) : ?><div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Submissions', 'bfcamel-crm' ); ?></h2><table class="widefat striped"><thead><tr><th>ID</th><th><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Received', 'bfcamel-crm' ); ?></th></tr></thead><tbody><?php if ( ! $subs ) : ?><tr><td colspan="3">—</td></tr><?php endif; ?><?php foreach ( $subs as $sub ) : ?><tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . absint( $sub->id ) ) ); ?>">#<?php echo esc_html( $sub->id ); ?></a></td><td><?php echo esc_html( SubmissionService::status_label( $sub->status ) ); ?></td><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sub->submitted_at ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                    <div class="bfcamel-crm-panel"><h2><?php esc_html_e( 'Contact history', 'bfcamel-crm' ); ?></h2><?php if ( ! $activity ) : ?><p class="description"><?php esc_html_e( 'No history entries yet.', 'bfcamel-crm' ); ?></p><?php else : ?><ul class="bfcamel-crm-activity"><?php foreach ( $activity as $event ) : ?><li><div class="bfcamel-crm-activity__dot"></div><div><strong><?php echo esc_html( ActivityFormatter::message( $event ) ); ?></strong><div class="bfcamel-crm-activity__meta"><?php echo esc_html( $event->actor_name ?: __( 'System', 'bfcamel-crm' ) ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->created_at ) ); ?></div></div></li><?php endforeach; ?></ul><?php endif; ?></div>
                </main>
                <aside>
                    <div class="bfcamel-crm-panel bfcamel-crm-sticky"><h2><?php esc_html_e( 'Contact tags', 'bfcamel-crm' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="bfcamel_crm_update_contact_tags"><input type="hidden" name="contact_id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'bfcamel_crm_update_contact_tags_' . $id ); ?><p><label><span class="screen-reader-text"><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></span><input type="text" name="tags" value="<?php echo esc_attr( implode( ', ', $tags ) ); ?>" list="bfcamel-crm-contact-tags-list"></label></p><datalist id="bfcamel-crm-contact-tags-list"><?php foreach ( $all_tags as $tag ) : ?><option value="<?php echo esc_attr( $tag->name ); ?>"><?php endforeach; ?></datalist><p class="description"><?php esc_html_e( 'Separate tags with commas.', 'bfcamel-crm' ); ?></p><?php submit_button( __( 'Update tags', 'bfcamel-crm' ), 'primary', 'submit', false ); ?></form></div>
                    <?php $this->consent_panel( $id, $current_consents, $can_manage_consents ); ?>
                    <?php $this->consent_history( $consents ); ?>
                </aside>
            </div>
        </div>
        <?php
    }

    private function table( $rows ) {
        ?><div class="bfcamel-crm-table-scroll"><table class="widefat striped bfcamel-crm-table"><thead><tr><th><?php esc_html_e( 'Name', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Email', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Phone', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Organization', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Updated', 'bfcamel-crm' ); ?></th></tr></thead><tbody>
        <?php if ( ! $rows ) : ?><tr><td colspan="6"><?php esc_html_e( 'No contacts yet.', 'bfcamel-crm' ); ?></td></tr><?php endif; ?>
        <?php foreach ( (array) $rows as $row ) : ?><tr><td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . absint( $row->id ) ) ); ?>"><?php echo esc_html( $row->display_name ); ?></a></strong></td><td><?php echo esc_html( $row->email_values ?: '—' ); ?></td><td><?php echo esc_html( $row->phone_values ?: '—' ); ?></td><td><?php echo esc_html( $row->organization ?: '—' ); ?></td><td><?php echo esc_html( $row->tag_names ?: '—' ); ?></td><td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row->updated_at ) ); ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php
    }

    private function consent_panel( $contact_id, $current, $can_manage ) {
        ?>
        <div class="bfcamel-crm-panel">
            <h2><?php esc_html_e( 'Current consent status', 'bfcamel-crm' ); ?></h2>
            <?php if ( $can_manage ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="bfcamel_crm_update_contact_consents">
                    <input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact_id ); ?>">
                    <?php wp_nonce_field( 'bfcamel_crm_update_contact_consents_' . $contact_id ); ?>
                    <?php foreach ( ConsentService::types() as $type => $label ) : ?>
                        <p><label><strong><?php echo esc_html( $label ); ?></strong><select name="consents[<?php echo esc_attr( $type ); ?>]"><?php foreach ( ConsentService::statuses() as $status => $status_label ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current[ $type ], $status ); ?>><?php echo esc_html( $status_label ); ?></option><?php endforeach; ?></select></label></p>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e( 'Every change creates a new audit event; previous consent history is retained.', 'bfcamel-crm' ); ?></p>
                    <?php submit_button( __( 'Update consent status', 'bfcamel-crm' ), 'primary', 'submit', false ); ?>
                </form>
            <?php else : ?>
                <dl class="bfcamel-crm-dl">
                    <?php foreach ( ConsentService::types() as $type => $label ) : ?><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( ConsentService::statuses()[ $current[ $type ] ] ); ?></dd><?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
        <?php
    }

    private function consent_history( $events ) {
        ?>
        <div class="bfcamel-crm-panel">
            <h2><?php esc_html_e( 'Consent history', 'bfcamel-crm' ); ?></h2>
            <?php if ( ! $events ) : ?>
                <p>—</p>
            <?php else : ?>
                <ul class="bfcamel-crm-timeline">
                    <?php foreach ( $events as $event ) : ?>
                        <?php
                        $manual = isset( $event->source_type ) && 'manual' === $event->source_type;
                        $source = $manual
                            ? __( 'Manual change', 'bfcamel-crm' )
                            : ( $event->submission_id ? sprintf( __( 'Form submission #%d', 'bfcamel-crm' ), $event->submission_id ) : __( 'Form submission', 'bfcamel-crm' ) );
                        ?>
                        <li>
                            <strong><?php echo esc_html( ActivityFormatter::consent_type_label( $event->consent_type ) ); ?></strong> — <?php echo esc_html( ActivityFormatter::consent_status_label( $event->status ) ); ?><br>
                            <small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->event_at ) ); ?> · <?php echo esc_html( $source ); ?><?php if ( $manual && ! empty( $event->recorded_by_name ) ) : ?> · <?php echo esc_html( $event->recorded_by_name ); ?><?php endif; ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    private function export_buttons( $filters ) {
        if ( ! current_user_can( 'bfcamel_crm_export_data' ) ) {
            return;
        }
        foreach ( array( 'csv' => __( 'Export CSV', 'bfcamel-crm' ), 'xlsx' => __( 'Export XLSX', 'bfcamel-crm' ) ) as $format => $label ) {
            $url = add_query_arg( array( 'action' => 'bfcamel_crm_export', 'type' => 'contacts', 'format' => $format, 's' => $filters['search'], 'tag_id' => $filters['tag_id'] ), admin_url( 'admin-post.php' ) );
            $url = wp_nonce_url( $url, 'bfcamel_crm_export_contacts_' . $format );
            echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
    }

    private function pagination( $result, $filters ) {
        if ( $result['total_pages'] <= 1 ) {
            return;
        }
        $base = add_query_arg( array( 'page' => 'bfcamel-crm-contacts', 's' => $filters['search'], 'tag_id' => $filters['tag_id'], 'paged' => '%#%' ), admin_url( 'admin.php' ) );
        echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => $base, 'format' => '', 'current' => $result['page'], 'total' => $result['total_pages'] ) ) ) . '</div></div>';
    }

    private function guard() {
        if ( ! current_user_can( 'bfcamel_crm_manage_contacts' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::is_current() ) {
            wp_die( esc_html( Schema::readiness_message() ), esc_html__( 'CRM database update required', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
    }

    private static function rollback_error( $message ) {
        Schema::rollback();
        wp_die( esc_html( $message ), esc_html__( 'Could not update contact', 'bfcamel-crm' ), array( 'back_link' => true ) );
    }
}
