<?php
if (!defined('ABSPATH')) { exit; }
/** Venue policy and per-reviewer votes. Published manifests remain immutable. */
final class HPADS_Approvals {
    static function placement($ad) { return ($ad['data']['record_type'] ?? '')==='placement'; }
    static function candidates($venue) {
        $out=[];
        foreach (get_users(['orderby'=>'display_name']) as $u) {
            if (!$u->has_cap('hpads_access')) { continue; }
            $global=$u->has_cap('hpads_owner') || $u->has_cap('hpads_team_admin');
            $role=$u->has_cap('hpads_venue_admin') || $u->has_cap('hpads_venue_user') || $u->has_cap('hpads_venue') || $u->has_cap('hpads_account_manager') || $u->has_cap('hpads_admin');
            if ($global || $role && in_array((int)$venue,HPADS_Core::ids(get_user_meta($u->ID,'hpads_companies',true)),true)) { $out[(int)$u->ID]=$u->display_name; }
        }
        return $out;
    }
    static function policy($venue) {
        $v=HPADS_Core::get($venue,'company');$p=$v['data']['ad_approval_policy'] ?? null;
        if (!$p) {
            $ids=[];foreach (self::candidates($venue) as $id=>$name) { $u=get_user_by('id',$id);if (($u->has_cap('hpads_venue_admin') || $u->has_cap('hpads_venue')) && in_array((int)$venue,HPADS_Core::ids(get_user_meta($id,'hpads_companies',true)),true)) { $ids[]=$id; } }
            $p=['required'=>true,'mode'=>'any','reviewers'=>$ids];
        }
        $p=['required'=>!empty($p['required']),'mode'=>($p['mode'] ?? '')==='all'?'all':'any','reviewers'=>HPADS_Core::ids($p['reviewers'] ?? [])];sort($p['reviewers']);
        $p['hash']=hash('sha256',wp_json_encode($p));return $p;
    }
    static function settings($r) {
        $id=(int)($r['id'] ?? 0);
        if (!HPADS_Workflow::global_admin() && !(HPADS_Workflow::venue_admin() && $id && in_array($id,HPADS_Core::assigned('companies'),true))) { echo '<p>Ad approval rules are managed by the venue administrator.</p>';return; }
        $p=self::policy($id);$choices=$id?self::candidates($id):[];
        echo '<h3>Ad approvals</h3><input type="hidden" name="ad_approval_settings" value="1">';
        HPADS_Admin::check('Require approval for outside advertisers','ad_approval_required',$p['required']);
        echo '<p><strong>Who needs to approve?</strong></p>';
        foreach (['any'=>'Any one selected reviewer','all'=>'Every selected reviewer'] as $key=>$label) { echo '<p><label><input type="radio" name="ad_approval_mode" value="'.esc_attr($key).'" '.checked($p['mode'],$key,false).'> '.esc_html($label).'</label></p>'; }
        HPADS_Admin::multi('Selected reviewers','ad_approval_reviewers',$choices,$p['reviewers']);
        if (!$id) { echo '<p>Save this venue, assign its staff in HWADS → Users, then select reviewers here.</p>'; }
        echo '<p class="description">Only selected authorized staff can approve. Every selected reviewer means this list, not all venue users. Changes require pending ads to be resubmitted; already published versions keep playing. SuperAdmin-authored revisions have a separate audited bypass.</p>';
    }
    static function save_policy($in,$r,$d) {
        if (empty($in['ad_approval_settings'])) { return $d; }
        if (HPADS_View_As::state() || !HPADS_Workflow::global_admin() && !(HPADS_Workflow::venue_admin() && $r && in_array((int)$r['id'],HPADS_Core::assigned('companies'),true))) { return HPADS_Core::error('Only this venue’s administrator can change its approval rules.',403); }
        $mode=sanitize_key($in['ad_approval_mode'] ?? 'any');$ids=HPADS_Core::ids($in['ad_approval_reviewers'] ?? []);$required=!empty($in['ad_approval_required']);
        if (!in_array($mode,['any','all'],true) || array_diff($ids,array_keys(self::candidates($r['id'] ?? 0)))) { return HPADS_Core::error('Choose authorized reviewers assigned to this venue.'); }
        if ($required && !$ids && $r) { return HPADS_Core::error('Select at least one reviewer when approval is required.'); }
        $d['ad_approval_policy']=['required'=>$required,'mode'=>$mode,'reviewers'=>$ids];return $d;
    }
    static function authored($d,$previous=null,$title=null) {
        if ($previous && ($title===null || $title===$previous['title'])) {
            $ignore=array_fill_keys(['workflow','content_revision','state','submission_revision','submitted_at','submitted_by','reviews','manager_review','approved_locations','review_history','approval_override'],true);
            if (array_diff_key($d,$ignore)==array_diff_key($previous['data'],$ignore)) { return $previous['data']; }
        }
        unset($d['approval_override']);
        if (($d['record_type'] ?? '')==='placement' && HPADS_Core::owner() && !HPADS_View_As::state()) {
            $d['approval_override']=['revision'=>(int)($d['content_revision'] ?? 1),'by'=>get_current_user_id(),'at'=>gmdate('c'),'reason'=>'SuperAdmin-authored revision'];
            $d['review_history'][]=array_merge(['stage'=>'superadmin_override'],$d['approval_override']);$d['review_history']=array_slice($d['review_history'],-100);
        }
        return $d;
    }
    static function context($ad,$lid) {
        $l=HPADS_Core::get($lid,'location');$d=$ad['data'];[$type,$oid]=HPADS_Workflow::owner_ref($ad);
        $eligible=array_map(function($x){return (int)$x['id'];},HPADS_Workflow::eligible_locations($type,$oid));
        if (!$l || !in_array((int)$lid,$d['locations'] ?? [],true) || !in_array((int)$lid,$eligible,true)) { return ['status'=>'unavailable','label'=>'Destination no longer eligible','reviewers'=>[],'approved'=>0]; }
        $p=self::policy($l['parent_id']);$p['venue']=(int)$l['parent_id'];$p['location']=$l;$p['approved']=0;
        $override=$d['approval_override'] ?? [];
        if (!empty($override['by']) && (int)($override['revision'] ?? 0)===(int)($d['content_revision'] ?? 1)) { return array_merge($p,['status'=>'approved','label'=>'Approved automatically · SuperAdmin revision']); }
        if (!$p['required'] || $type==='company' && (int)$oid===(int)$l['parent_id']) { return array_merge($p,['status'=>'approved','label'=>$p['required']?'Venue’s own ad · no outside review':'Venue does not require approval']); }
        if (!$p['reviewers'] || array_diff($p['reviewers'],array_keys(self::candidates($l['parent_id'])))) { return array_merge($p,['status'=>'setup','label'=>'Venue needs authorized reviewers']); }
        $review=$d['reviews'][(string)$lid] ?? [];
        if ((int)($d['submission_revision'] ?? 0)!==(int)($d['content_revision'] ?? 1) || ($review['policy_hash'] ?? '')!==$p['hash']) { return array_merge($p,['status'=>'draft','label'=>empty($d['submission_revision'])?'Not submitted':'Approval rules changed · resubmit']); }
        $p['votes']=$review['votes'] ?? [];$rejected=false;
        foreach ($p['reviewers'] as $id) { $vote=$p['votes'][(string)$id] ?? [];if ((int)($vote['revision'] ?? 0)!==(int)$d['content_revision']) { continue; }if (($vote['status'] ?? '')==='rejected') { $rejected=true; }if (($vote['status'] ?? '')==='approved') { $p['approved']++; } }
        if ($rejected) { return array_merge($p,['status'=>'rejected','label'=>'Changes requested']); }
        $done=$p['mode']==='all'?$p['approved']===count($p['reviewers']):$p['approved']>0;
        return array_merge($p,['status'=>$done?'approved':'pending','label'=>$done?'Approved':($p['mode']==='all'?$p['approved'].' of '.count($p['reviewers']).' approved':'Awaiting approval · any one reviewer')]);
    }
    static function visible($ad) {
        if (!current_user_can('hpads_access') || !self::placement($ad) || (int)($ad['data']['submission_revision'] ?? 0)!==(int)($ad['data']['content_revision'] ?? 1)) { return false; }
        foreach ($ad['data']['locations'] ?? [] as $lid) { $l=HPADS_Core::get($lid,'location');if ($l && isset(self::candidates($l['parent_id'])[get_current_user_id()]) && in_array(get_current_user_id(),self::policy($l['parent_id'])['reviewers'],true)) { return true; } }
        return false;
    }
    static function can_review($ad,$lid) {
        if (HPADS_View_As::state() || !self::placement($ad)) { return false; }
        $c=self::context($ad,$lid);
        return $c['status']==='pending' && in_array(get_current_user_id(),$c['reviewers'],true);
    }
    static function needs_me($ad,$lid) {
        if (!self::can_review($ad,$lid)) { return false; }
        $c=self::context($ad,$lid);$vote=$c['votes'][(string)get_current_user_id()] ?? [];
        return ($vote['status'] ?? '')!=='approved' || (int)($vote['revision'] ?? 0)!==(int)$ad['data']['content_revision'];
    }
    static function submit($ad) {
        if (HPADS_View_As::state() || !HPADS_Workflow::can_ad($ad,true)) { return HPADS_Core::error('Only the ad owner team or a producer can submit it.',403); }
        $d=$ad['data'];[$type,$oid]=HPADS_Workflow::owner_ref($ad);$rotation=HPADS_Core::get($d['rotation_id'] ?? 0,'playlist');
        if (!$rotation || (!HPADS_Placements::can_bind($type,$oid,$rotation) && !HPADS_Content_Access::grant($ad,true)) || empty($d['attachment_id']) || ($d['locations'] ?? [])!==[(int)$rotation['parent_id']]) { return HPADS_Core::error('Choose eligible media and a playlist destination before submitting.'); }
        $needed=false;
        foreach ($d['locations'] as $lid) { $c=self::context($ad,$lid);if ($c['status']==='setup' || $c['status']==='unavailable') { return HPADS_Core::error($c['label']); }if (in_array($c['status'],['draft','rejected'],true)) { $needed=true; } }
        if (!$needed) { return HPADS_Core::error('This revision is already approved or submitted.'); }
        $d['submission_revision']=(int)$d['content_revision'];$d['submitted_by']=get_current_user_id();$d['submitted_at']=gmdate('c');$d['state']='venue_review';$d['manager_review']=[];$d['approved_locations']=[];$d['reviews']=[];
        foreach ($d['locations'] as $lid) { $l=HPADS_Core::get($lid,'location');$d['reviews'][(string)$lid]=['policy_hash'=>self::policy($l['parent_id'])['hash'],'votes'=>[],'status'=>'pending','revision'=>$d['content_revision']]; }
        $d['review_history'][]=['stage'=>'submitted','revision'=>$d['content_revision'],'by'=>get_current_user_id(),'at'=>gmdate('c')];$d['review_history']=array_slice($d['review_history'],-100);
        return HPADS_Core::put('creative',$ad['title'],$ad['parent_id'],$d,$ad['id'],(int)$ad['revision']);
    }
    static function review($ad,$lid,$decision,$note) {
        if (!self::can_review($ad,$lid)) { return HPADS_Core::error('You are not an authorized pending reviewer for this revision and venue.',403); }
        if (!in_array($decision,['approved','rejected'],true)) { return HPADS_Core::error('Choose approve or request changes.'); }
        $d=$ad['data'];$entry=['status'=>$decision,'by'=>get_current_user_id(),'at'=>gmdate('c'),'revision'=>(int)$d['content_revision'],'note'=>substr(sanitize_textarea_field($note),0,1000)];
        $d['reviews'][(string)$lid]['votes'][(string)get_current_user_id()]=$entry;$d['review_history'][]=array_merge(['stage'=>'venue','location_id'=>(int)$lid],$entry);$d['review_history']=array_slice($d['review_history'],-100);
        $candidate=$ad;$candidate['data']=$d;$c=self::context($candidate,$lid);$d['reviews'][(string)$lid]['status']=$c['status'];$d['approved_locations']=[];
        foreach ($d['locations'] as $target) { if (self::context($candidate,$target)['status']==='approved') { $d['approved_locations'][]=(int)$target; } }
        if ($decision==='rejected') { $d['state']='changes_requested'; }
        return HPADS_Core::put('creative',$ad['title'],$ad['parent_id'],$d,$ad['id'],(int)$ad['revision']);
    }
    static function summary($ad) {
        $labels=[];foreach ($ad['data']['locations'] ?? [] as $lid) { $labels[]=self::context($ad,$lid)['label']; }return $labels?implode(' · ',array_unique($labels)):'Choose a playlist destination';
    }
    static function override($ad) {
        if (!HPADS_Core::owner() || HPADS_View_As::state() || !self::placement($ad)) { return HPADS_Core::error('Only SuperAdmin can bypass review.',403); }
        $d=self::authored($ad['data']);return HPADS_Core::put('creative',$ad['title'],$ad['parent_id'],$d,$ad['id'],(int)$ad['revision']);
    }
    static function controls($ad) {
        if (!HPADS_Workflow::can_ad($ad)) { return; }
        $resubmit=false;
        foreach ($ad['data']['locations'] ?? [] as $lid) {
            $c=self::context($ad,$lid);$l=HPADS_Core::get($lid,'location');if (!$l) { continue; }
            echo '<h3>'.esc_html($l['title']).'</h3><p><strong>'.esc_html($c['label']).'</strong></p>';
            if (isset($c['mode']) && in_array($c['status'],['pending','rejected','approved'],true) && isset($c['votes'])) {
                echo '<p>'.($c['mode']==='all'?'Every selected reviewer':'Any one selected reviewer').'</p><ul>';
                foreach ($c['reviewers'] as $uid) { $u=get_user_by('id',$uid);$v=$c['votes'][(string)$uid] ?? [];echo '<li>'.esc_html(($u?$u->display_name:'Unavailable reviewer').' · '.ucfirst($v['status'] ?? 'pending')).(!empty($v['note'])?' — '.esc_html($v['note']):'').'</li>'; }echo '</ul>';
            }
            if (in_array($c['status'],['draft','rejected'],true)) { $resubmit=true; }
            if (self::can_review($ad,$lid)) {
                HPADS_Admin::begin('creative',$ad,'venue_review');echo '<input type="hidden" name="location_id" value="'.(int)$lid.'"><p><label>Review note (optional)<br><textarea name="review_note" rows="3" class="widefat"></textarea></label></p><p><button type="submit" class="button button-primary" name="decision" value="approved">Approve Ad</button> <button type="submit" class="button" name="decision" value="rejected">Request Changes</button></p></form>';
            }
        }
        if ($resubmit && HPADS_Workflow::can_ad($ad,true) && !HPADS_View_As::state()) { HPADS_Admin::begin('creative',$ad,'submit_ad');echo '<p>Submit the last saved revision. Edits reset approvals.</p>';submit_button('Submit for Approval','primary','submit',false);echo '</form>'; }
        if (HPADS_Core::owner() && !HPADS_View_As::state() && empty($ad['data']['approval_override'])) { HPADS_Admin::begin('creative',$ad,'superadmin_approve');submit_button('Approve This Revision as SuperAdmin','secondary','submit',false);echo '<p class="description">Records an explicit SuperAdmin bypass for this saved revision only.</p></form>'; }
        echo '<p class="description">The published version stays live while changes are reviewed. Approval alone does not publish a playlist.</p>';
    }
    static function rows($mine=false) {
        $rows=[];foreach (HPADS_Core::all('creative') as $ad) { if (!self::placement($ad) || empty($ad['data']['submitted_at']) || empty($ad['data']['submission_revision'])) { continue; }
            foreach ($ad['data']['locations'] ?? [] as $lid) { $l=HPADS_Core::get($lid,'location');if (!$l) { continue; }
                $scoped=HPADS_Workflow::global_staff() || in_array((int)$l['parent_id'],HPADS_Core::assigned('companies'),true);
                if (!$scoped || $mine && !self::needs_me($ad,$lid)) { continue; }$rows[]=['ad'=>$ad,'location'=>$l,'context'=>self::context($ad,$lid)];
            }
        }usort($rows,function($a,$b){return strcmp($b['ad']['data']['submitted_at'],$a['ad']['data']['submitted_at']);});return $rows;
    }
    static function url($ad) { return HPADS_Core::can($ad,true)?HPADS_Admin::url('creative',$ad['id']).'#hwads-approvals':add_query_arg('review',$ad['id'],HPADS_Admin::url('approvals')); }
    static function page() {
        if (!current_user_can('hpads_access')) { HPADS_Admin::fail('Access denied.'); }
        if (!empty($_GET['review'])) {
            $ad=HPADS_Core::get(absint($_GET['review']),'creative');if (!$ad || !self::placement($ad) || !HPADS_Workflow::can_ad($ad)) { HPADS_Admin::fail('Placement unavailable.'); }
            echo '<p><a href="'.esc_url(HPADS_Admin::url('approvals')).'">← Approvals</a></p><h2>'.esc_html($ad['title']).'</h2><p>Reviewing saved content revision '.(int)$ad['data']['content_revision'].'. Review access does not grant editing access.</p>';
            $preview=add_query_arg(['action'=>'hpads_ad_review_preview','id'=>$ad['id']],admin_url('admin-post.php'));
            echo '<iframe title="Saved placement preview" style="width:100%;max-width:900px;aspect-ratio:'.(int)($ad['data']['width'] ?? 1920).'/'.(int)($ad['data']['height'] ?? 1080).';border:0" src="'.esc_url($preview).'"></iframe>';
            echo '<p><a class="button" target="_blank" rel="noopener" href="'.esc_url(HPADS_Placements::preview_url($ad['id'])).'">Preview Landing Page</a></p><p>External link: '.esc_html($ad['data']['link_url'] ?? 'None').'</p>';self::controls($ad);return;
        }
        $mine=($_GET['view'] ?? 'mine')!=='all';$all=self::rows(false);$needs=self::rows(true);
        echo '<p>Review ads submitted to your venues.</p><nav class="nav-tab-wrapper">';
        foreach (['mine'=>'Needs my review ('.count($needs).')','all'=>'All venue approvals ('.count($all).')'] as $key=>$label) { echo '<a class="nav-tab '.(($mine?'mine':'all')===$key?'nav-tab-active':'').'" href="'.esc_url(add_query_arg('view',$key,HPADS_Admin::url('approvals'))).'">'.esc_html($label).'</a>'; }echo '</nav>';
        $venue=absint($_GET['venue'] ?? 0);$status=sanitize_key($_GET['status'] ?? ($mine?'pending':'all'));$search=sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        echo '<form method="get"><input type="hidden" name="page" value="hpads-approvals"><input type="hidden" name="view" value="'.($mine?'mine':'all').'"><p><label>Venue <select name="venue"><option value="0">All venues</option>';
        $venues=[];foreach ($all as $row) { $v=HPADS_Core::get($row['location']['parent_id'],'company');if ($v) { $venues[$v['id']]=$v['title']; } }
        foreach ($venues as $id=>$name) { echo '<option value="'.(int)$id.'" '.selected($venue,(int)$id,false).'>'.esc_html($name).'</option>'; }echo '</select></label> <label>Status <select name="status">';
        foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Changes requested','draft'=>'Needs resubmission','setup'=>'Needs venue setup','all'=>'All statuses'] as $key=>$label) { echo '<option value="'.esc_attr($key).'" '.selected($status,$key,false).'>'.esc_html($label).'</option>'; }echo '</select></label> <input type="search" name="s" placeholder="Search ads" value="'.esc_attr($search).'"> <button class="button">Filter</button></p></form>';
        $rows=array_values(array_filter($mine?$needs:$all,function($row) use($venue,$status,$search) { $o=HPADS_Workflow::owner_record($row['ad']);return (!$venue || (int)$row['location']['parent_id']===$venue) && ($status==='all' || $row['context']['status']===$status) && (!$search || stripos($row['ad']['title'].' '.($o['title'] ?? ''),$search)!==false); }));
        $page=max(1,absint($_GET['paged'] ?? 1));$total=count($rows);$rows=array_slice($rows,($page-1)*20,20);
        echo '<div style="overflow-x:auto"><table class="widefat striped" style="min-width:850px"><thead><tr><th>Ad</th><th>Advertiser / owner</th><th>Venue / location</th><th>Submitted</th><th>Progress</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $row) { $ad=$row['ad'];$o=HPADS_Workflow::owner_record($ad);$v=HPADS_Core::get($row['location']['parent_id'],'company');echo '<tr><td>'.wp_get_attachment_image($ad['data']['attachment_id'],'thumbnail',false,['style'=>'width:100px;height:56px;object-fit:cover;vertical-align:middle;margin-right:10px']).esc_html($ad['title']).'</td><td>'.esc_html($o['title'] ?? '').'</td><td>'.esc_html(($v['title'] ?? '').' / '.$row['location']['title']).'</td><td>'.esc_html(HPADS_Core::display_datetime(strtotime($ad['data']['submitted_at']))).'</td><td>'.esc_html($row['context']['label']).'</td><td><a class="button button-primary" href="'.esc_url(self::url($ad)).'">Review Ad</a></td></tr>'; }
        if (!$rows) { echo '<tr><td colspan="6">No matching approvals.</td></tr>'; }echo '</tbody></table></div><p>Only ads you are authorized to view appear here.</p>';
        echo wp_kses_post(paginate_links(['base'=>add_query_arg('paged','%#%'),'total'=>max(1,(int)ceil($total/20)),'current'=>$page]));
    }
}
