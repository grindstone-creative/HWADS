<?php
/**
 * Plugin Name: HWADS
 * Description: Central venue, device, media and playlist management.
 * Version: 0.8.2
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: HWADS
 * License: GPL-2.0-or-later
 * Text Domain: hpads
 */
if (!defined('ABSPATH')) { exit; }
define('HPADS_VERSION', '0.8.2');
define('HPADS_FILE', __FILE__);
require_once __DIR__ . '/includes/post-types.php';
add_action('init',['HPADS_Post_Types','register']);
HPADS_Post_Types::init();
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/workflow.php';
require_once __DIR__ . '/includes/teams.php';
require_once __DIR__ . '/includes/ads.php';
require_once __DIR__ . '/includes/placements.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/native-editor.php';
HPADS_Native_Editor::init();
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/device-qr.php';
require_once __DIR__ . '/includes/preview.php';
require_once __DIR__ . '/includes/embeds.php';
HPADS_Embeds::init();
register_activation_hook(__FILE__, ['HPADS_Core', 'activate']);
add_action('plugins_loaded', ['HPADS_Core', 'boot']);
