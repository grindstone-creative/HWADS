<?php
if (!defined('ABSPATH')) { exit; }
/** Shared location policy; no device override and no executable update command. */
final class HPADS_Update_Policy {
    private static $form=[];
    static function init() { add_action('admin_post_hwads_update_policy',[__CLASS__,'save']);add_action('admin_footer',[__CLASS__,'form']); }
    static function location($r) { return $r && $r['kind']==='device'?HPADS_Core::get($r['parent_id'],'location'):($r && $r['kind']==='location'?$r:null); }
    static function allowed($l) {
        return $l && HPADS_Core::can($l) && (HPADS_Workflow::global_admin() || current_user_can('hpads_admin') || current_user_can('hpads_account_manager') || HPADS_Workflow::venue_admin());
    }
    static function policy($l) { $p=$l['data']['update_policy'] ?? [];return ['enabled'=>!empty($p['enabled']),'time'=>$p['time'] ?? '','window_minutes'=>30,'timezone'=>wp_timezone_string(),'tv_off_required'=>true]; }
    static function apply($lid,$input) {
        $l=HPADS_Core::get($lid,'location');if (!self::allowed($l)) { return HPADS_Core::error('You cannot change this location update schedule.',403); }
        if ((int)($input['location_revision'] ?? -1)!==(int)$l['revision']) { return HPADS_Core::error('Location changed. Reload before saving its update schedule.',409); }
        $time=$input['update_time'] ?? '';if (!is_string($time) || ($time!=='' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time))) { return HPADS_Core::error('Choose a valid update time.'); }
        if (!empty($input['schedule_enabled']) && $time==='') { return HPADS_Core::error('Choose a time before enabling scheduled updates.'); }
        $data=$l['data'];$data['update_policy']=['enabled'=>!empty($input['schedule_enabled']),'time'=>$time];
        return HPADS_Core::put('location',$l['title'],$l['parent_id'],$data,$l['id'],$l['revision']);
    }
    static function save() {
        $lid=absint($_POST['location_id'] ?? 0);check_admin_referer('hwads_update_policy_'.$lid,'hwads_policy_nonce');
        $result=self::apply($lid,wp_unslash($_POST));if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'HWADS',['response'=>400,'back_link'=>true]); }
        $device=HPADS_Core::get(absint($_POST['return_device'] ?? 0),'device');$url=$device && (int)$device['parent_id']===$lid && HPADS_Core::can($device)?HPADS_Admin::url('device',$device['id']):HPADS_Admin::url('location',$lid);
        wp_safe_redirect($url);exit;
    }
    static function render($r) {
        $l=self::location($r);if (!$l || !HPADS_Core::can($l)) { return; }$p=self::policy($l);$allowed=self::allowed($l);
        echo '<div class="postbox"><div class="inside"><h3>Location software update window</h3><p>Shared by every device at <strong>'.esc_html($l['title']).'</strong>. Editing here changes the same location setting; this is not a device override. Timezone: '.esc_html($p['timezone']).'.</p>';
        if ($allowed) {
            self::$form=['location'=>$l,'return_device'=>$r['kind']==='device'?$r['id']:0];
            echo '<p><label><input type="checkbox" name="schedule_enabled" value="1" form="hwads-policy-form" '.checked($p['enabled'],true,false).'> Allow scheduled software updates</label></p><p><label>Daily update time <input type="time" name="update_time" value="'.esc_attr($p['time']).'" form="hwads-policy-form"></label></p><p><button class="button" type="submit" form="hwads-policy-form">Save Location Update Schedule</button></p>';
        } else { echo '<p>'.esc_html($p['enabled']?$p['time'].' daily':'Scheduled software updates disabled').'</p>'; }
        echo '<p>30-minute window. Future automatic software installation must also confirm the TV is off; unknown TV status leaves the update pending. This does not schedule playlist/media syncing or authorize a Pi reboot.</p><p>Schedule is stored for the software updater. Automatic software installation and TV-power detection are not implemented yet. This action saves only the schedule, not other editor changes.</p></div></div>';
    }
    static function form() {
        if (!self::$form) { return; }$l=self::$form['location'];echo '<form id="hwads-policy-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        foreach (['action'=>'hwads_update_policy','location_id'=>$l['id'],'location_revision'=>$l['revision'],'return_device'=>self::$form['return_device'],'hwads_policy_nonce'=>wp_create_nonce('hwads_update_policy_'.$l['id'])] as $key=>$value) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">'; }echo '</form>';
    }
}
