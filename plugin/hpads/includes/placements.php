<?php
if (!defined('ABSPATH')) { exit; }
/** Independent rotation entries and copy-only templates, stored in the existing ad table. */
final class HPADS_Placements {
    const TRANSITIONS=['none'=>'None','fade'=>'Fade','slide-left'=>'Slide left','slide-right'=>'Slide right','slide-up'=>'Slide up','slide-down'=>'Slide down','zoom'=>'Zoom'];
    const TYPES=['advertising'=>'Advertising','menu'=>'Menu','promotion'=>'Promotion','special'=>'Special','event'=>'Event','house'=>'House content','placeholder'=>'Placeholder'];
    static function manager() { return current_user_can('hpads_admin') || current_user_can('hpads_account_manager'); }
    static function managed_location($lid) {
        $l=HPADS_Core::get($lid,'location'); return $l && in_array((int)$l['parent_id'],HPADS_Core::assigned('companies'),true);
    }
    static function author_owner($type,$oid) {
        if (HPADS_Workflow::team_owns($type,$oid)) { return true; }
        if (self::manager()) { foreach (HPADS_Workflow::eligible_locations($type,$oid) as $l) { if (self::managed_location($l['id'])) { return true; } } }
        return false;
    }
    static function is_template($ad) { return ($ad['data']['record_type'] ?? '')==='template'; }
    static function owner_playlist_options($owner) {
        $ref=explode(':',sanitize_text_field($owner));
        if (!current_user_can('hpads_access') || count($ref)!==2 || !in_array($ref[0],['company','advertiser'],true) || !HPADS_Core::get(absint($ref[1]),$ref[0]) || !self::author_owner($ref[0],absint($ref[1]))) { return HPADS_Core::error('Owner unavailable for your account.',403); }
        $options=[]; foreach (self::rotations($ref[0],absint($ref[1])) as $value=>$text) { $options[]=['value'=>(int)$value,'text'=>$text]; }
        return $options;
    }
    static function ajax_owner_playlists() {
        check_ajax_referer('hpads_owner_playlists','nonce');
        $options=self::owner_playlist_options(wp_unslash($_POST['owner'] ?? ''));
        if (is_wp_error($options)) { wp_send_json_error(['message'=>$options->get_error_message()],403); }
        wp_send_json_success(['playlists'=>$options]);
    }
    static function create_in_playlist($in) {
        $p=HPADS_Core::get(absint($in['rotation_id'] ?? 0),'playlist');
        if (!$p || !HPADS_Core::can($p,true)) { return HPADS_Core::error('Playlist unavailable.',403); }
        if (!empty($in['source_id'])) {
            $source=HPADS_Core::get(absint($in['source_id']),'creative');
            if (!$source || !HPADS_Workflow::can_ad($source)) { return HPADS_Core::error('Source unavailable.',403); }
            return self::copy($source,'placement',$p['id']);
        }
        $in['record_type']='placement'; $in['id']=0; $in['active']=1;
        return self::save($in);
    }
    static function card($id) {
        $r=HPADS_Core::get($id,'creative'); $d=$r['data'];
        return ['id'=>(int)$id,'title'=>$r['title'],'preview'=>wp_get_attachment_url($d['attachment_id']),'video'=>strpos((string)get_post_mime_type($d['attachment_id']),'video/')===0,'edit'=>HPADS_Admin::url('creative',$id),'duration'=>$d['duration'] ?? '', 'start'=>HPADS_Core::local_value($d['start'] ?? ''), 'end'=>HPADS_Core::local_value($d['end'] ?? ''),'link_url'=>$d['link_url'] ?? '', 'qr_enabled'=>!empty($d['qr_enabled']),'transition_in'=>$d['transition_in'] ?? 'none','transition_out'=>$d['transition_out'] ?? 'none','revision'=>(int)$r['revision'],'template_edit'=>!empty($d['source_template_id'])?HPADS_Admin::url('templates',$d['source_template_id']):''];
    }
    static function ajax_create() {
        check_ajax_referer('hpads_playlist_create','nonce');
        $id=self::create_in_playlist(wp_unslash($_POST));
        if (is_wp_error($id)) { wp_send_json_error(['message'=>$id->get_error_message()],400); }
        wp_send_json_success(self::card($id));
    }
    static function ajax_template() {
        check_ajax_referer('hpads_playlist_create','nonce');
        $in=wp_unslash($_POST);$in['id']=0;$in['record_type']='template';
        $id=self::save($in);
        if (is_wp_error($id)) { wp_send_json_error(['message'=>$id->get_error_message()],400); }
        wp_send_json_success(['id'=>(int)$id,'title'=>HPADS_Core::get($id)['title'],'edit'=>HPADS_Admin::url('templates',$id)]);
    }
    static function public_url($id) { return add_query_arg('hpads_ad',(int)$id,home_url('/')); }
    static function published_item($id) {
        global $wpdb;
        $r=HPADS_Core::get($id,'creative'); if (!$r || self::is_template($r)) { return null; }
        $p=HPADS_Core::get($r['data']['rotation_id'] ?? 0,'playlist'); if (!$p || empty($p['data']['published_version'])) { return null; }
        $json=$wpdb->get_var($wpdb->prepare('SELECT manifest FROM '.HPADS_Core::table(true).' WHERE id=%d AND playlist_id=%d',$p['data']['published_version'],$p['id']));
        $manifest=json_decode((string)$json,true);
        foreach (($manifest['items'] ?? []) as $item) { if ((int)($item['placement_id'] ?? 0)===(int)$id) { return $item; } }
        return null;
    }
    static function public_page() {
        if (!isset($_GET['hpads_ad'])) { return; }
        $item=self::published_item(absint($_GET['hpads_ad']));
        if (!$item) { status_header(404);nocache_headers();wp_die('This ad is not published.','HWADS',['response'=>404]); }
        status_header(200); header('Content-Type: text/html; charset=UTF-8');header('X-Robots-Tag: noindex, follow');
        $d=$item['landing'] ?? [];
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($item['title']).'</title><style>body{font:18px/1.5 system-ui;max-width:900px;margin:30px auto;padding:0 20px}img,video{max-width:100%;height:auto}a{color:#17558a}</style></head><body><h1>'.esc_html($item['title']).'</h1>';
        if (strpos($item['mime'],'video/')===0) { echo '<video controls src="'.esc_url($item['url']).'"></video>'; }
        else { echo '<img src="'.esc_url($item['url']).'" alt="'.esc_attr($d['alt'] ?? '').'">'; }
        if (!empty($d['caption'])) { echo '<p>'.esc_html($d['caption']).'</p>'; }
        echo wp_kses_post($d['description'] ?? '');
        if (!empty($d['external_url'])) { echo '<p><a rel="noopener noreferrer" href="'.esc_url($d['external_url']).'">Visit website</a></p>'; }
        echo '</body></html>';exit;
    }
    static function preview_url($id) { return add_query_arg(['action'=>'hpads_landing_preview','id'=>(int)$id],admin_url('admin-post.php')); }
    static function preview() {
        $r=HPADS_Core::get(absint($_GET['id'] ?? 0),'creative');
        if (!$r || !is_user_logged_in() || !HPADS_Workflow::can_ad($r)) { wp_die('Preview unavailable.','HWADS',['response'=>403]); }
        nocache_headers();header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
        $d=$r['data'];$url=wp_get_attachment_url($d['attachment_id']);
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($r['title']).'</title><style>body{font:18px/1.5 system-ui;max-width:900px;margin:30px auto;padding:0 20px}img,video{max-width:100%;height:auto}aside{background:#f0f6fc;padding:14px}a{color:#17558a}</style></head><body><aside>Private draft preview · Engagement tracking is not enabled yet.</aside><h1>'.esc_html($r['title']).'</h1>';
        if (strpos((string)get_post_mime_type($d['attachment_id']),'video/')===0) { echo '<video controls src="'.esc_url($url).'"></video>'; }
        else { echo '<img src="'.esc_url($url).'" alt="'.esc_attr($d['media_alt'] ?? '').'">'; }
        if (!empty($d['caption'])) { echo '<p>'.esc_html($d['caption']).'</p>'; }
        echo wp_kses_post($d['description'] ?? '');
        if (!empty($d['link_url'])) { echo '<p><a rel="noopener noreferrer" href="'.esc_url($d['link_url']).'">Visit website</a></p>'; }
        echo '</body></html>';exit;
    }
    static function template_upload_panel() {
        $owners=HPADS_Ads::owners(true);if (!$owners) { return; }
        echo '<div class="hpads-template-upload"><h2>Upload new ad templates</h2><label>Owner <select id="hpads-template-owner">';
        foreach ($owners as $key=>$label) { echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>'; }
        echo '</select></label> <label>Orientation <select id="hpads-template-orientation"><option value="auto">Detect from file</option><option value="horizontal">Horizontal</option><option value="vertical">Vertical</option></select></label><div id="hpads-template-drop" tabindex="0" role="button" aria-label="Upload media to create ad templates">Drop images or videos here, or click to choose files</div><input type="file" id="hpads-template-files" multiple hidden accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" data-max-bytes="'.esc_attr(wp_max_upload_size()).'"><p id="hpads-template-upload-status" role="status"></p><ul id="hpads-template-upload-results"></ul><p class="description">One template per file. Templates are saved immediately and are not added to a playlist. Server maximum: '.esc_html(size_format(wp_max_upload_size())).'.</p></div>';
    }
    static function time_state($ad) {
        $d=$ad['data']; $now=gmdate('Y-m-d\TH:i');
        if (!empty($d['end']) && $d['end']<=$now) { return 'past'; }
        if (isset($d['active']) && !$d['active']) { return 'inactive'; }
        return !empty($d['start']) && $d['start']>$now?'scheduled':'current';
    }
    static function can_bind($type,$oid,$rotation) {
        if (!$rotation || $rotation['kind']!=='playlist' || !self::author_owner($type,$oid)) { return false; }
        if (self::manager() && !HPADS_Workflow::production() && !self::managed_location($rotation['parent_id'])) { return false; }
        $eligible=array_map(function($l){return (int)$l['id'];},HPADS_Workflow::eligible_locations($type,$oid));
        if (!in_array((int)$rotation['parent_id'],$eligible,true)) { return false; }
        if (($rotation['data']['purpose'] ?? '')==='house') {
            if ($type!=='company') { return false; }
            $l=HPADS_Core::get($rotation['parent_id'],'location');
            return HPADS_Workflow::production() || ((HPADS_Workflow::venue_admin() || current_user_can('hpads_admin') || current_user_can('hpads_account_manager')) && !empty($l['data']['allow_house']));
        }
        return true;
    }
    static function rotations($type,$oid) {
        global $wpdb; $out=[0=>'— Select one playlist —'];
        foreach (HPADS_Core::entity_ids('playlist') as $id) {
            $p=HPADS_Core::get($id,'playlist');
            if (self::can_bind($type,$oid,$p)) { $l=HPADS_Core::get($p['parent_id'],'location'); $out[$id]=$l['title'].' → '.$p['title']; }
        }
        return $out;
    }
    static function source($id) {
        $ad=HPADS_Core::get($id,'creative');
        return $ad && self::is_template($ad) && HPADS_Workflow::can_ad($ad) ? $ad : null;
    }
    static function fresh_data($source,$mode,$rotation=null) {
        $d=$source['data'];
        // Copy authored content/media, not destination-specific state or approval history.
        foreach (['reviews','manager_review','review_history','approved_locations','submitted_at','submitted_by','submission_revision','published_version','invoice_id','billed_at','billing_history','rotation_id','locations','target_mode','start','end','active','copied_from','source_template_id'] as $key) { unset($d[$key]); }
        $d['record_type']=$mode; $d['qr_enabled']=!empty($d['qr_enabled']); $d['workflow']=2; $d['state']='draft'; $d['content_revision']=1;
        $d['submission_revision']=0; $d['reviews']=[]; $d['manager_review']=[]; $d['approved_locations']=[]; $d['review_history']=[];
        $d['rotation_id']=$rotation?(int)$rotation['id']:0; $d['locations']=$rotation?[(int)$rotation['parent_id']]:[]; $d['location_id']=0;
        $d['target_mode']='selected'; $d['start']=''; $d['end']=''; $d['active']=true; $d['created_by']=get_current_user_id();
        $d['copied_from']=(int)$source['id'];
        if (self::is_template($source)) { $d['source_template_id']=(int)$source['id']; }
        return $d;
    }
    static function copy($source,$mode,$rotation_id=0,$title='') {
        if (!(HPADS_Workflow::can_ad($source,true) || (self::is_template($source) && HPADS_Workflow::can_ad($source))) || !in_array($mode,['placement','template'],true)) { return HPADS_Core::error('You cannot copy this entry.',403); }
        [$type,$oid]=HPADS_Workflow::owner_ref($source); $rotation=null;
        if ($mode==='placement') {
            $rotation=HPADS_Core::get($rotation_id,'playlist');
            if (!self::can_bind($type,$oid,$rotation)) { return HPADS_Core::error('Choose an eligible playlist. Each copy must have its own destination.',403); }
        }
        $d=self::fresh_data($source,$mode,$rotation);
        return HPADS_Core::put('creative',$title?:$source['title'].($mode==='template'?' — Template':' — Copy'),$type==='advertiser'?$oid:0,$d);
    }
    static function prepare($in,$r) {
        $mode=sanitize_key($in['record_type'] ?? 'placement');
        if (!in_array($mode,['placement','template'],true) || ($r && $mode!==($r['data']['record_type'] ?? 'placement'))) { return HPADS_Core::error('An existing entry cannot change between placement and template. Use Save as Template or Create Placement.'); }
        $source_id=absint($in['source_template_id'] ?? 0); $source=null;
        if (!$r && $source_id) { $source=self::source($source_id); if (!$source) { return HPADS_Core::error('Template is unavailable.',403); } }
        $ref=explode(':',sanitize_text_field($in['owner'] ?? ''));
        if (count($ref)!==2 || !in_array($ref[0],['company','advertiser'],true) || !HPADS_Core::get(absint($ref[1]),$ref[0]) || !self::author_owner($ref[0],absint($ref[1]))) { return HPADS_Core::error('Select an owner available for a property you manage.',403); }
        $ref=[$ref[0],absint($ref[1])];
        if (($r||$source) && $ref!==HPADS_Workflow::owner_ref($r?:$source) && !(HPADS_Workflow::production() || self::manager())) { return HPADS_Core::error('Owner changes require an authorized HWADS manager.',403); }
        [$type,$oid]=$ref; $rotation=null;
        if ($mode==='placement') {
            $rotation=HPADS_Core::get(absint($in['rotation_id'] ?? 0),'playlist');
            if (!self::can_bind($type,$oid,$rotation)) { return HPADS_Core::error('Choose a playlist at a location connected to this owner.',403); }
            if ($r && (int)($r['data']['rotation_id'] ?? 0)!==(int)$rotation['id']) { return HPADS_Core::error('This placement belongs to one playlist. Duplicate it to create an independent entry elsewhere.'); }
        }
        $date_method=!empty($in['_local_dates'])?'local_date':'date';$start=HPADS_Core::$date_method($in['start'] ?? ''); $end=HPADS_Core::$date_method($in['end'] ?? '');
        if (is_wp_error($start) || is_wp_error($end) || ($start && $end && $start>=$end)) { return HPADS_Core::error('Check the schedule: end must be after start.'); }
        $duration=isset($in['duration']) && $in['duration']!==''?(int)$in['duration']:null;
        if ($duration!==null && ($duration<1 || $duration>3600)) { return HPADS_Core::error('Image duration must be 1–3600 seconds, or empty for the location default.'); }
        $base=$r;
        if (!$base && $source) { $base=$source; $base['data']=self::fresh_data($source,$mode,$rotation); $base['data']['content_revision']=0; }
        $content_input=$in; $content_input['locations']=$rotation?[(int)$rotation['parent_id']]:[]; $content_input['target_mode']='selected';
        $d=HPADS_Ads::record($content_input,$base,$type,$oid,$content_input['locations']); if (is_wp_error($d)) { return $d; }
        if (!$r) { $d['content_revision']=1; }
        $d['record_type']=$mode; $d['rotation_id']=$rotation?(int)$rotation['id']:0; $d['locations']=$rotation?[(int)$rotation['parent_id']]:[];
        $d['start']=$mode==='placement'?$start:''; $d['end']=$mode==='placement'?$end:''; $d['duration']=$duration; $d['active']=$mode==='placement'?!empty($in['active']):true;
        $placement_type=sanitize_key($in['placement_type'] ?? 'advertising');
        if (!isset(self::TYPES[$placement_type])) { return HPADS_Core::error('Invalid placement type.'); }
        $d['placement_type']=$placement_type; $d['media_alt']=sanitize_text_field($in['media_alt'] ?? ''); $d['caption']=sanitize_textarea_field($in['caption'] ?? '');
        foreach (['transition_in','transition_out'] as $key) { $value=sanitize_key($in[$key] ?? 'none');if (!isset(self::TRANSITIONS[$value])) { return HPADS_Core::error('Invalid transition.'); }$d[$key]=$value; }
        $d['qr_enabled']=!empty($in['qr_enabled']); $d['description']=wp_kses_post($in['description'] ?? ''); $d['link_url']=esc_url_raw($in['link_url'] ?? '',['http','https']);
        if (!$r && $source) { $d['source_template_id']=(int)$source['id']; }
        return ['title'=>sanitize_text_field($in['title'] ?? ''),'parent'=>$type==='advertiser'?$oid:0,'data'=>$d];
    }
    static function save_playlist($r,$in) {
        global $wpdb;
        if (!HPADS_Core::can($r,true) || (int)($in['revision'] ?? 0)!==(int)$r['revision']) { return HPADS_Core::error('Playlist unavailable or changed. Reload before saving.'); }
        if ((int)($in['parent_id'] ?? 0)!==(int)$r['parent_id']) { return HPADS_Core::error('The playlist location cannot be changed.'); }
        $purpose=sanitize_key($in['purpose'] ?? 'primary');$candidate=$r;$candidate['data']['purpose']=$purpose;
        if (!in_array($purpose,['primary','house'],true) || !HPADS_Core::can($candidate,true)) { return HPADS_Core::error('You cannot use this playlist purpose.'); }
        $items=json_decode($in['items'] ?? '[]',true);if (!is_array($items) || count($items)>250) { return HPADS_Core::error('A playlist supports up to 250 placements.'); }
        $wpdb->query('START TRANSACTION');$seen=[];
        $rollback=function() use (&$seen,$wpdb) { $wpdb->query('ROLLBACK');foreach (array_keys($seen) as $id) { $pid=HPADS_Storage::post_id($id);if ($pid) { clean_post_cache($pid); } } };
        foreach ($items as $item) {
            $id=absint($item['creative_id'] ?? 0);$entry=HPADS_Core::get($id,'creative');
            if (!$entry || isset($seen[$id]) || ($entry['data']['record_type'] ?? '')!=='placement' || (int)($entry['data']['rotation_id'] ?? 0)!==(int)$r['id'] || !HPADS_Workflow::can_ad($entry,true)) { $rollback();return HPADS_Core::error('Choose independent placements belonging to this playlist that you can edit.'); }
            $seen[$id]=true;
            if ($purpose==='house' && HPADS_Workflow::owner_ref($entry)[0]!=='company') { $rollback();return HPADS_Core::error('Remove advertiser placements before changing to House / Placeholder.'); }
            if (isset($item['revision'])) {
                $input=array_merge($entry['data'],$item,['id'=>$id,'revision'=>(int)$item['revision'],'title'=>sanitize_text_field($item['title'] ?? $entry['title']),'record_type'=>'placement','owner'=>implode(':',HPADS_Workflow::owner_ref($entry)),'rotation_id'=>$r['id'],'_local_dates'=>1]);
                $saved=self::save($input);if (is_wp_error($saved)) { $rollback();return $saved; }
            }
        }
        $items=HPADS_Core::normalize_items($items,HPADS_Core::get($r['parent_id'],'location'),$r['id']);
        if (is_wp_error($items)) { $rollback();return $items; }
        $d=$r['data'];$d['items']=$items;$d['purpose']=$purpose;if (isset($in['description'])) { $d['description']=wp_kses_post($in['description']); }
        $saved=HPADS_Core::put('playlist',sanitize_text_field($in['title'] ?? $r['title']),$r['parent_id'],$d,$r['id'],$r['revision']);
        if (is_wp_error($saved)) { $rollback();return $saved; }$wpdb->query('COMMIT');return $saved;
    }
    static function save($in) {
        $id=absint($in['id'] ?? 0); $r=$id?HPADS_Core::get($id,'creative'):null;
        if ($id && (!$r || !HPADS_Workflow::can_ad($r,true))) { return HPADS_Core::error('Entry unavailable.',403); }
        if ($r && (int)($in['revision'] ?? 0)!==(int)$r['revision']) { return HPADS_Core::error('This entry changed. Reload before saving.'); }
        $title=sanitize_text_field($in['title'] ?? ''); if (!$title || strlen($title)>200) { return HPADS_Core::error('Enter a name of no more than 200 bytes.'); }
        $values=self::prepare($in,$r); if (is_wp_error($values)) { return $values; }
        $result=HPADS_Core::put('creative',$title,$values['parent'],$values['data'],$id,(int)($r['revision'] ?? 0));
        if (!is_wp_error($result)) { update_post_meta($values['data']['attachment_id'],'_hpads_advertiser_id',$values['parent']); }
        return $result;
    }
    static function page($templates=false) {
        if (!isset($_GET['edit'])) {
            if ($templates) { self::template_upload_panel(); }
            echo '<p>'.($templates?'Templates supply starting content only. Creating a placement copies the values into a new entry; later edits do not propagate.':'Each placement belongs to exactly one playlist. Duplicate it, or use a template, to create another independent entry.').'</p>';
            if (HPADS_Ads::owners(true)) { echo '<p><a class="page-title-action" href="'.esc_url(HPADS_Admin::url($templates?'templates':'creative',0)).'">Add New '.($templates?'Template':'Placement').'</a></p>'; }
            if (!$templates) { echo '<p class="description">Past/expired entries are hidden by default. Each placement is the billable booking record; templates are not billed.</p>'; }
            HPADS_Admin::listing('creative'); return;
        }
        self::editor(absint($_GET['edit']),$templates);
    }
    static function box($title,$content) { echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">'.esc_html($title).'</h2></div><div class="inside">'.$content.'</div></div>'; }
    static function editor($id,$templates) {
        $r=$id?HPADS_Core::get($id,'creative'):null;
        if ($id && (!$r || !HPADS_Core::can($r) || self::is_template($r)!==$templates)) { HPADS_Admin::fail('Entry unavailable.'); }
        if (!$id && !HPADS_Ads::owners(true)) { HPADS_Admin::fail('No owner team is available.'); }
        $source=null; if (!$id && !empty($_GET['source_template_id'])) { $source=self::source(absint($_GET['source_template_id'])); if (!$source) { HPADS_Admin::fail('Template unavailable.'); } }
        $d=$r['data'] ?? ($source?self::fresh_data($source,$templates?'template':'placement'):[]);
        $owners=HPADS_Ads::owners(true); $owner=($r||$source)?implode(':',HPADS_Workflow::owner_ref($r?:$source)):sanitize_text_field($_GET['owner'] ?? '');
        $context=!$r && !$templates?HPADS_Core::get(absint($_GET['rotation_id'] ?? 0),'playlist'):null;
        if ($context && !$source) { $compatible=[]; foreach ($owners as $key=>$label) { [$ot,$oi]=explode(':',$key); if (self::can_bind($ot,(int)$oi,$context)) { $compatible[$key]=$label; } } $owners=$compatible; if (!$owners) { HPADS_Admin::fail('No owner connected to this playlist is available to your account. Connect the advertiser to the venue, or use the venue as owner.'); } }
        if ($context && $source) { [$ot,$oi]=HPADS_Workflow::owner_ref($source); if (!self::can_bind($ot,$oi,$context)) { HPADS_Admin::fail('This template owner is not connected to the playlist location. Connect the advertiser to the venue first.'); } }
        if (!isset($owners[$owner]) && !$r) { $owner=array_key_first($owners) ?? ''; }
        if ($r && !isset($owners[$owner])) { $o=HPADS_Workflow::owner_record($r); $owners[$owner]=$o?$o['title']:'Unavailable owner'; }
        [$type,$oid]=array_pad(explode(':',$owner),2,0); $write=$r?HPADS_Workflow::can_ad($r,true):true;
        $rotation_id=(int)($d['rotation_id'] ?? 0); if (!$r) { $rotation_id=absint($_GET['rotation_id'] ?? $rotation_id); }
        echo '<p><a href="'.esc_url(HPADS_Admin::url($templates?'templates':'creative')).'">← All '.($templates?'My Ad Templates':'My Ad Placements').'</a></p>';
        if (!$id && !$templates) {
            echo '<label><strong>Start from a template</strong> <select id="hpads-template-picker"><option value="0">Blank placement</option>';
            foreach (HPADS_Core::all('creative') as $t) { if (self::is_template($t) && HPADS_Workflow::can_ad($t) && (!$context || self::can_bind(...array_merge(HPADS_Workflow::owner_ref($t),[$context])))) { echo '<option value="'.(int)$t['id'].'" '.selected((int)($source['id'] ?? 0),(int)$t['id'],false).'>'.esc_html($t['title']).'</option>'; } }
            echo '</select></label><p class="description">Selecting a template fills this new draft. Select it before editing the fields below.</p>';
        }
        HPADS_Admin::begin('creative',$r,'placement_record','hpads-placement-form');
        foreach (['record_type'=>$templates?'template':'placement','source_template_id'=>$source['id'] ?? ($d['source_template_id'] ?? 0)] as $name=>$value) { echo '<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr($value).'">'; }
        echo '<fieldset '.(!$write?'disabled':'').'><div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content"><div id="titlediv"><div id="titlewrap"><label class="screen-reader-text" for="title">Entry name</label><input id="title" name="title" type="text" value="'.esc_attr($r['title'] ?? ($source?$source['title']:'')).'" placeholder="'.($templates?'Template name':'Placement name').'" required></div></div>';
        $aid=(int)($d['attachment_id'] ?? 0); $url=$aid?wp_get_attachment_url($aid):''; $mime=$aid?get_post_mime_type($aid):'';
        $preview='<div id="hpads-media-preview">';
        if ($url) { $preview.=strpos((string)$mime,'video/')===0?'<video controls preload="metadata" src="'.esc_url($url).'"></video>':'<img src="'.esc_url($url).'" alt="'.esc_attr($d['media_alt'] ?? '').'">'; }
        else { $preview.='<p>Upload an image or video, or start from a template.</p>'; }
        $preview.='</div><p><label class="button" for="hpads-media-file">'.($aid?'Replace Media':'Add Media').'</label> <input id="hpads-media-file" type="file" name="media" '.(!$aid?'required ':'').'accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" data-max-bytes="'.esc_attr(wp_max_upload_size()).'"></p><p class="hpads-upload-size" aria-live="polite"></p><p class="description">Server upload maximum: '.esc_html(size_format(wp_max_upload_size())).'.</p>';
        self::box('Placement Media',$preview);
        echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">Description / supporting content</h2></div><div class="inside">';
        if ($write) { wp_editor($d['description'] ?? '','hpads_description',['textarea_name'=>'description','media_buttons'=>false,'textarea_rows'=>8,'teeny'=>true,'tinymce'=>true,'quicktags'=>true]); }
        else { echo wp_kses_post($d['description'] ?? ''); }
        echo '<p class="description">Stored with this entry. Public landing-page publishing is not enabled yet.</p></div></div></div><div id="postbox-container-1" class="postbox-container">';
        ob_start();
        echo '<p><strong>'.esc_html($templates?'Template':($r?HPADS_Workflow::summary($r):'Draft')).'</strong></p>';
        if ($r && !$templates && self::published_item($r['id'])) { echo '<p><a class="button" target="_blank" rel="noopener" href="'.esc_url(self::public_url($r['id'])).'">View Published Landing Page</a></p>'; }
        if ($r && !$templates) { echo '<p><a class="button" target="_blank" rel="noopener" href="'.esc_url(self::preview_url($r['id'])).'">Preview Landing Page</a></p>'; }
        if ($r) { echo '<p>Entry #'.(int)$r['id'].' · Revision '.(int)($d['content_revision'] ?? 1).'<br>Updated '.esc_html($r['updated_at']).' UTC</p>'; }
        if ($source) { echo '<p>Starting values copied from template #'.(int)$source['id'].'.</p>'; }
        if ($write) { submit_button($r?'Save Changes':'Save Draft','primary','submit',false); if ($r && !$templates && self::published_item($r['id'])) { echo '<p><a class="button" target="_blank" rel="noopener" href="'.esc_url(self::public_url($r['id'])).'">View Published Landing Page</a></p>'; }
        if ($r && !$templates) { echo '<p><a href="#hpads-save-template">Save as Template</a> · <a href="#hpads-duplicate">Duplicate Placement</a></p>'; } }
        echo '<p class="description">Publication is a separate step in its playlist. Approvals follow the mode selected in Settings.</p>';
        self::box('Save',ob_get_clean());
        ob_start();
        if ($r || $source) { echo '<p><strong>Owner</strong><br>'.esc_html($owners[$owner] ?? $owner).'</p><input type="hidden" name="owner" value="'.esc_attr($owner).'">'; }
        else { HPADS_Admin::select('Owner','owner',$owners,$owner); echo '<p class="description hpads-owner-status" role="status">Select the owner; eligible playlists update here without leaving this page.</p><button type="button" class="button hpads-refresh-owner" hidden>Retry Playlist Lookup</button>'; }
        HPADS_Admin::select('Display orientation','orientation',['auto'=>'Detect from file','horizontal'=>'Horizontal / landscape','vertical'=>'Vertical / portrait'],$d['orientation'] ?? 'auto');
        HPADS_Admin::select('Entrance transition','transition_in',self::TRANSITIONS,$d['transition_in'] ?? 'none');
        HPADS_Admin::select('Exit transition','transition_out',self::TRANSITIONS,$d['transition_out'] ?? 'none');
        HPADS_Admin::select('Placement type','placement_type',self::TYPES,$d['placement_type'] ?? 'advertising');
        echo '<p class="description">Content category only. This setting currently does not change the display style or schedule.</p>';
        if (!$templates) {
            if ($r) { $p=HPADS_Core::get($rotation_id,'playlist'); $l=$p?HPADS_Core::get($p['parent_id'],'location'):null; echo '<p><strong>Playlist</strong><br>'.esc_html($p?$p['title']:'Missing playlist').($l?'<br>'.esc_html($l['title']):'').'</p><input type="hidden" name="rotation_id" value="'.(int)$rotation_id.'"><p class="description">This destination is fixed. Use Duplicate for another playlist.</p>'; }
            else { HPADS_Admin::select('Playlist','rotation_id',self::rotations($type,(int)$oid),$rotation_id); }
        }
        self::box($templates?'Template defaults':'Owner & destination',ob_get_clean());
        ob_start();
        HPADS_Admin::field('Alternative text','media_alt',$d['media_alt'] ?? '');
        HPADS_Admin::field('Caption','caption',$d['caption'] ?? '');
        HPADS_Admin::check($templates?'Show QR Code by default on new placements':'Show QR Code','qr_enabled',$d['qr_enabled'] ?? false);
        echo '<p class="description">Controls HWADS-generated placement QR display when supported by the player/embed. Does not remove a QR already in uploaded artwork or affect the private device-settings QR.</p>';
        HPADS_Admin::field('External URL (optional)','link_url',$d['link_url'] ?? '','url','Saved with this entry. Tracked links and QR codes are not enabled yet.');
        if ($aid) { $meta=wp_get_attachment_metadata($aid) ?: []; $file=get_attached_file($aid); echo '<p><strong>Media information</strong><br>'.esc_html(basename((string)$file)).'<br>'.esc_html($mime).'<br>'.esc_html(($meta['width'] ?? '?').' × '.($meta['height'] ?? '?')).' pixels<br>'.esc_html($file && is_readable($file)?size_format(filesize($file)):'File unavailable').'<br>Attachment #'.(int)$aid.'</p>'; }
        self::box('Media information',ob_get_clean());
        ob_start();
        HPADS_Admin::field('Image duration override (seconds)','duration',$d['duration'] ?? '','number','Empty uses the location default. Videos play full length.');
        if (!$templates) { echo '<input type="hidden" name="_local_dates" value="1"><p>Schedule timezone: '.esc_html(wp_timezone_string()).'</p>';HPADS_Admin::field('Start','start',HPADS_Core::local_value($d['start'] ?? ''),'datetime-local'); HPADS_Admin::field('End','end',HPADS_Core::local_value($d['end'] ?? ''),'datetime-local'); HPADS_Admin::check('Active after approval and publication','active',$d['active'] ?? true); }
        self::box($templates?'Playback defaults':'Schedule & playback',ob_get_clean());
        if (!$templates) { self::box('Placement record','<p>This independent placement is the booking/billing unit. The template is not billed.</p><p class="description">Invoicing and fee collection are not enabled yet.</p>'); }
        if (!$templates) { self::box('Engagement','<p><strong>Not reporting yet</strong></p><p>Displays: TV · Website · Third party</p><p>QR scans: Not reporting<br>Landing-page visits: Not reporting</p><p class="description">Counts require events from each delivery source. A heartbeat is not a display. Engagement will be attached to this placement, not its template.</p>'); }
        if (!$templates) { self::box('Approval requirements','<p>Advertiser location eligibility is managed by the account manager—not checked by the ad owner.</p><p>Draft → Account manager → Venue → Publish rotation.</p>'); }
        echo '</div><div class="clear"></div></div></div></fieldset></form>';
        if ($r && ($write || ($templates && HPADS_Workflow::can_ad($r)))) { self::copy_controls($r); }
        if ($r && !$templates) { HPADS_Ads::controls($r); }
    }
    static function copy_controls($r) {
        [$type,$oid]=HPADS_Workflow::owner_ref($r);
        HPADS_Admin::begin('creative',$r,'copy_placement');
        echo '<h2 id="hpads-duplicate">'.(self::is_template($r)?'Create Placement from Template':'Duplicate Placement').'</h2><p>Creates a new draft with its own destination, content and reviews. No live connection is kept.</p>';
        HPADS_Admin::select('Destination playlist','destination_rotation',self::rotations($type,$oid),(int)($r['data']['rotation_id'] ?? 0));
        echo '<input type="hidden" name="copy_mode" value="placement">'; submit_button(self::is_template($r)?'Create New Placement':'Duplicate as New Placement','secondary','submit',false); echo '</form>';
        if (!self::is_template($r) && HPADS_Workflow::can_ad($r,true)) { HPADS_Admin::begin('creative',$r,'copy_placement'); echo '<input type="hidden" name="copy_mode" value="template"><h2 id="hpads-save-template">Save as Template</h2><p>Copies content and playback defaults. Rotation, schedule and approvals are not included.</p>'; submit_button('Save as New Template','secondary','submit',false); echo '</form>'; }
    }
    static function upgrade() {
        if (get_option('hpads_placement_schema')==='1') { return; }
        global $wpdb;
        // Each old playlist occurrence becomes a distinct entry. Old sources become templates.
        foreach (HPADS_Core::entity_ids('playlist') as $id) {
            $p=HPADS_Core::get($id,'playlist'); $pd=$p['data']; $changed=false;
            $wpdb->query('START TRANSACTION');
            foreach (($pd['items'] ?? []) as $index=>$item) {
                $source=HPADS_Core::get($item['creative_id'] ?? 0,'creative');
                if (!$source || !empty($source['data']['record_type'])) { continue; }
                $d=$source['data']; $d['record_type']='placement'; $d['rotation_id']=(int)$id; $d['locations']=[(int)$p['parent_id']]; $d['target_mode']='selected';
                $d['duration']=$item['duration'] ?? null; $d['start']=$item['start'] ?? ''; $d['end']=$item['end'] ?? ''; $d['active']=!empty($item['active']); $d['source_template_id']=(int)$source['id'];
                if (!empty($d['workflow'])) { $d['reviews']=array_intersect_key($d['reviews'] ?? [],[(string)$p['parent_id']=>true]); $d['approved_locations']=array_values(array_intersect($d['approved_locations'] ?? [],[(int)$p['parent_id']])); }
                $new=HPADS_Core::put('creative',$source['title'],$source['parent_id'],$d);
                if (is_wp_error($new)) { $wpdb->query('ROLLBACK'); return; }
                $pd['items'][$index]=['creative_id'=>$new,'active'=>$d['active'],'duration'=>$d['duration'],'start'=>$d['start'],'end'=>$d['end']]; $changed=true;
            }
            if ($changed && is_wp_error(HPADS_Core::put('playlist',$p['title'],$p['parent_id'],$pd,$id,(int)$p['revision']))) { $wpdb->query('ROLLBACK'); return; }
            $wpdb->query('COMMIT');
        }
        foreach (HPADS_Core::entity_ids('creative') as $id) {
            $r=HPADS_Core::get($id,'creative'); if (!empty($r['data']['record_type'])) { continue; }
            $d=$r['data']; $d['record_type']='template'; $d['rotation_id']=0; $d['state']='draft'; $d['locations']=[]; $d['approved_locations']=[]; $d['reviews']=[]; $d['manager_review']=[];
            if (is_wp_error(HPADS_Core::put('creative',$r['title'],$r['parent_id'],$d,$r['id'],(int)$r['revision']))) { return; }
        }
        update_option('hpads_placement_schema','1');
    }
}
