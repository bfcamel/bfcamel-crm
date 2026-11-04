<?php
namespace BfCamel\CRM\Consent;

if ( ! defined( 'ABSPATH' ) ) exit;

final class DocumentArchive {
    public static function publish( $metadata, $body ) {
        if ( '' === trim( $body ) ) return '';
        $entry = array( 'document' => $metadata, 'body' => $body );
        $id = hash( 'sha256', wp_json_encode( $entry ) );
        $key = 'bfcamel_crm_document_' . $id;
        if ( ! add_option( $key, $entry, '', false ) && get_option( $key ) !== $entry ) {
            wp_die( 'Не удалось сохранить архивную редакцию.' );
        }
        return $id;
    }

    public static function url( $id ) {
        return add_query_arg( array( 'action' => 'bfcamel_crm_document', 'revision' => $id ), admin_url( 'admin-post.php' ) );
    }

    public static function display() {
        $id = \BfCamel\CRM\Support\Request::get_key( 'revision' );
        $entry = preg_match( '/^[a-f0-9]{64}$/D', $id ) ? get_option( 'bfcamel_crm_document_' . $id ) : false;
        if ( ! $entry ) wp_die( 'Редакция не найдена.', '', array( 'response' => 404 ) );
        header( 'Content-Type: text/plain; charset=UTF-8' );
        header( 'X-Content-Type-Options: nosniff' );
        echo $entry['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This response is a plain-text document.
        exit;
    }

    public static function context( $form, $revision, $page ) {
        return hash_hmac( 'sha256', wp_json_encode( array( $form, $revision, $page, get_option( 'bfcamel_crm_legal_documents', array() ) ) ), wp_salt( 'nonce' ) );
    }
}
