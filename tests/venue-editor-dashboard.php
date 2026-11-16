<?php
if(!defined('WP_ADMIN'))define('WP_ADMIN',true);require '/wordpress/wp-load.php';require_once ABSPATH.'wp-admin/includes/admin.php';wp_set_current_user(1);
$f=json_decode(file_get_contents('/wordpress/wp-content/hwads-dashboard-fixture.json'),true);$v=HPADS_Core::get($f['venue'],'company');$l=HPADS_Core::get($f['location'],'location');
$n=0;function venue_editor_ok($v,$label){global $n;if(!$v)throw new Exception($label);$n++;echo "PASS: $label\n";}
set_current_screen('hpads_venue');$GLOBALS['post']=get_post($f['venue_pid']);$prior=$GLOBALS['post'];$screen=get_current_screen();$query=$GLOBALS['wp_query'];$_GET=['post'=>$f['venue_pid'],'action'=>'edit'];$_REQUEST=$_GET;$get=$_GET;
HPADS_Native_Editor::boxes('hpads_venue');venue_editor_ok(isset($GLOBALS['wp_meta_boxes']['hpads_venue']['side']['default']['hwads-details']),'venue details registered below native Save box in sidebar');
$html=HPADS_Venue_Editor_Dashboard::build($v,'playlists');
venue_editor_ok(strpos($html,'id="hwads-venue-list-title"')!==false && strpos($html,'id="title"')===false,'embedded table cannot shadow native title input');
venue_editor_ok($_GET===$get && $GLOBALS['post']===$prior && get_current_screen()===$screen && $GLOBALS['wp_query']===$query,'list rendering restores venue editor and request state');
venue_editor_ok(strpos($html,'name="post" value="'.$f['venue_pid'].'"')!==false && strpos($html,'name="hwads_tab" value="playlists"')!==false,'filter and search target native venue editor');
venue_editor_ok(strpos($html,'name="action" id="bulk-action-selector')===false,'no unsupported bulk action conflicts with venue editor action');
venue_editor_ok(strpos(HPADS_Venue_Workspace::venue_url($v['id']),'post='.$f['venue_pid'])!==false,'venue dashboard navigation leads to combined editor');
venue_editor_ok(HPADS_Venue_Editor_Dashboard::build($v,'playlists',HPADS_Core::get($f['foreign_location'],'location'))==='','foreign location rejected');
wp_set_current_user($f['manager']);venue_editor_ok(HPADS_Venue_Editor_Dashboard::build(HPADS_Core::get($f['other_venue'],'company'),'playlists')==='','foreign venue does not expose embedded records');wp_set_current_user(1);
$target=HPADS_Venue_Workspace::url($l['id']);parse_str(wp_parse_url($target,PHP_URL_QUERY),$params);venue_editor_ok(($params['post'] ?? 0)==$f['venue_pid'] && ($params['hwads_location'] ?? 0)==$l['id'] && !isset($params['hwads_venue']),'location links target same venue editor with its location selected');
venue_editor_ok(!method_exists('HPADS_Venue_Workspace','legacy_workspace') && !method_exists('HPADS_Venue_Workspace','native_header'),'obsolete route and duplicate header removed without redirects');
venue_editor_ok(strpos(HPADS_Venue_Workspace::copy_url(HPADS_Core::get($f['playlist'],'playlist')),'workspace=')===false,'copy form no longer references removed workspace route');
$reader=wp_create_user('consolidated_reader',wp_generate_password());(new WP_User($reader))->set_role('hpads_venue_user');update_user_meta($reader,'hpads_companies',[$v['id']]);wp_set_current_user($reader);venue_editor_ok(!HPADS_Core::can($v,true) && HPADS_Core::can($v),'reader has view permission without venue editing');
$readonly=HPADS_Venue_Editor_Dashboard::build($v,'playlists',$l);venue_editor_ok(strpos($readonly,'hwads-venue-dashboard')!==false && !current_user_can('edit_post',$f['venue_pid']),'same dashboard renders for read-only user without granting venue edits');parse_str(wp_parse_url(HPADS_Venue_Editor_Dashboard::url($v['id']),PHP_URL_QUERY),$rid);venue_editor_ok(($rid['page'] ?? '')==='hpads-company' && (int)($rid['edit'] ?? 0)===(int)$v['id'] && !isset($rid['post']),'read-only canonical URL uses authorized view route');
preg_match_all('/<form[^>]+action="([^"]+)"/',$readonly,$forms);venue_editor_ok(count($forms[1])>=2 && count(array_filter($forms[1],function($url){return basename(wp_parse_url(html_entity_decode($url),PHP_URL_PATH))==='post.php';}))===0,'read-only filters and search never submit to a native edit page');
venue_editor_ok(strpos($readonly,'name="page" value="hpads-company"')!==false && strpos($readonly,'name="edit" value="'.$v['id'].'"')!==false,'read-only forms retain their canonical record identity');
file_put_contents('/wordpress/wp-content/hwads-readonly-venue.json',wp_json_encode(['url'=>HPADS_Venue_Editor_Dashboard::url($v['id']),'venue'=>$v['id'],'location'=>$l['id'],'cookies'=>[AUTH_COOKIE=>wp_generate_auth_cookie($reader,time()+3600,'auth'),LOGGED_IN_COOKIE=>wp_generate_auth_cookie($reader,time()+3600,'logged_in')]]));
wp_set_current_user(1);
$menu=[];$submenu=[];$admin_page_hooks=[];$_registered_pages=[];$_parent_pages=[];HPADS_Admin::menu();venue_editor_ok(!in_array('hpads-playlist',array_column($submenu['hpads-dashboard'],2),true),'redundant standalone Playlists menu removed for venue-access user');
set_current_screen('edit-hpads_playlist');venue_editor_ok(HPADS_Post_Types::menu_section()==='company','playlist editor navigation highlights Venues');
echo "VENUE EDITOR DASHBOARD: $n checks passed\n";
