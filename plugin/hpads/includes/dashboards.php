<?php
if (!defined('ABSPATH')) { exit; }
/** Summaries above the existing WordPress lists; all totals use the actor's visible records. */
final class HPADS_Dashboards {
    static function init() {
        add_action('all_admin_notices',[__CLASS__,'devices'],20);
        add_filter('manage_hpads_device_posts_columns',[__CLASS__,'device_columns'],40);
        add_action('manage_hpads_device_posts_custom_column',[__CLASS__,'device_column'],40,2);
    }
    static function cards($values,$label) {
        echo '<section class="hwads-dashboard-cards" aria-label="'.esc_attr($label).'">';foreach ($values as $name=>$value) { echo '<div class="hwads-dashboard-card"><span>'.esc_html($name).'</span><strong>'.esc_html(number_format_i18n($value)).'</strong></div>'; }echo '</section>';
    }
    static function main() {
        echo '<p>Across the venues and records you can access.</p>';$cards=[];
        foreach (['company'=>'Venues','location'=>'Locations','device'=>'Devices'] as $kind=>$label) { if (HPADS_Admin::can_section($kind)) { $cards[$label]=count(HPADS_Core::all($kind)); } }
        if (HPADS_Admin::can_section('approvals')) { $cards['Awaiting your approval']=count(HPADS_Approvals::rows(true)); }
        self::cards($cards,'HWADS overview');
        if (HPADS_Admin::can_section('company')) { echo '<h2>Venues</h2>';HPADS_Admin::listing('company'); }
        elseif (HPADS_Admin::can_section('creative')) { echo '<p><a class="button button-primary" href="'.esc_url(HPADS_Ad_Library::url()).'">Open Ads library</a></p>'; }
    }
    static function health_rows($records) {
        global $wpdb;$ids=array_column($records,'id');if (!$ids) { return []; }
        $rows=$wpdb->get_results($wpdb->prepare('SELECT device_id,last_seen,payload FROM '.$wpdb->prefix.'hpads_status WHERE device_id IN ('.implode(',',array_fill(0,count($ids),'%d')).')',...$ids),ARRAY_A);$out=[];foreach ($rows as $row) { $out[(int)$row['device_id']]=$row; }return $out;
    }
    static function device_totals($records,$health,$now=null) {
        $now=$now ?? time();$values=['Total devices'=>count($records),'Online'=>0,'Offline / stale'=>0,'Disabled'=>0,'Never connected'=>0];
        foreach ($records as $r) {
            if (empty($r['data']['enabled'])) { $values['Disabled']++;continue; }
            $row=$health[$r['id']] ?? null;
            if (!$row || empty($row['last_seen'])) { $values['Never connected']++;continue; }
            $values[$now-(int)$row['last_seen']>HPADS_Core::interval()*3+60?'Offline / stale':'Online']++;
        }return $values;
    }
    static function devices() {
        global $pagenow;if ($pagenow!=='edit.php' || ($_GET['post_type'] ?? '')!=='hpads_device' || !HPADS_Admin::can_section('device')) { return; }
        $records=array_values(array_filter(HPADS_Core::all('device'),['HPADS_Venue_Workspace','matches_context']));$v=HPADS_Venue_Workspace::context_venue();$l=HPADS_Venue_Workspace::context_location();
        echo '<div class="wrap hwads-devices-dashboard"><h1>Devices Dashboard</h1>';if ($v) { echo '<p><a href="'.esc_url($l?HPADS_Venue_Workspace::url($l['id']):HPADS_Venue_Workspace::venue_url($v['id'])).'">'.esc_html($v['title']).' · Venue Dashboard</a></p>'; }
        echo '<p>Latest check-ins from devices you can access. Totals cover the selected venue and location, across all list pages and searches.</p>';
        self::cards(self::device_totals($records,self::health_rows($records)),'Device status overview');
        HPADS_Venue_Workspace::selectors(true,'devices',$v,$l);
        echo '</div>';
    }
    static function device_columns($columns) { $columns['hpads_parent']='Venue / Location';$date=$columns['date'] ?? 'Date';unset($columns['date']);$columns['hwads_player_software']='Player software';$columns['date']=$date;return $columns; }
    static function device_column($column,$pid) {
        if ($column!=='hwads_player_software') { return; }$r=HPADS_Post_Types::record($pid);if (!$r || !HPADS_Core::can($r)) { return; }
        $rows=self::health_rows([$r]);$p=json_decode($rows[$r['id']]['payload'] ?? '{}',true);echo esc_html($p['player_version'] ?? 'Not reported');
    }
}
