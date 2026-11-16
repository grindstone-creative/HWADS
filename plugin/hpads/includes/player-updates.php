<?php
if (!defined('ABSPATH')) { exit; }
/** Owner-only executable releases; authenticated devices pull individually signed commands. */
final class HPADS_Player_Updates {
    const MAX_ZIP=20971520;
    const FILES=['hwads_sync.py','hwads_player.py','hwads_browser.py','hwads_launch.py','hwads-control.py','hwads_update.py','browser/index.html','browser/player.js','browser/player.css','browser/overlays.js','browser/overlays.css','browser/vendor/qrcodegen.js'];
    const EXTRAS=['README.md','THIRD_PARTY.md','install.sh','device.example.json','update-manifest.json'];
    static function init() { add_action('admin_post_hwads_player_update',[__CLASS__,'save']);add_filter('views_edit-hpads_device',[__CLASS__,'device_views']); }
    static function device_views($views) {
        if (self::owner()) { $views['hwads-player-updates']='<a href="'.esc_url(admin_url('admin.php?page=hpads-updates')).'"><strong>Player Updates</strong></a>'; }
        return $views;
    }
    static function owner() { return current_user_can('hpads_owner') && !HPADS_View_As::state(); }
    static function releases() { return get_option('hwads_player_releases',[]); }
    static function device($id) { return get_option('hwads_player_update_'.(int)$id,[]); }
    static function validate_zip($path) {
        if (!class_exists('ZipArchive')) { return HPADS_Core::error('The hosting PHP ZIP extension is required.'); }
        if (!is_file($path) || filesize($path)>self::MAX_ZIP) { return HPADS_Core::error('Player ZIP must be 20 MB or smaller.'); }
        $zip=new ZipArchive();if ($zip->open($path)!==true) { return HPADS_Core::error('Upload a valid player ZIP.'); }
        try {
            if ($zip->numFiles>50) { return HPADS_Core::error('Too many files in player package.'); }$seen=[];$expanded=0;
            for ($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);$name=$stat['name'];$expanded+=(int)$stat['size'];
                if (isset($seen[$name]) || strpos($name,'hwads-pi/')!==0 || strpos($name,'\\')!==false || strpos($name,"\0")!==false || in_array('..',explode('/',$name),true) || $stat['size']>10485760 || $expanded>41943040 || !empty($stat['encryption_method'])) { return HPADS_Core::error('Unsafe or oversized player package.'); }$seen[$name]=true;
                $opsys=0;$attrs=0;$zip->getExternalAttributesIndex($i,$opsys,$attrs);$mode=($attrs>>16)&0170000;if (!in_array($mode,[0,0100000,0040000],true)) { return HPADS_Core::error('Player ZIP cannot contain links.'); }
                $relative=substr($name,9);if (substr($name,-1)==='/') { if (!in_array($relative,['','browser/','browser/vendor/'],true)) { return HPADS_Core::error('Unexpected package directory.'); } }
                elseif (!in_array($relative,array_merge(self::FILES,self::EXTRAS),true)) { return HPADS_Core::error('Unexpected package file: '.$relative); }
            }
            $raw=$zip->getFromName('hwads-pi/update-manifest.json');if (!$raw || strlen($raw)>32768) { return HPADS_Core::error('Use an updater-ready player package (0.4.0 or newer).'); }
            $m=json_decode($raw,true);$files=$m['files'] ?? null;$expected=self::FILES;sort($expected);$actual=is_array($files)?array_keys($files):[];sort($actual);
            if (!is_array($m) || ($m['schema'] ?? 0)!==1 || ($m['updater_protocol'] ?? 0)!==1 || !preg_match('/^\d+\.\d+\.\d+$/D',$m['version'] ?? '') || $actual!==$expected) { return HPADS_Core::error('Unsupported or incomplete player manifest.'); }
            foreach ($files as $name=>$hash) { $data=$zip->getFromName('hwads-pi/'.$name);if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash) || $data===false || !hash_equals($hash,hash('sha256',$data))) { return HPADS_Core::error('Package checksum failed for '.$name); } }
            $example=json_decode($zip->getFromName('hwads-pi/device.example.json') ?: '{}',true);
            if (preg_match('/^[A-Za-z0-9_-]{43}$/D',$example['device_key'] ?? '')) { return HPADS_Core::error('Do not upload a package containing a real device key.'); }
            return ['version'=>$m['version'],'schema'=>1,'sha256'=>hash_file('sha256',$path),'bytes'=>filesize($path)];
        } finally { $zip->close(); }
    }
    static function import($path) {
        if (!self::owner()) { return HPADS_Core::error('Only SuperAdmin may upload executable player updates.',403); }
        $release=self::validate_zip($path);if (is_wp_error($release)) { return $release; }
        $upload=wp_upload_dir();if (!empty($upload['error'])) { return HPADS_Core::error('Uploads directory is unavailable.'); }
        $directory=$upload['basedir'].'/hwads-player-updates';if (!wp_mkdir_p($directory)) { return HPADS_Core::error('Cannot create update storage.'); }
        $id=$release['sha256'];$target=$directory.'/'.$id.'.zip';
        if (!is_file($target)) { $temp=$target.'.part-'.wp_generate_uuid4();if (!copy($path,$temp) || !rename($temp,$target)) { if (is_file($temp)) { unlink($temp); }return HPADS_Core::error('Cannot store player update.'); } }
        $url=$upload['baseurl'].'/hwads-player-updates/'.$id.'.zip';if (wp_parse_url($url,PHP_URL_SCHEME)!=='https') { return HPADS_Core::error('Player updates require HTTPS on WordPress uploads.'); }
        $release=array_merge($release,['id'=>$id,'url'=>$url,'uploaded_at'=>time(),'uploaded_by'=>get_current_user_id()]);$all=self::releases();$all[$id]=$release;update_option('hwads_player_releases',$all,false);return $id;
    }
    /** Serialize queue/poll/cancel so a late status report cannot overwrite a new request. */
    static function locked($id,$callback) {
        global $wpdb;$key='hwads_player_update_lock_'.(int)$id;$token=['token'=>wp_generate_uuid4(),'at'=>time()];
        if (!add_option($key,$token,'',false)) {
            $prior=get_option($key);
            if (is_array($prior) && (int)($prior['at'] ?? 0)<time()-30) { $wpdb->delete($wpdb->options,['option_name'=>$key,'option_value'=>maybe_serialize($prior)]);wp_cache_delete($key,'options');wp_cache_delete('alloptions','options'); }
            if (!add_option($key,$token,'',false)) { return HPADS_Core::error('This device update is being changed. Please try again.',409); }
        }
        try { return $callback(); }
        finally { $wpdb->delete($wpdb->options,['option_name'=>$key,'option_value'=>maybe_serialize($token)]);wp_cache_delete($key,'options'); }
    }
    static function scheduled_time($device,$now=null) {
        $location=HPADS_Update_Policy::location($device);$policy=$location?HPADS_Update_Policy::policy($location):[];
        if (empty($policy['enabled']) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$policy['time'] ?? '')) { return HPADS_Core::error('Enable a location update time before scheduling this device.'); }
        $now=$now??time();$zone=wp_timezone();$local=(new DateTimeImmutable('@'.$now))->setTimezone($zone);
        $due=new DateTimeImmutable($local->format('Y-m-d').' '.$policy['time'],$zone);
        if ($now>=$due->getTimestamp()+30*MINUTE_IN_SECONDS) { $due=new DateTimeImmutable($local->modify('+1 day')->format('Y-m-d').' '.$policy['time'],$zone); }
        return $due->getTimestamp();
    }
    static function cancel($id) { return self::locked($id,function() use($id){return self::cancel_locked($id);}); }
    static function cancel_locked($id) {
        if (!self::owner()) { return HPADS_Core::error('Only SuperAdmin may cancel a player update.',403); }
        $state=self::device($id);
        if (($state['status'] ?? '')!=='scheduled' || !empty($state['delivery_started_at'])) { return HPADS_Core::error('Only an undelivered scheduled update can be cancelled.'); }
        $state['status']='cancelled';$state['command']=null;$state['message']='Scheduled update cancelled';update_option('hwads_player_update_'.$id,$state,false);return true;
    }
    static function queue($id,$release_id,$rollback=false,$scheduled=false) { return self::locked($id,function() use($id,$release_id,$rollback,$scheduled){return self::queue_locked($id,$release_id,$rollback,$scheduled);}); }
    static function queue_locked($id,$release_id,$rollback=false,$scheduled=false) {
        if (!self::owner()) { return HPADS_Core::error('Only SuperAdmin may deploy player code.',403); }
        $d=HPADS_Core::get($id,'device');if (!$d || !HPADS_Core::can($d,true) || empty($d['data']['enabled'])) { return HPADS_Core::error('Choose an enabled device.'); }
        $old=self::device($id);$report=$old['report'] ?? [];
        if (($report['updater_protocol'] ?? 0)!==1 || empty($report['ready']) || (!$scheduled && time()-(int)($old['seen_at'] ?? 0)>180)) { return HPADS_Core::error('This device needs its initial updater install or a fresh updater check-in.'); }
        if (!empty($old['command']) && !in_array($old['status'] ?? '',['completed','failed','rolled_back','expired','cancelled'],true) && (($old['status'] ?? '')==='scheduled' || ($old['command']['expires_at'] ?? 0)>time())) { return HPADS_Core::error('A player update is already pending on this device.'); }
        $due=$scheduled?self::scheduled_time($d):0;if (is_wp_error($due)) { return $due; }
        if ($scheduled && $rollback) { return HPADS_Core::error('Rollback is an immediate action.'); }
        if ($rollback) {
            if (empty($report['rollback_available']) || $release_id!==($report['previous_release_id'] ?? '')) { return HPADS_Core::error('No matching previous release is available.'); }
            $release=['id'=>$release_id,'version'=>$report['previous_version'],'sha256'=>'','bytes'=>0,'url'=>''];
        } else {
            $release=self::releases()[$release_id] ?? null;if (!$release) { return HPADS_Core::error('Choose an uploaded release.'); }
            if (!version_compare($release['version'],$report['installed_version'] ?? '0.0.0','>')) { return HPADS_Core::error('Install requires a newer version. Use Roll back for the previous release.'); }
        }
        $command=['type'=>$rollback?'rollback_player':'install_player','id'=>wp_generate_uuid4(),'release_id'=>$release['id'],'version'=>$release['version'],'sha256'=>$release['sha256'],'bytes'=>$release['bytes'],'url'=>$release['url'],'expires_at'=>time()+7*DAY_IN_SECONDS];
        $old['command']=$command;$old['status']=$scheduled?'scheduled':'queued';$old['not_before']=$due;unset($old['delivery_started_at']);$old['requested_at']=time();$old['requested_by']=get_current_user_id();$old['message']=$scheduled?'Scheduled for '.HPADS_Core::display_datetime($due,true).' · missed time runs at next check-in':'Waiting for device check-in';update_option('hwads_player_update_'.$id,$old,false);return $command['id'];
    }
    static function signed($device,$command) {
        $values=[];foreach (['type','id','release_id','version','sha256','bytes','url','expires_at'] as $key) { $values[]=(string)$command[$key]; }
        $command['signature']=hash_hmac('sha256',implode('|',$values),$device['data']['key_hash']);return $command;
    }
    static function poll($request) { return self::locked(absint($request->get_param('_hpads_authenticated_device')),function() use($request){return self::poll_locked($request);}); }
    static function poll_locked($request) {
        if (strlen($request->get_body())>4096) { return HPADS_Core::error('Update status exceeds 4 KB.',413); }
        $id=absint($request->get_param('_hpads_authenticated_device'));$d=HPADS_Core::get($id,'device');if (!$d) { return HPADS_Core::error('Invalid device.',401); }
        $body=$request->get_json_params();if (!is_array($body)) { return HPADS_Core::error('Send an update status object.'); }
        $old=self::device($id);if (time()-(int)($old['seen_at'] ?? 0)<3) { return new WP_REST_Response(['error'=>'Wait before polling again.'],429); }
        $report=[];foreach (['id','status','message','version','release_id','installed_version','previous_version','previous_release_id'] as $key) { $report[$key]=substr(sanitize_text_field($body[$key] ?? ''),0,$key==='message'?250:100); }
        $report['updater_protocol']=absint($body['updater_protocol'] ?? 0);foreach (['ready','rollback_available'] as $key) { $report[$key]=!empty($body[$key]); }
        $command=$old['command'] ?? null;
        if ($command && ($old['status'] ?? '')==='scheduled' && time()>=(int)($old['not_before'] ?? PHP_INT_MAX) && !empty($report['ready']) && $report['updater_protocol']===1) { $command['expires_at']=time()+7*DAY_IN_SECONDS;$old['command']=$command;$old['status']='queued';$old['message']='Scheduled time reached · waiting for device startup'; }
        if ($command && ($command['expires_at'] ?? 0)<time() && ($old['status'] ?? '')==='queued') { $old['status']='expired'; }
        if ($command && ($old['status'] ?? '')!=='scheduled' && hash_equals($command['id'],$report['id']) && in_array($report['status'],['downloading','validating','installing','awaiting_health','completed','failed','rolled_back'],true)) {
            if ($report['status']!=='completed' || $report['installed_version']===$command['version']) { $old['status']=$report['status'];$old['message']=$report['message']; }
        }
        $send=$command && !in_array($old['status'] ?? '',['completed','failed','rolled_back','expired','cancelled','scheduled'],true) && $command['expires_at']>time() && !empty($report['ready']) && $report['updater_protocol']===1?self::signed($d,$command):null;
        if ($send && empty($old['delivery_started_at'])) { $old['delivery_started_at']=time(); }
        $old['report']=$report;$old['seen_at']=time();update_option('hwads_player_update_'.$id,$old,false);
        return new WP_REST_Response(['ok'=>true,'command'=>$send]);
    }
    static function save() {
        if (!self::owner()) { wp_die('SuperAdmin access required.','HWADS',['response'=>403]); }check_admin_referer('hwads_player_updates');$op=sanitize_key($_POST['operation'] ?? '');
        if ($op==='upload') { $file=$_FILES['package'] ?? [];if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) { HPADS_Admin::fail('Choose a player ZIP within the server upload limit.'); }$result=self::import($file['tmp_name']);if (is_wp_error($result)) { HPADS_Admin::fail($result->get_error_message()); } }
        elseif (in_array($op,['install','rollback','schedule','cancel'],true)) {
            $ids=HPADS_Core::ids(wp_unslash($_POST['devices'] ?? []));if (!$ids) { HPADS_Admin::fail('Select at least one device.'); }$messages=[];
            foreach ($ids as $id) { $result=$op==='cancel'?self::cancel($id):self::queue($id,sanitize_text_field(wp_unslash($_POST['release'] ?? '')),$op==='rollback',$op==='schedule');if (is_wp_error($result)) { $messages[]='Device #'.$id.': '.$result->get_error_message(); } }
            if ($messages) { set_transient('hwads_update_notice_'.get_current_user_id(),implode(' ',$messages),120); }
        } else { HPADS_Admin::fail('Unknown player update action.'); }
        wp_safe_redirect(admin_url('admin.php?page=hpads-updates'));exit;
    }
    static function status_label($status) { return ['scheduled'=>'Scheduled','cancelled'=>'Cancelled','queued'=>'Waiting for check-in','downloading'=>'Downloading','validating'=>'Checking package','installing'=>'Installing','awaiting_health'=>'Checking startup','completed'=>'Completed','failed'=>'Failed','rolled_back'=>'Previous version restored','expired'=>'Request expired'][$status] ?? 'Needs initial updater install'; }
    static function page() {
        if (!self::owner()) { echo '<p>SuperAdmin access is required for executable player updates.</p>';return; }
        echo '<p><a href="'.esc_url(add_query_arg('post_type','hpads_device',admin_url('edit.php'))).'">← Devices</a></p>';
        $notice=get_transient('hwads_update_notice_'.get_current_user_id());if ($notice) { delete_transient('hwads_update_notice_'.get_current_user_id());echo '<div class="notice notice-warning"><p>'.esc_html($notice).'</p></div>'; }
        echo '<p>Upload a player package, choose devices, then install now or schedule installation. Installation briefly restarts player software, preserves settings/media, and restores the previous code if startup fails. No HWADS Box reboot is requested. Choose Install Now or Schedule Selected Devices. Scheduled requests wait for the saved location time and catch up after the next boot or connection. Uploading alone never deploys a release; the TV does not need to be off.</p><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_player_update"><input type="hidden" name="operation" value="upload">';wp_nonce_field('hwads_player_updates');echo '<h2>Upload player release</h2><input type="file" name="package" accept=".zip" required> ';submit_button('Upload Player ZIP','secondary','submit',false);echo '<p>Maximum 20 MB; use an updater-ready HWADS package, not your installed hpads folder.</p></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_player_update">';wp_nonce_field('hwads_player_updates');echo '<h2>Install on devices</h2><label>Release <select name="release" required><option value="">Choose release</option>';
        foreach (array_reverse(self::releases()) as $id=>$r) { echo '<option value="'.esc_attr($id).'">'.esc_html($r['version'].' · '.size_format($r['bytes'])).'</option>'; }echo '</select></label><table class="widefat striped"><thead><tr><th>Select</th><th>Device</th><th>Installed</th><th>Update status</th><th>Previous release</th></tr></thead><tbody>';
        $rollbacks=[];$scheduled_rows=[];foreach (HPADS_Core::all('device') as $d) { $s=self::device($d['id']);$r=$s['report'] ?? [];$ready=!empty($r['ready']) && time()-($s['seen_at'] ?? 0)<180;$known=!empty($r['ready']) && ($r['updater_protocol'] ?? 0)===1 && !empty($d['data']['enabled']);if (($s['status'] ?? '')==='scheduled' && empty($s['delivery_started_at'])) { $scheduled_rows[]=$d; }echo '<tr><td><input type="checkbox" name="devices[]" value="'.(int)$d['id'].'" '.disabled(!$known,true,false).' aria-label="'.esc_attr('Select '.$d['title']).'"></td><td><a href="'.esc_url(HPADS_Admin::url('device',$d['id'])).'">'.esc_html($d['title']).'</a></td><td>'.esc_html($r['installed_version'] ?? 'Not reported').'</td><td>'.esc_html(self::status_label($s['status'] ?? '').($ready?'':($known?' · offline; scheduling available':' · updater not ready'))).'<br>'.esc_html($s['message'] ?? '').'</td><td>'.esc_html($r['previous_version'] ?? '—').'</td></tr>';if ($ready && !empty($r['rollback_available'])) { $rollbacks[]=['device'=>$d,'report'=>$r]; } }echo '</tbody></table>';echo '<p><button type="submit" class="button button-primary" name="operation" value="install">Install on Selected Devices</button> <button type="submit" class="button" name="operation" value="schedule">Schedule Selected Devices</button></p><p>Scheduling requires an enabled location update time. It uses that time when queued; later location changes do not move existing requests. Offline devices with a previously ready updater can be scheduled.</p>';echo '</form><p><a class="button" href="'.esc_url(admin_url('admin.php?page=hpads-updates')).'">Refresh Status</a> · Progress comes from device check-ins.</p>';
        if ($scheduled_rows) { echo '<h2>Scheduled requests</h2>';foreach ($scheduled_rows as $device) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_player_update"><input type="hidden" name="operation" value="cancel"><input type="hidden" name="devices[]" value="'.(int)$device['id'].'">';wp_nonce_field('hwads_player_updates');echo '<p>'.esc_html($device['title']).' ';submit_button('Cancel Scheduled Update','secondary','submit',false);echo '</p></form>'; } }
        if ($rollbacks) { echo '<h2>Roll back a device</h2>';foreach ($rollbacks as $row) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_player_update"><input type="hidden" name="operation" value="rollback"><input type="hidden" name="devices[]" value="'.(int)$row['device']['id'].'"><input type="hidden" name="release" value="'.esc_attr($row['report']['previous_release_id']).'">';wp_nonce_field('hwads_player_updates');echo '<p>'.esc_html($row['device']['title']).' ';submit_button('Roll back to '.$row['report']['previous_version'],'secondary','submit',false);echo '</p></form>'; } }
    }
}
