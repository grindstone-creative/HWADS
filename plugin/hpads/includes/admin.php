<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Admin {
    const PAGES=['dashboard'=>'Dashboard','company'=>'Venues','location'=>'Locations','device'=>'Devices','advertiser'=>'Advertisers','creative'=>'Ads','templates'=>'My Ad Templates','shared'=>'Shared with me','playlist'=>'Playlists','users'=>'Users','settings'=>'Settings','updates'=>'Player Updates','approvals'=>'Approvals'];
    static function can_section($key) {
        if (!current_user_can('hpads_access')) { return false; }
        if ($key==='dashboard') { return true; }
        if ($key==='shared') { return HPADS_Content_Access::content_role(); }
        if (HPADS_Content_Access::designer() && in_array($key,['company','location','device','advertiser','users','settings','approvals','updates'],true)) { return false; }
        if ($key==='updates') { return HPADS_Player_Updates::owner(); }
        if ($key==='settings') { return HPADS_Workflow::global_admin(); }
        if ($key==='users') { return HPADS_Teams::can_manage() || (bool)HPADS_Teams::all(); }
        if ($key==='approvals') { return HPADS_Workflow::global_staff() || ((HPADS_Workflow::venue_member() || current_user_can('hpads_account_manager') || current_user_can('hpads_admin')) && (bool)HPADS_Core::assigned('companies')); }
        if ($key==='engagement') { return HPADS_Workflow::global_staff() || (bool)HPADS_Core::all('creative') || (bool)HPADS_Core::all('playlist') || self::can_add('creative'); }
        $kind=$key==='templates'?'creative':$key;
        return in_array($kind,HPADS_Core::KINDS,true) && (HPADS_Workflow::global_staff() || self::can_add($kind) || (bool)HPADS_Core::all($kind));
    }
    static function menu() {
        add_menu_page('HWADS','HWADS','hpads_access','hpads-dashboard',[__CLASS__,'page'],'dashicons-format-video',26);
        foreach (self::PAGES as $key=>$label) { if (!self::can_section($key)) { continue; }if ($key==='approvals') { $count=count(HPADS_Approvals::rows(true));if ($count) { $label.=' <span class="awaiting-mod"><span class="pending-count">'.(int)$count.'</span></span>'; } }add_submenu_page(($key==='updates' || $key==='templates' || ($key==='playlist' && self::can_section('company')))?null:'hpads-dashboard',$label,$label,$key==='updates'?'hpads_owner':'hpads_access','hpads-'.$key,[__CLASS__,'page']); }
    }
    static function assets($hook) {
        if (strpos($hook,'hpads-')===false && !isset(HPADS_Post_Types::TYPES[$_GET['post_type'] ?? ''])) { return; }
        if (sanitize_key($_GET['page'] ?? '')==='hpads-playlist') { wp_enqueue_media(); }
        if (isset($_GET['edit']) && in_array(sanitize_key($_GET['page'] ?? ''),['hpads-creative','hpads-templates'],true)) { wp_enqueue_style('edit'); wp_enqueue_editor(); }
        if (sanitize_key($_GET['page'] ?? '')==='hpads-device' && !empty($_GET['edit'])) { wp_enqueue_script('hpads-qr-library',plugins_url('assets/vendor/qrcodegen.js',HPADS_FILE),[],HPADS_VERSION,true); wp_enqueue_script('hpads-device-qr',plugins_url('assets/device-qr.js',HPADS_FILE),['hpads-qr-library'],HPADS_VERSION,true); }
        wp_enqueue_style('edit');
        wp_enqueue_style('hpads',plugins_url('assets/admin.css',HPADS_FILE),[],HPADS_VERSION);
        wp_enqueue_script('hpads',plugins_url('assets/admin.js',HPADS_FILE),['jquery','jquery-ui-sortable'],HPADS_VERSION,true);
        wp_localize_script('hpads','HPADSAdmin',['ajaxUrl'=>admin_url('admin-ajax.php'),'ownerNonce'=>wp_create_nonce('hpads_owner_playlists'),'createNonce'=>wp_create_nonce('hpads_playlist_create'),'transitions'=>HPADS_Placements::TRANSITIONS,'timezone'=>wp_timezone_string()]);
    }
    static function expected_address_input($in,$data) {
        if (!array_key_exists('expected_address',$in)) { return $data; }
        if (!is_string($in['expected_address'])) { return HPADS_Core::error('Enter a valid expected installation address.'); }
        $address=sanitize_textarea_field($in['expected_address']);
        if (strlen($address)>2000 || preg_match_all('/./us',$address)>500) { return HPADS_Core::error('Expected installation address must be 500 characters or fewer.'); }
        $data['expected_address']=$address;
        return $data;
    }
    static function expected_address_field($data,$record) {
        echo '<p><label for="hwads-expected-address"><strong>Expected installation address</strong></label><br><textarea id="hwads-expected-address" name="expected_address" rows="4" maxlength="500" class="large-text">'.esc_textarea($data['expected_address'] ?? '').'</textarea><br><span class="description">Where this HWADS Box should be: street, city, state/province, postal code and country. This is your reference address; approximate network location cannot confirm a street address.</span></p>';
        if (empty($data['expected_address']) && $record) {
            $location=HPADS_Core::get($record['parent_id'],'location');
            if ($location && HPADS_Core::can($location) && !empty($location['data']['address'])) { echo '<p>Assigned location address: '.nl2br(esc_html($location['data']['address'])).'</p>'; }
        }
    }
    static function url($kind,$id=null) { if ($id===null && in_array($kind,['creative','templates'],true)) { return HPADS_Ad_Library::url($kind==='templates'?'templates':'all'); } if ($id!==null && HPADS_Storage::active() && in_array($kind,['company','location','device','advertiser','creative','templates','playlist'],true) && (!$id || HPADS_Core::can(HPADS_Core::get($id),true))) { return HPADS_Native_Editor::url($kind==='templates'?'hpads_template':($kind==='creative'?'hpads_placement':HPADS_Storage::type($kind)),$id); } $args=['page'=>'hpads-'.$kind]; if ($id!==null) { $args['edit']=$id; } return add_query_arg($args,admin_url('admin.php')); }
    static function fail($message) { wp_die(esc_html($message),'HWADS',['response'=>400,'back_link'=>true]); }
    static function field($label,$name,$value='',$type='text',$hint='') {
        echo '<p><label><strong>'.esc_html($label).'</strong><br><input class="regular-text" type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'"'.($type==='number'?' min="0"':'').'></label>';
        if ($hint) { echo '<br><span class="description">'.esc_html($hint).'</span>'; } echo '</p>';
    }
    static function check($label,$name,$value) { echo '<p><label><input type="checkbox" name="'.esc_attr($name).'" value="1" '.checked((bool)$value,true,false).'> '.esc_html($label).'</label></p>'; }
    static function select($label,$name,$choices,$value=0) {
        echo '<p><label><strong>'.esc_html($label).'</strong><br><select name="'.esc_attr($name).'" aria-label="'.esc_attr($label).'">';
        foreach ($choices as $id=>$text) { echo '<option value="'.esc_attr($id).'" '.selected((string)$value,(string)$id,false).'>'.esc_html($text).'</option>'; } echo '</select></label></p>';
    }
    static function multi($label,$name,$choices,$selected=[]) {
        echo '<fieldset class="hpads-checkboxes"><legend><strong>'.esc_html($label).'</strong></legend>';
        foreach ($choices as $id=>$text) { if (!(int)$id) { continue; } echo '<label><input type="checkbox" name="'.esc_attr($name).'[]" value="'.(int)$id.'" '.checked(in_array((int)$id,$selected,true),true,false).'> '.esc_html($text).'</label><br>'; }
        if (!array_filter(array_keys($choices))) { echo '<p>No eligible options. An account manager must approve the advertiser for specific locations first.</p>'; }
        echo '</fieldset>';
    }
    static function choices($kind,$filter=null) {
        $out=[0=>'— None —']; foreach (HPADS_Core::all($kind) as $r) { if (!$filter || $filter($r)) { $out[$r['id']]=$r['title'].' (#'.$r['id'].')'; } } return $out;
    }
    static function begin($kind,$r=null,$action='record',$class='hpads-form') {
        echo '<form method="post" action="'.esc_url(add_query_arg('action','hpads_save',admin_url('admin-post.php'))).'" enctype="multipart/form-data" class="'.esc_attr($class).'">';
        wp_nonce_field('hpads_save');
        foreach (['action'=>'hpads_save','operation'=>$action,'kind'=>$kind,'id'=>$r['id'] ?? 0,'revision'=>$r['revision'] ?? 0] as $k=>$v) { echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">'; }
    }
    static function remote_access_fields($d) {
        echo '<h3>Remote access</h3><p class="description">Optional HWADS Box remote access links. Leave blank until you have the URLs. Each saved link opens in a new tab using your Connect login.</p>';
        foreach (['remote_shell_url'=>['Remote shell URL','Open remote shell'],'screen_share_url'=>['Screen share URL','Open screen share']] as $key=>$labels) {
            self::field($labels[0],$key,$d[$key] ?? '','url');
            if (!empty($d[$key])) { echo '<p><a class="button" href="'.esc_url($d[$key],['https']).'" target="_blank" rel="noopener noreferrer">'.esc_html($labels[1]).'</a></p>'; }
        }
    }
    static function remote_access_input($in,$d) {
        foreach (['remote_shell_url','screen_share_url'] as $key) {
            if (!array_key_exists($key,$in)) { continue; }
            if (!is_string($in[$key]) || strlen($in[$key])>2048) { return HPADS_Core::error('Enter a remote-access URL of 2,048 characters or fewer.'); }
            $value=trim($in[$key]);if ($value==='') { $d[$key]='';continue; }
            $parts=wp_parse_url($value);$url=esc_url_raw($value,['https']);
            if (!$url || !$parts || strtolower($parts['scheme'] ?? '')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) { return HPADS_Core::error('Remote shell and screen share links must be complete HTTPS URLs without embedded login credentials.'); }
            $d[$key]=$url;
        }
        return $d;
    }
    static function layout_open($heading) {
        echo '<div id="poststuff" class="hpads-editor-layout"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content"><div class="postbox"><div class="postbox-header"><h2 class="hndle">'.esc_html($heading).'</h2></div><div class="inside">';
    }
    static function layout_close($button) {
        echo '</div></div></div><div id="postbox-container-1" class="postbox-container"><div class="postbox"><div class="postbox-header"><h2 class="hndle">Save</h2></div><div class="inside">';submit_button($button,'primary','submit',false);echo '</div></div></div></div><div class="clear"></div></div>';
    }
    static function page() {
        if (!current_user_can('hpads_access')) { self::fail('Access denied.'); }
        $kind=substr(sanitize_key($_GET['page'] ?? ''),6);
        if ($kind==='playlist' && isset($_GET['copy_playlist'])) { HPADS_Venue_Workspace::render(absint($_GET['copy_playlist']));return; }
        if ($kind==='playlist' && isset($_GET['revision'])) { self::revision_page(absint($_GET['revision']));return; }
        if (!isset(self::PAGES[$kind])) { return; }
        $is_list=in_array($kind,HPADS_Core::KINDS,true) && !isset($_GET['edit']);
        echo '<div class="wrap hpads'.($is_list?' hpads-list-page':'').'"><h1 class="wp-heading-inline">'.esc_html($kind==='dashboard'?'Main HWADS Dashboard':'HWADS · '.self::PAGES[$kind]).'</h1>';
        if ($is_list && $kind==='device' && HPADS_Player_Updates::owner()) { echo ' <a class="page-title-action" href="'.esc_url(self::url('updates')).'">Player Updates</a>'; }
        if ($is_list && $kind!=='creative' && self::can_add($kind)) {
            $singular=['company'=>'Venue','location'=>'Location','device'=>'Device','advertiser'=>'Advertiser','creative'=>'Media / Ad','playlist'=>'Playlist'];
            echo ' <a class="page-title-action" href="'.esc_url(self::url($kind,0)).'">Add New '.esc_html($singular[$kind]).'</a>';
        }
        echo '<hr class="wp-header-end"><p class="description">Central control · Venue Company → Location → Device</p>';
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success"><p>Saved. Ads require review and playlist publication before reaching players. Device assignments take effect on their next poll.</p></div>'; }
        $secret=get_transient('hpads_key_'.get_current_user_id());
        if ($secret) {
            delete_transient('hpads_key_'.get_current_user_id());
            echo '<div class="notice notice-warning"><p><strong>Save this device key now. It is shown once; rotating revokes the previous key.</strong></p><p><code>'.esc_html($secret).'</code></p></div>';
        }
        if ($kind==='dashboard') { self::dashboard(); }
        elseif ($kind==='creative' || $kind==='templates') { HPADS_Placements::page($kind==='templates'); }
        elseif ($kind==='shared') { HPADS_Content_Access::page(); }
        elseif ($kind==='users') { HPADS_Teams::page(); }
        elseif ($kind==='settings') { self::settings(); }
        elseif ($kind==='approvals') { HPADS_Approvals::page(); }
        elseif ($kind==='updates') { HPADS_Player_Updates::page(); }
        else {
            if (isset($_GET['edit'])) { self::editor($kind,absint($_GET['edit']));if (in_array($kind,['location','device'],true)) { $record=HPADS_Core::get(absint($_GET['edit']),$kind);if ($record && HPADS_Core::can($record)) { HPADS_Update_Policy::render($record); } } }
            else { self::listing($kind); }
        }
        echo '</div>';
    }
    static function dashboard() { HPADS_Dashboards::main(); }
    static function can_add($kind) {
        if ($kind==='creative') { return (bool)HPADS_Ads::owners(true); }
        if (HPADS_Workflow::global_admin()) { return true; }
        if (current_user_can('hpads_producer')) { return $kind==='playlist'; }
        if (current_user_can('hpads_sales')) { return in_array($kind,['company','advertiser'],true); }
        if (current_user_can('hpads_admin') || current_user_can('hpads_account_manager')) { return in_array($kind,['location','device','playlist'],true); }
        if (HPADS_Workflow::venue_admin() && $kind==='playlist') { foreach (HPADS_Core::all('location') as $l) { if (!empty($l['data']['allow_house'])) { return true; } } }
        return false;
    }
    static function listing($kind,$add=true) {
        require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
        require_once __DIR__.'/list-table.php';

        $table=new HPADS_List_Table($kind); $table->prepare_items();
        $page=sanitize_key($_GET['page'] ?? 'hpads-'.$kind);
        $table->views();
        echo '<form method="get" action="'.esc_url(admin_url('admin.php')).'"><input type="hidden" name="page" value="'.esc_attr($page).'">';
        foreach (['orderby','order','time_view'] as $key) {
            if (isset($_GET[$key])) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr(sanitize_key($_GET[$key])).'">'; }
        }
        $table->search_box('Search '.self::PAGES[$kind],'hpads-'.$kind);
        $table->display();
        echo '</form>';
    }
    static function rotation_choices($rotation_id,$location_id,$purpose='primary') {
        $out=[];
        if (!$rotation_id || !$location_id) { return $out; }
        foreach (HPADS_Core::all('creative') as $entry) {
            $e=$entry['data'];
            if (($e['record_type'] ?? '')!=='placement' || (int)($e['rotation_id'] ?? 0)!==$rotation_id || !in_array($location_id,$e['locations'] ?? [],true)) { continue; }
            [$owner_type,$owner_id]=HPADS_Workflow::owner_ref($entry);
            if ($purpose==='house' && $owner_type!=='company') { continue; }
            $state=HPADS_Workflow::approved($entry,$location_id)?'Approved':HPADS_Workflow::summary($entry);
            $out[$entry['id']]=$entry['title'].' — '.$state.' (#'.$entry['id'].')';
        }
        return $out;
    }
    static function revision_link($id) {
        $label='Playlist revision #'.(int)$id;$m=HPADS_Core::version($id);
        $playlist=$m?HPADS_Core::get($m['playlist_id'],'playlist'):null;
        if (!$playlist || !HPADS_Core::can($playlist)) { return esc_html($label); }
        return '<a href="'.esc_url(add_query_arg(['page'=>'hpads-playlist','revision'=>(int)$id],admin_url('admin.php'))).'">'.esc_html($label).'</a>';
    }
    static function revision_page($id) {
        global $wpdb;$m=HPADS_Core::version($id);$playlist=$m?HPADS_Core::get($m['playlist_id'],'playlist'):null;
        if (!$playlist || !HPADS_Core::can($playlist)) { wp_die('Revision unavailable.','HWADS',['response'=>403]); }
        $v=$wpdb->get_row($wpdb->prepare('SELECT created_at,created_by FROM '.HPADS_Core::table(true).' WHERE id=%d',$id),ARRAY_A);
        echo '<div class="wrap"><h1>Playlist revision #'.(int)$id.'</h1><h2>'.esc_html($playlist['title']).'</h2><p>Published '.esc_html(HPADS_Core::display_datetime($v['created_at'],true)).' · user #'.(int)$v['created_by'].'</p><p>Read-only published snapshot. Later edits do not change this revision. Placement links open the current editor.</p><table class="widefat striped"><thead><tr><th>Order</th><th>Placement</th><th>Duration</th><th>Start</th><th>End</th></tr></thead><tbody>';
        foreach ($m['items'] as $i=>$item) {
            $label=esc_html($item['title']).' #'.(int)$item['placement_id'];
            $placement=HPADS_Core::get((int)$item['placement_id'],'creative');
            if ($placement && !HPADS_Placements::is_template($placement) && HPADS_Core::can($placement,true)) {
                $label='<a href="'.esc_url(self::url('creative',$placement['id'])).'" title="Open current placement editor">'.$label.'</a>';
            }
            echo '<tr><td>'.($i+1).'</td><td>'.$label.'</td><td>'.esc_html(isset($item['duration_seconds'])?$item['duration_seconds'].' seconds':'Full video').'</td><td>'.esc_html(!empty($item['start_utc'])?HPADS_Core::display_datetime(strtotime($item['start_utc'])):'—').'</td><td>'.esc_html(!empty($item['end_utc'])?HPADS_Core::display_datetime(strtotime($item['end_utc'])):'—').'</td></tr>'; }
        echo '</tbody></table></div>';
    }
    static function status($id,$html=false) {
        global $wpdb;
        $s=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'hpads_status WHERE device_id=%d',$id),ARRAY_A);
        if (!$s) { return 'Never seen'; }
        $p=json_decode($s['payload'],true) ?: [];
        $text=((time()-(int)$s['last_seen'])>HPADS_Core::interval()*3+60?'Offline / stale':'Online').' · '.HPADS_Core::display_datetime((int)$s['last_seen'],true).' · '.($p['state'] ?? 'idle').' · '.('Playlist revision #'.(int)($p['active_version'] ?? 0)).(!empty($p['error'])?' · '.$p['error']:'');
        return $html?str_replace(esc_html('Playlist revision #'.(int)($p['active_version'] ?? 0)),self::revision_link((int)($p['active_version'] ?? 0)),esc_html($text)):$text;
    }
    static function editor($kind,$id) {
        $r=$id?HPADS_Core::get($id,$kind):null;
        if ($id && (!$r || !HPADS_Core::can($r))) { self::fail('Record unavailable.'); }
        if (!$id && !self::can_add($kind)) { self::fail('Access denied.'); }
        $d=$r['data'] ?? [];
        $write=$r?HPADS_Core::can($r,true):true;
        if ($kind==='device' && !$write) { echo '<p>View-only device status. Settings and the settings QR require device-management access.</p><p>'.esc_html(self::status($r['id'])).'</p>'; return; }
        if (!$write) { echo '<p>View only. Changes require an account manager or owner.</p>'; }
        echo '<p><a href="'.esc_url(self::url($kind)).'">← All '.esc_html(self::PAGES[$kind]).'</a></p>';
        self::begin($kind,$r);
        echo '<fieldset '.($kind==='company' && !$write?'id="hwads-details" ':'').(!$write?'disabled':'').'>';
        self::field('Name','title',$r['title'] ?? '');
        if ($kind==='company' || $kind==='advertiser') {
            self::field('Contact email','email',$d['email'] ?? '','email');
            if ($kind==='company') { HPADS_Approvals::settings($r); }
            if ($kind==='advertiser') {
                if (HPADS_Workflow::relations()) { self::multi('Connected venues','venues',self::choices('company'),$d['venues'] ?? []); }
                if (HPADS_Workflow::global_admin() || current_user_can('hpads_admin') || current_user_can('hpads_account_manager')) { self::multi('Locations approved for this advertiser','approved_placements',self::choices('location'),$d['approved_placements'] ?? []); }
                echo '<p>Connected venues determine where templates and placements can be used. Content approvals follow each venue’s reviewer settings.</p>';
            }
        } elseif ($kind==='location') {
            self::select('Venue','parent_id',self::choices('company'),$r['parent_id'] ?? absint($_GET['venue_id'] ?? 0));
            echo '<p><label for="hwads-location-address"><strong>Location address</strong></label><br><textarea id="hwads-location-address" name="address" rows="4" maxlength="500" class="large-text">'.esc_textarea($d['address'] ?? '').'</textarea><br><span class="description">Street, city, state/province, postal code and country.</span></p>';
            self::field('Default image duration (seconds)','duration',$d['duration'] ?? 10,'number','1–3600 seconds. Used when publishing items with no override.');
            echo '<p>Outside-ad approval rules are configured on the venue. Selected staff review the saved revision.</p>';
            self::check('Require approval for any unmigrated legacy media','approval_required',$d['approval_required'] ?? true);
            self::check('Allow venue managers to upload house media and manage/publish house playlists','allow_house',$d['allow_house'] ?? true);
            self::check('Include house playlist in normal rotation (otherwise fallback only)','house_rotation',$d['house_rotation'] ?? false);
            self::playlist_selects($r['id'] ?? 0,$d,false);HPADS_Playback_Mix::render('location',$r,$d,$r['id'] ?? 0);
            echo '<p>Assignments and location settings take effect on the next device poll. Duration and approval changes require republishing affected playlists. Enabling approval does not recall already published versions.</p>';
        } elseif ($kind==='device') {
            self::expected_address_field($d,$r);
            self::select('Location','parent_id',self::choices('location'),$r['parent_id'] ?? absint($_GET['location_id'] ?? 0));
            self::select('Display orientation','orientation',['horizontal'=>'Horizontal / landscape','vertical'=>'Vertical / portrait'],$d['orientation'] ?? 'horizontal');
            self::select('Device type','type',HPADS_Core::TYPES,$d['type'] ?? 'mixed');
            self::field('Custom type label','custom_type',$d['custom_type'] ?? '');
            self::remote_access_fields($d);
            self::check('Allow device API access','enabled',$d['enabled'] ?? true);echo '<p class="description">Turning this off revokes syncing, heartbeats and updates. Cached playback continues. Stop playback on the HWADS Box with its playback control.</p>';
            self::playlist_selects($r['parent_id'] ?? 0,$d,true);HPADS_Playback_Mix::render('device',$r,$d,$r['parent_id'] ?? 0);
            if ($r) {
                echo '<p>Status: '.esc_html(self::status($r['id'])).'</p><p>Playlist API: <code>'.esc_html(rest_url('hpads/v1/devices/'.$r['id'].'/playlist')).'</code></p>';
                HPADS_Device_Health::render($r);
                HPADS_Device_QR::render($r);
                self::check('Rotate device key (old key stops working immediately)','rotate_key',false);
            }
            echo '<p>Device types are labels in this release; assign the intended playlists explicitly. Save a new device before choosing its playlist overrides. No key is sent in a URL.</p>';
        } elseif ($kind==='creative') {
            HPADS_Ads::fields($r);
        } elseif ($kind==='playlist') {
            self::select('Location','parent_id',self::choices('location'),$r['parent_id'] ?? absint($_GET['location_id'] ?? 0));
            self::select('Purpose','purpose',['primary'=>'Primary','house'=>'House / placeholder'],$d['purpose'] ?? (HPADS_Workflow::venue_admin()&&!HPADS_Core::staff()?'house':'primary'));
            self::select('Content type','category',HPADS_Playlist_Limits::TYPES,HPADS_Playlist_Limits::category(['data'=>$d]));
            self::field('Maximum active placements','active_limit',HPADS_Playlist_Limits::cap(['data'=>$d]),'number','1–100. Counts Active switches. Total capacity: 100 placements.');
            if ($r) { echo '<p><a class="button" target="_blank" rel="noopener" href="'.esc_url(add_query_arg(['action'=>'hpads_playlist_preview','id'=>$r['id']],admin_url('admin-post.php'))).'">Preview Playlist</a></p>';echo '<p><a class="button" href="'.esc_url(add_query_arg('rotation_id',$r['id'],self::url('creative',0))).'">Add New Placement for This Playlist</a></p>'; }
            echo '<p>Placement schedules and duration come from each entry. Use the arrows or drag to order eligible placements. Dates and times use the WordPress site timezone. Start is inclusive; end is exclusive. Empty duration uses the location default; videos always play full-length. Save a new playlist with its location before adding media.</p>';
            $location_id=$r['parent_id'] ?? 0;
            $choices=self::rotation_choices((int)($r['id'] ?? 0),(int)$location_id,$d['purpose'] ?? 'primary');
            $bound=[]; foreach (HPADS_Core::all('creative') as $entry) { if (($entry['data']['record_type'] ?? '')==='placement' && (int)$entry['data']['rotation_id']===(int)($r['id'] ?? 0)) { $bound[$entry['id']]=HPADS_Placements::card($entry['id']); } }
            if ($r && $write) {
                $owners=[]; foreach (HPADS_Ads::owners(true) as $key=>$label) { [$ot,$oi]=explode(':',$key); if (HPADS_Placements::can_bind($ot,(int)$oi,$r)) { $owners[$key]=$label; } }
                echo '<div class="hpads-playlist-import hpads-upload-panel" id="hpads-placement-picker"><h3>Add Placement</h3>'; self::select('Owner for new uploads','upload_owner',$owners,isset($owners['company:'.HPADS_Core::company(HPADS_Core::get($location_id,'location'))])?'company:'.HPADS_Core::company(HPADS_Core::get($location_id,'location')):array_key_first($owners));
                echo '<div class="hpads-upload-drop" id="hpads-dropzone" tabindex="0" role="button" aria-label="Upload media to create placements">Drop images or videos here, or click to choose files</div><input id="hpads-bulk-media" type="file" multiple hidden accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" data-max-bytes="'.esc_attr(wp_max_upload_size()).'"><p id="hpads-import-status" role="status"></p><label>Ad template <select id="hpads-playlist-template"><option value="">Choose an ad template</option>';
                $picker_location=HPADS_Core::get($location_id,'location');
                foreach (['This location','Venue templates','My templates','Shared with me'] as $group) { echo '<optgroup label="'.esc_attr($group).'">';foreach (HPADS_Core::all('creative') as $t) { [$ot,$oi]=HPADS_Workflow::owner_ref($t);if ($picker_location && HPADS_Venue_Workspace::group($t,$picker_location)===$group && (HPADS_Placements::can_bind($ot,$oi,$r) || (HPADS_Content_Access::grant($t) && (bool)$owners))) { echo '<option data-preview="'.esc_url(wp_get_attachment_image_url($t['data']['attachment_id'] ?? 0,'medium') ?: '').'" value="'.(int)$t['id'].'">'.esc_html($t['title']).'</option>'; } }echo '</optgroup>'; }
                echo '</select></label> <button type="button" class="button" id="hpads-copy-template">Add Selected Template</button> <button type="button" class="button" id="hpads-library-media">New Placement from Media Library</button><p class="description">Choose a template or media to create an independent placement. Changes to it do not change the template. Save or Update the playlist to keep your changes; Publish or Update makes them live.</p></div>';
            }
            if ($r && $write && $choices) {
                echo '<p><label>Restore a saved placement <select id="hpads-existing-placement"><option value="">Choose a placement removed from this playlist</option>';
                foreach ($choices as $id=>$title) { echo '<option value="'.(int)$id.'">'.esc_html($title).'</option>'; }
                echo '</select></label> <button type="button" class="button" id="hpads-restore-placement">Add Saved Placement</button></p>';
            }
            echo '<div id="hpads-playlist" data-max-items="'.HPADS_Playlist_Limits::TOTAL.'" data-active-limit="'.HPADS_Playlist_Limits::cap(['data'=>$d]).'" data-category="'.esc_attr(HPADS_Playlist_Limits::category(['data'=>$d])).'" data-caps="'.esc_attr(wp_json_encode(HPADS_Playlist_Limits::ACTIVE)).'" data-default-duration="'.(int)(HPADS_Core::get($location_id,'location')['data']['duration'] ?? 10).'" data-bound="'.esc_attr(wp_json_encode($bound)).'" data-items="'.esc_attr(wp_json_encode($d['items'] ?? [])).'" data-media="'.esc_attr(wp_json_encode($choices)).'"></div><input type="hidden" id="hpads-items" name="items" value="'.esc_attr(wp_json_encode($d['items'] ?? [])).'"><button type="button" class="button" id="hpads-add-item">Add placement</button>';
            if ($r) { echo '<p class="description">Draft placements can be arranged here. Approvals follow Settings; automatic approval is the default. Templates must first be copied into a placement for this playlist.</p>'; }
            if (!empty($d['published_version'])) { echo '<p>Current published version: #'.(int)$d['published_version'].'. Saving this form only updates the draft.</p>'; }
        }
        if ($write) { submit_button($kind==='playlist'?'Save Draft':'Save'); }
        echo '</fieldset></form>';
        if ($kind==='playlist' && $r && $write) {
            self::begin($kind,$r,'publish');
            echo '<p>Publish the <strong>last saved draft</strong>. Unsaved editor changes are not included. Publication freezes item order, media files, schedules and durations.</p>';
            submit_button('Publish to Players','primary','submit',false); echo '</form>';
            global $wpdb;
            $versions=$wpdb->get_results($wpdb->prepare('SELECT id,created_at,created_by FROM '.HPADS_Core::table(true).' WHERE playlist_id=%d ORDER BY id DESC LIMIT 20',$r['id']),ARRAY_A);
            echo '<h2>Recent immutable versions</h2><ul>';
            foreach ($versions as $v) { echo '<li>'.self::revision_link($v['id']).' · '.esc_html(HPADS_Core::display_datetime($v['created_at'],true)).' · user #'.(int)$v['created_by'].'</li>'; } echo '</ul>';
        }
        if ($kind==='creative' && $r) { HPADS_Ads::controls($r); }
    }
    static function playlist_selects($location_id,$d,$override) {
        foreach (['primary','house'] as $purpose) {
            $c=self::choices('playlist',function($r) use($location_id,$purpose) { return (int)$r['parent_id']===(int)$location_id && ($r['data']['purpose'] ?? '')===$purpose; });
            $c[0]=$override?'— Inherit location —':'— None —';
            self::select(ucfirst($purpose).' playlist'.($override?' override':''),$purpose.'_playlist',$c,$d[$purpose.'_playlist'] ?? 0);
        }
    }
    static function settings() {
        echo '<p>Manage teams in Users, advertiser location eligibility in Advertisers, and ad submissions in Ads.</p>';
        if (!HPADS_Workflow::global_admin()) { echo '<p>Global HWADS Admin access is required to change settings.</p>'; return; }
        self::begin('settings',null,'settings');
        self::layout_open('Settings');
        self::field('Heartbeat / polling interval (seconds)','heartbeat',HPADS_Core::interval(),'number','60–3600 seconds. Default 300.');
        HPADS_Landing_Layout::setting();
        HPADS_Public_Device::setting();
        echo '<h3>Ad approvals</h3><p>Configure outside-ad approval rules and selected reviewers in <strong>Venues → Edit Venue → Ad approvals</strong>. Only SuperAdmin-authored placement revisions bypass required review automatically.</p>';
        self::select('Legacy location eligibility','approval_mode',['automatic'=>'Connected venue locations','manual'=>'Explicit advertiser location assignments'],get_option('hpads_settings',[])['approval_mode'] ?? 'automatic');
        echo '<p>This compatibility setting controls advertiser location eligibility and legacy ads. Placement content approvals always follow the venue policy. Saving settings does not publish a playlist.</p>';
        self::layout_close('Save Settings');echo '</form>';
        echo '<h2>Upload environment</h2><p>PHP upload_max_filesize: <code>'.esc_html(ini_get('upload_max_filesize')).'</code> · post_max_size: <code>'.esc_html(ini_get('post_max_size')).'</code> · WordPress effective maximum: <strong>'.esc_html(size_format(wp_max_upload_size())).'</strong></p><p>These limits are configured in hosting PHP settings, not by HWADS.</p>';
    }
    static function redirect($kind,$id=null) { wp_safe_redirect(add_query_arg('saved',1,self::url($kind,$id))); exit; }
    static function save() {
        if (!current_user_can('hpads_access')) { self::fail('Access denied.'); }
        // PHP discards all POST fields when post_max_size is exceeded. The action
        // in the form URL lets us report that failure before checking the lost nonce.
        $post_limit=wp_convert_hr_to_bytes(ini_get('post_max_size'));
        if (empty($_POST) && $post_limit>0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0)>$post_limit) {
            self::fail('The upload request exceeds the server post_max_size limit ('.size_format($post_limit).'). Ask your host to raise post_max_size and upload_max_filesize, or use a smaller file.');
        }
        check_admin_referer('hpads_save');
        $in=wp_unslash($_POST); $op=sanitize_key($in['operation'] ?? '');
        $kind=sanitize_key($in['kind'] ?? ''); $id=absint($in['id'] ?? 0);
        if ($op==='placement_record') {
            $result=HPADS_Placements::save($in); if (is_wp_error($result)) { self::fail($result->get_error_message()); }
            $entry=HPADS_Core::get($result,'creative'); self::redirect(HPADS_Placements::is_template($entry)?'templates':'creative',$result);
        }
        if ($op==='copy_placement') {
            $source=HPADS_Core::get($id,'creative');
            if (!$source || !(HPADS_Workflow::can_ad($source,true) || (HPADS_Placements::is_template($source) && HPADS_Workflow::can_ad($source))) || (int)($in['revision'] ?? 0)!==(int)$source['revision']) { self::fail('Entry is unavailable or changed. Reload before copying.'); }
            $mode=sanitize_key($in['copy_mode'] ?? ''); $result=HPADS_Placements::copy($source,$mode,absint($in['destination_rotation'] ?? 0));
            if (is_wp_error($result)) { self::fail($result->get_error_message()); } self::redirect($mode==='template'?'templates':'creative',$result);
        }
        if ($op==='team_user') { $result=HPADS_Teams::save($in); if (is_wp_error($result)) { self::fail($result->get_error_message()); } self::redirect('users'); }
        if (in_array($op,['settings','assign'],true)) { self::save_settings($op,$in); }
        if (!in_array($kind,HPADS_Core::KINDS,true)) { self::fail('Invalid record type.'); }
        $r=$id?HPADS_Core::get($id,$kind):null;
        if ($id && (!$r || !HPADS_Core::can($r,!in_array($op,['approve','manager_review','venue_review'],true)))) { self::fail('Access denied.'); }
        if ($r && (int)($in['revision'] ?? 0)!==(int)$r['revision']) { self::fail('Record changed. Reload before saving.'); }
        if ($op==='record' && $kind==='playlist' && $r) { $result=HPADS_Placements::save_playlist($r,$in);if (is_wp_error($result)) { self::fail($result->get_error_message()); }self::redirect('playlist',$result); }
        if ($op==='publish') {
            if ($kind!=='playlist' || !$r) { self::fail('Invalid publication.'); }
            $result=HPADS_Core::publish($id); if (is_wp_error($result)) { self::fail($result->get_error_message()); } self::redirect($kind,$id);
        }
        if (in_array($op,['submit_ad','manager_review','venue_review'],true)) {
            if (!$r || $kind!=='creative') { self::fail('Invalid ad action.'); }
            $result=$op==='submit_ad'?HPADS_Workflow::submit($r):($op==='manager_review'?HPADS_Workflow::manager_review($r,sanitize_key($in['decision'] ?? ''),$in['review_note'] ?? ''):HPADS_Workflow::review($r,absint($in['location_id'] ?? 0),sanitize_key($in['decision'] ?? ''),$in['review_note'] ?? ''));
            if (is_wp_error($result)) { self::fail($result->get_error_message()); } if (HPADS_Approvals::placement($r) && !HPADS_Core::can($r,true)) { wp_safe_redirect(add_query_arg('review',$id,self::url('approvals')));exit; }self::redirect('creative',$id);
        }
        if ($op==='superadmin_approve') { $result=HPADS_Approvals::override($r);if (is_wp_error($result)) { self::fail($result->get_error_message()); }self::redirect('creative',$id); }
        if ($op==='approve') { self::fail('Use the two-stage review controls on the ad.'); }
        if ($op!=='record') { self::fail('Unknown operation.'); }
        $title=sanitize_text_field($in['title'] ?? ''); if (!$title || strlen($title)>200) { self::fail('Name is required (maximum 200 bytes).'); }
        $parent=absint($in['parent_id'] ?? 0);
        if ($kind==='creative') { self::fail('Use the placement or template editor.'); }
        $d=$r['data'] ?? [];
        if ($r && $parent!==(int)$r['parent_id']) { self::fail('Parent cannot be changed after creation. Create a new record instead.'); }
        if (in_array($kind,['company','advertiser'],true)) {
            if (!$id && !HPADS_Workflow::relations()) { self::fail('Only global admins and Sales can create venues/advertisers.'); }
            $d['email']=sanitize_email($in['email'] ?? ''); $parent=0;
            if ($kind==='company') { $d=HPADS_Approvals::save_policy($in,$r,$d);if (is_wp_error($d)) { self::fail($d->get_error_message()); } }
            if ($kind==='advertiser') {
                if (HPADS_Workflow::relations()) {
                    $venues=HPADS_Core::ids($in['venues'] ?? []); foreach ($venues as $cid) { if (!HPADS_Core::can(HPADS_Core::get($cid,'company'))) { self::fail('Venue is outside your scope.'); } } $d['venues']=$venues;
                }
                if (HPADS_Workflow::global_admin() || current_user_can('hpads_admin') || current_user_can('hpads_account_manager')) {
                    $placements=HPADS_Core::ids($in['approved_placements'] ?? []);
                    foreach ($placements as $lid) { $l=HPADS_Core::get($lid,'location'); if (!$l || !HPADS_Core::can($l) || !in_array((int)$l['parent_id'],$d['venues'] ?? [],true)) { self::fail('Approved locations must be accessible and belong to a connected venue.'); } }
                    $d['approved_placements']=$placements;
                }
            }
        }
        if (in_array($kind,['location','device','playlist'],true)) {
            $pk=$kind==='location'?'company':'location'; $p=HPADS_Core::get($parent,$pk);
            if (!$p || !HPADS_Core::can($p)) { self::fail('Choose an accessible parent.'); }
        }
        if ($kind==='location') {
            if (!HPADS_Workflow::global_admin() && !current_user_can('hpads_admin') && !current_user_can('hpads_account_manager')) { self::fail('Only admins/account managers can manage locations.'); }
            $duration=(int)($in['duration'] ?? 10); if ($duration<1 || $duration>3600) { self::fail('Duration must be 1–3600 seconds.'); }
            $d=array_merge($d,['duration'=>$duration,'approval_required'=>!empty($in['approval_required']),'allow_house'=>!empty($in['allow_house']),'house_rotation'=>!empty($in['house_rotation'])]);
            if (array_key_exists('address',$in)) { $address=sanitize_textarea_field($in['address']);if (strlen($address)>2000 || mb_strlen($address)>500) { self::fail('Location address must be 500 characters or fewer.'); }$d['address']=$address; }
            $d=self::assign_playlists($d,$in,$id);
        }
        if ($kind==='device') {
            if (!HPADS_Workflow::global_admin() && !current_user_can('hpads_admin') && !current_user_can('hpads_account_manager')) { self::fail('Only admins/account managers can manage devices.'); }
            $d=self::expected_address_input($in,$d);if (is_wp_error($d)) { self::fail($d->get_error_message()); }
            $type=sanitize_key($in['type'] ?? 'mixed'); if (!isset(HPADS_Core::TYPES[$type])) { self::fail('Invalid device type.'); }
            $orientation=sanitize_key($in['orientation'] ?? 'horizontal');
            if (!in_array($orientation,['horizontal','vertical'],true)) { self::fail('Choose Horizontal or Vertical.'); }
            $d['orientation']=$orientation;
            $d['type']=$type; $d['custom_type']=substr(sanitize_text_field($in['custom_type'] ?? ''),0,80); $d['enabled']=!empty($in['enabled']);
            $d=self::remote_access_input($in,$d);if (is_wp_error($d)) { self::fail($d->get_error_message()); }
            $d=self::assign_playlists($d,$in,$parent);
        }
        if ($kind==='playlist') {
            $purpose=sanitize_key($in['purpose'] ?? 'primary');
            if (!in_array($purpose,['primary','house'],true) || ($r && $purpose!==$d['purpose'])) { self::fail('Playlist purpose cannot change.'); }
            $candidate=['id'=>$id,'kind'=>'playlist','parent_id'=>$parent,'data'=>['purpose'=>$purpose]];
            if (!HPADS_Core::can($candidate,true)) { self::fail('House content editing is disabled or this location is outside your scope.'); }
            $items=json_decode($in['items'] ?? '[]',true);
            $items=HPADS_Core::normalize_items($items,HPADS_Core::get($parent,'location'),$id);
            if (is_wp_error($items)) { self::fail($items->get_error_message()); }
            if ($purpose==='house') { foreach ($items as $item) { $c=HPADS_Core::get($item['creative_id'],'creative'); if (!empty($c['parent_id'])) { self::fail('House playlists accept house media only.'); } } }
            $d['purpose']=$purpose; $d['items']=$items;$settings=HPADS_Playlist_Limits::settings($in,$d);if (is_wp_error($settings)) { self::fail($settings->get_error_message()); }$d=array_merge($d,$settings);
            $candidate['data']=$d;$limits=HPADS_Playlist_Limits::validate($candidate);if (is_wp_error($limits)) { self::fail($limits->get_error_message()); }
        }
        $key='';
        if ($kind==='device' && (!$r || !empty($in['rotate_key']))) {
            $key=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'='); $d['key_hash']=hash('sha256',$key);
        }
        if (in_array($kind,['location','device'],true)) { $d=HPADS_Playback_Mix::save($kind,$in,$r,$d);if (is_wp_error($d)) { self::fail($d->get_error_message()); } }
        $result=HPADS_Core::put($kind,$title,$parent,$d,$id,(int)($r['revision'] ?? 0));
        if (is_wp_error($result)) { self::fail($result->get_error_message()); }
        if ($kind==='creative') { update_post_meta($d['attachment_id'],'_hpads_creative_id',$result); update_post_meta($d['attachment_id'],'_hpads_advertiser_id',$parent); }
        if ($key) { set_transient('hpads_key_'.get_current_user_id(),'Device #'.$result.': '.$key,120); }
        self::redirect($kind,$result);
    }
    static function assign_playlists($d,$in,$lid) {
        foreach (['primary','house'] as $purpose) {
            $pid=absint($in[$purpose.'_playlist'] ?? 0);
            if ($pid) { $p=HPADS_Core::get($pid,'playlist'); if (!$p || !HPADS_Core::can($p) || (int)$p['parent_id']!==(int)$lid || $p['data']['purpose']!==$purpose) { self::fail('Playlist must match this location and purpose.'); } }
            $d[$purpose.'_playlist']=$pid;
        } return $d;
    }
    static function validate_upload($file) {
        if (!is_array($file) || !isset($file['error']) || !is_scalar($file['error'])) {
            return HPADS_Core::error('The server did not receive the selected file. Choose the file again. If it still fails, ask your host to check PHP file_uploads, upload limits and security filtering for wp-admin/admin-post.php.');
        }
        $code=(int)$file['error'];
        $limit=size_format(wp_max_upload_size());
        $messages=[
            UPLOAD_ERR_INI_SIZE=>'The file exceeds the PHP upload_max_filesize limit ('.ini_get('upload_max_filesize').'). Ask your host to raise upload_max_filesize and post_max_size, or use a smaller file.',
            UPLOAD_ERR_FORM_SIZE=>'The file exceeds the allowed upload size. Current WordPress maximum: '.$limit.'.',
            UPLOAD_ERR_PARTIAL=>'The file upload was interrupted. Choose the file again and retry.',
            UPLOAD_ERR_NO_FILE=>'No file was attached. Choose a JPG, PNG, WebP, MP4 or WebM before saving.',
            UPLOAD_ERR_NO_TMP_DIR=>'The server is missing its temporary upload folder. Ask your host to fix PHP upload_tmp_dir.',
            UPLOAD_ERR_CANT_WRITE=>'The server could not write the uploaded file. Ask your host to check temporary-folder permissions and free disk space.',
            UPLOAD_ERR_EXTENSION=>'A server PHP extension stopped the upload. Ask your host to check its upload/security extensions.'
        ];
        if ($code!==UPLOAD_ERR_OK) { return HPADS_Core::error(($messages[$code] ?? 'The server rejected the upload.').' (PHP upload error '.$code.'.)'); }
        if (empty($file['tmp_name']) || !is_string($file['tmp_name'])) { return HPADS_Core::error('The server reported a successful upload but provided no temporary file. Ask your host to check PHP upload handling.'); }
        if ((int)($file['size'] ?? 0)>wp_max_upload_size()) { return HPADS_Core::error('The file exceeds the current WordPress upload maximum of '.$limit.'.'); }
        return true;
    }
    static function save_settings($op,$in) {
        if (!HPADS_Workflow::global_admin() || $op!=='settings') { self::fail('Use Users for team access; global admin access is required for settings.'); }
        $seconds=(int)($in['heartbeat'] ?? 300); if ($seconds<60 || $seconds>3600) { self::fail('Use 60–3600 seconds.'); }
        $device_page=absint($in['device_page_id'] ?? 0);$public=$device_page?get_post($device_page):null;if ($device_page && (!$public || $public->post_type!=='page' || $public->post_status!=='publish' || $public->post_password || !current_user_can('edit_post',$device_page))) { self::fail('Choose a published public page you can edit.'); }
        $mode=sanitize_key($in['approval_mode'] ?? 'automatic'); if (!in_array($mode,['automatic','manual'],true)) { self::fail('Invalid approval mode.'); } $base=absint($in['landing_page_id'] ?? 0);if ($base && !HPADS_Landing_Layout::candidate($base,true)) { self::fail('Choose a published or private page you can edit, without password protection.'); }update_option('hpads_settings',array_merge(get_option('hpads_settings',[]),['heartbeat'=>$seconds,'approval_mode'=>$mode,'landing_page_id'=>$base,'device_page_id'=>$device_page])); self::redirect('settings');
    }
}
