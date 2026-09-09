<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TagService {
    public static function all() {
        global $wpdb;
        $table = Schema::table( 'tags' );
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function for_submission( $submission_id ) {
        return self::for_entity( 'submission', $submission_id );
    }

    public static function for_contact( $contact_id ) {
        return self::for_entity( 'contact', $contact_id );
    }

    public static function names_for_submission( $submission_id ) {
        return wp_list_pluck( self::for_submission( $submission_id ), 'name' );
    }

    public static function names_for_contact( $contact_id ) {
        return wp_list_pluck( self::for_contact( $contact_id ), 'name' );
    }

    public static function sync_submission( $submission_id, $raw_names ) {
        return self::sync_entity( 'submission', $submission_id, $raw_names );
    }

    public static function sync_contact( $contact_id, $raw_names ) {
        return self::sync_entity( 'contact', $contact_id, $raw_names );
    }

    private static function for_entity( $entity_type, $entity_id ) {
        global $wpdb;
        $relation = self::relation( $entity_type );
        if ( ! $relation ) {
            return array();
        }

        $tags = Schema::table( 'tags' );
        $links = Schema::table( $relation['table'] );
        $column = $relation['column'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.* FROM {$tags} t INNER JOIN {$links} rel ON rel.tag_id=t.id WHERE rel.{$column}=%d ORDER BY t.name ASC,t.id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $entity_id )
            )
        );
    }

    private static function sync_entity( $entity_type, $entity_id, $raw_names ) {
        global $wpdb;
        $entity_id = absint( $entity_id );
        $relation = self::relation( $entity_type );
        if ( ! $entity_id || ! $relation ) {
            return new \WP_Error( 'bfcamel_crm_invalid_tag_target', __( 'Invalid tag target.', 'bfcamel-crm' ) );
        }

        $tag_ids = array();
        foreach ( self::normalize_names( $raw_names ) as $name ) {
            $tag_id = self::get_or_create( $name );
            if ( is_wp_error( $tag_id ) ) {
                return $tag_id;
            }
            $tag_ids[] = absint( $tag_id );
        }
        $tag_ids = array_values( array_unique( array_filter( $tag_ids ) ) );

        $links = Schema::table( $relation['table'] );
        $column = $relation['column'];
        $existing = array_map(
            'absint',
            (array) $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT tag_id FROM {$links} WHERE {$column}=%d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $entity_id
                )
            )
        );

        foreach ( array_diff( $existing, $tag_ids ) as $tag_id ) {
            $deleted = $wpdb->delete(
                $links,
                array( $column => $entity_id, 'tag_id' => absint( $tag_id ) ),
                array( '%d', '%d' )
            );
            if ( false === $deleted ) {
                return self::database_error( __( 'Could not remove a tag.', 'bfcamel-crm' ) );
            }
        }

        $now = current_time( 'mysql' );
        foreach ( array_diff( $tag_ids, $existing ) as $tag_id ) {
            $inserted = $wpdb->insert(
                $links,
                array(
                    $column      => $entity_id,
                    'tag_id'     => absint( $tag_id ),
                    'created_at' => $now,
                ),
                array( '%d', '%d', '%s' )
            );
            if ( ! $inserted ) {
                return self::database_error( __( 'Could not add a tag.', 'bfcamel-crm' ) );
            }
        }

        return self::for_entity( $entity_type, $entity_id );
    }

    private static function relation( $entity_type ) {
        if ( 'submission' === $entity_type ) {
            return array( 'table' => 'submission_tags', 'column' => 'submission_id' );
        }
        if ( 'contact' === $entity_type ) {
            return array( 'table' => 'contact_tags', 'column' => 'contact_id' );
        }
        return null;
    }

    private static function normalize_names( $raw_names ) {
        $parts = is_array( $raw_names ) ? $raw_names : preg_split( '/[,;\n\r]+/u', (string) $raw_names );
        $result = array();
        $seen = array();

        foreach ( (array) $parts as $part ) {
            $name = trim( sanitize_text_field( wp_unslash( (string) $part ) ) );
            if ( '' === $name ) {
                continue;
            }
            $name = self::truncate_name( $name );
            $slug = self::slug( $name );
            if ( isset( $seen[ $slug ] ) ) {
                continue;
            }
            $seen[ $slug ] = true;
            $result[] = $name;
        }

        return $result;
    }

    private static function truncate_name( $name ) {
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $name, 0, 120, 'UTF-8' );
        }
        if ( function_exists( 'iconv_substr' ) ) {
            $value = iconv_substr( $name, 0, 120, 'UTF-8' );
            if ( false !== $value ) {
                return $value;
            }
        }
        if ( preg_match_all( '/./us', $name, $characters ) ) {
            return implode( '', array_slice( $characters[0], 0, 120 ) );
        }
        return substr( $name, 0, 120 );
    }

    private static function slug( $name ) {
        $slug = sanitize_title( $name );
        if ( '' === $slug || strlen( $slug ) > 110 ) {
            $normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
            return 'tag-' . substr( hash( 'sha256', $normalized ), 0, 40 );
        }
        return $slug;
    }

    private static function get_or_create( $name ) {
        global $wpdb;
        $table = Schema::table( 'tags' );
        $slug = self::slug( $name );

        $existing = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s LIMIT 1", $slug ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        if ( $existing ) {
            return $existing;
        }

        $now = current_time( 'mysql' );
        $inserted = $wpdb->insert(
            $table,
            array(
                'name'       => $name,
                'slug'       => $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            return absint( $wpdb->insert_id );
        }

        // A concurrent request may have inserted the same unique slug.
        $existing = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s LIMIT 1", $slug ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        return $existing ? $existing : self::database_error( __( 'Could not create a tag.', 'bfcamel-crm' ) );
    }

    private static function database_error( $fallback ) {
        global $wpdb;
        return new \WP_Error(
            'bfcamel_crm_tag_database_error',
            $wpdb->last_error ? $fallback . ' ' . sanitize_text_field( $wpdb->last_error ) : $fallback
        );
    }
}
