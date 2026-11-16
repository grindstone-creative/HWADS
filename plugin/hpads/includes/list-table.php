<?php
if (!defined('ABSPATH')) { exit; }
/** Use the native WordPress list table, retaining HPADS record-level scope checks. */
final class HPADS_List_Table extends WP_List_Table {
    private $kind;
    function __construct($kind) {
        $this->kind=$kind;
        parent::__construct(['singular'=>'hpads_record','plural'=>'hpads_records','ajax'=>false]);
    }
    function get_columns() {
        $columns=['title'=>'Name','id'=>'ID'];
        if (in_array($this->kind,['company','advertiser'],true)) { $columns['email']='Contact email'; }
        else { $columns[$this->kind==='creative'?'owner':'parent']=$this->kind==='location'?'Venue company':($this->kind==='creative'?'Owner':'Location'); }
        if ($this->kind==='company') { $columns['locations']='Locations'; }
        if ($this->kind==='location') { $columns['workspace']='Dashboard'; }
        if ($this->kind==='device') { $columns+=['type'=>'Device type','orientation'=>'Orientation','status'=>'Status']; }
        if ($this->kind==='creative') { $columns+=['rotation'=>'Rotation','targets'=>'Location','approval'=>'Review status','orientation'=>'Orientation','attachment'=>'Media']; }
        if ($this->kind==='playlist') { $columns+=['purpose'=>'Purpose','publication'=>'Publication']; }
        if ($this->kind==='creative' && ($_GET['page'] ?? '')==='hpads-templates') { unset($columns['rotation'],$columns['targets'],$columns['approval']); }
        elseif ($this->kind==='creative') { $columns['schedule']='Schedule ('.wp_timezone_string().')'; }
        $columns['updated_at']='Last updated ('.wp_timezone_string().')';
        return $columns;
    }
    protected function get_sortable_columns() { return ['title'=>['title',false],'id'=>['id',false],'owner'=>['owner_sort',false],'updated_at'=>['updated_at',true]]; }
    function no_items() { echo esc_html('No matching '.strtolower(HPADS_Admin::PAGES[$this->kind]).' found.'); }
    function prepare_items() {
        $this->_column_headers=[$this->get_columns(),[],$this->get_sortable_columns(),'title'];
        $search=sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $advertiser=absint($_GET['advertiser'] ?? 0);
        $owner=sanitize_text_field($_GET['ad_owner'] ?? ''); $location=absint($_GET['location_filter'] ?? 0); $status=sanitize_key($_GET['ad_status'] ?? '');
        $rows=[];
        foreach (HPADS_Core::all($this->kind) as $r) {
            if ($this->kind==='creative') {
                $templates=($_GET['page'] ?? '')==='hpads-templates';
                if (HPADS_Placements::is_template($r)!==$templates) { continue; }
                if (!$templates) {
                    $time=HPADS_Placements::time_state($r); $view=sanitize_key($_GET['time_view'] ?? 'open');
                    if ($view==='open' && !in_array($time,['current','scheduled'],true)) { continue; }
                    if (in_array($view,['past','inactive','current','scheduled'],true) && $view!==$time) { continue; }
                }
            }
            if ($this->kind==='creative' && $advertiser && (int)$r['parent_id']!==$advertiser) { continue; }
            if ($this->kind==='creative') {
                if ($owner && implode(':',HPADS_Workflow::owner_ref($r))!==$owner) { continue; }
                if ($location && !in_array($location,$r['data']['locations'] ?? [],true)) { continue; }
                if ($status && ($r['data']['state'] ?? 'draft')!==$status) { continue; }
                $o=HPADS_Workflow::owner_record($r); $r['owner_sort']=$o?$o['title']:'';
            }
            $parent=HPADS_Core::get($r['parent_id']);
            if ($this->kind==='creative' && !$parent) { $parent=HPADS_Core::get($r['data']['location_id'] ?? 0,'location'); }
            $r['parent_record']=$parent;
            $text=$r['title'].' '.($r['owner_sort'] ?? '').' '.$r['id'].' '.($parent['title'] ?? '').' '.($r['data']['email'] ?? '').' '.($r['data']['type'] ?? '').' '.($r['data']['orientation'] ?? '');
            if ($search!=='' && stripos($text,$search)===false) { continue; }
            $rows[]=$r;
        }
        $orderby=sanitize_key($_GET['orderby'] ?? 'title');
        if (!in_array($orderby,['title','id','owner_sort','updated_at'],true)) { $orderby='title'; }
        if ($orderby==='owner_sort' && $this->kind!=='creative') { $orderby='title'; }
        $desc=strtolower(sanitize_key($_GET['order'] ?? 'asc'))==='desc';
        usort($rows,function($a,$b) use($orderby,$desc) {
            $cmp=$orderby==='id' ? (int)$a['id']<=>(int)$b['id'] : strnatcasecmp($a[$orderby],$b[$orderby]);
            if ($cmp===0) { $cmp=(int)$a['id']<=>(int)$b['id']; }
            return $desc ? -$cmp : $cmp;
        });
        $per_page=20;
        $this->set_pagination_args(['total_items'=>count($rows),'per_page'=>$per_page]);
        $this->items=array_slice($rows,($this->get_pagenum()-1)*$per_page,$per_page);
    }
    protected function column_title($item) {
        $url=HPADS_Admin::url($this->kind==='creative' && HPADS_Placements::is_template($item)?'templates':$this->kind,$item['id']);
        $label=HPADS_Core::can($item,true)?'Edit':'View';
        $link='<a href="'.esc_url($url).'">'.esc_html($label).'</a>';
        $actions=[strtolower($label)=>$link];
        if ($this->kind==='device' && HPADS_Device_QR::allowed($item)) { $actions['settings_qr']='<a href="'.esc_url($url.'#hpads-device-qr').'">Settings QR</a>'; }
        if ($this->kind==='creative' && HPADS_Placements::is_template($item) && HPADS_Workflow::can_ad($item)) { $actions['use_template']='<a href="'.esc_url(add_query_arg('source_template_id',$item['id'],HPADS_Admin::url('creative',0))).'">Create Placement</a>'; }
        elseif ($this->kind==='creative' && HPADS_Workflow::can_ad($item,true)) { $actions['duplicate']='<a href="'.esc_url($url.'#hpads-duplicate').'">Duplicate</a>'; }
        return '<strong><a class="row-title" href="'.esc_url($url).'">'.esc_html($item['title']).'</a></strong>'.$this->row_actions($actions);
    }
    function column_default($item,$column) {
        $d=$item['data'];
        switch ($column) {
            case 'schedule': return esc_html(ucfirst(HPADS_Placements::time_state($item)).' · '.(!empty($d['start'])?HPADS_Core::display_datetime($d['start']):'No start').' → '.(!empty($d['end'])?HPADS_Core::display_datetime($d['end']):'No end'));
            case 'locations': return HPADS_Venue_Workspace::location_links($item['id']);
            case 'workspace': return '<a href="'.esc_url(HPADS_Venue_Workspace::url($item['id'])).'">Open venue dashboard</a>';
            case 'id': return (string)(int)$item['id'];
            case 'email': return esc_html($d['email'] ?? '');
            case 'rotation': $p=HPADS_Core::get($d['rotation_id'] ?? 0,'playlist'); return esc_html($p?$p['title']:(HPADS_Placements::is_template($item)?'Template only':'—'));
            case 'owner':
                $o=HPADS_Workflow::owner_record($item); return esc_html($o?$o['title']:'Unavailable owner');
            case 'targets':
                $names=[];
                foreach (($d['locations'] ?? []) as $lid) { $l=HPADS_Core::get($lid,'location'); if ($l && (HPADS_Workflow::can_ad($item,true) || HPADS_Core::can($l))) { $names[]=$l['title']; } }
                return esc_html(implode(', ',$names) ?: 'No locations selected');
            case 'approval': return HPADS_Placements::is_template($item)?'Template — not in rotation':esc_html(HPADS_Workflow::summary($item));
            case 'parent':
                $parent=$item['parent_record'];
                if (!$parent || !HPADS_Core::can($parent)) { return '—'; }
                return '<a href="'.esc_url(HPADS_Admin::url($parent['kind'],$parent['id'])).'">'.esc_html($parent['title']).'</a>';
            case 'type': return esc_html(HPADS_Core::TYPES[$d['type'] ?? 'mixed'] ?? 'Custom');
            case 'orientation': return esc_html(ucfirst($d['orientation'] ?? 'unknown'));
            case 'status': return (empty($d['enabled'])?'Disabled · ':'').HPADS_Admin::status($item['id'],true);
            case 'attachment': return 'Attachment #'.(int)($d['attachment_id'] ?? 0);
            case 'purpose': return ($d['purpose'] ?? '')==='house'?'House / placeholder':'Primary';
            case 'publication': return empty($d['published_version'])?'Unpublished draft':'Published version #'.(int)$d['published_version'];
            case 'updated_at': return esc_html(HPADS_Core::display_datetime($item['updated_at']));
        }
        return '';
    }
    protected function get_views() {
        if ($this->kind!=='creative' || ($_GET['page'] ?? '')==='hpads-templates') { return []; }
        $counts=['open'=>0,'current'=>0,'scheduled'=>0,'past'=>0,'inactive'=>0,'all'=>0];
        foreach (HPADS_Core::all('creative') as $ad) { if (HPADS_Placements::is_template($ad)) { continue; } $state=HPADS_Placements::time_state($ad); $counts[$state]++; $counts['all']++; if (in_array($state,['current','scheduled'],true)) { $counts['open']++; } }
        $view=sanitize_key($_GET['time_view'] ?? 'open'); $out=[];
        foreach (['open'=>'Current & Scheduled','past'=>'Past / Expired','inactive'=>'Inactive','all'=>'All'] as $key=>$label) {
            $url=add_query_arg(['page'=>(($_GET['page'] ?? '')==='hpads-dashboard'?'hpads-dashboard':'hpads-creative'),'time_view'=>$key],admin_url('admin.php'));
            $out[$key]='<a href="'.esc_url($url).'"'.($view===$key?' class="current" aria-current="page"':'').'>'.esc_html($label).' <span class="count">('.$counts[$key].')</span></a>';
        }
        return $out;
    }
    protected function extra_tablenav($which) {
        if ($which!=='top' || $this->kind!=='creative') { return; }
        echo '<div class="alignleft actions"><label class="screen-reader-text" for="hpads-owner-filter">Filter by owner</label><select name="ad_owner" id="hpads-owner-filter"><option value="">All owners</option>';
        $owners=[];
        foreach (HPADS_Core::all('creative') as $ad) { $o=HPADS_Workflow::owner_record($ad); if ($o) { $owners[implode(':',HPADS_Workflow::owner_ref($ad))]=$o['title']; } }
        foreach ($owners as $key=>$name) { echo '<option value="'.esc_attr($key).'" '.selected(sanitize_text_field($_GET['ad_owner'] ?? ''),$key,false).'>'.esc_html($name).'</option>'; }
        if (($_GET['page'] ?? '')==='hpads-templates') { echo '</select>'; submit_button('Filter','', 'filter_action',false); echo '</div>'; return; }
        echo '</select><label class="screen-reader-text" for="hpads-location-filter">Filter by location</label><select name="location_filter" id="hpads-location-filter"><option value="0">All locations</option>';
        foreach (HPADS_Core::all('location') as $l) { echo '<option value="'.(int)$l['id'].'" '.selected(absint($_GET['location_filter'] ?? 0),(int)$l['id'],false).'>'.esc_html($l['title']).'</option>'; }
        echo '</select><label class="screen-reader-text" for="hpads-status-filter">Filter review stage</label><select name="ad_status" id="hpads-status-filter">';
        foreach ([''=>'All review stages','draft'=>'Draft','account_review'=>'Account manager review','venue_review'=>'Venue review','changes_requested'=>'Changes requested'] as $key=>$text) { echo '<option value="'.esc_attr($key).'" '.selected(sanitize_key($_GET['ad_status'] ?? ''),$key,false).'>'.esc_html($text).'</option>'; }
        echo '</select>'; submit_button('Filter','', 'filter_action',false); echo '</div>';
    }
}
