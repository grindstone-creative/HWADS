<?php
if (!defined('ABSPATH')) { exit; }
/** Real WordPress post editors; authored content stays in the native post body. */
final class HPADS_Native_Editor {
    const TYPES=['hpads_template','hpads_placement','hpads_playlist'];
    static $writing=false;
    static function init() {
        add_action('edit_form_after_title',[__CLASS__,'preview_top']);
        add_action('add_meta_boxes',[__CLASS__,'boxes']);
        add_action('save_post',[__CLASS__,'save'],20,3);
        add_filter('wp_insert_post_data',[__CLASS__,'validate'],20,4);
        add_filter('use_block_editor_for_post_type',[__CLASS__,'classic'],20,2);
        add_action('admin_enqueue_scripts',[__CLASS__,'assets']);
        add_action('post_submitbox_misc_actions',[__CLASS__,'sidebar']);
        add_filter('redirect_post_location',[__CLASS__,'redirect'],20,2);
    }
    static function supports($type) { return in_array($type,self::TYPES,true); }
    static function classic($use,$type) { return self::supports($type)?false:$use; }
    static function url($type,$id=0) { return $id?add_query_arg(['post'=>HPADS_Storage::post_id($id),'action'=>'edit'],admin_url('post.php')):add_query_arg('post_type',$type,admin_url('post-new.php')); }
    static function record($pid) { return HPADS_Post_Types::record($pid); }
    static function assets() {
        $s=get_current_screen();if (!$s || !self::supports($s->post_type) || $s->base!=='post') { return; }
        wp_enqueue_media();HPADS_Admin::assets('hpads-native');
        wp_enqueue_script('hwads-native',plugins_url('assets/native-editor.js',HPADS_FILE),['hpads'],HPADS_VERSION,true);
    }
    static function boxes($type) {
        if (!self::supports($type)) { return; }
        global $wp_meta_boxes;if (isset($wp_meta_boxes[$type]['side']['core']['submitdiv'])) { $wp_meta_boxes[$type]['side']['core']['submitdiv']['title']='Save'; }
        add_meta_box('hwads-details',$type==='hpads_playlist'?'Playlist Details & Placements':'Ad Details',[__CLASS__,'details'],$type,'normal','high');
    }
    static function context($post) {
        $r=self::record($post->ID);$source=null;
        if (!$r && !empty($_GET['source_template_id'])) { $source=HPADS_Placements::source(absint($_GET['source_template_id'])); }
        $d=$r['data'] ?? ($source?HPADS_Placements::fresh_data($source,$post->post_type==='hpads_template'?'template':'placement'):[]);
        return [$r,$d,$source];
    }
    static function preview_top($post) {
        if ($post->post_type!=='hpads_placement') { return; }
        [$r,$d,$source]=self::context($post);$aid=(int)($d['attachment_id'] ?? 0);$url=$aid?wp_get_attachment_url($aid):'';
        echo '<div class="hwads-placement-preview"><div id="hwads-native-preview">';
        if ($url) { echo strpos((string)get_post_mime_type($aid),'video/')===0?'<video controls preload="metadata" src="'.esc_url($url).'"></video>':'<img src="'.esc_url($url).'" alt="'.esc_attr($d['media_alt'] ?? '').'">'; }
        else { echo '<p>Choose media in the details card below to preview this placement.</p>'; }
        echo '</div><p><button type="button" class="button" id="hwads-toggle-content" aria-expanded="false" aria-controls="postdivrich">Edit Web Content</button></p></div>';
    }
    static function details($post) {
        [$r,$d,$source]=self::context($post);
        wp_nonce_field('hwads_native_save','hwads_native_nonce');
        echo '<input type="hidden" name="hwads_revision" value="'.(int)($r['revision'] ?? 0).'"><input type="hidden" name="id" value="'.(int)($r['id'] ?? 0).'">';
        if ($post->post_type==='hpads_playlist') {
            // Reuse the tested bucket controls inside WordPress's own post form, not a nested form.
            ob_start();HPADS_Admin::editor('playlist',(int)($r['id'] ?? 0));$html=ob_get_clean();
            if (preg_match('~<fieldset[^>]*>(.*?)</fieldset></form>~s',$html,$m)) {
                $body=preg_replace('~<p><label><strong>Name</strong>.*?</p>~s','',$m[1],1);
                $body=preg_replace('~<p class="submit">.*?</p>~s','',$body);
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
        echo '<div'.($mode==='placement'?' class="hwads-card-media-summary"':' id="hwads-native-preview"').'>'.($mode==='placement'?'<p>Placement media is previewed above.</p>':'');if ($url && $mode!=='placement') { echo strpos((string)get_post_mime_type($aid),'video/')===0?'<video controls preload="metadata" src="'.esc_url($url).'"></video>':'<img src="'.esc_url($url).'" alt="">'; }echo '</div><input type="hidden" name="hwads[attachment_id]" id="hwads-attachment" value="'.$aid.'"><button type="button" class="button" id="hwads-choose-media">Choose / Upload Media</button></div><div class="hpads-native-fields">';
        if ($r||$source) { $o=HPADS_Core::get((int)$oid,$type);echo '<p><strong>Owner:</strong> '.esc_html($o['title'] ?? 'Unavailable owner').'</p><input type="hidden" name="hwads[owner]" value="'.esc_attr($owner).'">'; }
        else { HPADS_Admin::select('Owner','hwads[owner]',$owners,$owner); }
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
        if ($post->post_type!=='hpads_playlist') { $in=self::input($post,$input);$check=HPADS_Placements::prepare($in,$r);if (is_wp_error($check)) { wp_die(esc_html($check->get_error_message()),'HWADS',['response'=>400,'back_link'=>true]); } }
        $data['post_status']='draft';return $data;
    }
    static function save($pid,$post,$update) {
        if (self::$writing || !self::supports($post->post_type) || empty($_POST['hwads_native_nonce']) || wp_is_post_revision($pid) || wp_is_post_autosave($pid)) { return; }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hwads_native_nonce'])),'hwads_native_save') || !current_user_can('edit_post',$pid)) { return; }
        self::$writing=true;
        try { $result=self::save_record($pid,wp_unslash($_POST));if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'HWADS',['response'=>400,'back_link'=>true]); } }
        finally { self::$writing=false; }
    }
    static function save_record($pid,$raw) {
        $post=get_post($pid);if (!$post || !self::supports($post->post_type) || !current_user_can('edit_post',$pid)) { return HPADS_Core::error('Entry unavailable.',403); }
        $r=self::record($pid);
        if ($r && (int)($raw['hwads_revision'] ?? 0)!==(int)$r['revision']) { return HPADS_Core::error('This entry changed. Reload before saving.'); }
        if ($post->post_type==='hpads_playlist') {
            $in=$raw;$in['revision']=absint($raw['hwads_revision'] ?? 0);$in['title']=$raw['post_title'] ?? $post->post_title;$in['description']=$raw['content'] ?? $post->post_content;
            if ($r) { $result=HPADS_Placements::save_playlist($r,$in); }
            else {
                $lid=absint($in['parent_id'] ?? 0);$l=HPADS_Core::get($lid,'location');$purpose=sanitize_key($in['purpose'] ?? 'primary');$candidate=['kind'=>'playlist','parent_id'=>$lid,'data'=>['purpose'=>$purpose]];
                if (!$l || !in_array($purpose,['primary','house'],true) || !HPADS_Core::can($candidate,true) || !HPADS_Admin::can_add('playlist')) { return HPADS_Core::error('Choose an accessible playlist location.',403); }
                $result=HPADS_Storage::put('playlist',sanitize_text_field($in['title']),$lid,['purpose'=>$purpose,'items'=>[],'description'=>$post->post_content],0,0,$pid);
            }
            if (!is_wp_error($result) && !empty($raw['hwads_publish'])) { $published=HPADS_Core::publish($result);if (is_wp_error($published)) { return $published; } }
            return $result;
        }
        $in=self::input($post,$raw);$values=HPADS_Placements::prepare($in,$r);if (is_wp_error($values)) { return $values; }
        if (!$values['title']) { return HPADS_Core::error('Enter an ad name.'); }
        $result=HPADS_Storage::put('creative',$values['title'],$values['parent'],$values['data'],$r['id'] ?? 0,$r['revision'] ?? 0,$pid);
        if (!is_wp_error($result) && ($raw['hwads_copy'] ?? '')==='template') { $copy=HPADS_Placements::copy(HPADS_Core::get($result),'template');if (!is_wp_error($copy)) { self::$redirect=HPADS_Native_Editor::url('hpads_template',$copy); } }
        return $result;
    }
    static $redirect='';
    static function redirect($url,$pid) { return self::$redirect?:$url; }
    static function sidebar($post) {
        if (!self::supports($post->post_type)) { return; }$r=self::record($post->ID);
        echo '<div class="misc-pub-section">Save stores a draft. Player publication remains separate.</div>';
        if ($r && $post->post_type==='hpads_playlist') { echo '<div class="misc-pub-section"><a class="button" target="_blank" rel="noopener" href="'.esc_url(add_query_arg(['action'=>'hpads_playlist_preview','id'=>$r['id']],admin_url('admin-post.php'))).'">Preview Playlist</a> <button class="button" name="hwads_publish" value="1">Save & Publish to Players</button></div>'; }
        if ($r && $post->post_type==='hpads_placement') { echo '<div class="misc-pub-section"><button class="button" name="hwads_copy" value="template">Save as New Template</button> <a target="_blank" rel="noopener" href="'.esc_url(HPADS_Placements::preview_url($r['id'])).'">Preview Landing Page</a></div>'; }
    }
}
