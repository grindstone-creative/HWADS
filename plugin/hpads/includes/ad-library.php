<?php
if (!defined('ABSPATH')) { exit; }
/** One native WordPress list for both ad types; records and editors stay independent. */
final class HPADS_Ad_Library {
    static function init() {
        add_filter('posts_results',[__CLASS__,'restore_list_type'],10,2);
        add_filter('views_edit-hpads_placement',[__CLASS__,'views'],30);
        add_filter('manage_hpads_placement_posts_columns',[__CLASS__,'columns'],30);
        foreach (['hpads_placement','hpads_template'] as $type) { add_action('manage_'.$type.'_posts_custom_column',[__CLASS__,'column'],30,2); }
    }
    static function tab() { $tab=sanitize_key($_GET['hwads_ads'] ?? 'all');return in_array($tab,['all','templates','placements'],true)?$tab:'all'; }
    static function active() { return isset($_GET['hwads_ads']) && ($_GET['post_type'] ?? '')==='hpads_placement'; }
    static function url($tab='all') { return add_query_arg(['post_type'=>'hpads_placement','hwads_ads'=>$tab],admin_url('edit.php')); }
    static function rows($tab=null) {
        $tab=$tab ?? self::tab();$rows=[];
        foreach (HPADS_Core::all('creative') as $r) {
            if (!HPADS_Core::can($r) || !HPADS_Venue_Workspace::matches_context($r)) { continue; }
            $template=HPADS_Placements::is_template($r);
            if (($tab==='templates' && !$template) || ($tab==='placements' && $template)) { continue; }
            $filter=sanitize_key($_GET['approval_filter'] ?? '');
            if ($filter) {
                if ($template) { continue; }$match=false;
                foreach ($r['data']['locations'] ?? [] as $lid) { if (HPADS_Approvals::context($r,$lid)['status']===$filter) { $match=true;break; } }
                if (!$match) { continue; }
            }
            $rows[]=$r;
        }
        return $rows;
    }
    static function scope($query) {
        $ids=array_map(function($r){return HPADS_Storage::post_id($r['id']);},self::rows());
        $query->set('hwads_ad_library',true);
        $query->set('post_type',['hpads_template','hpads_placement']);
        $query->set('post__in',$ids?:[0]);
    }
    static function restore_list_type($posts,$query) {
        // Native edit.php uses this scalar to build search/filter form fields after SQL executes.
        if ($query->get('hwads_ad_library')) { $query->set('post_type','hpads_placement'); }
        return $posts;
    }
    static function views($views) {
        if (!self::active()) { return $views; }
        $out=[];
        foreach (['all'=>'All','templates'=>'Templates','placements'=>'Placements'] as $tab=>$label) {
            $out[$tab]='<a href="'.esc_url(self::url($tab)).'"'.(self::tab()===$tab?' class="current" aria-current="page"':'').'>'.esc_html($label).' <span class="count">('.count(self::rows($tab)).')</span></a>';
        }
        echo '<p>Templates are reusable designs. Placements are independent ads assigned to playlists. Inactive and expired ads remain available here.</p>';
        if (HPADS_Admin::can_add('creative')) { echo '<p><a class="button" href="'.esc_url(HPADS_Native_Editor::url('hpads_template',0)).'">Add Template</a> <a class="button" href="'.esc_url(HPADS_Native_Editor::url('hpads_placement',0)).'">Add Placement</a></p>'; }
        if (self::tab()==='templates') { HPADS_Placements::template_upload_panel(); }
        return $out;
    }
    static function columns($columns) { if (self::active()) { $columns=['title'=>$columns['title'] ?? 'Name','hwads_ad_type'=>'Type']+$columns; }return $columns; }
    static function column($column,$pid) {
        if ($column!=='hwads_ad_type') { return; }$r=HPADS_Post_Types::record($pid);
        if ($r && HPADS_Core::can($r)) { echo HPADS_Placements::is_template($r)?'Template':'Placement'; }
    }
}
