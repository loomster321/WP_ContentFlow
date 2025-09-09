<?php
/**
 * Test Admin Settings in proper WordPress context
 * This simulates the actual WordPress admin request flow
 */

// Simulate WordPress admin request
$_SERVER['REQUEST_URI'] = '/wp-admin/options.php';
$_GET['page'] = 'wp-content-flow-settings';

// WordPress bootstrap with admin context
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');

// Load WordPress admin
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// Set up admin user
wp_set_current_user(1);

// Set current screen to simulate settings page
set_current_screen('toplevel_page_wp-content-flow-settings');

echo "=== WordPress Admin Settings Test ===\n";

echo "1. Checking WordPress admin functions:\n";
if (function_exists('register_setting')) {
    echo "✅ register_setting() function available\n";
} else {
    echo "❌ register_setting() function not available\n";
}

if (function_exists('add_settings_section')) {
    echo "✅ add_settings_section() function available\n";
} else {
    echo "❌ add_settings_section() function not available\n";
}

echo "\n2. Plugin initialization:\n";
if (class_exists('WP_Content_Flow_Admin_Menu')) {
    $admin_menu = WP_Content_Flow_Admin_Menu::get_instance();
    echo "✅ Admin menu instance created\n";
} else {
    echo "❌ Admin menu class not found\n";
    exit(1);
}

echo "\n3. Triggering admin_init hook:\n";
do_action('admin_init');
echo "✅ admin_init hook triggered\n";

echo "\n4. Checking settings registration:\n";
global $wp_settings_sections, $wp_settings_fields, $allowed_options;

if (isset($wp_settings_sections['wp-content-flow'])) {
    echo "✅ Settings sections registered:\n";
    foreach ($wp_settings_sections['wp-content-flow'] as $section_id => $section) {
        echo "   - " . $section['title'] . " ($section_id)\n";
    }
} else {
    echo "❌ Settings sections not found\n";
}

if (isset($wp_settings_fields['wp-content-flow'])) {
    echo "✅ Settings fields registered:\n";
    foreach ($wp_settings_fields['wp-content-flow'] as $section_id => $fields) {
        foreach ($fields as $field_id => $field) {
            echo "   - " . $field['title'] . " ($field_id)\n";
        }
    }
} else {
    echo "❌ Settings fields not found\n";
}

if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ Settings group in allowed options:\n";
    foreach ($allowed_options['wp_content_flow_settings_group'] as $option) {
        echo "   - $option\n";
    }
} else {
    echo "❌ Settings group not in allowed options\n";
    if (isset($allowed_options)) {
        echo "Available option groups:\n";
        foreach (array_keys($allowed_options) as $group) {
            echo "   - $group\n";
        }
    }
}

echo "\n🎯 **Final Status:**\n";
if (isset($allowed_options['wp_content_flow_settings_group'])) {
    echo "✅ Settings registration is working! You should now be able to save API keys.\n";
} else {
    echo "❌ Settings registration still has issues.\n";
}

echo "\n📋 **Next Steps:**\n";
echo "1. Try saving your API keys in WordPress admin\n";
echo "2. If you still get errors, deactivate and reactivate the plugin\n";
echo "3. Check the WordPress error log for any additional issues\n";

?>