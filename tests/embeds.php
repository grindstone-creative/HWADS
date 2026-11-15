<?php
// Appended to disposable native editor integration tests.
$embed_start=$checks;wp_set_current_user(0);
$public_playlist=HPADS_Embeds::payload('playlist',$playlist['id']);ok($public_playlist && count($public_playlist['items'])===1,'anonymous playlist embed reads published snapshot');
$public_placement=HPADS_Embeds::payload('placement',$placement['id']);ok($public_placement && $public_placement['items'][0]['title']==='Renamed on playlist card','placement embed retains published title after private edits');
ok(strpos(wp_json_encode($public_placement),'Private changed draft')===false && strpos(wp_json_encode($public_placement),'key_hash')===false,'embed payload does not expose draft content or credentials');
ok(HPADS_Embeds::payload('placement',$template['id'])===null,'template cannot be embedded as a placement');ok(!shortcode_exists('hwads_template'),'no template shortcode is registered');
$tag=do_shortcode('[hwads_playlist id="'.$playlist['id'].'"]');ok(strpos($tag,'<iframe')!==false && strpos($tag,'hwads_embed=playlist')!==false,'playlist shortcode generates public iframe');
$single=do_shortcode('[hwads_placement id="'.$placement['id'].'" ratio="9/16"]');ok(strpos($single,'aspect-ratio:9/16')!==false && strpos($single,'hwads_embed=placement')!==false,'placement shortcode generates portrait iframe');
ok(do_shortcode('[hwads_placement id="'.$template['id'].'"]')==='','template ID cannot bypass public shortcode restrictions');
$draft=record('playlist','Private unpublished', $l['id'],['purpose'=>'primary','items'=>[]]);ok(do_shortcode('[hwads_playlist id="'.$draft['id'].'"]')==='','unpublished playlist shortcode outputs no draft information');
$invalid=HPADS_Embeds::frame('playlist',$playlist['id'],'1;position:fixed');ok(strpos($invalid,'aspect-ratio:16/9')!==false && strpos($invalid,'position:fixed')===false,'embed ratio validation prevents arbitrary styling injection');
ok(HPADS_Embeds::active(['start'=>'2026-01-01T00:00:00Z','end'=>'2026-01-02T00:00:00Z'],strtotime('2026-01-01T12:00:00Z')),'embed honors active scheduling window');ok(!HPADS_Embeds::active(['end'=>'2026-01-02T00:00:00Z'],strtotime('2026-01-02T00:00:00Z')),'embed end time is exclusive');
wp_set_current_user(1);ob_start();HPADS_Embeds::box(get_post($playlist_pid));$box=ob_get_clean();ok(strpos($box,'[hwads_playlist')!==false && strpos($box,'id=&quot;'.$playlist['id'].'&quot;')!==false,'editor supplies ready-to-copy shortcode with logical ID');
file_put_contents('/wordpress/wp-content/hwads-embed-fixture.json',wp_json_encode(['playlist'=>$playlist['id'],'placement'=>$placement['id'],'template'=>$template['id'],'unpublished'=>$draft['id'],'payload'=>$public_playlist]));
echo 'EMBEDS: '.($checks-$embed_start)." checks passed; TOTAL: $checks checks passed\n";

wp_set_current_user(1);
$_SERVER['REQUEST_METHOD']='GET';$_GET=['page'=>'hpads-dashboard'];$_REQUEST=$_GET;
delete_user_meta(1,'hwads_dashboard_view');ob_start();HPADS_Admin::dashboard();$dashboard=ob_get_clean();
ok(strpos($dashboard,'<h2>My Ad Placements</h2>')!==false && strpos($dashboard,'Device type')===false,'dashboard defaults to placements without device list');
$_SERVER['REQUEST_METHOD']='POST';$_POST=['hwads_dashboard_view'=>'device','_wpnonce'=>wp_create_nonce('hwads_dashboard_preferences')];$_REQUEST=array_merge($_GET,$_POST);
ob_start();HPADS_Admin::dashboard();$dashboard=ob_get_clean();
ok(get_user_meta(1,'hwads_dashboard_view',true)==='device' && strpos($dashboard,'<h2>Devices</h2>')!==false,'dashboard choice saved to current user');
wp_set_current_user(2);$_SERVER['REQUEST_METHOD']='GET';$_POST=[];$_REQUEST=$_GET;
ob_start();HPADS_Admin::dashboard();$dashboard=ob_get_clean();
ok(strpos($dashboard,'<h2>My Ad Placements</h2>')!==false,'dashboard preference is isolated per user');
wp_set_current_user(1);$_GET=[];$_POST=[];$_REQUEST=[];
echo "DASHBOARD: 3 checks passed.\n";

ok(HPADS_Native_Editor::publishing(['save'=>'Update','original_post_status'=>'publish','post_status'=>'publish']),'native Update button recognized as playlist publication');
ok(!HPADS_Native_Editor::publishing(['save'=>'Save Draft','original_post_status'=>'draft','post_status'=>'draft']),'native Save Draft remains unpublished');

wp_set_current_user(1);$_POST=[];$_GET=[];$_REQUEST=[];
$health_fixture=json_decode(file_get_contents('/wordpress/wp-content/hwads-native-fixture.json'),true);
$device=HPADS_Post_Types::record($health_fixture['device_pid']);
update_option('hwads_purge_'.$device['id'],['id'=>'test-command-1','status'=>'pending','requested_at'=>time()],false);
HPADS_Device_Health::acknowledge($device['id'],['purge_command_id'=>'wrong']);ok(HPADS_Device_Health::command($device['id'])['status']==='pending','wrong cleanup acknowledgement cannot complete command');
$heartbeat=new WP_REST_Request('POST','/hpads/v1/devices/'.$device['id'].'/heartbeat');$heartbeat->set_header('content-type','application/json');$heartbeat->set_param('_hpads_authenticated_device',$device['id']);$heartbeat->set_body(wp_json_encode(['active_version'=>0,'state'=>'playing','engine'=>'browser','placement_id'=>91,'disk_free_bytes'=>1000000000,'last_sync_utc'=>time(),'player_version'=>'HWADS-Pi/0.2.0','sync_version'=>'0.2.0']));
$heartbeat->set_param('_hpads_authenticated_device',$device['id']);$response=HPADS_API::heartbeat($heartbeat);ok($response instanceof WP_REST_Response && $response->get_status()===200 && $response->get_data()['commands'][0]['id']==='test-command-1','authenticated heartbeat delivers only that device cleanup request');
ob_start();HPADS_Device_Health::render($device);$health=ob_get_clean();ok(strpos($health,'browser')!==false && strpos($health,'Free disk space')!==false && strpos($health,'Purge unused media')!==false,'native device health renders telemetry and cleanup button');
HPADS_Device_Health::acknowledge($device['id'],['purge_command_id'=>'test-command-1','purge_files'=>3,'purge_bytes'=>1024]);ok(HPADS_Device_Health::command($device['id'])['status']==='completed','matching device acknowledgement completes cleanup with results');
ok(isset(HPADS_Placements::TRANSITIONS['wipe-diagonal']) && isset(HPADS_Placements::TRANSITIONS['wipe-circle']),'lightweight wipe choices are available to templates and placements');
echo "DEVICE HEALTH: 5 checks passed.\n";
