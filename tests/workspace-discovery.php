<?php
require '/wordpress/wp-load.php';wp_set_current_user(1);require_once ABSPATH.'wp-admin/includes/admin.php';set_current_screen('dashboard');require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';require_once dirname(HPADS_FILE).'/includes/list-table.php';
$f=json_decode(file_get_contents('/wordpress/wp-content/hwads-native-fixture.json'),true);$l=HPADS_Post_Types::record($f['location_pid']);$v=HPADS_Core::get($l['parent_id'],'company');
$columns=apply_filters('manage_hpads_venue_posts_columns',[]);if($columns['hpads_parent']!=='Locations')throw new Exception('Missing Locations heading');
ob_start();HPADS_Post_Types::column('hpads_parent',HPADS_Storage::post_id($v['id']));$html=ob_get_clean();if(strpos($html,esc_url(HPADS_Venue_Workspace::url($l['id'])))===false || strpos($html,'workspace=')!==false)throw new Exception('Missing consolidated venue dashboard link');
$table=new HPADS_List_Table('company');if(!isset($table->get_columns()['locations']) || strpos($table->column_default($v,'locations'),esc_url(HPADS_Venue_Workspace::url($l['id'])))===false)throw new Exception('Missing consolidated dashboard location link');
$u=wp_create_user('discovery_foreign',wp_generate_password());(new WP_User($u))->set_role('hpads_advertiser_user');wp_set_current_user($u);if(HPADS_Venue_Workspace::location_links($v['id'])!=='')throw new Exception('Unrelated venue exposed');
echo 'Workspace discovery checks passed.';
