<?php
require '/wordpress/wp-load.php';wp_set_current_user(1);
$f=json_decode(file_get_contents('/wordpress/wp-content/hwads-native-fixture.json'),true);$p=HPADS_Core::get($f['playlist'],'playlist');$id=(int)$p['data']['published_version'];
$link=HPADS_Admin::revision_link($id);if(strpos($link,'revision='.$id)===false || strpos($link,'Playlist revision #'.$id)===false)throw new Exception('Missing revision link');
ob_start();HPADS_Admin::revision_page($id);$html=ob_get_clean();if(strpos($html,'Read-only published snapshot')===false || strpos($html,'<table')===false)throw new Exception('Missing snapshot');
$manifest=HPADS_Core::version($id);foreach($manifest['items'] as $item){$url=esc_url(HPADS_Admin::url('creative',$item['placement_id']));if(strpos($html,'href="'.$url.'"')===false)throw new Exception('Missing placement editor link');}
$u=wp_create_user('revision_foreign',wp_generate_password());(new WP_User($u))->set_role('hpads_advertiser_user');wp_set_current_user($u);if(strpos(HPADS_Admin::revision_link($id),'<a')!==false)throw new Exception('Foreign revision link exposed');
echo 'Revision link checks passed.';
