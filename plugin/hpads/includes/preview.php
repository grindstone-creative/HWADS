<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Preview {
    static function items($id) {
        $p=HPADS_Core::get($id,'playlist');if (!$p || !HPADS_Core::can($p)) { return HPADS_Core::error('Playlist unavailable.',403); }
        $location=HPADS_Core::get($p['parent_id'],'location');$out=[];
        foreach (($p['data']['items'] ?? []) as $item) {
            $r=HPADS_Core::get($item['creative_id'],'creative');if (!$r || !HPADS_Core::can($r) || (($r['data']['record_type'] ?? '')!=='placement' && empty($item['active'])) || empty($r['data']['active'])) { continue; }
            $d=$r['data'];$aid=$d['attachment_id'];
            $out[]=array_merge(HPADS_Visuals::payload($d),['width'=>$d['width'] ?? 1920,'height'=>$d['height'] ?? 1080,'qr_enabled'=>!empty($d['qr_enabled']),'qr_url'=>HPADS_Placements::public_url($r['id'])],['id'=>$r['id'],'title'=>$r['title'],'url'=>wp_get_attachment_url($aid),'video'=>strpos((string)get_post_mime_type($aid),'video/')===0,'seconds'=>$d['duration'] ?? (int)($location['data']['duration'] ?? 10),'start'=>!empty($d['start'])?$d['start'].':00Z':null,'end'=>!empty($d['end'])?$d['end'].':00Z':null,'entrance'=>$d['transition_in'] ?? 'none','exit'=>$d['transition_out'] ?? 'none']);
        }
        return $out;
    }
    static function route() {
        if (!is_user_logged_in()) { auth_redirect(); }
        $id=absint($_GET['id'] ?? 0);$items=self::items($id);if (is_wp_error($items)) { wp_die('Playlist unavailable.','HWADS',['response'=>403]); }
        nocache_headers();header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
        $p=HPADS_Core::get($id,'playlist');
        self::render($items,$p['title']);
    }
    static function ad_route() {
        $ad=HPADS_Core::get(absint($_GET['id'] ?? 0),'creative');
        if (!is_user_logged_in() || !$ad || !HPADS_Approvals::placement($ad) || !HPADS_Workflow::can_ad($ad)) { wp_die('Placement unavailable.','HWADS',['response'=>403]); }
        nocache_headers();header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
        $d=$ad['data'];$item=array_merge(HPADS_Visuals::payload($d),['id'=>$ad['id'],'title'=>$ad['title'],'url'=>wp_get_attachment_url($d['attachment_id']),'video'=>strpos((string)get_post_mime_type($d['attachment_id']),'video/')===0,'width'=>$d['width'] ?? 1920,'height'=>$d['height'] ?? 1080,'seconds'=>$d['duration'] ?? HPADS_Core::inherited_duration($ad),'qr_url'=>HPADS_Placements::public_url($ad['id']),'entrance'=>$d['transition_in'] ?? 'none','exit'=>$d['transition_out'] ?? 'none']);
        self::render([$item],$ad['title']);
    }
    static function render($items,$title) {
        $payload=['preview'=>true,'items'=>$items];
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html($title).' — Preview</title><link rel="stylesheet" href="'.esc_url(plugins_url('assets/overlays.css',HPADS_FILE)).'"><style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:transparent}#hwads-display{position:relative;width:100%;height:100%;overflow:hidden}#hwads-display a>img,#hwads-display a>video{display:block;width:100%;height:100%;object-fit:contain}.nav{position:absolute;top:50%;transform:translateY(-50%);z-index:10;border:1px solid white;border-radius:50%;background:#0008;color:white;width:44px;height:44px;font-size:30px;cursor:pointer}#previous{left:12px}#next{right:12px}</style></head><body><div id="hwads-display"></div><button class="nav" id="previous" aria-label="Previous slide">&#8249;</button><button class="nav" id="next" aria-label="Next slide">&#8250;</button><script id="hwads-embed-data" type="application/json">'.wp_json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script src="'.esc_url(plugins_url('assets/vendor/qrcodegen.js',HPADS_FILE)).'"></script><script src="'.esc_url(plugins_url('assets/overlays.js',HPADS_FILE)).'"></script><script src="'.esc_url(plugins_url('assets/embed.js',HPADS_FILE)).'"></script></body></html>';exit;
    }
}
