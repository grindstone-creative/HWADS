<?php
if (!defined('ABSPATH')) { exit; }
/** Screen playback accounting. Independent of publication, heartbeats and updater commands. */
final class HPADS_Analytics {
    const SCHEMA='2';
    const RETENTION_DAYS=90;
    static function table() { global $wpdb;return $wpdb->prefix.'hpads_plays'; }
    static function daily_table() { global $wpdb;return $wpdb->prefix.'hpads_play_totals'; }
    static function init() {
        add_action('hwads_analytics_cleanup',[__CLASS__,'cleanup']);
        add_action('hwads_analytics_cleanup_batch',[__CLASS__,'cleanup']);
        add_action('init',[__CLASS__,'install'],30);
        add_action('rest_api_init',function() {
            register_rest_route('hpads/v1','/devices/(?P<id>\d+)/events',['methods'=>'POST','callback'=>[__CLASS__,'ingest'],'permission_callback'=>['HPADS_API','auth']]);
        });
        add_action('admin_menu',function() { if (!HPADS_Admin::can_section('engagement')) { return; }add_submenu_page('hpads-dashboard','Engagement','Engagement','hpads_access','hwads-engagement',[__CLASS__,'page']); },20);
        add_action('admin_menu',function(){add_submenu_page(null,'Pending playback records','Pending playback records','hpads_access','hwads-pending-playback',[__CLASS__,'queue_page']);},20);
        add_action('admin_post_hwads_queue_snapshot',[__CLASS__,'request_queue']);
        add_action('admin_post_hwads_queue_purge',[__CLASS__,'request_purge']);
        add_action('add_meta_boxes',function($type) {
            if (in_array($type,['hpads_placement','hpads_playlist'],true)) { add_meta_box('hwads-engagement','Screen Engagement',[__CLASS__,'box'],$type,'normal','low'); }
        });
    }
    static function install() {
        if (!wp_next_scheduled('hwads_analytics_cleanup')) { wp_schedule_event(time()+3600,'daily','hwads_analytics_cleanup'); }
        if (get_option('hwads_analytics_schema')===self::SCHEMA) { return; }
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();
        dbDelta('CREATE TABLE '.self::table()." (
            device_id bigint(20) unsigned NOT NULL,
            play_id varchar(36) NOT NULL,
            placement_id bigint(20) unsigned NOT NULL,
            playlist_id bigint(20) unsigned NOT NULL,
            version_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned NOT NULL,
            venue_id bigint(20) unsigned NOT NULL,
            started_at datetime NOT NULL,
            received_at datetime NOT NULL,
            report_day date NOT NULL,
            seq bigint(20) unsigned NOT NULL,
            elapsed_ms bigint(20) unsigned NOT NULL DEFAULT 0,
            outcome varchar(16) NOT NULL,
            engine varchar(12) NOT NULL,
            PRIMARY KEY  (device_id,play_id),
            KEY placement_date (placement_id,started_at),
            KEY playlist_date (playlist_id,started_at),
            KEY date_device (started_at,device_id)
        ) ENGINE=InnoDB $c;");
        dbDelta('CREATE TABLE '.self::daily_table()." (
            report_day date NOT NULL,
            placement_id bigint(20) unsigned NOT NULL,
            playlist_id bigint(20) unsigned NOT NULL,
            device_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned NOT NULL,
            venue_id bigint(20) unsigned NOT NULL,
            starts bigint(20) unsigned NOT NULL DEFAULT 0,
            completed bigint(20) unsigned NOT NULL DEFAULT 0,
            elapsed_ms bigint(20) unsigned NOT NULL DEFAULT 0,
            last_received datetime NOT NULL,
            PRIMARY KEY  (report_day,placement_id,playlist_id,device_id,location_id,venue_id),
            KEY placement_day (placement_id,report_day),
            KEY playlist_day (playlist_id,report_day)
        ) ENGINE=InnoDB $c;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',self::table()))===self::table() && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',self::daily_table()))===self::daily_table()) { update_option('hwads_analytics_schema',self::SCHEMA,false); }
    }
    static function cleanup() {
        global $wpdb;
        // Old replays are refused before insertion, so pruning cannot make them count twice.
        $removed=$wpdb->query($wpdb->prepare('DELETE FROM '.self::table().' WHERE started_at<%s LIMIT 5000',gmdate('Y-m-d H:i:s',time()-self::RETENTION_DAYS*DAY_IN_SECONDS)));
        if ($removed===5000 && !wp_next_scheduled('hwads_analytics_cleanup_batch')) { wp_schedule_single_event(time()+60,'hwads_analytics_cleanup_batch'); }
    }
    static function grant($device,$location,$version) {
        $manifest=HPADS_Core::version($version);$issued=strtotime($manifest['published_at'] ?? '') ?: time();
        $body=rtrim(strtr(base64_encode(wp_json_encode(['device'=>(int)$device['id'],'location'=>(int)$location['id'],'venue'=>(int)$location['parent_id'],'version'=>(int)$version,'issued'=>$issued])),'+/','-_'),'=');
        return $body.'.'.hash_hmac('sha256',$body,wp_salt('auth'));
    }
    static function attribution($token,$device,$version) {
        $parts=explode('.',(string)$token);
        if (count($parts)!==2 || strlen($token)>1024 || !hash_equals(hash_hmac('sha256',$parts[0],wp_salt('auth')),$parts[1])) { return null; }
        $a=json_decode((string)base64_decode(strtr($parts[0],'-_','+/'),true),true);
        return is_array($a) && ($a['device'] ?? 0)===(int)$device && ($a['version'] ?? 0)===(int)$version && !empty($a['location']) && !empty($a['venue']) && !empty($a['issued'])?$a:null;
    }
    static function observation($p,$device) {
        if (!is_array($p)) { return new WP_Error('invalid','Invalid playback observation.'); }
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',(string)($p['play_id'] ?? ''))) { return new WP_Error('invalid','Invalid play ID.'); }
        foreach (['placement_id','version_id','seq','elapsed_ms'] as $key) {
            if (!isset($p[$key]) || !is_int($p[$key]) || $p[$key]<($key==='elapsed_ms'?0:1)) { return new WP_Error('invalid','Invalid playback number.'); }
        }
        if ($p['elapsed_ms']>86400000 || $p['seq']>100000 || !in_array($p['outcome'] ?? '',['playing','completed','interrupted','error'],true) || !in_array($p['engine'] ?? '',['browser','mpv'],true)) { return new WP_Error('invalid','Invalid playback result.'); }
        if (!is_string($p['grant'] ?? null)) { return new WP_Error('attribution','Missing attribution.'); }
        $a=self::attribution($p['grant'],$device,$p['version_id']);
        if (!$a) { return new WP_Error('attribution','Attribution is invalid; resync after server salt changes.'); }
        $stamp=$p['started_at'] ?? null;
        if ((!is_int($stamp) && !is_float($stamp)) || !is_finite((float)$stamp) || $stamp<max(1577836800,$a['issued']-300) || $stamp>time()+300 || $p['elapsed_ms']>(time()-(float)$stamp+300)*1000) { return new WP_Error('clock','Playback clock is outside allowed limits.'); }
        if ($stamp<time()-self::RETENTION_DAYS*DAY_IN_SECONDS) { return new WP_Error('too_old','Playback is older than the 90-day replay window.'); }
        $m=HPADS_Core::version($p['version_id']);$item=null;
        foreach (($m['items'] ?? []) as $candidate) { if ((int)($candidate['placement_id'] ?? 0)===$p['placement_id']) { $item=$candidate;break; } }
        if (!$item) { return new WP_Error('placement','Placement is absent from the published version.'); }
        $r=HPADS_Core::get($p['placement_id'],'creative');if ($r && HPADS_Placements::is_template($r)) { return new WP_Error('template','Templates cannot own playback.'); }
        $image=strpos($item['mime'] ?? '','video/')!==0;$expected=(int)round((float)($item['duration_seconds'] ?? 0)*1000);
        if ($image && ($p['elapsed_ms']>$expected+1000 || ($p['outcome']==='completed' && $p['elapsed_ms']<$expected))) { return new WP_Error('duration','Image duration does not match the published version.'); }
        return ['device_id'=>(int)$device,'play_id'=>$p['play_id'],'placement_id'=>$p['placement_id'],'playlist_id'=>(int)$m['playlist_id'],'version_id'=>$p['version_id'],'location_id'=>(int)$a['location'],'venue_id'=>(int)$a['venue'],'started_at'=>gmdate('Y-m-d H:i:s',(int)$stamp),'received_at'=>gmdate('Y-m-d H:i:s'),'report_day'=>wp_date('Y-m-d',(int)$stamp),'seq'=>$p['seq'],'elapsed_ms'=>$p['elapsed_ms'],'outcome'=>$p['outcome'],'engine'=>$p['engine']];
    }
    static function store($row) {
        global $wpdb;$t=self::table();
        if ($wpdb->query('START TRANSACTION')===false) { return false; }
        // Establish the row, then lock it: racing retries must never add starts or elapsed time.
        $seed=$row;$seed['seq']=0;$seed['elapsed_ms']=0;$seed['outcome']='playing';
        $sql=$wpdb->prepare("INSERT INTO $t (device_id,play_id,placement_id,playlist_id,version_id,location_id,venue_id,started_at,received_at,report_day,seq,elapsed_ms,outcome,engine) VALUES (%d,%s,%d,%d,%d,%d,%d,%s,%s,%s,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE play_id=VALUES(play_id)",array_values($seed));
        if ($wpdb->query($sql)===false) { $wpdb->query('ROLLBACK');return false; }
        $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE device_id=%d AND play_id=%s FOR UPDATE",$row['device_id'],$row['play_id']),ARRAY_A);
        if (!$old) { $wpdb->query('ROLLBACK');return false; }
        foreach (['placement_id','playlist_id','version_id','location_id','venue_id','started_at','engine'] as $key) {
            if ((string)$old[$key] !== (string)$row[$key]) { $wpdb->query('ROLLBACK');return new WP_Error('identity','Play identity cannot change.'); }
        }
        if ((int)$old['seq']===$row['seq'] && ((int)$old['elapsed_ms']!==$row['elapsed_ms'] || $old['outcome']!==$row['outcome'])) { $wpdb->query('ROLLBACK');return new WP_Error('conflict','Same checkpoint cannot have different values.'); }
        if ((int)$old['seq']<$row['seq']) {
            if ($old['outcome']!=='playing' || $row['elapsed_ms']<(int)$old['elapsed_ms']) { $wpdb->query('ROLLBACK');return new WP_Error('sequence','Playback cannot regress or change after finishing.'); }
            $ok=$wpdb->update($t,array_intersect_key($row,array_flip(['received_at','seq','elapsed_ms','outcome'])),['device_id'=>$row['device_id'],'play_id'=>$row['play_id']]);
            if ($ok===false) { $wpdb->query('ROLLBACK');return false; }
            $daily=self::daily_table();$delta=$row['elapsed_ms']-(int)$old['elapsed_ms'];$starts=(int)$old['seq']===0?1:0;$complete=$row['outcome']==='completed'?1:0;
            $ok=$wpdb->query($wpdb->prepare("INSERT INTO $daily (report_day,placement_id,playlist_id,device_id,location_id,venue_id,starts,completed,elapsed_ms,last_received) VALUES (%s,%d,%d,%d,%d,%d,%d,%d,%d,%s) ON DUPLICATE KEY UPDATE starts=starts+VALUES(starts),completed=completed+VALUES(completed),elapsed_ms=elapsed_ms+VALUES(elapsed_ms),last_received=GREATEST(last_received,VALUES(last_received))",$old['report_day'],$row['placement_id'],$row['playlist_id'],$row['device_id'],$row['location_id'],$row['venue_id'],$starts,$complete,$delta,$row['received_at']));
            if ($ok===false) { $wpdb->query('ROLLBACK');return false; }
        }
        if ($wpdb->query('COMMIT')===false) { $wpdb->query('ROLLBACK');return false; }
        return true;
    }
    static function ingest($request) {
        if (strlen($request->get_body())>262144) { return HPADS_Core::error('Analytics batch exceeds 256 KB.',413); }
        $body=$request->get_json_params();$plays=$body['plays'] ?? null;
        if (($body['schema'] ?? null)!==1 || !is_array($plays) || count($plays)>100 || (!$plays && !is_array($body['health'] ?? null))) { return HPADS_Core::error('Send schema 1 and up to 100 observations with queue health.'); }
        $device=(int)$request->get_param('_hpads_authenticated_device');$ack=[];$rejected=[];
        foreach ($plays as $p) {
            $row=self::observation($p,$device);
            $result=is_wp_error($row)?$row:self::store($row);
            if ($result===false) { return HPADS_Core::error('Analytics storage unavailable; retry batch.',503); }
            if (is_wp_error($result)) { $rejected[]=['play_id'=>is_array($p)?($p['play_id'] ?? ''):'','code'=>$result->get_error_code()]; }
            else { $ack[]=['play_id'=>$p['play_id'],'seq'=>$p['seq']]; }
        }
        if (isset($body['health']) && is_array($body['health'])) {
            $health=['received_at'=>time()];
            foreach (['pending','rejected','dropped','oldest_pending_utc'] as $field) { $health[$field]=max(0,min(PHP_INT_MAX,(int)($body['health'][$field] ?? 0))); }
            update_option('hwads_analytics_health_'.$device,$health,false);
        }
        self::receive_queue($device,$body['queue_snapshot'] ?? null);$request=get_option('hwads_pending_snapshot_'.$device,[]);
        $pending=($request['status'] ?? '')==='pending' && time()-($request['requested_at'] ?? 0)<7*DAY_IN_SECONDS?['id'=>$request['id']]:null;
        $purge=get_option('hwads_queue_purge_'.$device,[]);
        if (($purge['status'] ?? '')==='pending' && ($body['purge_ack']['id'] ?? '')===($purge['id'] ?? null)) {
            $purge['status']='completed';$purge['deleted']=max(0,min(50,(int)($body['purge_ack']['deleted'] ?? 0)));update_option('hwads_queue_purge_'.$device,$purge,false);
            delete_option('hwads_pending_snapshot_'.$device);
        }
        $command=($purge['status'] ?? '')==='pending' && time()-$purge['requested_at']<3600?array_intersect_key($purge,array_flip(['id','records'])):null;
        return new WP_REST_Response(['acknowledged'=>$ack,'rejected'=>$rejected,'queue_snapshot_request'=>$pending,'queue_purge'=>$command]);
    }
    static function queue_url($device) { return add_query_arg(['page'=>'hwads-pending-playback','device_id'=>(int)$device],admin_url('admin.php')); }
    static function request_queue() {
        $id=absint($_POST['device_id'] ?? 0);check_admin_referer('hwads_queue_snapshot_'.$id);
        $device=HPADS_Core::get($id,'device');
        if (!$device || !HPADS_Core::can($device) || HPADS_View_As::state()) { wp_die('Device diagnostics unavailable.','HWADS',['response'=>403]); }
        $request=get_option('hwads_pending_snapshot_'.$id,[]);$request=array_merge($request,['id'=>wp_generate_uuid4(),'status'=>'pending','requested_at'=>time(),'requested_by'=>get_current_user_id()]);update_option('hwads_pending_snapshot_'.$id,$request,false);
        wp_safe_redirect(self::queue_url($id));exit;
    }
    static function request_purge() {
        $id=absint($_POST['device_id'] ?? 0);check_admin_referer('hwads_queue_purge_'.$id);
        $device=HPADS_Core::get($id,'device');
        if (!$device || !HPADS_Core::can($device,true) || HPADS_View_As::state() || empty($_POST['confirm_purge'])) { wp_die('Purge not authorized.','HWADS',['response'=>403]); }
        $snapshot=get_option('hwads_pending_snapshot_'.$id,[]);$data=$snapshot['data'] ?? [];
        if (time()-($data['received_at'] ?? 0)>900) { wp_die('Refresh the snapshot before purging.'); }
        $records=[];foreach ($data['records'] ?? [] as $r) {
            $records[]=['play_id'=>$r['play_id'],'seq'=>$r['seq']];
        }
        if (!$records) { wp_die('No pending records in this snapshot.'); }
        update_option('hwads_queue_purge_'.$id,['id'=>wp_generate_uuid4(),'records'=>$records,'status'=>'pending','requested_at'=>time(),'requested_by'=>get_current_user_id()],false);
        wp_safe_redirect(self::queue_url($id));exit;
    }
    static function receive_queue($device,$body) {
        if (!is_array($body)) { return; }$request=get_option('hwads_pending_snapshot_'.$device,[]);
        if (($request['status'] ?? '')!=='pending' || !is_string($body['request_id'] ?? null) || !hash_equals($request['id'],$body['request_id'])) { return; }
        if (!is_array($body['records'] ?? null) || count($body['records'])>50 || !is_int($body['pending'] ?? null) || $body['pending']<0 || $body['pending']>100000) { return; }
        $records=[];
        foreach ($body['records'] as $p) {
            if (!is_array($p) || !is_string($p['play_id'] ?? null) || !preg_match('/^[a-f0-9-]{36}$/D',$p['play_id'])) { continue; }
            foreach (['placement_id','version_id','seq','elapsed_ms'] as $key) { if (!is_int($p[$key] ?? null) || $p[$key]<($key==='elapsed_ms'?0:1)) { continue 2; } }
            if ($p['elapsed_ms']>86400000 || $p['seq']>100000 || !in_array($p['outcome'] ?? '',['playing','completed','interrupted','error'],true) || !in_array($p['engine'] ?? '',['browser','mpv'],true)) { continue; }
            $r=array_intersect_key($p,array_fill_keys(['play_id','placement_id','version_id','seq','elapsed_ms','outcome','engine'],true));
            foreach (['started_at','updated_at'] as $key) { $v=$p[$key] ?? null;$r[$key]=(is_int($v)||is_float($v)) && is_finite((float)$v) && $v>=0 && $v<=4102444800?(int)$v:0; }
            $r['verified']=false;$attribution=self::attribution(is_string($p['grant'] ?? null)?$p['grant']:'',$device,$p['version_id']);$manifest=HPADS_Core::version($p['version_id']);
            if ($attribution && $manifest && (int)$manifest['location_id']===(int)$attribution['location']) {
                foreach ($manifest['items'] as $item) { if ((int)($item['placement_id'] ?? 0)===$p['placement_id']) { $r+=['title'=>substr($item['title'],0,300),'location_id'=>(int)$attribution['location'],'playlist_id'=>(int)$manifest['playlist_id']];$r['verified']=true;break; } }
            }
            $records[]=$r;
        }
        $request['data']=['pending'=>$body['pending'],'records'=>$records,'received_at'=>time()];$request['status']='completed';update_option('hwads_pending_snapshot_'.$device,$request,false);
    }
    static function queue_page() {
        $id=absint($_GET['device_id'] ?? 0);$device=HPADS_Core::get($id,'device');
        if (!$device || !HPADS_Core::can($device)) { wp_die('Device diagnostics unavailable.','HWADS',['response'=>403]); }
        $request=get_option('hwads_pending_snapshot_'.$id,[]);$data=$request['data'] ?? null;
        echo '<div class="wrap"><h1>Pending playback records</h1><h2>'.esc_html($device['title']).'</h2><p>A snapshot from this HWADS Box, not a live list. Every row is awaiting upload confirmation. Playback status describes the ad, not whether its metrics were uploaded. Successful uploads clear matching records automatically; newer checkpoints can briefly remain queued. Purge is a manual recovery action for stuck records and requires the matching HWADS Box software update. Older software will leave a purge request unacknowledged.</p>';
        $purge=get_option('hwads_queue_purge_'.$id,[]);
        if ($purge) { $purge_state=$purge['status']==='pending' && time()-$purge['requested_at']>=3600?'expired':$purge['status'];echo '<p>Purge: '.esc_html($purge_state).(($purge['status'] ?? '')==='completed'?' · '.(int)$purge['deleted'].' records removed':' · waiting for the HWADS Box; request expires after one hour').'</p>'; }
        if (!HPADS_View_As::state() && HPADS_Core::can($device,true) && $data && time()-$data['received_at']<=900) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_queue_purge"><input type="hidden" name="device_id" value="'.$id.'">';wp_nonce_field('hwads_queue_purge_'.$id);
            echo '<p><label><input required type="checkbox" name="confirm_purge" value="1"> Discard '.count($data['records']).' pending records shown in this snapshot. Unreported metrics can be lost. Newer checkpoints are preserved. Playback continues; an active play may queue a newer checkpoint again.</label></p>';submit_button('Purge pending queue','delete','submit',false);echo '</form>';
        }
        if (!HPADS_View_As::state()) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_queue_snapshot"><input type="hidden" name="device_id" value="'.$id.'">';wp_nonce_field('hwads_queue_snapshot_'.$id);submit_button('Refresh snapshot','secondary','submit',false);echo '</form>'; }
        if (($request['status'] ?? '')==='pending') { echo '<p>Waiting for the HWADS Box. Refresh this page after its next metrics check-in. The HWADS Box must be online and running a player that supports queue snapshots.</p>'; }
        if (!$data) { echo '<p>No snapshot received yet. Use Refresh snapshot to request the latest pending records.</p></div>';return; }
        echo '<p><strong>'.(int)$data['pending'].' pending when captured</strong> · Snapshot received '.esc_html(HPADS_Core::display_datetime($data['received_at'],true)).'. Showing up to 50 records.</p><table class="widefat striped"><thead><tr><th>Placement</th><th>Playlist</th><th>Started</th><th>Recorded play time</th><th>Status</th><th>Version</th></tr></thead><tbody>';
        $shown=0;$hidden=0;
        foreach ($data['records'] as $r) {
            if (!empty($r['verified']) && (!HPADS_Core::can(HPADS_Core::get($r['location_id'],'location')) || !HPADS_Core::can(HPADS_Core::get($r['placement_id'],'creative')))) { $hidden++;continue; }
            $shown++;$playlist=!empty($r['verified'])?HPADS_Core::get($r['playlist_id'],'playlist'):null;
            $title=!empty($r['verified'])?$r['title']:'Unverified queue record';$state=['playing'=>'In progress','completed'=>'Playback ended; awaiting upload','interrupted'=>'Interrupted; awaiting upload','error'=>'Playback error'][$r['outcome']];
            echo '<tr><td>'.esc_html($title).' #'.(int)$r['placement_id'].'<details><summary>Record ID</summary><code>'.esc_html($r['play_id']).'</code></details></td><td>'.esc_html($playlist && HPADS_Core::can($playlist)?$playlist['title']:'—').'</td><td>'.esc_html($r['started_at']?HPADS_Core::display_datetime($r['started_at']):'Not reported').'</td><td>'.esc_html(number_format_i18n($r['elapsed_ms']/1000,1)).' seconds</td><td>'.esc_html($state).'</td><td>#'.(int)$r['version_id'].'</td></tr>';
        }
        if (!$shown) { echo '<tr><td colspan="6">'.($data['pending']?'No pending records are available in this snapshot for your access.':'No pending records at snapshot time.').'</td></tr>'; }echo '</tbody></table>';
        if ($hidden) { echo '<p>'.(int)$hidden.' records hidden by your access permissions.</p>'; }echo '<p>Refreshing does not delete records, retry plays or change playback.</p></div>';
    }
    static function can_report($kind,$id) {
        $r=HPADS_Core::get($id,$kind==='placement'?'creative':'playlist');
        return $r && ($kind!=='placement' || !HPADS_Placements::is_template($r)) && HPADS_Core::can($r);
    }
    static function totals($kind,$id,$start,$end,$group=false) {
        global $wpdb;if (!self::can_report($kind,$id)) { return []; }
        $field=$kind==='placement'?'placement_id':'playlist_id';
        $select=$group?'device_id,location_id,venue_id,':'';
        $sql='SELECT '.$select."SUM(starts) starts,SUM(completed) completed,SUM(elapsed_ms) elapsed_ms,MAX(last_received) last_received FROM ".self::daily_table()." WHERE $field=%d AND report_day>=%s AND report_day<%s".($group?' GROUP BY device_id,location_id,venue_id':'');
        return $wpdb->get_results($wpdb->prepare($sql,$id,wp_date('Y-m-d',strtotime($start.' UTC')),wp_date('Y-m-d',strtotime($end.' UTC'))),ARRAY_A);
    }
    static function box($post) {
        $r=HPADS_Post_Types::record($post->ID);if (!$r) { echo '<p>Save this record to view reporting.</p>';return; }
        $kind=$post->post_type==='hpads_playlist'?'playlist':'placement';
        echo '<p>Screen starts, completed plays and cumulative play time across reporting devices.</p><a class="button" href="'.esc_url(add_query_arg(['page'=>'hwads-engagement','kind'=>$kind,'id'=>$r['id']],admin_url('admin.php'))).'">View screen engagement</a><p class="description">Website embed impressions are available in the report. Landing-page, outbound and QR metrics are not yet collected.</p>';
    }
    static function dates($from,$to) {
        $zone=wp_timezone();$a=DateTimeImmutable::createFromFormat('!Y-m-d',$from,$zone);$b=DateTimeImmutable::createFromFormat('!Y-m-d',$to,$zone);
        if (!$a || !$b || $a->format('Y-m-d')!==$from || $b->format('Y-m-d')!==$to || $a>$b) { return null; }
        return [$a->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$b->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
    }
    static function page() {
        if (!current_user_can('hpads_access')) { wp_die('Access denied.'); }
        $kind=($_GET['kind'] ?? '')==='playlist'?'playlist':'placement';$id=absint($_GET['id'] ?? 0);
        $from=sanitize_text_field(wp_unslash($_GET['from'] ?? wp_date('Y-m-01')));$to=sanitize_text_field(wp_unslash($_GET['to'] ?? wp_date('Y-m-d')));$dates=self::dates($from,$to);
        if (!empty($_GET['record']) && preg_match('/^(placement|playlist):(\d+)$/D',(string)$_GET['record'],$match)) { $kind=$match[1];$id=(int)$match[2]; }
        $options='<option value="">Choose a placement or playlist</option>';
        foreach (['placement'=>'creative','playlist'=>'playlist'] as $report_kind=>$entity_kind) {
            $options.='<optgroup label="'.($report_kind==='placement'?'Placements':'Playlists').'">';
            foreach (HPADS_Core::all($entity_kind) as $entry) { $record=HPADS_Core::get($entry['id'],$entity_kind);if ($report_kind==='placement' && HPADS_Placements::is_template($record)) { continue; }$value=$report_kind.':'.$entry['id'];$options.='<option value="'.esc_attr($value).'" '.selected($kind.':'.$id,$value,false).'>'.esc_html($entry['title'].' #'.$entry['id']).'</option>'; }
            $options.='</optgroup>';
        }
        echo '<div class="wrap"><h1>Screen Engagement</h1><form method="get"><input type="hidden" name="page" value="hwads-engagement"><label>Report <select name="record">'.$options.'</select></label> <label>From <input type="date" name="from" value="'.esc_attr($from).'"></label> <label>Through <input type="date" name="to" value="'.esc_attr($to).'"></label> ';submit_button('View report','secondary','submit',false);echo '</form>';
        if (!$dates) { echo '<p>Choose a valid date range.</p></div>';return; }
        if (!$id || !self::can_report($kind,$id)) { echo '<p>Choose a placement or playlist you can access, or open Engagement from its editor.</p></div>';return; }
        $record=HPADS_Core::get($id);echo '<h2>'.esc_html($record['title']).'</h2>';
        HPADS_Web_Metrics::report($kind,$id,$from,$to);
        $totals=self::totals($kind,$id,$dates[0],$dates[1]);$total=$totals[0] ?? [];
        if (empty($total['starts'])) { echo '<p><strong>Not reporting for this range.</strong> No screen observations received. This does not establish that no playback occurred.</p></div>';return; }
        echo '<p><strong>HWADS Box plays: '.number_format_i18n($total['starts']).' · Completed: '.number_format_i18n($total['completed']).' · Completion rate: '.number_format_i18n(100*$total['completed']/max(1,$total['starts']),1).'% · Screen-minutes: '.number_format_i18n($total['elapsed_ms']/60000,2).'</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>Device</th><th>Location</th><th>Channel</th><th>HWADS Box plays</th><th>Completed</th><th>Screen-minutes</th><th>Last received</th><th>Queue status</th></tr></thead><tbody>';
        foreach (self::totals($kind,$id,$dates[0],$dates[1],true) as $row) {
            $device=HPADS_Core::get($row['device_id'],'device');$location=HPADS_Core::get($row['location_id'],'location');$health=get_option('hwads_analytics_health_'.(int)$row['device_id'],[]);
            $queue=$health?((int)$health['pending'].' pending · '.(int)$health['rejected'].' rejected · '.(int)$health['dropped'].' dropped; as of '.HPADS_Core::display_datetime($health['received_at'])):'Not received';
            $queue_html=esc_html($queue);if ($device && HPADS_Core::can($device)) { $queue_html.='<br><a href="'.esc_url(self::queue_url($device['id'])).'">View pending records</a>'; }
            echo '<tr><td>'.esc_html(($device['title'] ?? 'Device').' #'.$row['device_id']).'</td><td>'.esc_html(($location['title'] ?? 'Location').' #'.$row['location_id']).'</td><td>Screen</td><td>'.number_format_i18n($row['starts']).'</td><td>'.number_format_i18n($row['completed']).'</td><td>'.number_format_i18n($row['elapsed_ms']/60000,2).'</td><td>'.esc_html(HPADS_Core::display_datetime(strtotime($row['last_received'].' UTC'))).'</td><td>'.$queue_html.'</td></tr>';
        }
        echo '</tbody></table><p>Dates use '.esc_html(wp_timezone_string()).'. Plays and their time belong to the date they started. Screen-minutes accumulate across devices. Offline uploads and five-second checkpoints can delay totals. A start without completion may be interrupted, still running, or awaiting upload.</p><p>Counts begin after analytics-capable players sync this plugin. Landing-page, link and QR tracking are not yet collected. Playback records software delivery, not audience size.</p></div>';
    }
}
