<?php
if (!defined('ABSPATH')) { exit; }
/** Real WordPress post editors; authored content stays in the native post body. */
final class HPADS_Native_Editor {
    const TYPES=['hpads_venue','hpads_location','hpads_device','hpads_advertiser','hpads_template','hpads_placement','hpads_playlist'];
    const CONFIG=['hpads_venue'=>'company','hpads_location'=>'location','hpads_device'=>'device','hpads_advertiser'=>'advertiser'];
    static $writing=false;
    static function init() {
        add_action('edit_form_after_title',[__CLASS__,'preview_top']);
        add_action('add_meta_boxes',[__CLASS__,'boxes']);
        add_action('save_post',[__CLASS__,'save'],20,3);
        add_filter('wp_insert_post_data',[__CLASS__,'validate'],20,4);
        add_filter('use_block_editor_for_post_type',[__CLASS__,'classic'],20,2);
        add_action('admin_notices',[__CLASS__,'notices']);
        add_action('admin_enqueue_scripts',[__CLASS__,'assets']);
        add_action('post_submitbox_misc_actions',[__CLASS__,'sidebar']);
        add_filter('redirect_post_location',[__CLASS__,'redirect'],20,2);
    }
    static function notices() {
        $screen=get_current_screen();if (!$screen || $screen->post_type!=='hpads_device') { return; }
        $key=get_transient('hpads_key_'.get_current_user_id());if (!$key || !preg_match('/Device #(\d+):/',$key,$match) || !HPADS_Core::can(HPADS_Core::get((int)$match[1],'device'),true)) { return; }
        delete_transient('hpads_key_'.get_current_user_id());echo '<div class="notice notice-warning"><p><strong>Save this device key now. It is shown once; rotating invalidates the previous key.</strong></p><p><code>'.esc_html($key).'</code></p></div>';
    }
    static function supports($type) { return in_array($type,self::TYPES,true); }
    static function classic($use,$type) { return self::supports($type)?false:$use; }
    static function url($type,$id=0) { return $id?add_query_arg(['post'=>HPADS_Storage::post_id($id),'action'=>'edit'],admin_url('post.php')):add_query_arg('post_type',$type,admin_url('post-new.php')); }
    static function record($pid) { return HPADS_Post_Types::record($pid); }
    static function assets() {
        $s=get_current_screen();if (!$s || !self::supports($s->post_type) || $s->base!=='post') { return; }
        wp_enqueue_media();HPADS_Admin::assets('hpads-native');
        if ($s->post_type==='hpads_device') { wp_enqueue_script('hpads-qr-library',plugins_url('assets/vendor/qrcodegen.js',HPADS_FILE),[],HPADS_VERSION,true);wp_enqueue_script('hpads-device-qr',plugins_url('assets/device-qr.js',HPADS_FILE),['hpads-qr-library'],HPADS_VERSION,true); }
        wp_enqueue_script('hwads-native',plugins_url('assets/native-editor.js',HPADS_FILE),['hpads'],HPADS_VERSION,true);
        $labels=['hpads_venue'=>'Save Venue','hpads_location'=>'Save Location','hpads_device'=>'Save Device','hpads_advertiser'=>'Save Advertiser'];wp_localize_script('hwads-native','HWADSNative',['saveLabel'=>$labels[$s->post_type] ?? 'Save Draft','postType'=>$s->post_type]);
    }
    static function boxes($type) {
        if (!self::supports($type)) { return; }
        global $wp_meta_boxes;if (isset($wp_meta_boxes[$type]['side']['core']['submitdiv'])) { $wp_meta_boxes[$type]['side']['core']['submitdiv']['title']='Save'; }
        if ($type==='hpads_playlist') { add_meta_box('hwads-playlist-settings','Playlist Settings',[__CLASS__,'playlist_settings'],$type,'side','default'); }
        if (in_array($type,['hpads_playlist','hpads_placement'],true)) { add_meta_box('hwads-embed','Shortcode & Embed Code',['HPADS_Embeds','box'],$type,'side','low'); }
        add_meta_box('hwads-details',$type==='hpads_playlist'?'Playlist Details & Placements':(isset(self::CONFIG[$type])?HPADS_Admin::PAGES[self::CONFIG[$type]].' Details':'Ad Details'),[__CLASS__,'details'],$type,'normal','high');
    }
    static function playlist_settings($post) {
        $r=self::record($post->ID);$d=$r['data'] ?? [];
        HPADS_Admin::select('Location','parent_id',HPADS_Admin::choices('location'),$r['parent_id'] ?? 0);
        HPADS_Admin::select('Purpose','purpose',['primary'=>'Primary','house'=>'House / placeholder'],$d['purpose'] ?? (HPADS_Workflow::venue_admin()&&!HPADS_Core::staff()?'house':'primary'));
    }
    static function context($post) {
        $r=self::record($post->ID);$source=null;
        if (!$r && !empty($_GET['source_template_id'])) { $source=HPADS_Placements::source(absint($_GET['source_template_id'])); }
        $d=$r['data'] ?? ($source?HPADS_Placements::fresh_data($source,$post->post_type==='hpads_template'?'template':'placement'):[]);
        return [$r,$d,$source];
    }
    static function preview_top($post) {
        if ($post->post_type==='hpads_playlist') { $r=self::record($post->ID);echo '<div class="hwads-placement-preview"><h2>Playlist Preview</h2>';if ($r) { $url=add_query_arg(['action'=>'hpads_playlist_preview','id'=>$r['id']],admin_url('admin-post.php'));echo '<iframe id="hwads-inline-playlist" title="Saved playlist preview" src="'.esc_url($url).'" allow="autoplay; fullscreen" allowfullscreen></iframe><p>Saved draft preview. Save your changes before refreshing. <button class="button" type="button" id="hwads-refresh-preview">Refresh Preview</button></p>'; }else { echo '<p>Save the playlist with its location first. Its preview will appear here after placements have been added and saved.</p>'; }echo '</div>';return; }
        if (!in_array($post->post_type,['hpads_template','hpads_placement'],true)) { return; }
        [$r,$d,$source]=self::context($post);$aid=(int)($d['attachment_id'] ?? 0);$url=$aid?wp_get_attachment_url($aid):'';
        echo '<div class="hwads-placement-preview"><div id="hwads-native-preview">';
        if ($url) { echo strpos((string)get_post_mime_type($aid),'video/')===0?'<video controls preload="metadata" src="'.esc_url($url).'"></video>':'<img src="'.esc_url($url).'" alt="'.esc_attr($d['media_alt'] ?? '').'">'; }
        else { echo '<p>Choose media in the details card below to preview this ad.</p>'; }
        echo '</div></div><h2>Landing Page Content</h2><p>Use the Visual / Code editor below for this ad’s landing-page content.</p>';
    }
    static function details($post) {
        [$r,$d,$source]=self::context($post);
        wp_nonce_field('hwads_native_save','hwads_native_nonce');
        echo '<input type="hidden" name="hwads_revision" value="'.(int)($r['revision'] ?? 0).'"><input type="hidden" name="id" value="'.(int)($r['id'] ?? 0).'">';
        if (isset(self::CONFIG[$post->post_type])) {
            $kind=self::CONFIG[$post->post_type];ob_start();HPADS_Admin::editor($kind,(int)($r['id'] ?? 0));$html=ob_get_clean();
            if (preg_match('~<fieldset[^>]*>(.*?)</fieldset></form>~s',$html,$m)) { $body=preg_replace('~<p><label><strong>Name</strong>.*?</p>~s','',$m[1],1);$body=preg_replace('~<p class="submit">.*?</p>~s','',$body);echo $body; }
            if ($kind==='company') { HPADS_Admin::check('Venue active','venue_active',$d['active'] ?? true);echo '<p>A saved venue is available immediately; it does not require approval. Active/inactive is its administrative status. Device playback is managed separately.</p>'; }return;
        }
        if ($post->post_type==='hpads_playlist') {
            // Reuse the tested bucket controls inside WordPress's own post form, not a nested form.
            ob_start();HPADS_Admin::editor('playlist',(int)($r['id'] ?? 0));$html=ob_get_clean();
            if (preg_match('~<fieldset[^>]*>(.*?)</fieldset></form>~s',$html,$m)) {
                $body=preg_replace('~<p><label><strong>Name</strong>.*?</p>~s','',$m[1],1);
                $body=preg_replace('~<p><label><strong>(?:Location|Purpose)</strong>.*?</p>~s','',$body);$body=preg_replace('~<p class="submit">.*?</p>~s','',$body);$body=preg_replace('~<p><a[^>]*href="[^"]*hpads_playlist_preview[^"]*"[^>]*>Preview Playlist</a></p>~','',$body);
                echo $body;
            }
            echo '<p class="description">This playlist contains placement cards only. Edit web content on the ad template or placement. Save Draft stores the bucket; Publish to Players is separate.</p>';return;
        }
        $mode=$post->post_type==='hpads_template'?'template':'placement';$owners=HPADS_Ads::owners(true);
        $owner=($r||$source)?implode(':',HPADS_Workflow::owner_ref($r?:$source)):sanitize_text_field($_GET['owner'] ?? '');
        $rotation=absint($d['rotation_id'] ?? $_GET['rotation_id'] ?? 0);if (!$r && !empty($_GET['rotation_id'])) { $rotation=absint($_GET['rotation_id']); }
        $p=$rotation?HPADS_Core::get($rotation,'playlist'):null;
        if (!$r && !$source && $p) { $owners=array_filter($owners,function($label,$key) use($p){[$t,$id]=explode(':',$key);return HPADS_Placements::can_bind($t,(int)$id,$p);},ARRAY_FILTER_USE_BOTH); }
        if (!isset($owners[$owner]) && !$r && !$source) { $owner=array_key_first($owners) ?? ''; }
        [$type,$oid]=array_pad(explode(':',$owner),2,0);
        echo '<div class="hpads-native-card hpads-item"><div class="hpads-native-media">';$aid=(int)($d['attachment_id'] ?? 0);$url=$aid?wp_get_attachment_url($aid):'';
        echo '<p>Ad media is previewed above.</p><input type="hidden" name="hwads[attachment_id]" id="hwads-attachment" value="'.$aid.'"><button type="button" class="button" id="hwads-choose-media">Choose / Upload Media</button></div><div class="hpads-native-fields">';
        if (!$r || HPADS_Workflow::production() || HPADS_Placements::manager()) {
            if ($owner && !isset($owners[$owner])) { $o=HPADS_Core::get((int)$oid,$type);$owners[$owner]=$o['title'] ?? 'Current owner'; }
            HPADS_Admin::select('Owner','hwads[owner]',$owners,$owner);
        } else { $o=HPADS_Core::get((int)$oid,$type);echo '<p><strong>Owner:</strong> '.esc_html($o['title'] ?? 'Unavailable owner').'</p><input type="hidden" name="hwads[owner]" value="'.esc_attr($owner).'">'; }

        if ($source) { echo '<input type="hidden" name="hwads[source_template_id]" value="'.(int)$source['id'].'">'; }
        if ($mode==='placement') {
            if ($r) { echo '<p><strong>Playlist:</strong> '.esc_html($p['title'] ?? 'Unavailable playlist').'</p><input type="hidden" name="hwads[rotation_id]" value="'.$rotation.'">'; }
            else { HPADS_Admin::select('Playlist','hwads[rotation_id]',HPADS_Placements::rotations($type,(int)$oid),$rotation); }
            echo '<p id="hwads-owner-status" role="status"></p>';
        }
        HPADS_Admin::select('Orientation','hwads[orientation]',['auto'=>'Detect from media','horizontal'=>'Horizontal','vertical'=>'Vertical'],$d['orientation'] ?? 'auto');
        HPADS_Admin::select('Placement type','hwads[placement_type]',HPADS_Placements::TYPES,$d['placement_type'] ?? 'advertising');
        HPADS_Admin::field('Seconds override','hwads[duration]',$d['duration'] ?? '','number','Empty inherits the location default; videos play full length.');
        if ($mode==='placement') { HPADS_Admin::field('Start','hwads[start]',HPADS_Core::local_value($d['start'] ?? ''),'datetime-local');HPADS_Admin::field('End','hwads[end]',HPADS_Core::local_value($d['end'] ?? ''),'datetime-local');HPADS_Admin::check('Active','hwads[active]',$d['active'] ?? true);echo '<p>Timezone: '.esc_html(wp_timezone_string()).'</p>'; }
        HPADS_Admin::field('External URL (optional)','hwads[link_url]',$d['link_url'] ?? '','url');HPADS_Admin::check('Show QR Code','hwads[qr_enabled]',$d['qr_enabled'] ?? false);
        HPADS_Admin::select('Entrance','hwads[transition_in]',HPADS_Placements::TRANSITIONS,$d['transition_in'] ?? 'none');HPADS_Admin::select('Exit','hwads[transition_out]',HPADS_Placements::TRANSITIONS,$d['transition_out'] ?? 'none');
        HPADS_Admin::field('Alternative text','hwads[media_alt]',$d['media_alt'] ?? '');HPADS_Admin::field('Caption','hwads[caption]',$d['caption'] ?? '');
        echo '</div></div><p>The editor above is the landing-page content. Media and playback options belong to this entry. Saving does not publish a playlist to players.</p>';
    }
    static function input($post,$raw) {
        $r=self::record($post->ID);$in=isset($raw['hwads']) && is_array($raw['hwads'])?$raw['hwads']:[];
        return array_merge($in,['title'=>$raw['post_title'] ?? $post->post_title,'description'=>$raw['content'] ?? $raw['post_content'] ?? $post->post_content,'record_type'=>$post->post_type==='hpads_template'?'template':'placement','_local_dates'=>1,'id'=>$r['id'] ?? 0,'revision'=>absint($raw['hwads_revision'] ?? 0)]);
    }
    static function validate($data,$postarr,$raw,$update) {
        if (self::$writing || !self::supports($data['post_type'] ?? '') || empty($_POST['hwads_native_nonce'])) { return $data; }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hwads_native_nonce'])),'hwads_native_save')) { wp_die('Invalid editor nonce.','HWADS',['response'=>403]); }
        $pid=absint($_POST['post_ID'] ?? 0);$post=get_post($pid);
        if (!$post || $data['post_type']!==$post->post_type || !current_user_can('edit_post',$pid)) { wp_die('Entry unavailable.','HWADS',['response'=>403]); }
        $r=self::record($pid);$input=wp_unslash($_POST);
        if ($r && (int)($input['hwads_revision'] ?? 0)!==(int)$r['revision']) { wp_die('This entry changed. Reload before saving.','HWADS',['response'=>409]); }
        if (isset(self::CONFIG[$post->post_type])) { $check=self::prepare_config(self::CONFIG[$post->post_type],$input,$r);if (is_wp_error($check)) { wp_die(esc_html($check->get_error_message()),'HWADS',['response'=>400,'back_link'=>true]); } }
        elseif ($post->post_type!=='hpads_playlist') { $in=self::input($post,$input);$check=HPADS_Placements::prepare($in,$r);if (is_wp_error($check)) { wp_die(esc_html($check->get_error_message()),'HWADS',['response'=>400,'back_link'=>true]); } }
        $data['post_status']=isset(self::CONFIG[$post->post_type])?($post->post_type==='hpads_venue' && empty($input['venue_active'])?'draft':'publish'):($post->post_type==='hpads_playlist' && self::publishing($input)?'publish':'draft');return $data;
    }
    static function save($pid,$post,$update) {
        if (self::$writing || !self::supports($post->post_type) || empty($_POST['hwads_native_nonce']) || wp_is_post_revision($pid) || wp_is_post_autosave($pid)) { return; }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hwads_native_nonce'])),'hwads_native_save') || !current_user_can('edit_post',$pid)) { return; }
        self::$writing=true;
        try { $result=self::save_record($pid,wp_unslash($_POST));if (is_wp_error($result)) { $record=self::record($pid);wp_update_post(['ID'=>$pid,'post_status'=>!empty($record['data']['published_version'])?'publish':'draft']);wp_die(esc_html($result->get_error_message()),'HWADS',['response'=>400,'back_link'=>true]); } }
        finally { self::$writing=false; }
    }
    static function save_record($pid,$raw) {
        $post=get_post($pid);if (!$post || !self::supports($post->post_type) || !current_user_can('edit_post',$pid)) { return HPADS_Core::error('Entry unavailable.',403); }
        $r=self::record($pid);
        if ($r && (int)($raw['hwads_revision'] ?? 0)!==(int)$r['revision']) { return HPADS_Core::error('This entry changed. Reload before saving.'); }
        if (isset(self::CONFIG[$post->post_type])) {
            $kind=self::CONFIG[$post->post_type];$values=self::prepare_config($kind,$raw,$r);if (is_wp_error($values)) { return $values; }$key='';
            if ($kind==='device' && (!$r || !empty($raw['rotate_key']))) { $key=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$values['data']['key_hash']=hash('sha256',$key); }
            $result=HPADS_Storage::put($kind,$values['title'],$values['parent'],$values['data'],$r['id'] ?? 0,$r['revision'] ?? 0,$pid);
            if (!is_wp_error($result) && $key) { set_transient('hpads_key_'.get_current_user_id(),'Device #'.$result.': '.$key,120); }return $result;
        }
        if ($post->post_type==='hpads_playlist') {
            $in=$raw;$in['revision']=absint($raw['hwads_revision'] ?? 0);$in['title']=$raw['post_title'] ?? $post->post_title;$in['description']=$raw['content'] ?? $post->post_content;
            if ($r) { $result=HPADS_Placements::save_playlist($r,$in); }
            else {
                $lid=absint($in['parent_id'] ?? 0);$l=HPADS_Core::get($lid,'location');$purpose=sanitize_key($in['purpose'] ?? 'primary');$candidate=['kind'=>'playlist','parent_id'=>$lid,'data'=>['purpose'=>$purpose]];
                if (!$l || !in_array($purpose,['primary','house'],true) || !HPADS_Core::can($candidate,true) || !HPADS_Admin::can_add('playlist')) { return HPADS_Core::error('Choose an accessible playlist location.',403); }
                $result=HPADS_Storage::put('playlist',sanitize_text_field($in['title']),$lid,['purpose'=>$purpose,'items'=>[],'description'=>$post->post_content],0,0,$pid);
            }
            if (!is_wp_error($result) && self::publishing($raw)) { $published=HPADS_Core::publish($result);if (is_wp_error($published)) { return $published; } }
            return $result;
        }
        $in=self::input($post,$raw);$values=HPADS_Placements::prepare($in,$r);if (is_wp_error($values)) { return $values; }
        if (!$values['title']) { return HPADS_Core::error('Enter an ad name.'); }
        $result=HPADS_Storage::put('creative',$values['title'],$values['parent'],$values['data'],$r['id'] ?? 0,$r['revision'] ?? 0,$pid);
        if (!is_wp_error($result) && ($raw['hwads_copy'] ?? '')==='template') { $copy=HPADS_Placements::copy(HPADS_Core::get($result),'template');if (!is_wp_error($copy)) { self::$redirect=HPADS_Native_Editor::url('hpads_template',$copy); } }
        return $result;
    }
    static function publishing($raw) { return !empty($raw['hwads_publish']) || (!empty($raw['publish']) && empty($raw['save'])); }
    static function prepare_config($kind,$in,$r) {
        if ($r ? !HPADS_Core::can($r,true) : !HPADS_Admin::can_add($kind)) { return HPADS_Core::error('Access denied.',403); }
        $title=sanitize_text_field($in['post_title'] ?? '');if (!$title || strlen($title)>200) { return HPADS_Core::error('Enter a name of no more than 200 bytes.'); }
        $parent=absint($in['parent_id'] ?? 0);$d=$r['data'] ?? [];
        if ($r && $parent!==(int)$r['parent_id']) { return HPADS_Core::error('The parent cannot be changed. Create a new record instead.'); }
        if (in_array($kind,['company','advertiser'],true)) {
            $parent=0;$d['email']=sanitize_email($in['email'] ?? '');
            if ($kind==='company') { $d['active']=!empty($in['venue_active']); }
            else {
                if (HPADS_Workflow::relations()) { $d['venues']=HPADS_Core::ids($in['venues'] ?? []);foreach ($d['venues'] as $cid) { if (!HPADS_Core::can(HPADS_Core::get($cid,'company'))) { return HPADS_Core::error('Venue outside your scope.',403); } } }
                if (HPADS_Workflow::global_admin() || current_user_can('hpads_admin') || current_user_can('hpads_account_manager')) {
                    $d['approved_placements']=HPADS_Core::ids($in['approved_placements'] ?? []);
                    foreach ($d['approved_placements'] as $lid) { $l=HPADS_Core::get($lid,'location');if (!$l || !HPADS_Core::can($l) || !in_array((int)$l['parent_id'],$d['venues'] ?? [],true)) { return HPADS_Core::error('Approved locations must belong to an accessible connected venue.'); } }
                }
            }
        } else {
            $p=HPADS_Core::get($parent,$kind==='location'?'company':'location');if (!$p || !HPADS_Core::can($p)) { return HPADS_Core::error('Choose an accessible parent.',403); }
            if (!HPADS_Workflow::global_admin() && !current_user_can('hpads_admin') && !current_user_can('hpads_account_manager')) { return HPADS_Core::error('Location/device management requires account-manager access.',403); }
            if ($kind==='location') { $duration=(int)($in['duration'] ?? 10);if ($duration<1 || $duration>3600) { return HPADS_Core::error('Duration must be 1–3600 seconds.'); }$d=array_merge($d,['duration'=>$duration,'approval_required'=>!empty($in['approval_required']),'allow_house'=>!empty($in['allow_house']),'house_rotation'=>!empty($in['house_rotation'])]); }
            else { $type=sanitize_key($in['type'] ?? 'mixed');$orientation=sanitize_key($in['orientation'] ?? 'horizontal');if (!isset(HPADS_Core::TYPES[$type]) || !in_array($orientation,['horizontal','vertical'],true)) { return HPADS_Core::error('Choose a valid device type and orientation.'); }$d=array_merge($d,['type'=>$type,'orientation'=>$orientation,'custom_type'=>sanitize_text_field($in['custom_type'] ?? ''),'enabled'=>!empty($in['enabled'])]); }
            foreach (['primary','house'] as $purpose) { $id=absint($in[$purpose.'_playlist'] ?? 0);if ($id) { $pl=HPADS_Core::get($id,'playlist');$lid=$kind==='location'?($r['id'] ?? 0):$parent;if (!$pl || !HPADS_Core::can($pl) || (int)$pl['parent_id']!==(int)$lid || ($pl['data']['purpose'] ?? '')!==$purpose) { return HPADS_Core::error('Playlist must match this location and purpose.'); } }$d[$purpose.'_playlist']=$id; }
        }
        return ['title'=>$title,'parent'=>$parent,'data'=>$d];
    }
    static $redirect='';
    static function redirect($url,$pid) { return self::$redirect?:$url; }
    static function sidebar($post) {
        if (!self::supports($post->post_type)) { return; }$r=self::record($post->ID);
        if (isset(self::CONFIG[$post->post_type])) { echo '<div class="misc-pub-section">Save makes this record available immediately. No approval or player-publication step is required.</div>';if ($r && $post->post_type==='hpads_device') { echo '<div class="misc-pub-section"><strong>Device status</strong><p>'.esc_html(HPADS_Admin::status($r['id'])).'</p></div>'; }return; }
        if ($post->post_type!=='hpads_playlist') { echo '<div class="misc-pub-section">Save stores a draft. Publish the playlist separately to make placement changes live.</div>'; }

        if ($r && $post->post_type==='hpads_template') { echo '<div class="misc-pub-section"><a class="button" target="_blank" rel="noopener" href="'.esc_url(HPADS_Placements::preview_url($r['id'])).'">Preview Ad Template</a><p>Private preview of the last saved template.</p></div>'; }
        if ($r && $post->post_type==='hpads_placement') { echo '<div class="misc-pub-section"><button class="button" name="hwads_copy" value="template">Save as New Template</button> <a target="_blank" rel="noopener" href="'.esc_url(HPADS_Placements::preview_url($r['id'])).'">Preview Landing Page</a></div>'; }
    }
}
