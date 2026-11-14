<?php
if (!defined('ABSPATH')) { exit; }
/** Stable device settings links: the link is an identifier, never an access credential. */
final class HPADS_Device_QR {
    static function url($id) {
        return add_query_arg(['action'=>'hpads_device_settings','device_id'=>absint($id)],admin_url('admin-post.php','https'));
    }
    static function allowed($device) {
        return is_user_logged_in() && $device && $device['kind']==='device' && HPADS_Core::can($device,true);
    }
    static function destination($id) {
        if (!is_user_logged_in()) { return HPADS_Core::error('Sign in to manage this device.',401); }
        $device=HPADS_Core::get(absint($id),'device');
        if (!self::allowed($device)) { return HPADS_Core::error('You do not have permission to manage this device.',403); }
        return HPADS_Admin::url('device',$device['id']);
    }
    static function login_url($id) { return wp_login_url(self::url($id)); }
    static function route() {
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Referrer-Policy: no-referrer');
        $id=absint($_GET['device_id'] ?? 0); $target=self::destination($id);
        if (is_wp_error($target)) {
            if (($target->get_error_data()['status'] ?? 0)===401) {
                wp_safe_redirect(self::login_url($id)); exit;
            }
            wp_die(esc_html($target->get_error_message()),'HWADS',['response'=>403]);
        }
        wp_safe_redirect($target); exit;
    }
    static function render($device) {
        if (!self::allowed($device)) { return; }
        $url=self::url($device['id']);
        echo '<section id="hpads-device-qr" class="hpads-device-qr" data-device-id="'.(int)$device['id'].'" data-settings-url="'.esc_attr($url).'">';
        echo '<h2>Device Settings QR</h2><p>Scan to open this device’s settings. WordPress login and device-management permission are required.</p>';
        echo '<label for="hpads-device-settings-url"><strong>Device settings link</strong></label><p><input id="hpads-device-settings-url" type="url" class="large-text code" readonly value="'.esc_attr($url).'"> <button type="button" class="button hpads-copy-device-url">Copy Link</button> <a class="button" href="'.esc_url($url).'">Open Settings Link</a></p>';
        echo '<div class="hpads-qr-image" role="img" aria-label="QR code for device settings"></div><p class="hpads-qr-status" role="status">Preparing QR code…</p>';
        echo '<p><button type="button" class="button hpads-download-device-svg" disabled>Download QR (SVG)</button> <button type="button" class="button hpads-download-device-png" disabled>Download QR (PNG)</button></p>';
        echo '<p class="description">Print this code for the device. It contains no device API key, password, login token or expiry. Downloading it does not bypass login or grant permissions.</p><noscript><p>Enable JavaScript to generate the QR, or copy the settings link above.</p></noscript></section>';
    }
}
