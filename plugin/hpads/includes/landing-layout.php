<?php
if (!defined('ABSPATH')) { exit; }
/** A shared WordPress/Divi page supplies layout; ad text remains a frozen per-placement value. */
final class HPADS_Landing_Layout {
    static $context=null;
    static $rendering_content=false;
    static function init() {
        add_shortcode('hwads_ad_content',[__CLASS__,'content']);
        add_shortcode('hwads_ad_title',[__CLASS__,'title']);
        add_shortcode('hwads_ad_media',[__CLASS__,'media']);
        add_filter('default_content',[__CLASS__,'default_body'],10,2);
    }
    static function candidate($id,$check_access=false) {
        $p=get_post($id);return $p && $p->post_type==='page' && in_array($p->post_status,['publish','private'],true) && !$p->post_password && (!$check_access || current_user_can('edit_post',$p->ID))?$p:null;
    }
    static function base() { return self::candidate(absint(get_option('hpads_settings',[])['landing_page_id'] ?? 0)); }
    static function setting() {
        $options=[0=>'Built-in layout (no shared page)'];
        foreach (get_posts(['post_type'=>'page','post_status'=>['publish','private'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']) as $p) { if (self::candidate($p->ID,true)) { $options[$p->ID]=$p->post_title; } }
        HPADS_Admin::select('Default landing-page layout','landing_page_id',$options,get_option('hpads_settings',[])['landing_page_id'] ?? 0);
        echo '<p>Choose a page designed with Divi or the normal editor. Put <code>[hwads_ad_content]</code> where each ad’s simple-editor text should appear. Optional: <code>[hwads_ad_title]</code> and <code>[hwads_ad_media]</code>. Changing this page updates the shared design for all ads using it; published ad text remains frozen until its playlist is updated. Ads with their own Divi layout keep that layout.</p>';
    }
    static function placeholder($text) { return is_user_logged_in() && current_user_can('edit_pages')?'<p class="hwads-layout-placeholder">'.esc_html($text).'</p>':''; }
    static function content() {
        if (!self::$context) { return self::placeholder('Ad text will appear here.'); }
        if (self::$rendering_content) { return ''; }self::$rendering_content=true;
        try { return do_shortcode(shortcode_unautop(wpautop(wp_kses_post(self::$context['landing']['main_text'] ?? self::$context['landing']['description'] ?? '')))); }
        finally { self::$rendering_content=false; }
    }
    static function title($attrs=[]) {
        if (!self::$context) { return self::placeholder('Ad title'); }
        if (!(self::$context['landing']['show_title'] ?? true)) { return ''; }
        $text=esc_html(self::$context['title'] ?? '');$level=absint(is_array($attrs)?($attrs['heading'] ?? 0):0);return $level>=1 && $level<=6?'<h'.$level.'>'.$text.'</h'.$level.'>':$text;
    }
    static function media() {
        if (!self::$context) { return self::placeholder('Ad image or video will appear here.'); }
        $i=self::$context;if (!($i['landing']['show_ad'] ?? true)) { return ''; }return strpos($i['mime'] ?? '','video/')===0?'<video controls playsinline style="max-width:100%;height:auto" src="'.esc_url($i['url']).'"></video>':'<img style="max-width:100%;height:auto" src="'.esc_url($i['url'] ?? '').'" alt="'.esc_attr($i['landing']['alt'] ?? $i['title'] ?? '').'">';
    }
    static function default_body($content,$post) {
        if ($content!=='' || !in_array($post->post_type ?? '',['hpads_template','hpads_placement'],true)) { return $content; }
        $source=!empty($_GET['source_template_id'])?HPADS_Placements::source(absint($_GET['source_template_id'])):null;
        if (!$source && HPADS_Divi::available()) { if (!empty($post->ID)) { update_post_meta($post->ID,'_hwads_divi_starter',1);update_post_meta($post->ID,'_et_pb_use_builder','on'); }return HPADS_Divi::starter(); }
        return $source?($source['data']['description'] ?? ''):'[hwads_ad_title heading="1"]
[hwads_ad_media]
[hwads_ad_content]';
    }
    static function draft_item($r) {
        $d=$r['data'];$pid=HPADS_Storage::post_id($r['id']);$builder=HPADS_Divi::custom_layout($r['id']);
        return ['placement_id'=>$r['id'],'title'=>$r['title'],'url'=>wp_get_attachment_url($d['attachment_id']),'mime'=>get_post_mime_type($d['attachment_id']),'landing'=>['custom_layout'=>!empty($d['landing_layout_custom']),'description'=>$d['description'] ?? '','main_text'=>$d['landing_text'] ?? ($builder?'':($d['description'] ?? '')),'show_ad'=>$d['show_ad_on_page'] ?? true,'show_title'=>$d['show_page_title'] ?? true,'divi_builder'=>$builder,'alt'=>$d['media_alt'] ?? '']];
    }
    static function render($item) {
        $base=empty($item['landing']['divi_builder']) && empty($item['landing']['custom_layout'])?self::base():null;
        $body=$base?$base->post_content:($item['landing']['description'] ?? '');
        $custom=!empty($item['landing']['custom_layout']) || !empty($item['landing']['divi_builder']) || has_shortcode($body,'hwads_ad_content') || has_shortcode($body,'hwads_ad_media') || has_shortcode($body,'hwads_ad_title');
        if (!$base && !$custom) { return false; }
        $layout=$base?:get_post(HPADS_Storage::post_id($item['placement_id'] ?? 0));if (!$layout) { return false; }
        $prior=self::$context;$original=$GLOBALS['post'] ?? null;self::$context=$item;$GLOBALS['post']=clone $layout;$GLOBALS['post']->post_content=$body;setup_postdata($GLOBALS['post']);
        try {
            // Render before wp_head so module-generated styles can be enqueued in time.
            $rendered=HPADS_Divi::render($body);
            get_header();echo '<div id="main-content"><div class="entry-content">'.$rendered.'</div></div>';get_footer();
        }
        finally { self::$context=$prior;$GLOBALS['post']=$original;if ($original) { setup_postdata($original); } }
        return true;
    }
}
