<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Post_Types {
    const TYPES=['hpads_venue'=>'Venues','hpads_location'=>'Locations','hpads_device'=>'Devices','hpads_advertiser'=>'Advertisers','hpads_template'=>'My Ad Templates','hpads_placement'=>'My Ad Placements','hpads_playlist'=>'Playlists'];
    const KINDS=['hpads_venue'=>'company','hpads_location'=>'location','hpads_device'=>'device','hpads_advertiser'=>'advertiser','hpads_template'=>'creative','hpads_placement'=>'creative','hpads_playlist'=>'playlist'];
    static function register() {
        foreach (self::TYPES as $type=>$label) {
            $singular=['hpads_template'=>'Ad Template','hpads_placement'=>'Ad Placement','hpads_playlist'=>'Playlist'][$type] ?? rtrim($label,'s');
            register_post_type($type,['label'=>$label,'labels'=>['name'=>$label,'singular_name'=>$singular,'add_new_item'=>'Add New '.$singular,'edit_item'=>'Edit '.$singular,'new_item'=>'New '.$singular],'public'=>false,'publicly_queryable'=>false,'show_ui'=>true,'show_in_menu'=>false,'show_in_rest'=>false,'rewrite'=>false,'supports'=>in_array($type,['hpads_template','hpads_placement'],true)?['title','editor']:['title'],'capabilities'=>['edit_posts'=>'hpads_access','create_posts'=>'hpads_access','publish_posts'=>HPADS_Native_Editor::supports($type)?'hpads_access':'do_not_allow','delete_posts'=>'do_not_allow','edit_others_posts'=>'hpads_access','read_private_posts'=>'hpads_access'],'map_meta_cap'=>true,'delete_with_user'=>false]);
            add_filter('manage_'.$type.'_posts_columns',[__CLASS__,'columns']);
            add_action('manage_'.$type.'_posts_custom_column',[__CLASS__,'column'],10,2);
        }
    }
    static function init() {
        add_action('admin_init',[__CLASS__,'editor_redirect']);
        add_filter('parent_file',[__CLASS__,'menu_parent']);
        add_filter('submenu_file',[__CLASS__,'menu_child']);
        add_action('pre_get_posts',[__CLASS__,'scope']);
        add_filter('map_meta_cap',[__CLASS__,'caps'],10,4);
        add_filter('wp_count_posts',[__CLASS__,'counts'],10,3);
        add_filter('post_row_actions',[__CLASS__,'actions'],10,2);
        add_filter('get_edit_post_link',[__CLASS__,'edit_link'],10,3);
        add_action('restrict_manage_posts',[__CLASS__,'upload_panel'],10,2);
    }
    static function menu_section() {
        $screen=get_current_screen();
        $type=$screen?$screen->post_type:'';
        if (isset(self::KINDS[$type])) { return $type==='hpads_template'?'templates':($type==='hpads_placement'?'playlist':self::KINDS[$type]); }
        $page=sanitize_key($_GET['page'] ?? '');
        foreach (array_keys(HPADS_Admin::PAGES) as $section) { if ($page==='hpads-'.$section) { return $section; } }
        return null;
    }
    static function menu_parent($parent) { return self::menu_section()!==null?'hpads-dashboard':$parent; }
    static function menu_child($child) { $section=self::menu_section();return $section!==null?'hpads-'.$section:$child; }
    static function kind_page($post) { return $post->post_type==='hpads_template'?'templates':self::KINDS[$post->post_type]; }
    static function record($pid) { $id=get_post_meta($pid,'_hpads_id',true);return $id?HPADS_Core::get((int)$id):null; }
    static function caps($caps,$cap,$uid,$args) {
        if (!in_array($cap,['edit_post','read_post','delete_post'],true) || empty($args[0])) { return $caps; }
        $p=get_post($args[0]);if (!$p || !isset(self::TYPES[$p->post_type])) { return $caps; }
        // HPADS record checks are based on the logged-in actor, matching native admin requests.
        if ((int)$uid!==get_current_user_id() || $cap==='delete_post') { return ['do_not_allow']; }
        $r=self::record($p->ID);if (!$r && HPADS_Native_Editor::supports($p->post_type) && (int)$p->post_author===(int)$uid && HPADS_Admin::can_add(self::KINDS[$p->post_type])) { return ['hpads_access']; }return $r && HPADS_Core::can($r,$cap==='edit_post')?['hpads_access']:['do_not_allow'];
    }
    static function scope($q) {
        if (!is_admin() || !$q->is_main_query() || !HPADS_Storage::active()) { return; }
        $type=$q->get('post_type');if (!is_string($type) || !isset(self::KINDS[$type])) { return; }
        $ids=[];foreach (HPADS_Core::all(self::KINDS[$type]) as $r) {
            if (HPADS_Storage::type($r['kind'],$r['data'])!==$type) { continue; }
            if ($type==='hpads_placement' && empty($_GET['show_past']) && in_array(HPADS_Placements::time_state($r),['past','inactive'],true)) { continue; }
            $ids[]=HPADS_Storage::post_id($r['id']);
        }
        $q->set('post__in',$ids?:[0]);
    }
    static function counts($counts,$type,$perm) {
        if (!isset(self::KINDS[$type]) || !HPADS_Storage::active()) { return $counts; }
        $out=new stdClass();foreach (get_post_stati() as $status) { $out->$status=0; }
        foreach (HPADS_Core::all(self::KINDS[$type]) as $r) { if (HPADS_Storage::type($r['kind'],$r['data'])!==$type) { continue; }$status=get_post_status(HPADS_Storage::post_id($r['id']));if ($status) { $out->$status++; } }
        return $out;
    }
    static function edit_link($link,$pid,$context) {
        $p=get_post($pid);if (!$p || !isset(self::KINDS[$p->post_type]) || !HPADS_Storage::active()) { return $link; }
        if (HPADS_Native_Editor::supports($p->post_type)) { return $link; }
        $r=self::record($pid);return $r?HPADS_Admin::url(self::kind_page($p),$r['id']):'';
    }
    static function editor_redirect() {
        global $pagenow;
        if (!HPADS_Storage::active()) { return; }
        $page=sanitize_key($_GET['page'] ?? '');
        if ($pagenow==='admin.php' && isset($_GET['edit']) && in_array($page,['hpads-creative','hpads-templates','hpads-playlist'],true)) { $type=$page==='hpads-templates'?'hpads_template':($page==='hpads-playlist'?'hpads_playlist':'hpads_placement');wp_safe_redirect(HPADS_Native_Editor::url($type,absint($_GET['edit'])));exit; }
        if ($pagenow==='admin.php' && !isset($_GET['edit'])) { foreach (self::KINDS as $type=>$kind) { $slug=$type==='hpads_template'?'templates':$kind; if ($page==='hpads-'.$slug) { wp_safe_redirect(add_query_arg('post_type',$type,admin_url('edit.php')));exit; } } }
        if ($pagenow==='post-new.php' && isset(self::KINDS[$_GET['post_type'] ?? ''])) {
            $type=sanitize_key($_GET['post_type']);if (HPADS_Native_Editor::supports($type)) { return; }$kind=$type==='hpads_template'?'templates':self::KINDS[$type];wp_safe_redirect(HPADS_Admin::url($kind,0));exit;
        }
        if ($pagenow!=='post.php' || empty($_REQUEST['post'])) { return; }
        $p=get_post(absint($_REQUEST['post']));if (!$p || !isset(self::KINDS[$p->post_type])) { return; }
        if (HPADS_Native_Editor::supports($p->post_type)) { return; }
        $r=self::record($p->ID);if (!$r || !HPADS_Core::can($r)) { HPADS_Admin::fail('Entry unavailable.'); }
        if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') { HPADS_Admin::fail('Save this record through its HWADS editor.'); }
        wp_safe_redirect(HPADS_Admin::url(self::kind_page($p),$r['id']));exit;
    }
    static function actions($actions,$p) {
        if (!isset(self::KINDS[$p->post_type])) { return $actions; }
        $r=self::record($p->ID);if (!$r || !HPADS_Core::can($r)) { return []; }
        $out=['edit'=>'<a href="'.esc_url(HPADS_Admin::url(self::kind_page($p),$r['id'])).'">'.(HPADS_Core::can($r,true)?'Edit':'View').'</a>'];
        if ($p->post_type==='hpads_placement' && HPADS_Placements::published_item($r['id'])) { $out['view']='<a href="'.esc_url(HPADS_Placements::public_url($r['id'])).'">View Landing Page</a>'; }
        return $out;
    }
    static function columns($columns) { return ['title'=>'Name','hpads_id'=>'ID','hpads_parent'=>'Owner / location','hpads_state'=>'HWADS status','date'=>'Date']; }
    static function column($column,$pid) {
        $r=self::record($pid);if (!$r || !HPADS_Core::can($r)) { return; }
        if ($column==='hpads_id') { echo (int)$r['id']; }
        if ($column==='hpads_parent') { $p=$r['kind']==='creative'?HPADS_Workflow::owner_record($r):HPADS_Core::get($r['parent_id']);echo esc_html($p['title'] ?? ''); }
        if ($column==='hpads_state' && $r['kind']==='company') { echo empty($r['data']['active']) && isset($r['data']['active'])?'Inactive':'Active';return; }
        if ($column==='hpads_state') { echo esc_html($r['kind']==='creative'?(HPADS_Placements::is_template($r)?'Template':HPADS_Workflow::summary($r)):(!empty($r['data']['published_version'])?'Published version #'.$r['data']['published_version']:'')); }
    }
    static function upload_panel($type,$which='top') {
        if ($which!=='top') { return; }
        if ($type==='hpads_playlist') { echo '<a class="button" href="'.esc_url(add_query_arg('post_type','hpads_placement',admin_url('edit.php'))).'">All Ad Placements</a> '; }
        if ($type==='hpads_template' && HPADS_Storage::active()) { HPADS_Placements::template_upload_panel(); }
        if ($type==='hpads_placement') { echo '<label><input type="checkbox" name="show_past" value="1" '.checked(!empty($_GET['show_past']),true,false).'> Include past/inactive placements</label>'; }
    }
    static function notice() {
        if (HPADS_Storage::active() || !HPADS_Workflow::global_admin()) { return; }
        global $wpdb;$count=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.HPADS_Core::table());
        echo '<div class="notice notice-warning"><p><strong>HWADS post-type upgrade:</strong> '.(int)$count.' records will be copied to WordPress posts. Existing IDs, device keys, media and published versions are preserved; legacy tables are retained. Back up your database and uploads first.</p><form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="hpads_migrate">';wp_nonce_field('hpads_migrate');submit_button('Migrate HWADS Records','primary','submit',false);echo '</form><p>Editing is temporarily unavailable while the upgrade runs.</p></div>';
    }
    static function migrate() {
        if (!HPADS_Workflow::global_admin()) { wp_die('Not allowed.','HWADS',['response'=>403]); }
        check_admin_referer('hpads_migrate');$result=HPADS_Storage::migrate();if (is_wp_error($result)) { HPADS_Admin::fail($result->get_error_message()); }
        wp_safe_redirect(HPADS_Admin::url('dashboard'));exit;
    }
}
