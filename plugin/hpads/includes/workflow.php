<?php
if (!defined('ABSPATH')) { exit; }
/** Team permissions and ad submission rules; no device or public authentication bypasses. */
final class HPADS_Workflow {
    const ROLES=[
        'hpads_owner'=>'HWADS SuperAdmin / Owner',
        'hpads_team_admin'=>'HWADS Admin',
        'hpads_producer'=>'HWADS Producer',
        'hpads_account_manager'=>'HWADS Account Manager',
        'hpads_sales'=>'HWADS Sales',
        'hpads_advertiser_admin'=>'Advertiser Admin',
        'hpads_advertiser_user'=>'Advertiser User',
        'hpads_venue_admin'=>'Venue Admin',
        'hpads_venue_user'=>'Venue User',
        'hpads_admin'=>'HWADS Account Manager (legacy, scoped)',
        'hpads_venue'=>'Venue Manager (legacy)',
        'hpads_advertiser'=>'Advertiser User (legacy)'
    ];
    static function automatic() { return (get_option('hpads_settings',[])['approval_mode'] ?? 'automatic')==='automatic'; }
    static function global_admin() { return HPADS_Core::owner() || current_user_can('hpads_team_admin'); }
    static function global_staff() { return self::global_admin() || current_user_can('hpads_producer') || current_user_can('hpads_sales'); }
    static function production() { return self::global_admin() || current_user_can('hpads_producer'); }
    static function relations() { return self::global_admin() || current_user_can('hpads_sales'); }
    static function venue_member() { return current_user_can('hpads_venue_admin') || current_user_can('hpads_venue_user') || current_user_can('hpads_venue'); }
    static function advertiser_member() { return current_user_can('hpads_advertiser_admin') || current_user_can('hpads_advertiser_user') || current_user_can('hpads_advertiser'); }
    static function venue_admin() { return current_user_can('hpads_venue_admin') || current_user_can('hpads_venue'); }
    static function advertiser_admin() { return current_user_can('hpads_advertiser_admin'); }
    static function team_owns($type,$id) {
        if (self::production()) { return true; }
        if ((current_user_can('hpads_admin') || current_user_can('hpads_account_manager'))) { return in_array((int)$id,HPADS_Core::assigned($type==='advertiser'?'advertisers':'companies'),true); }
        return $type==='advertiser' ? self::advertiser_member() && in_array((int)$id,HPADS_Core::assigned('advertisers'),true) : self::venue_member() && in_array((int)$id,HPADS_Core::assigned('companies'),true);
    }
    static function owner_ref($ad) {
        $d=$ad['data'];
        if (!empty($d['owner_type'])) { return [$d['owner_type'],(int)$d['owner_id']]; }
        if (!empty($ad['parent_id'])) { return ['advertiser',(int)$ad['parent_id']]; }
        return ['company',HPADS_Core::company(HPADS_Core::get($d['location_id'] ?? 0,'location'))];
    }
    static function owner_record($ad) { [$type,$id]=self::owner_ref($ad); return HPADS_Core::get($id,$type); }
    static function can_ad($ad,$write=false) {
        if (!current_user_can('hpads_access')) { return false; }
        [$type,$id]=self::owner_ref($ad);
        if (($ad['data']['record_type'] ?? '')==='placement' && HPADS_Placements::manager() && !self::production()) { return HPADS_Placements::managed_location($ad['data']['locations'][0] ?? 0); }
        if (self::global_staff()) { return !$write || self::production(); }
        if (self::team_owns($type,$id)) { return true; }
        if (($ad['data']['record_type'] ?? '')==='template') { return !$write && HPADS_Placements::author_owner($type,$id); }
        if (!$write && ($ad['data']['state'] ?? 'draft')==='venue_review') {
            foreach (($ad['data']['locations'] ?? []) as $lid) {
                $l=HPADS_Core::get($lid,'location');
                if ($l && self::venue_member() && in_array((int)$l['parent_id'],HPADS_Core::assigned('companies'),true)) { return true; }
                if ($l && (current_user_can('hpads_admin') || current_user_can('hpads_account_manager')) && in_array((int)$l['parent_id'],HPADS_Core::assigned('companies'),true)) { return true; }
            }
        }
        return false;
    }
    static function eligible_locations($type,$id) {
        global $wpdb;
        $out=[]; $owner=HPADS_Core::get($id,$type); if (!$owner) { return []; }
        $companies=$type==='company'?[(int)$id]:HPADS_Core::ids($owner['data']['venues'] ?? []);
        foreach (HPADS_Core::entity_ids('location') as $lid) {
            $l=HPADS_Core::get($lid,'location');
            if ((self::automatic() && self::global_admin() || in_array((int)$l['parent_id'],$companies,true)) && ($type==='company' || self::automatic() || in_array((int)$lid,HPADS_Core::ids($owner['data']['approved_placements'] ?? []),true))) { $out[]=$l; }
        }
        return $out;
    }
    static function targets($type,$id,$mode,$selected) {
        if (!in_array($type,['company','advertiser'],true) || !self::team_owns($type,$id)) { return HPADS_Core::error('Choose an owner team you can manage.',403); }
        if (!in_array($mode,['selected','all'],true)) { return HPADS_Core::error('Choose selected or all locations.'); }
        $eligible=array_map(function($l){return (int)$l['id'];},self::eligible_locations($type,$id));
        $ids=$mode==='all'?$eligible:HPADS_Core::ids($selected);
        if (array_diff($ids,$eligible)) { return HPADS_Core::error('Ads can only target locations belonging to the owner venue or its connected venues.',403); }
        return $ids;
    }
    static function submit($ad) {
        if (!self::can_ad($ad,true)) { return HPADS_Core::error('Only the ad owner team or a producer can submit it.',403); }
        [$type,$id]=self::owner_ref($ad); $d=$ad['data'];
        if (($d['record_type'] ?? '')==='template') { return HPADS_Core::error('Templates are starting content only. Create a placement and submit that entry.'); }
        if (($d['record_type'] ?? '')==='placement' && !HPADS_Placements::can_bind($type,$id,HPADS_Core::get($d['rotation_id'] ?? 0,'playlist'))) { return HPADS_Core::error('Placement destination is no longer eligible.'); }
        $targets=($d['record_type'] ?? '')==='placement'?$d['locations']:self::targets($type,$id,$d['target_mode'] ?? 'selected',$d['locations'] ?? []);
        if (is_wp_error($targets)) { return $targets; }
        if (!$targets) { return HPADS_Core::error('Select at least one eligible location before submitting. Connect the advertiser to a venue first.'); }
        if (empty($d['attachment_id'])) { return HPADS_Core::error('Upload the ad before submitting.'); }
        if (in_array($d['state'] ?? '',['account_review','venue_review'],true)) { return HPADS_Core::error('This revision is already submitted. Save changes before submitting a new revision.'); }
        $d['locations']=$targets; $d['state']='account_review'; $d['manager_review']=[]; $d['submitted_at']=gmdate('c'); $d['submitted_by']=get_current_user_id();
        $d['submission_revision']=(int)($d['content_revision'] ?? 1); $d['reviews']=[]; $d['approved_locations']=[];
        foreach ($targets as $lid) { $d['reviews'][(string)$lid]=['status'=>'pending','revision'=>$d['submission_revision']]; }
        return HPADS_Core::put('creative',$ad['title'],$ad['parent_id'],$d,$ad['id'],(int)$ad['revision']);
    }
    static function can_manager_review($ad) {
        if (($ad['data']['state'] ?? '')!=='account_review') { return false; }
        if (self::global_admin()) { return true; }
        if (!current_user_can('hpads_account_manager') && !current_user_can('hpads_admin')) { return false; }
        if (($ad['data']['record_type'] ?? '')==='placement') { return HPADS_Placements::managed_location($ad['data']['locations'][0] ?? 0); }
        [$type,$id]=self::owner_ref($ad);
        return in_array($id,HPADS_Core::assigned($type==='advertiser'?'advertisers':'companies'),true);
    }
    static function manager_review($ad,$decision,$note='') {
        if (!self::can_manager_review($ad)) { return HPADS_Core::error('This ad must be reviewed by its assigned HWADS account manager.',403); }
        if (!in_array($decision,['approved','rejected'],true)) { return HPADS_Core::error('Invalid account manager decision.'); }
        $d=$ad['data']; $entry=['status'=>$decision,'revision'=>(int)$d['content_revision'],'by'=>get_current_user_id(),'at'=>gmdate('c'),'note'=>substr(sanitize_textarea_field($note),0,1000)];
        $d['manager_review']=$entry; $d['state']=$decision==='approved'?'venue_review':'changes_requested';
        $d['review_history'][]=array_merge(['stage'=>'account_manager'],$entry);
        $d['review_history']=array_slice($d['review_history'],-100);
        return HPADS_Core::put('creative',$ad['title'],$ad['parent_id'],$d,$ad['id'],(int)$ad['revision']);
    }
    static function can_review($ad,$lid) {
        $l=HPADS_Core::get($lid,'location');
        if (!$l || !in_array((int)$lid,$ad['data']['locations'] ?? [],true) || ($ad['data']['state'] ?? '')!=='venue_review') { return false; }
        if (!self::approved_for_review($ad,$lid)) { return false; }
        return self::global_admin() || (self::venue_admin() && in_array((int)$l['parent_id'],HPADS_Core::assigned('companies'),true));
    }
    static function approved_for_review($ad,$lid) {
        $d=$ad['data']; [$type,$id]=self::owner_ref($ad);
        $eligible=array_map(function($l){return (int)$l['id'];},self::eligible_locations($type,$id));
        return in_array((int)$lid,$eligible,true) && ($d['manager_review']['status'] ?? '')==='approved' && (int)($d['manager_review']['revision'] ?? 0)===(int)($d['content_revision'] ?? 1);
    }
    static function review($ad,$lid,$decision,$note='') {
        if (!self::can_review($ad,$lid)) { return HPADS_Core::error('Only the receiving venue admin can review this location.',403); }
        if (!in_array($decision,['approved','rejected','pending'],true)) { return HPADS_Core::error('Invalid review decision.'); }
        $d=$ad['data']; $entry=['status'=>$decision,'revision'=>(int)$d['submission_revision'],'by'=>get_current_user_id(),'at'=>gmdate('c'),'note'=>substr(sanitize_textarea_field($note),0,1000)];
        $d['reviews'][(string)$lid]=$entry;
        $d['review_history'][]=array_merge(['location_id'=>(int)$lid],$entry);
        $d['review_history']=array_slice($d['review_history'],-100);
        $d['approved_locations']=[];
        foreach ($d['reviews'] as $key=>$review) { if ($review['status']==='approved' && (int)$review['revision']===(int)$d['content_revision']) { $d['approved_locations'][]=(int)$key; } }
        return HPADS_Core::put('creative',$ad['title'],$ad['parent_id'],$d,$ad['id'],(int)$ad['revision']);
    }
    static function approved($ad,$lid) {
        $d=$ad['data']; [$type,$id]=self::owner_ref($ad);
        $eligible=array_map(function($l){return (int)$l['id'];},self::eligible_locations($type,$id));
        if (self::automatic()) { return in_array((int)$lid,$eligible,true) && in_array((int)$lid,$d['locations'] ?? [],true) && ($d['record_type'] ?? '')!=='template'; }
        if (!in_array((int)$lid,$eligible,true) || ($d['state'] ?? '')!=='venue_review' || !in_array((int)$lid,$d['locations'] ?? [],true)) { return false; }
        $manager=$d['manager_review'] ?? [];
        if (($manager['status'] ?? '')!=='approved' || (int)($manager['revision'] ?? 0)!==(int)($d['content_revision'] ?? 1)) { return false; }
        $r=$d['reviews'][(string)$lid] ?? [];
        return ($r['status'] ?? '')==='approved' && (int)($r['revision'] ?? 0)===(int)($d['content_revision'] ?? 1);
    }
    static function summary($ad) {
        $d=$ad['data']; if (self::automatic() && ($d['record_type'] ?? '')==='placement') { return 'Approved automatically'; } if (empty($d['workflow'])) { return 'Legacy'; }
        if (($d['state'] ?? '')==='account_review') { return 'Awaiting account manager'; }
        if (($d['state'] ?? '')==='changes_requested') { return 'Account manager requested changes'; }
        if (($d['state'] ?? '')!=='venue_review') { return 'Draft'; }
        $n=['approved'=>0,'pending'=>0,'rejected'=>0];
        foreach (($d['reviews'] ?? []) as $r) { if (isset($n[$r['status']])) { $n[$r['status']]++; } }
        return sprintf('Submitted · %d approved · %d pending · %d rejected',$n['approved'],$n['pending'],$n['rejected']);
    }
    static function upgrade() {
        foreach (self::ROLES as $key=>$label) { add_role($key,$label,['read'=>true,'hpads_access'=>true,$key=>true]); }
        foreach (array_keys(self::ROLES) as $role_key) { $role=get_role($role_key);if ($role && $role_key!=='hpads_sales' && !$role->has_cap('upload_files')) { $role->add_cap('upload_files'); } }
        if (get_option('hpads_workflow_schema')==='2') { return; }
        global $wpdb;
        foreach (HPADS_Core::entity_ids('creative') as $id) {
            $ad=HPADS_Core::get($id,'creative'); if (!empty($ad['data']['workflow'])) { continue; }
            [$type,$oid]=self::owner_ref($ad); $d=$ad['data'];
            $d['workflow']=2; $d['owner_type']=$type; $d['owner_id']=$oid; $d['content_revision']=1; $d['target_mode']='selected';
            $d['locations']=!empty($ad['parent_id'])?HPADS_Core::ids($d['locations'] ?? []):HPADS_Core::ids([$d['location_id'] ?? 0]);
            $d['reviews']=[]; $d['state']=empty($d['approved_locations'])?'draft':'venue_review'; $d['manager_review']=empty($d['approved_locations'])?[]:['status'=>'approved','revision'=>1,'legacy'=>true]; $d['submission_revision']=1;
            foreach ($d['locations'] as $lid) { $d['reviews'][(string)$lid]=['status'=>in_array($lid,$d['approved_locations'] ?? [],true)?'approved':'pending','revision'=>1]; }
            // Preserve already-approved legacy advertiser relationships, not draft targets.
            if ($type==='advertiser' && !empty($d['approved_locations'])) {
                $a=HPADS_Core::get($oid,'advertiser');
                if ($a) { $av=$a['data']; $venues=HPADS_Core::ids($av['venues'] ?? []); foreach ($d['approved_locations'] as $lid) { $l=HPADS_Core::get($lid,'location'); if ($l) { $venues[]=(int)$l['parent_id']; } } $av['venues']=HPADS_Core::ids($venues); $av['approved_placements']=HPADS_Core::ids(array_merge($av['approved_placements'] ?? [],$d['approved_locations'])); HPADS_Core::put('advertiser',$a['title'],0,$av,$a['id'],(int)$a['revision']); }
            }
            HPADS_Core::put('creative',$ad['title'],$ad['parent_id'],$d,$ad['id'],(int)$ad['revision']);
        }
        update_option('hpads_workflow_schema','2');
    }
}
