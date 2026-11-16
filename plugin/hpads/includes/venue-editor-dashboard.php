<?php
if (!defined('ABSPATH')) { exit; }
/** Native list tables above the native venue form, with independent GET forms. */
final class HPADS_Venue_Editor_Dashboard {
    private static $html='';
    static function init() {
        add_action('load-post.php',[__CLASS__,'prepare']);
        add_action('admin_init',[__CLASS__,'prepare_readonly'],30);
        add_action('all_admin_notices',[__CLASS__,'display'],25);
        add_action('admin_enqueue_scripts',function(){if(self::$html){wp_enqueue_script('hwads-venue-editor',plugins_url('assets/venue-editor.js',HPADS_FILE),wp_script_is('hwads-native','registered')?['hpads','hwads-native']:['hpads'],HPADS_VERSION,true);}});
    }
    static function url($venue,$tab='playlists',$location=0) {
        $url=add_query_arg(['hwads_tab'=>$tab,'hwads_location'=>(int)$location],HPADS_Admin::url('company',$venue));
        return $tab==='settings'?$url.'#hwads-details':$url;
    }
    static function prepare() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET' || ($_GET['action'] ?? '')!=='edit') { return; }
        $post=get_post(absint($_GET['post'] ?? 0));
        if (!$post || $post->post_type!=='hpads_venue' || !current_user_can('edit_post',$post->ID)) { return; }
        $v=HPADS_Post_Types::record($post->ID);if (!$v || !HPADS_Core::can($v,true)) { return; }
        $tab=sanitize_key($_GET['hwads_tab'] ?? 'playlists');
        $types=['playlists'=>'hpads_playlist','templates'=>'hpads_placement','devices'=>'hpads_device','locations'=>'hpads_location'];
        if ($tab==='settings') { $tab='playlists'; }
        if (!isset($types[$tab])) { $tab='playlists'; }
        $lid=absint($_GET['hwads_location'] ?? 0);$l=$lid?HPADS_Core::get($lid,'location'):null;
        if ($lid && (!$l || !HPADS_Core::can($l) || (int)$l['parent_id']!==(int)$v['id'])) { wp_die('Location does not belong to an available venue.','HWADS',['response'=>403]); }
        self::$html=self::build($v,$tab,$l);
    }
    static function prepare_readonly() {
        global $pagenow;if ($pagenow!=='admin.php' || ($_GET['page'] ?? '')!=='hpads-company' || empty($_GET['edit']) || ($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET') { return; }
        $v=HPADS_Core::get(absint($_GET['edit']),'company');if (!$v || !HPADS_Core::can($v) || HPADS_Core::can($v,true)) { return; }
        $tab=sanitize_key($_GET['hwads_tab'] ?? 'playlists');if ($tab==='settings') { $tab='playlists'; }
        $lid=absint($_GET['hwads_location'] ?? 0);$l=$lid?HPADS_Core::get($lid,'location'):null;
        if ($lid && (!$l || !HPADS_Core::can($l) || (int)$l['parent_id']!==(int)$v['id'])) { wp_die('Location unavailable.','HWADS',['response'=>403]); }
        self::$html=self::build($v,$tab,$l);
    }
    static function display() { echo self::$html;self::$html=''; }
    static function build($v,$tab,$l=null) {
        if (!HPADS_Core::can($v)) { return ''; }
        $types=['playlists'=>'hpads_playlist','templates'=>'hpads_placement','devices'=>'hpads_device','locations'=>'hpads_location'];
        $kinds=['playlists'=>'playlist','templates'=>'templates','devices'=>'device','locations'=>'location'];
        if (!isset($types[$tab]) || !HPADS_Admin::can_section($kinds[$tab])) { return ''; }
        if ($l && (!HPADS_Core::can($l) || (int)$l['parent_id']!==(int)$v['id'])) { return ''; }
        require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
        require_once ABSPATH.'wp-admin/includes/class-wp-posts-list-table.php';
        // wp_edit_posts_query() runs the main admin query. Isolate it from the venue editor.
        $keys=['current_screen','post','post_ID','post_type','post_type_object','typenow','taxnow','pagenow','wp','wp_query','wp_the_query','wp_list_table','mode','avail_post_stati','per_page','cat','id','authordata','currentday','currentmonth','page','pages','multipage','more','numpages','request','posts','query_string','single'];
        $saved=[];foreach ($keys as $key) { $saved[$key]=[array_key_exists($key,$GLOBALS),$GLOBALS[$key] ?? null]; }
        $get=$_GET;$request=$_REQUEST;$uri=$_SERVER['REQUEST_URI'] ?? '';$level=ob_get_level();
        $bulk='bulk_actions-edit-'.$types[$tab];add_filter($bulk,'__return_empty_array');
        $columns='manage_'.$types[$tab].'_posts_columns';$no_checks=function($values){unset($values['cb']);return $values;};add_filter($columns,$no_checks,100);
        try {
            $_GET=array_intersect_key($get,array_flip(['s','m','paged','orderby','order','post_status','author','copy_template']));
            $_GET+=['hwads_tab'=>$tab,'post_type'=>$types[$tab],'hwads_venue'=>(int)$v['id'],'hwads_location'=>(int)($l['id'] ?? 0)];
            if ($tab==='templates') { $ad_tab=sanitize_key($get['hwads_ads'] ?? 'templates');$_GET['hwads_ads']=in_array($ad_tab,['all','templates','placements'],true)?$ad_tab:'templates'; }
            $_REQUEST=$_GET;
            $GLOBALS['pagenow']='edit.php';$GLOBALS['typenow']=$types[$tab];$GLOBALS['post_type']=$types[$tab];
            $GLOBALS['wp']=new WP();$GLOBALS['wp_the_query']=new WP_Query();$GLOBALS['wp_query']=$GLOBALS['wp_the_query'];
            set_current_screen('edit-'.$types[$tab]);
            parse_str(wp_parse_url(self::url($v['id'],$tab,$l['id'] ?? 0),PHP_URL_QUERY),$identity);$_SERVER['REQUEST_URI']=wp_parse_url(self::url($v['id'],$tab,$l['id'] ?? 0),PHP_URL_PATH).'?'.http_build_query(array_merge(array_intersect_key($_GET,array_flip(['s','m','paged','orderby','order','post_status','author','hwads_ads','copy_template'])),$identity));
            $table=new WP_Posts_List_Table(['screen'=>get_current_screen()]);$table->prepare_items();
            ob_start();
            echo '<section id="hwads-venue-dashboard" class="wrap hwads-location-context" data-location="'.(int)($l['id'] ?? 0).'" data-venue="'.(int)$v['id'].'" aria-label="Venue dashboard"><h1>Venue Dashboard</h1><h2>'.esc_html($v['title']).'</h2>';
            echo '<form method="get" action="'.esc_url(strtok(self::url($v['id'],$tab,$l['id'] ?? 0),'?')).'" class="hwads-dashboard-filters">';
            self::hidden($v,$tab);echo '<label>Location <select name="hwads_location"><option value="0">All locations</option>';
            foreach (HPADS_Core::all('location') as $location) { if ((int)$location['parent_id']!==(int)$v['id']) { continue; }echo '<option value="'.(int)$location['id'].'" '.selected($l['id'] ?? 0,$location['id'],false).'>'.esc_html($location['title']).'</option>'; }
            echo '</select></label> ';submit_button('Apply','secondary','submit',false);echo '</form><nav class="nav-tab-wrapper">';
            foreach (['playlists'=>'Playlists','templates'=>'Templates','devices'=>'Devices','locations'=>'Locations','settings'=>'Settings'] as $key=>$label) {
                if ($key!=='settings' && !HPADS_Admin::can_section($kinds[$key])) { continue; }
                $url=$key==='settings'?'#hwads-details':self::url($v['id'],$key,$l['id'] ?? 0);
                echo '<a class="nav-tab'.($key===$tab?' nav-tab-active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>';
            }
            echo '</nav><h2>'.esc_html(ucfirst($tab)).'</h2>';
            if (current_user_can('create_'.$types[$tab])) { $kind=$tab==='templates'?'templates':$kinds[$tab];$url=HPADS_Admin::url($kind,0);if ($l && in_array($tab,['playlists','devices'],true)) { $url=add_query_arg('location_id',$l['id'],$url); }if ($tab==='locations') { $url=add_query_arg('venue_id',$v['id'],$url); }if ($tab!=='templates') { echo '<p><a class="button" href="'.esc_url($url).'">Add New '.esc_html(['playlists'=>'Playlist','devices'=>'Device','locations'=>'Location'][$tab]).'</a></p>'; } }
            if ($tab==='devices') { $records=array_values(array_filter(HPADS_Core::all('device'),['HPADS_Venue_Workspace','matches_context']));HPADS_Dashboards::cards(HPADS_Dashboards::device_totals($records,HPADS_Dashboards::health_rows($records)),'Device status overview'); }
            if ($tab==='templates') { HPADS_Venue_Workspace::template_copy_panel(); }
            $table->views();
            echo '<form method="get" action="'.esc_url(strtok(self::url($v['id'],$tab,$l['id'] ?? 0),'?')).'" class="hwads-venue-list">';self::hidden($v,$tab);$table->search_box('Search '.ucfirst($tab),'hwads-venue-list');$table->display();echo '</form></section>';
            $html=ob_get_clean();
            // Keep native status, sort and pagination links on this combined editor.
            $tags=new WP_HTML_Tag_Processor($html);
            while ($tags->next_tag()) {
                // Native list columns and the editor both use IDs such as "title".
                // Keep editor scripts targeting the input, not the table heading.
                if ($tags->get_tag()==='TH' && $tags->get_attribute('id')) { $tags->set_attribute('id','hwads-venue-list-'.$tags->get_attribute('id')); }
                if ($tags->get_tag()!=='A') { continue; }
                $href=$tags->get_attribute('href');if (!is_string($href)) { continue; }$parts=wp_parse_url($href);if (basename($parts['path'] ?? '')!=='edit.php') { continue; }
                parse_str($parts['query'] ?? '',$args);if (($args['post_type'] ?? '')!==$types[$tab]) { continue; }
                unset($args['post_type'],$args['hwads_venue']);$tags->set_attribute('href',add_query_arg($args,self::url($v['id'],$tab,$l['id'] ?? 0)));
            }
            return $tags->get_updated_html();
        } finally {
            remove_filter($bulk,'__return_empty_array');remove_filter($columns,$no_checks,100);
            while (ob_get_level()>$level) { ob_end_clean(); }
            $_GET=$get;$_REQUEST=$request;$_SERVER['REQUEST_URI']=$uri;
            foreach ($saved as $key=>[$exists,$value]) { if ($exists) { $GLOBALS[$key]=$value; }else { unset($GLOBALS[$key]); } }
        }
    }
    private static function hidden($v,$tab) {
        parse_str(wp_parse_url(self::url($v['id'],$tab),PHP_URL_QUERY),$identity);
        foreach (['post','action','page','edit','hwads_tab'] as $key) { if (isset($identity[$key])) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($identity[$key]).'">'; } }
    }
}
