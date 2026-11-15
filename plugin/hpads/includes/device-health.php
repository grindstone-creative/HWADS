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
    static function render($r) {
        if (!HPADS_Core::can($r)) { return; }global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'hpads_status WHERE device_id=%d',$r['id']),ARRAY_A);$p=$row?(json_decode($row['payload'],true) ?: []):[];
        echo '<h3>Device health</h3><p>Latest reported readings; updated at the heartbeat interval, not live play counts.</p><table class="widefat striped"><tbody>';
        $values=['Last contact'=>$row?wp_date('Y-m-d H:i:s',(int)$row['last_seen']):'Never seen','Playback state'=>$p['state'] ?? 'Unknown','Display engine'=>$p['engine'] ?? 'Not reported','Current placement'=>!empty($p['placement_id'])?'#'.$p['placement_id']:'—','Active playlist version'=>!empty($p['active_version'])?'#'.$p['active_version']:'—','Last successful sync'=>!empty($p['last_sync_utc'])?wp_date('Y-m-d H:i:s',(int)$p['last_sync_utc']):'Not reported','Free disk space'=>isset($p['disk_free_bytes'])?size_format($p['disk_free_bytes'],1):'Not reported','Player / sync version'=>($p['player_version'] ?? '—').' / '.($p['sync_version'] ?? '—'),'Last reported error'=>$p['error'] ?? ''];
        foreach ($values as $label=>$value) { echo '<tr><th scope="row">'.esc_html($label).'</th><td>'.esc_html($value ?: 'None').'</td></tr>'; }echo '</tbody></table>';
        if (HPADS_Core::can($r,true)) {
            $command=self::command($r['id']);self::$device_id=(int)$r['id'];echo '<p><button class="button" type="submit" form="hwads-purge-form">Purge unused media</button></p><p>Queues cleanup for the next device heartbeat. Keeps current/previous playlist files, files in playback, placeholders and configuration. Only unused media older than 24 hours is removed. Unsaved editor changes are not saved by this action.</p>';
            if ($command) { echo '<p>Cleanup: '.esc_html($command['status']).(($command['status'] ?? '')==='completed'?' · '.(int)$command['files'].' files · '.esc_html(size_format($command['bytes'],1)).' freed':'').'</p>'; }
        }
    }
}
