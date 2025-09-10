<?php
/**
 * WordPress Admin Test Script - Run with proper admin context
 */

// WordPress admin bootstrap - this sets up is_admin() = true
define('WP_ADMIN', true);
define('DOING_AJAX', false);
require_once('/var/www/html/wp-config.php');
require_once('/var/www/html/wp-load.php');
require_once('/var/www/html/wp-admin/includes/admin.php');
require_once('/var/www/html/wp-admin/includes/plugin.php');

// Content type for proper CLI output
if (php_sapi_name() === 'cli') {
    // CLI mode
    echo "WordPress Settings API Fix Test - Admin Context\n";
    echo "===============================================\n";
} else {
    // Web mode
    header('Content-Type: text/plain');
    echo "WordPress Settings API Fix Test - Admin Context\n";
    echo "===============================================\n";
}

// Step 1: Verify admin context
echo "1. Admin Context Verification:\n";
echo "   is_admin(): " . (is_admin() ? 'YES' : 'NO') . "\n";
echo "   WP_ADMIN defined: " . (defined('WP_ADMIN') ? 'YES' : 'NO') . "\n";
echo "   Current user can manage_options: " . (current_user_can('manage_options') ? 'YES' : 'NO') . "\n";

// Step 2: Check plugin activation
echo "\n2. Plugin Status:\n";
$plugin_file = 'wp-content-flow/wp-content-flow.php';

if (is_plugin_active($plugin_file)) {
    echo "   ✅ Plugin is active\n";
    
    // Trigger the plugin initialization manually to ensure everything loads
    if (class_exists('WP_Content_Flow')) {
        $plugin_instance = WP_Content_Flow::get_instance();
        echo "   ✅ Plugin main class loaded\n";
    }
    
} else {
    echo "   ❌ Plugin is not active\n";
    echo "   Attempting to activate plugin...\n";
    
    $result = activate_plugin($plugin_file);
    if (is_wp_error($result)) {
        echo "   ❌ Activation failed: " . $result->get_error_message() . "\n";
    } else {
        echo "   ✅ Plugin activated successfully\n";
    }
}

// Step 3: Trigger WordPress admin initialization
echo "\n3. WordPress Admin Initialization:\n";

// Manually trigger all the hooks that WordPress would normally trigger
echo "   Triggering admin_init action...\n";
do_action('admin_init');

echo "   Triggering admin_menu action...\n";  
do_action('admin_menu');

// Step 4: Check if settings class is loaded
echo "\n4. Settings Class Status:\n";

if (class_exists('WP_Content_Flow_Settings_Page')) {
    echo "   ✅ Settings class is available\n";
    
    // The settings page should now be instantiated by the admin menu class
    echo "   ✅ Settings should be registered via admin menu class\n";
    
} else {
    echo "   ❌ Settings class is not available\n";
    
    // Try to manually load it
    $settings_file = '/var/www/html/wp-content/plugins/wp-content-flow/includes/admin/class-settings-page.php';
    if (file_exists($settings_file)) {
        require_once($settings_file);
        echo "   ✅ Settings class loaded manually\n";
    } else {
        echo "   ❌ Settings class file not found: $settings_file\n";
    }
}

// Step 5: Check WordPress Settings API registration
echo "\n5. WordPress Settings API Check:\n";

global $allowed_options;
$settings_group = 'wp_content_flow_settings_group';
$option_name = 'wp_content_flow_settings';

echo "   Settings group: $settings_group\n";
echo "   Option name: $option_name\n";

if (isset($allowed_options) && is_array($allowed_options)) {
    if (isset($allowed_options[$settings_group])) {
        echo "   ✅ Settings group is registered in \$allowed_options\n";
        
        if (in_array($option_name, $allowed_options[$settings_group])) {
            echo "   ✅ Option '$option_name' is in the allowed list\n";
            echo "   📋 Allowed options: " . implode(', ', $allowed_options[$settings_group]) . "\n";
        } else {
            echo "   ❌ Option '$option_name' is NOT in the allowed list\n";
            echo "   📋 Current allowed options: " . implode(', ', $allowed_options[$settings_group]) . "\n";
        }
        
    } else {
        echo "   ❌ Settings group '$settings_group' is NOT registered\n";
        echo "   📋 Available groups (" . count($allowed_options) . "): " . implode(', ', array_keys($allowed_options)) . "\n";
    }
} else {
    echo "   ❌ \$allowed_options is not properly initialized\n";
}

// Step 6: Test settings functionality
echo "\n6. Settings Functionality Test:\n";

// Check current settings
$current_settings = get_option($option_name, array());

if (empty($current_settings)) {
    echo "   ⚠️  No settings found - creating test settings...\n";
    
    $test_settings = array(
        'default_ai_provider' => 'openai',
        'cache_enabled' => true,
        'openai_api_key' => 'sk-test-key',
        'anthropic_api_key' => '',
        'google_api_key' => '',
        'requests_per_minute' => 10
    );
    
    update_option($option_name, $test_settings);
    $current_settings = $test_settings;
    echo "   ✅ Test settings created\n";
}

echo "   📋 Current settings:\n";
foreach ($current_settings as $key => $value) {
    $display = is_bool($value) ? ($value ? 'true' : 'false') : $value;
    if (strpos($key, 'api_key') !== false && !empty($value)) {
        $display = '[has value]';
    } elseif (strpos($key, 'api_key') !== false) {
        $display = '[empty]';
    }
    echo "      $key: $display\n";
}

// Test settings change
echo "\n7. Settings Change Test:\n";

$original_provider = $current_settings['default_ai_provider'] ?? 'openai';
$new_provider = ($original_provider === 'openai') ? 'anthropic' : 'openai';
$original_cache = $current_settings['cache_enabled'] ?? true;
$new_cache = !$original_cache;

$updated_settings = $current_settings;
$updated_settings['default_ai_provider'] = $new_provider;
$updated_settings['cache_enabled'] = $new_cache;

echo "   Changing provider: $original_provider → $new_provider\n";
echo "   Changing cache: " . ($original_cache ? 'true' : 'false') . " → " . ($new_cache ? 'true' : 'false') . "\n";

$save_result = update_option($option_name, $updated_settings);
echo "   update_option result: " . ($save_result ? 'SUCCESS' : 'FAILED') . "\n";

// Verify persistence
$verified_settings = get_option($option_name);
$provider_persisted = ($verified_settings['default_ai_provider'] === $new_provider);
$cache_persisted = ($verified_settings['cache_enabled'] === $new_cache);

echo "   Provider persistence: " . ($provider_persisted ? '✅ YES' : '❌ NO') . "\n";
echo "   Cache persistence: " . ($cache_persisted ? '✅ YES' : '❌ NO') . "\n";

// Step 8: Final Assessment
echo "\n8. Final Assessment:\n";

$registration_ok = isset($allowed_options[$settings_group]) && in_array($option_name, $allowed_options[$settings_group]);
$functionality_ok = $provider_persisted && $cache_persisted;

if ($registration_ok && $functionality_ok) {
    echo "   🎉 OVERALL STATUS: ✅ FIX IS WORKING!\n";
    echo "   ✅ WordPress Settings API registration: WORKING\n";
    echo "   ✅ Settings save and persistence: WORKING\n";
    echo "   ✅ Form should now work correctly in the admin\n";
    
    echo "\n   📝 Manual Test Instructions:\n";
    echo "   1. Visit: http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings\n";
    echo "   2. Change dropdown and checkbox values\n";
    echo "   3. Click 'Save Settings'\n";
    echo "   4. Look for success message\n";
    echo "   5. Reload page to confirm persistence\n";
    
} else {
    echo "   ⚠️  OVERALL STATUS: ❌ ISSUES DETECTED\n";
    
    if (!$registration_ok) {
        echo "   ❌ WordPress Settings API registration: FAILED\n";
    } else {
        echo "   ✅ WordPress Settings API registration: OK\n";
    }
    
    if (!$functionality_ok) {
        echo "   ❌ Settings functionality: FAILED\n";
    } else {
        echo "   ✅ Settings functionality: OK\n";
    }
    
    echo "\n   🔧 Issues to investigate:\n";
    if (!$registration_ok) {
        echo "   - Settings group not in \$allowed_options\n";
    }
    if (!$functionality_ok) {
        echo "   - Settings not persisting correctly\n";
    }
}

echo "\n✅ Test Complete!\n";
?>