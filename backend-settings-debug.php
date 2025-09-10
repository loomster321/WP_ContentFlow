<?php
/**
 * WordPress Content Flow Backend Settings Debug
 * 
 * This script tests the WordPress settings functionality directly
 * to isolate backend issues from frontend issues.
 */

// WordPress environment setup
$wp_path = '/var/www/html';
if (file_exists($wp_path . '/wp-config.php')) {
    require_once $wp_path . '/wp-config.php';
    require_once $wp_path . '/wp-load.php';
} else {
    die("WordPress not found at $wp_path\n");
}

echo "🧪 WORDPRESS CONTENT FLOW BACKEND SETTINGS DEBUG\n";
echo "================================================\n\n";

// Test 1: Check if plugin is active
echo "📋 Test 1: Plugin Activation Status\n";
echo "-----------------------------------\n";

$active_plugins = get_option('active_plugins', array());
$plugin_active = in_array('wp-content-flow/wp-content-flow.php', $active_plugins);

echo "Plugin Active: " . ($plugin_active ? "✅ YES" : "❌ NO") . "\n";
if (!$plugin_active) {
    echo "⚠️  Plugin is not active - activating now...\n";
    activate_plugin('wp-content-flow/wp-content-flow.php');
    $plugin_active = true;
}
echo "\n";

// Test 2: Check current settings
echo "📋 Test 2: Current Settings State\n";
echo "---------------------------------\n";

$option_name = 'wp_content_flow_settings';
$current_settings = get_option($option_name, array());

echo "Settings Option Name: $option_name\n";
echo "Current Settings: " . json_encode($current_settings, JSON_PRETTY_PRINT) . "\n";
echo "Settings Empty: " . (empty($current_settings) ? "YES" : "NO") . "\n\n";

// Test 3: WordPress Settings API status
echo "📋 Test 3: WordPress Settings API Registration\n";
echo "----------------------------------------------\n";

global $wp_settings_sections, $wp_settings_fields, $allowed_options;

echo "WordPress Settings Sections:\n";
if (isset($wp_settings_sections['wp-content-flow'])) {
    echo "✅ Plugin sections registered\n";
    print_r($wp_settings_sections['wp-content-flow']);
} else {
    echo "❌ No plugin sections found\n";
}

echo "\nWordPress Settings Fields:\n";
if (isset($wp_settings_fields['wp-content-flow'])) {
    echo "✅ Plugin fields registered\n";
    foreach ($wp_settings_fields['wp-content-flow'] as $section => $fields) {
        echo "  Section: $section\n";
        foreach ($fields as $field_id => $field) {
            echo "    Field: $field_id\n";
        }
    }
} else {
    echo "❌ No plugin fields found\n";
}

echo "\nAllowed Options:\n";
$settings_group = 'wp_content_flow_settings_group';
if (isset($allowed_options[$settings_group])) {
    echo "✅ Plugin options group registered: $settings_group\n";
    echo "   Allowed options: " . implode(', ', $allowed_options[$settings_group]) . "\n";
} else {
    echo "❌ Plugin options group not found: $settings_group\n";
}
echo "\n";

// Test 4: Simulate settings save
echo "📋 Test 4: Simulate Settings Save\n";
echo "---------------------------------\n";

$test_settings = array(
    'openai_api_key' => 'sk-test-backend-debug-' . time(),
    'default_ai_provider' => 'anthropic',
    'cache_enabled' => false,
    'requests_per_minute' => 15
);

echo "Test settings to save:\n";
echo json_encode($test_settings, JSON_PRETTY_PRINT) . "\n";

// Save using update_option directly
$save_result = update_option($option_name, $test_settings);
echo "Direct update_option result: " . ($save_result ? "✅ SUCCESS" : "❌ FAILED") . "\n";

// Verify the save
$verified_settings = get_option($option_name, array());
$save_verified = ($verified_settings == $test_settings);
echo "Save verification: " . ($save_verified ? "✅ SUCCESS" : "❌ FAILED") . "\n";

if (!$save_verified) {
    echo "Expected: " . json_encode($test_settings, JSON_PRETTY_PRINT) . "\n";
    echo "Got: " . json_encode($verified_settings, JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Test 5: Simulate WordPress Settings API save
echo "📋 Test 5: WordPress Settings API Save Simulation\n";
echo "---------------------------------------------------\n";

// Force register settings first
if (class_exists('WP_Content_Flow_Settings_Page')) {
    $settings_page = new WP_Content_Flow_Settings_Page();
    
    // Simulate admin_init
    do_action('admin_init');
    
    echo "Settings page class instantiated\n";
    
    // Check if settings are now registered
    if (isset($allowed_options[$settings_group])) {
        echo "✅ Settings registered through Settings API\n";
        
        // Simulate form submission
        $_POST = array(
            'option_page' => $settings_group,
            '_wpnonce' => wp_create_nonce($settings_group . '-options'),
            $option_name => array(
                'openai_api_key' => 'sk-test-api-save-' . time(),
                'default_ai_provider' => 'google',
                'cache_enabled' => true,
                'requests_per_minute' => 20
            )
        );
        
        echo "Simulated POST data prepared\n";
        
        // Test nonce verification
        $nonce_valid = wp_verify_nonce($_POST['_wpnonce'], $settings_group . '-options');
        echo "Nonce verification: " . ($nonce_valid ? "✅ VALID" : "❌ INVALID") . "\n";
        
        // Test capability
        $can_manage = current_user_can('manage_options');
        echo "User capability: " . ($can_manage ? "✅ CAN MANAGE" : "❌ CANNOT MANAGE") . "\n";
        
    } else {
        echo "❌ Settings still not registered\n";
    }
} else {
    echo "❌ Settings page class not found\n";
}
echo "\n";

// Test 6: Database direct access
echo "📋 Test 6: Database Direct Access\n";
echo "---------------------------------\n";

global $wpdb;

$option_row = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->options} WHERE option_name = %s",
    $option_name
));

if ($option_row) {
    echo "✅ Option found in database\n";
    echo "Option ID: {$option_row->option_id}\n";
    echo "Option Name: {$option_row->option_name}\n";
    echo "Option Value: {$option_row->option_value}\n";
    echo "Autoload: {$option_row->autoload}\n";
} else {
    echo "❌ Option not found in database\n";
}
echo "\n";

// Test 7: Check for WordPress errors
echo "📋 Test 7: WordPress Error Check\n";
echo "--------------------------------\n";

if (WP_DEBUG) {
    echo "✅ WordPress Debug Mode: ENABLED\n";
} else {
    echo "⚠️  WordPress Debug Mode: DISABLED\n";
}

$error_log_path = ini_get('error_log');
echo "PHP Error Log: $error_log_path\n";

if (file_exists($error_log_path)) {
    $recent_errors = shell_exec("tail -20 $error_log_path | grep -i 'wp.content.flow' || echo 'No recent plugin errors found'");
    echo "Recent Plugin Errors:\n$recent_errors\n";
} else {
    echo "Error log file not accessible\n";
}
echo "\n";

// Test 8: Plugin classes and hooks
echo "📋 Test 8: Plugin Classes and Hooks\n";
echo "-----------------------------------\n";

$classes_to_check = array(
    'WP_Content_Flow',
    'WP_Content_Flow_Settings_Page',
    'WP_Content_Flow_Admin_Menu'
);

foreach ($classes_to_check as $class_name) {
    if (class_exists($class_name)) {
        echo "✅ Class exists: $class_name\n";
    } else {
        echo "❌ Class missing: $class_name\n";
    }
}

// Check admin_init hooks
$admin_init_hooks = $GLOBALS['wp_filter']['admin_init'] ?? new stdClass();
$admin_init_count = isset($admin_init_hooks->callbacks) ? count($admin_init_hooks->callbacks) : 0;
echo "Admin init hooks registered: $admin_init_count\n";
echo "\n";

// Test 9: Final recommendations
echo "📋 Test 9: Analysis and Recommendations\n";
echo "---------------------------------------\n";

$issues_found = array();

if (!$plugin_active) {
    $issues_found[] = "Plugin not activated";
}

if (empty($current_settings)) {
    $issues_found[] = "No settings saved in database";
}

if (!isset($allowed_options[$settings_group])) {
    $issues_found[] = "WordPress Settings API not properly registered";
}

if (!class_exists('WP_Content_Flow_Settings_Page')) {
    $issues_found[] = "Settings page class not loaded";
}

if (empty($issues_found)) {
    echo "✅ No major issues detected\n";
    echo "The settings persistence issue may be frontend-related:\n";
    echo "  1. JavaScript form handling\n";
    echo "  2. WordPress admin form processing\n";
    echo "  3. Caching or race conditions\n";
    echo "  4. User interface refresh issues\n";
} else {
    echo "❌ Issues found:\n";
    foreach ($issues_found as $issue) {
        echo "  • $issue\n";
    }
}

echo "\n📊 BACKEND DEBUG SUMMARY:\n";
echo "=========================\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Database accessible: " . ($wpdb->last_error ? "NO" : "YES") . "\n";
echo "Settings in database: " . (!empty($current_settings) ? "YES" : "NO") . "\n";
echo "WordPress Settings API: " . (isset($allowed_options[$settings_group]) ? "REGISTERED" : "NOT REGISTERED") . "\n";

echo "\n🔧 NEXT STEPS:\n";
if (empty($issues_found)) {
    echo "Backend appears functional. Focus on frontend testing:\n";
    echo "1. Use manual-browser-debug-test.html for frontend testing\n";
    echo "2. Check browser console for JavaScript errors\n";
    echo "3. Monitor network requests during form submission\n";
    echo "4. Test with different browsers\n";
} else {
    echo "Fix backend issues first:\n";
    echo "1. Ensure plugin is properly activated\n";
    echo "2. Check WordPress Settings API registration\n";
    echo "3. Verify admin class loading\n";
    echo "4. Check for PHP errors\n";
}

?>