<?php
/**
 * Plugin activation script for WP Content Flow
 * Activates the plugin and displays current plugin status
 */

// WordPress bootstrap
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-config.php');

// Include admin functions
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

// Check if plugin file exists
$plugin_file = 'wp-content-flow/wp-content-flow.php';
$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

echo "=== WP Content Flow Plugin Activation ===\n";
echo "Plugin file: $plugin_file\n";
echo "Plugin path: $plugin_path\n";
echo "Plugin exists: " . (file_exists($plugin_path) ? 'YES' : 'NO') . "\n";

if (!file_exists($plugin_path)) {
    echo "ERROR: Plugin file not found!\n";
    exit(1);
}

// Get plugin data
$plugin_data = get_plugin_data($plugin_path);
echo "Plugin Name: " . $plugin_data['Name'] . "\n";
echo "Plugin Version: " . $plugin_data['Version'] . "\n";

// Check if already active
if (is_plugin_active($plugin_file)) {
    echo "Status: Plugin is already ACTIVE\n";
} else {
    echo "Status: Plugin is INACTIVE\n";
    
    // Activate the plugin
    echo "Activating plugin...\n";
    $result = activate_plugin($plugin_file);
    
    if (is_wp_error($result)) {
        echo "ERROR: Failed to activate plugin: " . $result->get_error_message() . "\n";
        exit(1);
    } else {
        echo "SUCCESS: Plugin activated successfully!\n";
    }
}

// List all plugins
echo "\n=== All Installed Plugins ===\n";
$all_plugins = get_plugins();
foreach ($all_plugins as $plugin_slug => $plugin_info) {
    $status = is_plugin_active($plugin_slug) ? 'ACTIVE' : 'inactive';
    echo sprintf("- %s (%s) - %s\n", $plugin_info['Name'], $plugin_info['Version'], $status);
}

echo "\n=== Plugin activation complete ===\n";
?>