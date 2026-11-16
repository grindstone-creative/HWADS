<?php
if (!defined('ABSPATH')) { exit; }
final class HPADS_Teams {
    static function relevant($u) {
        foreach (array_keys(HPADS_Workflow::ROLES) as $role) { if ($u->has_cap($role)) { return true; } }
        return false;
    }
    static function family($u) {
        foreach (['hpads_owner','hpads_team_admin','hpads_producer','hpads_sales','hpads_account_manager','hpads_admin'] as $r) { if ($u->has_cap($r)) { return 'staff'; } }
        foreach (['hpads_venue_admin','hpads_venue_user','hpads_venue'] as $r) { if ($u->has_cap($r)) { return 'venue'; } }
        if ($u->has_cap('hpads_designer')) { return 'designer'; }
        return 'advertiser';
    }
    static function visible($u) {
        if (!self::relevant($u)) { return false; }
        if (HPADS_Workflow::global_staff()) { return true; }
        if (self::family($u)==='staff') { return (int)$u->ID===get_current_user_id(); }
        if (self::family($u)==='designer') { return self::designer_in_scope($u); }
        $type=self::family($u)==='venue'?'companies':'advertisers';
        $member=$type==='companies'?HPADS_Workflow::venue_member():HPADS_Workflow::advertiser_member();
        return $member && (bool)array_intersect(HPADS_Core::assigned($type),HPADS_Core::ids(get_user_meta($u->ID,'hpads_'.$type,true)));
    }
    static function can_manage() { return HPADS_Workflow::global_admin() || HPADS_Workflow::venue_admin() || HPADS_Workflow::advertiser_admin(); }
    static function can_edit($u) {
        if (!self::visible($u) || (int)$u->ID===get_current_user_id()) { return false; }
        if (HPADS_Core::owner()) { return true; }
        if ($u->has_cap('hpads_owner') || user_can($u,'manage_options') || user_can($u,'edit_users') || user_can($u,'install_plugins')) { return false; }
        if (HPADS_Workflow::global_admin()) { return true; }
        $family=self::family($u);if ($family==='designer') { return self::designer_in_scope($u,true); }
        if (($family==='venue' && !HPADS_Workflow::venue_admin()) || ($family==='advertiser' && !HPADS_Workflow::advertiser_admin()) || $family==='staff') { return false; }
        $type=$family==='venue'?'companies':'advertisers';
        $other=$family==='venue'?'advertisers':'companies';
        return !array_diff(HPADS_Core::ids(get_user_meta($u->ID,'hpads_'.$type,true)),HPADS_Core::assigned($type)) && !HPADS_Core::ids(get_user_meta($u->ID,'hpads_'.$other,true));
    }
    static function designer_in_scope($u,$strict=false) {
        $own=HPADS_Workflow::venue_admin()?'companies':(HPADS_Workflow::advertiser_admin()?'advertisers':'');if (!$own) { return (int)$u->ID===get_current_user_id(); }
        $other=$own==='companies'?'advertisers':'companies';$ids=HPADS_Core::ids(get_user_meta($u->ID,'hpads_'.$own,true));$locations=HPADS_Core::ids(get_user_meta($u->ID,'hpads_locations',true));
        if ($own==='companies') { foreach ($locations as $lid) { $l=HPADS_Core::get($lid,'location');if ($l) { $ids[]=(int)$l['parent_id']; } } }
        if (!$ids || !array_intersect($ids,HPADS_Core::assigned($own))) { return false; }
        return !$strict || (!array_diff($ids,HPADS_Core::assigned($own)) && !HPADS_Core::ids(get_user_meta($u->ID,'hpads_'.$other,true)) && ($own==='companies' || !$locations));
    }
    static function all() {
        $users=[];
        foreach (get_users(['orderby'=>'display_name']) as $u) { if (self::visible($u)) { $users[]=$u; } }
        return $users;
    }
    static function role_choices() {
        if (HPADS_Core::owner()) { return HPADS_Workflow::ROLES; }
        if (HPADS_Workflow::global_admin()) { return array_diff_key(HPADS_Workflow::ROLES,['hpads_owner'=>true]); }
        $out=[];
        if (HPADS_Workflow::venue_admin()) { $out+=['hpads_venue_admin'=>'Venue Admin','hpads_venue_user'=>'Venue User']; }
        if (HPADS_Workflow::advertiser_admin()) { $out+=['hpads_advertiser_admin'=>'Advertiser Admin','hpads_advertiser_user'=>'Advertiser User']; }
        if (self::can_manage()) { $out['hpads_designer']='Designer'; }
        return $out;
    }
    static function save($in) {
        $id=absint($in['user_id'] ?? 0); $role=sanitize_key($in['hpads_role'] ?? '');
        if (!self::can_manage() || !isset(self::role_choices()[$role])) { return HPADS_Core::error('You cannot grant this role.',403); }
        $u=$id?get_user_by('id',$id):null;
        if ($id && (!$u || !self::can_edit($u))) { return HPADS_Core::error('You cannot edit this account.',403); }
        $scopes=['companies'=>HPADS_Core::ids($in['companies'] ?? []),'advertisers'=>HPADS_Core::ids($in['advertisers'] ?? []),'locations'=>HPADS_Core::ids($in['locations'] ?? [])];
        if ($role==='hpads_designer' && !HPADS_Workflow::global_admin()) {
            if (HPADS_Workflow::venue_admin() && ($scopes['advertisers'] || array_diff($scopes['companies'],HPADS_Core::assigned('companies'))) || HPADS_Workflow::advertiser_admin() && ($scopes['companies'] || $scopes['locations'] || array_diff($scopes['advertisers'],HPADS_Core::assigned('advertisers')))) { return HPADS_Core::error('Designer assignments must stay within your own team.',403); }
        }
        if ($role!=='hpads_designer' && $scopes['locations']) { return HPADS_Core::error('Location-only design assignments are for the Designer role.'); }
        $family=strpos($role,'hpads_venue')===0?'venue':(strpos($role,'hpads_advertiser')===0?'advertiser':'staff');
        if ($family==='venue' && (!$scopes['companies'] || $scopes['advertisers'])) { return HPADS_Core::error('Venue roles need venue memberships only.'); }
        if ($family==='advertiser' && (!$scopes['advertisers'] || $scopes['companies'])) { return HPADS_Core::error('Advertiser roles need advertiser memberships only.'); }
        foreach (['companies'=>'company','advertisers'=>'advertiser','locations'=>'location'] as $key=>$kind) {
            foreach ($scopes[$key] as $sid) {
                if (!HPADS_Core::get($sid,$kind) || (!HPADS_Workflow::global_admin() && !($key==='locations'?in_array((int)(HPADS_Core::get($sid,'location')['parent_id']??0),HPADS_Core::assigned('companies'),true):in_array($sid,HPADS_Core::assigned($key),true)))) { return HPADS_Core::error('Membership is outside your team.',403); }
            }
        }
        $name=sanitize_text_field($in['display_name'] ?? '');
        if (!$name) { return HPADS_Core::error('Display name is required.'); }
        if (!$u) {
            $login=sanitize_user($in['user_login'] ?? '',true); $email=sanitize_email($in['user_email'] ?? '');
            $password=(string)($in['user_pass'] ?? '');
            if (!$login || !is_email($email) || strlen($password)<12) { return HPADS_Core::error('Enter a username, valid email and initial password of at least 12 characters.'); }
            // Do not attach team access to an existing email/account: that requires a global administrator.
            if (username_exists($login) || email_exists($email)) { return HPADS_Core::error('That account already exists. Ask the global administrator to add an HWADS role through WordPress Users first.'); }
            $new=wp_insert_user(['user_login'=>$login,'user_email'=>$email,'display_name'=>$name,'user_pass'=>$password,'role'=>$role]);
            if (is_wp_error($new)) { return $new; } $u=new WP_User($new);
        } else {
            $result=wp_update_user(['ID'=>$id,'display_name'=>$name]); if (is_wp_error($result)) { return $result; }
            foreach (array_keys(HPADS_Workflow::ROLES) as $old) { $u->remove_role($old); $u->remove_cap($old); }
            $u->add_role($role);
        }
        $u->add_cap('hpads_access');
        foreach ($scopes as $key=>$ids) { update_user_meta($u->ID,'hpads_'.$key,$ids); }
        return (int)$u->ID;
    }
    static function page() {
        if (isset($_GET['edit'])) { self::editor(absint($_GET['edit'])); return; }
        require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php'; require_once __DIR__.'/users-table.php';
        if (self::can_manage()) { echo '<p><a class="page-title-action" href="'.esc_url(HPADS_Admin::url('users',0)).'">Add New User</a></p>'; }
        echo '<p><a class="button" href="'.esc_url(admin_url('profile.php')).'">Edit My Profile</a></p>';
        echo '<p>Only HWADS staff and members of relevant advertiser/venue teams appear here. Existing unrelated WordPress accounts are excluded.</p>';
        $table=new HPADS_Users_Table(); $table->prepare_items();
        echo '<form method="get"><input type="hidden" name="page" value="hpads-users">'; $table->search_box('Search Users','hpads-users'); $table->display(); echo '</form>';
    }
    static function editor($id) {
        $u=$id?get_user_by('id',$id):null;
        if (!self::can_manage() || ($id && (!$u || !self::can_edit($u)))) { HPADS_Admin::fail('This account cannot be edited here.'); }
        HPADS_Admin::begin('users',null,'team_user');
        echo '<input type="hidden" name="user_id" value="'.(int)$id.'">';
        HPADS_Admin::layout_open('User Details and Access');
        HPADS_Admin::field('Display name','display_name',$u?$u->display_name:'');
        if (!$u) {
            HPADS_Admin::field('Username','user_login'); HPADS_Admin::field('Email','user_email','','email');
            HPADS_Admin::field('Initial password (minimum 12 characters)','user_pass','','password','Share the initial credentials yourself. HWADS does not send account emails in this release.');
        } else { echo '<p>'.esc_html($u->user_login).' · '.esc_html($u->user_email).'</p>'; }
        $current=''; if ($u) { foreach (array_keys(self::role_choices()) as $r) { if ($u->has_cap($r)) { $current=$r; break; } } }
        HPADS_Admin::select('Team role','hpads_role',self::role_choices(),$current);
        HPADS_Admin::multi('Venue memberships','companies',HPADS_Admin::choices('company'),$u?HPADS_Core::ids(get_user_meta($u->ID,'hpads_companies',true)):[]);
        HPADS_Admin::multi('Advertiser memberships','advertisers',HPADS_Admin::choices('advertiser'),$u?HPADS_Core::ids(get_user_meta($u->ID,'hpads_advertisers',true)):[]);
        HPADS_Admin::multi('Designer location assignments (optional)','locations',HPADS_Admin::choices('location'),$u?HPADS_Core::ids(get_user_meta($u->ID,'hpads_locations',true)):[]);
        echo '<p>Designers can use venue, advertiser or individual location assignments. They edit content and submit revisions, but cannot manage playlists, approvals, devices or teams.</p>';
        echo '<p>Venue roles use venue memberships; advertiser roles use advertiser memberships. SuperAdmin, HWADS Admin, Producer and Sales have global scopes. Account Managers need venue/advertiser memberships for their assigned work.</p>';
        HPADS_Admin::layout_close('Save User');echo '</form>';
    }
}
