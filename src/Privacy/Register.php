<?php
namespace BfCamel\CRM\Privacy;

use BfCamel\CRM\Database\Schema;
use BfCamel\CRM\CRM\ContactService;
use BfCamel\CRM\Support\Request;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Register {
    const HOOK = 'bfcamel_crm_privacy_cleanup';
    const PURPOSES = array( 'volunteer' => 'Волонтёрство', 'animal' => 'Сообщение о животном', 'notice' => 'Разовое уведомление', 'marketing' => 'Учёт согласия на рассылку' );

    public static function register() {
        add_action( self::HOOK, array( __CLASS__, 'cleanup' ) );
        add_action( 'admin_post_bfcamel_crm_privacy_action', array( __CLASS__, 'action' ) );
        add_action( 'admin_post_bfcamel_crm_privacy_report', array( __CLASS__, 'report' ) );
    }

    public static function log( $operation, $details ) {
        global $wpdb;
        return false !== $wpdb->insert( Schema::table( 'privacy_register' ), array(
            'operation' => $operation, 'actor' => get_current_user_id(), 'occurred_at' => gmdate( 'Y-m-d H:i:s' ), 'details' => wp_json_encode( $details ),
        ) );
    }

    public static function cleanup() {
        global $wpdb;
        if ( ! Schema::is_current() ) return;
        $limit = current_datetime()->modify( '-12 months' )->format( 'Y-m-d H:i:s' );
        foreach ( array( 'consent_events' => 'event_at', 'submissions' => 'submitted_at' ) as $table => $date ) {
            $wpdb->query( $wpdb->prepare( "UPDATE %i SET source_ip='',user_agent='' WHERE %i < %s AND (source_ip<>'' OR user_agent<>'') ORDER BY id LIMIT 500", Schema::table( $table ), $date, $limit ) );
        }
        $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE occurred_at < %s ORDER BY id LIMIT 500', Schema::table( 'privacy_register' ), gmdate( 'Y-m-d H:i:s', strtotime( '-3 years' ) ) ) );
    }

    public static function capture( $id, $settings, $schema, $answers ) {
        $purposes = array();
        foreach ( self::PURPOSES as $key => $label ) {
            if ( 'marketing' === $key ) {
                foreach ( $schema as $field ) {
                    if ( 'consent_marketing' === $field['type'] && ! empty( $answers[ $field['name'] ] ) ) $purposes[] = $key;
                }
                continue;
            }
            $rule = trim( $settings[ 'purpose_' . $key ] ?? '' );
            if ( '*' === $rule ) $purposes[] = $key;
            elseif ( false !== strpos( $rule, '=' ) ) {
                list( $field, $value ) = explode( '=', $rule, 2 );
                if ( in_array( trim( $value ), (array) ( $answers[ trim( $field ) ] ?? array() ), true ) ) $purposes[] = $key;
            }
        }
        global $wpdb;
        foreach ( array_unique( $purposes ) as $key ) {
            $period = array( 'volunteer' => '+3 years', 'notice' => '+1 year', 'marketing' => '+3 months' );
            $due = isset( $period[ $key ] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $period[ $key ] ) ) : null;
            if ( false === $wpdb->insert( Schema::table( 'purposes' ), array( 'submission_id' => $id, 'purpose' => $key, 'reviewed_at' => gmdate( 'Y-m-d H:i:s' ), 'due_at' => $due ) ) ) return false;
        }
        return true;
    }

    private static function guard() {
        if ( ! Schema::is_current() || ! current_user_can( 'manage_options' ) || ! current_user_can( 'bfcamel_crm_manage_settings' ) ) wp_die( 'Недостаточно прав.', '', array( 'response' => 403 ) );
    }

    public static function action() {
        self::guard();
        check_admin_referer( 'bfcamel_privacy' );
        global $wpdb;
        $operation = Request::post_key( 'operation' );
        $id = Request::post_id( 'submission' );
        $reason = Request::post_text( 'reason' );
        if ( ! $id || ! $reason ) wp_die( 'Нужны номер заявки и основание.' );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Schema::table( 'submissions' ), $id ) );
        if ( ! $row ) wp_die( 'Заявка не найдена.' );
        if ( 'erase' === $operation ) {
            if ( ! Request::post_id( 'confirmed' ) ) wp_die( 'Подтвердите проверку личности и состава данных.' );
            if ( ! Privacy::instance()->erase_selected( $row, $reason ) ) wp_die( 'Уничтожение не выполнено. Проверьте журнал и повторите операцию.' );
        } else {
            $purpose = Request::post_key( 'purpose' );
            $state = Request::post_key( 'state' );
            if ( ! isset( self::PURPOSES[ $purpose ] ) || ! in_array( $state, array( 'active', 'blocked', 'closed' ), true ) ) wp_die( 'Проверьте цель и статус.' );
            $due = Request::post_text( 'due' );
            $date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $due, new \DateTimeZone( 'UTC' ) );
            if ( $due && ( ! $date || $date->format( 'Y-m-d' ) !== $due ) ) wp_die( 'Неверный срок.' );
            if ( 'notice' === $purpose && 'active' === $state ) {
                $maximum = ( new \DateTimeImmutable( $row->submitted_at, wp_timezone() ) )->modify( '+1 year' )->format( 'Y-m-d' );
                if ( ! $due || $due > $maximum ) $due = $maximum;
            }
            if ( ! Schema::begin_transaction() ) wp_die( 'Не удалось начать операцию.' );
            $ok = $wpdb->replace( Schema::table( 'purposes' ), array( 'submission_id' => $id, 'purpose' => $purpose, 'state' => $state, 'reason' => $reason, 'reviewed_at' => gmdate( 'Y-m-d H:i:s' ), 'due_at' => $due ? $due . ' 00:00:00' : null ) );
            if ( ! $ok || ! self::log( 'purpose', compact( 'id', 'purpose', 'state', 'due', 'reason' ) ) || ! Schema::commit() ) {
                Schema::rollback();
                wp_die( 'Изменение цели не сохранено.' );
            }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=bfcamel-crm-privacy' ) );
        exit;
    }

    public static function report() {
        self::guard();
        check_admin_referer( 'bfcamel_privacy_report' );
        global $wpdb;
        $id = Request::get_id( 'id' );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Schema::table( 'privacy_register' ), $id ) );
        if ( ! $row ) wp_die( 'Запись не найдена.' );
        nocache_headers();
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="crm-register-' . $id . '.json"' );
        echo wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        exit;
    }

    public static function page() {
        self::guard();
        global $wpdb;
        $goals = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY due_at IS NULL,due_at LIMIT 100', Schema::table( 'purposes' ) ) );
        $logs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', Schema::table( 'privacy_register' ) ) );
        ?>
        <div class="wrap"><h1>Обработка персональных данных</h1>
        <h2>Поиск заявок</h2>
        <form method="post"><?php wp_nonce_field( 'bfcamel_privacy_lookup' ); ?><label>Телефон <input type="tel" name="lookup"></label> <button class="button">Найти</button></form>
        <?php if ( isset( $_POST['lookup'] ) ) {
            check_admin_referer( 'bfcamel_privacy_lookup' );
            $phone = ContactService::normalize_phone( Request::post_text( 'lookup' ) );
            if ( strlen( $phone ) >= 10 ) self::matches( $phone );
        } ?>
        <h2>Цели и сроки проверки</h2><ul><?php foreach ( $goals as $goal ) : ?>
        <li>#<?php echo (int) $goal->submission_id; ?> · <?php echo esc_html( self::PURPOSES[ $goal->purpose ] ?? $goal->purpose ); ?> · <?php echo esc_html( $goal->state . ' · ' . $goal->due_at ); ?></li><?php endforeach; ?></ul>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'bfcamel_privacy' ); ?><input type="hidden" name="action" value="bfcamel_crm_privacy_action">
        <p><label>Номер заявки <input type="number" min="1" name="submission" required></label></p>
        <p><label>Цель <select name="purpose"><?php foreach ( self::PURPOSES as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; ?></select></label>
        <label>Состояние <select name="state"><option value="active">Действует</option><option value="blocked">Заблокирована</option><option value="closed">Завершена</option></select></label>
        <label>Срок проверки <input type="date" name="due"></label></p>
        <p><label>Основание <input name="reason" required></label></p>
        <button class="button" name="operation" value="purpose">Сохранить цель</button>
        <hr><label><input type="checkbox" name="confirmed" value="1"> Проверены личность, все цели заявки и состав удаляемых данных</label>
        <button class="button" name="operation" value="erase">Уничтожить данные выбранной заявки</button></form>
        <h2>Журнал операций</h2><ul><?php foreach ( $logs as $log ) : ?>
        <li><?php echo esc_html( $log->occurred_at . ' UTC · ' . $log->operation ); ?> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bfcamel_crm_privacy_report', 'id' => $log->id ), admin_url( 'admin-post.php' ) ), 'bfcamel_privacy_report' ) ); ?>">Скачать запись</a></li><?php endforeach; ?></ul></div><?php
    }

    private static function matches( $phone ) {
        global $wpdb;
        $cursor = 0;
        do {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT s.id,s.payload_json,r.schema_json,s.contact_id FROM %i s LEFT JOIN %i r ON r.id=s.revision_id WHERE s.id>%d ORDER BY s.id LIMIT 200', Schema::table( 'submissions' ), Schema::table( 'form_revisions' ), $cursor ) );
            foreach ( (array) $rows as $row ) {
                $cursor = (int) $row->id;
                $values = json_decode( $row->payload_json, true ) ?: array();
                $schema = json_decode( (string) $row->schema_json, true );
                $candidate = false;
                foreach ( (array) $schema as $field ) {
                    if ( 'tel' !== $field['type'] && 'contact.phone' !== ( $field['mapping'] ?? '' ) ) continue;
                    $value = $values[ $field['name'] ] ?? '';
                    if ( is_string( $value ) && $phone === ContactService::normalize_phone( $value ) ) $candidate = true;
                }
                if ( ! $schema ) foreach ( $values as $value ) if ( is_string( $value ) && $phone === ContactService::normalize_phone( $value ) ) $candidate = true;
                if ( $candidate ) echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . $cursor ) ) . '">Заявка #' . $cursor . '</a></p>';
            }
        } while ( count( (array) $rows ) === 200 );
    }
}
