<?php
if (!defined('ABSPATH')) { exit; }
/** Venue/location navigation and independent, unpublished copies. */
final class HPADS_Venue_Workspace {
    static function init() {
        add_action('edit_form_after_title',[__CLASS__,'navigation'],5);
        add_action('admin_init',[__CLASS__,'native_context'],5);
        add_action('all_admin_notices',[__CLASS__,'template_copy_panel'],15);
        add_action('restrict_manage_posts',[__CLASS__,'hidden_context']);
        foreach (['hpads_playlist','hpads_device','hpads_location','hpads_placement'] as $type) { add_filter('views_edit-'.$type,[__CLASS__,'preserve_views'],50); }

        add_action('admin_post_hwads_copy_playlist',[__CLASS__,'handle_copy']);
        add_action('admin_post_hwads_copy_template',[__CLASS__,'handle_template']);
        add_filter('post_row_actions',[__CLASS__,'row_actions'],20,2);
        add_action('admin_enqueue_scripts',function(){ $screen=get_current_screen();if (!$screen || (!isset(HPADS_Post_Types::TYPES[$screen->post_type]) && strpos($screen->id,'hpads-')===false)) { return; }wp_enqueue_style('hwads-venue-workspace',plugins_url('assets/venue-workspace.css',HPADS_FILE),[],HPADS_VERSION);wp_enqueue_script('hwads-venue-workspace',plugins_url('assets/venue-workspace.js',HPADS_FILE),[],HPADS_VERSION,true);});
    }
    static function url($location,$tab='playlists') {
        $l=HPADS_Core::get($location,'location');if (!$l || !HPADS_Core::can($l)) { return ''; }
        if ($tab==='settings') { return HPADS_Admin::url('location',$location); }
        return HPADS_Venue_Editor_Dashboard::url($l['parent_id'],$tab,$l['id']);
    }
    static function venue_url($venue,$tab='playlists') { return HPADS_Venue_Editor_Dashboard::url($venue,$tab); }
    static function context_venue() {
        $l=self::context_location();$id=absint($_GET['hwads_venue'] ?? ($l['parent_id'] ?? 0));
        $v=$id?HPADS_Core::get($id,'company'):null;return $v && HPADS_Core::can($v)?$v:null;
    }
    static function context_location() {
        if (empty($_GET['hwads_location'])) { return null; }
        $l=HPADS_Core::get(absint($_GET['hwads_location']),'location');return $l && HPADS_Core::can($l)?$l:null;
    }
    static function matches_context($r) {
        $lid=absint($_GET['hwads_location'] ?? 0);$vid=absint($_GET['hwads_venue'] ?? 0);
        if (!$lid && !$vid) { return true; }$l=self::context_location();$v=self::context_venue();
        if (($lid && !$l) || ($vid && !$v) || ($l && $vid && (int)$l['parent_id']!==$vid)) { return false; }
        if ($r['kind']==='creative') {
            if ($l) { return HPADS_Placements::is_template($r)?(bool)self::group($r,$l):in_array($lid,HPADS_Core::ids($r['data']['locations'] ?? []),true); }
            foreach (HPADS_Core::all('location') as $location) { if ((int)$location['parent_id']===$vid && (HPADS_Placements::is_template($r)?(bool)self::group($r,$location):in_array((int)$location['id'],HPADS_Core::ids($r['data']['locations'] ?? []),true))) { return true; } }return false;
        }
        if ($r['kind']==='location') { return $l?(int)$r['id']===$lid:(int)$r['parent_id']===$vid; }
        if (!in_array($r['kind'],['device','playlist'],true)) { return false; }
        return $l?(int)$r['parent_id']===$lid:HPADS_Core::company($r)===$vid;
    }
    static function preserve_views($views) {
        $args=[];foreach (['hwads_location','hwads_venue'] as $key) { if (isset($_GET[$key])) { $args[$key]=absint($_GET[$key]); } }
        if (!$args) { return $views; }
        if (isset($views['mine'])) {
            $type=sanitize_key($_GET['post_type'] ?? '');$kind=HPADS_Post_Types::KINDS[$type] ?? null;$mine=0;
            if ($kind) { foreach (HPADS_Core::all($kind) as $r) { if (!self::matches_context($r) || HPADS_Storage::type($r['kind'],$r['data'])!==$type) { continue; }$post=get_post(HPADS_Storage::post_id($r['id']));if ($post && (int)$post->post_author===get_current_user_id() && !in_array($post->post_status,['trash','auto-draft','inherit'],true)) { $mine++; } } }
            if ($mine) { $views['mine']=preg_replace('/<span class="count">.*?<\/span>/','<span class="count">('.number_format_i18n($mine).')</span>',$views['mine']); }else { unset($views['mine']); }
        }
        foreach ($views as &$view) { $view=preg_replace_callback('/href="([^"]+)"/',function($m)use($args){return 'href="'.esc_url(add_query_arg($args,html_entity_decode($m[1],ENT_QUOTES,'UTF-8'))).'"';},$view); }return $views;
    }
    static function hidden_context() {
        if (HPADS_Ad_Library::active()) { echo '<input type="hidden" name="hwads_ads" value="'.esc_attr(HPADS_Ad_Library::tab()).'">'; }
        foreach (['hwads_location','hwads_venue'] as $key) { if ($key==='hwads_venue' && isset($_GET['hwads_tab'])) { continue; }if (isset($_GET[$key])) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.absint($_GET[$key]).'">'; } }
    }
    static function native_context() {
        global $pagenow;if ($pagenow!=='edit.php') { return; }
        if (!empty($_GET['hwads_location']) && !self::context_location()) { wp_die('Location unavailable.','HWADS',['response'=>403]); }
        if (!empty($_GET['hwads_venue'])) { $v=HPADS_Core::get(absint($_GET['hwads_venue']),'company');if (!$v || !HPADS_Core::can($v)) { wp_die('Venue unavailable.','HWADS',['response'=>403]); } }
        $l=self::context_location();if ($l && !empty($_GET['hwads_venue']) && (int)$l['parent_id']!==absint($_GET['hwads_venue'])) { wp_die('Location does not belong to this venue.','HWADS',['response'=>403]); }
    }
    static function selectors($fleet,$tab,$v=null,$l=null) {
        $types=['playlists'=>'hpads_playlist','devices'=>'hpads_device','locations'=>'hpads_location','templates'=>'hpads_placement'];
        echo '<form method="get" action="'.esc_url(admin_url('edit.php')).'" class="hwads-dashboard-filters"><input type="hidden" name="post_type" value="'.esc_attr($types[$tab] ?? 'hpads_device').'">';
        if ($tab==='templates') { echo '<input type="hidden" name="hwads_ads" value="templates">'; }
        if ($fleet) { echo '<label>Venue <select name="hwads_venue"><option value="0">All venues</option>';foreach (HPADS_Core::all('company') as $venue) { echo '<option value="'.(int)$venue['id'].'" '.selected($v['id'] ?? 0,$venue['id'],false).'>'.esc_html($venue['title']).'</option>'; }echo '</select></label> '; }
        else { echo '<input type="hidden" name="hwads_venue" value="'.(int)$v['id'].'">'; }
        echo '<label>Location <select name="hwads_location"><option value="0">All locations</option>';foreach (HPADS_Core::all('location') as $location) { if (!$fleet && $v && (int)$location['parent_id']!==(int)$v['id']) { continue; }echo '<option data-venue="'.(int)$location['parent_id'].'" value="'.(int)$location['id'].'" '.selected($l['id'] ?? 0,$location['id'],false).'>'.esc_html($location['title']).'</option>'; }echo '</select></label> ';submit_button('Apply','secondary','submit',false);echo '</form>';
    }
    static function copy_url($p) { return add_query_arg(['page'=>'hpads-playlist','copy_playlist'=>(int)$p['id']],admin_url('admin.php')); }

    static function location_links($venue) {
        $v=HPADS_Core::get($venue,'company');if (!$v || !HPADS_Core::can($v)) { return ''; }
        $links=['<a href="'.esc_url(self::venue_url($venue)).'">Open venue dashboard</a>'];$found=false;foreach (HPADS_Core::all('location') as $l) { if ((int)$l['parent_id']===(int)$venue) { $found=true;$links[]='<a href="'.esc_url(self::url($l['id'])).'">'.esc_html($l['title']).' — View playlists</a>'; } }
        if (!$found) { $links[]='No locations yet'; }
        if (HPADS_Admin::can_add('location')) { $links[]='<a href="'.esc_url(add_query_arg('venue_id',$venue,HPADS_Admin::url('location',0))).'">Add location</a>'; }
        return implode('<br>',$links);
    }
    static function scope($ad) {
        $d=$ad['data'];[$type,$oid]=HPADS_Workflow::owner_ref($ad);
        return ['type'=>$d['template_scope'] ?? ($type==='company'?'venue':'personal'),'venue'=>(int)($d['template_venue'] ?? ($type==='company'?$oid:0)),'location'=>(int)($d['template_location'] ?? 0),'user'=>(int)($d['template_user'] ?? $d['created_by'] ?? 0)];
    }
    static function group($ad,$location) {
        if (!HPADS_Placements::is_template($ad) || !HPADS_Core::can($ad)) { return ''; }
        if (HPADS_Content_Access::grant($ad)) { return 'Shared with me'; }
        $s=self::scope($ad);
        if ($s['type']==='location') { return $s['location']===(int)$location['id']?'This location':''; }
        if ($s['type']==='venue') { return $s['venue']===(int)$location['parent_id']?'Venue templates':''; }
        return ($s['user']===get_current_user_id() || (!$s['user'] && HPADS_Core::can($ad,true)))?'My templates':'';
    }
    static function scope_input($in,$prior,$type,$oid) {
        $scope=sanitize_key($in['template_scope'] ?? $prior['template_scope'] ?? ($type==='company'?'venue':'personal'));
        if (!in_array($scope,['venue','location','personal'],true)) { return HPADS_Core::error('Choose a template scope.'); }
        $venue=absint($in['template_venue'] ?? $prior['template_venue'] ?? ($type==='company'?$oid:0));
        if (!$venue && $type==='company') { $venue=(int)$oid; }
        if ($scope==='venue' && $type==='company' && ($prior['owner_type'] ?? '')==='company' && $venue===(int)($prior['owner_id'] ?? 0)) { $venue=(int)$oid; }
        $location=absint($in['template_location'] ?? $prior['template_location'] ?? 0);
        if ($scope==='location') { $l=HPADS_Core::get($location,'location');if (!$l || !HPADS_Core::can($l) || !HPADS_Placements::author_owner($type,$oid)) { return HPADS_Core::error('Choose an available template location.',403); }$venue=(int)$l['parent_id']; }
        if ($scope!=='personal') {
            $v=HPADS_Core::get($venue,'company');if (!$v || !HPADS_Core::can($v) || ($type==='company' && (int)$oid!==$venue)) { return HPADS_Core::error('Template venue must match its owner venue.',403); }
            if ($type==='advertiser') { $a=HPADS_Core::get($oid,'advertiser');if (!in_array($venue,HPADS_Core::ids($a['data']['venues'] ?? []),true)) { return HPADS_Core::error('Template venue must be connected to the advertiser.',403); } }
        }
        if (HPADS_Content_Access::designer() && $type==='company' && !in_array((int)$oid,HPADS_Core::assigned('companies'),true) && ($scope!=='location' || !in_array($location,HPADS_Core::assigned('locations'),true))) { return HPADS_Core::error('Choose one of your assigned locations for this template.',403); }
        return ['template_scope'=>$scope,'template_venue'=>$scope==='personal'?0:$venue,'template_location'=>$scope==='location'?$location:0,'template_user'=>(int)($prior['template_user'] ?? $prior['created_by'] ?? get_current_user_id())];
    }
    static function scope_fields($d,$prefix='') {
        if (HPADS_Content_Access::designer() && ($d['owner_type']??'')==='company' && !isset($d['template_scope']) && !in_array((int)($d['owner_id']??0),HPADS_Core::assigned('companies'),true)) { foreach(HPADS_Core::assigned('locations') as $lid){$l=HPADS_Core::get($lid,'location');if($l && (int)$l['parent_id']===(int)($d['owner_id']??0)){$d['template_scope']='location';$d['template_location']=$lid;break;}} }
        $name=function($key)use($prefix){return $prefix?$prefix.'['.$key.']':$key;};
        HPADS_Admin::select('Template availability',$name('template_scope'),['venue'=>'Venue templates','location'=>'This location only','personal'=>'My templates'],$d['template_scope'] ?? (($d['owner_type'] ?? 'company')==='company'?'venue':'personal'));
        HPADS_Admin::select('Template venue',$name('template_venue'),[0=>'— Choose venue —']+HPADS_Admin::choices('company'),$d['template_venue'] ?? (($d['owner_type'] ?? '')==='company'?($d['owner_id'] ?? 0):0));
        HPADS_Admin::select('Template location (location scope only)',$name('template_location'),[0=>'— Choose location —']+HPADS_Admin::choices('location'),$d['template_location'] ?? 0);
        echo '<p class="description">Availability organizes the picker; existing ownership and permissions still apply.</p>';
    }
    static function destination($source,$location) {
        if (!$source || (!empty($source['id']) && !HPADS_Core::can($source)) || !$location || !HPADS_Core::can($location) || !HPADS_Admin::can_add('playlist')) { return false; }
        $probe=['id'=>0,'kind'=>'playlist','parent_id'=>$location['id'],'data'=>['purpose'=>$source['data']['purpose'] ?? 'primary']];
        return HPADS_Core::can($probe,true);
    }
    static function discard($ids) {
        global $wpdb;foreach (array_reverse($ids) as $id) { if (HPADS_Storage::active()) { wp_delete_post(HPADS_Storage::post_id($id),true); }else { $wpdb->delete(HPADS_Core::table(),['id'=>$id]); } }
    }
    static function copy_playlist($id,$lid,$title,$revision) {
        return HPADS_Playlist_Limits::locked($id,function()use($id,$lid,$title,$revision){
            $source=HPADS_Playlist_Limits::fresh($id,'playlist');$l=HPADS_Core::get($lid,'location');$title=sanitize_text_field($title);
            if (!self::destination($source,$l) || HPADS_View_As::state()) { return HPADS_Core::error('Playlist or destination unavailable.',403); }
            if ((int)$source['revision']!==(int)$revision) { return HPADS_Core::error('The playlist changed. Reload before copying.',409); }
            if (!$title) { return HPADS_Core::error('Enter a name for the copied playlist.'); }
            $valid=HPADS_Playlist_Limits::validate($source);if (is_wp_error($valid)) { return $valid; }
            $entries=[];
            foreach ($source['data']['items'] ?? [] as $item) {
                $ad=HPADS_Core::get($item['creative_id'] ?? 0,'creative');
                if (!$ad || !HPADS_Core::can($ad)) { return HPADS_Core::error('One or more source placements are unavailable.',403); }
                [$type,$oid]=HPADS_Workflow::owner_ref($ad);
                // Cross-venue venue content becomes destination-owned. Advertisers retain their identity and venue relationship.
                if ($type==='company') { $oid=(int)$l['parent_id']; }
                if (!HPADS_Placements::author_owner($type,$oid)) { return HPADS_Core::error('You cannot create content for the destination owner.',403); }
                $probe=['kind'=>'playlist','id'=>0,'parent_id'=>$lid,'data'=>$source['data']];
                if (!HPADS_Placements::can_bind($type,$oid,$probe)) { return HPADS_Core::error('An advertiser or owner is not eligible for the destination location.',403); }
                $entries[]=[$ad,$item,$type,$oid];
            }
            $data=array_intersect_key($source['data'],array_fill_keys(['purpose','category','active_limit','description'],true));$data['items']=[];$data['copied_from']=$id;
            $pid=HPADS_Core::put('playlist',$title,$lid,$data);if (is_wp_error($pid)) { return $pid; }$created=[$pid];$p=HPADS_Core::get($pid,'playlist');
            foreach ($entries as [$ad,$item,$type,$oid]) {
                $d=HPADS_Placements::fresh_data($ad,'placement',$p);$d['owner_type']=$type;$d['owner_id']=$oid;
                foreach (['start','end','duration','active'] as $key) { $d[$key]=($ad['data']['record_type'] ?? '')==='placement'?($ad['data'][$key] ?? ($key==='active'?true:($key==='duration'?null:''))):($item[$key] ?? ($key==='active'?true:($key==='duration'?null:''))); }
                foreach (['template_scope','template_venue','template_location','template_user'] as $key) { unset($d[$key]); }
                $cid=HPADS_Core::put('creative',$ad['title'],$type==='advertiser'?$oid:0,$d);
                if (is_wp_error($cid)) { self::discard($created);return $cid; }$created[]=$cid;HPADS_Divi::copy_meta($ad['id'],$cid);$data['items'][]=['creative_id'=>$cid];
            }
            $result=HPADS_Core::put('playlist',$title,$lid,$data,$pid,$p['revision']);
            if (is_wp_error($result)) { self::discard($created); }return $result;
        });
    }
    static function copy_form($p) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_copy_playlist"><input type="hidden" name="source_id" value="'.(int)$p['id'].'"><input type="hidden" name="revision" value="'.(int)$p['revision'].'">';wp_nonce_field('hwads_copy_playlist_'.$p['id']);
        self::destination_fields($p);HPADS_Admin::field('New playlist name','copy_title',$p['title'].' — Copy');echo '<p>Independent placements. Shared media. Saved as a draft; destination approval rules apply. Copies the last saved playlist.</p>';submit_button('Copy playlist');echo '</form>';
    }
    static function destination_fields($p) {
        $locations=[];$venues=[];foreach (HPADS_Core::all('location') as $l) { if (self::destination($p,$l)) { $locations[]=$l;$v=HPADS_Core::get($l['parent_id'],'company');$venues[$l['parent_id']]=$v['title'] ?? 'Venue'; } }
        echo '<div class="hwads-copy-destination">';HPADS_Admin::select('Venue','copy_venue',$venues,HPADS_Core::company($p));echo '<p><label><strong>Location</strong><br><select name="copy_location" required>';foreach ($locations as $l) { echo '<option data-venue="'.(int)$l['parent_id'].'" value="'.(int)$l['id'].'">'.esc_html($l['title']).'</option>'; }echo '</select></label></p></div>';
    }
    static function handle_copy() {
        $id=absint($_POST['source_id'] ?? 0);check_admin_referer('hwads_copy_playlist_'.$id);
        $lid=absint($_POST['copy_location'] ?? 0);$l=HPADS_Core::get($lid,'location');if (!$l || (int)$l['parent_id']!==absint($_POST['copy_venue'] ?? 0)) { HPADS_Admin::fail('Choose a location within the selected venue.'); }
        $result=self::copy_playlist($id,$lid,wp_unslash($_POST['copy_title'] ?? ''),absint($_POST['revision'] ?? 0));if (is_wp_error($result)) { HPADS_Admin::fail($result->get_error_message()); }wp_safe_redirect(HPADS_Admin::url('playlist',$result));exit;
    }
    static function template_copy($id,$scope,$venue,$location) {
        $ad=HPADS_Core::get($id,'creative');if (!$ad || !HPADS_Placements::is_template($ad) || !HPADS_Core::can($ad) || HPADS_View_As::state()) { return HPADS_Core::error('Template unavailable.',403); }
        [$type,$oid]=HPADS_Workflow::owner_ref($ad);if ($type==='company' && $scope!=='personal') { $oid=$venue; }
        if (!HPADS_Placements::author_owner($type,$oid)) { return HPADS_Core::error('Destination owner unavailable.',403); }
        $s=self::scope_input(['template_scope'=>$scope,'template_venue'=>$venue,'template_location'=>$location],[],$type,$oid);if (is_wp_error($s)) { return $s; }
        $d=array_merge(HPADS_Placements::fresh_data($ad,'template'),$s,['owner_type'=>$type,'owner_id'=>$oid]);
        $result=HPADS_Core::put('creative',$ad['title'].' — Copy',$type==='advertiser'?$oid:0,$d);if (!is_wp_error($result)) { HPADS_Divi::copy_meta($id,$result); }return $result;
    }
    static function handle_template() {
        $id=absint($_POST['source_id'] ?? 0);check_admin_referer('hwads_copy_template_'.$id);
        $result=self::template_copy($id,sanitize_key($_POST['template_scope'] ?? ''),absint($_POST['template_venue'] ?? 0),absint($_POST['template_location'] ?? 0));if (is_wp_error($result)) { HPADS_Admin::fail($result->get_error_message()); }wp_safe_redirect(HPADS_Admin::url('templates',$result));exit;
    }
    static function row_actions($actions,$post) {
        $r=HPADS_Post_Types::record($post->ID);if (!$r || !HPADS_Core::can($r)) { return $actions; }
        if ($r['kind']==='creative' && HPADS_Placements::is_template($r) && !HPADS_View_As::state()) { $args=['copy_template'=>$r['id']];foreach (['hwads_location','hwads_venue'] as $key) { if (!empty($_GET[$key])) { $args[$key]=absint($_GET[$key]); } }$l=self::context_location();$v=self::context_venue();$target=$v?HPADS_Venue_Editor_Dashboard::url($v['id'],'templates',$l['id'] ?? 0):HPADS_Ad_Library::url('templates');unset($args['hwads_venue']);$actions['copy_template']='<a href="'.esc_url(add_query_arg($args,$target)).'">Copy template to…</a>'; }
        if ($r['kind']==='device' && HPADS_Core::can($r,true) && !HPADS_View_As::state()) { foreach (['remote_shell_url'=>'Remote shell','screen_share_url'=>'Screen share'] as $key=>$label) { $url=$r['data'][$key] ?? '';if (wp_parse_url($url,PHP_URL_SCHEME)==='https') { $actions[$key]='<a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">'.esc_html($label).'</a>'; } } }
        if ($r['kind']==='company') { $actions['dashboard']='<a href="'.esc_url(self::venue_url($r['id'])).'">Open venue dashboard</a>'; }
        if ($r['kind']==='location') { $actions['workspace']='<a href="'.esc_url(self::url($r['id'])).'">Open venue dashboard</a>'; }
        if ($r['kind']==='playlist' && HPADS_Admin::can_add('playlist')) { $actions['copy_location']='<a href="'.esc_url(self::copy_url($r)).'">Copy to location</a>'; }
        return $actions;
    }
    static function template_copy_panel() {
        global $pagenow;if ($pagenow!=='edit.php' || !HPADS_Ad_Library::active() || empty($_GET['copy_template'])) { return; }
        $ad=HPADS_Core::get(absint($_GET['copy_template']),'creative');if (!$ad || !HPADS_Placements::is_template($ad) || !HPADS_Core::can($ad) || HPADS_View_As::state()) { return; }
        echo '<div class="wrap"><h2>Copy template: '.esc_html($ad['title']).'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_copy_template"><input type="hidden" name="source_id" value="'.(int)$ad['id'].'">';wp_nonce_field('hwads_copy_template_'.$ad['id']);self::scope_fields($ad['data']);submit_button('Copy template','secondary');echo '</form></div>';
    }
    static function navigation($post) {
        if (!HPADS_Admin::can_section('company')) { return; }
        $r=HPADS_Post_Types::record($post->ID);if (!$r || !HPADS_Core::can($r)) { return; }

        $lid=$r['kind']==='location'?$r['id']:(in_array($r['kind'],['playlist','device'],true)?$r['parent_id']:0);
        if ($lid) { $l=HPADS_Core::get($lid,'location');$v=$l?HPADS_Core::get($l['parent_id'],'company'):null;echo '<p>'.esc_html($v['title'] ?? 'Venue').' → <a href="'.esc_url(self::url($lid)).'">'.esc_html($l['title'] ?? 'Location').'</a> → '.esc_html($r['title']).'</p>'; }
    }
    static function render($id) {
        $p=HPADS_Core::get($id,'playlist');$l=$p?HPADS_Core::get($p['parent_id'],'location'):null;
        if (!$l || !HPADS_Core::can($l) || !$p || !HPADS_Core::can($p)) { wp_die('Playlist unavailable.','HWADS',['response'=>403]); }
        echo '<div class="wrap"><h1>Copy playlist</h1>';self::copy_form($p);echo '</div>';
    }
}
