<?php
if (!defined('ABSPATH')) { exit; }
/** Scoped design work and explicit item access, separate from ownership/publication. */
final class HPADS_Content_Access {
    private static $forms=[];
    static function init() {
        add_action('admin_post_hwads_content_share',[__CLASS__,'handle']);
        add_action('admin_post_hwads_shared_copy',[__CLASS__,'handle_copy']);
        add_action('admin_footer',[__CLASS__,'forms']);
        add_action('add_meta_boxes',function($type){if(in_array($type,['hpads_template','hpads_placement'],true)){add_meta_box('hwads-content-sharing','Sharing',[__CLASS__,'box'],$type,'normal','default');}});
        add_filter('post_row_actions',[__CLASS__,'row_actions'],30,2);
    }
    static function designer() { return current_user_can('hpads_designer') && !HPADS_Workflow::production() && !HPADS_Placements::manager(); }
    static function content_role() { return HPADS_Workflow::production() || HPADS_Placements::manager() || HPADS_Workflow::venue_member() || HPADS_Workflow::advertiser_member() || current_user_can('hpads_designer'); }
    static function owner_scope($type,$id) {
        if (in_array((int)$id,HPADS_Core::assigned($type==='company'?'companies':'advertisers'),true)) { return true; }
        if ($type==='company') {foreach(HPADS_Core::assigned('locations') as $lid){$l=HPADS_Core::get($lid,'location');if($l && (int)$l['parent_id']===(int)$id)return true;}}
        return false;
    }
    static function location_scope($lid) {
        $l=HPADS_Core::get($lid,'location');if(!$l)return false;
        if(in_array((int)$lid,HPADS_Core::assigned('locations'),true) || in_array((int)$l['parent_id'],HPADS_Core::assigned('companies'),true))return true;
        foreach(HPADS_Core::assigned('advertisers') as $aid){foreach(HPADS_Workflow::eligible_locations('advertiser',$aid) as $eligible){if((int)$eligible['id']===(int)$lid)return true;}}
        return false;
    }
    static function design_ad($ad,$write) {
        [$type,$id]=HPADS_Workflow::owner_ref($ad);$d=$ad['data'];
        if(in_array((int)$id,HPADS_Core::assigned($type==='company'?'companies':'advertisers'),true))return true;
        if($type!=='company')return false;
        if(($d['record_type']??'')==='placement') { $p=HPADS_Core::get($d['rotation_id']??0,'playlist');return $p && self::location_scope($p['parent_id']) && (int)HPADS_Core::company($p)===(int)$id; }
        $scope=HPADS_Venue_Workspace::scope($ad);
        if($scope['type']==='location')return in_array($scope['location'],HPADS_Core::assigned('locations'),true);
        return !$write && $scope['type']==='venue' && self::owner_scope('company',$id);
    }
    static function shares($ad) { $pid=HPADS_Storage::post_id($ad['id']);$value=$pid?get_post_meta($pid,'_hwads_content_shares',true):[];return is_array($value)&&isset($value['revision'],$value['grants'])?$value:['revision'=>0,'grants'=>[]]; }
    static function grant($ad,$write=false) {
        if(!current_user_can('hpads_access') || !self::content_role())return false;
        foreach(self::shares($ad)['grants']??[] as $g){
            $match=false;
            if($g['type']==='user')$match=(int)$g['id']===get_current_user_id();
            if(in_array($g['type'],['company','advertiser'],true))$match=in_array((int)$g['id'],HPADS_Core::assigned($g['type']==='company'?'companies':'advertisers'),true);
            if($g['type']==='location'){$l=HPADS_Core::get($g['id'],'location');$match=$l && (in_array((int)$g['id'],HPADS_Core::assigned('locations'),true)||in_array((int)$l['parent_id'],HPADS_Core::assigned('companies'),true));}
            if($match && (!$write || $g['access']==='edit'))return true;
        }
        return false;
    }
    static function manages($ad) {
        if(HPADS_View_As::state() || !current_user_can('hpads_access'))return false;
        if(HPADS_Workflow::production())return true;
        [$type,$id]=HPADS_Workflow::owner_ref($ad);
        if(HPADS_Placements::manager())return HPADS_Placements::author_owner($type,$id) && HPADS_Workflow::can_ad($ad,true);
        return $type==='company'?HPADS_Workflow::venue_admin()&&in_array($id,HPADS_Core::assigned('companies'),true):HPADS_Workflow::advertiser_admin()&&in_array($id,HPADS_Core::assigned('advertisers'),true);
    }
    static function recipient($type,$value) {
        $value=trim((string)$value);
        if($type==='user'){$u=is_email($value)?get_user_by('email',$value):get_user_by('login',$value);return $u && $u->has_cap('hpads_access')?(int)$u->ID:0;}
        if(!in_array($type,['company','advertiser','location'],true))return 0;
        $matches=[];foreach(HPADS_Core::entity_ids($type) as $id){$r=HPADS_Core::get($id,$type);if((ctype_digit($value)&&(int)$value===(int)$id)||strcasecmp($r['title']??'',$value)===0)$matches[]=(int)$id;}
        return count($matches)===1?$matches[0]:0;
    }
    static function label($g) {
        if($g['type']==='user'){$u=get_user_by('id',$g['id']);return $u?$u->display_name.' ('.$u->user_login.')':'Unavailable user';}
        $r=HPADS_Core::get($g['id'],$g['type']);return ($r['title']??'Unavailable team').' · '.['company'=>'Venue team','advertiser'=>'Advertiser team','location'=>'Location team'][$g['type']];
    }
    static function change($ad,$revision,$type,$id,$access) {
        if(!self::manages($ad))return HPADS_Core::error('Only the owner’s authorized administrators can change sharing.',403);
        if(!in_array($type,['user','company','advertiser','location'],true)||!in_array($access,['use','edit','remove'],true))return HPADS_Core::error('Choose a valid recipient and access level.');
        if($type==='user'){$u=get_user_by('id',$id);$exists=$u&&$u->has_cap('hpads_access');}else{$exists=HPADS_Core::get($id,$type);}
        if($type==='user' && $exists && $access!=='remove') { $suitable=false;foreach(['hpads_owner','hpads_team_admin','hpads_producer','hpads_designer','hpads_account_manager','hpads_admin','hpads_venue_admin','hpads_venue_user','hpads_venue','hpads_advertiser_admin','hpads_advertiser_user','hpads_advertiser'] as $role){if($u->has_cap($role)){$suitable=true;break;}}if(!$suitable)return HPADS_Core::error('This user needs a content role before receiving shared access.'); }
        if(!$exists && $access!=='remove')return HPADS_Core::error('Recipient unavailable.');
        $pid=HPADS_Storage::post_id($ad['id']);if(!$pid)return HPADS_Core::error('Sharing requires native record storage.');
        $old=get_post_meta($pid,'_hwads_content_shares',true);$prior=is_array($old)&&isset($old['revision'],$old['grants'])?$old:['revision'=>0,'grants'=>[]];if((int)$revision!==(int)$prior['revision'])return HPADS_Core::error('Sharing changed in another tab. Reload before saving.',409);
        $grants=array_values(array_filter($prior['grants'],function($g)use($type,$id){return $g['type']!==$type||(int)$g['id']!==(int)$id;}));
        if($access!=='remove')$grants[]=['type'=>$type,'id'=>(int)$id,'access'=>$access,'by'=>get_current_user_id(),'at'=>time()];
        if(count($grants)>100)return HPADS_Core::error('Use up to 100 explicit sharing recipients.');
        $next=['revision'=>$prior['revision']+1,'grants'=>$grants];$ok=is_array($old)?update_post_meta($pid,'_hwads_content_shares',$next,$old):add_post_meta($pid,'_hwads_content_shares',$next,true);
        return $ok?true:HPADS_Core::error('Sharing changed. Reload before saving.',409);
    }
    static function handle() {
        $id=absint($_POST['ad_id']??0);check_admin_referer('hwads_content_share_'.$id);$ad=HPADS_Core::get($id,'creative');if(!$ad || !self::manages($ad))wp_die('Sharing unavailable.','HWADS',['response'=>403]);
        $remove=sanitize_text_field(wp_unslash($_POST['remove']??''));
        if($remove){[$type,$rid]=array_pad(explode(':',$remove),2,0);$access='remove';$rid=absint($rid);}else{$type=sanitize_key($_POST['recipient_type']??'');$rid=self::recipient($type,wp_unslash($_POST['recipient']??''));$access=sanitize_key($_POST['access']??'');if(!$rid)HPADS_Admin::fail('Recipient not found or ambiguous. Use the username/email or exact team/location name.');}
        $result=self::change($ad,absint($_POST['share_revision']??0),$type,$rid,$access);if(is_wp_error($result))HPADS_Admin::fail($result->get_error_message());wp_safe_redirect(HPADS_Admin::url('templates', $id));exit;
    }
    static function box($post) {
        $ad=HPADS_Post_Types::record($post->ID);if(!$ad){echo '<p>Save this item before sharing it.</p>';return;}
        [$type,$id]=HPADS_Workflow::owner_ref($ad);$owner=HPADS_Core::get($id,$type);echo '<p><strong>Owner:</strong> '.esc_html($owner['title']??'Unavailable').'</p><p>The owner’s team already has its normal access. Sharing adds access to this item only.</p>';
        if(!self::manages($ad)){echo '<p>The owner’s administrators manage sharing. Shared editing never grants approval, publication or resharing rights.</p><p><a class="button" href="'.esc_url(add_query_arg('item',$ad['id'],HPADS_Admin::url('shared'))).'">Use an independent copy</a></p>';return;}
        $shares=self::shares($ad);$form='hwads-share-'.(int)$ad['id'];self::$forms[$form]=['id'=>$ad['id'],'revision'=>$shares['revision']??0];
        echo '<p><label>Share with <select name="recipient_type" form="'.$form.'"><option value="user">User</option><option value="company">Venue team</option><option value="advertiser">Advertiser team</option><option value="location">Location team</option></select></label> <label>Recipient <input name="recipient" form="'.$form.'" type="text"></label></p><p class="description">Username or email for a user; exact venue, advertiser or location name for a team.</p><p><label>Access <select name="access" form="'.$form.'"><option value="use">Use a copy</option><option value="edit">Edit the draft + use a copy</option></select></label> <button type="submit" form="'.$form.'" class="button">Grant / Update access</button></p><p>This saves sharing only, not unsaved content. Editing resets content approvals; published versions stay unchanged until explicit publication.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Recipient</th><th>Access</th><th>Action</th></tr></thead><tbody>';foreach($shares['grants']??[] as $g){echo '<tr><td>'.esc_html(self::label($g)).'</td><td>'.esc_html($g['access']==='edit'?'Edit the draft + use a copy':'Use a copy').'</td><td><button class="button" type="submit" form="'.$form.'" name="remove" value="'.esc_attr($g['type'].':'.$g['id']).'">Remove access</button></td></tr>';}echo '</tbody></table><p class="description">Removing access does not delete independent copies already made.</p>';
    }
    static function forms(){foreach(self::$forms as $form=>$f){echo '<form id="'.esc_attr($form).'" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_content_share"><input type="hidden" name="ad_id" value="'.(int)$f['id'].'"><input type="hidden" name="share_revision" value="'.(int)$f['revision'].'">';wp_nonce_field('hwads_content_share_'.$f['id']);echo '</form>';}self::$forms=[];}
    static function row_actions($actions,$post){$ad=HPADS_Post_Types::record($post->ID);if(!$ad||$ad['kind']!=='creative'||!self::grant($ad))return $actions;$url=add_query_arg('item',$ad['id'],HPADS_Admin::url('shared'));$actions['shared_copy']='<a href="'.esc_url($url).'">Use a copy</a>';if(!HPADS_Workflow::can_ad($ad,true))$actions['edit']='<a href="'.esc_url($url).'">View shared item</a>';return $actions;}
    static function handle_copy(){ $id=absint($_POST['source_id']??0);check_admin_referer('hwads_shared_copy_'.$id);$ad=HPADS_Core::get($id,'creative');if(!$ad||!HPADS_Workflow::can_ad($ad)||HPADS_View_As::state())wp_die('Shared item unavailable.','HWADS',['response'=>403]);$result=HPADS_Placements::copy($ad,sanitize_key($_POST['copy_mode']??''),absint($_POST['rotation_id']??0),'',sanitize_text_field(wp_unslash($_POST['owner']??'')));if(is_wp_error($result))HPADS_Admin::fail($result->get_error_message());$r=HPADS_Core::get($result);wp_safe_redirect(HPADS_Admin::url(HPADS_Placements::is_template($r)?'templates':'creative',$result));exit; }
    static function page(){
        if(!self::content_role())wp_die('Content access unavailable.','HWADS',['response'=>403]);
        $id=absint($_GET['item']??0);echo '<h2>Shared with me</h2>';
        if(!$id){echo '<p>Items shared with you or your team. Owners keep their originals; copies are independent.</p><table class="widefat striped"><thead><tr><th>Item</th><th>Access</th><th>Action</th></tr></thead><tbody>';$n=0;foreach(HPADS_Core::entity_ids('creative') as $aid){$ad=HPADS_Core::get($aid,'creative');if(!self::grant($ad))continue;$n++;echo '<tr><td>'.esc_html($ad['title']).'</td><td>'.(self::grant($ad,true)?'Edit the draft + use a copy':'Use a copy').'</td><td><a href="'.esc_url(add_query_arg('item',$aid,HPADS_Admin::url('shared'))).'">Open shared item</a></td></tr>';}if(!$n)echo '<tr><td colspan="3">No items have been shared with you.</td></tr>';echo '</tbody></table>';return;}
        $ad=HPADS_Core::get($id,'creative');if(!$ad||!HPADS_Workflow::can_ad($ad))wp_die('Shared item unavailable.','HWADS',['response'=>403]);echo '<h3>'.esc_html($ad['title']).'</h3><p><a class="button" target="_blank" rel="noopener" href="'.esc_url(HPADS_Placements::preview_url($id)).'">Preview content</a> ';if(HPADS_Workflow::can_ad($ad,true))echo '<a class="button" href="'.esc_url(HPADS_Admin::url(HPADS_Placements::is_template($ad)?'templates':'creative',$id)).'">Edit the draft</a>';echo '</p>';
        $owners=HPADS_Ads::owners(true);if(!$owners){echo '<p>You need an assigned owner company before creating an independent copy. Shared draft editing does not require access to the owner’s other content.</p>';return;}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_shared_copy"><input type="hidden" name="source_id" value="'.$id.'">';wp_nonce_field('hwads_shared_copy_'.$id);HPADS_Admin::select('Owner for the new copy','owner',$owners,array_key_first($owners));HPADS_Admin::select('Copy as','copy_mode',['template'=>'Template','placement'=>'Placement'],'template');$dest=[0=>'— Choose for a placement copy —'];foreach($owners as $ref=>$name){[$t,$oid]=explode(':',$ref);$dest+=HPADS_Placements::rotations($t,(int)$oid);}HPADS_Admin::select('Destination playlist (placement copies)','rotation_id',$dest,0);echo '<p>Copies preserve artwork, not source approvals, schedules or sharing grants. Destination ownership and eligibility are checked when copying.</p>';submit_button('Create independent copy');echo '</form>';
    }
}
