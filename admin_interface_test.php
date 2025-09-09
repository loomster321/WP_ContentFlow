<?php
/**
 * Admin Interface E2E Test
 * Tests actual WordPress admin interface content and functionality
 */

// WordPress bootstrap with admin context
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require_once('/var/www/html/wp-config.php');

// Include admin functions
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// Set up admin user context
wp_set_current_user(1); // Admin user

echo "=== WordPress AI Content Flow - Admin Interface Test ===\n";

/**
 * Test 1: Plugin Menu Registration
 */
echo "\n🧪 TEST 1: Admin Menu Registration\n";

global $menu, $submenu;

// Trigger menu registration
do_action('admin_init');
do_action('admin_menu');

// Check main menu
$content_flow_menu_found = false;
if (is_array($menu)) {
    foreach ($menu as $menu_item) {
        if (isset($menu_item[2]) && $menu_item[2] === 'wp-content-flow') {
            $content_flow_menu_found = true;
            echo "✅ Main menu found: " . $menu_item[0] . "\n";
            echo "   Capability: " . $menu_item[1] . "\n";
            echo "   Slug: " . $menu_item[2] . "\n";
            break;
        }
    }
}

if (!$content_flow_menu_found) {
    echo "❌ Main menu not found\n";
} 

// Check submenus
if (isset($submenu['wp-content-flow'])) {
    echo "✅ Submenus found:\n";
    foreach ($submenu['wp-content-flow'] as $submenu_item) {
        echo "   - " . $submenu_item[0] . " (" . $submenu_item[2] . ")\n";
    }
} else {
    echo "❌ No submenus found\n";
}

/**
 * Test 2: Settings Page Content
 */
echo "\n🧪 TEST 2: Settings Page Content\n";

// Create settings page instance
if (class_exists('WP_Content_Flow_Settings_Page')) {
    $settings_page = new WP_Content_Flow_Settings_Page();
    
    // Test settings registration
    do_action('admin_init');
    
    // Check if settings are registered
    global $wp_settings_sections, $wp_settings_fields;
    
    if (isset($wp_settings_sections['wp-content-flow'])) {
        echo "✅ Settings sections registered:\n";
        foreach ($wp_settings_sections['wp-content-flow'] as $section_id => $section) {
            echo "   - " . $section['title'] . " ($section_id)\n";
        }
    } else {
        echo "❌ No settings sections found\n";
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
        echo "❌ No settings fields found\n";
    }
    
    // Test settings page output
    echo "\n📄 Settings Page Output Test:\n";
    ob_start();
    $settings_page->render();
    $settings_output = ob_get_clean();
    
    if (!empty($settings_output)) {
        echo "✅ Settings page generates content (" . strlen($settings_output) . " characters)\n";
        
        // Check for key elements
        $checks = [
            'WP Content Flow Settings' => strpos($settings_output, 'WP Content Flow Settings') !== false,
            'form tag' => strpos($settings_output, '<form') !== false,
            'settings fields' => strpos($settings_output, 'settings_fields') !== false,
            'submit button' => strpos($settings_output, 'submit_button') !== false || strpos($settings_output, 'type="submit"') !== false,
        ];
        
        foreach ($checks as $element => $found) {
            echo ($found ? "✅" : "❌") . " Contains: $element\n";
        }
    } else {
        echo "❌ Settings page generates no content\n";
    }
} else {
    echo "❌ Settings page class not found\n";
}

/**
 * Test 3: Dashboard Page Content  
 */
echo "\n🧪 TEST 3: Dashboard Page Content\n";

if (class_exists('WP_Content_Flow_Admin_Menu')) {
    $admin_menu = WP_Content_Flow_Admin_Menu::get_instance();
    
    // Test dashboard page output
    echo "📄 Dashboard Page Output Test:\n";
    ob_start();
    $admin_menu->render_dashboard_page();
    $dashboard_output = ob_get_clean();
    
    if (!empty($dashboard_output)) {
        echo "✅ Dashboard page generates content (" . strlen($dashboard_output) . " characters)\n";
        
        // Check for key elements
        $checks = [
            'wrap div' => strpos($dashboard_output, 'class="wrap"') !== false,
            'dashboard heading' => strpos($dashboard_output, 'Dashboard') !== false,
            'plugin name' => strpos($dashboard_output, 'Content Flow') !== false,
        ];
        
        foreach ($checks as $element => $found) {
            echo ($found ? "✅" : "❌") . " Contains: $element\n";
        }
        
        // Show actual content preview
        echo "\n📋 Dashboard Content Preview:\n";
        echo "---\n" . substr(strip_tags($dashboard_output), 0, 200) . "...\n---\n";
        
    } else {
        echo "❌ Dashboard page generates no content\n";
    }
} else {
    echo "❌ Admin menu class not found\n";
}

/**
 * Test 4: Current Settings Values
 */
echo "\n🧪 TEST 4: Current Settings Values\n";

$settings = get_option('wp_content_flow_settings', array());
if (!empty($settings)) {
    echo "✅ Settings exist:\n";
    foreach ($settings as $key => $value) {
        if (strpos($key, 'api_key') !== false) {
            echo "   $key: " . (empty($value) ? 'NOT SET' : 'SET (****)') . "\n";
        } else {
            echo "   $key: $value\n";
        }
    }
} else {
    echo "❌ No settings found in database\n";
}

/**
 * Test 5: Admin Assets and Scripts
 */
echo "\n🧪 TEST 5: Admin Assets and Scripts\n";

// Test if admin assets exist
$admin_js_path = WP_CONTENT_FLOW_PLUGIN_DIR . 'assets/js/admin.js';
$admin_css_path = WP_CONTENT_FLOW_PLUGIN_DIR . 'assets/css/admin.css';

echo (file_exists($admin_js_path) ? "✅" : "❌") . " Admin JS file exists\n";
echo (file_exists($admin_css_path) ? "❌" : "⚠️ ") . " Admin CSS file " . (file_exists($admin_css_path) ? "exists" : "missing (optional)") . "\n";

/**
 * Test 6: Capabilities and Permissions
 */
echo "\n🧪 TEST 6: User Capabilities\n";

$current_user = wp_get_current_user();
echo "Current user: " . $current_user->user_login . " (ID: " . $current_user->ID . ")\n";

$capabilities_to_check = [
    'manage_options',
    'manage_ai_content',
    'manage_ai_settings', 
    'manage_ai_workflows'
];

foreach ($capabilities_to_check as $cap) {
    $has_cap = current_user_can($cap);
    echo ($has_cap ? "✅" : "❌") . " Capability: $cap\n";
}

/**
 * Final Analysis
 */
echo "\n" . str_repeat("=", 60) . "\n";
echo "🔍 ADMIN INTERFACE ANALYSIS COMPLETE\n";
echo str_repeat("=", 60) . "\n";

echo "\n📊 **Issue Analysis:**\n";
echo "If subpanels are empty, the likely causes are:\n";
echo "1. Settings sections/fields not registering properly\n";
echo "2. Admin init hooks not firing correctly\n"; 
echo "3. Settings page render method not generating content\n";
echo "4. Missing form elements or WordPress settings API issues\n";

echo "\n🔧 **Recommended Actions:**\n";
echo "1. Check settings registration in admin_init hook\n";
echo "2. Verify settings page render method outputs HTML\n";
echo "3. Ensure WordPress settings API is used correctly\n";
echo "4. Test with WordPress admin user logged in\n";

?>