<?php
if (!defined('ABSPATH')) { exit; }
/** Anonymous reported render events; never a count of people. */
final class HPADS_Web_Metrics {
    static function table() { global $wpdb;return $wpdb->prefix.'hwads_web_events'; }
    static function init() {
        add_action('init',[__CLASS__,'install'],31);
        add_action('rest_api_init',function(){register_rest_route('hpads/v1','/embed-events',['methods'=>'POST','callback'=>[__CLASS__,'receive'],'permission_callback'=>'__return_true']);});
        add_action('hwads_analytics_cleanup',[__CLASS__,'cleanup']);
        add_action('hwads_web_cleanup_batch',[__CLASS__,'cleanup']);
    }
    static function cleanup() {
        global $wpdb;$removed=$wpdb->query($wpdb->prepare('DELETE FROM '.self::table().' WHERE received_at<%s LIMIT 5000',gmdate('Y-m-d H:i:s',time()-90*DAY_IN_SECONDS)));
        if($removed===5000 && !wp_next_scheduled('hwads_web_cleanup_batch'))wp_schedule_single_event(time()+60,'hwads_web_cleanup_batch');
    }
    static function install() {
        if (get_option('hwads_web_metrics_schema')==='2') { return; }
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE '.self::table().' (
 event_id varchar(36) NOT NULL,
 placement_id bigint unsigned NOT NULL,
 playlist_id bigint unsigned NOT NULL,
 version_id bigint unsigned NOT NULL,
 revision bigint unsigned NOT NULL,
 source varchar(12) NOT NULL,
 received_at datetime NOT NULL,
 report_day date NOT NULL,
 PRIMARY KEY  (event_id),
 KEY placement_day (placement_id,report_day),
 KEY playlist_day (playlist_id,report_day)
) '.$wpdb->get_charset_collate().';');
        dbDelta('CREATE TABLE '.$wpdb->prefix.'hwads_web_quota (
 bucket varchar(80) NOT NULL,
 hits bigint unsigned NOT NULL,
 PRIMARY KEY  (bucket)
) '.$wpdb->get_charset_collate().';');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',self::table()))===self::table() && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'hwads_web_quota'))===$wpdb->prefix.'hwads_web_quota') { update_option('hwads_web_metrics_schema','2',false); }
    }
    static function token($type,$id,$stamp,$version) { return hash_hmac('sha256',$type.':'.$id.':'.$stamp.':'.$version,wp_salt('auth')); }
    static function config($type,$id,$version) {
        $stamp=(int)(floor(time()/3600)*3600);
        return ['endpoint'=>rest_url('hpads/v1/embed-events'),'type'=>$type,'id'=>(int)$id,'stamp'=>$stamp,'version'=>$version,'token'=>self::token($type,$id,$stamp,$version),'host'=>wp_parse_url(home_url('/'),PHP_URL_HOST)];
    }
    static function receive($request) {
        if (strlen($request->get_body())>2048) { return HPADS_Core::error('Event too large.',413); }
        $b=$request->get_json_params();if (!is_array($b)) { return HPADS_Core::error('Invalid event.'); }
        $version=$b['version'] ?? 0;$type=$b['type'] ?? '';$id=$b['id'] ?? 0;$stamp=$b['stamp'] ?? 0;
        if (!in_array($type,['playlist','placement'],true) || !is_int($id) || $id<1 || !is_int($version) || $version<1 || !is_int($stamp) || $stamp>time() || $stamp<time()-7200 || !is_string($b['token'] ?? null) || !hash_equals(self::token($type,$id,$stamp,$version),$b['token'])) { return HPADS_Core::error('Expired event grant.',403); }
        if (!is_string($b['event_id'] ?? null) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$b['event_id']) || !in_array($b['source'] ?? '',['local','remote','unknown'],true) || !is_int($b['placement_id'] ?? null)) { return HPADS_Core::error('Invalid event.'); }
        $manifest=HPADS_Core::version($version);$found=false;
        if (!$manifest || ($type==='playlist' && (int)$manifest['playlist_id']!==$id) || ($type==='placement' && $id!==$b['placement_id'])) { return HPADS_Core::error('Invalid publication.',404); }
        foreach ($manifest['items'] ?? [] as $item) {
            if ((int)($item['placement_id'] ?? 0)===$b['placement_id'] && (int)($item['placement_revision'] ?? 0)===($b['revision'] ?? null) && (empty($item['start_utc']) || strtotime($item['start_utc'])<=time()) && (empty($item['end_utc']) || strtotime($item['end_utc'])>time())) { $found=true;break; }
        }
        if (!$found) { return HPADS_Core::error('Published placement unavailable.',404); }
        if (!self::quota()) { return HPADS_Core::error('Event rate limit reached.',429); }
        global $wpdb;$ok=$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.self::table().' (event_id,placement_id,playlist_id,version_id,revision,source,received_at,report_day) VALUES (%s,%d,%d,%d,%d,%s,%s,%s)',$b['event_id'],$b['placement_id'],(int)$manifest['playlist_id'],$version,$b['revision'],$b['source'],gmdate('Y-m-d H:i:s'),wp_date('Y-m-d')));
        return $ok===false?HPADS_Core::error('Storage unavailable.',503):new WP_REST_Response(['accepted'=>true]);
    }
    static function quota() {
        global $wpdb;$table=$wpdb->prefix.'hwads_web_quota';$minute=(string)floor(time()/60);
        // Fixed windows bound both per-client traffic and total storage growth. Proxy headers are untrusted.
        $keys=['daily:'.gmdate('Y-m-d')=>25000,$minute.':all'=>2000,$minute.':'.substr(hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? '',wp_salt('auth')),0,40)=>120];
        foreach($keys as $key=>$limit) {
            if($wpdb->query($wpdb->prepare("INSERT INTO $table (bucket,hits) VALUES (%s,1) ON DUPLICATE KEY UPDATE hits=hits+1",$key))===false)return false;
            if((int)$wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket=%s",$key))>$limit)return false;
        }
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE bucket LIKE 'daily:%%' AND bucket<%s LIMIT 100",'daily:'.gmdate('Y-m-d',time()-86400)));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE bucket NOT LIKE 'daily:%%' AND bucket<%s LIMIT 100",(string)(floor(time()/60)-120).':'));
        return true;
    }
    static function totals($kind,$id,$from,$to) {
        if (!HPADS_Analytics::can_report($kind,$id)) { return []; }
        global $wpdb;$field=$kind==='playlist'?'playlist_id':'placement_id';
        return $wpdb->get_results($wpdb->prepare('SELECT source,COUNT(*) impressions FROM '.self::table()." WHERE $field=%d AND report_day>=%s AND report_day<=%s GROUP BY source",$id,$from,$to),ARRAY_A);
    }
    static function report($kind,$id,$from,$to) {
        $counts=['local'=>0,'remote'=>0,'unknown'=>0];foreach (self::totals($kind,$id,$from,$to) as $r) { $counts[$r['source']]=(int)$r['impressions']; }
        echo '<table class="widefat striped"><thead><tr><th>Local website embed impressions</th><th>Remote website embed impressions</th><th>Unknown-source embed impressions</th></tr></thead><tbody><tr>';foreach ($counts as $n) { echo '<td>'.number_format_i18n($n).'</td>'; }echo '</tr></tbody></table><p>Reported media renders visible for at least one second. Source uses the embedding page referrer; blocked referrers are unknown. Repeated rotations count again. Previews and failed media do not count. These are anonymous browser reports, not verified views or audience size. Website records are retained for 90 days; historical website impressions are unavailable.</p>';
    }
}
