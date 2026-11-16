<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Admin_Access {
    static function plugin_upload_admin($user=null) {
        $u=$user?:wp_get_current_user();return $u && $u->has_cap('hpads_access') && ($u->has_cap('hpads_team_admin') || $u->has_cap('hpads_admin'));
    }
    static function restricted($user=null) {
        $u=$user?:wp_get_current_user();if (!$u || !$u->has_cap('hpads_access')) { return false; }
        if (self::plugin_upload_admin($u) && !$u->has_cap('manage_options') && !$u->has_cap('hpads_owner')) { return true; }
        foreach (['manage_options','edit_users','install_plugins','hpads_owner','hpads_team_admin','hpads_producer','hpads_sales','hpads_admin','hpads_account_manager'] as $cap) { if ($u->has_cap($cap)) { return false; } }
        foreach (['hpads_designer','hpads_venue_team','hpads_venue_admin','hpads_venue_user','hpads_venue','hpads_advertiser_admin','hpads_advertiser_user','hpads_advertiser'] as $cap) { if ($u->has_cap($cap)) { return true; } }return false;
    }
    static function menus() {
        if (self::plugin_upload_admin() && current_user_can('install_plugins') && !HPADS_View_As::state()) { add_submenu_page('hpads-dashboard','Add New Plugin','Add New Plugin','install_plugins','plugin-install.php?tab=upload'); }
        if (!self::restricted()) { return; }global $menu;
        foreach ((array)$menu as $item) { if ($item[2]!=='hpads-dashboard') { remove_menu_page($item[2]); } }
    }
    static function allowed_request($page,$query) {
        if (self::plugin_upload_admin() && !HPADS_View_As::state()) {
            if ($page==='plugin-install.php') { return ($query['tab'] ?? '')==='upload' && current_user_can('install_plugins'); }
            if ($page==='update.php') { return ($query['action'] ?? '')==='upload-plugin' && current_user_can('install_plugins'); }
            if ($page==='plugins.php') { return ($query['action'] ?? '')==='activate' && current_user_can('activate_plugins'); }
        }

        if ($page==='profile.php') { return true; }
        if ($page==='admin.php') { $slug=$query['page'] ?? '';if ($slug==='hwads-migration') { return HPADS_Migration::allowed(); }if ($slug==='hwads-pending-playback') { return HPADS_Admin::can_section('device'); }if ($slug==='hwads-engagement') { return HPADS_Admin::can_section('engagement'); }return is_string($slug) && strpos($slug,'hpads-')===0 && isset(HPADS_Admin::PAGES[substr($slug,6)]) && HPADS_Admin::can_section(substr($slug,6)); }
        if (in_array($page,['edit.php','post-new.php'],true)) { $type=$query['post_type'] ?? '';return is_string($type) && isset(HPADS_Post_Types::TYPES[$type]); }
        if ($page==='post.php') { $p=get_post(absint($query['post'] ?? $query['post_ID'] ?? 0));return $p && isset(HPADS_Post_Types::TYPES[$p->post_type]); }
        if ($page==='admin-post.php') { return in_array($query['action'] ?? '',['hwads_content_share','hwads_shared_copy','hwads_copy_playlist','hwads_copy_template','hwads_queue_snapshot','hwads_queue_purge','hwads_export','hwads_import','hpads_ad_review_preview','hpads_save','hpads_device_settings','hpads_playlist_preview','hpads_landing_preview','hwads_purge_device','hwads_update_policy','hwads_view_as'],true); }
        if (in_array($page,['async-upload.php','media-upload.php'],true)) { return current_user_can('upload_files'); }
        return false;
    }
    static function guard() {
        if (wp_doing_ajax()) { return; }global $pagenow;
        $query=array_merge($_GET,$_POST);$section=null;
        if ($pagenow==='admin.php') { $slug=$query['page'] ?? '';if ($slug==='hwads-pending-playback') { $section='device'; }elseif ($slug==='hwads-engagement') { $section='engagement'; }elseif (is_string($slug) && strpos($slug,'hpads-')===0 && isset(HPADS_Admin::PAGES[substr($slug,6)])) { $section=substr($slug,6); } }
        if (in_array($pagenow,['edit.php','post-new.php'],true)) { $type=$query['post_type'] ?? '';if (is_string($type) && isset(HPADS_Post_Types::KINDS[$type])) { $section=$type==='hpads_template'?'templates':HPADS_Post_Types::KINDS[$type]; } }
        if ($section!==null && !HPADS_Admin::can_section($section)) { wp_die('This section is not available to your role or assigned teams.','HWADS',['response'=>403]); }
        if (!self::restricted()) { return; }
        if (self::plugin_upload_admin() && $pagenow==='plugins.php' && empty($query['action']) && !empty($query['activate']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET')==='GET') { wp_safe_redirect(HPADS_Admin::url('dashboard'));exit; }
        if ($pagenow==='index.php' && ($_SERVER['REQUEST_METHOD'] ?? 'GET')==='GET') { wp_safe_redirect(HPADS_Admin::url('dashboard'));exit; }
        if (!self::allowed_request($pagenow,array_merge($_GET,$_POST))) { wp_die('This account can access HWADS and its own profile only.','HWADS',['response'=>403,'back_link'=>true]); }
    }
    static function toolbar($bar) {
        if (!self::restricted()) { return; }
        foreach ($bar->get_nodes() ?: [] as $node) { if ((empty($node->parent) || $node->parent==='top-secondary') && !in_array($node->id,['my-account','top-secondary'],true)) { $bar->remove_node($node->id); } }
        $bar->add_node(['id'=>'hpads-control','title'=>'HWADS','href'=>HPADS_Admin::url('dashboard')]);
    }
    static function login($redirect,$requested,$user) {
        if (!($user instanceof WP_User) || !self::restricted($user)) { return $redirect; }
        $parts=wp_parse_url($redirect);$admin_path=wp_parse_url(admin_url(),PHP_URL_PATH);
        if (!$parts || strpos($parts['path'] ?? '',$admin_path)!==0) { return $redirect; }
        parse_str($parts['query'] ?? '',$query);$page=basename($parts['path']);
        return self::allowed_request($page,$query)?$redirect:HPADS_Admin::url('dashboard');
    }
}
