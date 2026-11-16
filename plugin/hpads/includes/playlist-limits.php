<?php
if (!defined('ABSPATH')) { exit; }
/** Playlist membership cap and enabled placement policy; publications stay immutable. */
final class HPADS_Playlist_Limits {
    const TOTAL=100;
    const TYPES=['ads'=>'Ads','menus'=>'Menus','community'=>'Community Events','house'=>'House Ads','fallback'=>'Fallback'];
    const ACTIVE=['ads'=>15,'menus'=>5,'community'=>5,'house'=>15,'fallback'=>15];
    private static $held=[];
    static function category($p) { return $p['data']['category'] ?? (($p['data']['purpose'] ?? '')==='house'?'house':'ads'); }
    static function cap($p) { return (int)($p['data']['active_limit'] ?? (self::ACTIVE[self::category($p)] ?? 15)); }
    static function fresh($id,$kind='') {
        if (HPADS_Storage::active()) { $pid=HPADS_Storage::post_id($id);if ($pid) { clean_post_cache($pid); } }
        return HPADS_Core::get($id,$kind);
    }
    static function rows($p,$replacement_id=0,$replacement=null) {
        $rows=[];
        foreach ($p['data']['items'] ?? [] as $item) {
            $id=(int)($item['creative_id'] ?? 0);$ad=self::fresh($id,'creative');
            $rows[]=($ad['data']['record_type'] ?? '')==='placement'?($id===$replacement_id?$replacement:$ad['data']):$item;
        }
        return $rows;
    }
    static function stats($rows) {
        return ['total'=>count($rows),'active'=>count(array_filter($rows,function($row){return !empty($row['active']);}))];
    }
    static function settings($input,$prior) {
        $category=sanitize_key($input['category'] ?? self::category(['data'=>$prior]));
        if (!isset(self::TYPES[$category])) { return HPADS_Core::error('Choose a valid playlist content type.'); }
        $limit=$input['active_limit'] ?? ($category===self::category(['data'=>$prior])?self::cap(['data'=>$prior]):self::ACTIVE[$category]);
        if (filter_var($limit,FILTER_VALIDATE_INT)===false || (int)$limit<1 || (int)$limit>self::TOTAL) { return HPADS_Core::error('Maximum active placements must be a whole number from 1 to 100.'); }
        return ['category'=>$category,'active_limit'=>(int)$limit];
    }
    static function validate($p,$rows=null) {
        if (!isset(self::TYPES[self::category($p)])) { return HPADS_Core::error('Choose a valid playlist content type.'); }
        $rows=$rows??self::rows($p);
        if (count($rows)>self::TOTAL) { return HPADS_Core::error('A playlist can contain at most 100 placements, including inactive and expired placements. Remove entries before saving.'); }
        $stats=self::stats($rows);if (is_wp_error($stats)) { return $stats; }
        $cap=self::cap($p);
        if ($cap<1 || $cap>self::TOTAL) { return HPADS_Core::error('Maximum active placements must be from 1 to 100.'); }
        if ($stats['active']>$cap) { return HPADS_Core::error(sprintf('Only %d placements can be active on this playlist. %d Active switches are on. Turn some off or change Maximum active placements in Playlist Settings. Schedules do not change this count.',$cap,$stats['active'])); }
        return true;
    }
    static function placement($id,$data) {
        $p=self::fresh($data['rotation_id'] ?? 0,'playlist');if (!$p || !$id) { return true; }
        return self::validate($p,self::rows($p,(int)$id,$data));
    }
    static function room($p) {
        return count($p['data']['items'] ?? [])<self::TOTAL?true:HPADS_Core::error('This playlist already contains 100 placements. Remove an entry and save the playlist before adding another.');
    }
    static function new_active($p) {
        $rows=self::rows($p);$rows[]=['active'=>true];return !is_wp_error(self::validate($p,$rows));
    }
    static function locked($id,$callback) {
        if (!$id || isset(self::$held[$id])) { return $callback(); }
        global $wpdb;$key='hwads_playlist_lock_'.(int)$id;$token=['id'=>wp_generate_uuid4(),'at'=>time()];
        if (!add_option($key,$token,'',false)) {
            $old=get_option($key);
            if (is_array($old) && (int)($old['at'] ?? 0)<time()-900) { $wpdb->delete($wpdb->options,['option_name'=>$key,'option_value'=>maybe_serialize($old)]);wp_cache_delete($key,'options'); }
            if (!add_option($key,$token,'',false)) { return HPADS_Core::error('Another editor is saving this playlist. Please try again.',409); }
        }
        self::$held[$id]=true;
        try { return $callback(); }
        finally { unset(self::$held[$id]);$wpdb->delete($wpdb->options,['option_name'=>$key,'option_value'=>maybe_serialize($token)]);wp_cache_delete($key,'options'); }
    }
}
