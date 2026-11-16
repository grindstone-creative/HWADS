<?php
require '/wordpress/wp-load.php';wp_set_current_user(1);
function purge_check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
$id=9998;$token=wp_generate_uuid4();$key='hwads_queue_purge_'.$id;
update_option($key,['id'=>$token,'records'=>[['play_id'=>wp_generate_uuid4(),'seq'=>1]],'status'=>'pending','requested_at'=>time(),'requested_by'=>1],false);
$request=new WP_REST_Request('POST');$request->set_param('_hpads_authenticated_device',$id);$request->set_header('Content-Type','application/json');
$body=['schema'=>1,'plays'=>[],'health'=>['pending'=>1]];$request->set_body(wp_json_encode($body));$response=HPADS_Analytics::ingest($request)->get_data();purge_check($response['queue_purge']['id']===$token,'pending command delivered only in authenticated device response');
$body['purge_ack']=['id'=>'wrong','deleted'=>1];$request->set_body(wp_json_encode($body));HPADS_Analytics::ingest($request);purge_check(get_option($key)['status']==='pending','mismatched purge receipt ignored');
$body['purge_ack']['id']=$token;$request->set_body(wp_json_encode($body));$response=HPADS_Analytics::ingest($request)->get_data();purge_check($response['queue_purge']===null&&get_option($key)['deleted']===1,'matching receipt completes command');
update_option($key,['id'=>$token,'records'=>[],'status'=>'pending','requested_at'=>time()-3601],false);unset($body['purge_ack']);$request->set_body(wp_json_encode($body));purge_check(HPADS_Analytics::ingest($request)->get_data()['queue_purge']===null,'expired command not delivered');
echo "QUEUE PURGE: 4 checks passed\n";
