<?php
namespace BfCamel\CRM\Admin;

use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\CRM\SubmissionService;
use BfCamel\CRM\CRM\WorkflowService;
use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\Forms\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DashboardPage {
    public static function render() {
        if ( ! current_user_can( 'bfcamel_crm_view_dashboard' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'bfcamel-crm' ) );
        }
        if ( ! Schema::is_current() ) {
            wp_die( esc_html( Schema::readiness_message() ), esc_html__( 'CRM database update required', 'bfcamel-crm' ), array( 'back_link' => true ) );
        }

        $can_submissions = current_user_can( 'bfcamel_crm_view_submissions' );
        $can_contacts = current_user_can( 'bfcamel_crm_manage_contacts' );
        $can_forms = current_user_can( 'bfcamel_crm_manage_forms' );
        $cards = array();
        $recent = array( 'rows' => array() );
        $workload = array();

        if ( $can_submissions ) {
            $counts = SubmissionService::dashboard_counts();
            $recent = SubmissionService::query( array(), 1, 8 );
            $workload = SubmissionService::workload();
            $cards[] = array( 'value' => $counts['total'], 'label' => __( 'All submissions', 'bfcamel-crm' ), 'url' => 'admin.php?page=bfcamel-crm-submissions', 'tone' => 'neutral' );
            $cards[] = array( 'value' => $counts['new'], 'label' => sprintf( '%s: %s', __( 'Status', 'bfcamel-crm' ), WorkflowService::label( 'status', $counts['default_status'] ) ), 'url' => 'admin.php?page=bfcamel-crm-submissions&status=' . $counts['default_status'], 'tone' => 'blue' );
            $cards[] = array( 'value' => $counts['urgent'], 'label' => sprintf( '%s: %s', __( 'Priority', 'bfcamel-crm' ), WorkflowService::label( 'priority', $counts['top_priority'] ) ), 'url' => 'admin.php?page=bfcamel-crm-submissions&priority=' . $counts['top_priority'], 'tone' => 'red' );
            $cards[] = array( 'value' => $counts['unassigned'], 'label' => __( 'Unassigned submissions', 'bfcamel-crm' ), 'url' => 'admin.php?page=bfcamel-crm-submissions&assigned_to=0', 'tone' => 'amber' );
            if ( $counts['review_status'] ) $cards[] = array( 'value' => $counts['needs_review'], 'label' => __( 'Need review', 'bfcamel-crm' ), 'url' => 'admin.php?page=bfcamel-crm-submissions&status=' . $counts['review_status'], 'tone' => 'amber' );
        }
        if ( $can_contacts ) {
            $cards[] = array( 'value' => ContactService::count(), 'label' => __( 'Contacts', 'bfcamel-crm' ), 'url' => 'admin.php?page=bfcamel-crm-contacts', 'tone' => 'green' );
        }
        if ( $can_forms ) {
            $cards[] = array( 'value' => count( Repository::all( false ) ), 'label' => __( 'Forms', 'bfcamel-crm' ), 'url' => 'admin.php?page=bfcamel-crm-forms', 'tone' => 'neutral' );
        }
        ?>
        <div class="wrap bfcamel-crm-admin">
            <div class="bfcamel-crm-page-title">
                <div>
                    <h1><?php esc_html_e( 'BfCamel CRM', 'bfcamel-crm' ); ?></h1>
                    <p class="description"><?php esc_html_e( 'Current workload and the latest CRM activity.', 'bfcamel-crm' ); ?></p>
                </div>
                <div class="bfcamel-crm-actions bfcamel-crm-dashboard-actions">
                    <?php if ( current_user_can( 'bfcamel_crm_manage_contacts' ) ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&action=new' ) ); ?>"><?php esc_html_e( 'Add contact', 'bfcamel-crm' ); ?></a>
                    <?php endif; ?>
                    <?php if ( current_user_can( 'bfcamel_crm_manage_forms' ) ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-forms&action=new' ) ); ?>"><?php esc_html_e( 'Add form', 'bfcamel-crm' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bfcamel-crm-stats bfcamel-crm-stats--six">
                <?php foreach ( $cards as $card ) : ?>
                    <a class="bfcamel-crm-stat bfcamel-crm-stat--<?php echo esc_attr( $card['tone'] ); ?>" href="<?php echo esc_url( admin_url( $card['url'] ) ); ?>">
                        <strong><?php echo esc_html( number_format_i18n( $card['value'] ) ); ?></strong>
                        <span><?php echo esc_html( $card['label'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ( $can_submissions ) : ?>
            <div class="bfcamel-crm-dashboard-grid">
                <div class="bfcamel-crm-panel bfcamel-crm-panel--table">
                    <div class="bfcamel-crm-panel__head bfcamel-crm-panel__head--padded">
                        <h2><?php esc_html_e( 'Recent submissions', 'bfcamel-crm' ); ?></h2>
                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions' ) ); ?>"><?php esc_html_e( 'View all', 'bfcamel-crm' ); ?></a>
                    </div>
                    <?php self::recent_table( $recent['rows'] ); ?>
                </div>

                <div class="bfcamel-crm-panel">
                    <h2><?php esc_html_e( 'Open workload', 'bfcamel-crm' ); ?></h2>
                    <?php if ( ! $workload ) : ?>
                        <p class="description"><?php esc_html_e( 'No open submissions.', 'bfcamel-crm' ); ?></p>
                    <?php else : ?>
                        <ul class="bfcamel-crm-workload">
                            <?php foreach ( $workload as $item ) : ?>
                                <li>
                                    <span><?php echo esc_html( $item->assignee_name ?: __( 'Unassigned', 'bfcamel-crm' ) ); ?></span>
                                    <strong><?php echo esc_html( number_format_i18n( $item->total ) ); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ( ! $cards ) : ?>
                <div class="bfcamel-crm-panel"><p><?php esc_html_e( 'No dashboard data is available for your role.', 'bfcamel-crm' ); ?></p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function recent_table( $rows ) {
        ?>
        <div class="bfcamel-crm-table-scroll">
            <table class="widefat striped bfcamel-crm-table">
                <thead><tr><th>ID</th><th><?php esc_html_e( 'Form', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Contact', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Status', 'bfcamel-crm' ); ?></th><th><?php esc_html_e( 'Received', 'bfcamel-crm' ); ?></th></tr></thead>
                <tbody>
                <?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No submissions yet.', 'bfcamel-crm' ); ?></td></tr><?php endif; ?>
                <?php foreach ( (array) $rows as $row ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . absint( $row->id ) ) ); ?>">#<?php echo esc_html( $row->id ); ?></a></td>
                        <td><?php echo esc_html( $row->form_name ?: '#' . $row->form_id ); ?></td>
                        <td><?php echo esc_html( $row->contact_name ?: '—' ); ?></td>
                        <td><span class="bfcamel-crm-badge bfcamel-crm-badge--<?php echo esc_attr( sanitize_html_class( $row->status ) ); ?>"><?php echo esc_html( SubmissionService::status_label( $row->status ) ); ?></span></td>
                        <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->submitted_at ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
