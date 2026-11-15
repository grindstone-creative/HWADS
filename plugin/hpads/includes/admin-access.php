<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Admin_Access {
    static function restricted($user=null) {
        $u=$user?:wp_get_current_user();if (!$u || !$u->has_cap('hpads_access')) { return false; }
        foreach (['manage_options','edit_users','install_plugins','hpads_owner','hpads_team_admin','hpads_producer','hpads_sales','hpads_admin','hpads_account_manager'] as $cap) { if ($u->has_cap($cap)) { return false; } }
        foreach (['hpads_venue_team','hpads_venue_admin','hpads_venue_user','hpads_venue','hpads_advertiser_admin','hpads_advertiser_user','hpads_advertiser'] as $cap) { if ($u->has_cap($cap)) { return true; } }return false;
    }
    static function menus() {
        if (!self::restricted()) { return; }global $menu;
        foreach ((array)$menu as $item) { if ($item[2]!=='hpads-dashboard') { remove_menu_page($item[2]); } }
    }
    static function allowed_request($page,$query) {
        if ($page==='profile.php') { return true; }
        if ($page==='admin.php') { $slug=$query['page'] ?? '';return is_string($slug) && strpos($slug,'hpads-')===0 && isset(HPADS_Admin::PAGES[substr($slug,6)]); }
        if (in_array($page,['edit.php','post-new.php'],true)) { $type=$query['post_type'] ?? '';return is_string($type) && isset(HPADS_Post_Types::TYPES[$type]); }
        if ($page==='post.php') { $p=get_post(absint($query['post'] ?? $query['post_ID'] ?? 0));return $p && isset(HPADS_Post_Types::TYPES[$p->post_type]); }
        if ($page==='admin-post.php') { return in_array($query['action'] ?? '',['hpads_save','hpads_device_settings','hpads_playlist_preview','hpads_landing_preview','hwads_purge_device'],true); }
        if (in_array($page,['async-upload.php','media-upload.php'],true)) { return current_user_can('upload_files'); }
        return false;
    }
    static function guard() {
        if (!self::restricted() || wp_doing_ajax()) { return; }global $pagenow;
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
