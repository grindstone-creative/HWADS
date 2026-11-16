<?php
if (!defined('ABSPATH')) { exit; }
/** Divi 4 uses the normal post body. HWADS keeps playback metadata independent. */
final class HPADS_Divi {
    static $syncing=false;
    static function init() {
        add_action('load-post.php',[__CLASS__,'initialize_default']);
        add_action('template_redirect',[__CLASS__,'protect'],-10);
        add_action('save_post',[__CLASS__,'sync'],100,3);
        add_action('admin_notices',[__CLASS__,'notice']);
        add_filter('wp_sitemaps_post_types',function($types){unset($types['hpads_template'],$types['hpads_placement']);return $types;});
    }
    static function starter() {
        return '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text][hwads_ad_title heading="1"][/et_pb_text][et_pb_text][hwads_ad_media][/et_pb_text][et_pb_text][hwads_ad_content][/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';
    }
    static function custom_layout($id) {
        $pid=HPADS_Storage::post_id($id);$r=HPADS_Core::get($id);
        return get_post_meta($pid,'_et_pb_use_builder',true)==='on' && (!get_post_meta($pid,'_hwads_divi_starter',true) || !empty($r['data']['landing_layout_custom']));
    }
    static function initialize_default() {
        if (!self::available() || HPADS_View_As::state()) { return; }$pid=absint($_GET['post'] ?? 0);$post=get_post($pid);
        if (!$post || !in_array($post->post_type,self::types(),true) || !current_user_can('edit_post',$pid)) { return; }
        $r=HPADS_Post_Types::record($pid);if (!$r) { return; }$body=trim($r['data']['description'] ?? '');
        $plain="[hwads_ad_title heading=\"1\"]\n[hwads_ad_media]\n[hwads_ad_content]";
        if ($body!=='' && $body!==$plain) { return; }
        $d=$r['data'];$d['description']=self::starter();$saved=HPADS_Core::put('creative',$r['title'],$r['parent_id'],$d,$r['id'],$r['revision']);
        if (!is_wp_error($saved)) { update_post_meta($pid,'_hwads_divi_starter',1);update_post_meta($pid,'_et_pb_use_builder','on'); }
    }
    static function types() { return ['hpads_template','hpads_placement']; }
    static function available() { return function_exists('et_pb_is_pagebuilder_used') || defined('ET_BUILDER_VERSION'); }
    static function url($pid) { return add_query_arg('et_fb','1',get_preview_post_link($pid)); }
    static function notice() {
        $s=get_current_screen();if (!$s || !in_array($s->post_type,self::types(),true) || $s->base!=='post' || !self::available()) { return; }
        echo '<div class="notice notice-info"><p>Divi theme users: enable <strong>Ad Templates</strong> and <strong>Ad Placements</strong> under Divi → Theme Options → Builder → Post Type Integration. Divi edits this ad’s landing page; slideshow media and overlays stay in HWADS. The separate Divi Builder plugin is not required.</p></div>';
    }
    static function protect() {
        if (!is_singular(self::types())) { return; }$pid=get_queried_object_id();
        // Native front-end URLs are private editing previews, never an alternative draft/public route.
        if (!is_user_logged_in() || !current_user_can('edit_post',$pid)) { global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();wp_die('Ad editing preview unavailable.','HWADS',['response'=>404]); }
        $record=HPADS_Post_Types::record($pid);if ($record) { HPADS_Landing_Layout::$context=HPADS_Landing_Layout::draft_item($record); }
        nocache_headers();header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
    }
    static function sync($pid,$post,$update) {
        if (self::$syncing || HPADS_Native_Editor::$writing || !in_array($post->post_type,self::types(),true) || !empty($_POST['hwads_native_nonce']) || wp_is_post_revision($pid) || wp_is_post_autosave($pid) || !current_user_can('edit_post',$pid) || HPADS_View_As::state()) { return; }
        $r=HPADS_Post_Types::record($pid);if (!$r || !HPADS_Workflow::can_ad($r,true) || ($r['data']['description'] ?? '')===$post->post_content && $r['title']===$post->post_title) { return; }
        $d=$r['data'];$d['description']=$post->post_content;$d['landing_layout_custom']=true;$d['content_revision']=(int)($d['content_revision'] ?? 0)+1;$d['state']='draft';$d['submission_revision']=0;$d['reviews']=[];$d['manager_review']=[];$d['approved_locations']=[];$d=HPADS_Approvals::authored($d);
        self::$syncing=true;
        try { $result=HPADS_Core::put('creative',$post->post_title,$r['parent_id'],$d,$r['id'],$r['revision']);if (is_wp_error($result)) { wp_die('The landing page changed in another tab. Reload before saving.','HWADS',['response'=>409]); } }
        finally { self::$syncing=false; }
    }
    static function copy_meta($source_id,$destination_id) {
        $source=HPADS_Storage::post_id($source_id);$destination=HPADS_Storage::post_id($destination_id);if (!$source || !$destination) { return; }
        foreach (['_et_pb_use_builder','_et_pb_page_layout','_et_pb_side_nav','_hwads_divi_starter'] as $key) { $value=get_post_meta($source,$key,true);if ($value!=='') { update_post_meta($destination,$key,$value); } }
    }
    static function prepare_modules($content) {
        // The public ?hpads_ad route is not a normal singular builder query.
        // Divi can therefore skip registering its modules even though this frozen body needs them.
        if (!preg_match('/\[et_pb_[a-z0-9_]+(?:\s|\])/', $content) || shortcode_exists('et_pb_section')) { return; }
        if (function_exists('et_builder_init_global_settings')) { et_builder_init_global_settings(); }
        if (function_exists('et_builder_add_main_elements')) { et_builder_add_main_elements(); }
    }
    static function render($content,$id=0) {
        $pid=$id?HPADS_Storage::post_id($id):0;$original=$GLOBALS['post'] ?? null;
        if ($pid) { $p=get_post($pid);if ($p) { $p=clone $p;$p->post_content=$content;$GLOBALS['post']=$p;setup_postdata($p); } }
        try { self::prepare_modules($content);return apply_filters('the_content',$content); }
        finally { $GLOBALS['post']=$original;if ($original) { setup_postdata($original); } }
    }
}
