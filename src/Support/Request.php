<?php
namespace BfCamel\CRM\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Normalizes request data at the controller boundary.
 */
final class Request {
    const MAX_JSON_BYTES = 1048576;

    public static function get_text( $key, $default = '' ) {
        // Read-only filters do not change state; state-changing handlers verify their own nonces.
        $value = isset( $_GET[ $key ] ) ? $_GET[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filter value is sanitized by text() on the next line.
        return self::text( $value );
    }

    public static function get_key( $key, $default = '' ) {
        // Read-only filters do not change state; state-changing handlers verify their own nonces.
        $value = isset( $_GET[ $key ] ) ? $_GET[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filter value is unslashed and sanitized on the next line.
        return is_scalar( $value ) ? sanitize_key( wp_unslash( (string) $value ) ) : sanitize_key( $default );
    }

    public static function get_id( $key, $default = 0 ) {
        // Read-only filters do not change state; state-changing handlers verify their own nonces.
        $value = isset( $_GET[ $key ] ) ? $_GET[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filter value is unslashed and normalized on the next line.
        return is_scalar( $value ) ? absint( wp_unslash( (string) $value ) ) : absint( $default );
    }

    public static function get_flag( $key ) {
        // Presence-only status flags are read-only and do not authorize an operation.
        return isset( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    public static function post_text( $key, $default = '' ) {
        $value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; this value is sanitized on the next line.
        return self::text( $value );
    }

    public static function post_textarea( $key, $default = '' ) {
        $value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; this value is sanitized below.
        if ( ! is_scalar( $value ) ) {
            return sanitize_textarea_field( $default );
        }
        return sanitize_textarea_field( wp_unslash( (string) $value ) );
    }

    public static function post_key( $key, $default = '' ) {
        $value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; this value is sanitized on the next line.
        return is_scalar( $value ) ? sanitize_key( wp_unslash( (string) $value ) ) : sanitize_key( $default );
    }

    public static function post_id( $key, $default = 0 ) {
        $value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; this value is normalized on the next line.
        return is_scalar( $value ) ? absint( wp_unslash( (string) $value ) ) : absint( $default );
    }

    public static function post_url( $key, $default = '' ) {
        $value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; this value is sanitized on the next line.
        return is_scalar( $value ) ? esc_url_raw( wp_unslash( (string) $value ) ) : esc_url_raw( $default );
    }

    public static function post_array( $key ) {
        if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; the array is unslashed and sanitized below.
            return array();
        }
        return map_deep( wp_unslash( $_POST[ $key ] ), 'sanitize_textarea_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; every scalar value is sanitized by map_deep().
    }

    public static function post_payload() {
        // The public submission handler verifies its form nonce before calling this method.
        return map_deep( wp_unslash( $_POST ), 'sanitize_textarea_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every submitted scalar value is unslashed and sanitized at the request boundary.
    }

    public static function post_id_list( $key ) {
        $values = self::post_array( $key );
        return array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
    }

    public static function post_contact( $key = 'contact' ) {
        $posted = self::post_array( $key );
        return array(
            'name'         => sanitize_text_field( isset( $posted['name'] ) ? $posted['name'] : '' ),
            'organization' => sanitize_text_field( isset( $posted['organization'] ) ? $posted['organization'] : '' ),
            'email'        => sanitize_email( isset( $posted['email'] ) ? $posted['email'] : '' ),
            'phone'        => sanitize_text_field( isset( $posted['phone'] ) ? $posted['phone'] : '' ),
        );
    }

    /**
     * Decode a JSON object/array while preserving HTML for field-level validation.
     *
     * @return array|\WP_Error
     */
    public static function post_json( $key ) {
        if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action controllers verify their nonce; decoded fields are validated by Repository.
            return new \WP_Error( 'bfcamel_crm_json_missing', __( 'The form schema is invalid.', 'bfcamel-crm' ) );
        }
        // JSON is not flattened with a text sanitizer because Repository validates every decoded field by type.
        $json = wp_unslash( (string) $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The controller verifies its nonce; decoded fields are validated by Repository according to their declared type.
        if ( strlen( $json ) > self::MAX_JSON_BYTES ) {
            return new \WP_Error( 'bfcamel_crm_json_too_large', __( 'The form schema is too large.', 'bfcamel-crm' ) );
        }
        $decoded = json_decode( $json, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            return new \WP_Error( 'bfcamel_crm_json_invalid', __( 'The form schema is invalid.', 'bfcamel-crm' ) );
        }
        return $decoded;
    }

    public static function server_textarea( $key, $max_length = 1000 ) {
        if ( ! isset( $_SERVER[ $key ] ) || ! is_scalar( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server metadata is sanitized and length-limited below.
            return '';
        }
        return substr( sanitize_textarea_field( wp_unslash( (string) $_SERVER[ $key ] ) ), 0, absint( $max_length ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server metadata is unslashed, sanitized and length-limited here.
    }

    public static function request_uri() {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_scalar( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URI is normalized below and rebuilt against trusted home_url().
            return '/';
        }
        $uri = wp_unslash( (string) $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Control characters are removed below and Renderer rebuilds the URL against trusted home_url().
        $uri = preg_replace( '/[\x00-\x1F\x7F]/', '', $uri );
        return is_string( $uri ) && '' !== $uri ? $uri : '/';
    }

    private static function text( $value ) {
        return is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
    }
}
