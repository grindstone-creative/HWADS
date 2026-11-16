<?php
if (!defined('ABSPATH')) { exit; }
/** Location-owned mix of immutable playlist publications. Device legacy overrides remain available. */
final class HPADS_Playback_Mix {
    const LIMIT=20;
    static function choices($lid) {
        $out=[];foreach (HPADS_Core::all('playlist') as $p) { if ((int)$p['parent_id']===(int)$lid && !empty($p['data']['published_version']) && HPADS_Core::version($p['data']['published_version'])) { $out[(int)$p['id']]=$p['title']; } }return $out;
    }
    static function mode($d) { return $d['playback_mix_mode'] ?? (!empty($d['primary_playlist']) || !empty($d['house_playlist'])?'legacy':'inherit'); }
    static function render($kind,$r,$d,$lid) {
        echo '<h3>Playback mix</h3>';
        if ($kind==='device') { HPADS_Admin::select('Playback source','playback_mix_mode',['inherit'=>'Inherit location Playback mix','legacy'=>'Use this device’s Primary / House assignments'],self::mode($d));echo '<input type="hidden" name="hwads_mix_settings" value="1"><p>When the location mix is disabled, inherited devices continue using the existing Primary / House settings. Existing device assignments stay as overrides until you choose inheritance.</p>';return; }
        $mix=$d['playback_mix'] ?? ['enabled'=>false,'groups'=>[],'fallback_id'=>(int)($d['house_playlist'] ?? 0)];$choices=self::choices($lid);
        echo '<input type="hidden" name="hwads_mix_settings" value="1"><div class="hwads-playback-mix" data-choices="'.esc_attr(wp_json_encode($choices)).'" data-mix="'.esc_attr(wp_json_encode($mix)).'"><input type="hidden" name="playback_mix" value="'.esc_attr(wp_json_encode($mix)).'"><p><label><input class="hwads-mix-enabled" type="checkbox" '.checked(!empty($mix['enabled']),true,false).'> Enable weighted shuffle for this location</label></p><div class="hwads-mix-rows"></div><p><button type="button" class="button hwads-mix-add">Add Playlist</button> <strong class="hwads-mix-total" aria-live="polite"></strong></p><p><label>Fallback playlist <select class="hwads-mix-fallback"><option value="0">Local placeholders when no mix can play</option>';
        foreach ($choices as $id=>$name) { echo '<option value="'.(int)$id.'" '.selected((int)($mix['fallback_id'] ?? 0),(int)$id,false).'>'.esc_html($name).'</option>'; }echo '</select></label></p><p class="description">Percentages must total 100%. The player shuffles placements and balances actual playback time. Shares are long-term targets; long videos and avoiding repeats can affect short runs. Only published playlists at this location are available. Empty, expired or wrong-orientation playlists are skipped; available shares rebalance automatically. The fallback plays only when no mixed playlist can play. Requires HWADS Box player 0.5.0 or newer; older players continue Primary / House playback.</p><p class="hwads-mix-status" role="status"></p></div>';
        if (!$lid || !$choices) { echo '<p>Save this location and publish its playlists to populate the choices.</p>'; }
    }
    static function save($kind,$in,$r,$d) {
        if (empty($in['hwads_mix_settings'])) { return $d; }
        if (HPADS_View_As::state() || ($r && !HPADS_Core::can($r,true))) { return HPADS_Core::error('You cannot change this playback configuration.',403); }
        if ($kind==='device') { $mode=sanitize_key($in['playback_mix_mode'] ?? 'inherit');if (!in_array($mode,['inherit','legacy'],true)) { return HPADS_Core::error('Choose a valid playback source.'); }$d['playback_mix_mode']=$mode;return $d; }
        $raw=$in['playback_mix'] ?? '';if (!is_string($raw) || strlen($raw)>16384 || !is_array($mix=json_decode($raw,true))) { return HPADS_Core::error('Invalid Playback mix.'); }
        if (empty($mix['enabled'])) { $d['playback_mix']=array_merge($d['playback_mix'] ?? [],['enabled'=>false]);return $d; }
        $groups=$mix['groups'] ?? null;if (!is_array($groups) || !$groups || count($groups)>self::LIMIT) { return HPADS_Core::error('Choose 1–20 playlists for the Playback mix.'); }
        $choices=self::choices($r['id'] ?? 0);$seen=[];$sum=0;$clean=[];
        foreach ($groups as $g) {
            if (!is_array($g)) { return HPADS_Core::error('Invalid mix row.'); }
            $id=absint($g['playlist_id'] ?? 0);$weight=$g['weight'] ?? null;
            if (!isset($choices[$id]) || isset($seen[$id]) || filter_var($weight,FILTER_VALIDATE_INT)===false || (int)$weight<1 || (int)$weight>100) { return HPADS_Core::error('Choose unique published playlists at this location, each with a whole-number share from 1–100%.'); }
            $seen[$id]=true;$sum+=(int)$weight;$clean[]=['playlist_id'=>$id,'weight'=>(int)$weight];
        }
        if ($sum!==100) { return HPADS_Core::error('Playback mix percentages must total 100%.'); }
        $fallback=absint($mix['fallback_id'] ?? 0);if ($fallback && !isset($choices[$fallback])) { return HPADS_Core::error('Choose a published fallback playlist at this location.'); }
        $d['playback_mix']=['enabled'=>true,'groups'=>$clean,'fallback_id'=>$fallback];return $d;
    }
    static function manifest($pid,$device,$location) {
        $p=HPADS_Core::get($pid,'playlist');if (!$p || (int)$p['parent_id']!==(int)$location['id'] || empty($p['data']['published_version'])) { return null; }
        $m=HPADS_Core::version($p['data']['published_version']);if (!$m || (int)$m['playlist_id']!==(int)$p['id'] || (int)$m['location_id']!==(int)$location['id']) { return null; }
        $m['items']=array_values(array_filter($m['items'],function($item) use($device){return ($item['orientation'] ?? '')===($device['data']['orientation'] ?? 'horizontal');}));$m['analytics_grant']=HPADS_Analytics::grant($device,$location,$m['version_id']);return HPADS_API::player_manifest($m);
    }
    static function payload($device,$location) {
        $mix=$location['data']['playback_mix'] ?? [];
        if (empty($mix['enabled']) || self::mode($device['data'])!=='inherit') { return ['enabled'=>false]; }
        $out=['enabled'=>true,'schema'=>1,'signature'=>hash('sha256',wp_json_encode($mix)),'groups'=>[],'fallback'=>self::manifest((int)($mix['fallback_id'] ?? 0),$device,$location)];
        foreach ($mix['groups'] ?? [] as $g) { $out['groups'][]=['playlist_id'=>$g['playlist_id'],'weight'=>$g['weight'],'manifest'=>self::manifest($g['playlist_id'],$device,$location)]; }return $out;
    }
}
