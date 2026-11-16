<?php
if (!defined('ABSPATH')) { exit; }
/** Small, bounded visual schema. Attachment IDs only; no HTML, scripts or arbitrary asset URLs. */
final class HPADS_Visuals {
    static function number($v,$min,$max,$default) { return is_numeric($v)?max($min,min($max,(float)$v)):$default; }
    static function normalize($in,$type,$oid,$context=null) {
        if (is_string($in)) { $in=json_decode($in,true); }
        if (!is_array($in) || count($in)>8) { return HPADS_Core::error('Use up to eight text or image overlays.'); }
        $out=[];
        foreach ($in as $v) {
            if (!is_array($v) || !in_array($v['type'] ?? '',['text','image'],true)) { return HPADS_Core::error('Invalid overlay.'); }
            $o=['type'=>$v['type'],'x'=>self::number($v['x'] ?? 5,0,95,5),'y'=>self::number($v['y'] ?? 5,0,95,5),'width'=>self::number($v['width'] ?? 30,5,100,30)];
            $o['x']=min($o['x'],100-$o['width']);
            if ($v['type']==='text') {
                $o['text']=mb_substr(sanitize_textarea_field($v['text'] ?? ''),0,500);
                $o['size']=self::number($v['size'] ?? 4,1,15,4);
                $o['color']=sanitize_hex_color($v['color'] ?? '#ffffff') ?: '#ffffff';
                $o['background']=sanitize_hex_color($v['background'] ?? '') ?: '';
                $o['align']=in_array($v['align'] ?? '',['left','center','right'],true)?$v['align']:'left';
                $o['font']=in_array($v['font'] ?? '',['sans-serif','serif','monospace'],true)?$v['font']:'sans-serif';
            } else {
                $aid=absint($v['attachment_id'] ?? 0);
                if (!HPADS_Ads::can_media($aid,$type,$oid,$context) || !in_array(get_post_mime_type($aid),['image/jpeg','image/png','image/webp'],true)) { return HPADS_Core::error('Choose an accessible JPEG, PNG or WebP from Media Library.'); }
                $path=get_attached_file($aid);if (!$path || !is_readable($path) || filesize($path)>5*1024*1024) { return HPADS_Core::error('Overlay images must be local files of 5 MB or less.'); }
                $o['attachment_id']=$aid;
            }
            $out[]=$o;
        }
        return $out;
    }
    static function qr($v) {
        if (is_string($v)) { $v=json_decode($v,true); }$v=is_array($v)?$v:[];
        $size=self::number($v['size'] ?? 15,8,35,15);
        return ['foreground'=>sanitize_hex_color($v['foreground'] ?? '#000000') ?: '#000000','background'=>sanitize_hex_color($v['background'] ?? '#ffffff') ?: '#ffffff','edge_px'=>self::number($v['edge_px'] ?? 24,0,500,24),'size'=>$size,'x'=>self::number($v['x'] ?? 82,0,100-$size,82),'y'=>self::number($v['y'] ?? 70,0,95,70)];
    }
    static function payload($d,$snapshot=false) {
        $out=[];
        foreach (($d['overlays'] ?? []) as $o) {
            if ($o['type']==='image') {
                $aid=$o['attachment_id'];$o['url']=wp_get_attachment_url($aid);
                if ($snapshot) {
                    $path=get_attached_file($aid);$mime=get_post_mime_type($aid);
                    if (!$path || !is_readable($path) || filesize($path)>5*1024*1024 || !in_array($mime,['image/jpeg','image/png','image/webp'],true)) { return HPADS_Core::error('An overlay image is missing, unsupported, or larger than 5 MB.'); }
                    $o['sha256']=hash_file('sha256',$path);$o['bytes']=filesize($path);$o['mime']=$mime;
                    $o['url']=HPADS_Core::snapshot($path,$mime,$o['sha256']);if (is_wp_error($o['url'])) { return $o['url']; }
                }
            }
            $out[]=$o;
        }
        return ['overlays'=>$out,'qr_layout'=>self::qr($d['qr_layout'] ?? []),'thumbnail_time'=>(float)($d['thumbnail_time'] ?? 1)];
    }
}
