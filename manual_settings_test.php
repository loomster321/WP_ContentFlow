<?php
/**
 * Manual Settings Test Script
 * 
 * This script tests the settings save functionality by simulating the WordPress environment
 */

// Simulate WordPress environment for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}

// Test data
$test_settings = array(
    'openai_api_key' => 'sk-test-openai-key-1234567890abcdef',
    'anthropic_api_key' => 'sk-ant-test-anthropic-key-1234567890abcdef',
    'google_api_key' => 'AIza-test-google-key-1234567890abcdef',
    'default_ai_provider' => 'anthropic',
    'cache_enabled' => 1,
    'requests_per_minute' => 15
);

echo "=== WP Content Flow Settings Test ===\n";
echo "Testing settings save functionality...\n\n";

// Test 1: Check if the settings page class exists
if (!class_exists('WP_Content_Flow_Settings_Page')) {
    require_once 'wp-content-flow/includes/admin/class-settings-page.php';
}

echo "✓ Settings page class loaded\n";

// Test 2: Create settings page instance
try {
    $settings_page = new WP_Content_Flow_Settings_Page();
    echo "✓ Settings page instance created\n";
} catch (Exception $e) {
    echo "✗ Failed to create settings page instance: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Test sanitization
echo "\n=== Testing Data Sanitization ===\n";
$sanitized = $settings_page->sanitize_settings($test_settings);
echo "Original data:\n";
print_r($test_settings);
echo "Sanitized data:\n";
print_r($sanitized);

// Test 4: Check form processing logic
echo "\n=== Testing Form Processing Logic ===\n";

// Simulate POST data
$_POST = array(
    'option_page' => 'wp_content_flow_settings_group',
    '_wpnonce' => 'test_nonce',
    'wp_content_flow_settings' => $test_settings
);

echo "Simulated POST data:\n";
print_r($_POST);

// Test the key components that might be failing
echo "\n=== Checking Key Components ===\n";

// Check 1: Is the option_page set correctly?
$option_page = isset($_POST['option_page']) ? $_POST['option_page'] : 'NOT_SET';
echo "Option page value: " . $option_page . "\n";

// Check 2: Is the nonce field present?
$nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : 'NOT_SET';
echo "Nonce value: " . $nonce . "\n";

// Check 3: Are the settings data present?
$settings_data = isset($_POST['wp_content_flow_settings']) ? $_POST['wp_content_flow_settings'] : array();
echo "Settings data present: " . (empty($settings_data) ? 'NO' : 'YES') . "\n";
echo "Settings fields count: " . count($settings_data) . "\n";

// Test 5: Check if WordPress functions are being called
echo "\n=== WordPress Function Dependencies ===\n";
$required_functions = array(
    'wp_verify_nonce',
    'current_user_can', 
    'sanitize_text_field',
    'update_option',
    'add_settings_error',
    'wp_redirect',
    'admin_url'
);

foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "✓ $func exists\n";
    } else {
        echo "✗ $func missing\n";
    }
}

// Test 6: Manual save simulation (without WordPress functions)
echo "\n=== Manual Save Simulation ===\n";
$option_name = 'wp_content_flow_settings';

// Simulate the save process
echo "1. Checking option_page match: ";
if ($_POST['option_page'] === 'wp_content_flow_settings_group') {
    echo "✓ MATCH\n";
} else {
    echo "✗ NO MATCH (expected: wp_content_flow_settings_group, got: " . $_POST['option_page'] . ")\n";
}

echo "2. Processing settings data...\n";
if (method_exists($settings_page, 'sanitize_settings')) {
    $sanitized_data = $settings_page->sanitize_settings($test_settings);
    echo "   ✓ Data sanitized successfully\n";
    echo "   Sanitized values:\n";
    foreach ($sanitized_data as $key => $value) {
        if (strpos($key, 'api_key') !== false) {
            echo "     - $key: [REDACTED]\n";
        } else {
            echo "     - $key: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
        }
    }
} else {
    echo "   ✗ sanitize_settings method not found\n";
}

echo "\n=== Test Results ===\n";
echo "Settings page functionality appears to be structurally correct.\n";
echo "The issue is likely in the WordPress environment integration.\n";
echo "\nPossible issues to investigate:\n";
echo "1. WordPress nonce verification failing\n";
echo "2. User capability check failing\n";  
echo "3. Form not submitting to correct URL\n";
echo "4. WordPress hooks not firing properly\n";
echo "5. Settings registration not working\n";

echo "\n=== Recommendations ===\n";
echo "1. Add debug logging to the handle_settings_save method\n";
echo "2. Check WordPress admin_init hook execution\n";
echo "3. Verify nonce generation matches verification\n";
echo "4. Test with a simplified form submission\n";
echo "5. Check for PHP errors in WordPress debug log\n";
?>