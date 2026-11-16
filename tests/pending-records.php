<?php
require '/wordpress/wp-load.php';wp_set_current_user(1);
$f=json_decode(file_get_contents('/wordpress/wp-content/hwads-native-fixture.json'),true);
$playlist=HPADS_Core::get($f['playlist'],'playlist');$version=(int)$playlist['data']['published_version'];$location=HPADS_Core::get($playlist['parent_id'],'location');
$id=9001;$request=wp_generate_uuid4();$key='hwads_pending_snapshot_'.$id;
update_option($key,['id'=>$request,'status'=>'pending','requested_at'=>time()],false);
$p=['play_id'=>wp_generate_uuid4(),'placement_id'=>(int)$f['placement'],'version_id'=>$version,'seq'=>1,'elapsed_ms'=>3000,'started_at'=>time(),'outcome'=>'playing','engine'=>'browser','grant'=>HPADS_Analytics::grant(['id'=>$id],$location,$version)];
function pending_check($ok,$name){if(!$ok)throw new Exception($name);echo "PASS: $name\n";}
HPADS_Analytics::receive_queue($id,['request_id'=>'wrong','pending'=>1,'records'=>[$p]]);
pending_check(get_option($key)['status']==='pending','mismatched request ignored');
HPADS_Analytics::receive_queue($id,['request_id'=>$request,'pending'=>7,'records'=>[$p]]);
$r=get_option($key);pending_check($r['status']==='completed' && $r['data']['pending']===7,'matching snapshot stored');
pending_check($r['data']['records'][0]['verified'] && !empty($r['data']['records'][0]['title']),'published attribution resolves placement name');
pending_check(!isset($r['data']['records'][0]['grant']),'credentials excluded from saved diagnostics');
update_option($key,['id'=>$request,'status'=>'pending'],false);$p['grant']=[];
HPADS_Analytics::receive_queue($id,['request_id'=>$request,'pending'=>1,'records'=>[$p]]);
pending_check(get_option($key)['data']['records'][0]['verified']===false,'malformed attribution remains unverified');
update_option($key,['id'=>$request,'status'=>'pending'],false);
HPADS_Analytics::receive_queue($id,['request_id'=>$request,'pending'=>51,'records'=>array_fill(0,51,$p)]);
pending_check(get_option($key)['status']==='pending','oversized snapshots rejected');
echo "Pending records checks passed.\n";
