<?php
$_COOKIE['playground_auto_login_already_happened']='1';require '/wordpress/wp-load.php';wp_set_current_user(1);
$n=0;function library_ok($value,$label){global $n;if(!$value)throw new Exception('FAIL: '.$label);$n++;echo "PASS: $label\n";}
$_GET=['post_type'=>'hpads_placement','hwads_ads'=>'all'];
$all=HPADS_Ad_Library::rows();$templates=HPADS_Ad_Library::rows('templates');$placements=HPADS_Ad_Library::rows('placements');
library_ok(count($templates)>0 && count($placements)>0,'fixture contains both ad types');
library_ok(count($all)===count($templates)+count($placements),'All contains both types without duplicates');
foreach($templates as $r)library_ok(HPADS_Placements::is_template($r),'template tab contains only templates');
foreach($placements as $r)library_ok(!HPADS_Placements::is_template($r),'placement tab contains only placements');
$q=new WP_Query();HPADS_Ad_Library::scope($q);library_ok($q->get('post_type')===['hpads_template','hpads_placement'],'native list queries both post types');
library_ok(count($q->get('post__in'))===count($all),'native list IDs exactly match authorized rows');
HPADS_Ad_Library::restore_list_type([],$q);library_ok($q->get('post_type')==='hpads_placement','native form retains scalar post type after mixed SQL query');
ob_start();$views=HPADS_Ad_Library::views([]);$intro=ob_get_clean();library_ok(array_keys($views)===['all','templates','placements'] && strpos($intro,'Add Template')!==false && strpos($intro,'Add Placement')!==false,'native tabs and both create actions render');
$columns=HPADS_Ad_Library::columns(['title'=>'Name','date'=>'Date']);library_ok(isset($columns['hwads_ad_type']),'mixed list distinguishes ad types');
library_ok(strpos(HPADS_Admin::url('templates'),'hwads_ads=templates')!==false && strpos(HPADS_Admin::url('creative'),'hwads_ads=all')!==false,'old navigation helpers target the library');
$pid=HPADS_Storage::post_id($templates[0]['id']);library_ok(strpos(HPADS_Admin::url('templates',$templates[0]['id']),'post='.$pid)!==false,'template edit link retains its native record editor');
$u=wp_create_user('library-no-access','testing-only-password','library-no-access@example.test');wp_set_current_user($u);
library_ok(HPADS_Ad_Library::rows()===[],'unauthorized user receives no records');$q=new WP_Query();HPADS_Ad_Library::scope($q);library_ok($q->get('post__in')===[0],'empty permissions cannot fall back to all posts');
wp_set_current_user(1);$_GET=[];echo "AD LIBRARY TOTAL: $n checks passed\n";
