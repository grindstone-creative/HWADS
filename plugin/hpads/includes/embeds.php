<?php
if (!defined('ABSPATH')) { exit; }
/** Public embeds expose published snapshot content only, never templates or drafts. */
final class HPADS_Embeds {
    static function init() {
        add_shortcode('hwads_playlist',[__CLASS__,'playlist_shortcode']);
        add_shortcode('hwads_placement',[__CLASS__,'placement_shortcode']);
        add_action('template_redirect',[__CLASS__,'route'],0);
    }
    static function url($type,$id,$json=false) { return add_query_arg(array_filter(['hwads_embed'=>$type,'id'=>(int)$id,'format'=>$json?'json':null]),home_url('/')); }
    static function payload($type,$id) {
        if (!in_array($type,['playlist','placement'],true) || !$id) { return null; }
        if ($type==='placement') { $item=HPADS_Placements::published_item($id);if (!$item) { return null; }$items=[$item];$version=$item['placement_revision'] ?? 0; }
        else {
            $p=HPADS_Core::get($id,'playlist');if (!$p || empty($p['data']['published_version'])) { return null; }
            $m=HPADS_Core::version($p['data']['published_version']);if (!$m || (int)$m['playlist_id']!==(int)$id) { return null; }$items=$m['items'];$version=$m['version_id'];
        }
        $out=[];foreach ($items as $item) {
            if (empty($item['placement_id'])) { continue; }
            $out[]=['id'=>(int)$item['placement_id'],'revision'=>(int)($item['placement_revision'] ?? 0),'title'=>$item['title'],'url'=>$item['url'],'video'=>strpos($item['mime'],'video/')===0,'seconds'=>$item['duration_seconds'] ?? 10,'start'=>$item['start_utc'] ?? null,'end'=>$item['end_utc'] ?? null,'entrance'=>$item['transition_in'] ?? 'none','exit'=>$item['transition_out'] ?? 'none','href'=>!empty($item['landing']['external_url'])?$item['landing']['external_url']:HPADS_Placements::public_url($item['placement_id'])];
        }
        return ['type'=>$type,'id'=>(int)$id,'version'=>$version,'items'=>$out];
    }
    static function frame($type,$id,$ratio='16/9') {
        $ratios=['16/9','9/16','4/3','1/1'];if (!in_array($ratio,$ratios,true)) { $ratio='16/9'; }
        return '<iframe src="'.esc_url(self::url($type,$id)).'" title="'.esc_attr($type==='playlist'?'Ad playlist':'Ad placement').'" loading="lazy" allow="autoplay; fullscreen" allowfullscreen style="display:block;width:100%;aspect-ratio:'.esc_attr($ratio).';border:0;background:#111"></iframe>';
    }
    static function playlist_shortcode($attrs) { return self::shortcode('playlist',$attrs); }
    static function placement_shortcode($attrs) { return self::shortcode('placement',$attrs); }
    static function shortcode($type,$attrs) {
        $a=shortcode_atts(['id'=>0,'ratio'=>'16/9'],is_array($attrs)?$attrs:[],'hwads_'.$type);$id=absint($a['id']);
        return self::payload($type,$id)?self::frame($type,$id,$a['ratio']):'';
    }
    static function box($post) {
        $r=HPADS_Post_Types::record($post->ID);if (!$r) { echo '<p>Save this record first to generate its embed code.</p>';return; }
        $type=$post->post_type==='hpads_playlist'?'playlist':'placement';$id=(int)$r['id'];
        echo '<p><strong>'.esc_html(ucfirst($type)).' ID: '.$id.'</strong></p><p><label>WordPress shortcode<input readonly class="widefat" value="'.esc_attr('[hwads_'.$type.' id="'.$id.'"]').'" onclick="this.select()"></label></p><p><label>Embed on another website<textarea readonly rows="5" class="widefat" onclick="this.select()">'.esc_textarea(self::frame($type,$id)).'</textarea></label></p><p>Shortcodes work on this WordPress site. Use the iframe on other websites. Only published content is served; publish the playlist first. Optional shortcode ratio: 16/9, 9/16, 4/3 or 1/1.</p>';
    }
    static function active($item,$now=null) { $now=$now??time();return (empty($item['start']) || strtotime($item['start'])<=$now) && (empty($item['end']) || strtotime($item['end'])>$now); }
    static function media($item) {
        $media=$item['video']?'<video autoplay muted playsinline controls preload="metadata" src="'.esc_url($item['url']).'"></video>':'<img src="'.esc_url($item['url']).'" alt="'.esc_attr($item['title']).'">';
        return '<a href="'.esc_url($item['href']).'" target="_blank" rel="noopener noreferrer">'.$media.'</a>';
    }
    static function route() {
        if (!isset($_GET['hwads_embed'])) { return; }
        $type=is_string($_GET['hwads_embed'])?sanitize_key($_GET['hwads_embed']):'';$id=absint($_GET['id'] ?? 0);$payload=self::payload($type,$id);
        nocache_headers();header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
        if (!$payload) { status_header(404);header('Content-Type: text/html; charset=UTF-8');echo '<!doctype html><html><body></body></html>';exit; }
        // Deliberately frameable, public ad-only response; admin/settings routes retain their protections.
        header_remove('X-Frame-Options');status_header(200);
        $tag='"'.hash('sha256',wp_json_encode($payload)).'"';header('ETag: '.$tag);
        if (($_GET['format'] ?? '')==='json') { header('Content-Type: application/json; charset=UTF-8');if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')===$tag) { status_header(304);exit; }echo wp_json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);exit; }
        header('Content-Type: text/html; charset=UTF-8');$first=null;foreach ($payload['items'] as $item) { if (self::active($item)) { $first=$item;break; } }
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HWADS Ad Display</title><style>html,body{margin:0;width:100%;height:100%;background:#111;overflow:hidden}#hwads-display{width:100%;height:100%;display:grid;place-items:center;overflow:hidden}#hwads-display a{width:100%;height:100%;display:grid;place-items:center}#hwads-display img,#hwads-display video{display:block;max-width:100%;max-height:100vh;width:auto;height:auto;object-fit:contain}</style></head><body><div id="hwads-display">'.($first?self::media($first):'').'</div><script type="application/json" id="hwads-embed-data">'.wp_json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script src="'.esc_url(plugins_url('assets/embed.js',HPADS_FILE)).'"></script></body></html>';exit;
    }
}
