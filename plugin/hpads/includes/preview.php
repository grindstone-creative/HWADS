<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Preview {
    static function items($id) {
        $p=HPADS_Core::get($id,'playlist');if (!$p || !HPADS_Core::can($p)) { return HPADS_Core::error('Playlist unavailable.',403); }
        $location=HPADS_Core::get($p['parent_id'],'location');$out=[];
        foreach (($p['data']['items'] ?? []) as $item) {
            $r=HPADS_Core::get($item['creative_id'],'creative');if (!$r || !HPADS_Core::can($r) || empty($item['active']) || empty($r['data']['active'])) { continue; }
            $d=$r['data'];$aid=$d['attachment_id'];
            $out[]=['id'=>$r['id'],'title'=>$r['title'],'url'=>wp_get_attachment_url($aid),'video'=>strpos((string)get_post_mime_type($aid),'video/')===0,'seconds'=>$d['duration'] ?? (int)($location['data']['duration'] ?? 10),'start'=>!empty($d['start'])?$d['start'].':00Z':null,'end'=>!empty($d['end'])?$d['end'].':00Z':null,'entrance'=>$d['transition_in'] ?? 'none','exit'=>$d['transition_out'] ?? 'none'];
        }
        return $out;
    }
    static function route() {
        if (!is_user_logged_in()) { auth_redirect(); }
        $id=absint($_GET['id'] ?? 0);$items=self::items($id);if (is_wp_error($items)) { wp_die('Playlist unavailable.','HWADS',['response'=>403]); }
        nocache_headers();header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
        $p=HPADS_Core::get($id,'playlist');
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($p['title']).' — Preview</title><style>body{margin:0;background:#151515;color:white;font:16px system-ui}header{padding:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center}button{padding:8px}#stage{height:75vh;overflow:hidden;position:relative;background:black;display:grid;place-items:center}#stage img,#stage video{max-width:100%;max-height:75vh;object-fit:contain}#status{padding:16px}label{white-space:nowrap}</style></head><body><header><strong>'.esc_html($p['title']).' · Saved Draft Preview</strong><button id="previous">Previous</button><button id="pause">Pause</button><button id="next">Next</button><label><input id="all-dates" type="checkbox"> Include scheduled/expired placements</label></header><div id="stage"></div><div id="status" role="status"></div><p>This private preview does not publish or record impressions. Save edits before refreshing. Timezone: '.esc_html(wp_timezone_string()).'. Videos play full length. TV-player transitions require player support.</p><script id="hpads-preview-data" type="application/json">'.wp_json_encode($items,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script src="'.esc_url(plugins_url('assets/preview.js',HPADS_FILE)).'"></script></body></html>';exit;
    }
}
