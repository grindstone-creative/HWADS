<?php
if (!defined('ABSPATH')) { exit; }
/** CPT-backed entity storage; logical IDs remain stable across migration. */
final class HPADS_Storage {
    static function active() { return get_option('hpads_cpt_schema')==='1'; }
    static function type($kind,$data=[]) {
        return $kind==='creative' ? (($data['record_type'] ?? '')==='template'?'hpads_template':'hpads_placement') : ['company'=>'hpads_venue','location'=>'hpads_location','device'=>'hpads_device','advertiser'=>'hpads_advertiser','playlist'=>'hpads_playlist'][$kind];
    }
    static function mark_published($id) { $pid=self::post_id($id);if ($pid) { wp_update_post(['ID'=>$pid,'post_status'=>'publish']); } }
    static function post_id($id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id WHERE m.meta_key='_hpads_id' AND m.meta_value=%s AND p.post_type IN ('hpads_venue','hpads_location','hpads_device','hpads_advertiser','hpads_template','hpads_placement','hpads_playlist') LIMIT 1",(string)(int)$id));
    }
    static function get($id,$kind='') {
        $pid=self::post_id($id); if (!$pid) { return null; }
        $r=json_decode((string)get_post_meta($pid,'_hpads_record',true),true);
        return $r && (!$kind || $r['kind']===$kind)?$r:null;
    }
    static function ids($kind) {
        global $wpdb;
        $types=$kind==='creative'?['hpads_template','hpads_placement']:[self::type($kind)];
        $marks=implode(',',array_fill(0,count($types),'%s'));
        return array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT m.meta_value FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id WHERE m.meta_key='_hpads_id' AND p.post_type IN ($marks) ORDER BY p.post_title,p.ID",...$types)));
    }
    static function put($kind,$title,$parent,$data,$id=0,$revision=0,$adopt=0) {
        global $wpdb;
        $pid=$id?self::post_id($id):0;
        if ($id && !$pid) { return HPADS_Core::error('Record unavailable.'); }
        if ($pid) {
            $meta=$wpdb->get_row($wpdb->prepare("SELECT meta_id,meta_value FROM $wpdb->postmeta WHERE post_id=%d AND meta_key='_hpads_record' LIMIT 1",$pid),ARRAY_A);
            $old=$meta?json_decode($meta['meta_value'],true):null;
            if (!$old || (int)$old['revision']!==(int)$revision || $old['kind']!==$kind) { return HPADS_Core::error('This record changed in another tab. Reload before saving.'); }
        } else {
            $pid=$adopt?:wp_insert_post(['post_type'=>self::type($kind,$data),'post_status'=>'draft','post_title'=>$title,'post_author'=>get_current_user_id()],true);
            if (is_wp_error($pid)) { return $pid; }
            if ($adopt && (!get_post($pid) || get_post_type($pid)!==self::type($kind,$data) || get_post_meta($pid,'_hpads_id',true))) { return HPADS_Core::error('Invalid native post.'); }
            // New logical IDs are disjoint from all migrated IDs; native IDs are monotonic.
            $id=(int)get_option('hpads_cpt_id_floor',0)+(int)$pid;
            if (!add_post_meta($pid,'_hpads_id',(string)$id,true)) { wp_delete_post($pid,true);return HPADS_Core::error('Could not allocate record ID.'); }
        }
        $r=['id'=>(int)$id,'kind'=>$kind,'parent_id'=>(int)$parent,'title'=>$title,'data'=>$data,'revision'=>(int)$revision+1,'updated_at'=>gmdate('Y-m-d H:i:s')];
        $json=wp_json_encode($r);
        if (!empty($meta)) {
            if ($wpdb->update($wpdb->postmeta,['meta_value'=>$json],['meta_id'=>$meta['meta_id'],'meta_value'=>$meta['meta_value']])!==1) { return HPADS_Core::error('This record changed in another tab. Reload before saving.'); }
            wp_cache_delete($pid,'post_meta');
        } elseif (!add_post_meta($pid,'_hpads_record',wp_slash($json),true)) { wp_delete_post($pid,true);return HPADS_Core::error('Could not save record.'); }
        $prior=HPADS_Native_Editor::$writing;HPADS_Native_Editor::$writing=true;
        try { $result=wp_update_post(wp_slash(['ID'=>$pid,'post_title'=>$title,'post_type'=>self::type($kind,$data),'post_parent'=>$parent?self::post_id($parent):0,'post_content'=>$data['description'] ?? '']),true); } finally { HPADS_Native_Editor::$writing=$prior; }
        if (is_wp_error($result)) {
            if (!empty($meta)) { $wpdb->update($wpdb->postmeta,['meta_value'=>$meta['meta_value']],['meta_id'=>$meta['meta_id'],'meta_value'=>$json]);wp_cache_delete($pid,'post_meta'); }
            else { wp_delete_post($pid,true); }
            return $result;
        }
        return (int)$id;
    }
    static function migrate() {
        if (self::active()) { return true; }
        if (!HPADS_Workflow::global_admin()) { return HPADS_Core::error('Global administrator access is required.'); }
        $lock=(int)get_option('hpads_cpt_migrating',0);
        if ($lock && $lock<time()-1800) { delete_option('hpads_cpt_migrating'); }
        if (!add_option('hpads_cpt_migrating',time(),'','no')) { return HPADS_Core::error('The migration is already running. Reload after it finishes.'); }
        try { return self::convert(); } finally { delete_option('hpads_cpt_migrating'); }
    }
    private static function convert() {
        if (self::active()) { return true; }
        global $wpdb;
        if (!HPADS_Workflow::global_admin()) { return HPADS_Core::error('A global HWADS administrator must run the post-type upgrade.'); }
        $rows=$wpdb->get_results('SELECT * FROM '.HPADS_Core::table().' ORDER BY id',ARRAY_A);
        $max=0;foreach ($rows as $r) { $max=max($max,(int)$r['id']); }
        $wpdb->query('START TRANSACTION');$created=[];
        foreach ($rows as $r) {
            $r['data']=json_decode($r['data'],true) ?: [];
            $pid=self::post_id($r['id']);
            if ($pid) {
                $saved=json_decode((string)get_post_meta($pid,'_hpads_record',true),true);
                if ($saved!=$r) { $wpdb->query('ROLLBACK');return HPADS_Core::error('An incomplete migration differs from the legacy data. No storage switch was made.'); }
                continue;
            }
            $pid=wp_insert_post(['post_type'=>self::type($r['kind'],$r['data']),'post_status'=>'draft','post_title'=>$r['title'],'post_content'=>$r['data']['description'] ?? '','post_author'=>get_current_user_id(),'post_date_gmt'=>$r['updated_at'],'post_date'=>get_date_from_gmt($r['updated_at'])],true);
            if (is_wp_error($pid) || !add_post_meta($pid,'_hpads_id',(string)$r['id'],true) || !add_post_meta($pid,'_hpads_record',wp_slash(wp_json_encode($r)),true)) {
                $wpdb->query('ROLLBACK'); foreach ($created as $cid) { clean_post_cache($cid); }return HPADS_Core::error('Migration could not copy a record. Legacy storage remains active.');
            }
            $created[]=$pid;
        }
        foreach ($rows as $r) {
            $pid=self::post_id($r['id']);$copy=self::get($r['id']);$expected=$r;$expected['data']=json_decode($r['data'],true) ?: [];
            if ($copy!=$expected || !$pid) { $wpdb->query('ROLLBACK');return HPADS_Core::error('Migration reconciliation failed. Legacy storage remains active.'); }
            $wpdb->update($wpdb->posts,['post_parent'=>$r['parent_id']?self::post_id($r['parent_id']):0],['ID'=>$pid]);clean_post_cache($pid);
        }
        update_option('hpads_cpt_id_floor',$max,false);
        update_option('hpads_cpt_migration_report',['count'=>count($rows),'at'=>gmdate('c'),'by'=>get_current_user_id(),'legacy_retained'=>true],false);
        update_option('hpads_cpt_schema','1',false);
        $wpdb->query('COMMIT');
        foreach ($rows as $row) { $d=json_decode($row['data'],true) ?: [];if ($row['kind']==='playlist' && !empty($d['published_version'])) { self::mark_published($row['id']);$v=HPADS_Core::version($d['published_version']);foreach (($v['items'] ?? []) as $item) { self::mark_published($item['creative_id']); } } }
        return true;
    }
}
