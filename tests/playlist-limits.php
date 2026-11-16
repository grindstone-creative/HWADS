<?php
$_COOKIE['playground_auto_login_already_happened']='1';require '/wordpress/wp-load.php';wp_set_current_user(1);
$limit_checks=0;function limit_ok($value,$label){global $limit_checks;if(!$value)throw new Exception('FAIL: '.$label);$limit_checks++;echo "PASS: $label\n";}
$now=strtotime('2030-01-01T00:00:00Z');$enabled=['active'=>true,'start'=>'','end'=>''];
foreach (['ads'=>15,'menus'=>5,'community'=>5,'house'=>15,'fallback'=>15] as $type=>$cap) {
 $p=['data'=>['category'=>$type]];
 limit_ok(HPADS_Playlist_Limits::cap($p)===$cap,$type.' has correct active cap');
 limit_ok(HPADS_Playlist_Limits::validate($p,array_fill(0,$cap,$enabled))===true,$type.' accepts exact active limit');
 limit_ok(is_wp_error(HPADS_Playlist_Limits::validate($p,array_fill(0,$cap+1,$enabled))),$type.' rejects excess active placements');
}
$p=['data'=>['category'=>'ads']];$inactive=array_fill(0,100,['active'=>false]);
limit_ok(HPADS_Playlist_Limits::validate($p,$inactive)===true,'100 inactive placements accepted');$inactive[]=['active'=>false];limit_ok(is_wp_error(HPADS_Playlist_Limits::validate($p,$inactive)),'101 inactive placements rejected');
limit_ok(HPADS_Playlist_Limits::category(['data'=>['purpose'=>'primary']])==='ads' && HPADS_Playlist_Limits::category(['data'=>['purpose'=>'house']])==='house','existing playlists have deterministic compatible categories');
$morning=['active'=>true,'start'=>'2030-01-01T08:00','end'=>'2030-01-01T12:00'];$evening=['active'=>true,'start'=>'2030-01-01T12:00','end'=>'2030-01-01T18:00'];
$rows=array_merge(array_fill(0,15,$morning),array_fill(0,15,$evening));$stats=HPADS_Playlist_Limits::stats($rows);limit_ok($stats['total']===30 && $stats['active']===30,'non-overlapping scheduled placements still count every Active switch');
limit_ok(is_wp_error(HPADS_Playlist_Limits::validate($p,$rows)),'scheduling cannot bypass a 15-switch playlist limit');
limit_ok(HPADS_Playlist_Limits::stats([['active'=>true,'end'=>'2000-01-01T00:00'],['active'=>false]])['active']===1,'expired slides count until their Active switch is turned off');
$custom=['data'=>['category'=>'house','active_limit'=>3]];limit_ok(HPADS_Playlist_Limits::cap($custom)===3 && is_wp_error(HPADS_Playlist_Limits::validate($custom,array_fill(0,4,$enabled))),'House playlist uses its own configured maximum');
foreach ([0,-1,101,'banana',2.5] as $bad_limit) {limit_ok(is_wp_error(HPADS_Playlist_Limits::settings(['active_limit'=>$bad_limit],[])),'invalid per-playlist maximum rejected: '.(string)$bad_limit);}
limit_ok(HPADS_Playlist_Limits::settings(['category'=>'menus'],[])['active_limit']===5,'new menu playlist suggests five active slots');
limit_ok(HPADS_Playlist_Limits::settings(['active_limit'=>7],['category'=>'fallback'])['active_limit']===7,'fallback maximum is set in that playlist editor');
$f=json_decode(file_get_contents('/wordpress/wp-content/hwads-native-fixture.json'),true);$source=HPADS_Core::get($f['placement'],'creative');$existing=HPADS_Core::get($f['playlist'],'playlist');$location=HPADS_Core::get($existing['parent_id'],'location');
$pid=HPADS_Core::put('playlist','Capacity fixture',$location['id'],['purpose'=>'primary','category'=>'menus','items'=>[]]);$playlist=HPADS_Core::get($pid,'playlist');$refs=[];
for($i=0;$i<6;$i++) {
 $data=$source['data'];$data['owner_type']='company';$data['owner_id']=$location['parent_id'];$data['locations']=[$location['id']];$data['rotation_id']=$pid;$data['active']=$i<5;$data['start']='';$data['end']='';$data['duration']=null;$data=HPADS_Approvals::authored($data);
 $id=HPADS_Core::put('creative','Capacity item '.$i,0,$data);$refs[]=['creative_id'=>$id];
}
$d=$playlist['data'];$d['items']=$refs;HPADS_Core::put('playlist',$playlist['title'],$playlist['parent_id'],$d,$pid,$playlist['revision']);$playlist=HPADS_Core::get($pid,'playlist');
limit_ok(HPADS_Playlist_Limits::validate($playlist)===true,'five enabled menu placements plus one inactive is valid');limit_ok(!HPADS_Playlist_Limits::new_active($playlist),'new entry defaults inactive when active slots are full');
$ad=HPADS_Core::get($refs[5]['creative_id'],'creative');$raw=['post_title'=>$ad['title'],'hwads_revision'=>$ad['revision'],'hwads'=>array_merge($ad['data'],['owner'=>'company:'.$location['parent_id'],'record_type'=>'placement','rotation_id'=>$pid,'active'=>true])];
$result=HPADS_Native_Editor::save_record(HPADS_Storage::post_id($ad['id']),$raw);limit_ok(is_wp_error($result),'individual native editor cannot activate a sixth menu');limit_ok(!HPADS_Core::get($ad['id'])['data']['active'],'failed activation leaves canonical placement unchanged');
// Batch swapping which five are active is valid even if iteration temporarily sees six.
$batch=[];foreach(array_reverse($refs) as $i=>$ref){$ad=HPADS_Core::get($ref['creative_id']);$batch[]=array_merge($ref,['revision'=>$ad['revision'],'active'=>$ref['creative_id']!==$refs[4]['creative_id'],'start'=>'','end'=>'']);}
$result=HPADS_Placements::save_playlist($playlist,['revision'=>$playlist['revision'],'parent_id'=>$playlist['parent_id'],'purpose'=>'primary','category'=>'menus','items'=>wp_json_encode($batch)]);limit_ok(!is_wp_error($result),'batch activation/deactivation validates the final state');
$playlist=HPADS_Core::get($pid,'playlist');$before=HPADS_Core::get($refs[4]['creative_id']);$bad=[];foreach($refs as $ref){$ad=HPADS_Core::get($ref['creative_id']);$bad[]=array_merge($ref,['revision'=>$ad['revision'],'active'=>true,'start'=>'','end'=>'']);}
$result=HPADS_Placements::save_playlist($playlist,['revision'=>$playlist['revision'],'parent_id'=>$playlist['parent_id'],'purpose'=>'primary','category'=>'menus','items'=>wp_json_encode($bad)]);limit_ok(is_wp_error($result),'playlist save rejects six enabled menu placements');limit_ok(HPADS_Core::get($before['id'])['data']['active']===false,'failed batch rolls back placement changes');
$version=HPADS_Core::publish($pid);limit_ok(!is_wp_error($version) && count(HPADS_Core::version($version)['items'])===5,'publication freezes a valid five-menu playlist');
// Simulate pre-upgrade over-cap data: block new publication without recalling the old version.
$bad=HPADS_Core::get($refs[4]['creative_id']);$bd=$bad['data'];$bd['active']=true;HPADS_Core::put('creative',$bad['title'],$bad['parent_id'],$bd,$bad['id'],$bad['revision']);
limit_ok(is_wp_error(HPADS_Core::publish($pid)),'publication rejects pre-existing over-cap data');limit_ok(HPADS_Core::get($pid)['data']['published_version']===$version,'invalid draft does not replace the last published snapshot');
$capped=HPADS_Core::get($pid,'playlist');$capped['data']['items']=array_fill(0,100,$refs[0]);limit_ok(is_wp_error(HPADS_Playlist_Limits::room($capped)),'adding a 101st placement is blocked even when all slots could be inactive');
limit_ok(is_wp_error(HPADS_Core::normalize_items(array_fill(0,101,$refs[0]),$location,$pid)),'normalization rejects over-100 membership before publication');
$key='hwads_playlist_lock_'.$pid;add_option($key,['id'=>'other','at'=>time()],'',false);$called=false;$locked=HPADS_Playlist_Limits::locked($pid,function()use(&$called){$called=true;return true;});limit_ok(is_wp_error($locked) && !$called,'competing saves cannot bypass playlist lock');delete_option($key);
$locked=HPADS_Playlist_Limits::locked($pid,function()use($pid){return HPADS_Playlist_Limits::locked($pid,function(){return true;});});limit_ok($locked===true && !get_option($key),'nested save/publication uses one released playlist lock');
echo "PLAYLIST LIMITS TOTAL: $limit_checks checks passed\n";
file_put_contents('/wordpress/wp-content/hwads-limits-fixture.json',wp_json_encode(['playlist'=>$pid,'playlist_pid'=>HPADS_Storage::post_id($pid),'placements'=>$refs]));
