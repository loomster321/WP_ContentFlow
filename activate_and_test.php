<?php
/**
 * Activate plugin and test Settings API registration
 */

// WordPress bootstrap  
require_once('/var/www/html/wp-config.php');
require_once('/var/www/html/wp-load.php');
require_once('/var/www/html/wp-admin/includes/plugin.php');

echo "WordPress Settings API Fix Test Results\n";
echo "=====================================\n";

// Step 1: Check plugin status and activate if needed
echo "1. Plugin Status Check...\n";

$plugin_file = 'wp-content-flow/wp-content-flow.php';
if (is_plugin_active($plugin_file)) {
    echo "✅ Plugin is already active\n";
} else {
    echo "⚠️  Plugin is not active - attempting activation...\n";
    
    $result = activate_plugin($plugin_file);
    if (is_wp_error($result)) {
        echo "❌ Plugin activation failed: " . $result->get_error_message() . "\n";
        exit(1);
    } else {
        echo "✅ Plugin activated successfully\n";
    }
}

// Step 2: Force admin context and load settings
echo "\n2. Loading WordPress Settings API...\n";

if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

// Trigger admin init to register settings
do_action('admin_init');

// Step 3: Check if settings class exists and works
echo "\n3. Settings Class Check...\n";

if (class_exists('WP_Content_Flow_Settings_Page')) {
    echo "✅ Settings class found\n";
    
    // Instantiate settings page to trigger registration
    $settings_page = new WP_Content_Flow_Settings_Page();
    echo "✅ Settings class instantiated\n";
    
} else {
    echo "❌ Settings class not found\n";
    echo "Available classes: " . count(get_declared_classes()) . " total\n";
}

// Step 4: Check WordPress Settings API registration
echo "\n4. WordPress Settings API Registration...\n";

global $allowed_options;
$settings_group = 'wp_content_flow_settings_group';
$option_name = 'wp_content_flow_settings';

if (isset($allowed_options[$settings_group])) {
    echo "✅ Settings group '{$settings_group}' is registered\n";
    
    if (in_array($option_name, $allowed_options[$settings_group])) {
        echo "✅ Option '{$option_name}' is in allowed list\n";
    } else {
        echo "❌ Option '{$option_name}' is NOT in allowed list\n";
    }
    
    echo "Registered options: " . implode(', ', $allowed_options[$settings_group]) . "\n";
    
} else {
    echo "❌ Settings group '{$settings_group}' is NOT registered\n";
    echo "Available groups (" . count($allowed_options) . "): " . implode(', ', array_keys($allowed_options)) . "\n";
}

// Step 5: Test settings save/load
echo "\n5. Settings Save/Load Test...\n";

// Get current settings
$current_settings = get_option($option_name, array());

if (empty($current_settings)) {
    echo "⚠️  No existing settings - creating defaults...\n";
    
    $default_settings = array(
        'default_ai_provider' => 'openai',
        'cache_enabled' => true,
        'openai_api_key' => '',
        'anthropic_api_key' => '',
        'google_api_key' => '',
        'requests_per_minute' => 10
    );
    
    update_option($option_name, $default_settings);
    $current_settings = $default_settings;
    echo "✅ Default settings created\n";
}

echo "Current settings:\n";
foreach ($current_settings as $key => $value) {
    $display = is_bool($value) ? ($value ? 'true' : 'false') : $value;
    if (strpos($key, 'api_key') !== false && !empty($value)) {
        $display = '[configured]';
    }
    echo "  {$key}: {$display}\n";
}

// Test changing settings
echo "\n6. Settings Change Test...\n";

$test_settings = $current_settings;
$original_provider = $test_settings['default_ai_provider'];
$new_provider = ($original_provider === 'openai') ? 'anthropic' : 'openai';
$original_cache = $test_settings['cache_enabled'];
$new_cache = !$original_cache;

$test_settings['default_ai_provider'] = $new_provider;
$test_settings['cache_enabled'] = $new_cache;
$test_settings['openai_api_key'] = 'sk-test-api-key-for-validation';

echo "Changing provider: {$original_provider} → {$new_provider}\n";
echo "Changing cache: " . ($original_cache ? 'true' : 'false') . " → " . ($new_cache ? 'true' : 'false') . "\n";

// Save the changes
$save_result = update_option($option_name, $test_settings);
echo "update_option() returned: " . ($save_result ? 'true' : 'false') . "\n";

// Verify the changes persisted
$verified_settings = get_option($option_name);
$provider_persisted = ($verified_settings['default_ai_provider'] === $new_provider);
$cache_persisted = ($verified_settings['cache_enabled'] === $new_cache);

echo "Provider persistence: " . ($provider_persisted ? '✅ YES' : '❌ NO') . "\n";
echo "Cache persistence: " . ($cache_persisted ? '✅ YES' : '❌ NO') . "\n";

// Step 7: Final Assessment
echo "\n7. Final Assessment...\n";

$registration_working = isset($allowed_options[$settings_group]) && in_array($option_name, $allowed_options[$settings_group]);
$persistence_working = $provider_persisted && $cache_persisted;

if ($registration_working && $persistence_working) {
    echo "🎉 OVERALL RESULT: ✅ FIX IS WORKING!\n";
    echo "✅ WordPress Settings API registration: WORKING\n";
    echo "✅ Settings save and persistence: WORKING\n";
    echo "✅ The settings form should now save successfully\n";
} else {
    echo "⚠️  OVERALL RESULT: ❌ ISSUES DETECTED\n";
    
    if (!$registration_working) {
        echo "❌ WordPress Settings API registration: NOT WORKING\n";
    }
    
    if (!$persistence_working) {
        echo "❌ Settings persistence: NOT WORKING\n";
    }
}

echo "\n8. Next Steps...\n";

if ($registration_working && $persistence_working) {
    echo "✅ The fix is working! Try these manual tests:\n";
    echo "  1. Go to: http://localhost:8080/wp-admin/admin.php?page=wp-content-flow-settings\n";
    echo "  2. Change the 'Default AI Provider' dropdown\n";
    echo "  3. Toggle the 'Enable Caching' checkbox\n";
    echo "  4. Click 'Save Settings'\n";
    echo "  5. Verify success message appears\n";
    echo "  6. Reload page and confirm values persist\n";
} else {
    echo "❌ The fix needs more work. Issues to address:\n";
    if (!$registration_working) {
        echo "  - WordPress Settings API registration\n";
    }
    if (!$persistence_working) {
        echo "  - Settings data persistence\n";
    }
}

echo "\n✅ Test Complete!\n";
?>