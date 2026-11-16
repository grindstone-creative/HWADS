<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Device_Health {
    private static $device_id=0;
    static function init() { add_action('admin_post_hwads_purge_device',[__CLASS__,'queue']);add_action('admin_footer',[__CLASS__,'form']); }
    static function form() {
        $id=self::$device_id;if (!$id) { return; }
        echo '<form id="hwads-purge-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_purge_device"><input type="hidden" name="hwads_purge_device" value="'.(int)$id.'"><input type="hidden" name="hwads_purge_nonce" value="'.esc_attr(wp_create_nonce('hwads_purge_'.$id)).'"></form>';
    }
    static function command($id) { return get_option('hwads_purge_'.(int)$id,[]); }
    static function queue() {
        $id=absint($_POST['hwads_purge_device'] ?? 0);$r=HPADS_Core::get($id,'device');
        if (!$r || !HPADS_Core::can($r,true) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hwads_purge_nonce'] ?? '')),'hwads_purge_'.$id)) { wp_die('Device unavailable or invalid nonce.','HWADS',['response'=>403]); }
        $old=self::command($id);
        if (($old['status'] ?? '')!=='pending') { update_option('hwads_purge_'.$id,['id'=>wp_generate_uuid4(),'status'=>'pending','requested_at'=>time()],false); }
        wp_safe_redirect(HPADS_Admin::url('device',$id));exit;
    }
    static function acknowledge($id,$body) {
        $command=self::command($id);
        if (($command['status'] ?? '')==='pending' && is_string($body['purge_command_id'] ?? null) && hash_equals($command['id'],$body['purge_command_id'])) {
            $command['status']='completed';$command['completed_at']=time();$command['files']=absint($body['purge_files'] ?? 0);$command['bytes']=absint($body['purge_bytes'] ?? 0);update_option('hwads_purge_'.$id,$command,false);
        }
    }
    static function telemetry($raw) {
        if (!is_array($raw)) { return []; }
        $number=function($v,$min,$max){return (is_int($v)||is_float($v)) && is_finite((float)$v) && $v>=$min && $v<=$max?$v:null;};
        $out=['cpu_percent'=>$number($raw['cpu_percent'] ?? null,0,100),'temperature_c'=>$number($raw['temperature_c'] ?? null,-40,150),'uptime_seconds'=>is_int($raw['uptime_seconds'] ?? null)?$number($raw['uptime_seconds'],0,3155760000):null,'cpu_cores'=>is_int($raw['cpu_cores'] ?? null)?$number($raw['cpu_cores'],1,4096):null,'load_average'=>null];
        $load=$raw['load_average'] ?? null;if (is_array($load) && count($load)===3) { $clean=array_map(function($v) use($number){return $number($v,0,65536);},array_values($load));if (!in_array(null,$clean,true)) { $out['load_average']=$clean; } }
        $total=$raw['memory_total_bytes'] ?? null;$available=$raw['memory_available_bytes'] ?? null;
        if (is_int($total) && $total>0 && $total<=1125899906842624 && is_int($available) && $available>=0 && $available<=$total) { $out+=['memory_total_bytes'=>$total,'memory_available_bytes'=>$available,'memory_used_bytes'=>$total-$available,'memory_used_percent'=>round(100*($total-$available)/$total,1)]; }
        return $out;
    }
    static function uptime($seconds) {
        if ($seconds===null) { return 'Not reported'; }$seconds=(int)$seconds;$days=intdiv($seconds,86400);$hours=intdiv($seconds%86400,3600);$minutes=intdiv($seconds%3600,60);
        return $days?$days.'d '.$hours.'h '.$minutes.'m':($hours?$hours.'h '.$minutes.'m':($minutes?$minutes.'m '.($seconds%60).'s':$seconds.'s'));
    }
    static function render($r) {
        if (!HPADS_Core::can($r)) { return; }global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'hpads_status WHERE device_id=%d',$r['id']),ARRAY_A);$p=$row?(json_decode($row['payload'],true) ?: []):[];
        echo '<h3>Device health</h3><p>Latest reported readings; refreshed at the heartbeat interval. CPU usage is averaged since the previous sample. These are snapshots, not live monitoring.</p><table class="widefat striped"><tbody>';
        $values=['Last contact'=>$row?HPADS_Core::display_datetime((int)$row['last_seen']):'Never seen','Playback state'=>$p['state'] ?? 'Unknown','Playlist assignment'=>!empty($p['assignment_mismatch'])?'Playing a previous or different assignment':'Matches latest report','Display engine'=>$p['engine'] ?? 'Not reported','Current placement'=>!empty($p['placement_id'])?'#'.$p['placement_id']:'—','Active playlist revision'=>!empty($p['active_version'])?'#'.$p['active_version']:'—','Last successful sync'=>!empty($p['last_sync_utc'])?HPADS_Core::display_datetime((int)$p['last_sync_utc']):'Not reported','Free disk space'=>isset($p['disk_free_bytes'])?size_format($p['disk_free_bytes'],1):'Not reported','Player / sync version'=>($p['player_version'] ?? '—').' / '.($p['sync_version'] ?? '—'),'Last reported error'=>$p['error'] ?? ''];
        $health=self::telemetry($p['system_health'] ?? []);
        $values+=['RAM used / total'=>isset($health['memory_total_bytes'])?size_format($health['memory_used_bytes'],1).' / '.size_format($health['memory_total_bytes'],1).' ('.$health['memory_used_percent'].'%)':'Not reported',
            'CPU usage'=>isset($health['cpu_percent'])?number_format_i18n($health['cpu_percent'],1).'%':'Not reported',
            'System load (1 / 5 / 15 min)'=>isset($health['load_average'])?implode(' / ',array_map(function($v){return number_format_i18n($v,2);},$health['load_average'])):'Not reported',
            'CPU cores'=>$health['cpu_cores'] ?? 'Not reported','CPU temperature'=>isset($health['temperature_c'])?number_format_i18n($health['temperature_c'],1).' °C':'Not reported','Uptime'=>self::uptime($health['uptime_seconds'] ?? null)];
        foreach ($values as $label=>$value) { echo '<tr><th scope="row">'.esc_html($label).'</th><td>'.($label==='Active playlist revision' && !empty($p['active_version'])?HPADS_Admin::revision_link((int)$p['active_version']):esc_html($value ?: 'None')).'</td></tr>'; }echo '</tbody></table>';
        echo '<p><a href="'.esc_url(HPADS_Analytics::queue_url($r['id'])).'">View pending playback records</a></p>';
        if (current_user_can('hpads_owner')) { $update=HPADS_Player_Updates::device($r['id']);echo '<p><a href="'.esc_url(admin_url('admin.php?page=hpads-updates')).'">Player updates</a>: '.esc_html($update['status'] ?? 'Initial updater install needed').'</p>'; }
        if (HPADS_Core::can($r,true)) {
            $command=self::command($r['id']);self::$device_id=(int)$r['id'];echo '<p><button class="button" type="submit" form="hwads-purge-form">Purge unused media</button></p><p>Queues cleanup for the next device heartbeat. Keeps current/previous playlist files, files in playback, placeholders and configuration. Only unused media older than 24 hours is removed. Unsaved editor changes are not saved by this action.</p>';
            if ($command) { echo '<p>Cleanup: '.esc_html($command['status']).(($command['status'] ?? '')==='completed'?' · '.(int)$command['files'].' files · '.esc_html(size_format($command['bytes'],1)).' freed':'').'</p>'; }
        }
    }
}
