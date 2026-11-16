<?php
require '/wordpress/wp-load.php';wp_set_current_user(1);
$n=0;function thumb_ok($yes,$label){global $n;if(!$yes)throw new Exception('FAIL: '.$label);$n++;echo "PASS: $label\n";}
$f=json_decode(file_get_contents('/wordpress/wp-content/hwads-native-fixture.json'),true);
foreach (['hpads_template','hpads_placement'] as $type) { $cols=apply_filters('manage_'.$type.'_posts_columns',[]);thumb_ok(isset($cols['hpads_preview']) && isset($cols['title']),'native '.$type.' list includes preview and name'); }
$cols=apply_filters('manage_hpads_device_posts_columns',[]);thumb_ok(!isset($cols['hpads_preview']),'device list stays unchanged');
foreach (['template','placement'] as $kind) { $r=HPADS_Core::get($f[$kind]);$html=HPADS_Post_Types::preview($r);thumb_ok(strpos($html,'<img')!==false && strpos($html,'object-fit:contain')!==false,'uncropped '.$kind.' image thumbnail renders');thumb_ok(strpos($html,'post.php')!==false,'thumbnail links to native editor'); }
$r=HPADS_Core::get($f['template']);$r['data']['attachment_id']=0;thumb_ok(strpos(HPADS_Post_Types::preview($r),'No media')!==false,'missing media has fallback');
$uid=wp_create_user('thumbnail_foreign',wp_generate_password(20));$u=new WP_User($uid);$u->set_role('hpads_advertiser_user');wp_set_current_user($uid);thumb_ok(HPADS_Post_Types::preview(HPADS_Core::get($f['template']))==='','unauthorized preview does not expose media');wp_set_current_user(1);
$upload=wp_upload_dir();$file=$upload['path'].'/thumb-test.mp4';file_put_contents($file,'fixture');$vid=wp_insert_attachment(['post_mime_type'=>'video/mp4','post_title'=>'Video fixture','post_status'=>'inherit'],$file);$r=HPADS_Core::get($f['template']);$r['data']['attachment_id']=$vid;thumb_ok(strpos(HPADS_Post_Types::preview($r),'dashicons-video-alt3')!==false,'video without a saved poster gets a video fallback');
set_post_thumbnail($vid,$f['attachment']);thumb_ok(strpos(HPADS_Post_Types::preview($r),'<img')!==false,'video with saved poster uses that image');
echo "THUMBNAILS TOTAL: $n checks passed\n";
