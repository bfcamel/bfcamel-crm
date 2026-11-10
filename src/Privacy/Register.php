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
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'bfcamel_crm_manage_settings' ) ) wp_die( 'Недостаточно прав.', '', array( 'response' => 403 ) );
        if ( ! Schema::is_current() ) wp_die( esc_html( Schema::readiness_message() ), '', array( 'response' => 503 ) );
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
        if ( 'act' === Request::get_key( 'format' ) && 'erase_submission' === $row->operation ) {
            $info = json_decode( $row->details, true );
            header( 'Content-Type: text/plain; charset=UTF-8' );
            header( 'Content-Disposition: attachment; filename="crm-act-' . $id . '.txt"' );
            echo "ПРОЕКТ АКТА ОБ УНИЧТОЖЕНИИ ПЕРСОНАЛЬНЫХ ДАННЫХ\n";
            echo "Дата и время (UTC): " . $row->occurred_at . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This response is a plain-text document.
            foreach ( $info as $name => $value ) echo $name . ': ' . $value . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This response is a plain-text document.
            echo "Обработчик по поручению (если применимо): __________________\nДолжность и подпись ответственного: __________________";
            exit;
        }
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
        $states = array( 'active' => 'Действует', 'closed' => 'Завершена', 'blocked' => 'Заблокирована' );
        $operations = array( 'purpose' => 'Изменение цели', 'export' => 'Выгрузка', 'erase_submission' => 'Уничтожение данных заявки', 'erase_failed' => 'Ошибка уничтожения', 'erase_requested' => 'Запрос на уничтожение' );
        ?>
        <div class="wrap bfcamel-crm-admin bfcamel-crm-privacy">
            <div class="bfcamel-crm-page-title"><div><h1>Обработка персональных данных</h1><p class="description">Реестр ПДн: цели обработки, сроки проверки и история действий с данными заявителей.</p></div></div>
            <section class="bfcamel-crm-panel" aria-labelledby="bfcamel-privacy-about">
                <h2 id="bfcamel-privacy-about">Для чего нужен реестр</h2>
                <p>Здесь ответственный сотрудник проверяет, зачем хранятся персональные данные из заявок, когда нужно пересмотреть срок их хранения и какие действия уже выполнены. Согласия и ответы заявителя доступны в карточке заявки.</p>
                <ol class="bfcamel-crm-privacy-steps"><li><strong>Найдите заявку</strong><span>По телефону заявителя или по номеру из списка ниже.</span></li><li><strong>Проверьте цель и срок</strong><span>При необходимости обновите их и укажите основание.</span></li><li><strong>Зафиксируйте результат</strong><span>Изменения и выгрузки сохраняются в журнале операций.</span></li></ol>
            </section>
            <section class="bfcamel-crm-panel" aria-labelledby="bfcamel-privacy-search">
                <h2 id="bfcamel-privacy-search">Поиск заявок</h2><p class="description" id="bfcamel-privacy-lookup-help">Введите телефон с кодом страны: поиск покажет связанные контакты и заявки.</p>
                <form method="post" class="bfcamel-crm-privacy-search"><?php wp_nonce_field( 'bfcamel_privacy_lookup' ); ?><label for="bfcamel-privacy-phone">Телефон</label><div class="bfcamel-crm-privacy-search-row"><input id="bfcamel-privacy-phone" type="tel" name="lookup" required autocomplete="tel" aria-describedby="bfcamel-privacy-lookup-help" value="<?php echo esc_attr( Request::post_text( 'lookup' ) ); ?>" placeholder="+7 999 123-45-67"><button type="submit" class="button">Найти</button></div></form>
                <?php if ( isset( $_POST['lookup'] ) ) {
                    check_admin_referer( 'bfcamel_privacy_lookup' );
                    $phone = ContactService::normalize_phone( Request::post_text( 'lookup' ) );
                    if ( strlen( $phone ) >= 10 ) {
                        ob_start();
                        self::matches( $phone );
                        $matches = ob_get_clean();
                        echo '<div class="bfcamel-crm-privacy-results" role="status">' . ( $matches ? wp_kses_post( $matches ) : '<p>Контакты и заявки с этим телефоном не найдены.</p>' ) . '</div>';
                    } else {
                        echo '<p role="alert">Введите корректный телефон с кодом страны, не менее 10 цифр.</p>';
                    }
                } ?>
            </section>
            <section class="bfcamel-crm-panel bfcamel-crm-panel--table" aria-labelledby="bfcamel-privacy-goals">
                <div class="bfcamel-crm-panel__head bfcamel-crm-panel__head--padded"><div><h2 id="bfcamel-privacy-goals">Цели и сроки проверки</h2><p class="description">До 100 записей с ближайшими сроками в начале списка. Срок означает дату проверки необходимости дальнейшего хранения.</p></div></div>
                <?php if ( ! $goals ) : ?><div class="bfcamel-crm-empty">Цели пока не зарегистрированы. Они появляются при отправке форм с настроенными целями обработки; существующую заявку можно добавить через форму ниже.</div><?php else : ?>
                <div class="bfcamel-crm-table-scroll"><table class="widefat bfcamel-crm-table"><thead><tr><th scope="col">Заявка</th><th scope="col">Цель обработки</th><th scope="col">Состояние</th><th scope="col">Срок проверки (UTC)</th></tr></thead><tbody>
                <?php foreach ( $goals as $goal ) : ?><tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . absint( $goal->submission_id ) ) ); ?>">#<?php echo absint( $goal->submission_id ); ?></a></td><td><?php echo esc_html( self::PURPOSES[ $goal->purpose ] ?? $goal->purpose ); ?></td><td><span class="bfcamel-crm-badge"><?php echo esc_html( $states[ $goal->state ] ?? $goal->state ); ?></span></td><td><?php echo esc_html( $goal->due_at ? mysql2date( get_option( 'date_format' ), $goal->due_at ) : 'Не назначен' ); ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </section>
            <section class="bfcamel-crm-panel" aria-labelledby="bfcamel-privacy-edit">
                <h2 id="bfcamel-privacy-edit">Изменить цель обработки</h2><p class="description">Укажите номер заявки, выберите цель и состояние. Основание сохранится в журнале вместе с изменением.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'bfcamel_privacy' ); ?><input type="hidden" name="action" value="bfcamel_crm_privacy_action"><input type="hidden" name="operation" value="purpose">
                    <div class="bfcamel-crm-privacy-fields"><label>Номер заявки<input type="number" min="1" name="submission" required></label><label>Цель<select name="purpose"><?php foreach ( self::PURPOSES as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; ?></select></label><label>Состояние<select name="state"><?php foreach ( $states as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; ?></select></label><label>Срок проверки (UTC)<input type="date" name="due"></label><label class="bfcamel-crm-privacy-wide">Основание<textarea name="reason" rows="3" required></textarea></label></div>
                    <button type="submit" class="button button-primary">Сохранить цель</button>
                </form>
            </section>
            <details class="bfcamel-crm-panel bfcamel-crm-privacy-erasure"><summary>Уничтожение данных выбранной заявки</summary>
                <p>Используйте после проверки личности заявителя, всех целей обработки и состава данных. Операция обезличивает заявку; результат сохраняется в журнале, где можно скачать проект акта.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'bfcamel_privacy' ); ?><input type="hidden" name="action" value="bfcamel_crm_privacy_action"><input type="hidden" name="operation" value="erase">
                    <div class="bfcamel-crm-privacy-fields"><label>Номер заявки<input type="number" min="1" name="submission" required></label><label class="bfcamel-crm-privacy-wide">Основание<textarea name="reason" rows="3" required></textarea></label><label class="bfcamel-crm-privacy-wide">Состояние писем, выгрузок и резервных копий<textarea name="copies" rows="2"></textarea></label></div>
                    <p><label><input type="checkbox" name="confirmed" value="1" required> Проверены личность, все цели заявки и состав удаляемых данных</label></p>
                    <button type="submit" class="button button-link-delete" data-bfcamel-confirm data-bfcamel-confirm-message="Уничтожить персональные данные выбранной заявки? Отменить эту операцию нельзя.">Уничтожить данные выбранной заявки</button>
                </form>
            </details>
            <section class="bfcamel-crm-panel bfcamel-crm-panel--table" aria-labelledby="bfcamel-privacy-log">
                <div class="bfcamel-crm-panel__head bfcamel-crm-panel__head--padded"><div><h2 id="bfcamel-privacy-log">Журнал операций</h2><p class="description">Последние 100 операций, новые сверху. Запись содержит подробности действия; для уничтожения данных доступен проект акта.</p></div></div>
                <?php if ( ! $logs ) : ?><div class="bfcamel-crm-empty">Операций пока нет. Здесь появятся изменения целей, выгрузки и результаты уничтожения данных.</div><?php else : ?>
                <div class="bfcamel-crm-table-scroll"><table class="widefat bfcamel-crm-table"><thead><tr><th scope="col">Дата и время (UTC)</th><th scope="col">Операция</th><th scope="col">Документы</th></tr></thead><tbody>
                <?php foreach ( $logs as $log ) : ?><tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log->occurred_at ) ); ?></td><td><?php echo esc_html( $operations[ $log->operation ] ?? $log->operation ); ?></td><td class="bfcamel-crm-privacy-downloads"><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bfcamel_crm_privacy_report', 'id' => $log->id ), admin_url( 'admin-post.php' ) ), 'bfcamel_privacy_report' ) ); ?>">Скачать запись</a><?php if ( 'erase_submission' === $log->operation ) : ?><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bfcamel_crm_privacy_report', 'id' => $log->id, 'format' => 'act' ), admin_url( 'admin-post.php' ) ), 'bfcamel_privacy_report' ) ); ?>">Проект акта</a><?php endif; ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </section>
        </div><?php
    }

    private static function matches( $phone ) {
        global $wpdb;
        $contacts = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT contact_id FROM %i WHERE normalized=%s', Schema::table( 'contact_phones' ), $phone ) );
        foreach ( $contacts as $contact ) echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bfcamel-crm-contacts&id=' . (int) $contact ) ) . '">Контакт #' . (int) $contact . '</a></p>';
        $contacts = array_map( 'intval', $contacts );
        $cursor = 0;
        do {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT s.id,s.payload_json,r.schema_json,s.contact_id FROM %i s LEFT JOIN %i r ON r.id=s.revision_id WHERE s.id>%d ORDER BY s.id LIMIT 200', Schema::table( 'submissions' ), Schema::table( 'form_revisions' ), $cursor ) );
            foreach ( (array) $rows as $row ) {
                $cursor = (int) $row->id;
                $values = json_decode( $row->payload_json, true ) ?: array();
                $schema = json_decode( (string) $row->schema_json, true );
                $candidate = in_array( (int) $row->contact_id, $contacts, true );
                foreach ( (array) $schema as $field ) {
                    if ( 'tel' !== $field['type'] && 'contact.phone' !== ( $field['mapping'] ?? '' ) ) continue;
                    $value = $values[ $field['name'] ] ?? '';
                    if ( is_string( $value ) && $phone === ContactService::normalize_phone( $value ) ) $candidate = true;
                }
                if ( ! $schema ) foreach ( $values as $value ) if ( is_string( $value ) && $phone === ContactService::normalize_phone( $value ) ) $candidate = true;
                if ( $candidate ) echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bfcamel-crm-submissions&id=' . $cursor ) ) . '">Заявка #' . absint( $cursor ) . '</a></p>';
            }
        } while ( count( (array) $rows ) === 200 );
    }
}
