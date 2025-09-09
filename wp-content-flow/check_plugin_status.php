<?php
/**
 * Check Plugin Status and Debug Settings Registration
 */

// WordPress bootstrap
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');

// Include admin functions
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

echo "=== Plugin Status Debug ===\n";

// Check if plugin is active
$active_plugins = get_option('active_plugins', array());
$plugin_file = 'wp-content-flow/wp-content-flow.php';

echo "1. Plugin Status:\n";
if (in_array($plugin_file, $active_plugins)) {
    echo "✅ WP Content Flow plugin is ACTIVE\n";
} else {
    echo "❌ WP Content Flow plugin is INACTIVE\n";
    echo "Active plugins:\n";
    foreach ($active_plugins as $plugin) {
        echo "   - $plugin\n";
    }
}

echo "\n2. Plugin Class Loading:\n";
if (class_exists('WP_Content_Flow')) {
    echo "✅ Main plugin class loaded\n";
} else {
    echo "❌ Main plugin class not loaded\n";
}

if (class_exists('WP_Content_Flow_Admin_Menu')) {
    echo "✅ Admin Menu class loaded\n";
} else {
    echo "❌ Admin Menu class not loaded\n";
}

if (class_exists('WP_Content_Flow_Settings_Page')) {
    echo "✅ Settings Page class loaded\n";
} else {
    echo "❌ Settings Page class not loaded\n";
}

echo "\n3. Testing Manual Settings Registration:\n";

// Try to manually create and register settings
if (class_exists('WP_Content_Flow_Settings_Page')) {
    $settings_page = new WP_Content_Flow_Settings_Page();
    echo "✅ Settings page instance created\n";
    
    // Call register_settings directly
    $settings_page->register_settings();
    echo "✅ register_settings() called\n";
    
    // Now check if they're registered
    global $wp_settings_sections, $wp_settings_fields, $allowed_options;
    
    if (isset($wp_settings_sections['wp-content-flow'])) {
        echo "✅ Settings sections now registered\n";
    } else {
        echo "❌ Settings sections still not found\n";
    }
    
    if (isset($allowed_options['wp_content_flow_settings_group'])) {
        echo "✅ Settings group now in allowed options\n";
    } else {
        echo "❌ Settings group still not in allowed options\n";
    }
} else {
    echo "❌ Cannot create settings page instance\n";
}

echo "\n🔧 Debug complete.\n";

?>