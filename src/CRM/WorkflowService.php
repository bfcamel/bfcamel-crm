<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Forms\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WorkflowService {
    const CONFIG_OPTION = 'bfcamel_crm_workflow_config';
    const RULES_OPTION = 'bfcamel_crm_automation_rules';
    const TAG_SCOPES_OPTION = 'bfcamel_crm_tag_scopes';

    public static function seed_defaults() {
        if ( false === get_option( self::CONFIG_OPTION, false ) ) {
            add_option( self::CONFIG_OPTION, self::default_config(), '', false );
        }
        if ( false === get_option( self::RULES_OPTION, false ) ) {
            add_option( self::RULES_OPTION, array(), '', false );
        }
        if ( false === get_option( self::TAG_SCOPES_OPTION, false ) ) {
            add_option( self::TAG_SCOPES_OPTION, array(), '', false );
        }
    }

    public static function default_config() {
        return array(
            'statuses' => array(
                array( 'slug' => 'new', 'name' => 'New', 'color' => '#2271b1', 'weight' => 10, 'enabled' => 1, 'default' => 1, 'builtin' => 1 ),
                array( 'slug' => 'in_progress', 'name' => 'In progress', 'color' => '#dba617', 'weight' => 20, 'enabled' => 1, 'default' => 0, 'builtin' => 1 ),
                array( 'slug' => 'waiting', 'name' => 'Waiting', 'color' => '#8c8f94', 'weight' => 30, 'enabled' => 1, 'default' => 0, 'builtin' => 1 ),
                array( 'slug' => 'completed', 'name' => 'Completed', 'color' => '#00a32a', 'weight' => 100, 'enabled' => 1, 'default' => 0, 'builtin' => 1 ),
                array( 'slug' => 'needs_review', 'name' => 'Needs review', 'color' => '#d63638', 'weight' => 90, 'enabled' => 1, 'default' => 0, 'builtin' => 1 ),
            ),
            'priorities' => array(
                array( 'slug' => 'normal', 'name' => 'Normal', 'color' => '#8c8f94', 'weight' => 10, 'enabled' => 1, 'default' => 1, 'builtin' => 1 ),
                array( 'slug' => 'high', 'name' => 'High', 'color' => '#dba617', 'weight' => 20, 'enabled' => 1, 'default' => 0, 'builtin' => 1 ),
                array( 'slug' => 'urgent', 'name' => 'Urgent', 'color' => '#d63638', 'weight' => 30, 'enabled' => 1, 'default' => 0, 'builtin' => 1 ),
            ),
        );
    }

    public static function config() {
        self::seed_defaults();
        $config = get_option( self::CONFIG_OPTION, array() );
        $defaults = self::default_config();
        if ( ! is_array( $config ) ) {
            return $defaults;
        }
        foreach ( array( 'statuses', 'priorities' ) as $key ) {
            if ( empty( $config[ $key ] ) || ! is_array( $config[ $key ] ) ) {
                $config[ $key ] = $defaults[ $key ];
            }
            $config[ $key ] = self::prepare_rows( $key, $config[ $key ] );
        }
        return $config;
    }

    public static function definitions( $type, $active_only = false ) {
        $key = 'priority' === $type || 'priorities' === $type ? 'priorities' : 'statuses';
        $items = self::config()[ $key ];
        $result = array();
        foreach ( (array) $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['slug'] ) || empty( $item['name'] ) ) {
                continue;
            }
            $item = wp_parse_args( $item, array( 'color' => '#8c8f94', 'weight' => 0, 'enabled' => 1, 'default' => 0, 'builtin' => 0 ) );
            if ( $active_only && empty( $item['enabled'] ) ) {
                continue;
            }
            $result[] = $item;
        }
        usort( $result, static function ( $a, $b ) {
            $cmp = (int) $a['weight'] <=> (int) $b['weight'];
            return 0 !== $cmp ? $cmp : strcmp( (string) $a['name'], (string) $b['name'] );
        } );
        return $result;
    }

    public static function statuses() { return self::labels( 'status' ); }
    public static function priorities() { return self::labels( 'priority' ); }

    public static function labels( $type ) {
        $result = array();
        foreach ( self::definitions( $type, true ) as $item ) {
            $result[ $item['slug'] ] = $item['name'];
        }
        return $result;
    }

    public static function label( $type, $slug ) {
        $slug = sanitize_key( $slug );
        foreach ( self::definitions( $type, false ) as $item ) {
            if ( $slug === $item['slug'] ) return $item['name'];
        }
        return $slug;
    }

    public static function color( $type, $slug ) {
        $slug = sanitize_key( $slug );
        foreach ( self::definitions( $type, false ) as $item ) {
            if ( $slug === $item['slug'] ) return sanitize_hex_color( $item['color'] ) ?: '#8c8f94';
        }
        return '#8c8f94';
    }

    public static function default_status() { return self::default_slug( 'status', 'new' ); }
    public static function default_priority() { return self::default_slug( 'priority', 'normal' ); }

    public static function default_slug( $type, $fallback ) {
        $items = self::definitions( $type, true );
        foreach ( $items as $item ) if ( ! empty( $item['default'] ) ) return $item['slug'];
        return $items ? $items[0]['slug'] : sanitize_key( $fallback );
    }

    public static function highest_priority() {
        $items = self::definitions( 'priority', true );
        if ( ! $items ) return self::default_priority();
        usort( $items, static function ( $a, $b ) { return (int) $b['weight'] <=> (int) $a['weight']; } );
        return $items[0]['slug'];
    }

    public static function is_valid( $type, $slug ) {
        $labels = self::labels( $type );
        return isset( $labels[ sanitize_key( $slug ) ] );
    }

    public static function save_config( $posted ) {
        $current = self::config();
        $saved = array();
        foreach ( array( 'statuses', 'priorities' ) as $key ) {
            $rows = isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ? $posted[ $key ] : array();
            $saved[ $key ] = self::sanitize_rows( $key, $rows, $current[ $key ] );
            if ( ! $saved[ $key ] ) $saved[ $key ] = $current[ $key ];
            $has_default = false;
            $has_enabled = false;
            foreach ( $saved[ $key ] as &$row ) {
                if ( ! empty( $row['enabled'] ) ) {
                    $has_enabled = true;
                    if ( ! empty( $row['default'] ) && ! $has_default ) $has_default = true;
                    else $row['default'] = 0;
                } else {
                    $row['default'] = 0;
                }
            }
            unset( $row );
            if ( ! $has_enabled && $saved[ $key ] ) {
                $saved[ $key ][0]['enabled'] = 1;
                $saved[ $key ][0]['default'] = 1;
                $has_default = true;
            }
            if ( ! $has_default ) {
                foreach ( $saved[ $key ] as &$row ) {
                    if ( ! empty( $row['enabled'] ) ) { $row['default'] = 1; break; }
                }
                unset( $row );
            }
        }
        update_option( self::CONFIG_OPTION, $saved, false );
        return $saved;
    }

    private static function sanitize_rows( $type, $rows, $existing ) {
        $result = array(); $seen = array(); $existing_by_slug = array();
        foreach ( (array) $existing as $item ) {
            if ( is_array( $item ) && ! empty( $item['slug'] ) ) {
                $existing_by_slug[ sanitize_key( $item['slug'] ) ] = $item;
            }
        }
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) continue;
            $name = self::truncate_text( sanitize_text_field( $row['name'] ?? '' ), 190 );
            $slug = sanitize_key( $row['slug'] ?? '' );
            if ( '' === $name && '' === $slug ) continue;
            if ( '' === $name ) $name = $slug;
            if ( '' === $slug ) $slug = sanitize_title( $name );
            $slug = sanitize_key( str_replace( '-', '_', $slug ) );
            $slug = self::bounded_slug( $type, $slug, $name );
            if ( '' === $slug || isset( $seen[ $slug ] ) ) continue;
            $seen[ $slug ] = true;
            $existing_item = $existing_by_slug[ $slug ] ?? array();
            $builtin_name = self::builtin_name( $type, $slug );
            $was_builtin = ! empty( $row['builtin'] ) || ! empty( $existing_item['builtin'] );
            $is_builtin = $was_builtin && '' !== $builtin_name && $name === $builtin_name;
            $result[] = array(
                'slug' => $slug,
                'name' => $is_builtin ? self::builtin_source_name( $type, $slug ) : $name,
                'color' => sanitize_hex_color( $row['color'] ?? '' ) ?: '#8c8f94',
                'weight' => (int) ( $row['weight'] ?? 0 ),
                'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
                'default' => ! empty( $row['default'] ) ? 1 : 0,
                'builtin' => $is_builtin ? 1 : 0,
            );
        }
        return $result;
    }

    private static function prepare_rows( $type, $rows ) {
        $result = array();
        $has_enabled = false;
        $has_default = false;
        foreach ( (array) $rows as $row ) {
            if ( ! is_array( $row ) ) continue;
            $row = wp_parse_args( $row, array( 'slug'=>'', 'name'=>'', 'color'=>'#8c8f94', 'weight'=>0, 'enabled'=>1, 'default'=>0, 'builtin'=>null ) );
            $row['slug'] = sanitize_key( $row['slug'] );
            if ( '' === $row['slug'] || '' === (string) $row['name'] ) continue;
            if ( null === $row['builtin'] ) {
                $source_name = self::builtin_source_name( $type, $row['slug'] );
                $translated_name = self::builtin_name( $type, $row['slug'] );
                $row['builtin'] = '' !== $source_name && in_array( (string) $row['name'], array( $source_name, $translated_name ), true ) ? 1 : 0;
            }
            if ( ! empty( $row['builtin'] ) ) {
                $translated_name = self::builtin_name( $type, $row['slug'] );
                if ( '' !== $translated_name ) $row['name'] = $translated_name;
            }
            $row['enabled'] = ! empty( $row['enabled'] ) ? 1 : 0;
            $row['default'] = ! empty( $row['default'] ) ? 1 : 0;
            if ( $row['enabled'] ) {
                $has_enabled = true;
                if ( $row['default'] && ! $has_default ) $has_default = true;
                else $row['default'] = 0;
            } else {
                $row['default'] = 0;
            }
            $result[] = $row;
        }
        if ( ! $result ) return self::prepare_rows( $type, self::default_config()[ $type ] );
        if ( ! $has_enabled ) $result[0]['enabled'] = 1;
        if ( ! $has_default ) {
            foreach ( $result as &$row ) {
                if ( ! empty( $row['enabled'] ) ) { $row['default'] = 1; break; }
            }
            unset( $row );
        }
        return $result;
    }

    private static function builtin_names() {
        return array(
            'statuses' => array(
                'new'          => __( 'New', 'bfcamel-crm' ),
                'in_progress'  => __( 'In progress', 'bfcamel-crm' ),
                'waiting'      => __( 'Waiting', 'bfcamel-crm' ),
                'completed'    => __( 'Completed', 'bfcamel-crm' ),
                'needs_review' => __( 'Needs review', 'bfcamel-crm' ),
            ),
            'priorities' => array(
                'normal' => __( 'Normal', 'bfcamel-crm' ),
                'high'   => __( 'High', 'bfcamel-crm' ),
                'urgent' => __( 'Urgent', 'bfcamel-crm' ),
            ),
        );
    }

    private static function builtin_source_names() {
        return array(
            'statuses' => array( 'new'=>'New', 'in_progress'=>'In progress', 'waiting'=>'Waiting', 'completed'=>'Completed', 'needs_review'=>'Needs review' ),
            'priorities' => array( 'normal'=>'Normal', 'high'=>'High', 'urgent'=>'Urgent' ),
        );
    }

    private static function builtin_name( $type, $slug ) {
        $names = self::builtin_names();
        return isset( $names[ $type ][ $slug ] ) ? $names[ $type ][ $slug ] : '';
    }

    private static function builtin_source_name( $type, $slug ) {
        $names = self::builtin_source_names();
        return isset( $names[ $type ][ $slug ] ) ? $names[ $type ][ $slug ] : '';
    }

    private static function bounded_slug( $type, $slug, $name ) {
        $max = 'priorities' === $type ? 20 : 40;
        if ( '' === $slug ) $slug = ( 'priorities' === $type ? 'p_' : 's_' ) . substr( hash( 'sha256', (string) $name ), 0, 16 );
        if ( strlen( $slug ) > $max ) $slug = substr( $slug, 0, $max - 9 ) . '_' . substr( hash( 'sha256', $slug ), 0, 8 );
        return $slug;
    }

    private static function truncate_text( $value, $length ) {
        $value = (string) $value;
        if ( function_exists( 'mb_substr' ) ) return mb_substr( $value, 0, $length, 'UTF-8' );
        if ( function_exists( 'iconv_substr' ) ) {
            $truncated = iconv_substr( $value, 0, $length, 'UTF-8' );
            if ( false !== $truncated ) return $truncated;
        }
        if ( preg_match_all( '/./us', $value, $characters ) ) return implode( '', array_slice( $characters[0], 0, $length ) );
        return substr( $value, 0, $length );
    }

    public static function rules() {
        self::seed_defaults();
        $rules = get_option( self::RULES_OPTION, array() );
        return is_array( $rules ) ? $rules : array();
    }

    public static function save_rules( $rows ) {
        $result = array(); $order = 0;
        foreach ( (array) $rows as $row ) {
            if ( ! is_array( $row ) ) continue;
            $form_id = absint( $row['form_id'] ?? 0 );
            $field = sanitize_key( $row['field'] ?? '' );
            $priority = sanitize_key( $row['priority'] ?? '' );
            if ( ! $form_id || ! $field || ! self::is_valid( 'priority', $priority ) ) continue;
            $operator = sanitize_key( $row['operator'] ?? 'equals' );
            if ( ! in_array( $operator, array( 'equals', 'not_equals', 'contains', 'filled' ), true ) ) $operator = 'equals';
            $result[] = array(
                'id' => sanitize_key( $row['id'] ?? '' ) ?: 'rule_' . substr( md5( $form_id . '|' . $field . '|' . $order . '|' . microtime() ), 0, 12 ),
                'form_id' => $form_id,
                'field' => $field,
                'operator' => $operator,
                'value' => sanitize_text_field( $row['value'] ?? '' ),
                'priority' => $priority,
                'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
                'order' => $order++,
            );
        }
        update_option( self::RULES_OPTION, $result, false );
        return $result;
    }

    public static function evaluate( $form_id, $form_settings, $payload ) {
        $status = sanitize_key( $form_settings['default_status'] ?? '' );
        if ( ! self::is_valid( 'status', $status ) ) $status = self::default_status();
        $priority = sanitize_key( $form_settings['default_priority'] ?? '' );
        if ( ! self::is_valid( 'priority', $priority ) ) $priority = self::default_priority();
        foreach ( self::rules() as $rule ) {
            if ( empty( $rule['enabled'] ) || absint( $rule['form_id'] ?? 0 ) !== absint( $form_id ) ) continue;
            $field = sanitize_key( $rule['field'] ?? '' );
            if ( '' === $field ) continue;
            $value = isset( $payload[ $field ] ) ? $payload[ $field ] : '';
            if ( ! self::rule_matches( $value, $rule['operator'] ?? 'equals', $rule['value'] ?? '' ) ) continue;
            $candidate = sanitize_key( $rule['priority'] ?? '' );
            if ( self::is_valid( 'priority', $candidate ) ) $priority = $candidate;
        }
        return array( 'status' => $status, 'priority' => $priority );
    }

    private static function rule_matches( $actual, $operator, $expected ) {
        $operator = sanitize_key( $operator );
        if ( is_array( $actual ) ) {
            $actual_values = array_map( 'strval', $actual );
            if ( 'filled' === $operator ) return ! empty( $actual_values );
            if ( 'contains' === $operator || 'equals' === $operator ) return in_array( (string) $expected, $actual_values, true );
            if ( 'not_equals' === $operator ) return ! in_array( (string) $expected, $actual_values, true );
            return false;
        }
        $actual = (string) $actual; $expected = (string) $expected;
        if ( 'filled' === $operator ) return '' !== trim( $actual );
        if ( 'not_equals' === $operator ) return $actual !== $expected;
        if ( 'contains' === $operator ) return '' !== $expected && false !== stripos( $actual, $expected );
        return $actual === $expected;
    }

    public static function field_catalog() {
        $result = array();
        foreach ( Repository::all() as $form ) {
            $revision = Repository::current_revision( $form );
            if ( ! $revision ) continue;
            foreach ( Repository::decode_schema( $revision ) as $field ) {
                $type = sanitize_key( $field['type'] ?? '' );
                $name = sanitize_key( $field['name'] ?? '' );
                if ( ! $name || 'html' === $type || 0 === strpos( $type, 'consent_' ) ) continue;
                $result[] = array( 'form_id' => absint( $form->id ), 'form_name' => (string) $form->name, 'field' => $name, 'label' => sanitize_text_field( $field['label'] ?? $name ) );
            }
        }
        return $result;
    }

    public static function tag_scopes() {
        self::seed_defaults();
        $map = get_option( self::TAG_SCOPES_OPTION, array() );
        return is_array( $map ) ? $map : array();
    }

    public static function save_tag_scopes( $map ) {
        $clean = array();
        foreach ( (array) $map as $tag_id => $scope ) {
            $id = absint( $tag_id ); if ( ! $id ) continue;
            $clean[ $id ] = array( 'submission' => ! empty( $scope['submission'] ) ? 1 : 0, 'contact' => ! empty( $scope['contact'] ) ? 1 : 0 );
        }
        update_option( self::TAG_SCOPES_OPTION, $clean, false );
        return $clean;
    }

    public static function tag_allowed( $tag_id, $scope ) {
        $map = self::tag_scopes(); $id = absint( $tag_id );
        if ( ! isset( $map[ $id ] ) ) return true;
        return ! empty( $map[ $id ][ 'contact' === $scope ? 'contact' : 'submission' ] );
    }
}
