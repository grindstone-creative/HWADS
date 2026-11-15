<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_API {
    static function routes() {
        foreach (['devices','locations','venues'] as $type) {
            register_rest_route('hpads/v1','/'.$type.'/(?P<id>\d+)/playlist',[
                'methods'=>'GET','callback'=>[__CLASS__,'playlist'],'permission_callback'=>[__CLASS__,'auth']
            ]);
        }
        register_rest_route('hpads/v1','/devices/(?P<id>\d+)/heartbeat',[
            'methods'=>'POST','callback'=>[__CLASS__,'heartbeat'],'permission_callback'=>[__CLASS__,'auth']
        ]);
    }
    static function auth($request) {
        if (!is_ssl()) { return HPADS_Core::error('Device API requires HTTPS. Configure WordPress HTTPS/proxy detection.',403); }
        $id=strpos($request->get_route(),'/devices/')!==false ? absint($request['id']) : absint($request->get_param('device_id'));
        $d=HPADS_Core::get($id,'device');
        $header=$request->get_header('authorization');
        $token=preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D',(string)$header,$m) ? $m[1] : '';
        if (!$d || empty($d['data']['enabled']) || empty($d['data']['key_hash']) || !$token || !hash_equals($d['data']['key_hash'],hash('sha256',$token))) { return HPADS_Core::error('Invalid device credentials.',401); }
        if (strpos($request->get_route(),'/locations/')!==false && (int)$d['parent_id']!==(int)$request['id']) { return HPADS_Core::error('Wrong device location.',403); }
        if (strpos($request->get_route(),'/venues/')!==false && HPADS_Core::company($d)!==(int)$request['id']) { return HPADS_Core::error('Wrong device venue.',403); }
        $request->set_param('_hpads_authenticated_device',$id);
        return true;
    }
    static function playlist($request) {
        $d=HPADS_Core::get($request->get_param('_hpads_authenticated_device'),'device');
        $l=HPADS_Core::get($d['parent_id'],'location');
        if (!$l) { return HPADS_Core::error('Device location is unavailable.',404); }
        $versions=HPADS_Core::selected($d);
        $payload=['schema'=>1,'device_id'=>(int)$d['id'],'device_settings_url'=>HPADS_Device_QR::url($d['id']),'orientation'=>$d['data']['orientation'] ?? 'horizontal','device_type'=>$d['data']['type'],'custom_type'=>$d['data']['custom_type'] ?? '', 'location_id'=>(int)$l['id'],'venue_company_id'=>(int)$l['parent_id'],'heartbeat_seconds'=>HPADS_Core::interval(),'house_in_rotation'=>!empty($l['data']['house_rotation']),'primary'=>null,'house'=>null];
        foreach ($versions as $slot=>$version) {
            $manifest=HPADS_Core::version($version);
            if ($manifest) {
                $manifest['items']=array_values(array_filter($manifest['items'],function($item) use($payload) { return ($item['orientation'] ?? '')===$payload['orientation']; }));
                $manifest['filtered_for_orientation']=$payload['orientation'];
            }
            $payload[$slot]=$manifest;
        }
        $tag='"'.hash('sha256',wp_json_encode($payload)).'"';
        $r=new WP_REST_Response($request->get_header('if-none-match')===$tag ? null : $payload,$request->get_header('if-none-match')===$tag ? 304 : 200);
        $r->header('ETag',$tag); return $r;
    }
    static function heartbeat($request) {
        global $wpdb;
        if (strlen($request->get_body())>4096) { return HPADS_Core::error('Heartbeat exceeds 4 KB.',413); }
        $d=HPADS_Core::get($request->get_param('_hpads_authenticated_device'),'device');
        if (!$d) { return HPADS_Core::error('Invalid device credentials.',401); }
        $table=$wpdb->prefix.'hpads_status'; $now=time();
        $last=(int)$wpdb->get_var($wpdb->prepare("SELECT last_seen FROM $table WHERE device_id=%d",$d['id']));
        if ($last && $now-$last<60) { $r=new WP_REST_Response(['error'=>'Please wait before sending another heartbeat.'],429); $r->header('Retry-After',(string)(60-($now-$last))); return $r; }
        $body=$request->get_json_params();
        if (!is_array($body)) { return HPADS_Core::error('Send a JSON object.'); }
        $active=absint($body['active_version'] ?? 0);
        if ($active) {
            $v=HPADS_Core::version($active); $allowed=[];
            foreach (HPADS_Core::selected($d) as $id) { $m=HPADS_Core::version($id); if ($m) { $allowed[]=$m['playlist_id']; } }
            if (!$v || !in_array($v['playlist_id'],$allowed,true)) { return HPADS_Core::error('Active version is not assigned to this device.'); }
        }
        $payload=['active_version'=>$active,'player_version'=>substr(sanitize_text_field($body['player_version'] ?? ''),0,80),'state'=>in_array($body['state'] ?? '',['playing','downloading','idle','error'],true)?$body['state']:'idle','error'=>substr(sanitize_text_field($body['error'] ?? ''),0,500)];
        foreach (['placement_id','last_sync_utc','disk_free_bytes'] as $field) { if (isset($body[$field]) && is_numeric($body[$field])) { $payload[$field]=max(0,min(PHP_INT_MAX,(int)$body[$field])); } }
        $payload['engine']=in_array($body['engine'] ?? '',['browser','mpv'],true)?$body['engine']:'unknown';$payload['sync_version']=substr(sanitize_text_field($body['sync_version'] ?? ''),0,80);
        HPADS_Device_Health::acknowledge($d['id'],$body);
        $ok=$wpdb->replace($table,['device_id'=>$d['id'],'last_seen'=>$now,'payload'=>wp_json_encode($payload)]);
        if ($ok===false) { return HPADS_Core::error('Cannot record heartbeat.',500); }
        $command=HPADS_Device_Health::command($d['id']);
        return new WP_REST_Response(['commands'=>($command['status'] ?? '')==='pending'?[['type'=>'purge_unused_media','id'=>$command['id']]]:[],'ok'=>true,'server_time'=>gmdate('c'),'next_heartbeat_seconds'=>HPADS_Core::interval(),'jitter_seconds'=>30,'playlist_url'=>rest_url('hpads/v1/devices/'.$d['id'].'/playlist')]);
    }
    static function no_cache($response,$server,$request) {
        if (strpos($request->get_route(),'/hpads/v1/')===0) {
            $response->header('Cache-Control','private, no-store, max-age=0');
            $response->header('Vary','Authorization');
        }
        return $response;
    }
}
