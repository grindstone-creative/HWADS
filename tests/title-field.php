<?php
$_COOKIE['playground_auto_login_already_happened']='1';require '/wordpress/wp-load.php';wp_set_current_user(1);$count=0;
function title_ok($c,$label){global $count;if(!$c)throw new Exception('FAIL: '.$label);$count++;echo "PASS: $label\n";}
$f=json_decode(file_get_contents('/wordpress/wp-content/hwads-native-fixture.json'),true);$ad=HPADS_Core::get($f['placement']);$name='Unified placement name';
title_ok($ad['title']===$name && get_post($f['placement_pid'])->post_title===$name,'one canonical placement name matches WordPress post title');
title_ok(HPADS_Placements::card($ad['id'])['title']===$name,'placement card uses canonical name');
$preview=HPADS_Preview::items($f['playlist']);title_ok($preview[0]['title']===$name,'playlist draft preview uses canonical placement name');
$before=HPADS_Placements::published_item($ad['id']);title_ok($before['title']!==$name,'renaming draft does not rewrite already-published title');
$version=HPADS_Core::publish($f['playlist']);title_ok(!is_wp_error($version),'renamed placement publishes successfully');
$item=HPADS_Placements::published_item($ad['id']);title_ok($item['title']===$name && HPADS_Core::version($version)['items'][0]['title']===$name,'published landing and device manifest use canonical name');
$embed=HPADS_Embeds::payload('placement',$ad['id']);title_ok($embed['items'][0]['title']===$name,'website embed receives same published name');
HPADS_Landing_Layout::$context=$item;title_ok(do_shortcode('[hwads_ad_title heading="1"]')==='<h1>'.$name.'</h1>','landing page heading shortcode uses same published name');HPADS_Landing_Layout::$context=null;
echo "TITLE PROPAGATION TOTAL: $count checks passed\n";
