<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Ads {
    static function can_media($aid,$type,$oid,$context=null) {
        if (get_post_type($aid)!=='attachment') { return false; }
        if (!HPADS_Placements::author_owner($type,$oid)) {
            if (!$context || !HPADS_Content_Access::grant($context,true) || HPADS_Workflow::owner_ref($context)!==[$type,(int)$oid]) { return false; }
            $assets=[(int)($context['data']['attachment_id']??0)];foreach($context['data']['overlays']??[] as $o){if(isset($o['attachment_id']))$assets[]=(int)$o['attachment_id'];}
            return in_array((int)$aid,$assets,true)||(int)get_post_field('post_author',$aid)===get_current_user_id();
        }
        if (HPADS_Workflow::global_admin()) { return true; }
        foreach (HPADS_Core::all('creative') as $entry) { if ((int)($entry['data']['attachment_id'] ?? 0)===$aid && HPADS_Workflow::owner_ref($entry)===[$type,(int)$oid]) { return true; } }
        return get_post_meta($aid,'_hpads_owner',true)===$type.':'.$oid || (int)get_post_field('post_author',$aid)===get_current_user_id();
    }
    static function media_query($args) {
        if (!current_user_can('hpads_access') || HPADS_Workflow::global_admin()) { return $args; }
        global $wpdb;$ids=array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type='attachment' AND post_author=%d",get_current_user_id())));foreach (HPADS_Core::all('creative') as $entry) { if (!empty($entry['data']['attachment_id'])) { $ids[]=(int)$entry['data']['attachment_id'];foreach($entry['data']['overlays']??[] as $o){if(!empty($o['attachment_id']))$ids[]=(int)$o['attachment_id'];} } }
        $args['post__in']=$ids?:[0];return $args;
    }
    static function owners($placements=false) {
        $out=[];
        foreach (['company'=>'Venue','advertiser'=>'Advertiser'] as $kind=>$label) {
            foreach (HPADS_Core::all($kind) as $r) { if ($placements?HPADS_Placements::author_owner($kind,$r['id']):HPADS_Workflow::team_owns($kind,$r['id'])) { $out[$kind.':'.$r['id']]=$label.' · '.$r['title']; } }
        }
        return $out;
    }
    static function owner_input($in,$r=null) {
        $ref=explode(':',sanitize_text_field($in['owner'] ?? ''));
        if (count($ref)!==2 || !in_array($ref[0],['company','advertiser'],true) || !HPADS_Core::get(absint($ref[1]),$ref[0]) || !HPADS_Workflow::team_owns($ref[0],absint($ref[1]))) { return HPADS_Core::error('Select an owner team you can manage.',403); }
        $result=[$ref[0],absint($ref[1])];
        if ($r && $result!==HPADS_Workflow::owner_ref($r)) { return HPADS_Core::error('Ad ownership cannot be transferred. Create a new ad instead.'); }
        return $result;
    }
    static function fields($r) {
        $d=$r['data'] ?? []; $owners=self::owners();
        $owner=$r?implode(':',HPADS_Workflow::owner_ref($r)):sanitize_text_field($_GET['owner'] ?? '');
        if (!isset($owners[$owner])) { $owner=$r?$owner:(array_key_first($owners) ?? ''); }
        if ($r && !isset($owners[$owner])) { $o=HPADS_Workflow::owner_record($r); $owners[$owner]=$o?$o['title']:'Unavailable owner'; }
        HPADS_Admin::select('Ad owner','owner',$owners,$owner);
        if (!$r) { echo '<p>Select the owner and click <strong>Choose Owner</strong> to refresh eligible locations before uploading.</p><button type="button" class="button" id="hpads-choose-owner">Choose Owner</button>'; }
        [$type,$oid]=array_pad(explode(':',$owner),2,0);
        HPADS_Admin::select('Orientation','orientation',['auto'=>'Detect from file','horizontal'=>'Horizontal','vertical'=>'Vertical'],$d['orientation'] ?? 'auto');
        HPADS_Admin::select('Targeting','target_mode',['selected'=>'Selected locations','all'=>'All eligible locations at submission'],$d['target_mode'] ?? 'selected');
        $choices=[];
        foreach (HPADS_Workflow::eligible_locations($type,(int)$oid) as $l) {
            if ($r && !HPADS_Workflow::can_ad($r,true) && !HPADS_Core::can($l)) { continue; }
            $company=HPADS_Core::get($l['parent_id'],'company');
            $choices[$l['id']]=($company?$company['title'].' → ':'').$l['title'];
        }
        HPADS_Admin::multi('Locations','locations',$choices,$d['locations'] ?? []);
        echo '<p>Advertiser ads can target only locations already approved for that advertiser. “All” captures eligible locations when submitted. Saving edits resets both review stages.</p>';
        if ($r) {
            echo '<p><strong>'.esc_html(HPADS_Workflow::summary($r)).'</strong> · Content revision '.(int)($d['content_revision'] ?? 1).'</p>';
            echo '<p><a href="'.esc_url(wp_get_attachment_url($d['attachment_id'])).'" target="_blank" rel="noopener">Preview current media</a></p>';
        }
        echo '<p><label><strong>'.($r?'Replace media (optional)':'Upload ad media').'</strong><br><input type="file" name="media" '.(!$r?'required ':'').'data-max-bytes="'.esc_attr(wp_max_upload_size()).'" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label><span class="hpads-upload-size" aria-live="polite"></span></p><p>JPG, PNG, WebP, MP4 or WebM · Server maximum '.esc_html(size_format(wp_max_upload_size())).'. Square or unreadable files require an explicit orientation.</p>';
    }
    static function resolution($g) {
        $w=(int)($g['width'] ?? 0);$h=(int)($g['height'] ?? 0);
        return ($w>=1920 && $h>=1080 || $w>=1080 && $h>=1920)?true:HPADS_Core::error('TV media must be at least 1920 × 1080 or 1080 × 1920 pixels. This file is '.$w.' × '.$h.'.');
    }
    static function record($in,$r,$type,$oid,$validated_targets=null) {
        $targets=$validated_targets!==null?$validated_targets:HPADS_Workflow::targets($type,$oid,sanitize_key($in['target_mode'] ?? 'selected'),$in['locations'] ?? []);
        if (is_wp_error($targets)) { return $targets; }
        $aid=(int)($r['data']['attachment_id'] ?? 0); $new=false;
        $file=$_FILES['media'] ?? null;
        $chosen=absint($in['attachment_id'] ?? 0);
        if ($chosen && $chosen!==$aid) { if (!self::can_media($chosen,$type,$oid,$r)) { return HPADS_Core::error('This media is not available for the selected owner.',403); }$aid=$chosen; }
        if ((!$r && !$chosen) || (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)) {
            $valid=HPADS_Admin::validate_upload($file); if (is_wp_error($valid)) { return $valid; }
            require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
            $aid=media_handle_upload('media',0,[],['test_form'=>false,'mimes'=>['jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','mp4|m4v'=>'video/mp4','webm'=>'video/webm']]);
            if (is_wp_error($aid)) { return $aid; } $new=true;
        }
        if ($new) { update_post_meta($aid,'_hpads_owner',$type.':'.$oid); }
        $g=HPADS_Core::geometry($aid,sanitize_key($in['orientation'] ?? 'auto'));
        if (is_wp_error($g)) { if ($new) { wp_delete_attachment($aid,true); } return $g; }
        if ($new || $aid!==(int)($r['data']['attachment_id'] ?? 0)) { $quality=self::resolution($g);if (is_wp_error($quality)) { if ($new) { wp_delete_attachment($aid,true); }return $quality; } }
        $d=$r['data'] ?? [];
        return array_merge($d,$g,['workflow'=>2,'owner_type'=>$type,'owner_id'=>(int)$oid,'attachment_id'=>(int)$aid,'location_id'=>0,'locations'=>$targets,'target_mode'=>sanitize_key($in['target_mode'] ?? 'selected'),'state'=>'draft','content_revision'=>(int)($d['content_revision'] ?? 0)+1,'submission_revision'=>0,'manager_review'=>[],'reviews'=>[],'approved_locations'=>[],'created_by'=>$d['created_by'] ?? get_current_user_id()]);
    }
    static function controls($r) {
        if (HPADS_Approvals::placement($r)) { HPADS_Approvals::controls($r);return; }
        $d=$r['data'];
        if (HPADS_Workflow::automatic()) { echo '<p>Approvals are automatic. Save the placement, then save and publish its playlist when ready.</p>'; return; }
        if (HPADS_Workflow::can_ad($r,true) && !in_array($d['state'] ?? '',['account_review','venue_review'],true)) {
            HPADS_Admin::begin('creative',$r,'submit_ad');
            echo '<p>Submit the last saved draft to the account manager. After their approval, the receiving venues can review it. Submission never publishes an ad.</p>';
            submit_button('Submit to Account Manager','primary','submit',false); echo '</form>';
        }
        if (HPADS_Workflow::can_manager_review($r)) {
            HPADS_Admin::begin('creative',$r,'manager_review');
            echo '<h2>Account manager review</h2><p>Approve this content revision before any venue can review it.</p><label>Review note<br><textarea name="review_note" rows="3" class="large-text"></textarea></label><p><button class="button button-primary" name="decision" value="approved">Approve and Send to Venues</button> <button class="button" name="decision" value="rejected">Request Changes</button></p></form>';
        }
        if (!empty($d['manager_review'])) { $m=$d['manager_review']; echo '<p>Account manager: '.esc_html(ucfirst($m['status']).' · '.($m['note'] ?? '')).'</p>'; }
        foreach (($d['locations'] ?? []) as $lid) {
            $l=HPADS_Core::get($lid,'location');
            if (!$l || (!HPADS_Workflow::can_ad($r,true) && !HPADS_Core::can($l))) { continue; }
            $review=$d['reviews'][(string)$lid] ?? ['status'=>'not submitted'];
            echo '<p><strong>'.esc_html($l['title']).'</strong>: '.esc_html(ucfirst($review['status']).(!empty($review['note'])?' · '.$review['note']:'')).'</p>';
            if (!HPADS_Workflow::can_review($r,$lid)) { continue; }
            HPADS_Admin::begin('creative',$r,'venue_review');
            echo '<input type="hidden" name="location_id" value="'.(int)$lid.'"><label>Review note<br><textarea name="review_note" rows="2" class="large-text"></textarea></label><p><button class="button button-primary" name="decision" value="approved">Approve for This Location</button> <button class="button" name="decision" value="rejected">Reject</button> <button class="button" name="decision" value="pending">Return to Pending</button></p></form>';
        }
        echo '<p>Approval applies to this content revision and location. Approved ads become available to the playlist editor; a producer must still publish a playlist. Changing approvals does not recall old device versions.</p>';
    }
}
