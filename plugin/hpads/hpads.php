<?php
/**
 * Plugin Name: HWADS
 * Description: Central venue, device, media and playlist management.
 * Version: 0.15.10
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: HWADS
 * License: GPL-2.0-or-later
 * Text Domain: hpads
 */
if (!defined('ABSPATH')) { exit; }
define('HPADS_VERSION', '0.15.10');
define('HPADS_FILE', __FILE__);
require_once __DIR__ . '/includes/post-types.php';
add_action('init',['HPADS_Post_Types','register']);
HPADS_Post_Types::init();
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/playlist-limits.php';
require_once __DIR__ . '/includes/workflow.php';
require_once __DIR__ . '/includes/content-access.php';
HPADS_Content_Access::init();
require_once __DIR__ . '/includes/view-as.php';
HPADS_View_As::init();
require_once __DIR__ . '/includes/teams.php';
require_once __DIR__ . '/includes/approvals.php';
require_once __DIR__ . '/includes/ads.php';
require_once __DIR__ . '/includes/visuals.php';
require_once __DIR__ . '/includes/placements.php';
require_once __DIR__ . '/includes/playback-mix.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/ad-library.php';
HPADS_Ad_Library::init();
require_once __DIR__ . '/includes/venue-workspace.php';
require_once __DIR__ . '/includes/venue-editor-dashboard.php';
HPADS_Venue_Editor_Dashboard::init();
HPADS_Venue_Workspace::init();
require_once __DIR__ . '/includes/dashboards.php';
HPADS_Dashboards::init();
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/native-editor.php';
HPADS_Native_Editor::init();
require_once __DIR__ . '/includes/divi.php';
HPADS_Divi::init();
require_once __DIR__ . '/includes/landing-layout.php';
HPADS_Landing_Layout::init();
require_once __DIR__ . '/includes/update-policy.php';
HPADS_Update_Policy::init();
require_once __DIR__ . '/includes/device-health.php';
HPADS_Device_Health::init();
require_once __DIR__ . '/includes/player-updates.php';
HPADS_Player_Updates::init();
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/device-qr.php';
require_once __DIR__ . '/includes/preview.php';
require_once __DIR__ . '/includes/embeds.php';
HPADS_Embeds::init();
register_activation_hook(__FILE__, ['HPADS_Core', 'activate']);
add_action('plugins_loaded', ['HPADS_Core', 'boot']);

require_once __DIR__ . '/includes/analytics.php';
HPADS_Analytics::init();

require_once __DIR__ . '/includes/public-device.php';
HPADS_Public_Device::init();

require_once __DIR__ . '/includes/web-metrics.php';
HPADS_Web_Metrics::init();

require_once __DIR__ . '/includes/migration.php';
HPADS_Migration::init();
