<?php
/**
 * WordPress Settings API Registration Test Script
 * 
 * This script tests if the WordPress Settings API registration fix is working
 * by directly checking the $allowed_options global and running debug tests.
 */

// WordPress headers for direct access
require_once(__DIR__ . '/wp-config.php');
require_once(ABSPATH . 'wp-includes/wp-db.php');
require_once(ABSPATH . 'wp-includes/functions.php');
require_once(ABSPATH . 'wp-includes/option.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

echo "<h1>WordPress Settings API Registration Test</h1>\n";
echo "<pre>\n";

// Test 1: Check if settings group is registered
echo "=== TEST 1: WordPress Settings API Registration Status ===\n";

global $allowed_options;
$settings_group = 'wp_content_flow_settings_group';
$option_name = 'wp_content_flow_settings';

echo "Settings group name: {$settings_group}\n";
echo "Option name: {$option_name}\n\n";

// Force load the WordPress admin environment
if (!is_admin()) {
    define('WP_ADMIN', true);
    require_once(ABSPATH . 'wp-admin/admin.php');
}

// Initialize the plugin settings class to trigger registration
if (class_exists('WP_Content_Flow_Settings_Page')) {
    $settings_page = new WP_Content_Flow_Settings_Page();
    echo "✅ Settings page class instantiated\n";
} else {
    echo "❌ Settings page class not found - loading plugin files...\n";
    
    // Try to load the plugin
    $plugin_file = __DIR__ . '/wp-content-flow/wp-content-flow.php';
    if (file_exists($plugin_file)) {
        require_once($plugin_file);
        echo "✅ Plugin file loaded\n";
        
        if (class_exists('WP_Content_Flow_Settings_Page')) {
            $settings_page = new WP_Content_Flow_Settings_Page();
            echo "✅ Settings page class instantiated after plugin load\n";
        } else {
            echo "❌ Settings page class still not found\n";
        }
    } else {
        echo "❌ Plugin file not found at: {$plugin_file}\n";
    }
}

// Trigger admin_init to force settings registration
echo "\nTriggering admin_init action to force settings registration...\n";
do_action('admin_init');

// Check if the settings group is now in allowed_options
echo "\n=== Checking \$allowed_options global ===\n";
if (isset($allowed_options[$settings_group])) {
    echo "✅ Settings group '{$settings_group}' is registered in \$allowed_options\n";
    echo "Allowed options in group: " . implode(', ', $allowed_options[$settings_group]) . "\n";
    
    if (in_array($option_name, $allowed_options[$settings_group])) {
        echo "✅ Option '{$option_name}' is in the allowed list\n";
    } else {
        echo "❌ Option '{$option_name}' is NOT in the allowed list\n";
    }
} else {
    echo "❌ Settings group '{$settings_group}' is NOT registered in \$allowed_options\n";
    echo "Available groups: " . implode(', ', array_keys($allowed_options)) . "\n";
}

// Test 2: Check current settings values
echo "\n=== TEST 2: Current Settings Values ===\n";

$current_settings = get_option($option_name, array());
if (empty($current_settings)) {
    echo "⚠️  No settings found in database yet\n";
    
    // Set default values for testing
    $default_settings = array(
        'default_ai_provider' => 'openai',
        'cache_enabled' => true,
        'openai_api_key' => '',
        'anthropic_api_key' => '',
        'google_api_key' => '',
        'requests_per_minute' => 10
    );
    
    update_option($option_name, $default_settings);
    echo "✅ Default settings created for testing\n";
    $current_settings = $default_settings;
}

echo "Current settings:\n";
foreach ($current_settings as $key => $value) {
    if (strpos($key, 'api_key') !== false) {
        $display_value = empty($value) ? '[empty]' : '[configured]';
    } else {
        $display_value = is_bool($value) ? ($value ? 'true' : 'false') : $value;
    }
    echo "  {$key}: {$display_value}\n";
}

// Test 3: Simulate settings save
echo "\n=== TEST 3: Settings Save Simulation ===\n";

// Create test data with changed values
$test_settings = $current_settings;
$original_provider = $test_settings['default_ai_provider'];
$test_settings['default_ai_provider'] = ($original_provider === 'openai') ? 'anthropic' : 'openai';
$test_settings['cache_enabled'] = !$test_settings['cache_enabled'];
$test_settings['openai_api_key'] = 'sk-test-key-for-testing';

echo "Original provider: {$original_provider}\n";
echo "New provider: {$test_settings['default_ai_provider']}\n";
echo "Original cache: " . ($current_settings['cache_enabled'] ? 'true' : 'false') . "\n";
echo "New cache: " . ($test_settings['cache_enabled'] ? 'true' : 'false') . "\n";

// Save the test settings
$save_result = update_option($option_name, $test_settings);
echo "update_option() returned: " . ($save_result ? 'true' : 'false') . "\n";

// Verify the save
$verified_settings = get_option($option_name, array());
$save_successful = ($verified_settings == $test_settings);

echo "Save verification: " . ($save_successful ? '✅ SUCCESS' : '❌ FAILED') . "\n";

if ($save_successful) {
    echo "✅ Settings were saved and persist correctly\n";
} else {
    echo "❌ Settings save failed or did not persist\n";
    echo "Expected: " . json_encode($test_settings) . "\n";
    echo "Got: " . json_encode($verified_settings) . "\n";
}

// Test 4: Check WordPress error logs for debug messages
echo "\n=== TEST 4: Recent Error Log Messages ===\n";

$error_log_file = ini_get('error_log');
if (empty($error_log_file)) {
    // Try common WordPress error log locations
    $possible_logs = array(
        ABSPATH . 'wp-content/debug.log',
        ABSPATH . 'error_log',
        '/var/log/apache2/error.log',
        '/var/log/nginx/error.log'
    );
    
    foreach ($possible_logs as $log_file) {
        if (file_exists($log_file)) {
            $error_log_file = $log_file;
            break;
        }
    }
}

if (!empty($error_log_file) && file_exists($error_log_file)) {
    echo "Reading error log: {$error_log_file}\n\n";
    
    // Get last 50 lines of error log
    $log_lines = array();
    $handle = fopen($error_log_file, 'r');
    if ($handle) {
        // Read file in reverse to get recent entries
        fseek($handle, -8192, SEEK_END); // Read last 8KB
        $content = fread($handle, 8192);
        fclose($handle);
        
        $lines = explode("\n", $content);
        $wp_content_flow_lines = array();
        
        foreach ($lines as $line) {
            if (strpos($line, 'WP Content Flow') !== false) {
                $wp_content_flow_lines[] = $line;
            }
        }
        
        if (!empty($wp_content_flow_lines)) {
            echo "Recent WP Content Flow log messages:\n";
            foreach (array_slice($wp_content_flow_lines, -10) as $line) {
                echo "  " . trim($line) . "\n";
            }
        } else {
            echo "⚠️  No recent WP Content Flow messages found in error log\n";
        }
    }
} else {
    echo "⚠️  Could not find error log file\n";
}

// Final Assessment
echo "\n=== FINAL ASSESSMENT ===\n";

$registration_working = isset($allowed_options[$settings_group]) && in_array($option_name, $allowed_options[$settings_group]);
$save_working = $save_successful;

if ($registration_working && $save_working) {
    echo "🎉 OVERALL RESULT: ✅ FIX IS WORKING\n";
    echo "✅ WordPress Settings API registration is working\n";
    echo "✅ Settings save and persistence is working\n";
} else {
    echo "⚠️  OVERALL RESULT: ❌ ISSUES DETECTED\n";
    if (!$registration_working) {
        echo "❌ WordPress Settings API registration is NOT working\n";
    }
    if (!$save_working) {
        echo "❌ Settings save/persistence is NOT working\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "</pre>\n";
?>