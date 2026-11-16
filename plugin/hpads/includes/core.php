<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Core {
    const KINDS = ['company', 'location', 'device', 'advertiser', 'creative', 'playlist'];
    const TYPES = ['mixed'=>'Mixed', 'ads'=>'Ads Only', 'menu'=>'Menu Only', 'house'=>'House Content', 'static'=>'Static', 'custom'=>'Custom'];
    static function table($versions = false) { global $wpdb; return $wpdb->prefix . ($versions ? 'hpads_versions' : 'hpads_entities'); }
    static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta('CREATE TABLE ' . self::table() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            kind varchar(20) NOT NULL,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(200) NOT NULL,
            data longtext NOT NULL,
            revision bigint(20) unsigned NOT NULL DEFAULT 1,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY kind_parent (kind,parent_id)
        ) ENGINE=InnoDB $c;");
        dbDelta('CREATE TABLE ' . self::table(true) . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            playlist_id bigint(20) unsigned NOT NULL,
            manifest longtext NOT NULL,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY playlist_id (playlist_id)
        ) ENGINE=InnoDB $c;");
        dbDelta('CREATE TABLE ' . $wpdb->prefix . "hpads_status (
            device_id bigint(20) unsigned NOT NULL,
            last_seen bigint(20) unsigned NOT NULL DEFAULT 0,
            payload text NOT NULL,
            PRIMARY KEY  (device_id)
        ) ENGINE=InnoDB $c;");
        foreach (['hpads_owner'=>'HWADS Owner', 'hpads_admin'=>'HWADS Admin / Account Manager', 'hpads_venue'=>'HWADS Venue Manager', 'hpads_advertiser'=>'HWADS Advertiser'] as $role=>$label) {
            add_role($role, $label, ['read'=>true, 'hpads_access'=>true, $role=>true]);
        }
        // The installing WP administrator remains an administrator and gains global HPADS ownership.
        $u = wp_get_current_user();
        if ($u->exists() && current_user_can('activate_plugins')) { $u->add_cap('hpads_owner'); $u->add_cap('hpads_access'); }
        add_option('hpads_settings', ['heartbeat'=>300]);
        update_option('hpads_schema', HPADS_VERSION);
        if (!(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table())) { update_option('hpads_cpt_id_floor',0,false);update_option('hpads_cpt_schema','1',false); }
    }
    static function boot() {
        add_action('admin_post_hpads_migrate',['HPADS_Post_Types','migrate']);
        add_action('admin_notices',['HPADS_Post_Types','notice']);
        HPADS_Workflow::upgrade();
        HPADS_Placements::upgrade();
        add_action('admin_init',['HPADS_Placements','upgrade_playlist_refs']);
        add_filter('wp_read_video_metadata', [__CLASS__,'video_rotation'], 10, 4);
        add_action('admin_post_hpads_ad_review_preview',['HPADS_Preview','ad_route']);
        add_action('admin_post_nopriv_hpads_ad_review_preview',['HPADS_Preview','ad_route']);
        add_action('admin_post_hpads_playlist_preview',['HPADS_Preview','route']);
        add_action('admin_post_nopriv_hpads_playlist_preview',['HPADS_Preview','route']);
        add_filter('ajax_query_attachments_args',['HPADS_Ads','media_query']);
        add_action('wp_ajax_hpads_template_upload',['HPADS_Placements','ajax_template']);
        add_action('template_redirect',['HPADS_Placements','public_page']);
        add_action('template_redirect',['HPADS_Placements','front_preview'],0);
        add_action('admin_post_hpads_landing_preview',['HPADS_Placements','preview']);
        add_action('admin_post_nopriv_hpads_landing_preview',['HPADS_Placements','preview']);
        add_action('wp_ajax_hpads_playlist_create', ['HPADS_Placements','ajax_create']);
        add_action('wp_ajax_hpads_owner_playlists', ['HPADS_Placements','ajax_owner_playlists']);
        add_action('admin_menu', ['HPADS_Admin','menu']);
        add_action('admin_menu',['HPADS_Admin_Access','menus'],999);
        add_action('admin_init',['HPADS_Admin_Access','guard'],20);
        add_action('admin_bar_menu',['HPADS_Admin_Access','toolbar'],999);
        add_filter('login_redirect',['HPADS_Admin_Access','login'],10,3);
        add_action('admin_enqueue_scripts', ['HPADS_Admin','assets']);
        add_action('admin_post_hpads_device_settings', ['HPADS_Device_QR','route']);
        add_action('admin_post_nopriv_hpads_device_settings', ['HPADS_Device_QR','route']);
        add_action('admin_post_hpads_save', ['HPADS_Admin','save']);
        add_action('rest_api_init', ['HPADS_API','routes']);
        add_filter('rest_post_dispatch', ['HPADS_API','no_cache'], 10, 3);
    }
    static function owner() { return current_user_can('hpads_owner'); }
    static function staff() { return HPADS_Workflow::global_staff() || current_user_can('hpads_admin') || current_user_can('hpads_account_manager'); }
    static function ids($value) {
        if (!is_array($value)) { $value = preg_split('/[\s,]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY); }
        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }
    static function assigned($type) { return HPADS_View_As::assignments($type,self::ids(get_user_meta(get_current_user_id(), 'hpads_' . $type, true))); }
    static function get($id, $kind = '') {
        if (HPADS_Storage::active()) { return HPADS_Storage::get($id,$kind); }
        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $id), ARRAY_A);
        if (!$r || ($kind && $r['kind'] !== $kind)) { return null; }
        $r['data'] = json_decode($r['data'], true) ?: [];
        return $r;
    }
    static function entity_ids($kind) {
        if (HPADS_Storage::active()) { return HPADS_Storage::ids($kind); }
        global $wpdb;return $wpdb->get_col($wpdb->prepare('SELECT id FROM '.self::table().' WHERE kind=%s ORDER BY title,id',$kind));
    }
    static function all($kind) {
        global $wpdb;
        $rows=array_map(function($id){return ['id'=>$id];},self::entity_ids($kind));
        $out = [];
        foreach ($rows as $row) { $r=self::get($row['id']); if (self::can($r)) { $out[]=$r; } }
        return $out;
    }
    static function company($r) {
        if (!$r) { return 0; }
        if ($r['kind']==='company') { return (int)$r['id']; }
        if ($r['kind']==='location') { return (int)$r['parent_id']; }
        if (in_array($r['kind'], ['device','playlist'], true)) { $l=self::get($r['parent_id'],'location'); return $l ? (int)$l['parent_id'] : 0; }
        return 0;
    }
    static function can($r, $write = false) {
        if (!$r || !current_user_can('hpads_access')) { return false; }
        if ($r['kind']==='creative' && !empty($r['data']['workflow'])) { return HPADS_Workflow::can_ad($r,$write); }
        if (HPADS_Content_Access::designer()) {
            if ($write) { return false; }
            if (in_array($r['kind'],['company','advertiser'],true)) { return HPADS_Content_Access::owner_scope($r['kind'],$r['id']); }
            if ($r['kind']==='location') { return HPADS_Content_Access::location_scope($r['id']); }
            if ($r['kind']==='playlist') { return HPADS_Content_Access::location_scope($r['parent_id']); }
            return false;
        }
        if (HPADS_Workflow::global_staff()) {
            if (!$write) { return true; }
            if (HPADS_Workflow::global_admin()) { return true; }
            if (current_user_can('hpads_producer')) { return in_array($r['kind'],['creative','playlist'],true); }
            if (current_user_can('hpads_sales')) { return in_array($r['kind'],['company','advertiser'],true); }
        }
        if ($r['kind']==='company' && HPADS_Workflow::venue_admin() && in_array((int)$r['id'],self::assigned('companies'),true)) { return true; }
        if (!$write && $r['kind']==='advertiser' && HPADS_Placements::manager() && HPADS_Placements::author_owner('advertiser',$r['id'])) { return true; }
        if ($r['kind']==='advertiser' && HPADS_Workflow::advertiser_member() && in_array((int)$r['id'],self::assigned('advertisers'),true)) { return !$write || HPADS_Workflow::advertiser_admin(); }
        if (!$write && $r['kind']==='location' && HPADS_Workflow::advertiser_member()) {
            foreach (self::assigned('advertisers') as $aid) { $a=self::get($aid,'advertiser'); if ($a && in_array((int)$r['parent_id'],$a['data']['venues'] ?? [],true) && (HPADS_Workflow::automatic() || in_array((int)$r['id'],$a['data']['approved_placements'] ?? [],true))) { return true; } }
        }
        $staff=current_user_can('hpads_admin') || current_user_can('hpads_account_manager'); $venue=HPADS_Workflow::venue_member();
        if ($r['kind']==='advertiser' || ($r['kind']==='creative' && !empty($r['parent_id']))) {
            $aid=$r['kind']==='advertiser' ? (int)$r['id'] : (int)$r['parent_id'];
            if (in_array($aid,self::assigned('advertisers'),true)) { return !$write || $staff; }
            if ($r['kind']==='creative' && !$write && ($venue || $staff)) {
                foreach (($r['data']['locations'] ?? []) as $lid) { if (self::can(self::get($lid,'location'))) { return true; } }
            }
            return false;
        }
        if ($r['kind']==='creative') {
            $l=self::get($r['data']['location_id'] ?? 0,'location');
            return $l && self::can($l) && (!$write || $staff || ($venue && !empty($l['data']['allow_house'])));
        }
        $cid=self::company($r);
        if (!$cid || !in_array($cid, self::assigned('companies'), true) || (!$staff && !$venue)) { return false; }
        if (!$write) { return true; }
        if ($staff) { return true; }
        if ($r['kind']==='playlist' && ($r['data']['purpose'] ?? '')==='house') {
            $l=self::get($r['parent_id'],'location'); return HPADS_Workflow::venue_admin() && !empty($l['data']['allow_house']);
        }
        return false;
    }
    static function error($message, $status=400) { return new WP_Error('hpads_error',$message,['status'=>$status]); }
    static function put($kind,$title,$parent,$data,$id=0,$revision=0) {
        global $wpdb;
        if (!HPADS_Storage::active() && get_option('hpads_cpt_migrating')) { return self::error('The post-type migration is running. Retry after it finishes.'); }
        if (HPADS_Storage::active()) { return HPADS_Storage::put($kind,$title,$parent,$data,$id,$revision); }
        $values=['title'=>$title,'parent_id'=>$parent,'data'=>wp_json_encode($data),'updated_at'=>gmdate('Y-m-d H:i:s')];
        if ($id) {
            $values['revision']=$revision+1;
            $ok=$wpdb->update(self::table(),$values,['id'=>$id,'revision'=>$revision]);
            if ($ok !== 1) { return self::error('This record changed in another tab. Reload before saving.'); }
            return $id;
        }
        $values['kind']=$kind;
        if (!$wpdb->insert(self::table(),$values)) { return self::error('Database insert failed.'); }
        return (int)$wpdb->insert_id;
    }
    // Display only: storage and native date inputs retain their machine formats.
    static function display_datetime($value,$zone=false) {
        $stamp=is_numeric($value)?(int)$value:strtotime($value.' UTC');
        if ($stamp===false) { return ''; }
        $format=get_option('date_format','F j, Y').' '.get_option('time_format','g:i a');
        if ($zone && !preg_match('/(?<!\\\\)[TeOPZ]/',$format)) { $format.=' T'; }
        return wp_date($format,$stamp);
    }
    static function local_date($v) {
        if (trim((string)$v)==='') { return ''; }
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)$v,wp_timezone());
        if (!$d || $d->format('Y-m-d\TH:i')!==$v) { return self::error('Enter a valid date and time in the WordPress timezone.'); }
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i');
    }
    static function local_value($v) { return $v?(new DateTimeImmutable($v,new DateTimeZone('UTC')))->setTimezone(wp_timezone())->format('Y-m-d\TH:i'):''; }
    static function date($v) {
        $v=trim((string)$v);
        if (!$v) { return ''; }
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v, new DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d\TH:i')!==$v) { return self::error('Enter a valid schedule date and time.'); }
        return $v;
    }
    static function inherited_duration($ad) {
        $playlist=self::get($ad['data']['rotation_id'] ?? 0,'playlist');
        $location=$playlist?self::get($playlist['parent_id'],'location'):null;
        return (int)($location['data']['duration'] ?? 10);
    }
    static function normalize_items($items,$location,$rotation_id=0,$resolved=false) {
        if (!is_array($items) || count($items)>HPADS_Playlist_Limits::TOTAL) { return self::error('A playlist supports up to 100 placements, including inactive placements.'); }
        $out=[]; $seen=[];
        foreach ($items as $item) {
            if (!is_array($item)) { return self::error('Invalid playlist item.'); }
            $c=self::get(absint($item['creative_id'] ?? 0),'creative');
            if (!$c || !self::can($c)) { return self::error('An item references unavailable media.'); }
            if (($c['data']['record_type'] ?? '')==='template') { return self::error('Templates cannot run directly. Create an independent placement in this rotation first.'); }
            if (($c['data']['record_type'] ?? '')==='placement') {
                if ((int)($c['data']['rotation_id'] ?? 0)!==(int)$rotation_id || !$rotation_id) { return self::error('This placement belongs to another rotation. Duplicate it instead of reusing the same entry.'); }
                if (isset($seen[$c['id']])) { return self::error('Each rotation entry must be unique. Duplicate the placement for another slot.'); }
                $item['duration']=$c['data']['duration'] ?? null; $item['start']=$c['data']['start'] ?? ''; $item['end']=$c['data']['end'] ?? ''; $item['active']=!empty($c['data']['active']);
            }
            $seen[$c['id']]=true;
            if (!empty($c['data']['workflow']) && !in_array((int)$location['id'],$c['data']['locations'] ?? [],true)) { return self::error('Ad does not target this location.'); }
            if (empty($c['data']['workflow']) && !empty($c['parent_id']) && !in_array((int)$location['id'],$c['data']['locations'] ?? [],true)) { return self::error('Advertiser media must target this location.'); }
            if (empty($c['data']['workflow']) && empty($c['parent_id']) && (int)($c['data']['location_id'] ?? 0)!==(int)$location['id']) { return self::error('House media belongs to another location.'); }
            $start=self::date($item['start'] ?? ''); $end=self::date($item['end'] ?? '');
            if (is_wp_error($start) || is_wp_error($end) || ($start && $end && $start >= $end)) { return self::error('Check item dates: end must be after start.'); }
            $duration=isset($item['duration']) && $item['duration']!=='' ? (int)$item['duration'] : null;
            if ($duration!==null && ($duration<1 || $duration>3600)) { return self::error('Image duration must be 1–3600 seconds, or empty for the location default.'); }
            $out[]=!$resolved && ($c['data']['record_type'] ?? '')==='placement'?['creative_id'=>(int)$c['id']]:['creative_id'=>(int)$c['id'],'duration'=>$duration,'active'=>!empty($item['active']),'start'=>$start,'end'=>$end];
        }
        return $out;
    }
    static function publish($id) { return HPADS_Playlist_Limits::locked($id,function() use($id){return self::publish_locked($id);}); }
    private static function publish_locked($id) {
        global $wpdb;
        $p=HPADS_Playlist_Limits::fresh($id,'playlist');
        if (!$p || !self::can($p,true)) { return self::error('You cannot publish this playlist.',403); }
        $l=self::get($p['parent_id'],'location');
        if (!$l) { return self::error('Location is missing.'); }
        $items=self::normalize_items($p['data']['items'] ?? [],$l,$p['id'],true);
        if (is_wp_error($items)) { return $items; }
        $limits=HPADS_Playlist_Limits::validate($p,$items);if (is_wp_error($limits)) { return $limits; }
        $manifest=['schema'=>1,'playlist_id'=>(int)$id,'location_id'=>(int)$l['id'],'purpose'=>$p['data']['purpose'],'published_at'=>gmdate('c'),'timezone'=>'UTC','site_timezone'=>wp_timezone_string(),'items'=>[]];
        foreach ($items as $item) {
            if (!$item['active']) { continue; }
            $c=self::get($item['creative_id'],'creative');
            if (!empty($c['data']['workflow']) && !HPADS_Workflow::approved($c,(int)$l['id'])) { return self::error('Ad #'.$c['id'].' needs approval for this location and revision before publishing.'); }
            if (empty($c['data']['workflow']) && !empty($c['parent_id']) && !empty($l['data']['approval_required']) && !in_array((int)$l['id'],$c['data']['approved_locations'] ?? [],true)) { return self::error('Advertiser media #'.$c['id'].' needs location approval before publishing.'); }
            if (!in_array($c['data']['orientation'] ?? '',['horizontal','vertical'],true)) { return self::error('Media needs an explicit orientation before publishing.'); }
            $aid=$c['data']['attachment_id'];
            $geometry=self::geometry($aid,$c['data']['orientation']);
            if (is_wp_error($geometry)) { return $geometry; }
            $path=get_attached_file($aid); $mime=get_post_mime_type($aid); $url=wp_get_attachment_url($aid);
            if (!$path || !is_readable($path) || !$url || !in_array($mime,['image/jpeg','image/png','image/webp','video/mp4','video/webm'],true)) { return self::error('Media #'.$c['id'].' is missing, offloaded, or unsupported. Re-upload a local supported file.'); }
            $hash=hash_file('sha256',$path);
            if (!$hash) { return self::error('Unable to checksum media.'); }
            $url=self::snapshot($path,$mime,$hash);
            if (is_wp_error($url)) { return $url; }
            $visuals=HPADS_Visuals::payload($c['data'],true);if (is_wp_error($visuals)) { return $visuals; }
            $video=strpos($mime,'video/')===0;
            $manifest['items'][]=array_merge($visuals,['creative_id'=>(int)$c['id'],'placement_id'=>($c['data']['record_type'] ?? '')==='placement'?(int)$c['id']:null,'placement_type'=>$c['data']['placement_type'] ?? null,'placement_revision'=>$c['data']['content_revision'] ?? null,'qr_enabled'=>!empty($c['data']['qr_enabled']),'transition_in'=>$c['data']['transition_in'] ?? 'none','transition_out'=>$c['data']['transition_out'] ?? 'none','attachment_id'=>(int)$aid,'title'=>$c['title'],'landing'=>['custom_layout'=>!empty($c['data']['landing_layout_custom']),'main_text'=>$c['data']['landing_text'] ?? (get_post_meta(HPADS_Storage::post_id($c['id']),'_et_pb_use_builder',true)==='on'?'':($c['data']['description'] ?? '')),'show_ad'=>$c['data']['show_ad_on_page'] ?? true,'show_title'=>$c['data']['show_page_title'] ?? true,'divi_builder'=>HPADS_Divi::custom_layout($c['id']),'alt'=>$c['data']['media_alt'] ?? '', 'caption'=>$c['data']['caption'] ?? '', 'description'=>$c['data']['description'] ?? '', 'external_url'=>$c['data']['link_url'] ?? ''],'orientation'=>$c['data']['orientation'],'width'=>$geometry['width'],'height'=>$geometry['height'],'url'=>$url,'sha256'=>$hash,'bytes'=>filesize($path),'mime'=>$mime,'playback'=>$video?'full':'timed','duration_seconds'=>$video?null:($item['duration'] ?? (int)($l['data']['duration'] ?? 10)),'start_utc'=>$item['start'] ? $item['start'].':00Z' : null,'end_utc'=>$item['end'] ? $item['end'].':00Z' : null]);
        }
        // Insert a frozen snapshot, then compare-and-swap the current pointer. A competing edit rolls back both.
        $wpdb->query('START TRANSACTION');
        $ok=$wpdb->insert(self::table(true),['playlist_id'=>$id,'manifest'=>wp_json_encode($manifest),'created_at'=>gmdate('Y-m-d H:i:s'),'created_by'=>get_current_user_id()]);
        if (!$ok) { $wpdb->query('ROLLBACK'); return self::error('Could not save the version.'); }
        $version=(int)$wpdb->insert_id;
        $d=$p['data']; $d['published_version']=$version; $d['published_revision']=(int)$p['revision'];
        $result=self::put('playlist',$p['title'],$p['parent_id'],$d,$id,(int)$p['revision']);
        if (is_wp_error($result)) { $wpdb->query('ROLLBACK'); return $result; }
        $wpdb->query('COMMIT');
        if (HPADS_Storage::active()) { HPADS_Storage::mark_published($p['id']);foreach ($manifest['items'] as $entry) { HPADS_Storage::mark_published($entry['creative_id']); } }
        return $version;
    }
    static function video_rotation($metadata,$file,$format,$raw) {
        if (isset($raw['video']['rotate'])) { $metadata['_hpads_rotation']=(int)$raw['video']['rotate']; }
        return $metadata;
    }
    static function geometry($aid,$requested='auto') {
        if (!in_array($requested,['auto','horizontal','vertical'],true)) { return self::error('Invalid creative orientation.'); }
        $m=wp_get_attachment_metadata($aid) ?: [];
        $w=(int)($m['width'] ?? 0); $h=(int)($m['height'] ?? 0);
        // WordPress exposes QuickTime rotation; display dimensions swap at 90/270 degrees.
        $rotation=abs((int)($m['_hpads_rotation'] ?? ($m['rotate'] ?? 0))) % 180;
        if ($rotation===90) { [$w,$h]=[$h,$w]; }
        $detected=$w>0 && $h>0 && $w!==$h ? ($w>$h?'horizontal':'vertical') : '';
        if ($detected && $requested!=='auto' && $requested!==$detected) { return self::error('Selected orientation conflicts with file dimensions.'); }
        $orientation=$detected ?: ($requested==='auto'?'':$requested);
        if (!$orientation) { return self::error('Cannot detect orientation. Choose Horizontal or Vertical for square/unreadable media.'); }
        return ['orientation'=>$orientation,'width'=>$w ?: null,'height'=>$h ?: null];
    }
    static function snapshot($path,$mime,$hash) {
        $u=wp_upload_dir();
        if (!empty($u['error'])) { return self::error('Uploads directory is unavailable.'); }
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm'][$mime];
        $dir=$u['basedir'].'/hpads-snapshots';
        if (!wp_mkdir_p($dir)) { return self::error('Cannot create immutable media directory.'); }
        $dest=$dir.'/'.$hash.'.'.$ext;
        if (!file_exists($dest)) {
            $tmp=$dest.'.'.wp_generate_password(12,false,false).'.tmp';
            if (!copy($path,$tmp) || hash_file('sha256',$tmp)!==$hash || !rename($tmp,$dest)) {
                if (file_exists($tmp)) { wp_delete_file($tmp); }
                return self::error('Cannot freeze media for this publication.');
            }
        }
        if (hash_file('sha256',$dest)!==$hash) { return self::error('Snapshot checksum mismatch.'); }
        return $u['baseurl'].'/hpads-snapshots/'.$hash.'.'.$ext;
    }
    static function version($id) {
        global $wpdb;
        $v=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table(true).' WHERE id=%d',$id),ARRAY_A);
        if (!$v) { return null; }
        $m=json_decode($v['manifest'],true); $m['version_id']=(int)$v['id']; return $m;
    }
    static function selected($device,$include_mix=false) {
        $l=self::get($device['parent_id'],'location'); if (!$l) { return []; }
        $d=$device['data']; $out=[];
        foreach (['primary','house'] as $slot) {
            $pid=(int)($d[$slot.'_playlist'] ?? 0);
            if (!$pid) { $pid=(int)($l['data'][$slot.'_playlist'] ?? 0); }
            $p=self::get($pid,'playlist');
            $purpose=$slot==='house'?'house':'primary';
            $published=$p && !empty($p['data']['published_version'])?self::version($p['data']['published_version']):null;
            if ($p && (int)$p['parent_id']===(int)$l['id'] && $published && ($published['purpose'] ?? '')===$purpose) { $out[$slot]=(int)$p['data']['published_version']; }
        }
        if ($include_mix) {
            $mix=HPADS_Playback_Mix::payload($device,$l);
            foreach ($mix['groups'] ?? [] as $group) { if (!empty($group['manifest']['version_id'])) { $out['mix:'.$group['playlist_id']]=$group['manifest']['version_id']; } }
            if (!empty($mix['fallback']['version_id'])) { $out['mix:fallback']=$mix['fallback']['version_id']; }
        }
        return $out;
    }
    static function interval() { $s=get_option('hpads_settings',[]); return max(60,min(3600,(int)($s['heartbeat'] ?? 300))); }
}
