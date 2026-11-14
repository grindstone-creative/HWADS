<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Users_Table extends WP_List_Table {
    function __construct() { parent::__construct(['singular'=>'hpads_user','plural'=>'hpads_users','ajax'=>false]); }
    function get_columns() { return ['name'=>'Name','email'=>'Email','role'=>'Team role','memberships'=>'Memberships']; }
    protected function get_sortable_columns() { return ['name'=>['name',false],'email'=>['email',false]]; }
    function prepare_items() {
        $this->_column_headers=[$this->get_columns(),[],$this->get_sortable_columns(),'name'];
        $rows=[]; $search=sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        foreach (HPADS_Teams::all() as $u) {
            if ($search && stripos($u->display_name.' '.$u->user_email.' '.$u->user_login,$search)===false) { continue; }
            $rows[]=['name'=>$u->display_name,'email'=>$u->user_email,'user'=>$u];
        }
        $key=($_GET['orderby'] ?? '')==='email'?'email':'name'; $desc=($_GET['order'] ?? '')==='desc';
        usort($rows,function($a,$b) use($key,$desc){$c=strnatcasecmp($a[$key],$b[$key]);return $desc?-$c:$c;});
        $this->set_pagination_args(['total_items'=>count($rows),'per_page'=>20]); $this->items=array_slice($rows,($this->get_pagenum()-1)*20,20);
    }
    protected function column_name($r) {
        $name='<strong>'.esc_html($r['name']).'</strong>';
        if ((int)$r['user']->ID===get_current_user_id()) { return $name.$this->row_actions(['profile'=>'<a href="'.esc_url(admin_url('profile.php')).'">Edit Profile</a>']); }
        if (HPADS_Teams::can_edit($r['user'])) { $url=HPADS_Admin::url('users',$r['user']->ID); return '<strong><a class="row-title" href="'.esc_url($url).'">'.esc_html($r['name']).'</a></strong>'.$this->row_actions(['edit'=>'<a href="'.esc_url($url).'">Edit</a>']); }
        return $name;
    }
    function column_default($r,$column) {
        $u=$r['user'];
        if ($column==='email') { return esc_html($r['email']); }
        if ($column==='role') { $labels=[]; foreach (HPADS_Workflow::ROLES as $key=>$label) { if ($u->has_cap($key)) { $labels[]=$label; } } return esc_html(implode(', ',$labels)); }
        if ($column==='memberships') {
            $names=[];
            foreach (['companies'=>'company','advertisers'=>'advertiser'] as $key=>$kind) {
                foreach (HPADS_Core::ids(get_user_meta($u->ID,'hpads_'.$key,true)) as $id) { $e=HPADS_Core::get($id,$kind); if ($e && (HPADS_Workflow::global_staff() || in_array($id,HPADS_Core::assigned($key),true))) { $names[]=$e['title']; } }
            }
            return esc_html(implode(', ',$names) ?: '—');
        }
        return '';
    }
    function no_items() { echo 'No relevant team users found.'; }
}
