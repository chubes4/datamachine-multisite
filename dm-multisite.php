<?php
/**
 * Plugin Name:     Data Machine Multisite
 * Plugin URI:      https://wordpress.org/plugins/dm-multisite/
 * Description:     Multisite extension for Data Machine - Exposes AI tools network-wide and provides multisite-aware search capabilities.
 * Version:         0.1.0
 * Author:          Chris Huber
 * Author URI:      https://chubes.net
 * Text Domain:     dm-multisite
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Network:         true
 */

if (!defined('WPINC')) {
    die;
}

// Require network activation
if (!is_multisite()) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Data Machine Multisite requires WordPress Multisite to be enabled.', 'dm-multisite');
        echo '</p></div>';
    });
    return;
}

// Check if network activated (requires pluggable.php to be loaded)
add_action('admin_init', function() {
    if (!function_exists('is_plugin_active_for_network')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!is_plugin_active_for_network(plugin_basename(__FILE__))) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('Data Machine Multisite must be network activated to function properly.', 'dm-multisite');
            echo '</p></div>';
        });
    }
});

define('DM_MULTISITE_VERSION', '0.1.0');
define('DM_MULTISITE_PATH', plugin_dir_path(__FILE__));
define('DM_MULTISITE_URL', plugin_dir_url(__FILE__));

// Load classes
require_once DM_MULTISITE_PATH . 'inc/ToolRegistry.php';
require_once DM_MULTISITE_PATH . 'inc/MultisiteLocalSearch.php';
require_once DM_MULTISITE_PATH . 'inc/MultisiteWordPressPostReader.php';
require_once DM_MULTISITE_PATH . 'inc/MultisiteSiteContext.php';
require_once DM_MULTISITE_PATH . 'inc/MultisiteSiteContextDirective.php';

function run_dm_multisite() {
    // Initialize tool registry
    new DMMultisite\ToolRegistry();

    // Site context directive self-registers via filter at bottom of file
}

add_action('plugins_loaded', 'run_dm_multisite', 25);
