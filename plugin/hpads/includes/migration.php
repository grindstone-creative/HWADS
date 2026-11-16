<?php
if (!defined('ABSPATH')) { exit; }
/** Portable, create-only content bundles. No device credentials, users or publications. */
final class HPADS_Migration {
    const KINDS=['company','advertiser','location','playlist','creative'];
    const FIELDS='active address allow_house approval_required attachment_id caption category active_limit custom_type description duration email end height house_playlist house_rotation items landing_layout_custom landing_text link_url location_id locations media_alt orientation overlays owner_id owner_type placement_type playback_mix primary_playlist purpose qr_enabled qr_layout record_type rotation_id show_ad_on_page show_page_title source_template_id start target_mode template_location template_scope template_venue thumbnail_time transition_in transition_out venues width workflow approved_placements';
    const REFS=['location_id'=>'location','template_location'=>'location','template_venue'=>'company','rotation_id'=>'playlist','source_template_id'=>'creative','primary_playlist'=>'playlist','house_playlist'=>'playlist','playlist_id'=>'playlist','fallback_id'=>'playlist','creative_id'=>'creative'];
    static function allowed() { return HPADS_Workflow::global_admin() && !HPADS_View_As::state(); }
    static function init() {
        add_action('admin_menu',function(){if(self::allowed())add_submenu_page('hpads-dashboard','Import / Export','Import / Export','hpads_access','hwads-migration',[__CLASS__,'page']);},30);
        add_action('admin_post_hwads_export',[__CLASS__,'download']);
        add_action('admin_post_hwads_import',[__CLASS__,'handle']);
    }
    static function clean($data) { return array_intersect_key($data,array_flip(explode(' ',self::FIELDS))); }
    static function media_ids($data) {
        $ids=[];foreach($data as $key=>$value){if($key==='attachment_id'){if(!is_int($value) || $value<0)throw new RuntimeException('Invalid media relationship.');if($value)$ids[]=$value;}elseif(is_array($value)){$ids=array_merge($ids,self::media_ids($value));}}return array_values(array_unique($ids));
    }
    static function content_media($data) {
        $found=[];
        foreach($data as $value){
            if(is_array($value)){foreach(self::content_media($value) as $id=>$urls)$found[$id]=array_merge($found[$id] ?? [],$urls);}
            elseif(is_string($value) && preg_match_all('~https?://[^\s\x22\x27<>\[\]]+~',$value,$matches)){
                foreach($matches[0] as $url){$url=html_entity_decode($url,ENT_QUOTES);$id=attachment_url_to_postid($url);if(!$id)$id=attachment_url_to_postid(preg_replace('/-[0-9]+x[0-9]+(?=\.[a-z]+$)/i','',$url));if($id)$found[$id][]=$url;}
            }
        }
        return $found;
    }
    static function bundle() {
        $records=[];$media=[];
        foreach (self::KINDS as $kind) { foreach (HPADS_Core::all($kind) as $row) {
            $r=HPADS_Core::get($row['id'],$kind);$d=self::clean($r['data']);$meta=[];
            foreach (['_et_pb_use_builder','_et_pb_page_layout','_et_pb_side_nav','_hwads_divi_starter'] as $key) { $meta[$key]=get_post_meta(HPADS_Storage::post_id($r['id']),$key,true); }
            $records[]=['id'=>(int)$r['id'],'kind'=>$kind,'title'=>$r['title'],'parent_id'=>(int)$r['parent_id'],'data'=>$d,'divi'=>$meta];
            $content_media=self::content_media($d);foreach(array_unique(array_merge(self::media_ids($d),array_keys($content_media))) as $aid) { if (!isset($media[$aid])) {
                $file=get_attached_file($aid);if (!$file || !is_readable($file)) { throw new RuntimeException('A media file is missing; export stopped.'); }
                $raw=file_get_contents($file);$media[$aid]=['name'=>basename($file),'url'=>wp_get_attachment_url($aid),'sha256'=>hash('sha256',$raw),'bytes'=>base64_encode($raw),'aliases'=>[]];
            } $media[$aid]['aliases']=array_values(array_unique(array_merge($media[$aid]['aliases'],$content_media[$aid] ?? []))); }
        } }
        return ['schema'=>1,'origin'=>home_url('/'),'records'=>$records,'media'=>$media];
    }
    static function validate($b) {
        if (!is_array($b) || ($b['schema'] ?? null)!==1 || !is_string($b['origin'] ?? null) || !is_array($b['records'] ?? null) || count($b['records'])>2000 || !is_array($b['media'] ?? null)) { throw new RuntimeException('Invalid bundle schema or record limit (2000).'); }
        $by=[];foreach ($b['records'] as $r) {
            if (!is_array($r) || !is_int($r['id'] ?? null) || $r['id']<1 || isset($by[$r['id']]) || !in_array($r['kind'] ?? '',self::KINDS,true) || !is_int($r['parent_id'] ?? null) || !is_string($r['title'] ?? null) || !$r['title'] || strlen($r['title'])>200 || !is_array($r['data'] ?? null)) { throw new RuntimeException('Invalid or duplicate record.'); }
            $by[$r['id']]=$r;
        }
        $ref=function($id,$kind)use($by){if (!is_int($id) || $id<0 || ($id && (!isset($by[$id]) || $by[$id]['kind']!==$kind)))throw new RuntimeException('Missing or invalid '.$kind.' relationship.');};
        foreach ($by as $r) {
            $parent=['location'=>'company','playlist'=>'location','creative'=>'advertiser'][$r['kind']] ?? null;
            if ($parent) { $ref($r['parent_id'],$parent);if ($r['kind']!=='creative' && !$r['parent_id'])throw new RuntimeException('Parent required.'); }elseif($r['parent_id'])throw new RuntimeException('Unexpected parent.');
            self::walk(self::clean($r['data']),$ref);
            foreach(array_unique(array_merge(self::media_ids(self::clean($r['data'])),array_keys(self::content_media(self::clean($r['data']))))) as $aid) { if (!isset($b['media'][$aid]))throw new RuntimeException('Missing media.'); }
            if ($r['kind']==='creative' && !in_array($r['data']['record_type'] ?? '',['template','placement'],true))throw new RuntimeException('Unsupported legacy creative: migrate it to a template or placement first.');
        }
        foreach ($b['media'] as $aid=>$m) {
            if (!ctype_digit((string)$aid) || !is_array($m) || !is_string($m['name'] ?? null) || !is_string($m['url'] ?? null) || !is_string($m['bytes'] ?? null) || !is_string($m['sha256'] ?? null))throw new RuntimeException('Invalid media entry.');
            if(!is_array($m['aliases'] ?? []) || count($m['aliases'] ?? [])>100)throw new RuntimeException('Invalid media aliases.');foreach($m['aliases'] ?? [] as $alias){if(!is_string($alias))throw new RuntimeException('Invalid media alias.');}
            $raw=base64_decode($m['bytes'],true);if ($raw===false || strlen($raw)>wp_max_upload_size() || !hash_equals(hash('sha256',$raw),$m['sha256']))throw new RuntimeException('Media checksum or size failed.');
            $ext=strtolower(pathinfo($m['name'],PATHINFO_EXTENSION));if (!in_array($ext,['jpg','jpeg','png','webp','mp4','webm'],true))throw new RuntimeException('Unsupported media type.');
        }
        return $b;
    }
    static function walk($d,$ref) {
        foreach ($d as $key=>$v) {
            if (isset(self::REFS[$key])) { $ref($v,self::REFS[$key]); }
            elseif ($key==='owner_id') { $ref($v,($d['owner_type'] ?? '')==='company'?'company':'advertiser'); }
            elseif (in_array($key,['locations','approved_placements','venues'],true)) { if(!is_array($v))throw new RuntimeException('Invalid relationship list.');foreach($v as $id)$ref($id,$key==='venues'?'company':'location'); }
            elseif (is_array($v)) { self::walk($v,$ref); }
        }
    }
    static function remap($d,$map,$assets,$urls) {
        $out=[];foreach (self::clean($d) as $key=>$v) { $out[$key]=self::value($key,$v,$map,$assets,$urls); }return $out;
    }
    static function value($key,$v,$map,$assets,$urls) {
        if ($key==='attachment_id')return $v?($assets[$v] ?? 0):0;
        if (isset(self::REFS[$key]) || $key==='owner_id')return $v?($map[$v] ?? 0):0;
        if (in_array($key,['locations','approved_placements','venues'],true))return array_map(function($id)use($map){return $map[$id];},$v);
        if (is_array($v)) { $out=[];foreach($v as $k=>$x)$out[$k]=self::value($k,$x,$map,$assets,$urls);return $out; }
        if (is_string($v)) {
            $v=strtr($v,$urls);
            return preg_replace_callback('/(\[hwads_(?:playlist|placement)\b[^\]]*\bid\s*=\s*[\x22\x27]?)([0-9]+)/',function($m)use($map){return $m[1].($map[(int)$m[2]] ?? $m[2]);},$v);
        }
        return $v;
    }
    static function import($b,$mode='skip') {
        if (!self::allowed() || !HPADS_Storage::active())throw new RuntimeException('Global administrator and native storage required.');
        $b=self::validate($b);if(!in_array($mode,['skip','duplicate'],true))throw new RuntimeException('Invalid import mode.');
        if (!add_option('hwads_import_lock',time(),'','no'))throw new RuntimeException('An import is already running.');
        $created=[];$assets=[];$media_created=[];$map=[];$urls=[];$prior=get_option('hwads_import_map',[]);$origin=hash('sha256',$b['origin']);
        try {
            foreach($b['records'] as $r) {
                $old=$prior[$origin][$r['id']] ?? 0;$existing=$old?HPADS_Core::get($old,$r['kind']):null;
                if($mode==='skip' && $existing) { $map[$r['id']]=$old;continue; }
                $id=HPADS_Core::put($r['kind'],sanitize_text_field($r['title']),0,[]);if(is_wp_error($id))throw new RuntimeException($id->get_error_message());$map[$r['id']]=$id;$created[]=$id;
            }
            require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
            $needed=[];foreach($b['records'] as $r){if(in_array($map[$r['id']],$created,true)){foreach(self::media_ids(self::clean($r['data'])) as $aid)$needed[$aid]=true;foreach($b['media'] as $aid=>$m){if(!empty($m['aliases']))$needed[$aid]=true;}}}
            foreach(array_keys($needed) as $aid) {
                $m=$b['media'][$aid];$temp=wp_tempnam($m['name']);if(!$temp || file_put_contents($temp,base64_decode($m['bytes'],true))===false)throw new RuntimeException('Cannot stage media.');
                $file=['name'=>sanitize_file_name($m['name']),'tmp_name'=>$temp];$new=media_handle_sideload($file,0);@unlink($temp);if(is_wp_error($new))throw new RuntimeException($new->get_error_message());$assets[$aid]=$new;$media_created[]=$new;$urls[$m['url']]=wp_get_attachment_url($new);foreach($m['aliases'] ?? [] as $alias)$urls[$alias]=wp_get_attachment_url($new);
            }
            foreach($b['records'] as $r) {
                $id=$map[$r['id']];if(!in_array($id,$created,true))continue;
                $d=self::remap($r['data'],$map,$assets,$urls);
                if($r['kind']==='creative') { $d['template_user']=get_current_user_id();$d['state']='draft';$d['content_revision']=1;$d['reviews']=[];$d['manager_review']=[];$d['approved_locations']=[];$d['submission_revision']=0; }
                $old=HPADS_Core::get($id);$saved=HPADS_Core::put($r['kind'],sanitize_text_field($r['title']),$r['parent_id']?$map[$r['parent_id']]:0,$d,$id,$old['revision']);if(is_wp_error($saved))throw new RuntimeException($saved->get_error_message());
                foreach (['_et_pb_use_builder','_et_pb_page_layout','_et_pb_side_nav','_hwads_divi_starter'] as $key) { $v=$r['divi'][$key] ?? '';if(is_scalar($v))update_post_meta(HPADS_Storage::post_id($id),$key,sanitize_text_field((string)$v)); }
            }
            $prior[$origin]=$map;update_option('hwads_import_map',$prior,false);
            return ['created'=>count($created),'skipped'=>count($map)-count($created),'media'=>count($media_created)];
        } catch(Throwable $e) {
            foreach(array_reverse($created) as $id)wp_delete_post(HPADS_Storage::post_id($id),true);
            foreach($media_created as $aid)wp_delete_attachment($aid,true);
            throw $e;
        } finally { delete_option('hwads_import_lock'); }
    }
    static function download() {
        if(!self::allowed())wp_die('Access denied.');check_admin_referer('hwads_export');
        try{$json=wp_json_encode(self::bundle(),JSON_UNESCAPED_SLASHES);if(strlen($json)>64*1024*1024)throw new RuntimeException('Bundle exceeds 64 MB.');nocache_headers();header('Content-Type: application/json');header('Content-Disposition: attachment; filename="hwads-content.json"');echo $json;}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}exit;
    }
    static function handle() {
        if(!self::allowed())wp_die('Access denied.');check_admin_referer('hwads_import');
        try {
            $key='hwads_import_preview_'.get_current_user_id();
            if(!empty($_POST['commit'])) {
                $preview=get_transient($key);if(!$preview || !hash_equals($preview['token'],(string)($_POST['preview_token'] ?? '')))throw new RuntimeException('Preview expired. Upload again.');
                delete_transient($key);$result=self::import($preview['bundle'],$preview['mode']);set_transient('hwads_import_result_'.get_current_user_id(),$result,600);
            } else {
                $f=$_FILES['bundle'] ?? [];if(($f['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size']>min(wp_max_upload_size(),64*1024*1024))throw new RuntimeException('Upload failed or exceeds allowed size.');
                $b=self::validate(json_decode(file_get_contents($f['tmp_name']),true,64,JSON_THROW_ON_ERROR));$mode=($_POST['mode'] ?? '')==='duplicate'?'duplicate':'skip';
                set_transient($key,['bundle'=>$b,'mode'=>$mode,'token'=>wp_generate_uuid4()],600);
            }
            wp_safe_redirect(admin_url('admin.php?page=hwads-migration'));exit;
        }catch(Throwable $e){wp_die(esc_html($e->getMessage()),'Import / Export',['back_link'=>true]);}
    }
    static function page() {
        if(!self::allowed())wp_die('Access denied.');
        echo '<div class="wrap"><h1>Import / Export</h1><p>Portable content bundle with media: venues, advertisers, locations, templates, placements, playlists and playback groups. Excludes devices, credentials, user accounts, sharing permissions, analytics and published versions. Imported ads and playlists remain drafts and require review and publication. Existing content is never overwritten. Recognized WordPress media URLs in custom content are copied and rewritten. Theme settings, fonts, remote assets and unrecognized custom URLs require a separate WordPress media/theme migration. Personal templates are assigned to the importing administrator. Maximum 2000 records and 64 MB, subject to your hosting upload limit.</p>';
        $result=get_transient('hwads_import_result_'.get_current_user_id());if($result){echo '<div class="notice notice-success"><p>Created '.(int)$result['created'].' records; skipped '.(int)$result['skipped'].' previously imported records; copied '.(int)$result['media'].' media files.</p></div>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_export">';wp_nonce_field('hwads_export');submit_button('Export content bundle','secondary');echo '</form>';
        echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_import">';wp_nonce_field('hwads_import');echo '<p><input required type="file" name="bundle" accept="application/json,.json"></p><p><select name="mode"><option value="skip">Skip records previously imported from this origin</option><option value="duplicate">Create another independent copy</option></select></p>';submit_button('Preview import','secondary');echo '</form>';
        $preview=get_transient('hwads_import_preview_'.get_current_user_id());if($preview){echo '<h2>Import preview</h2><p>'.count($preview['bundle']['records']).' records and '.count($preview['bundle']['media']).' media files. Mode: '.esc_html($preview['mode']).'. Preview expires in ten minutes.</p><ul>';foreach($preview['bundle']['records'] as $r)echo '<li>'.esc_html($r['kind'].' — '.$r['title']).'</li>';echo '</ul><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hwads_import"><input type="hidden" name="commit" value="1"><input type="hidden" name="preview_token" value="'.esc_attr($preview['token']).'">';wp_nonce_field('hwads_import');submit_button('Import reviewed bundle');echo '</form>';}
        echo '</div>';
    }
}
