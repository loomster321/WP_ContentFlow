<?php
/**
 * Test Settings Registration Fix
 * Verifies WordPress settings are properly registered
 */

// WordPress bootstrap
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');

// Include admin functions
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// Set up user context
wp_set_current_user(1);

echo "=== Testing Settings Registration Fix ===\n";

// Initialize admin components
if (class_exists('WP_Content_Flow_Admin_Menu')) {
    $admin_menu = WP_Content_Flow_Admin_Menu::get_instance();
    echo "✅ Admin Menu instance created\n";
} else {
    echo "❌ Admin Menu class not available\n";
    exit(1);
}

// Trigger admin_init to register settings
do_action('admin_init');
echo "✅ admin_init hook triggered\n";

// Check if settings are registered
global $wp_settings_sections, $wp_settings_fields;

if (isset($wp_settings_sections['wp-content-flow'])) {
    echo "✅ Settings sections registered:\n";
    foreach ($wp_settings_sections['wp-content-flow'] as $section_id => $section) {
        echo "   - " . $section['title'] . " ($section_id)\n";
    }
} else {
    echo "❌ No settings sections registered\n";
}

if (isset($wp_settings_fields['wp-content-flow'])) {
    echo "✅ Settings fields registered:\n";
    foreach ($wp_settings_fields['wp-content-flow'] as $section_id => $fields) {
        echo "   Section: $section_id\n";
        foreach ($fields as $field_id => $field) {
            echo "     - " . $field['title'] . " ($field_id)\n";
        }
    }
} else {
    echo "❌ No settings fields registered\n";
}

// Check allowed options
global $allowed_options;
if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ Settings group registered in allowed options:\n";
    foreach ($allowed_options['wp_content_flow_settings_group'] as $option) {
        echo "   - $option\n";
    }
} else {
    echo "❌ Settings group not in allowed options\n";
    echo "Available option groups:\n";
    foreach (array_keys($allowed_options) as $group) {
        echo "   - $group\n";
    }
}

echo "\n=== Settings Registration Status ===\n";
echo "The WordPress settings should now be properly registered.\n";
echo "Try saving your API keys again in the WordPress admin.\n";

echo "\n🔧 **If the issue persists:**\n";
echo "1. Make sure to refresh the WordPress admin page\n";
echo "2. Clear any WordPress caches\n";
echo "3. Check that both API keys are entered correctly\n";

echo "\n🎯 **Expected Result:**\n";
echo "Settings should save successfully without the 'not in allowed options list' error.\n";

?>