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
        global $wpdb;
        $tags = Schema::table( 'tags' );
        $links = Schema::table( 'submission_tags' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.* FROM {$tags} t INNER JOIN {$links} st ON st.tag_id=t.id WHERE st.submission_id=%d ORDER BY t.name ASC,t.id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $submission_id )
            )
        );
    }

    public static function names_for_submission( $submission_id ) {
        return wp_list_pluck( self::for_submission( $submission_id ), 'name' );
    }

    public static function sync_submission( $submission_id, $raw_names ) {
        global $wpdb;
        $submission_id = absint( $submission_id );
        if ( ! $submission_id ) {
            return array();
        }

        $names = self::normalize_names( $raw_names );
        $tag_ids = array();
        foreach ( $names as $name ) {
            $tag_id = self::get_or_create( $name );
            if ( $tag_id ) {
                $tag_ids[] = $tag_id;
            }
        }
        $tag_ids = array_values( array_unique( array_map( 'absint', $tag_ids ) ) );

        $links = Schema::table( 'submission_tags' );
        $wpdb->delete( $links, array( 'submission_id' => $submission_id ), array( '%d' ) );
        $now = current_time( 'mysql' );
        foreach ( $tag_ids as $tag_id ) {
            $wpdb->insert(
                $links,
                array(
                    'submission_id' => $submission_id,
                    'tag_id'        => $tag_id,
                    'created_at'    => $now,
                ),
                array( '%d', '%d', '%s' )
            );
        }

        return self::for_submission( $submission_id );
    }

    private static function normalize_names( $raw_names ) {
        if ( is_array( $raw_names ) ) {
            $parts = $raw_names;
        } else {
            $parts = preg_split( '/[,;\n\r]+/u', (string) $raw_names );
        }

        $result = array();
        $seen = array();
        foreach ( (array) $parts as $part ) {
            $name = trim( sanitize_text_field( wp_unslash( (string) $part ) ) );
            if ( '' === $name ) {
                continue;
            }
            if ( function_exists( 'mb_substr' ) ) {
                $name = mb_substr( $name, 0, 120 );
            } else {
                $name = substr( $name, 0, 120 );
            }
            $slug = sanitize_title( $name );
            if ( '' === $slug || isset( $seen[ $slug ] ) ) {
                continue;
            }
            $seen[ $slug ] = true;
            $result[] = $name;
        }
        return $result;
    }

    private static function get_or_create( $name ) {
        global $wpdb;
        $table = Schema::table( 'tags' );
        $slug = sanitize_title( $name );
        if ( '' === $slug ) {
            return 0;
        }

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

        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE slug=%s LIMIT 1", $slug ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }
}
