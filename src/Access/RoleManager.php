<?php
namespace BfCamel\CRM\Access;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RoleManager {
    const OPTION = 'bfcamel_crm_role_permissions';
    const VERSION_OPTION = 'bfcamel_crm_role_permissions_version';
    const VERSION = '1';
    const ACCESS_CAPABILITY = 'bfcamel_crm_access';

    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'ensure_roles' ) );
    }

    public static function managed_capabilities() {
        return array(
            'bfcamel_crm_view_dashboard'   => __( 'View dashboard', 'bfcamel-crm' ),
            'bfcamel_crm_manage_forms'     => __( 'Manage forms', 'bfcamel-crm' ),
            'bfcamel_crm_view_submissions' => __( 'View submissions', 'bfcamel-crm' ),
            'bfcamel_crm_edit_submissions' => __( 'Edit submissions', 'bfcamel-crm' ),
            'bfcamel_crm_manage_contacts'  => __( 'Manage contacts', 'bfcamel-crm' ),
            'bfcamel_crm_manage_consents'  => __( 'Manage consents', 'bfcamel-crm' ),
            'bfcamel_crm_export_data'      => __( 'Export CRM data', 'bfcamel-crm' ),
        );
    }

    public static function capability_keys() {
        return array(
            'bfcamel_crm_view_dashboard',
            'bfcamel_crm_manage_forms',
            'bfcamel_crm_view_submissions',
            'bfcamel_crm_edit_submissions',
            'bfcamel_crm_manage_contacts',
            'bfcamel_crm_manage_consents',
            'bfcamel_crm_export_data',
        );
    }

    public static function all_capabilities() {
        return array_merge(
            self::capability_keys(),
            array( self::ACCESS_CAPABILITY, 'bfcamel_crm_manage_settings' )
        );
    }

    public static function ensure_roles() {
        $stored = get_option( self::OPTION, false );
        $version = (string) get_option( self::VERSION_OPTION, '' );
        $roles = wp_roles();
        if ( ! $roles ) {
            return;
        }

        $matrix = is_array( $stored ) ? $stored : array();
        $changed = false;
        foreach ( array_keys( $roles->roles ) as $role_slug ) {
            if ( ! isset( $matrix[ $role_slug ] ) || ! is_array( $matrix[ $role_slug ] ) ) {
                $matrix[ $role_slug ] = self::default_permissions();
                $changed = true;
            } else {
                foreach ( self::capability_keys() as $capability ) {
                    if ( ! array_key_exists( $capability, $matrix[ $role_slug ] ) ) {
                        $matrix[ $role_slug ][ $capability ] = true;
                        $changed = true;
                    }
                }
            }
        }

        if ( false === $stored || self::VERSION !== $version || $changed ) {
            self::apply( $matrix );
            update_option( self::OPTION, $matrix, false );
            update_option( self::VERSION_OPTION, self::VERSION, false );
        }
    }

    public static function save( $posted ) {
        $roles = wp_roles();
        if ( ! $roles ) {
            return new \WP_Error( 'bfcamel_crm_roles_unavailable', __( 'WordPress roles are unavailable.', 'bfcamel-crm' ) );
        }

        $posted = is_array( $posted ) ? $posted : array();
        $matrix = array();
        foreach ( array_keys( $roles->roles ) as $role_slug ) {
            $matrix[ $role_slug ] = array();
            foreach ( self::capability_keys() as $capability ) {
                $matrix[ $role_slug ][ $capability ] = 'administrator' === $role_slug || ! empty( $posted[ $role_slug ][ $capability ] );
            }
            if ( ! empty( $matrix[ $role_slug ]['bfcamel_crm_edit_submissions'] ) ) {
                $matrix[ $role_slug ]['bfcamel_crm_view_submissions'] = true;
            }
        }

        self::apply( $matrix );
        update_option( self::OPTION, $matrix, false );
        update_option( self::VERSION_OPTION, self::VERSION, false );
        return true;
    }

    public static function permissions() {
        self::ensure_roles();
        $value = get_option( self::OPTION, array() );
        return is_array( $value ) ? $value : array();
    }

    private static function default_permissions() {
        return array_fill_keys( self::capability_keys(), true );
    }

    private static function apply( $matrix ) {
        $roles = wp_roles();
        if ( ! $roles ) {
            return;
        }

        foreach ( array_keys( $roles->roles ) as $role_slug ) {
            $role = get_role( $role_slug );
            if ( ! $role ) {
                continue;
            }

            $has_access = false;
            foreach ( self::capability_keys() as $capability ) {
                $enabled = 'administrator' === $role_slug || ! empty( $matrix[ $role_slug ][ $capability ] );
                if ( $enabled ) {
                    $role->add_cap( $capability );
                    if ( in_array( $capability, array( 'bfcamel_crm_view_dashboard', 'bfcamel_crm_manage_forms', 'bfcamel_crm_view_submissions', 'bfcamel_crm_manage_contacts' ), true ) ) {
                        $has_access = true;
                    }
                } else {
                    $role->remove_cap( $capability );
                }
            }

            if ( $has_access ) {
                $role->add_cap( self::ACCESS_CAPABILITY );
            } else {
                $role->remove_cap( self::ACCESS_CAPABILITY );
            }

            if ( 'administrator' === $role_slug ) {
                $role->add_cap( 'bfcamel_crm_manage_settings' );
            } else {
                $role->remove_cap( 'bfcamel_crm_manage_settings' );
            }
        }
    }
}
