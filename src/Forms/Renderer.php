<?php
namespace BfCamel\CRM\Forms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Renderer {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function register() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_shortcode( 'gfr_form', array( $this, 'shortcode' ) );
        // Backward-compatible alias for installations created before the GFR CRM rebrand.
        add_shortcode( 'bfcamel_form', array( $this, 'shortcode' ) );
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'bfcamel-crm-frontend',
            BFCAMEL_CRM_URL . 'assets/frontend.css',
            array(),
            BFCAMEL_CRM_VERSION
        );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'   => 0,
                'slug' => '',
            ),
            $atts,
            'gfr_form'
        );

        $form = absint( $atts['id'] ) ? Repository::get( $atts['id'] ) : Repository::get_by_slug( $atts['slug'] );
        if ( ! $form || 'publish' !== $form->status ) {
            return current_user_can( 'bfcamel_crm_manage_forms' )
                ? '<p class="bfcamel-crm-notice">' . esc_html__( 'GFR CRM: form not found.', 'bfcamel-crm' ) . '</p>'
                : '';
        }

        $revision = Repository::current_revision( $form );
        if ( ! $revision ) {
            return '';
        }

        $schema   = Repository::decode_schema( $revision );
        $settings = Repository::decode_settings( $revision );


        $classes = array(
            'bfcamel-crm-form',
            'bfcamel-crm-form--' . sanitize_html_class( $settings['style_mode'] ),
            'bfcamel-crm-columns-' . absint( $settings['columns'] ),
        );
        if ( ! empty( $settings['custom_class'] ) ) {
            $classes[] = sanitize_html_class( $settings['custom_class'] );
        }

        $style = '';
        if ( 'theme' !== $settings['style_mode'] ) {
            $style = sprintf(
                '--bfcamel-primary:%1$s;--bfcamel-text:%2$s;--bfcamel-field-bg:%3$s;--bfcamel-border:%4$s;--bfcamel-button:%5$s;--bfcamel-button-text:%6$s;--bfcamel-radius:%7$dpx;',
                esc_attr( $settings['primary_color'] ),
                esc_attr( $settings['text_color'] ),
                esc_attr( $settings['field_bg'] ),
                esc_attr( $settings['border_color'] ),
                esc_attr( $settings['button_color'] ),
                esc_attr( $settings['button_text'] ),
                absint( $settings['border_radius'] )
            );
        }

        $state = isset( $_GET['bfcamel_crm_form'] ) ? sanitize_key( wp_unslash( $_GET['bfcamel_crm_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $state_form = isset( $_GET['bfcamel_crm_form_id'] ) ? absint( $_GET['bfcamel_crm_form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        ob_start();
        ?>
        <div class="bfcamel-crm-form-shell">
            <?php if ( $state_form === absint( $form->id ) && 'success' === $state ) : ?>
                <div class="bfcamel-crm-response bfcamel-crm-response--success" role="status">
                    <?php echo esc_html( $settings['success_message'] ); ?>
                </div>
            <?php elseif ( $state_form === absint( $form->id ) && 'error' === $state ) : ?>
                <div class="bfcamel-crm-response bfcamel-crm-response--error" role="alert">
                    <?php echo esc_html( $settings['error_message'] ); ?>
                </div>
            <?php endif; ?>

            <form class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="bfcamel_crm_submit">
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $form->id ); ?>">
                <input type="hidden" name="revision_id" value="<?php echo esc_attr( $revision->id ); ?>">
                <input type="hidden" name="return_url" value="<?php echo esc_url( $this->current_url() ); ?>">
                <?php wp_nonce_field( 'bfcamel_crm_submit_' . $form->id . '_' . $revision->id, 'bfcamel_crm_nonce' ); ?>

                <div class="bfcamel-crm-hp" aria-hidden="true">
                    <label>
                        <?php esc_html_e( 'Leave this field empty', 'bfcamel-crm' ); ?>
                        <input type="text" name="bfcamel_crm_website" value="" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <div class="bfcamel-crm-grid">
                    <?php foreach ( $schema as $field ) : ?>
                        <?php echo $this->render_field( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>

                    <div class="bfcamel-crm-field bfcamel-crm-field--100 bfcamel-crm-actions">
                        <button type="submit" class="bfcamel-crm-submit">
                            <?php echo esc_html( $settings['submit_label'] ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_field( $field ) {
        $type     = sanitize_key( $field['type'] ?? 'text' );
        $name     = sanitize_key( $field['name'] ?? '' );
        $label    = $field['label'] ?? '';
        $required = ! empty( $field['required'] );
        $width    = in_array( (string) ( $field['width'] ?? '100' ), array( '100', '50', '33' ), true ) ? (string) $field['width'] : '100';
        $id       = 'bfcamel-field-' . $name . '-' . wp_rand( 1000, 9999 );

        if ( 'html' === $type ) {
            return '<div class="bfcamel-crm-field bfcamel-crm-field--' . esc_attr( $width ) . ' bfcamel-crm-content">' . wp_kses_post( $label ) . '</div>';
        }

        if ( in_array( $type, array( 'consent_personal_data', 'consent_marketing' ), true ) ) {
            return $this->render_consent( $field, $id, $width );
        }

        $required_attr = $required ? ' required aria-required="true"' : '';
        $placeholder   = isset( $field['placeholder'] ) && '' !== $field['placeholder'] ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
        $html          = '<div class="bfcamel-crm-field bfcamel-crm-field--' . esc_attr( $width ) . '">';

        if ( 'hidden' !== $type ) {
            $html .= '<label class="bfcamel-crm-label" for="' . esc_attr( $id ) . '">' . esc_html( $label );
            if ( $required ) {
                $html .= ' <span class="bfcamel-crm-required" aria-hidden="true">*</span>';
            }
            $html .= '</label>';
        }

        switch ( $type ) {
            case 'textarea':
                $html .= '<textarea class="bfcamel-crm-control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $placeholder . $required_attr . '></textarea>';
                break;

            case 'select':
                $html .= '<select class="bfcamel-crm-control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $required_attr . '>';
                $html .= '<option value=""' . ( $required ? ' disabled selected' : '' ) . '>' . esc_html__( 'Select an option', 'bfcamel-crm' ) . '</option>';
                foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
                    $html .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'radio':
            case 'checkbox':
                $html .= '<div class="bfcamel-crm-options"' . ( $required ? ' data-required="1"' : '' ) . '>';
                $input_name = 'checkbox' === $type ? $name . '[]' : $name;
                foreach ( (array) ( $field['options'] ?? array() ) as $index => $option ) {
                    $option_id = $id . '-' . absint( $index );
                    $html .= '<label class="bfcamel-crm-option" for="' . esc_attr( $option_id ) . '">';
                    $html .= '<input id="' . esc_attr( $option_id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $option ) . '">';
                    $html .= '<span>' . esc_html( $option ) . '</span></label>';
                }
                $html .= '</div>';
                break;

            case 'hidden':
                $html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $field['placeholder'] ?? '' ) . '">';
                break;

            default:
                $input_type = in_array( $type, array( 'text', 'email', 'tel', 'number', 'date' ), true ) ? $type : 'text';
                $autocomplete = '';
                if ( 'email' === $input_type ) {
                    $autocomplete = ' autocomplete="email"';
                } elseif ( 'tel' === $input_type ) {
                    $autocomplete = ' autocomplete="tel"';
                }
                $html .= '<input class="bfcamel-crm-control" id="' . esc_attr( $id ) . '" type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '"' . $placeholder . $autocomplete . $required_attr . '>';
                break;
        }

        $html .= '</div>';
        return $html;
    }

    private function render_consent( $field, $id, $width ) {
        $name     = sanitize_key( $field['name'] ?? '' );
        $required = ! empty( $field['required'] );
        $label    = $this->legalize_label( (string) ( $field['label'] ?? '' ) );

        $html  = '<div class="bfcamel-crm-field bfcamel-crm-field--' . esc_attr( $width ) . ' bfcamel-crm-consent">';
        $html .= '<label class="bfcamel-crm-consent-label" for="' . esc_attr( $id ) . '">';
        $html .= '<input id="' . esc_attr( $id ) . '" type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . ( $required ? ' required aria-required="true"' : '' ) . '>';
        $html .= '<span>' . $label . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $html .= '</label></div>';

        return $html;
    }

    private function legalize_label( $label ) {
        $docs = get_option( 'bfcamel_crm_legal_documents', array() );
        $tokens = array(
            '{personal_data_consent}' => array(
                'url'  => $docs['personal_data_consent']['url'] ?? '',
                'text' => __( 'Personal Data Processing Consent', 'bfcamel-crm' ),
            ),
            '{privacy_policy}' => array(
                'url'  => $docs['privacy_policy']['url'] ?? '',
                'text' => __( 'Privacy Policy', 'bfcamel-crm' ),
            ),
            '{marketing_consent}' => array(
                'url'  => $docs['marketing_consent']['url'] ?? '',
                'text' => __( 'Marketing Consent', 'bfcamel-crm' ),
            ),
        );

        $safe = esc_html( $label );
        foreach ( $tokens as $token => $doc ) {
            $replacement = esc_html( $doc['text'] );
            if ( ! empty( $doc['url'] ) ) {
                $replacement = '<a href="' . esc_url( $doc['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $doc['text'] ) . '</a>';
            }
            $safe = str_replace( esc_html( $token ), $replacement, $safe );
        }

        return wp_kses(
            $safe,
            array(
                'a' => array(
                    'href'   => true,
                    'target' => true,
                    'rel'    => true,
                ),
            )
        );
    }

    private function current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
        $uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        return esc_url_raw( $scheme . $host . $uri );
    }
}
