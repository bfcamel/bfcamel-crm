<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\TagService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SubmissionsPage {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function page() {
        $this->guard( 'bfcamel_crm_view_submissions' );
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $id ) {
            SubmissionDetailPage::render( $id );
            return;
        }

        $filters = $this->filters();
        $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $result = SubmissionService::query( $filters, $page, 25 );
        $forms = Repository::all();
        $assignees = SubmissionService::assignees();
        $tags = TagService::all();
        $statuses = SubmissionService::statuses();
        $priorities = SubmissionService::priorities();
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div>
                    <h1><?php esc_html_e( 'Submissions', 'bfcamel-crm' ); ?></h1>
                    <p class="description"><?php esc_html_e( 'Search, filter and manage incoming submissions.', 'bfcamel-crm' ); ?></p>
                </div>
                <?php if ( current_user_can( 'bfcamel_crm_export_data' ) ) : ?>
                    <div class="bfcamel-crm-actions">
                        <?php foreach ( array( 'csv' => __( 'Export CSV', 'bfcamel-crm' ), 'xlsx' => __( 'Export XLSX', 'bfcamel-crm' ) ) as $format => $label ) : ?>
                            <?php
                            $args = array_merge( array( 'action' => 'bfcamel_crm_export', 'type' => 'submissions', 'format' => $format, 's' => $filters['search'] ), array_diff_key( $filters, array( 'search' => true ) ) );
                            $url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'bfcamel_crm_export_submissions_' . $format );
                            ?>
                            <a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="get" class="bfcamel-crm-filters">
                <input type="hidden" name="page" value="bfcamel-crm-submissions">
                <div class="bfcamel-crm-filter-search">
                    <label class="screen-reader-text" for="bfcamel-crm-search"><?php esc_html_e( 'Search submissions', 'bfcamel-crm' ); ?></label>
                    <input id="bfcamel-crm-search" type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr__( 'Search by ID, name, email, phone or form data', 'bfcamel-crm' ); ?>">
                </div>

                <select name="status" aria-label="<?php echo esc_attr__( 'Status', 'bfcamel-crm' ); ?>">
                    <option value=""><?php esc_html_e( 'All statuses', 'bfcamel-crm' ); ?></option>
                    <?php foreach ( $statuses as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="form_id" aria-label="<?php echo esc_attr__( 'Form', 'bfcamel-crm' ); ?>">
                    <option value="0"><?php esc_html_e( 'All forms', 'bfcamel-crm' ); ?></option>
                    <?php foreach ( $forms as $form ) : ?>
                        <option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $filters['form_id'], $form->id ); ?>><?php echo esc_html( $form->name ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="assigned_to" aria-label="<?php echo esc_attr__( 'Responsible', 'bfcamel-crm' ); ?>">
                    <option value="" <?php selected( $filters['assigned_to'], '' ); ?>><?php esc_html_e( 'All responsible employees', 'bfcamel-crm' ); ?></option>
                    <option value="0" <?php selected( (string) $filters['assigned_to'], '0' ); ?>><?php esc_html_e( 'Unassigned', 'bfcamel-crm' ); ?></option>
                    <?php foreach ( $assignees as $user ) : ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( (string) $filters['assigned_to'], (string) $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="priority" aria-label="<?php echo esc_attr__( 'Priority', 'bfcamel-crm' ); ?>">
                    <option value=""><?php esc_html_e( 'All priorities', 'bfcamel-crm' ); ?></option>
                    <?php foreach ( $priorities as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['priority'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="tag_id" aria-label="<?php echo esc_attr__( 'Tag', 'bfcamel-crm' ); ?>">
                    <option value="0"><?php esc_html_e( 'All tags', 'bfcamel-crm' ); ?></option>
                    <?php foreach ( $tags as $tag ) : ?>
                        <option value="<?php echo esc_attr( $tag->id ); ?>" <?php selected( $filters['tag_id'], $tag->id ); ?>><?php echo esc_html( $tag->name ); ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="bfcamel-crm-date-filter"><span><?php esc_html_e( 'From', 'bfcamel-crm' ); ?></span><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
                <label class="bfcamel-crm-date-filter"><span><?php esc_html_e( 'To', 'bfcamel-crm' ); ?></span><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'bfcamel-crm' ); ?></button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions' ) ); ?>"><?php esc_html_e( 'Reset', 'bfcamel-crm' ); ?></a>
            </form>

            <p class="bfcamel-crm-results-count"><?php echo esc_html( sprintf( __( 'Found: %d', 'bfcamel-crm' ), $result['total'] ) ); ?></p>
            <div class="bfcamel-crm-panel bfcamel-crm-panel--table"><?php $this->table( $result['rows'] ); ?></div>
            <?php $this->pagination( $result ); ?>
        </div>
        <?php
    }

    public function update_submission() {
        $this->guard( 'bfcamel_crm_edit_submissions' );
        SubmissionDetailPage::update();
    }

    private function table( $rows ) {
        ?>
        <div class="bfcamel-crm-table-scroll">
            <table class="widefat striped bfcamel-crm-table bfcamel-crm-submissions-table">
                <thead><tr>
                    <th>ID</th>
                    <th><?php esc_html_e( 'Form', 'bfcamel-crm' ); ?></th>
                    <th><?php esc_html_e( 'Contact', 'bfcamel-crm' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></th>
                    <th><?php esc_html_e( 'Priority', 'bfcamel-crm' ); ?></th>
                    <th><?php esc_html_e( 'Responsible', 'bfcamel-crm' ); ?></th>
                    <th><?php esc_html_e( 'Tags', 'bfcamel-crm' ); ?></th>
                    <th><?php esc_html_e( 'Received', 'bfcamel-crm' ); ?></th>
                </tr></thead>
                <tbody>
                    <?php if ( ! $rows ) : ?><tr><td colspan="8"><?php esc_html_e( 'No submissions yet.', 'bfcamel-crm' ); ?></td></tr><?php endif; ?>
                    <?php foreach ( (array) $rows as $row ) : ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . absint( $row->id ) ) ); ?>">#<?php echo esc_html( $row->id ); ?></a></strong></td>
                            <td><?php echo esc_html( $row->form_name ?: '#' . $row->form_id ); ?></td>
                            <td><?php echo esc_html( $row->contact_name ?: ( 'conflict' === $row->contact_sync_status ? __( 'Needs review', 'bfcamel-crm' ) : '—' ) ); ?></td>
                            <td><span class="bfcamel-crm-badge bfcamel-crm-badge--<?php echo esc_attr( sanitize_html_class( $row->status ) ); ?>"><?php echo esc_html( SubmissionService::status_label( $row->status ) ); ?></span></td>
                            <td><span class="bfcamel-crm-priority bfcamel-crm-priority--<?php echo esc_attr( sanitize_html_class( $row->priority ?: 'normal' ) ); ?>"><?php echo esc_html( SubmissionService::priority_label( $row->priority ?: 'normal' ) ); ?></span></td>
                            <td><?php echo esc_html( $row->assignee_name ?: __( 'Unassigned', 'bfcamel-crm' ) ); ?></td>
                            <td><?php $this->tags( $row->tag_names ); ?></td>
                            <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->submitted_at ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function tags( $tag_names ) {
        $names = array_filter( array_map( 'trim', explode( ',', (string) $tag_names ) ) );
        if ( ! $names ) {
            echo '—';
            return;
        }
        foreach ( $names as $name ) {
            echo '<span class="bfcamel-crm-tag">' . esc_html( $name ) . '</span> ';
        }
    }

    private function pagination( $result ) {
        if ( $result['total_pages'] <= 1 ) {
            return;
        }
        $base = remove_query_arg( 'paged' );
        $base = add_query_arg( 'paged', '%#%', $base );
        $links = paginate_links(
            array(
                'base'      => $base,
                'format'    => '',
                'current'   => $result['page'],
                'total'     => $result['total_pages'],
                'prev_text' => '&lsaquo;',
                'next_text' => '&rsaquo;',
            )
        );
        if ( $links ) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
        }
    }

    private function filters() {
        return array(
            'search'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'status'      => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'form_id'     => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'assigned_to' => isset( $_GET['assigned_to'] ) ? sanitize_text_field( wp_unslash( $_GET['assigned_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'priority'    => isset( $_GET['priority'] ) ? sanitize_key( $_GET['priority'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'tag_id'      => isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        );
    }

    private function guard( $capability ) {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::is_current() ) {
            wp_die( esc_html( Schema::readiness_message() ), esc_html__( 'CRM database update required', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }
    }
}
