<?php
if (!defined('ABSPATH')) { exit; }
/** Session-scoped, read-only role simulation; the account's real roles and assignments never change. */
final class HPADS_View_As {
    static $suspended=false;
    static function init() {
        add_filter('user_has_cap',[__CLASS__,'caps'],999,4);
        add_action('admin_bar_menu',[__CLASS__,'toolbar'],1100);
        add_action('admin_notices',[__CLASS__,'banner']);
        add_action('admin_post_hwads_view_as',[__CLASS__,'change']);
        add_action('admin_init',[__CLASS__,'readonly'],0);
        add_filter('rest_pre_dispatch',function($response,$server,$request){return self::state() && !in_array($request->get_method(),['GET','HEAD','OPTIONS'],true)?new WP_Error('hwads_preview_readonly','Exit role preview before saving.',['status'=>403]):$response;},10,3);
    }
    static function owner($u=null) { $u=$u?:wp_get_current_user();return $u && !empty($u->allcaps['hpads_owner']); }
    static function key() { return 'hwads_view_'.hash('sha256',get_current_user_id().':'.wp_get_session_token()); }
    static function state() { if (self::$suspended || !self::owner()) { return []; }$v=get_transient(self::key());return is_array($v) && isset(HPADS_Workflow::ROLES[$v['role'] ?? '']) && $v['role']!=='hpads_owner'?$v:[]; }
    static function caps($all,$caps,$args,$user) {
        if (self::$suspended || !self::owner($user) || (int)$user->ID!==get_current_user_id()) { return $all; }
        $state=self::state();if (!$state) { return $all; }$role=get_role($state['role']);
        if (!$role) { return $all; }
        return array_merge(array_fill_keys(array_keys($all),false),$role->capabilities,['exist'=>true,'do_not_allow'=>false]);
    }
    static function assignments($type,$default) { $s=self::state();return $s?(!empty($s[$type])?[(int)$s[$type]]:[]):$default; }
    static function link($role='') { return wp_nonce_url(add_query_arg(['action'=>'hwads_view_as','role'=>$role],admin_url('admin-post.php')),'hwads_view_as'); }
    static function toolbar($bar) {
        if (!self::owner()) { return; }$s=self::state();$bar->add_node(['id'=>'hwads-view-as','title'=>$s?'Viewing as: '.HPADS_Workflow::ROLES[$s['role']]:'View as','href'=>'#']);
        $bar->add_node(['id'=>'hwads-view-owner','parent'=>'hwads-view-as','title'=>'Return to SuperAdmin','href'=>self::link()]);
        foreach (HPADS_Workflow::ROLES as $role=>$label) { if ($role==='hpads_owner' || in_array($role,['hpads_admin','hpads_venue','hpads_advertiser'],true)) { continue; }$bar->add_node(['id'=>'hwads-view-'.$role,'parent'=>'hwads-view-as','title'=>esc_html($label),'href'=>self::link($role)]); }
    }
    static function banner() {
        $s=self::state();if (!$s) { return; }
        echo '<div class="notice notice-warning"><p><strong>Read-only role preview: '.esc_html(HPADS_Workflow::ROLES[$s['role']]).'.</strong> Your actual account is unchanged. Choose a team below to preview scoped records. <a href="'.esc_url(self::link()).'">Return to SuperAdmin</a></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_view_as"><input type="hidden" name="role" value="'.esc_attr($s['role']).'">';wp_nonce_field('hwads_view_as');
        self::$suspended=true;
        try { foreach (['companies'=>'company','advertisers'=>'advertiser','locations'=>'location'] as $key=>$kind) { echo '<label>'.(['companies'=>'Venue','advertisers'=>'Advertiser','locations'=>'Designer location'][$key]).' <select name="'.esc_attr($key).'"><option value="0">No team selected</option>';foreach (HPADS_Core::entity_ids($kind) as $id) { $r=HPADS_Core::get($id,$kind);if ($r) { echo '<option value="'.(int)$id.'" '.selected((int)($s[$key] ?? 0),(int)$id,false).'>'.esc_html($r['title']).'</option>'; } }echo '</select></label> '; } }
        finally { self::$suspended=false; }
        echo '<button class="button" type="submit">Preview team</button></form><p>Preview applies to this login session, including other tabs. Exit preview before saving changes.</p></div>';
    }
    static function change() {
        if (!self::owner()) { wp_die('Only SuperAdmin can preview roles.','HWADS',['response'=>403]); }check_admin_referer('hwads_view_as');
        $role=sanitize_key($_REQUEST['role'] ?? '');
        if ($role && $role!=='hpads_owner') {
            if (!isset(HPADS_Workflow::ROLES[$role])) { wp_die('Unknown role.','HWADS',['response'=>400]); }
            $v=['role'=>$role];foreach (['companies'=>'company','advertisers'=>'advertiser','locations'=>'location'] as $key=>$kind) { $id=absint($_POST[$key] ?? 0);if ($id && !HPADS_Core::get($id,$kind)) { wp_die('Unknown team.','HWADS',['response'=>400]); }$v[$key]=$id; }set_transient(self::key(),$v,8*HOUR_IN_SECONDS);
        } else { delete_transient(self::key()); }
        wp_safe_redirect(admin_url('admin.php?page=hpads-dashboard'));exit;
    }
    static function readonly() {
        if (!self::state()) { return; }$action=sanitize_key($_REQUEST['action'] ?? '');
        if ($action==='hwads_view_as') { return; }
        $safe_ajax=wp_doing_ajax() && in_array($action,['heartbeat','query-attachments','hpads_owner_playlists'],true);
        global $pagenow;$unsafe_route=$pagenow==='admin-post.php' && !in_array($action,['hpads_ad_review_preview','hpads_playlist_preview','hpads_landing_preview'],true);
        if ($unsafe_route || ($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST' && !$safe_ajax || in_array($action,['delete','trash','untrash','activate','deactivate','update','install-plugin','delete-plugin'],true)) { wp_die('Role preview is read-only. Return to SuperAdmin before making changes.','HWADS',['response'=>403,'back_link'=>true]); }
    }
}
