<?php
if (!defined('ABSPATH')) { exit; }
/** Public output is deliberately independent of private device records. */
final class HPADS_Public_Device {
    static function init() {
        add_shortcode('hwads_device',[__CLASS__,'shortcode']);
        add_action('wp_enqueue_scripts',function(){wp_register_style('hwads-public-device',false);wp_enqueue_style('hwads-public-device');wp_add_inline_style('hwads-public-device',self::styles());});
        add_action('login_enqueue_scripts',[__CLASS__,'logo']);
        add_filter('login_headerurl',function(){return home_url('/');});
        add_filter('login_headertext',function(){return get_bloginfo('name');});
    }
    static function logo_url() {
        $url=wp_get_attachment_image_url(get_theme_mod('custom_logo'),'full');if($url)return $url;
        $divi=get_option('et_divi',[]);foreach(['divi_logo','logo'] as $key){if(is_string($divi[$key] ?? null) && wp_http_validate_url($divi[$key]))return $divi[$key];}
        return '';
    }
    static function logo() {
        $url=self::logo_url() ?: get_site_icon_url(192);if (!$url) { echo '<style>body.login h1 a{background:none;text-indent:0;width:auto;height:auto;font:600 26px system-ui;line-height:1.4}</style>';return; }
        echo '<style>body.login h1 a{background-image:url('.esc_url($url).');background-size:contain;width:260px;height:100px}</style>';
    }
    static function shortcode() {
        // Divi or any native page can include this shortcode; no device data is public.
        $id=absint($_GET['hwads_device'] ?? $_GET['device_id'] ?? 0);
        $device=$id?HPADS_Core::get($id,'device'):null;$location=$device?HPADS_Core::get($device['parent_id'],'location'):null;$venue=$location?HPADS_Core::get($location['parent_id'],'company'):null;
        $title=$location?implode(' · ',array_filter([$venue['title'] ?? '',$location['title']])):'HWADS Box';
        $login=wp_login_url($id?HPADS_Device_QR::url($id):admin_url());$logo=self::logo_url();
        $brand=$logo?'<img src="'.esc_url($logo).'" alt="'.esc_attr(get_bloginfo('name')).'">':esc_html(get_bloginfo('name'));
        $display=$device && HPADS_Embeds::payload('device',$id)?HPADS_Embeds::frame('device',$id,($device['data']['orientation'] ?? '')==='vertical'?'9/16':'16/9'):'<p>Scheduled content is unavailable.</p>';
        return '<div class="hwads-device-page"><header><a class="hwads-brand" href="'.esc_url(home_url('/')).'">'.$brand.'</a><a class="hwads-login" href="'.esc_url($login).'">Log in</a></header><main><h1>'.esc_html($title).'</h1><p class="hwads-subtitle">Scheduled content for this HWADS Box</p><div class="hwads-device-display">'.$display.'</div><p class="hwads-device-help">Log in to manage this HWADS Box.</p><p class="hwads-device-note">Playback order may differ from the HWADS Box. Offline content and local fallback media are not mirrored.</p></main><footer>'.esc_html(get_bloginfo('name')).'</footer></div>';
    }
    static function styles() {
        return '.hwads-device-page{font:18px/1.5 system-ui,sans-serif;color:#1d2327;background:#fff}.hwads-device-page header{display:flex;align-items:center;justify-content:space-between;padding:16px 5%;border-bottom:1px solid #dce1e7}.hwads-brand{font-size:26px;font-weight:700;color:inherit;text-decoration:none}.hwads-brand img{max-width:220px;max-height:48px;object-fit:contain}.hwads-login{border:1px solid #1769aa;border-radius:5px;padding:8px 24px;color:#1769aa;text-decoration:none;font-weight:600}.hwads-device-page main{max-width:1440px;margin:auto;padding:32px 5%;text-align:center}.hwads-device-page h1{font-size:clamp(25px,3.5vw,44px);line-height:1.2;margin:8px 0}.hwads-subtitle,.hwads-device-help{color:#647084;font-size:clamp(15px,2vw,22px)}.hwads-device-display{margin:28px auto 20px;border-radius:14px;overflow:hidden;background:#f3f5f7;max-width:1200px}.hwads-device-display iframe[style*="9/16"]{max-height:75vh;width:auto!important;max-width:100%;margin:auto}.hwads-device-note{font-size:13px;color:#647084}.hwads-device-page footer{border-top:1px solid #dce1e7;text-align:center;margin:0 5%;padding:20px;color:#647084;font-size:14px}';
    }
    static function setting() {
        $value=(int)(get_option('hpads_settings',[])['device_page_id'] ?? 0);
        echo '<h3>Public HWADS Box page</h3><p>Choose a published page built with WordPress or Divi. Add <code>[hwads_device]</code> for a login button and the selected HWADS Box’s configured playback. Its assignments, schedules and playback groups are used automatically.</p>';
        wp_dropdown_pages(['name'=>'device_page_id','selected'=>$value,'show_option_none'=>'Default public welcome page','option_none_value'=>0,'post_status'=>'publish']);
    }
    static function page() {
        $pid=(int)(get_option('hpads_settings',[])['device_page_id'] ?? 0);$post=$pid?get_post($pid):null;
        if ($post && $post->post_type==='page' && $post->post_status==='publish' && !$post->post_password) {
            wp_safe_redirect(add_query_arg('hwads_device',absint($_GET['device_id'] ?? 0),get_permalink($pid)));return;
        }
        status_header(200);header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html(get_bloginfo('name')).'</title><style>body{margin:0}'.self::styles().'</style></head><body>';
        echo self::shortcode();echo '</body></html>';
    }
}
