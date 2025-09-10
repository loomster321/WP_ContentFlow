<?php
/**
 * Refresh Plugin Settings
 * This script will deactivate and reactivate the plugin to refresh settings
 */

define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

wp_set_current_user(1);

echo "=== Refreshing Plugin Settings ===\n";

$plugin_file = 'wp-content-flow/wp-content-flow.php';

// Deactivate plugin
echo "1. Deactivating plugin...\n";
deactivate_plugins($plugin_file);
echo "✅ Plugin deactivated\n";

// Wait a moment
sleep(1);

// Reactivate plugin
echo "2. Reactivating plugin...\n";
$result = activate_plugin($plugin_file);

if (is_wp_error($result)) {
    echo "❌ Plugin activation failed: " . $result->get_error_message() . "\n";
} else {
    echo "✅ Plugin reactivated successfully\n";
}

// Check if plugin is active
$active_plugins = get_option('active_plugins', array());
if (in_array($plugin_file, $active_plugins)) {
    echo "✅ Plugin is now active\n";
} else {
    echo "❌ Plugin activation verification failed\n";
}

// Force admin_init to register settings
echo "3. Forcing settings registration...\n";
do_action('admin_init');

global $wp_settings_sections, $allowed_options;

if (isset($wp_settings_sections['wp-content-flow'])) {
    echo "✅ Settings sections found after refresh\n";
    foreach ($wp_settings_sections['wp-content-flow'] as $section_id => $section) {
        echo "   - " . $section['title'] . "\n";
    }
} else {
    echo "❌ Settings sections still missing\n";
}

if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ Settings group registered\n";
} else {
    echo "❌ Settings group still missing\n";
}

echo "\n🎯 Plugin refresh complete. Try the settings page again.\n";

?>