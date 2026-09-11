<?php
namespace BfCamel\CRM\CRM;

use BfCamel\CRM\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TagService {
    const CACHE_GROUP = 'bfcamel_crm';

    public static function all( $scope = '' ) {
        global $wpdb;
        $table = Schema::table( 'tags' );
        $found = false;
        $rows = wp_cache_get( 'tags:all', self::CACHE_GROUP, false, $found );
        if ( ! $found ) {
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC, id ASC', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached read from the plugin's custom tag catalog.
            wp_cache_set( 'tags:all', $rows, self::CACHE_GROUP );
        }
        if ( ! $scope ) return $rows;
        return array_values( array_filter( (array) $rows, static function( $row ) use ( $scope ) { return WorkflowService::tag_allowed( $row->id, $scope ); } ) );
    }

    public static function catalog() {
        $scopes = WorkflowService::tag_scopes();
        $rows = self::all();
        foreach ( $rows as $row ) {
            $scope = isset( $scopes[ absint( $row->id ) ] ) ? $scopes[ absint( $row->id ) ] : array( 'submission'=>1, 'contact'=>1 );
            $row->for_submissions = ! empty( $scope['submission'] );
            $row->for_contacts = ! empty( $scope['contact'] );
        }
        return $rows;
    }

    public static function save_catalog( $rows ) {
        global $wpdb;
        $table = Schema::table( 'tags' );
        $scope_map = WorkflowService::tag_scopes();
        if ( ! Schema::begin_transaction() ) return new \WP_Error( 'bfcamel_crm_tag_transaction', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        foreach ( (array) $rows as $row ) {
            if ( ! is_array( $row ) ) continue;
            $id = absint( $row['id'] ?? 0 );
            $name = self::truncate_name( trim( sanitize_text_field( (string) ( $row['name'] ?? '' ) ) ) );
            if ( ! $id && '' === $name ) continue;
            if ( $id ) {
                if ( '' === $name ) continue;
                $slug = self::slug( $name );
                $duplicate = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE slug=%s AND id<>%d LIMIT 1', $table, $slug, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional uniqueness check requires current custom-table state.
                if ( $duplicate ) return self::rollback_error( __( 'Could not create a tag.', 'bfcamel-crm' ) );
                if ( false === $wpdb->update( $table, array( 'name'=>$name, 'slug'=>$slug, 'updated_at'=>current_time('mysql') ), array( 'id'=>$id ), array('%s','%s','%s'), array('%d') ) ) return self::rollback_error( __( 'Could not create a tag.', 'bfcamel-crm' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional write to the plugin's custom tag catalog.
            } else {
                $id = self::get_or_create( $name );
                if ( is_wp_error( $id ) ) { Schema::rollback(); return $id; }
            }
            $scope_map[ absint( $id ) ] = array( 'submission'=>!empty($row['submission'])?1:0, 'contact'=>!empty($row['contact'])?1:0 );
        }
        WorkflowService::save_tag_scopes( $scope_map );
        if ( ! Schema::commit() ) return self::rollback_error( __( 'Could not create a tag.', 'bfcamel-crm' ) );
        self::clear_cache();
        return true;
    }

    public static function delete_catalog_tag( $tag_id ) {
        global $wpdb;
        $tag_id = absint( $tag_id ); if ( ! $tag_id ) return false;
        if ( ! Schema::begin_transaction() ) return new \WP_Error( 'bfcamel_crm_tag_transaction', __( 'Could not start a database transaction.', 'bfcamel-crm' ) );
        $submission_links = Schema::table('submission_tags'); $contact_links = Schema::table('contact_tags'); $tags=Schema::table('tags');
        if(false===$wpdb->delete($submission_links,array('tag_id'=>$tag_id),array('%d'))||false===$wpdb->delete($contact_links,array('tag_id'=>$tag_id),array('%d')))return self::rollback_error(__( 'Could not remove a tag.', 'bfcamel-crm' )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional cleanup of plugin-owned tag relations.
        $deleted = $wpdb->delete($tags,array('id'=>$tag_id),array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional delete from the plugin's custom tag catalog.
        if(false===$deleted)return self::rollback_error(__( 'Could not remove a tag.', 'bfcamel-crm' ));
        $map = WorkflowService::tag_scopes(); unset($map[$tag_id]); WorkflowService::save_tag_scopes($map);
        if(!Schema::commit())return self::rollback_error(__( 'Could not remove a tag.', 'bfcamel-crm' ));
        self::clear_cache();
        return (bool)$deleted;
    }

    public static function for_submission( $submission_id ) { return self::for_entity( 'submission', $submission_id ); }
    public static function for_contact( $contact_id ) { return self::for_entity( 'contact', $contact_id ); }
    public static function names_for_submission( $submission_id ) { return wp_list_pluck( self::for_submission( $submission_id ), 'name' ); }
    public static function names_for_contact( $contact_id ) { return wp_list_pluck( self::for_contact( $contact_id ), 'name' ); }
    public static function sync_submission( $submission_id, $raw_names ) { return self::sync_entity( 'submission', $submission_id, $raw_names ); }
    public static function sync_contact( $contact_id, $raw_names ) { return self::sync_entity( 'contact', $contact_id, $raw_names ); }

    private static function for_entity( $entity_type, $entity_id ) {
        global $wpdb; $relation=self::relation($entity_type); if(!$relation)return array();
        $tags=Schema::table('tags'); $links=Schema::table($relation['table']); $column=$relation['column'];
        return $wpdb->get_results($wpdb->prepare('SELECT t.* FROM %i t INNER JOIN %i rel ON rel.tag_id=t.id WHERE rel.%i=%d ORDER BY t.name ASC,t.id ASC',$tags,$links,$column,absint($entity_id))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Entity tag relations must reflect current custom-table state.
    }

    private static function sync_entity( $entity_type, $entity_id, $raw_names ) {
        global $wpdb; $entity_id=absint($entity_id); $relation=self::relation($entity_type); if(!$entity_id||!$relation)return new \WP_Error('bfcamel_crm_invalid_tag_target',__( 'Invalid tag target.', 'bfcamel-crm' ));
        $scope = 'contact' === $entity_type ? 'contact' : 'submission'; $tag_ids=array();
        foreach(self::normalize_names($raw_names) as $name){ $tag_id=self::find_by_name($name); if(!$tag_id){ $tag_id=self::get_or_create($name); } if(is_wp_error($tag_id))return $tag_id; if(WorkflowService::tag_allowed($tag_id,$scope))$tag_ids[]=absint($tag_id); }
        $tag_ids=array_values(array_unique(array_filter($tag_ids))); $links=Schema::table($relation['table']); $column=$relation['column'];
        $existing=array_map('absint',(array)$wpdb->get_col($wpdb->prepare('SELECT tag_id FROM %i WHERE %i=%d',$links,$column,$entity_id))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Relation synchronization requires current custom-table state.
        foreach(array_diff($existing,$tag_ids) as $tag_id){ if(false===$wpdb->delete($links,array($column=>$entity_id,'tag_id'=>absint($tag_id)),array('%d','%d')))return self::database_error(__( 'Could not remove a tag.', 'bfcamel-crm' )); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes to the allowlisted custom relation table.
        $now=current_time('mysql'); foreach(array_diff($tag_ids,$existing) as $tag_id){ if(!$wpdb->insert($links,array($column=>$entity_id,'tag_id'=>absint($tag_id),'created_at'=>$now),array('%d','%d','%s')))return self::database_error(__( 'Could not add a tag.', 'bfcamel-crm' )); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes to the allowlisted custom relation table.
        return self::for_entity($entity_type,$entity_id);
    }

    private static function relation( $entity_type ) { if('submission'===$entity_type)return array('table'=>'submission_tags','column'=>'submission_id'); if('contact'===$entity_type)return array('table'=>'contact_tags','column'=>'contact_id'); return null; }

    private static function normalize_names( $raw_names ) {
        $parts=is_array($raw_names)?$raw_names:preg_split('/[,;\n\r]+/u',(string)$raw_names); $result=array();$seen=array();
        foreach((array)$parts as $part){$name=trim(sanitize_text_field(wp_unslash((string)$part)));if(''===$name)continue;$name=self::truncate_name($name);$slug=self::slug($name);if(isset($seen[$slug]))continue;$seen[$slug]=true;$result[]=$name;}return $result;
    }

    private static function truncate_name( $name ) { if(function_exists('mb_substr'))return mb_substr($name,0,120,'UTF-8'); if(function_exists('iconv_substr')){$v=iconv_substr($name,0,120,'UTF-8');if(false!==$v)return $v;} if(preg_match_all('/./us',$name,$c))return implode('',array_slice($c[0],0,120)); return substr($name,0,120); }
    private static function slug( $name ) { $slug=sanitize_title($name); if(''===$slug||strlen($slug)>110){$normalized=function_exists('mb_strtolower')?mb_strtolower($name,'UTF-8'):strtolower($name);return 'tag-'.substr(hash('sha256',$normalized),0,40);} return $slug; }
    private static function find_by_name( $name ) { global $wpdb; $table=Schema::table('tags'); return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE slug=%s LIMIT 1',$table,self::slug($name))); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uniqueness lookup must use current tag data.
    private static function get_or_create( $name ) { global $wpdb; $table=Schema::table('tags');$slug=self::slug($name);$existing=self::find_by_name($name);if($existing)return $existing;$now=current_time('mysql');$inserted=$wpdb->insert($table,array('name'=>$name,'slug'=>$slug,'created_at'=>$now,'updated_at'=>$now),array('%s','%s','%s','%s'));if($inserted){self::clear_cache();return absint($wpdb->insert_id);} $existing=self::find_by_name($name);return $existing?$existing:self::database_error(__( 'Could not create a tag.', 'bfcamel-crm' )); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes to the plugin's custom tag catalog.
    private static function database_error( $fallback ) { global $wpdb; return new \WP_Error('bfcamel_crm_tag_database_error',$wpdb->last_error?$fallback.' '.sanitize_text_field($wpdb->last_error):$fallback); }
    private static function rollback_error( $fallback ) { $error=self::database_error($fallback);Schema::rollback();return $error; }
    private static function clear_cache() { wp_cache_delete( 'tags:all', self::CACHE_GROUP ); }
}
